<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Withdrawal;

class WithdrawalController extends BaseController
{
    private Withdrawal $withdrawalModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->withdrawalModel = new Withdrawal();
    }

    public function index(): void
    {
        $withdrawals = $this->withdrawalModel->allWithDetails();

        $this->render('admin.withdrawals.index', [
            'activeMenu'  => 'withdrawals',
            'withdrawals' => $withdrawals
        ]);
    }

    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $withdrawal = $this->withdrawalModel->findWithDetails($id);

        if (!$withdrawal) {
            $_SESSION['error'] = 'Yêu cầu rút tiền không tồn tại!';
            $this->redirect('/admin/withdrawals');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if (in_array($action, ['approved', 'rejected'], true)) {
                if ($this->withdrawalModel->updateStatus($id, $action)) {
                    $_SESSION['flash_message'] = ($action === 'approved') 
                        ? 'Đã duyệt yêu cầu rút tiền thành công!' 
                        : 'Đã từ chối yêu cầu rút tiền!';
                    $_SESSION['flash_type'] = ($action === 'approved') ? 'success' : 'danger';
                } else {
                    $_SESSION['error'] = 'Không thể cập nhật trạng thái yêu cầu!';
                }
                $this->redirect('/admin/withdrawals/detail?id=' . $id);
                return;
            }
        }

        $this->render('admin.withdrawals.detail', [
            'activeMenu' => 'withdrawals',
            'withdrawal' => $withdrawal
        ]);
    }
}