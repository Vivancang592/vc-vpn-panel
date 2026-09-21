<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\VpnPlan;
use App\Models\Subscription;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\Post;
use App\Models\ServerGroup;
use App\Models\NodeInbound;
use App\Models\Coupon;
use App\Models\ReferralCommission;
use App\Models\Withdrawal;
use App\Models\TicketMessage;
use App\Services\VpnService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\NotificationService;

class UserController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    public function dashboard(): void
    {
        $userId = $_SESSION['user_id'];
        $user = [];
        $subscriptions = [];
        $orders = [];
        $tickets = [];
        $posts = [];
        $plans = [];
        $serverGroups = [];

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            $user = $userModel->findById($userId);
        }

        if (class_exists('App\Models\Subscription')) {
            $subModel = new Subscription();
            $subscriptions = $subModel->getByUserId($userId);
        }

        if (class_exists('App\Models\Order')) {
            $orderModel = new Order();
            $orders = $orderModel->getByUserId($userId);
        }

        if (class_exists('App\Models\SupportTicket')) {
            $ticketModel = new SupportTicket();
            if (method_exists($ticketModel, 'getByUserId')) {
                $tickets = $ticketModel->getByUserId($userId);
            } elseif (method_exists($ticketModel, 'allWithDetails')) {
                $allTickets = $ticketModel->allWithDetails();
                $tickets = array_values(array_filter($allTickets, static function ($t) use ($userId) {
                    return (int)($t['user_id'] ?? 0) === (int)$userId;
                }));
            }
        }

        if (class_exists('App\Models\Post')) {
            $postModel = new Post();
            $posts = $postModel->getAllPublished();
        }

        if (class_exists('App\Models\VpnPlan')) {
            $planModel = new VpnPlan();
            $plans = $planModel->getAllActive();
        }

        if (class_exists('App\Models\ServerGroup')) {
            $groupModel = new ServerGroup();
            if (method_exists($groupModel, 'getAll')) {
                $serverGroups = $groupModel->getAll();
            }
        }

        $this->render('user.dashboard', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'orders' => $orders,
            'tickets' => $tickets,
            'posts' => $posts,
            'plans' => $plans,
            'serverGroups' => $serverGroups,
            'activeMenu' => 'dashboard'
        ]);
    }

    public function plans(): void
    {
        $plans = [];
        $serverGroups = [];
        if (class_exists('App\Models\VpnPlan')) {
            $planModel = new VpnPlan();
            $plans = $planModel->getAllActive();
        }

        if (class_exists('App\Models\ServerGroup')) {
            $groupModel = new ServerGroup();
            if (method_exists($groupModel, 'getAll')) {
                $serverGroups = array_values(array_filter(
                    $groupModel->getAll(),
                    static fn(array $group): bool => ($group['status'] ?? 'active') === 'active'
                ));
            }
        }

        $this->render('user.plans.index', [
            'plans' => $plans,
            'serverGroups' => $serverGroups,
            'activeMenu' => 'plans'
        ]);
    }

    public function checkout(): void
    {
        $checkoutType = strtolower(trim((string) ($_GET['type'] ?? 'plan')));
        $subscriptionId = (int) ($_GET['subscription'] ?? 0);

        if ($checkoutType === 'deposit') {
            $minDeposit = (float) (
                $this->settings['min_deposit'] ??
                $this->settings['min_deposit_amount'] ??
                (strtoupper(trim((string) ($this->settings['currency'] ?? 'CNY'))) === 'VND' ? 10000 : 10)
            );
            $depositAmount = max(0, (float) ($_GET['amount'] ?? 0));

            $this->render('user.plans.checkout', [
                'checkoutType' => 'deposit',
                'plan' => null,
                'subscription' => null,
                'depositAmount' => $depositAmount,
                'minDeposit' => $minDeposit,
                'paymentGateways' => $this->getEnabledPaymentGateways(false),
                'pendingOrder' => null,
                'couponPreview' => null,
                'activeMenu' => 'wallet'
            ]);
            return;
        }

        if ($checkoutType === 'renewal') {
            $subscription = $this->getOwnedSubscription($subscriptionId);
            if ($subscription === null) {
                $_SESSION['error'] = 'Gói dịch vụ không hợp lệ hoặc không thuộc tài khoản của bạn.';
                $this->redirect('/subscriptions');
                return;
            }

            $planModel = new VpnPlan();
            $plan = $planModel->find((int) ($subscription['plan_id'] ?? 0));
            if (!$plan) {
                $_SESSION['error'] = 'Không tìm thấy gói cước của subscription.';
                $this->redirect('/subscriptions/detail?id=' . $subscriptionId);
                return;
            }

            $this->render('user.plans.checkout', [
                'checkoutType' => 'renewal',
                'plan' => $plan,
                'subscription' => $subscription,
                'paymentGateways' => $this->getEnabledPaymentGateways(false),
                'pendingOrder' => null,
                'couponPreview' => null,
                'activeMenu' => 'subscriptions'
            ]);
            return;
        }

        $planId = (int)($_GET['id'] ?? 0);
        $plan = [];
        if ($planId > 0 && class_exists('App\Models\VpnPlan')) {
            $planModel = new VpnPlan();
            $plan = $planModel->find($planId);
        }
        if (empty($plan)) {
            $this->redirect('/user/plans');
            return;
        }

        $paymentGateways = $this->getEnabledPaymentGateways();
        $pendingOrder = (new Order())->findPendingByUserId((int) $_SESSION['user_id']);
        $couponPreview = null;
        $previewSession = $_SESSION['checkout_coupon_preview'] ?? null;
        if (is_array($previewSession) && (int) ($previewSession['plan_id'] ?? 0) === $planId) {
            $couponPreview = $previewSession;
        }

        $this->render('user.plans.checkout', [
            'checkoutType' => 'plan',
            'plan' => $plan,
            'subscription' => null,
            'paymentGateways' => $paymentGateways,
            'pendingOrder' => $pendingOrder,
            'couponPreview' => $couponPreview,
            'activeMenu' => 'plans'
        ]);
    }

    public function buyPlan(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
            $this->redirect('/user/plans');
            return;
        }

        $checkoutType = strtolower(trim((string) ($_POST['checkout_type'] ?? 'plan')));

        if ($checkoutType === 'deposit') {
            $this->deposit();
            return;
        }

        if ($checkoutType === 'renewal') {
            $subscriptionId = (int) ($_POST['subscription_id'] ?? 0);
            $subscription = $this->getOwnedSubscription($subscriptionId);
            if ($subscription === null) {
                $_SESSION['error'] = 'Gói dịch vụ không hợp lệ hoặc không thuộc tài khoản của bạn.';
                $this->redirect('/subscriptions');
                return;
            }

            $paymentGateway = trim((string) ($_POST['payment_gateway'] ?? 'vietqr'));
            $allowedGateways = array_column($this->getEnabledPaymentGateways(false), 'id');
            if (!in_array($paymentGateway, $allowedGateways, true)) {
                $_SESSION['error'] = 'Cổng thanh toán không hợp lệ hoặc đang tạm tắt.';
                $this->redirect('/checkout?type=renewal&subscription=' . $subscriptionId);
                return;
            }

            $result = (new PaymentService())->createRenewalTransaction(
                (int) $_SESSION['user_id'], $subscriptionId, $paymentGateway, $this->getClientIp()
            );
            if (($result['status'] ?? false) !== true) {
                $_SESSION['error'] = $result['message'] ?? 'Không thể tạo giao dịch gia hạn.';
                $this->redirect('/checkout?type=renewal&subscription=' . $subscriptionId);
                return;
            }

            $paymentId = (int) ($result['payment_id'] ?? 0);
            if ($paymentId <= 0 && !empty($result['transaction_code'])) {
                $payment = (new Payment())->findByTransactionId((string) $result['transaction_code']);
                $paymentId = (int) ($payment['id'] ?? 0);
            }
            if ($paymentId <= 0) {
                $_SESSION['error'] = 'Đã tạo giao dịch gia hạn nhưng không xác định được giao dịch thanh toán.';
                $this->redirect('/subscriptions/detail?id=' . $subscriptionId);
                return;
            }

            $this->redirect('/payment/checkout?renewal=' . $subscriptionId . '&payment=' . $paymentId);
            return;
        }

        $planId = (int)($_POST['plan_id'] ?? 0);
        $couponCode = trim($_POST['coupon_code'] ?? '');
        $action = trim((string) ($_POST['checkout_action'] ?? 'create_order'));
        $paymentGateway = trim((string) ($_POST['payment_gateway'] ?? 'vietqr'));

        if ($couponCode !== '') {
            $couponCode = strtoupper($couponCode);
        }

        if ($planId <= 0) {
            $_SESSION['error'] = 'Gói dịch vụ không hợp lệ.';
            $this->redirect('/user/plans');
            return;
        }

        if ($action === 'preview_coupon') {
            $preview = $this->calculateCouponPreview($planId, $couponCode);
            $_SESSION['checkout_coupon_preview'] = [
                'plan_id' => $planId,
                'coupon_code' => $couponCode,
                ...$preview
            ];

            if (($preview['valid'] ?? false) === true) {
                $_SESSION['success'] = $preview['message'] ?? 'Mã giảm giá hợp lệ.';
            } else {
                $_SESSION['error'] = $preview['message'] ?? 'Mã giảm giá không hợp lệ.';
            }

            $this->redirect('/checkout?id=' . $planId);
            return;
        }

        $allowedGateways = array_column($this->getEnabledPaymentGateways(), 'id');
        if (!in_array($paymentGateway, $allowedGateways, true)) {
            $_SESSION['error'] = 'Cổng thanh toán không hợp lệ hoặc đang tạm tắt.';
            $this->redirect('/checkout?id=' . $planId);
            return;
        }

        $orderModel = new Order();
        $pendingOrder = $orderModel->findPendingByUserId((int) $_SESSION['user_id']);
        if ($pendingOrder) {
            $_SESSION['error'] = 'Bạn đang có một đơn hàng chờ thanh toán. Hãy thanh toán hoặc hủy đơn đó trước khi tạo đơn mới.';
            $this->redirect('/checkout?id=' . $planId);
            return;
        }

        $orderService = new OrderService();
        $result = $orderService->createOrder((int) $_SESSION['user_id'], $planId, $couponCode !== '' ? $couponCode : null, $paymentGateway, $this->getClientIp());

        unset($_SESSION['checkout_coupon_preview']);

        if (($result['status'] ?? false) === true) {
            $orderCode = $result['order_code'] ?? '';
            $amount = $this->formatCurrency((float) ($result['amount'] ?? 0));
$_SESSION['success'] = 'Đã tạo đơn hàng ' . $orderCode . ' thành công. Số tiền cần thanh toán: ' . $amount . '.';
            if ($paymentGateway === 'balance') {
                $this->redirect('/subscriptions');
                return;
            }

            $this->redirect('/payment/checkout?order=' . (int) ($result['order_id'] ?? 0));
            return;
        }

        $_SESSION['error'] = $result['message'] ?? 'Không thể tạo đơn hàng. Vui lòng thử lại.';
        $this->redirect('/checkout?id=' . $planId);
    }

    public function previewCoupon(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json([
                'valid' => false,
                'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'
            ], 419);
        }

        $planId = (int) ($_POST['plan_id'] ?? 0);
        $couponCode = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
        if ($planId <= 0) {
            $this->json([
                'valid' => false,
                'message' => 'Gói dịch vụ không hợp lệ.'
            ], 422);
        }

        $preview = $this->calculateCouponPreview($planId, $couponCode);
        if (($preview['valid'] ?? false) === true) {
            $_SESSION['checkout_coupon_preview'] = [
                'plan_id' => $planId,
                'coupon_code' => $couponCode,
                ...$preview
            ];
        } else {
            unset($_SESSION['checkout_coupon_preview']);
        }

        $this->json([
    ...$preview,
    'coupon_code' => $couponCode,
    'currency_symbol' => trim((string) ($this->settings['currency_symbol'] ?? 'đ')),
    'currency_position' => (string) ($this->settings['currency_position'] ?? 'right'),
    'currency_decimals' => max(
        0,
        min(4, (int) ($this->settings['currency_decimals'] ?? 0))
    )
], ($preview['valid'] ?? false) === true ? 200 : 422);
    }

    public function paymentCheckout(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $orderId = (int) ($_GET['order'] ?? 0);
        $depositId = (int) ($_GET['deposit'] ?? 0);
        $renewalId = (int) ($_GET['renewal'] ?? 0);
        $paymentId = (int) ($_GET['payment'] ?? 0);

        if ($orderId > 0) {
            $order = (new Order())->findWithDetails($orderId);
            if (!$order || (int) ($order['user_id'] ?? 0) !== $userId) {
                $_SESSION['error'] = 'Không tìm thấy đơn hàng thanh toán.';
                $this->redirect('/orders');
                return;
            }
            if (($order['payment_status'] ?? '') !== 'pending') {
                $this->redirect('/orders/detail?id=' . (int) $order['id']);
                return;
            }
            $this->render('user.payments.checkout', [
                'checkoutType' => 'order', 'order' => $order, 'payment' => null,
                'subscription' => null,
                'paymentInstructions' => $this->buildPaymentInstructions($order),
                'activeMenu' => 'orders'
            ]);
            return;
        }

        if ($depositId > 0) {
            $paymentModel = new Payment();
            $payment = method_exists($paymentModel, 'findWithDetails')
                ? $paymentModel->findWithDetails($depositId) : $paymentModel->find($depositId);
            if (!$payment || (int) ($payment['user_id'] ?? 0) !== $userId || ($payment['type'] ?? '') !== 'deposit') {
                $_SESSION['error'] = 'Không tìm thấy giao dịch nạp tiền hoặc giao dịch không thuộc tài khoản của bạn.';
                $this->redirect('/payments');
                return;
            }
            if (($payment['status'] ?? '') !== 'pending') {
                $this->redirect('/payments');
                return;
            }
            $this->render('user.payments.checkout', [
                'checkoutType' => 'deposit', 'order' => null, 'payment' => $payment,
                'subscription' => null,
                'paymentInstructions' => $this->buildDepositPaymentInstructions($payment),
                'activeMenu' => 'payments'
            ]);
            return;
        }

        if ($renewalId > 0 && $paymentId > 0) {
            $subscription = $this->getOwnedSubscription($renewalId);
            $paymentModel = new Payment();
            $payment = method_exists($paymentModel, 'findWithDetails')
                ? $paymentModel->findWithDetails($paymentId) : $paymentModel->find($paymentId);
            if (!$subscription || !$payment || (int) ($payment['user_id'] ?? 0) !== $userId
                || ($payment['type'] ?? '') !== 'payment'
                || (int) ($payment['subscription_id'] ?? 0) !== $renewalId) {
                $_SESSION['error'] = 'Giao dịch gia hạn không hợp lệ hoặc không thuộc tài khoản của bạn.';
                $this->redirect('/subscriptions');
                return;
            }
            if (($payment['status'] ?? '') !== 'pending') {
                $this->redirect('/subscriptions/detail?id=' . $renewalId);
                return;
            }
            $this->render('user.payments.checkout', [
                'checkoutType' => 'renewal', 'order' => null, 'payment' => $payment,
                'subscription' => $subscription,
                'paymentInstructions' => $this->buildDepositPaymentInstructions($payment),
                'activeMenu' => 'subscriptions'
            ]);
            return;
        }

        $_SESSION['error'] = 'Thiếu thông tin giao dịch thanh toán.';
        $this->redirect('/payments');
    }

    public function cancelOrder(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $this->redirect('/orders');
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $orderModel = new Order();
        if ($orderId <= 0 || !$orderModel->cancelPendingForUser($orderId, (int) $_SESSION['user_id'])) {
            $_SESSION['error'] = 'Không thể hủy đơn hàng này. Chỉ đơn đang chờ thanh toán mới có thể hủy.';
            $this->redirect('/orders/detail?id=' . $orderId);
            return;
        }

        $_SESSION['success'] = 'Đã hủy đơn hàng chờ thanh toán. Bạn có thể tạo đơn mới.';
        $this->redirect('/orders');
    }

    public function orderStatus(): void
    {
        $orderId = (int) ($_GET['id'] ?? 0);
        if ($orderId <= 0 || !class_exists('App\Models\Order')) {
            $this->json(['status' => 'not_found'], 404);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->find($orderId);
        if (!$order || (int) ($order['user_id'] ?? 0) !== (int) $_SESSION['user_id']) {
            $this->json(['status' => 'not_found'], 404);
            return;
        }

        $this->json(['status' => $order['payment_status'] ?? 'pending']);
    }

    private function formatCurrency(float $amount): string
{
    $symbol = trim((string) ($this->settings['currency_symbol'] ?? 'đ'));
    $position = (string) ($this->settings['currency_position'] ?? 'right');
    $decimals = max(
        0,
        min(4, (int) ($this->settings['currency_decimals'] ?? 0))
    );

    $formatted = number_format($amount, $decimals, '.', ',');

    return $position === 'left'
        ? $symbol . $formatted
        : $formatted . ' ' . $symbol;
}

    private function buildPaymentInstructions(array $order): array
    {
        $method = (string) ($order['payment_method'] ?? 'vietqr');
        $totalAmount = (float) ($order['total_amount'] ?? 0);
        $orderCode = (string) ($order['order_code'] ?? '');
        $orderId = (int) ($order['id'] ?? 0);
        $transferContent = !empty($order['transfer_content'])
            ? (string)$order['transfer_content']
            : (trim((string) ($this->settings['order_transfer_syntax'] ?? 'THANHTOAN')) . str_pad((string) $orderId, 2, '0', STR_PAD_LEFT));

        if ($method === 'wechat') {
            return [
                'name' => 'WeChat Pay',
                'qr_url' => trim((string) ($this->settings['wechat_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['wechat_account_name'] ?? '')),
                'transfer_content' => $transferContent,
                'amount' => $totalAmount,
                'amount_display' => $this->formatGatewayCurrency($totalAmount, $method)
            ];
        }

        if ($method === 'alipay') {
            return [
                'name' => 'Alipay',
                'qr_url' => trim((string) ($this->settings['alipay_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['alipay_account_name'] ?? '')),
                'transfer_content' => $transferContent,
                'amount' => $totalAmount,
                'amount_display' => $this->formatGatewayCurrency($totalAmount, $method)
            ];
        }

        $bankName = trim((string) ($this->settings['bank_name'] ?? ''));
        $accountNumber = trim((string) ($this->settings['bank_account_number'] ?? ''));
        $qrAmount = number_format($totalAmount, 0, '.', '');
        $qrUrl = '';
        if ($bankName !== '' && $accountNumber !== '') {
            $qrUrl = 'https://img.vietqr.io/image/' . rawurlencode($bankName) . '-' . rawurlencode($accountNumber)
                . '-compact2.jpg?amount=' . rawurlencode($qrAmount) . '&addInfo=' . rawurlencode($transferContent);
        }

        return [
            'name' => 'VietQR',
            'qr_url' => $qrUrl,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_name' => trim((string) ($this->settings['bank_account_name'] ?? '')),
            'transfer_content' => $transferContent,
            'amount' => $totalAmount,
            'amount_display' => $this->formatGatewayCurrency($totalAmount, $method)
        ];
    }

    private function buildDepositPaymentInstructions(array $payment): array
    {
        $method = (string) ($payment['payment_method'] ?? 'vietqr');
        $amount = $this->convertAmountForGateway(
            (float) ($payment['amount'] ?? 0),
            $method
        );
        $transactionCode = (string) ($payment['transaction_id'] ?? '');
        $paymentId = (int) ($payment['id'] ?? 0);
        $isRenewal = ($payment['type'] ?? '') === 'payment' && !empty($payment['subscription_id']);
        $syntax = trim((string) ($this->settings[
            $isRenewal ? 'renewal_transfer_syntax' : 'bank_transfer_syntax'
        ] ?? ($isRenewal ? 'GAHAN' : 'NAPTIEN')));
        $transferContent = $syntax . str_pad((string) $paymentId, 2, '0', STR_PAD_LEFT);

        if ($method === 'wechat') {
            return [
                'name' => 'WeChat Pay',
                'qr_url' => trim((string) ($this->settings['wechat_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['wechat_account_name'] ?? '')),
                'transfer_content' => $transferContent,
                'amount' => $amount,
                'amount_display' => $this->formatGatewayCurrency($amount, $method)
            ];
        }

        if ($method === 'alipay') {
            return [
                'name' => 'Alipay',
                'qr_url' => trim((string) ($this->settings['alipay_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['alipay_account_name'] ?? '')),
                'transfer_content' => $transferContent,
                'amount' => $amount,
                'amount_display' => $this->formatGatewayCurrency($amount, $method)
            ];
        }

        $bankName = trim((string) ($this->settings['bank_name'] ?? ''));
        $accountNumber = trim((string) ($this->settings['bank_account_number'] ?? ''));
        $qrAmount = number_format($amount, 0, '.', '');
        $qrUrl = '';

        if ($bankName !== '' && $accountNumber !== '') {
            $qrUrl = 'https://img.vietqr.io/image/' . rawurlencode($bankName) . '-' . rawurlencode($accountNumber)
                . '-compact2.jpg?amount=' . rawurlencode($qrAmount) . '&addInfo=' . rawurlencode($transferContent);
        }

        return [
            'name' => 'VietQR',
            'qr_url' => $qrUrl,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_name' => trim((string) ($this->settings['bank_account_name'] ?? '')),
            'transfer_content' => $transferContent,
            'amount' => $amount,
            'amount_display' => $this->formatGatewayCurrency($amount, $method)
        ];
    }

    private function convertAmountForGateway(float $amount, string $paymentMethod): float
    {
        // Không quy đổi tỷ giá nữa, giữ nguyên giá trị gốc theo đơn vị tiền tệ hệ thống
        return $amount;
    }

    private function formatGatewayCurrency(float $amount, string $paymentMethod): string
    {
        // Vì không quy đổi tỷ giá, định dạng tiền tệ hiển thị sẽ đi theo chuẩn của hệ thống
        return $this->formatCurrency($amount);
    }

    private function getEnabledPaymentGateways(bool $includeBalance = true): array
    {
        $gateways = [];
        $baseCurrency = strtoupper(trim((string) ($this->settings['currency'] ?? 'VND')));

        $currentBalance = 0.0;
        if (isset($_SESSION['user_id'])) {
            $userModel = new User();
            $user = $userModel->findById((int) $_SESSION['user_id']);
            $currentBalance = (float) ($user['balance'] ?? 0);
        }

        if ($includeBalance) {
            $gateways[] = [
                'id' => 'balance',
                'name' => 'Thanh toán bằng số dư',
                'hint' => 'Số dư khả dụng: ' . $this->formatCurrency($currentBalance)
            ];
        }

        // Nếu tiền tệ hệ thống là VND thì chỉ hiển thị VietQR
        if ($baseCurrency === 'VND') {
            if (($this->settings['enable_vietqr'] ?? '0') === '1') {
                $bankLabel = trim((string) ($this->settings['bank_name'] ?? ''));
                $gateways[] = [
                    'id' => 'vietqr',
                    'name' => 'VietQR' . ($bankLabel !== '' ? ' - ' . $bankLabel : ''),
                    'hint' => 'Chuyển khoản ngân hàng qua mã QR.'
                ];
            }
        } 
        // Nếu tiền tệ hệ thống là CNY thì chỉ hiển thị WeChat và Alipay
        elseif ($baseCurrency === 'CNY') {
            if (($this->settings['enable_wechat'] ?? '0') === '1') {
                $gateways[] = [
                    'id' => 'wechat',
                    'name' => 'WeChat Pay',
                    'hint' => 'Thanh toán nhanh bằng ví WeChat.'
                ];
            }

            if (($this->settings['enable_alipay'] ?? '0') === '1') {
                $gateways[] = [
                    'id' => 'alipay',
                    'name' => 'Alipay',
                    'hint' => 'Thanh toán trực tuyến qua Alipay.'
                ];
            }
        }

        return $gateways;
    }

    private function calculateCouponPreview(int $planId, string $couponCode): array
    {
        if ($couponCode === '') {
            return [
                'valid' => false,
                'discount_amount' => 0,
                'final_amount' => 0,
                'message' => 'Vui lòng nhập mã giảm giá để xác nhận.'
            ];
        }

        $planModel = new VpnPlan();
        $plan = $planModel->find($planId);
        if (!$plan || ($plan['status'] ?? 'active') !== 'active') {
            return [
                'valid' => false,
                'discount_amount' => 0,
                'final_amount' => 0,
                'message' => 'Gói dịch vụ không còn khả dụng.'
            ];
        }

        $originalPrice = (float) ($plan['price'] ?? 0);
        $couponModel = new Coupon();
        $coupon = $couponModel->findByCode($couponCode);

        if (!$coupon || ($coupon['status'] ?? '') !== 'active') {
            return [
                'valid' => false,
                'discount_amount' => 0,
                'final_amount' => $originalPrice,
                'message' => 'Mã giảm giá không hợp lệ hoặc đang bị vô hiệu hóa.'
            ];
        }

        $isExpired = !empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) < time();
        $isMaxUsed = !empty($coupon['max_uses']) && ((int) ($coupon['used_count'] ?? 0) >= (int) ($coupon['max_uses']));
        if ($isExpired || $isMaxUsed) {
            return [
                'valid' => false,
                'discount_amount' => 0,
                'final_amount' => $originalPrice,
                'message' => 'Mã giảm giá đã hết hạn hoặc đã đạt giới hạn sử dụng.'
            ];
        }

        $discountType = $coupon['discount_type'] ?? 'percent';
        $discountValue = (float) ($coupon['discount_value'] ?? 0);
        $discountAmount = $discountType === 'percent'
            ? ($originalPrice * $discountValue) / 100
            : $discountValue;
        $discountAmount = min($discountAmount, $originalPrice);
        $finalAmount = max(0, $originalPrice - $discountAmount);

        $discountLabel = $discountType === 'percent'
    ? rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.') . '%'
    : $this->formatCurrency($discountValue);

        return [
            'valid' => true,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'message' => 'Áp dụng mã thành công (' . $discountLabel . ').'
        ];
    }

    public function subscriptions(): void
    {
        $userId = $_SESSION['user_id'];
        $subscriptions = [];

        if (class_exists('App\Models\Subscription')) {
            $subModel = new Subscription();
            $subscriptions = $subModel->getByUserId($userId);
        }

        $this->render('user.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'activeMenu' => 'subscriptions',
            'showSidebar' => true
        ]);
    }

    public function subscriptionDetail(): void
    {
        $subscription = $this->getOwnedSubscription((int) ($_GET['id'] ?? 0));
        if ($subscription === null) {
            $_SESSION['error'] = 'Không tìm thấy gói dịch vụ hoặc bạn không có quyền truy cập.';
            $this->redirect('/subscriptions');
            return;
        }

        $connectionData = $this->buildSubscriptionConnection($subscription);
        $this->render('user.subscriptions.detail', [
            'subscription' => $subscription,
            ...$connectionData,
            'activeMenu' => 'subscriptions',
            'showSidebar' => true
        ]);
    }

    private function buildSubscriptionConnection(array $subscription): array
    {
        $appConfig = require BASE_PATH . '/config/app.php';
        $subscriptionUrl = rtrim((string) ($appConfig['url'] ?? ''), '/')
            . '/sub?uuid=' . rawurlencode((string) ($subscription['uuid'] ?? ''));
        $connectionData = [
            'subscriptionUrl' => $subscriptionUrl,
            'inboundLinks' => [],
            'qrCodeDataUri' => ''
        ];

        $isActive = ($subscription['status'] ?? '') === 'active'
            && !empty($subscription['end_date'])
            && strtotime($subscription['end_date']) >= time();
        if (!$isActive) {
            return $connectionData;
        }

        $groupIds = json_decode((string) ($subscription['group_id'] ?? '[]'), true);
        if (!is_array($groupIds)) {
            $groupIds = !empty($subscription['group_id']) ? [(int) $subscription['group_id']] : [];
        }

        $nodeInboundModel = new NodeInbound();
        $vpnService = new VpnService();
        foreach ($nodeInboundModel->getAllActiveWithServer($groupIds) as $inbound) {
            $link = $vpnService->buildLink($inbound, (string) $subscription['uuid']);
            if ($link === null) {
                continue;
            }

            $connectionData['inboundLinks'][] = [
                'name' => $inbound['tag'] ?? ($inbound['server_name'] ?? 'VPN Server'),
                'protocol' => strtoupper((string) ($inbound['protocol'] ?? '')),
                'link' => $link
            ];
        }

        if (class_exists('\Endroid\QrCode\QrCode') && class_exists('\Endroid\QrCode\Writer\SvgWriter')) {
            try {
                $qrCode = \Endroid\QrCode\QrCode::create($subscriptionUrl)->setSize(280)->setMargin(10);
                $connectionData['qrCodeDataUri'] = (new \Endroid\QrCode\Writer\SvgWriter())->write($qrCode)->getDataUri();
            } catch (\Throwable) {
                $connectionData['qrCodeDataUri'] = '';
            }
        }

        return $connectionData;
    }

    private function getOwnedSubscription(int $subscriptionId): ?array
    {
        if ($subscriptionId <= 0 || !class_exists('App\Models\Subscription')) {
            return null;
        }

        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->findWithDetails($subscriptionId);

        if (!$subscription || (int) ($subscription['user_id'] ?? 0) !== (int) $_SESSION['user_id']) {
            return null;
        }

        return $subscription;
    }

    public function orders(): void
    {
        $userId = $_SESSION['user_id'];
        $orders = [];

        if (class_exists('App\Models\Order')) {
            $orderModel = new Order();
            $orders = $orderModel->getByUserId($userId);
        }

        $this->render('user.orders.index', [
            'orders' => $orders,
            'activeMenu' => 'orders',
            'showSidebar' => true
        ]);
    }

    public function orderDetail(): void
    {
        $orderId = (int) ($_GET['id'] ?? 0);
        $order = null;

        if ($orderId > 0 && class_exists('App\Models\Order')) {
            $orderModel = new Order();
            $candidate = $orderModel->findWithDetails($orderId);

            if ($candidate && (int) ($candidate['user_id'] ?? 0) === (int) $_SESSION['user_id']) {
                $order = $candidate;
            }
        }

        if ($order === null) {
            $_SESSION['error'] = 'Không tìm thấy đơn hàng hoặc bạn không có quyền truy cập.';
            $this->redirect('/orders');
            return;
        }

        $this->render('user.orders.detail', [
            'order' => $order,
            'activeMenu' => 'orders',
            'showSidebar' => true
        ]);
    }

    public function payments(): void
    {
        $userId = $_SESSION['user_id'];
        $payments = [];

        if (class_exists('App\Models\Payment')) {
            $paymentModel = new Payment();
            $payments = $paymentModel->getByUserId($userId);
        }

        $this->render('user.payments.index', [
            'payments' => $payments,
            'activeMenu' => 'payments'
        ]);
    }

    public function cancelDeposit(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $this->redirect('/payments');
            return;
        }

        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $cancelled = $paymentId > 0 && (new Payment())->cancelPendingDeposit(
            $paymentId,
            (int) $_SESSION['user_id']
        );

        $_SESSION[$cancelled ? 'success' : 'error'] = $cancelled
            ? 'Đã hủy giao dịch nạp tiền đang chờ.'
            : 'Không thể hủy giao dịch này.';
        $this->redirect('/payments');
    }

    public function wallet(): void
    {
        $userId = $_SESSION['user_id'];
        $user = [];

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            $user = $userModel->findById($userId);
        }

        $this->render('user.wallet.index', [
            'user' => $user,
            'activeMenu' => 'wallet'
        ]);
    }

    public function showDeposit(): void
    {
        $this->redirect('/checkout?type=deposit');
    }

    public function deposit(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
            $this->redirect('/checkout?type=deposit');
            return;
        }

        $amount = (float) ($_POST['amount'] ?? 0);
        $paymentGateway = trim((string) ($_POST['payment_gateway'] ?? 'vietqr'));

        $minDeposit = (float) (
            $this->settings['min_deposit'] ??
            $this->settings['min_deposit_amount'] ??
            (strtoupper(trim((string) ($this->settings['currency'] ?? 'CNY'))) === 'VND' ? 10000 : 10)
        );

        if ($amount < $minDeposit) {
            $_SESSION['error'] = 'Số tiền nạp tối thiểu là ' .
                $this->formatCurrency($minDeposit) . '.';
            $this->redirect('/checkout?type=deposit&amount=' . rawurlencode((string) $amount));
            return;
        }

        $allowedGateways = array_column($this->getEnabledPaymentGateways(false), 'id');
        if (!in_array($paymentGateway, $allowedGateways, true)) {
            $_SESSION['error'] = 'Cổng thanh toán không hợp lệ hoặc đang tạm tắt.';
            $this->redirect('/checkout?type=deposit&amount=' . rawurlencode((string) $amount));
            return;
        }

        $paymentService = new PaymentService();
        $result = $paymentService->createDepositTransaction(
            (int) $_SESSION['user_id'],
            $amount,
            $paymentGateway
        );

        if (($result['status'] ?? false) !== true) {
            $_SESSION['error'] = $result['message'] ?? 'Không thể tạo giao dịch nạp tiền.';
            $this->redirect('/payments/deposit');
            return;
        }

        $paymentId = (int) ($result['payment_id'] ?? 0);
        if ($paymentId <= 0 && !empty($result['transaction_code'])) {
            $paymentModel = new Payment();
            $created = $paymentModel->findByTransactionId((string) $result['transaction_code']);
            $paymentId = (int) ($created['id'] ?? 0);
        }

        if ($paymentId <= 0) {
            $_SESSION['error'] = 'Đã tạo giao dịch nhưng không xác định được giao dịch thanh toán.';
            $this->redirect('/payments');
            return;
        }

        $this->redirect('/payment/checkout?deposit=' . $paymentId);
    }

    public function walletDeposit(): void
    {
        $this->deposit();
    }

    public function referrals(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $user = [];
        $commissions = [];

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            $user = $userModel->findById($userId);
        }

        if (class_exists('App\Models\ReferralCommission')) {
            $commissions = (new ReferralCommission())->getByReferrerId($userId);
        }

        $minWithdrawal = (float) ($this->settings['min_withdrawal'] ?? 0);
        $pendingWithdrawal = 0.0;
        if (class_exists('App\Models\Withdrawal')) {
            $pendingWithdrawal = (new Withdrawal())->getPendingTotalByUserId($userId);
        }
        $availableCommission = max(0.0, (float) ($user['commission_balance'] ?? 0) - $pendingWithdrawal);

        $this->render('user.referrals.index', [
            'user' => $user,
            'commissions' => $commissions,
            'minWithdrawal' => $minWithdrawal,
            'pendingWithdrawal' => $pendingWithdrawal,
            'availableCommission' => $availableCommission,
            'activeMenu' => 'referrals'
        ]);
    }

    public function tickets(): void
    {
        $userId = $_SESSION['user_id'];
        $tickets = [];

        if (class_exists('App\Models\SupportTicket')) {
            $ticketModel = new SupportTicket();
            if (method_exists($ticketModel, 'getByUserId')) {
                $tickets = $ticketModel->getByUserId($userId);
            } elseif (method_exists($ticketModel, 'allWithDetails')) {
                $allTickets = $ticketModel->allWithDetails();
                $tickets = array_values(array_filter($allTickets, static function ($t) use ($userId) {
                    return (int)($t['user_id'] ?? 0) === (int)$userId;
                }));
            }
        }

        $this->render('user.tickets.index', [
            'tickets' => $tickets,
            'activeMenu' => 'tickets'
        ]);
    }

    public function createTicket(): void
    {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $this->redirect('/tickets/create');
            return;
        }

        if ($title === '' || $content === '') {
            $_SESSION['error'] = 'Vui lòng nhập đầy đủ tiêu đề và nội dung.';
            $this->redirect('/tickets/create');
            return;
        }

        $ticketModel = new SupportTicket();
        if (!$ticketModel->create([
            'user_id' => (int) $_SESSION['user_id'],
            'subject' => substr($title, 0, 255),
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s')
        ])) {
            $_SESSION['error'] = 'Không thể tạo yêu cầu hỗ trợ. Vui lòng thử lại.';
            $this->redirect('/tickets/create');
            return;
        }

        $ticketId = $ticketModel->lastInsertId();
        (new TicketMessage())->create([
            'ticket_id' => $ticketId,
            'sender_id' => (int) $_SESSION['user_id'],
            'message' => $content,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['success'] = 'Đã gửi yêu cầu hỗ trợ thành công.';
        $this->redirect('/tickets/detail?id=' . $ticketId);
    }

    public function showCreateTicket(): void
    {
        $this->render('user.tickets.create', ['activeMenu' => 'tickets']);
    }

    public function ticketDetail(): void
    {
        $ticketId = (int) ($_GET['id'] ?? 0);
        $ticket = (new SupportTicket())->findWithDetails($ticketId);
        if (!$ticket || (int) ($ticket['user_id'] ?? 0) !== (int) $_SESSION['user_id']) {
            $_SESSION['error'] = 'Không tìm thấy yêu cầu hỗ trợ.';
            $this->redirect('/tickets');
            return;
        }

        $this->render('user.tickets.detail', [
            'ticket' => $ticket,
            'messages' => (new TicketMessage())->getByTicketId($ticketId),
            'activeMenu' => 'tickets'
        ]);
    }

    public function replyTicket(): void
    {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $message = trim((string) ($_POST['message'] ?? ''));
        $ticket = (new SupportTicket())->find($ticketId);

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '') || !$ticket
            || (int) ($ticket['user_id'] ?? 0) !== (int) $_SESSION['user_id'] || $message === '') {
            $_SESSION['error'] = 'Không thể gửi phản hồi. Vui lòng kiểm tra lại nội dung.';
            $this->redirect('/tickets/detail?id=' . $ticketId);
            return;
        }

        (new TicketMessage())->create([
            'ticket_id' => $ticketId,
            'sender_id' => (int) $_SESSION['user_id'],
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        (new SupportTicket())->updateTicket($ticketId, ['status' => 'open']);

        $_SESSION['success'] = 'Đã gửi phản hồi.';
        $this->redirect('/tickets/detail?id=' . $ticketId);
    }

    public function closeTicket(): void
    {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $ticket = (new SupportTicket())->find($ticketId);

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '') || !$ticket
            || (int) ($ticket['user_id'] ?? 0) !== (int) $_SESSION['user_id']) {
            $_SESSION['error'] = 'Không thể đóng yêu cầu hỗ trợ này.';
            $this->redirect('/tickets');
            return;
        }

        (new SupportTicket())->updateTicket($ticketId, ['status' => 'closed']);
        $_SESSION['success'] = 'Đã hủy/đóng yêu cầu hỗ trợ #' . $ticketId . '.';
        $this->redirect('/tickets');
    }

    public function withdrawals(): void
    {
        $withdrawals = (new Withdrawal())->getByUserId((int) $_SESSION['user_id']);
        $this->render('user.withdrawals.index', [
            'withdrawals' => $withdrawals,
            'activeMenu' => 'withdrawals'
        ]);
    }

    public function showCreateWithdrawal(): void
    {
        $user = (new User())->findById((int) $_SESSION['user_id']);
        $this->render('user.withdrawals.create', [
            'user' => $user ?: [],
            'minWithdrawal' => (float) ($this->settings['min_withdrawal'] ?? 0),
            'activeMenu' => 'withdrawals'
        ]);
    }

    public function createWithdrawal(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/withdrawals');
            return;
        }

        $amount = (float) ($_POST['amount'] ?? 0);
        $minWithdrawal = (float) ($this->settings['min_withdrawal'] ?? 0);
        $withdrawType = trim((string) ($_POST['withdraw_type'] ?? 'bank')); // 'balance' hoặc 'bank'
        $bankName = trim((string) ($_POST['bank_name'] ?? ''));
        $accountNumber = trim((string) ($_POST['bank_account_number'] ?? ''));
        $accountName = trim((string) ($_POST['bank_account_name'] ?? ''));
        $userId = (int) $_SESSION['user_id'];
        $userModel = new User();
        $user = $userModel->findById($userId);
        $withdrawalModel = new Withdrawal();
        $available = (float) ($user['commission_balance'] ?? 0) - $withdrawalModel->getPendingTotalByUserId($userId);

        if ($amount <= 0 || $amount < $minWithdrawal || $amount > $available) {
            $_SESSION['error'] = 'Số tiền rút không hợp lệ (tối thiểu ' . (isset($this->settings['currency_symbol']) ? number_format($minWithdrawal) . $this->settings['currency_symbol'] : number_format($minWithdrawal)) . ') hoặc số dư hoa hồng không đủ.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/withdrawals');
            return;
        }

        // 1. Rút về số dư tài khoản chính (cộng tiền vào balance ngay lập tức, tự hoàn tất)
        if ($withdrawType === 'balance') {
            $userModel->update($userId, [
                'commission_balance' => (float) ($user['commission_balance'] ?? 0) - $amount,
                'balance' => (float) ($user['balance'] ?? 0) + $amount
            ]);

            $withdrawalModel->create([
                'user_id' => $userId,
                'amount' => $amount,
                'bank_name' => 'Ví số dư tài khoản',
                'bank_account_number' => $user['username'] ?? ('USER#' . $userId),
                'bank_account_name' => $user['email'] ?? 'Chuyển sang số dư',
                'status' => 'approved',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $_SESSION['balance'] = (float) ($user['balance'] ?? 0) + $amount;
            $_SESSION['commission_balance'] = (float) ($user['commission_balance'] ?? 0) - $amount;
            $_SESSION['success'] = 'Đã rút hoa hồng về số dư tài khoản thành công.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/referrals');
            return;
        }

        // 2. Rút về tài khoản ngân hàng (tạo lệnh chờ duyệt)
        if ($bankName === '' || $accountNumber === '' || $accountName === '') {
            $_SESSION['error'] = 'Vui lòng nhập đầy đủ thông tin tài khoản ngân hàng.';
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/withdrawals');
            return;
        }

        $withdrawalModel->create([
            'user_id' => $userId,
            'amount' => $amount,
            'bank_name' => $bankName,
            'bank_account_number' => $accountNumber,
            'bank_account_name' => $accountName,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['success'] = 'Yêu cầu rút hoa hồng về tài khoản ngân hàng đã được gửi và đang chờ xử lý.';
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/referrals');
    }

    public function notifications(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $notifications = NotificationService::getNotifications($userId);
        $unreadCount = NotificationService::getUnreadCount($userId);

        $this->render('user.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'activeMenu' => 'notifications'
        ]);
    }

    public function markNotificationAsRead(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $id = trim((string) ($_REQUEST['id'] ?? ''));
        if ($id !== '') {
            NotificationService::markAsRead($userId, $id);
        }

        $redirect = $_SERVER['HTTP_REFERER'] ?? '/notifications';
        $this->redirect($redirect);
    }

    public function markAllNotificationsAsRead(): void
    {
        $userId = (int) $_SESSION['user_id'];
        NotificationService::markAllAsRead($userId);
        $_SESSION['success'] = 'Đã đánh dấu tất cả thông báo là đã xem.';
        $this->redirect('/notifications');
    }

    public function deleteNotification(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $id = trim((string) ($_REQUEST['id'] ?? ''));
        if ($id !== '') {
            NotificationService::deleteNotification($userId, $id);
            $_SESSION['success'] = 'Đã xóa thông báo.';
        }

        $redirect = $_SERVER['HTTP_REFERER'] ?? '/notifications';
        $this->redirect($redirect);
    }

    public function clearAllNotifications(): void
    {
        $userId = (int) $_SESSION['user_id'];
        NotificationService::clearAll($userId);
        $_SESSION['success'] = 'Đã xóa tất cả thông báo.';
        $this->redirect('/notifications');
    }

    public function profile(): void
    {
        $userId = $_SESSION['user_id'];
        $user = [];

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            $user = $userModel->findById($userId);
        }

        $this->render('user.profile.index', [
            'user' => $user,
            'activeMenu' => 'profile'
        ]);
    }

    public function updateProfile(): void
    {
        $userId = $_SESSION['user_id'];
        $newPassword = trim($_POST['new_password'] ?? '');

        if (!empty($newPassword) && class_exists('App\Models\User')) {
            $userModel = new User();
            $userModel->update($userId, [
                'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)
            ]);
        }

        $_SESSION['success'] = 'Cập nhật thông tin cá nhân thành công.';
        $this->redirect('/user/profile');
    }
}