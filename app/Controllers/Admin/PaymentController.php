<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Payment;
use App\Services\PaymentService;

class PaymentController extends BaseController
{
    private Payment $paymentModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->paymentModel = new Payment();
    }

    public function index(): void
    {
        $payments = $this->paymentModel->allWithDetails();

        $this->render('admin.payments.index', [
            'activeMenu' => 'payments',
            'payments'   => $payments
        ]);
    }

    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $payment = $this->paymentModel->findWithDetails($id);

        if (!$payment) {
            $_SESSION['error'] = 'Giao dịch thanh toán không tồn tại!';
            $this->redirect('/admin/payments');
            return;
        }

        $this->render('admin.payments.detail', [
            'activeMenu' => 'payments',
            'payment'    => $payment
        ]);
    }

    public function approveDeposit(): void
    {
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $payment = $this->paymentModel->find($paymentId);

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '') || !$payment
            || ($payment['type'] ?? '') !== 'deposit' || ($payment['status'] ?? '') !== 'pending') {
            $_SESSION['error'] = 'Chỉ có thể duyệt giao dịch nạp tiền đang chờ.';
            $this->redirect('/admin/payments/detail?id=' . $paymentId);
            return;
        }

        $approved = (new PaymentService())->completePayment($paymentId);
        $_SESSION[$approved ? 'flash_message' : 'error'] = $approved
            ? 'Đã duyệt và cộng tiền vào ví của khách hàng.'
            : 'Không thể duyệt giao dịch nạp tiền này.';
        if ($approved) {
            $_SESSION['flash_type'] = 'success';
            $this->logActivity('APPROVE_DEPOSIT', 'Duyệt thủ công giao dịch nạp tiền #' . $paymentId);
        }
        $this->redirect('/admin/payments/detail?id=' . $paymentId);
    }

    public function deleteCancelledDeposit(): void
    {
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $payment = $paymentId > 0 ? $this->paymentModel->find($paymentId) : null;

        // Hủy đơn hàng liên kết trước khi xóa payment để không còn đơn "pending" mồ côi.
        if ($payment !== null && !empty($payment['order_id'])) {
            (new \App\Models\Order())->cancelPendingForUser(
                (int) $payment['order_id'],
                (int) $payment['user_id']
            );
        }

        $deleted = $this->validateCsrfToken($_POST['csrf_token'] ?? '')
            && $payment !== null
            && $this->paymentModel->deletePendingOrFailed($paymentId);

        $_SESSION[$deleted ? 'flash_message' : 'error'] = $deleted
            ? 'Đã xóa giao dịch thanh toán.'
            : 'Chỉ có thể xóa giao dịch đang chờ hoặc đã thất bại.';
        if ($deleted) {
            $_SESSION['flash_type'] = 'success';
            $this->logActivity('DELETE_PAYMENT', 'Xóa giao dịch thanh toán #' . $paymentId);
        }
        $this->redirect('/admin/payments');
    }
}