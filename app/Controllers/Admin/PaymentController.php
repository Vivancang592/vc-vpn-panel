<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Payment;

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
}