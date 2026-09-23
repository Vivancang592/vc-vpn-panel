<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN 2027';
$homeAnchorPrefix = ($activeMenu ?? '') === 'home' ? '' : '/';
?>
<header class="site-header">
<nav class="navbar-container" aria-label="Điều hướng chính">
    <div class="nav-cluster">
        <?php if (isset($_SESSION['user_id'])): ?>
            <button id="sidebar-toggle" type="button" aria-label="Toggle Sidebar" class="mobile-only-btn nav-icon-button">
                ☰
            </button>
        <?php else: ?>
            <a href="/" class="nav-brand">
                <strong><?= htmlspecialchars($siteTitle) ?></strong>
            </a>
        <?php endif; ?>
    </div>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="nav-public-links">
            <a href="/" class="nav-public-link">Trang chủ</a>
            <a href="<?= $homeAnchorPrefix ?>#bang-gia" class="nav-public-link">Sản phẩm</a>
            <a href="/faq" class="nav-public-link">Hướng dẫn</a>
            <a href="<?= $homeAnchorPrefix ?>#cau-hoi-thuong-gap" class="nav-public-link">Câu hỏi thường gặp</a>
            <a href="/download" class="nav-public-link">Tải ứng dụng</a>
        </div>
    <?php endif; ?>

    <div class="nav-cluster">
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php
                $currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                $isAdminRoute = (strncmp($currentUri, '/admin', 6) === 0);
                $notifUid = (int) $_SESSION['user_id'];

                if ($isAdminRoute && ($_SESSION['role'] ?? '') === 'admin') {
                    $notifHref = '/admin/notifications';
                    $notifTitle = 'Thông báo Quản trị';
                    $notifCount = class_exists(\App\Services\NotificationService::class)
                        ? \App\Services\NotificationService::getAdminPendingCount($notifUid)
                        : 0;
                } else {
                    $notifHref = '/notifications';
                    $notifTitle = 'Thông báo';
                    $notifCount = class_exists(\App\Services\NotificationService::class) 
                        ? \App\Services\NotificationService::getUnreadCount($notifUid) 
                        : 0;
                }
            ?>
            <a href="<?= $notifHref ?>" title="<?= htmlspecialchars($notifTitle) ?>" class="notification-link">
                🔔
                <?php if ($notifCount > 0): ?>
                    <span class="notification-badge">
                        <?= $notifCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <div class="profile-dropdown-wrapper">
                <button type="button" id="profile-dropdown-btn" class="profile-trigger">
                    <span class="profile-avatar">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                    </span>
                    <span class="profile-caret">▼</span>
                </button>

                <div id="profile-dropdown-menu" class="profile-menu glass-card">
                    <div class="profile-menu-section">
                        <div class="profile-menu-name">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'N/A') ?>
                        </div>
                        <div class="profile-meta">
                            Email: <?= htmlspecialchars($_SESSION['email'] ?? 'Chưa cập nhật email') ?>
                        </div>
                    </div>

                    <div class="profile-stats">
                        <div class="profile-stat">
                            <span class="profile-label">Số dư ví:</span>
                            <span class="profile-value-success"><?= isset($formatMoney) ? $formatMoney($_SESSION['balance'] ?? 0) : number_format($_SESSION['balance'] ?? 0, 2) ?></span>
                        </div>
                        <div class="profile-stat">
                            <span class="profile-label">Số dư hoa hồng:</span>
                            <span class="profile-value-warning"><?= isset($formatMoney) ? $formatMoney($_SESSION['commission_balance'] ?? 0) : number_format($_SESSION['commission_balance'] ?? 0, 2) ?></span>
                        </div>
                        <div class="profile-stat">
                            <span class="profile-label">Đăng ký:</span>
                            <span class="profile-value"><?= !empty($_SESSION['created_at']) ? date('d/m/Y', strtotime($_SESSION['created_at'])) : 'N/A' ?></span>
                        </div>
                    </div>

                    <div class="profile-logout-wrap">
                        <a href="/logout" class="profile-logout">
                            🚪 Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="guest-desktop-actions">
                <a href="/login" class="guest-action">Đăng nhập</a>
                <a href="/register" class="guest-action guest-action-secondary">Đăng ký</a>
            </div>

            <div class="guest-mobile-wrapper">
                <button type="button" id="guest-menu-btn" class="guest-mobile-trigger nav-icon-button">
                    ☰
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div id="guest-dropdown-menu" class="guest-mobile-menu glass-card">
            <a href="/" class="guest-nav-link">Trang chủ</a>
            <a href="<?= $homeAnchorPrefix ?>#bang-gia" class="guest-nav-link">Sản phẩm</a>
            <a href="/faq" class="guest-nav-link">Hướng dẫn</a>
            <a href="<?= $homeAnchorPrefix ?>#cau-hoi-thuong-gap" class="guest-nav-link">Câu hỏi thường gặp</a>
            <a href="/download" class="guest-nav-link">Tải ứng dụng</a>
            <div class="guest-auth-group">
                <a href="/login" class="guest-auth-link">Đăng nhập</a>
                <a href="/register" class="glass-btn guest-auth-link">Đăng ký</a>
            </div>
        </div>
    <?php endif; ?>
</nav>
</header>