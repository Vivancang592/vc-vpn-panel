<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdminRoute = (strncmp($currentUri, '/admin', 6) === 0);
$userRole = $_SESSION['role'] ?? 'user';
$siteTitle = $settings['site_title'] ?? 'VC VPN PANEL';
$activeMenu = $activeMenu ?? '';
$infrastructureMenus = ['server-groups', 'servers', 'nodes', 'plans'];
$businessMenus = ['coupons', 'orders', 'payments', 'subscriptions', 'referrals', 'withdrawals'];
// 6 tab Phase C + id trang kỹ thuật (tasks/models/conversations/outputs)
// để group "Trung Tâm AI" luôn highlight đúng activeMenu.
// ai-modules/ai-prompts đã gộp vào ai-settings; ai-assets đã XOÁ (trang + route).
$aiMenus = ['ai-dashboard', 'ai-image', 'ai-tasks', 'ai-video', 'ai-dubbing', 'ai-fanpage', 'ai-reply', 'ai-settings', 'ai-models', 'ai-conversations', 'ai-outputs'];

$logoHref = '/';
if (isset($_SESSION['user_id'])) {
    if ($userRole === 'admin') {
        $logoHref = $isAdminRoute ? '/dashboard' : '/admin';
    } else {
        $logoHref = '/dashboard';
    }
}
?>
<aside class="admin-sidebar">
    <!-- Tên Web đặt trong Sidebar -->
    <div class="sidebar-brand">
        <a href="<?= $logoHref ?>">
            <img src="/assets/images/icon.svg" alt="" width="24" height="24" class="sidebar-brand-mark" aria-hidden="true">
            <span><?= htmlspecialchars($siteTitle) ?></span>
        </a>
    </div>

    <div class="sidebar-section-title">
        Quản Trị Hệ Thống
    </div>
    <hr class="sidebar-divider">
    <?php if ($userRole === 'admin'): ?>
    <a href="/admin" class="nav-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">📊</span>Dashboard</a>
    <a href="/admin/settings" class="nav-item <?= $activeMenu === 'settings' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">⚙️</span>Cài Đặt Hệ Thống</a>
    <a href="/admin/server-groups" class="nav-item <?= in_array($activeMenu, $infrastructureMenus, true) ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">🗄️</span>Q.Lý Hạ Tầng</a>
    <a href="/admin/users" class="nav-item <?= $activeMenu === 'users' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">👤</span>Q.Lý Người Dùng</a>    
    <a href="/admin/coupons" class="nav-item <?= in_array($activeMenu, $businessMenus, true) ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">💳</span>Q.Lý Kinh Doanh</a>
    <a href="/admin/tickets" class="nav-item <?= $activeMenu === 'tickets' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">🎟️</span>Q.Lý Ticket</a>
    <a href="/admin/ai" class="nav-item <?= in_array($activeMenu, $aiMenus, true) ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">🤖</span>Trung Tâm AI</a>
    <a href="/admin/posts" class="nav-item <?= $activeMenu === 'posts' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">🗞️</span>Q.Lý Bài Viết</a>
    <a href="/admin/expenses" class="nav-item <?= $activeMenu === 'expenses' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">📉</span>Q.Lý Chi Phí</a>    
    <a href="/admin/logs" class="nav-item <?= $activeMenu === 'logs' ? 'active' : '' ?>"><span class="sidebar-nav-icon" aria-hidden="true">📋</span>Nhật Ký Hệ Thống</a>
    <?php endif; ?>
</aside>
