<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Order;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Server;
use App\Models\NodeInbound;

class DashboardController extends BaseController
{
    private Order $orderModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->orderModel = new Order();
    }

    public function index(): void
    {
        $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

        // Tính toán tháng và năm kế trước để so sánh
        $lastMonth = ($selectedMonth === 1) ? 12 : $selectedMonth - 1;
        $lastMonthYear = ($selectedMonth === 1) ? $selectedYear - 1 : $selectedYear;

        // Lấy chuỗi doanh thu từng ngày trong tháng hiện tại và tháng trước
        $currentMonthData = $this->orderModel->getDailyRevenueByMonth($selectedYear, $selectedMonth);
        $lastMonthData = $this->orderModel->getDailyRevenueByMonth($lastMonthYear, $lastMonth);

        // Lấy các chỉ số thống kê
        $monthlyRevenue = $this->orderModel->getMonthlyRevenue($selectedYear, $selectedMonth);
        $recentOrders = $this->orderModel->getRecentOrders(5);

        $userModel = new User();
        $subscriptionModel = new Subscription();
        $serverModel = new Server();
        $inboundModel = new NodeInbound();

        $stats = [
            'total_users'          => method_exists($userModel, 'countAll') ? $userModel->countAll() : 0,
            'new_users_today'      => method_exists($userModel, 'countToday') ? $userModel->countToday() : 0,
            'active_subscriptions' => $subscriptionModel->countActive(),
            'monthly_revenue'      => $monthlyRevenue,
            'active_servers'       => method_exists($serverModel, 'countActive') ? $serverModel->countActive() : 0,
            'total_servers'        => method_exists($serverModel, 'countAll') ? $serverModel->countAll() : 0,
            'active_inbounds'      => method_exists($inboundModel, 'countActive') ? $inboundModel->countActive() : 0,
            'total_inbounds'       => method_exists($inboundModel, 'countAll') ? $inboundModel->countAll() : 0,
        ];

        $servers = method_exists($serverModel, 'getAll') ? $serverModel->getAll() : [];

        $this->render('admin.dashboard', [
            'activeMenu' => 'dashboard',
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'servers' => $servers,
            'monthlyChart' => [
                'current_month' => $currentMonthData,
                'last_month' => $lastMonthData
            ],
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth
        ]);
    }
}