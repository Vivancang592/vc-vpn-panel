<?php 
$extraCss = $extraCss ?? 'admin';
$extraJs = $extraJs ?? 'app';
// Phân vùng khu vực User: các trang có sidebar (dashboard, orders, wallet...)
// nhận body class "user-area" để nạp user.css & scope CSS riêng, không ảnh
// hưởng admin / home / policies / auth.
$pageArea = !empty($showSidebar) ? 'user' : ($pageArea ?? '');

// ==== PARTIAL-LOAD (xem public/assets/js/app.js) =========================
// Nhận header X-VC-Partial: trả JSON fragment (khung trang) thay vì HTML
// đầy đủ — điều hướng nội bộ mượt mà, không reload trang.
// Layout chung của MỌI trang public/user (home, policies, guides, orders...).
if (($_SERVER['HTTP_X_VC_PARTIAL'] ?? '') === '1') {
    ob_start();
    if (($pageArea ?? '') === 'user') {
        require_once BASE_PATH . '/resources/views/components/icons.php';
    }
    echo $content ?? '';
    $fragmentHtml = ob_get_clean();

    $fragmentSidebar = '';
    if (!empty($showSidebar)) {
        ob_start();
        require_once __DIR__ . '/sidebar.php';
        $fragmentSidebar = ob_get_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'title' => $pageTitle ?? 'VC VPN 2027',
        'html' => $fragmentHtml,
        'sidebar' => $fragmentSidebar,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
// ==========================================================================

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