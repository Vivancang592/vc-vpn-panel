<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdminRoute = (strncmp($currentUri, '/admin', 6) === 0);
$userRole = $_SESSION['role'] ?? 'user';
$siteTitle = $settings['site_title'] ?? 'VC VPN 2027';
$activeMenu = $activeMenu ?? '';
$infrastructureMenus = ['server-groups', 'servers', 'nodes', 'plans'];
$businessMenus = ['coupons', 'orders', 'payments', 'subscriptions', 'referrals', 'withdrawals'];

$logoHref = '/';
if (isset($_SESSION['user_id'])) {
    if ($userRole === 'admin') {
        $logoHref = $isAdminRoute ? '/dashboard' : '/admin';
    } else {
        $logoHref = '/dashboard';
    }
}
?>
<aside class="admin-sidebar" style="background: rgba(180, 187, 213, 0.49);">
    <!-- Tên Web đặt trong Sidebar -->
    <div class="sidebar-brand" style="padding: 0.5rem 0.5rem 1rem 0.5rem; border-bottom: 1px solid var(--glass-border); margin-bottom: 0.75rem;">
        <a href="<?= $logoHref ?>" style="display: flex; align-items: center; text-decoration: none; font-weight: 800; font-size: 1.25rem; letter-spacing: 0.6px; background: linear-gradient(135deg, #FFCC00 0%, #FF9500 50%, #FF2D55 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0px 2px 10px rgba(255, 149, 0, 0.5));">
            <span><?= htmlspecialchars($siteTitle) ?></span>
        </a>
    </div>

    <div style="padding: 0.25rem 0.5rem; font-weight: 700; color: var(--ios-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
        Quản Trị Hệ Thống
    </div>
    <a href="/admin" class="nav-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">📊</span>Dashboard</a>
    <a href="/admin/users" class="nav-item <?= $activeMenu === 'users' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">👤</span>Người Dùng</a>
    <a href="/admin/server-groups" class="nav-item <?= in_array($activeMenu, $infrastructureMenus, true) ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">🗄️</span>Quản Lý Hạ Tầng VPN</a>
    <a href="/admin/coupons" class="nav-item <?= in_array($activeMenu, $businessMenus, true) ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">💳</span>Quản Lý Kinh Doanh</a>
    <a href="/admin/tickets" class="nav-item <?= $activeMenu === 'tickets' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">🎟️</span>Ticket Hỗ Trợ</a>
    <a href="/admin/posts" class="nav-item <?= $activeMenu === 'posts' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">🗞️</span>Bài Viết</a>
    <a href="/admin/expenses" class="nav-item <?= $activeMenu === 'expenses' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">📉</span>Chi Phí</a>
    <a href="/admin/settings" class="nav-item <?= $activeMenu === 'settings' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">⚙️</span>Cài Đặt</a>
    <a href="/admin/logs" class="nav-item <?= $activeMenu === 'logs' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">📋</span>Nhật Ký Hệ Thống</a>
</aside>
