<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Order;
use App\Models\User;
use App\Models\VpnPlan;
use App\Models\Subscription;
use App\Models\Server;
use App\Models\NodeTask;
use App\Models\Payment;
use App\Services\PaymentService;

class OrderController extends BaseController
{
    private Order $orderModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
            $this->redirect('/login');
        }
        $this->orderModel = new Order();
    }

    /**
     * Hàm phụ trợ: Giải mã mảng JSON hoặc số nguyên của group_id thành mảng danh sách ID nhóm máy chủ
     */
    private function parseGroupIds(mixed $rawGroupId): array
    {
        if (is_array($rawGroupId)) {
            $ids = $rawGroupId;
        } else {
            $ids = json_decode((string)$rawGroupId, true);
            if (!is_array($ids)) {
                $ids = !empty($rawGroupId) ? [(int)$rawGroupId] : [];
            }
        }
        return array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
    }

    /**
     * Hàm phụ trợ: Tự động tạo task 'add_user' cho các VPS thuộc đúng các Nhóm Máy Chủ (group_ids) của gói cước
     */
    private function dispatchAddUserTask(int $subId, string $uuid, int $bytesTotal, string $endDate, array $groupIds): void
    {
        if (empty($groupIds)) return;

        $serverModel = new Server();
        $servers = $serverModel->getAll();

        if (empty($servers)) {
            return;
        }

        $taskModel = new NodeTask();
        $payload = [
            'username'        => 'sub_' . $subId,
            'uuid'            => $uuid,
            'transfer_enable' => $bytesTotal,
            'end_date'        => $endDate
        ];

        foreach ($servers as $server) {
            $serverId = (int)($server['id'] ?? 0);
            $serverGroupId = (int)($server['group_id'] ?? 0);
            $status = $server['status'] ?? 'active';

            if ($status === 'active' && in_array($serverGroupId, $groupIds, true)) {
                $taskModel->create([
                    'server_id' => $serverId,
                    'action'    => 'add_user',
                    'payload'   => $payload
                ]);
            }
        }
    }

    /**
     * Hàm phụ trợ: Tự động tạo task 'delete_user' gửi xuống VPS khi hủy đơn hàng
     */
    private function dispatchDelUserTask(int $subId, array $groupIds): void
    {
        if (empty($groupIds)) return;

        $serverModel = new Server();
        $servers = $serverModel->getAll();

        if (empty($servers)) {
            return;
        }

        $taskModel = new NodeTask();
        $payload = [
            'username' => 'sub_' . $subId
        ];

        foreach ($servers as $server) {
            $serverId = (int)($server['id'] ?? 0);
            $serverGroupId = (int)($server['group_id'] ?? 0);
            $status = $server['status'] ?? 'active';

            if ($status === 'active' && in_array($serverGroupId, $groupIds, true)) {
                $taskModel->create([
                    'server_id' => $serverId,
                    'action'    => 'delete_user',
                    'payload'   => $payload
                ]);
            }
        }
    }

    public function index(): void
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $orders = $this->orderModel->allWithDetails($userId);

        $filterUser = null;
        if ($userId && class_exists('App\Models\User')) {
            $userModel = new User();
            $filterUser = $userModel->findById($userId);
        }

        $this->render('admin.orders.index', [
            'activeMenu' => 'orders',
            'orders'     => $orders,
            'filterUser' => $filterUser,
            'userId'     => $userId
        ]);
    }

    public function updateStatus(): void
    {
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $status = $_POST['status'] ?? $_GET['status'] ?? '';
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_message'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
            return;
        }

        $validStatuses = ['completed', 'pending', 'failed', 'cancelled'];

        if ($id <= 0 || !in_array($status, $validStatuses, true)) {
            $_SESSION['flash_message'] = 'Yêu cầu không hợp lệ!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        $order = $this->orderModel->find($id);
        if (!$order) {
            $_SESSION['flash_message'] = 'Đơn hàng không tồn tại!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        if (($_SESSION['role'] ?? '') === 'staff'
            && ($status !== 'completed' || ($order['payment_status'] ?? '') !== 'pending')) {
            $_SESSION['flash_message'] = 'Nhân viên chỉ được duyệt đơn hàng đang chờ thanh toán.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
            return;
        }

        if ($status === 'cancelled' && ($order['payment_status'] ?? '') !== 'pending') {
            $_SESSION['flash_message'] = 'Chỉ có thể hủy đơn hàng đang chờ thanh toán.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
            return;
        }

        $renewalPayment = $status === 'completed'
            ? (new Payment())->findPendingRenewalByOrderId($id)
            : null;
        if ($renewalPayment !== null) {
            $completed = (new PaymentService())->completePayment((int) $renewalPayment['id']);
            $_SESSION['flash_message'] = $completed
                ? 'Đã duyệt đơn gia hạn và cập nhật thời hạn gói dịch vụ.'
                : 'Không thể duyệt đơn gia hạn này.';
            $_SESSION['flash_type'] = $completed ? 'success' : 'danger';
            $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
            return;
        }

        $orderUpdate = ['payment_status' => $status];
        if ($status === 'completed' && ($order['payment_status'] ?? '') !== 'completed') {
            $orderUpdate['approved_by'] = (int) $_SESSION['user_id'];
        }

        if ($this->orderModel->update($id, $orderUpdate)) {
            // 1. Nếu duyệt đơn thành công (completed) -> Kích hoạt Gói Đăng Ký & Phát task add_user
            if ($status === 'completed' && $order['payment_status'] !== 'completed' && class_exists('App\Models\Subscription') && class_exists('App\Models\VpnPlan')) {
                $paymentModel = new Payment();
                if ($paymentModel->findSuccessfulByOrderId($id) === null) {
                    $paymentModel->create([
                        'user_id' => (int) $order['user_id'],
                        'order_id' => $id,
                        'type' => 'payment',
                        'payment_method' => (string) ($order['payment_method'] ?? 'vietqr'),
                        'transaction_id' => 'MANUAL-' . (string) ($order['order_code'] ?? $id),
                        'amount' => (float) ($order['total_amount'] ?? 0),
                        'status' => 'success',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                $planModel = new VpnPlan();
                $plan      = $planModel->find((int)$order['plan_id']);

                if ($plan) {
                    $subModel       = new Subscription();
                    $durationDays   = (int)($plan['duration_days'] ?? 30);
                    $bandwidthLimit = (int)($plan['bandwidth_limit_gb'] ?? 0);
                    $groupIds       = $this->parseGroupIds($plan['group_id'] ?? []);
                    $bytesTotal     = $bandwidthLimit > 0 ? ($bandwidthLimit * 1073741824) : 0;
                    $uuid           = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
                                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), 
                                        mt_rand(0, 0xffff), 
                                        mt_rand(0, 0x0fff) | 0x4000, 
                                        mt_rand(0, 0x3fff) | 0x8000, 
                                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

                    $startDate = date('Y-m-d H:i:s');
                    $endDate   = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

                    $created = $subModel->create([
                        'user_id'         => $order['user_id'],
                        'plan_id'         => $order['plan_id'],
                        'order_id'        => $id,
                        'uuid'            => $uuid,
                        'transfer_enable' => $bytesTotal,
                        'start_date'      => $startDate,
                        'end_date'        => $endDate,
                        'status'          => 'active'
                    ]);

                    if ($created) {
                        $subId = method_exists($subModel, 'lastInsertId') ? $subModel->lastInsertId() : 0;
                        if ($subId > 0 && !empty($groupIds)) {
                            $this->dispatchAddUserTask($subId, $uuid, $bytesTotal, $endDate, $groupIds);
                        }
                    }
                }
            }

            // 2. Nếu hủy đơn hàng (cancelled) -> Chuyển trạng thái Subscription sang cancelled & Phát task del_user
            if ($status === 'cancelled' && $order['payment_status'] !== 'cancelled' && class_exists('App\Models\Subscription')) {
                (new Payment())->failPendingByOrderId($id);
                $subModel = new Subscription();
                $sub      = $subModel->findByOrderId($id);

                if ($sub) {
                    $subModel->update((int)$sub['id'], ['status' => 'cancelled']);

                    if (class_exists('App\Models\VpnPlan')) {
                        $planModel = new VpnPlan();
                        $plan      = $planModel->find((int)$order['plan_id']);
                        $groupIds  = $this->parseGroupIds($plan['group_id'] ?? []);

                        if (!empty($groupIds)) {
                            $this->dispatchDelUserTask((int)$sub['id'], $groupIds);
                        }
                    }
                }
            }

            $this->logActivity(
                'UPDATE_ORDER_STATUS',
                'Cập nhật trạng thái đơn hàng #' . $id . ' (' . ($order['order_code'] ?? 'N/A') . ') từ ' . ($order['payment_status'] ?? 'N/A') . ' sang ' . $status
            );
            $_SESSION['flash_message'] = 'Cập nhật trạng thái đơn hàng thành công!';
            $_SESSION['flash_type']    = 'success';
        } else {
            $_SESSION['flash_message'] = 'Không thể cập nhật trạng thái đơn hàng!';
            $_SESSION['flash_type']    = 'danger';
        }

        $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
    }

    public function delete(): void
    {
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_message'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
            return;
        }

        $order = $this->orderModel->find($id);

        if (!$order) {
            $_SESSION['flash_message'] = 'Không tìm thấy đơn hàng cần xóa!';
            $_SESSION['flash_type']    = 'danger';
        } else {
            if ($this->orderModel->delete($id)) {
                $this->logActivity('DELETE_ORDER', 'Xóa đơn hàng #' . $id . ' (' . ($order['order_code'] ?? 'N/A') . ')');
                $_SESSION['flash_message'] = 'Xóa đơn hàng thành công!';
                $_SESSION['flash_type']    = 'success';
            } else {
                $_SESSION['flash_message'] = 'Không thể xóa đơn hàng!';
                $_SESSION['flash_type']    = 'danger';
            }
        }

        $this->redirect('/admin/orders' . ($userId > 0 ? '?user_id=' . $userId : ''));
    }

    public function create(): void
    {
        $userModel = new User();
        $planModel = class_exists('App\Models\VpnPlan') ? new VpnPlan() : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId        = (int)($_POST['user_id'] ?? 0);
            $planId        = (int)($_POST['plan_id'] ?? 0);
            $paymentStatus = $_POST['payment_status'] ?? 'pending';
            $customAmount  = $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;

            if ($userId <= 0 || $planId <= 0) {
                $_SESSION['flash_message'] = 'Vui lòng chọn Người dùng và Gói cước hợp lệ!';
                $_SESSION['flash_type']    = 'danger';
                $this->redirect('/admin/orders/create' . ($userId > 0 ? '?user_id=' . $userId : ''));
            }

            $plan = $planModel ? $planModel->find($planId) : null;
            if (!$plan) {
                $_SESSION['flash_message'] = 'Gói cước không tồn tại!';
                $_SESSION['flash_type']    = 'danger';
                $this->redirect('/admin/orders/create?user_id=' . $userId);
            }

            $totalAmount = $customAmount !== null ? $customAmount : (float)$plan['price'];
            $orderCode   = 'AD' . date('YmdHis') . rand(100, 999);

            $orderData = [
                'order_code'     => $orderCode,
                'user_id'        => $userId,
                'plan_id'        => $planId,
                'total_amount'   => $totalAmount,
                'payment_status' => $paymentStatus,
                'purchase_ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'created_by'     => (int) $_SESSION['user_id'],
                'approved_by'    => $paymentStatus === 'completed' ? (int) $_SESSION['user_id'] : null,
                'created_at'     => date('Y-m-d H:i:s')
            ];

            if ($this->orderModel->create($orderData)) {
                $orderId = method_exists($this->orderModel, 'lastInsertId') ? $this->orderModel->lastInsertId() : 0;

                if ($paymentStatus === 'completed' && class_exists('App\Models\Subscription')) {
                    $subModel       = new Subscription();
                    $durationDays   = (int)($plan['duration_days'] ?? 30);
                    $bandwidthLimit = (int)($plan['bandwidth_limit_gb'] ?? 0);
                    $groupIds       = $this->parseGroupIds($plan['group_id'] ?? []);
                    
                    $bytesTotal = $bandwidthLimit > 0 ? ($bandwidthLimit * 1073741824) : 0;
                    $uuid       = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
                                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), 
                                    mt_rand(0, 0xffff), 
                                    mt_rand(0, 0x0fff) | 0x4000, 
                                    mt_rand(0, 0x3fff) | 0x8000, 
                                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

                    $startDate  = date('Y-m-d H:i:s');
                    $endDate    = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

                    $created = $subModel->create([
                        'user_id'         => $userId,
                        'plan_id'         => $planId,
                        'order_id'        => $orderId ?: null,
                        'uuid'            => $uuid,
                        'transfer_enable' => $bytesTotal,
                        'start_date'      => $startDate,
                        'end_date'        => $endDate,
                        'status'          => 'active'
                    ]);

                    if ($created) {
                        $subId = method_exists($subModel, 'lastInsertId') ? $subModel->lastInsertId() : 0;
                        if ($subId > 0 && !empty($groupIds)) {
                            $this->dispatchAddUserTask($subId, $uuid, $bytesTotal, $endDate, $groupIds);
                        }
                    }
                }

                $this->logActivity('CREATE_ORDER', 'Tạo đơn hàng #' . ($orderId ?: 'N/A') . ' (' . $orderCode . ') cho người dùng #' . $userId);
                $_SESSION['flash_message'] = 'Tạo đơn hàng thủ công thành công!';
                $_SESSION['flash_type']    = 'success';
                $this->redirect('/admin/orders');
            } else {
                $_SESSION['flash_message'] = 'Lỗi hệ thống, không thể tạo đơn hàng!';
                $_SESSION['flash_type']    = 'danger';
                $this->redirect('/admin/orders/create?user_id=' . $userId);
            }
        }

        $selectedUserId = (int)($_GET['user_id'] ?? 0);
        $users          = $userModel->getAll();
        $plans          = $planModel ? ($planModel->getAllActive() ?? $planModel->getAll()) : [];

        $this->render('admin.orders.create', [
            'activeMenu'     => 'orders',
            'users'          => $users,
            'plans'          => $plans,
            'selectedUserId' => $selectedUserId
        ]);
    }

    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $order = $this->orderModel->findWithDetails($id);

        if (!$order) {
            $_SESSION['error'] = 'Đơn hàng không tồn tại!';
            $this->redirect('/admin/orders');
            return;
        }

        $this->render('admin.orders.detail', [
            'activeMenu' => 'orders',
            'order'      => $order
        ]);
    }
}
