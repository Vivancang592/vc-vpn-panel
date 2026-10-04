<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Coupon;
use App\Models\User;

class CouponController extends BaseController
{
    private Coupon $couponModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->couponModel = new Coupon();
    }

    public function index(): void
    {
        $coupons = $this->couponModel->all();

        // Gắn thông tin gán user (công khai / danh sách được gán) cho từng dòng
        $assignments = $this->couponModel->getAssignmentsForCoupons(array_column($coupons, 'id'));
        foreach ($coupons as &$coupon) {
            $assigned = $assignments[(int) $coupon['id']] ?? [];
            $coupon['assigned_count']    = count($assigned);
            $coupon['assigned_names']    = implode(', ', $assigned);
            $coupon['assigned_user_ids'] = array_keys($assigned);
        }
        unset($coupon);

        $this->render('admin.coupons.index', [
            'activeMenu' => 'coupons',
            'coupons'    => $coupons,
            'users'      => (new User())->getAll()
        ]);
    }

    // GET /admin/coupons/create
    public function showCreate(): void
    {
        $this->render('admin.coupons.create', [
            'activeMenu' => 'coupons'
        ]);
    }

    // POST /admin/coupons/create
    public function create(): void
    {
        $code          = strtoupper(trim($_POST['code'] ?? ''));
        $discountType  = $_POST['discount_type'] ?? 'percent';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $maxUses       = (int)($_POST['max_uses'] ?? 0);
        $expiresAt     = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $status        = $_POST['status'] ?? 'active';

        if (empty($code) || $discountValue <= 0) {
            $_SESSION['error'] = 'Vui lòng nhập mã giảm giá và giá trị giảm hợp lệ!';
            $this->redirect('/admin/coupons/create');
            return;
        }

        $data = [
            'code'           => $code,
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'max_uses'       => $maxUses,
            'expires_at'     => $expiresAt,
            'status'         => $status,
            'created_at'     => date('Y-m-d H:i:s')
        ];

        if ($this->couponModel->create($data)) {
            $this->logActivity('CREATE_COUPON', 'Tạo mã giảm giá mới: ' . $code);
            $_SESSION['flash_message'] = 'Tạo mã giảm giá thành công!';
            $_SESSION['flash_type']    = 'success';
            $this->redirect('/admin/coupons');
        } else {
            $_SESSION['error'] = 'Lỗi hệ thống, không thể tạo mã giảm giá!';
            $this->redirect('/admin/coupons/create');
        }
    }

    // GET /admin/coupons/edit
    public function showEdit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $coupon = $this->couponModel->find($id);

        if (!$coupon) {
            $_SESSION['error'] = 'Mã giảm giá không tồn tại!';
            $this->redirect('/admin/coupons');
            return;
        }

        // Danh sách user đang được gán riêng cho mã này (id => username)
        $assignMap = $this->couponModel->getAssignmentsForCoupons([$id]);

        $this->render('admin.coupons.edit', [
            'activeMenu'     => 'coupons',
            'coupon'         => $coupon,
            'assigned_users' => $assignMap[$id] ?? [],
            'users'          => (new User())->getAll()
        ]);
    }

    // POST /admin/coupons/edit
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $coupon = $this->couponModel->find($id);

        if (!$coupon) {
            $_SESSION['error'] = 'Mã giảm giá không tồn tại!';
            $this->redirect('/admin/coupons');
            return;
        }

        $code          = strtoupper(trim($_POST['code'] ?? ''));
        $discountType  = $_POST['discount_type'] ?? 'percent';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $maxUses       = (int)($_POST['max_uses'] ?? 0);
        $expiresAt     = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $status        = $_POST['status'] ?? 'active';

        if (empty($code) || $discountValue <= 0) {
            $_SESSION['error'] = 'Vui lòng nhập mã giảm giá và giá trị giảm hợp lệ!';
            $this->redirect('/admin/coupons/edit?id=' . $id);
            return;
        }

        $data = [
            'code'           => $code,
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'max_uses'       => $maxUses,
            'expires_at'     => $expiresAt,
            'status'         => $status
        ];

        if ($this->couponModel->update($id, $data)) {
            $this->logActivity('UPDATE_COUPON', 'Cập nhật mã giảm giá ID #' . $id . ' (' . $code . ')');
            $_SESSION['flash_message'] = 'Cập nhật mã giảm giá thành công!';
            $_SESSION['flash_type']    = 'success';
            $this->redirect('/admin/coupons');
        } else {
            $_SESSION['error'] = 'Không thể cập nhật thông tin mã giảm giá!';
            $this->redirect('/admin/coupons/edit?id=' . $id);
        }
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        if ($id > 0) {
            if ($this->couponModel->delete($id)) {
                $this->logActivity('DELETE_COUPON', 'Xóa mã giảm giá ID #' . $id);
                $_SESSION['flash_message'] = 'Đã xóa mã giảm giá thành công!';
                $_SESSION['flash_type']    = 'success';
            } else {
                $_SESSION['error'] = 'Không thể xóa mã giảm giá này!';
            }
        } else {
            $_SESSION['error'] = 'Mã giảm giá không hợp lệ!';
        }

        $this->redirect('/admin/coupons');
    }

    /**
     * Gán (bulk) 1..n mã giảm giá cho một user cụ thể.
     * POST: coupon_ids[] (bắt buộc), user_id (bắt buộc), back?, coupon_id?
     */
    public function assign(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $_SESSION['error'] = 'Phiên làm việc đã hết hạn, vui lòng thử lại!';
            $this->backRedirect();
            return;
        }

        $ids = $this->cleanCouponIds();
        if (empty($ids)) {
            $_SESSION['error'] = 'Vui lòng chọn ít nhất một mã giảm giá!';
            $this->backRedirect();
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $user   = $userId > 0 ? (new User())->findById($userId) : null;
        if (!$user) {
            $_SESSION['error'] = 'User nhận gán không tồn tại!';
            $this->backRedirect();
            return;
        }

        $coupons = $this->couponModel->findByIds($ids);
        if (empty($coupons)) {
            $_SESSION['error'] = 'Không tìm thấy mã giảm giá hợp lệ!';
            $this->backRedirect();
            return;
        }

        foreach ($coupons as $coupon) {
            $this->couponModel->assignUsers((int)$coupon['id'], [$userId]);
        }

        $codes = implode(', ', array_column($coupons, 'code'));
        $this->logActivity('ASSIGN_COUPON', 'Gán mã giảm giá [' . $codes . '] cho user ' . $user['username'] . ' (#' . $userId . ')');
        $_SESSION['flash_message'] = 'Đã gán ' . count($coupons) . ' mã giảm giá cho @' . $user['username'] . '!';
        $_SESSION['flash_type']    = 'success';
        $this->backRedirect();
    }

    /**
     * Gỡ gán (bulk): có user_id → gỡ đúng user đó khỏi các coupon đã chọn;
     * không có user_id → xóa toàn bộ user được gán của các coupon đã chọn
     * (coupon trở lại công khai).
     * POST: coupon_ids[] (bắt buộc), user_id (tùy chọn), back?, coupon_id?
     */
    public function unassign(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $_SESSION['error'] = 'Phiên làm việc đã hết hạn, vui lòng thử lại!';
            $this->backRedirect();
            return;
        }

        $ids = $this->cleanCouponIds();
        if (empty($ids)) {
            $_SESSION['error'] = 'Vui lòng chọn ít nhất một mã giảm giá!';
            $this->backRedirect();
            return;
        }

        $coupons = $this->couponModel->findByIds($ids);
        if (empty($coupons)) {
            $_SESSION['error'] = 'Không tìm thấy mã giảm giá hợp lệ!';
            $this->backRedirect();
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $user   = null;
        if ($userId > 0) {
            $user = (new User())->findById($userId);
            if (!$user) {
                $_SESSION['error'] = 'User cần gỡ gán không tồn tại!';
                $this->backRedirect();
                return;
            }
        }

        foreach ($coupons as $coupon) {
            $this->couponModel->removeAssignments((int)$coupon['id'], $user ? $userId : null);
        }

        $codes = implode(', ', array_column($coupons, 'code'));
        if ($user) {
            $this->logActivity('UNASSIGN_COUPON', 'Gỡ gán mã [' . $codes . '] của user ' . $user['username'] . ' (#' . $userId . ')');
            $_SESSION['flash_message'] = 'Đã gỡ gán ' . count($coupons) . ' mã giảm giá khỏi @' . $user['username'] . '!';
        } else {
            $this->logActivity('UNASSIGN_COUPON', 'Xóa toàn bộ user được gán mã [' . $codes . ']');
            $_SESSION['flash_message'] = 'Đã đưa ' . count($coupons) . ' mã giảm giá về công khai (không gán riêng)!';
        }
        $_SESSION['flash_type'] = 'success';
        $this->backRedirect();
    }

    /**
     * Lọc & chuẩn hóa coupon_ids[] từ POST (int > 0, unique, tối đa 200 mã/lần)
     * @return int[]
     */
    private function cleanCouponIds(): array
    {
        $raw = $_POST['coupon_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $raw), static fn($v) => $v > 0)));
        return array_slice($ids, 0, 200);
    }

    /**
     * Redirect về trang gọi (index hoặc edit) sau bulk action
     */
    private function backRedirect(): void
    {
        if (($_POST['back'] ?? '') === 'edit') {
            $id = (int)($_POST['coupon_id'] ?? 0);
            if ($id > 0) {
                $this->redirect('/admin/coupons/edit?id=' . $id);
                return;
            }
        }
        $this->redirect('/admin/coupons');
    }
}