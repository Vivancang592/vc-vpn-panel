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
use App\Services\VpnService;

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
        if (class_exists('App\Models\VpnPlan')) {
            $planModel = new VpnPlan();
            $plans = $planModel->getAllActive();
        }

        $this->render('user.plans.index', [
            'plans' => $plans,
            'activeMenu' => 'plans'
        ]);
    }

    public function checkout(): void
    {
        $planId = (int)($_GET['id'] ?? 0);
        $plan = [];

        if ($planId > 0 && class_exists('App\Models\VpnPlan')) {
            $planModel = new VpnPlan();
            $plan = $planModel->find($planId);
        }

        if (empty($plan)) {
            $this->redirect('/user/plans');
        }

        $this->render('user.plans.checkout', [
            'plan' => $plan,
            'activeMenu' => 'plans'
        ]);
    }

    public function buyPlan(): void
    {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $couponCode = trim($_POST['coupon_code'] ?? '');

        if ($planId <= 0) {
            $_SESSION['error'] = 'Gói dịch vụ không hợp lệ.';
            $this->redirect('/user/plans');
        }

        $_SESSION['success'] = 'Đăng ký gói dịch vụ thành công!';
        $this->redirect('/user/subscriptions');
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

        if (class_exists('\Endroid\QrCode\QrCode') && class_exists('\Endroid\QrCode\Writer\PngWriter')) {
            try {
                $qrCode = \Endroid\QrCode\QrCode::create($subscriptionUrl)->setSize(280)->setMargin(10);
                $connectionData['qrCodeDataUri'] = (new \Endroid\QrCode\Writer\PngWriter())->write($qrCode)->getDataUri();
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

    public function deposit(): void
    {
        $amount = (float)($_POST['amount'] ?? 0);
        $minDeposit = (float)($this->settings['min_deposit'] ?? $this->settings['min_deposit_amount'] ?? 10);
        $symbol = $this->settings['currency_symbol'] ?? '¥';

        if ($amount < $minDeposit) {
            $_SESSION['error'] = 'Số tiền nạp tối thiểu là ' . number_format($minDeposit, 2, '.', ',') . ' ' . $symbol . '.';
            $this->redirect('/user/wallet');
            return;
        }

        $_SESSION['success'] = 'Yêu cầu nạp tiền đã tạo. Vui lòng hoàn tất thanh toán.';
        $this->redirect('/user/payments');
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