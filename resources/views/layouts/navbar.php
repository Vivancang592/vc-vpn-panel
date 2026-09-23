<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN 2027';
$homeAnchorPrefix = ($activeMenu ?? '') === 'home' ? '' : '/';
?>
<nav class="glass-card navbar-container" style="margin: 0; padding: 0.875rem 1.5rem; border-radius: 0; border-top: none; border-left: none; border-right: none; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; width: 100%; position: relative; z-index: 1000; background: rgba(180, 187, 213, 0.49);">
    <!-- Bên trái: Toggle Sidebar Mobile (khi đã đăng nhập) OR Tên Web (khi chưa đăng nhập) -->
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <?php if (isset($_SESSION['user_id'])): ?>
            <button id="sidebar-toggle" type="button" aria-label="Toggle Sidebar" style="background: transparent; border: none; font-size: 1.3rem; color: var(--ios-text); cursor: pointer; padding: 0.25rem 0.5rem; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm);" class="mobile-only-btn">
                ☰
            </button>
        <?php else: ?>
            <a href="/" style="font-weight: 700; font-size: 1.05rem; color: var(--ios-text); text-decoration: none; display: flex; align-items: center; gap: 0.4rem;">
                <span style="color: var(--ios-blue);"><?= htmlspecialchars($siteTitle) ?></span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Giữa: Nút điều hướng các trang công khai (Desktop) -->
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="nav-public-links">
            <a href="/" class="nav-public-link">Trang chủ</a>
            <a href="<?= $homeAnchorPrefix ?>#bang-gia" class="nav-public-link">Sản phẩm</a>
            <a href="/faq" class="nav-public-link">Hướng dẫn</a>
            <a href="<?= $homeAnchorPrefix ?>#cau-hoi-thuong-gap" class="nav-public-link">Câu hỏi thường gặp</a>
            <a href="/download" class="nav-public-link">Tải ứng dụng</a>
        </div>
    <?php endif; ?>

    <!-- Bên phải: Profile khi đã đăng nhập OR Đăng nhập/Đăng ký khi chưa đăng nhập -->
    <div style="display: flex; align-items: center; gap: 0.75rem;">
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
            <a href="<?= $notifHref ?>" title="<?= htmlspecialchars($notifTitle) ?>" style="position: relative; display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: rgba(255, 255, 255, 0.25); border: 1px solid var(--glass-border); color: var(--ios-text); text-decoration: none; font-size: 1rem; transition: var(--transition);">
                🔔
                <?php if ($notifCount > 0): ?>
                    <span style="position: absolute; top: -3px; right: -3px; min-width: 17px; height: 17px; padding: 0 4px; background: var(--ios-danger, #ff3b30); color: #fff; font-size: 0.65rem; font-weight: 700; border-radius: 9999px; display: flex; align-items: center; justify-content: center; line-height: 1;">
                        <?= $notifCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <div class="profile-dropdown-wrapper" style="position: relative; z-index: 1001;">
                <button type="button" id="profile-dropdown-btn" class="profile-trigger" style="display: flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.25); border: 1px solid var(--glass-border); padding: 0.4rem 0.8rem; border-radius: 9999px; cursor: pointer; color: var(--ios-text); font-weight: 600; font-size: 0.85rem;">
                    <span style="width: 24px; height: 24px; border-radius: 50%; background: var(--ios-blue); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                    </span>
                    <span style="font-size: 0.7rem; color: var(--ios-text-secondary);">▼</span>
                </button>

                <div id="profile-dropdown-menu" class="profile-menu glass-card">
                    <div style="border-bottom: 1px solid var(--glass-border); padding-bottom: 0.4rem;">
                        <div style="font-weight: 700; font-size: 0.95rem; color: var(--ios-text);">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'N/A') ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.15rem; word-break: break-all;">
                            Email: <?= htmlspecialchars($_SESSION['email'] ?? 'Chưa cập nhật email') ?>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.825rem; margin-top: 0.4rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--glass-border);">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--ios-text-secondary);">Số dư ví:</span>
                            <span style="font-weight: 700; color: var(--ios-success);"><?= isset($formatMoney) ? $formatMoney($_SESSION['balance'] ?? 0) : number_format($_SESSION['balance'] ?? 0, 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--ios-text-secondary);">Số dư hoa hồng:</span>
                            <span style="font-weight: 700; color: var(--ios-warning);"><?= isset($formatMoney) ? $formatMoney($_SESSION['commission_balance'] ?? 0) : number_format($_SESSION['commission_balance'] ?? 0, 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--ios-text-secondary);">Đăng ký:</span>
                            <span style="font-weight: 500; color: var(--ios-text);"><?= !empty($_SESSION['created_at']) ? date('d/m/Y', strtotime($_SESSION['created_at'])) : 'N/A' ?></span>
                        </div>
                    </div>

                    <div style="margin-top: 0.5rem;">
                        <a href="/logout" style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; width: 100%; color: var(--ios-danger); text-decoration: none; font-size: 0.85rem; font-weight: 700; padding: 0.5rem; background: rgba(255, 59, 48, 0.1); border-radius: var(--radius-sm); transition: var(--transition);">
                            🚪 Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Desktop view -->
            <div class="guest-desktop-actions">
                <a href="/login" style="color: #007aff; text-decoration: none; font-size: 0.85rem; font-weight: 600;">Đăng nhập</a>
                <a href="/register" style="color: var(--ios-warning); text-decoration: none; font-size: 0.85rem; font-weight: 600;">Đăng ký</a>
            </div>

            <!-- Mobile view: Nút Toggle Menu Khách -->
            <div class="guest-mobile-wrapper">
                <button type="button" id="guest-menu-btn" class="guest-mobile-trigger" style="background: transparent; border: 1px solid #d6b5b500; padding: 0.4rem 0.7rem; border-radius: var(--radius-sm); color: var(--ios-text); cursor: pointer; font-size: 1.1rem; align-items: center; justify-content: center;">
                    ☰
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mobile Dropdown Menu nằm sát bên dưới Navbar hiển thị dạng 2 cột -->
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div id="guest-dropdown-menu" class="guest-mobile-menu glass-card">
            <a href="/" class="guest-nav-link">Trang chủ</a>
            <a href="<?= $homeAnchorPrefix ?>#bang-gia" class="guest-nav-link">Sản phẩm</a>
            <a href="/faq" class="guest-nav-link">Hướng dẫn</a>
            <a href="<?= $homeAnchorPrefix ?>#cau-hoi-thuong-gap" class="guest-nav-link">Câu hỏi thường gặp</a>
            <a href="/download" class="guest-nav-link">Tải ứng dụng</a>
            <div class="guest-auth-group">
                <a href="/login" style="color: var(--ios-text); text-decoration: none; font-size: 0.85rem; font-weight: 600; text-align: center; padding: 0.5rem; background: rgba(239, 171, 12, 0.85); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center;">Đăng nhập</a>
                <a href="/register" class="glass-btn" style="padding: 0.5rem; font-size: 0.85rem; text-align: center; width: 100%;">Đăng ký</a>
            </div>
        </div>
    <?php endif; ?>
</nav>