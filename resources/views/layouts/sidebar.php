<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
$isAdminRoute = (strncmp($currentUri, '/admin', 6) === 0);
$userRole = $_SESSION['role'] ?? 'user';
$siteTitle = $settings['site_title'] ?? 'VC VPN 2027';

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
    <div class="sidebar-brand" style="padding: 0.5rem 0.5rem 1rem 0.5rem; border-bottom: 1px solid var(--glass-border); margin-bottom: 0.75rem;">
        <a href="<?= $logoHref ?>" title="<?= ($userRole === 'admin') ? ($isAdminRoute ? 'Chuyển sang Trang User' : 'Chuyển sang Trang Admin') : 'Trang Chủ' ?>" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: var(--ios-text); font-weight: 700; font-size: 1.1rem;">
            <?php if ($userRole !== 'admin'): ?><span class="festival-moon-mark" aria-hidden="true"></span><?php endif; ?>
            <span><?= htmlspecialchars($siteTitle) ?></span>
        </a>
    </div>

    <div style="padding: 0.25rem 0.5rem; font-weight: 700; color: var(--ios-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
        Menu Khách Hàng
    </div>
    <a href="/dashboard" class="nav-item <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-moon" aria-hidden="true"></span><span>Dashboard</span>
    </a>
    <a href="/user/plans" class="nav-item <?= ($activeMenu ?? '') === 'plans' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-lantern" aria-hidden="true"></span><span>Gói Dịch Vụ</span>
    </a>
    <a href="/faq" class="nav-item <?= $currentPath === '/faq' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-scroll" aria-hidden="true"></span><span>Hướng Dẫn</span>
    </a>
    <a href="/download" class="nav-item <?= $currentPath === '/download' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-download" aria-hidden="true"></span><span>Tải Ứng Dụng</span>
    </a>
    <a href="/subscriptions" class="nav-item <?= ($activeMenu ?? '') === 'subscriptions' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-spark" aria-hidden="true"></span><span>Gói Đã Mua</span>
    </a>
    <a href="/orders" class="nav-item <?= ($activeMenu ?? '') === 'orders' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-scroll" aria-hidden="true"></span><span>Đơn Hàng</span>
    </a>
    <a href="/payments" class="nav-item <?= ($activeMenu ?? '') === 'payments' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-coin" aria-hidden="true"></span><span>Lịch Sử Giao Dịch</span>
    </a>
    <a href="/wallet" class="nav-item <?= ($activeMenu ?? '') === 'wallet' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-wallet" aria-hidden="true"></span><span>Ví Tiền</span>
    </a>
    <a href="/referrals" class="nav-item <?= ($activeMenu ?? '') === 'referrals' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-gift" aria-hidden="true"></span><span>Tiếp Thị Liên Kết</span>
    </a>
    <a href="/tickets" class="nav-item <?= ($activeMenu ?? '') === 'tickets' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-chat" aria-hidden="true"></span><span>Hỗ Trợ</span>
    </a>
    <a href="/profile" class="nav-item <?= ($activeMenu ?? '') === 'profile' ? 'active' : '' ?>">
        <span class="festival-nav-icon festival-nav-user" aria-hidden="true"></span><span>Tài Khoản</span>
    </a>
</aside>