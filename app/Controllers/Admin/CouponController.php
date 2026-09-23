<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Coupon;

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

        $this->render('admin.coupons.index', [
            'activeMenu' => 'coupons',
            'coupons'    => $coupons
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

        $this->render('admin.coupons.edit', [
            'activeMenu' => 'coupons',
            'coupon'     => $coupon
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
}