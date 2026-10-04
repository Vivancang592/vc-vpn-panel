<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VpnPlan;
use App\Models\Order;

class SubscriptionController extends BaseController
{
    private Subscription $subscriptionModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->subscriptionModel = new Subscription();
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
     * Hàm phụ trợ: Tạo task add_user qua NodeTaskService (hợp đồng docs/task-contract.md)
     */
    private function dispatchAddUserTask(int $subId, string $uuid, int $bytesTotal, string $endDate, array $groupIds, ?int $maxDevices = null, string $status = 'active'): void
    {
        if (empty($groupIds)) return;

        (new \App\Services\NodeTaskService())->addUser(
            $groupIds,
            'sub_' . $subId,
            $uuid,
            $bytesTotal,
            $endDate,
            $status,
            $maxDevices
        );
    }

    /**
     * Hàm phụ trợ: Tạo task disable_user (tắt mạng) — thay cho toggle_user cũ
     */
    private function dispatchDisableUserTask(int $subId, string $reason, array $groupIds): void
    {
        if (empty($groupIds)) return;

        (new \App\Services\NodeTaskService())->disableUser($groupIds, 'sub_' . $subId, $reason);
    }

    /**
     * Hàm phụ trợ: Tạo task delete_user gửi xuống VPS thuộc đúng Nhóm Máy Chủ (group_ids)
     */
    private function dispatchDelUserTask(int $subId, array $groupIds, string $reason = 'admin'): void
    {
        if (empty($groupIds)) return;

        (new \App\Services\NodeTaskService())->deleteUser($groupIds, 'sub_' . $subId, $reason);
    }

    public function index(): void
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $subscriptions = $this->subscriptionModel->allWithDetails($userId);

        $filterUser = null;
        if ($userId && class_exists('App\Models\User')) {
            $userModel  = new User();
            $filterUser = $userModel->findById($userId);
        }

        $this->render('admin.subscriptions.index', [
            'activeMenu'    => 'subscriptions',
            'subscriptions' => $subscriptions,
            'filterUser'    => $filterUser,
            'userId'        => $userId
        ]);
    }

    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $subscription = $this->subscriptionModel->findWithDetails($id);

        if (!$subscription) {
            $_SESSION['error'] = 'Gói đăng ký không tồn tại!';
            $this->redirect('/admin/subscriptions');
            return;
        }

        $this->render('admin.subscriptions.detail', [
            'activeMenu'   => 'subscriptions',
            'subscription' => $subscription
        ]);
    }

    public function updateStatus(): void
    {
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $status = $_POST['status'] ?? $_GET['status'] ?? '';
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        $validStatuses = ['active', 'suspended', 'cancelled', 'expired'];

        if ($id <= 0 || !in_array($status, $validStatuses, true)) {
            $_SESSION['flash_message'] = 'Yêu cầu không hợp lệ!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        $sub = $this->subscriptionModel->find($id);
        if (!$sub) {
            $_SESSION['flash_message'] = 'Gói đăng ký không tồn tại!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        // Bật lại gói đã hủy (stock_state = released): trừ lại 1 suất tồn kho.
        // Hết hàng / gói ngừng bán → chặn, không đổi trạng thái.
        $reservedForReactivate = false;
        if (in_array($status, ['active', 'suspended'], true)
            && ($sub['stock_state'] ?? 'none') === 'released') {
            $reserve = $this->subscriptionModel->tryReserveSlotOnReactivate($sub);
            if (!$reserve['ok']) {
                $reserveErrors = [
                    'out_of_stock'  => 'Tồn kho gói đã hết — không thể bật lại gói đã hủy!',
                    'plan_disabled' => 'Gói cước đang ngừng bán — không thể bật lại gói đã hủy!',
                    'plan_missing'  => 'Không tìm thấy gói cước — không thể bật lại gói đã hủy!',
                ];
                $_SESSION['flash_message'] = $reserveErrors[$reserve['reason']]
                    ?? 'Không thể giữ suất tồn kho — vui lòng thử lại!';
                $_SESSION['flash_type']    = 'danger';
                $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
            }
            $reservedForReactivate = true;
        }

        // Khi bật lại sub active → xóa mốc khóa mạng 60s để cơ chế kiểm tra max_devices chạy lại từ đầu
        $updateData = ['status' => $status];
        if ($status === 'active') {
            $updateData['network_locked_until'] = null;
        }

        if ($this->subscriptionModel->update($id, $updateData)) {
            // Hủy gói: hoàn 1 suất tồn kho (idempotent — không giữ suất thì bỏ qua)
            if ($status === 'cancelled') {
                $this->subscriptionModel->releaseSlot($id);
            }

            $groupIds = [];
            if (class_exists('App\Models\VpnPlan') && !empty($sub['plan_id'])) {
                $planModel = new VpnPlan();
                $plan      = $planModel->find((int)$sub['plan_id']);
                $groupIds  = $this->parseGroupIds($plan['group_id'] ?? []);
            }

            if (!empty($groupIds)) {
                if ($status === 'active') {
                    $this->dispatchAddUserTask($id, $sub['uuid'], (int)$sub['transfer_enable'], $sub['end_date'], $groupIds, (int)($sub['max_devices'] ?? 1));
                } elseif ($status === 'suspended') {
                    $this->dispatchDisableUserTask($id, 'admin', $groupIds);
                } else {
                    // cancelled/expired: giữ user trên VPS chỉ cắt mạng (contract §2.2 — phe disable, không xóa)
                    $this->dispatchDisableUserTask($id, $status === 'expired' ? 'expired' : 'cancelled', $groupIds);
                }
            }

            $this->logActivity(
                'UPDATE_SUBSCRIPTION_STATUS',
                'Cập nhật trạng thái gói đăng ký #' . $id . ' từ ' . ($sub['status'] ?? 'N/A') . ' sang ' . $status
            );
            $_SESSION['flash_message'] = 'Cập nhật trạng thái gói đăng ký thành công!';
            $_SESSION['flash_type']    = 'success';
        } else {
            // Đổi trạng thái thất bại → hoàn tác suất vừa trừ (tránh treo tồn kho)
            if ($reservedForReactivate) {
                $this->subscriptionModel->releaseSlot($id);
            }
            $_SESSION['flash_message'] = 'Không thể cập nhật trạng thái gói!';
            $_SESSION['flash_type']    = 'danger';
        }

        $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
    }

    public function renew(): void
    {
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        $sub = $this->subscriptionModel->find($id);
        if (!$sub) {
            $_SESSION['flash_message'] = 'Gói đăng ký không tồn tại!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        $planModel = class_exists('App\Models\VpnPlan') ? new VpnPlan() : null;
        $plan      = $planModel ? $planModel->find((int)$sub['plan_id']) : null;

        if (!$plan) {
            $_SESSION['flash_message'] = 'Không tìm thấy thông tin gói cước tương ứng!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        // Gia hạn gói đã hủy (stock_state = released): trừ lại 1 suất tồn kho trước.
        // Hết hàng / gói ngừng bán → chặn, chưa tạo đơn hàng nào.
        $reservedForRenew = false;
        if (($sub['stock_state'] ?? 'none') === 'released') {
            $reserve = $this->subscriptionModel->tryReserveSlotOnReactivate($sub);
            if (!$reserve['ok']) {
                $reserveErrors = [
                    'out_of_stock'  => 'Tồn kho gói đã hết — không thể gia hạn gói đã hủy!',
                    'plan_disabled' => 'Gói cước đang ngừng bán — không thể gia hạn gói đã hủy!',
                    'plan_missing'  => 'Không tìm thấy gói cước — không thể gia hạn gói đã hủy!',
                ];
                $_SESSION['flash_message'] = $reserveErrors[$reserve['reason']]
                    ?? 'Không thể giữ suất tồn kho — vui lòng thử lại!';
                $_SESSION['flash_type']    = 'danger';
                $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
            }
            $reservedForRenew = true;
        }

        // 1. Khởi tạo đơn hàng mới cho giao dịch gia hạn
        $orderModel  = new Order();
        $orderCode   = 'AD' . date('YmdHis') . rand(100, 999);
        $totalAmount = (float)($plan['price'] ?? 0);

        $orderCreated = $orderModel->create([
            'order_code'     => $orderCode,
            'user_id'        => (int)$sub['user_id'],
            'plan_id'        => (int)$sub['plan_id'],
            'total_amount'   => $totalAmount,
            'payment_status' => 'completed',
            'purchase_ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'created_by'     => (int) $_SESSION['user_id'],
            'approved_by'    => (int) $_SESSION['user_id'],
        ]);

        if ($orderCreated) {
            $newOrderId   = method_exists($orderModel, 'lastInsertId') ? $orderModel->lastInsertId() : null;
            $durationDays = (int)($plan['duration_days'] ?? 30);
            $groupIds     = $this->parseGroupIds($plan['group_id'] ?? []);

            $currentEndDate = strtotime($sub['end_date']);
            $baseTime       = ($currentEndDate > time()) ? $currentEndDate : time();
            $newEndDate     = date('Y-m-d H:i:s', strtotime("+{$durationDays} days", $baseTime));

            $updateData = [
                'end_date' => $newEndDate,
                'status'   => 'active'
            ];

            if ($newOrderId) {
                $updateData['order_id'] = $newOrderId;
            }

            // 2. Cập nhật thời hạn và liên kết đơn hàng mới vào gói đăng ký
            if ($this->subscriptionModel->update($id, $updateData)) {
                if (!empty($groupIds)) {
                    $this->dispatchAddUserTask($id, $sub['uuid'], (int)$sub['transfer_enable'], $newEndDate, $groupIds, (int)($sub['max_devices'] ?? 1));
                }

                $this->logActivity(
                    'RENEW_SUBSCRIPTION',
                    'Gia hạn gói đăng ký #' . $id . ' đến ' . $newEndDate . ' bằng đơn hàng ' . $orderCode
                );
                $_SESSION['flash_message'] = 'Gia hạn gói đăng ký và tạo đơn hàng mới thành công!';
                $_SESSION['flash_type']    = 'success';
            } else {
                // Cập nhật gia hạn thất bại → hoàn tác suất vừa trừ (tránh treo tồn kho)
                if ($reservedForRenew) {
                    $this->subscriptionModel->releaseSlot($id);
                }
                $_SESSION['flash_message'] = 'Tạo đơn hàng thành công nhưng không thể cập nhật gia hạn!';
                $_SESSION['flash_type']    = 'warning';
            }
        } else {
            if ($reservedForRenew) {
                $this->subscriptionModel->releaseSlot($id);
            }
            $_SESSION['flash_message'] = 'Không thể khởi tạo đơn hàng mới cho lần gia hạn này!';
            $_SESSION['flash_type']    = 'danger';
        }

        $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
    }

    public function resetTraffic(): void
    {
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        $sub = $this->subscriptionModel->find($id);
        if (!$sub) {
            $_SESSION['flash_message'] = 'Gói đăng ký không tồn tại!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        if ($this->subscriptionModel->update($id, ['upload' => 0, 'download' => 0])) {
            $this->logActivity('RESET_SUBSCRIPTION_TRAFFIC', 'Đặt lại lưu lượng gói đăng ký #' . $id);
            $_SESSION['flash_message'] = 'Reset lưu lượng gói đăng ký về 0 GB thành công!';
            $_SESSION['flash_type']    = 'success';
        } else {
            $_SESSION['flash_message'] = 'Không thể reset lưu lượng!';
            $_SESSION['flash_type']    = 'danger';
        }

        $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
    }

    public function resetToken(): void
    {
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        $sub = $this->subscriptionModel->find($id);
        if (!$sub) {
            $_SESSION['flash_message'] = 'Gói đăng ký không tồn tại!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
        }

        $newUuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        if ($this->subscriptionModel->update($id, ['uuid' => $newUuid])) {
            $groupIds = [];
            if (class_exists('App\Models\VpnPlan') && !empty($sub['plan_id'])) {
                $planModel = new VpnPlan();
                $plan      = $planModel->find((int)$sub['plan_id']);
                $groupIds  = $this->parseGroupIds($plan['group_id'] ?? []);
            }

            if (!empty($groupIds)) {
                // Không âm thầm bật lại sub đang khóa: status gửi theo trạng thái thật của gói
                $subStatus = ($sub['status'] ?? '') === 'active' ? 'active' : 'disabled';
                $this->dispatchAddUserTask($id, $newUuid, (int)$sub['transfer_enable'], $sub['end_date'], $groupIds, (int)($sub['max_devices'] ?? 1), $subStatus);
            }

            $this->logActivity('RESET_SUBSCRIPTION_TOKEN', 'Đặt lại UUID gói đăng ký #' . $id);
            $_SESSION['flash_message'] = 'Đặt lại mã Token (UUID) mới thành công!';
            $_SESSION['flash_type']    = 'success';
        } else {
            $_SESSION['flash_message'] = 'Không thể đổi mã Token!';
            $_SESSION['flash_type']    = 'danger';
        }

        $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
    }

    public function delete(): void
    {
        $id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

        $sub = $this->subscriptionModel->find($id);
        if (!$sub) {
            $_SESSION['flash_message'] = 'Gói đăng ký không tồn tại!';
            $_SESSION['flash_type']    = 'danger';
        } else {
            // Xóa gói: hoàn 1 suất tồn kho trước khi xóa (idempotent — không giữ suất thì bỏ qua)
            $this->subscriptionModel->releaseSlot($id);

            if (class_exists('App\Models\VpnPlan') && !empty($sub['plan_id'])) {
                $planModel = new VpnPlan();
                $plan      = $planModel->find((int)$sub['plan_id']);
                $groupIds  = $this->parseGroupIds($plan['group_id'] ?? []);

                if (!empty($groupIds)) {
                    $this->dispatchDelUserTask($id, $groupIds);
                }
            }

            if ($this->subscriptionModel->delete($id)) {
                $this->logActivity('DELETE_SUBSCRIPTION', 'Xóa gói đăng ký #' . $id);
                $_SESSION['flash_message'] = 'Xóa gói đăng ký thành công!';
                $_SESSION['flash_type']    = 'success';
            } else {
                $_SESSION['flash_message'] = 'Không thể xóa gói đăng ký!';
                $_SESSION['flash_type']    = 'danger';
            }
        }

        $this->redirect('/admin/subscriptions' . ($userId > 0 ? '?user_id=' . $userId : ''));
    }
}
