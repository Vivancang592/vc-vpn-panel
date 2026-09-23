<?php 
$extraCss = $extraCss ?? 'app';
$extraJs = $extraJs ?? 'app';
require_once __DIR__ . '/header.php'; 
?>

<!-- Màn hình chờ Đang tải... -->
<div id="page-preloader">
    <div class="preloader-spinner"></div>
    <div class="preloader-text">Đang tải...</div>
</div>

<div class="customer-app-shell admin-app">
    <div class="sidebar-overlay"></div>

    <?php if (isset($showSidebar) && $showSidebar): ?>
        <?php require_once __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    
    <div class="customer-workspace admin-main-wrapper">
        <?php require_once __DIR__ . '/navbar.php'; ?>
        
        <main class="customer-main admin-main">
            <div class="customer-content admin-content">
                <?= $content ?? '' ?>
            </div>
            <?php require_once __DIR__ . '/footer.php'; ?>
        </main>
    </div>
</div>