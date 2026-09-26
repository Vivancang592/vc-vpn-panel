<?php 
$extraCss = $extraCss ?? 'admin';
$extraJs = $extraJs ?? 'app';
// Phân vùng khu vực User: các trang có sidebar (dashboard, orders, wallet...)
// nhận body class "user-area" để nạp user.css & scope CSS riêng, không ảnh
// hưởng admin / home / policies / auth.
$pageArea = !empty($showSidebar) ? 'user' : ($pageArea ?? '');
require_once __DIR__ . '/header.php'; 
?>

<?php if (($pageArea ?? '') === 'user'): ?>
    <!-- Sprite icon SVG dùng chung cho khu vực user (nạp 1 lần) -->
    <?php require_once BASE_PATH . '/resources/views/components/icons.php'; ?>
<?php endif; ?>

<!-- Màn hình chờ Đang tải... -->
<div id="page-preloader">
    <div class="preloader-spinner"></div>
    <div class="preloader-text">Đang tải...</div>
</div>

<div class="admin-app">
    <div class="sidebar-overlay"></div>

    <?php if (isset($showSidebar) && $showSidebar): ?>
        <?php require_once __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    
    <div class="admin-main-wrapper">
        <?php require_once __DIR__ . '/navbar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-content">
                <?= $content ?? '' ?>
            </div>
            <?php require_once __DIR__ . '/footer.php'; ?>
        </main>
    </div>
</div>