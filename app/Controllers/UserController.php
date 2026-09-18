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
use App\Services\VpnService;
use App\Services\OrderService;
use App\Services\PaymentService;

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
                10
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
                (int) $_SESSION['user_id'], $subscriptionId, $paymentGateway
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
        $result = $orderService->createOrder((int) $_SESSION['user_id'], $planId, $couponCode !== '' ? $couponCode : null, $paymentGateway);

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

        if ($method === 'wechat') {
            return [
                'name' => 'WeChat Pay',
                'qr_url' => trim((string) ($this->settings['wechat_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['wechat_account_name'] ?? '')),
                'transfer_content' => $orderCode,
                'amount_display' => $this->formatCurrency($totalAmount)
            ];
        }

        if ($method === 'alipay') {
            return [
                'name' => 'Alipay',
                'qr_url' => trim((string) ($this->settings['alipay_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['alipay_account_name'] ?? '')),
                'transfer_content' => $orderCode,
                'amount_display' => $this->formatCurrency($totalAmount)
            ];
        }

        $bankName = trim((string) ($this->settings['bank_name'] ?? ''));
        $accountNumber = trim((string) ($this->settings['bank_account_number'] ?? ''));
        $transferContent = trim((string) ($this->settings['order_transfer_syntax'] ?? 'THANHTOAN')) . ' ' . $orderCode;
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
            'amount_display' => $this->formatCurrency($totalAmount)
        ];
    }

    private function buildDepositPaymentInstructions(array $payment): array
    {
        $method = (string) ($payment['payment_method'] ?? 'vietqr');
        $amount = (float) ($payment['amount'] ?? 0);
        $transactionCode = (string) ($payment['transaction_id'] ?? '');
        $syntax = trim((string) ($this->settings['bank_transfer_syntax'] ?? 'NAPTIEN'));
        $transferContent = trim($syntax . ' ' . $transactionCode);

        if ($method === 'wechat') {
            return [
                'name' => 'WeChat Pay',
                'qr_url' => trim((string) ($this->settings['wechat_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['wechat_account_name'] ?? '')),
                'transfer_content' => $transactionCode,
                'amount_display' => $this->formatCurrency($amount)
            ];
        }

        if ($method === 'alipay') {
            return [
                'name' => 'Alipay',
                'qr_url' => trim((string) ($this->settings['alipay_qr_image'] ?? '')),
                'account_name' => trim((string) ($this->settings['alipay_account_name'] ?? '')),
                'transfer_content' => $transactionCode,
                'amount_display' => $this->formatCurrency($amount)
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
            'amount_display' => $this->formatCurrency($amount)
        ];
    }

    private function getEnabledPaymentGateways(bool $includeBalance = true): array
    {
        $gateways = [];

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

        if (($this->settings['enable_vietqr'] ?? '0') === '1') {
            $bankLabel = trim((string) ($this->settings['bank_name'] ?? ''));
            $gateways[] = [
                'id' => 'vietqr',
                'name' => 'VietQR' . ($bankLabel !== '' ? ' - ' . $bankLabel : ''),
                'hint' => 'Chuyển khoản ngân hàng qua mã QR.'
            ];
        }

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
            10
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
        $userId = $_SESSION['user_id'];
        $user = [];

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            $user = $userModel->findById($userId);
        }

        $this->render('user.referrals.index', [
            'user' => $user,
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

        if (empty($title) || empty($content)) {
            $_SESSION['error'] = 'Vui lòng nhập đầy đủ tiêu đề và nội dung.';
            $this->redirect('/user/tickets');
        }

        $_SESSION['success'] = 'Đã gửi yêu cầu hỗ trợ thành công.';
        $this->redirect('/user/tickets');
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