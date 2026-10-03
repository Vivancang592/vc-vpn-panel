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

    // Fragment KHÔNG kèm footer.php → script extraJs (vd home.js) phải tự
    // đi theo để runScripts nạp sau swap; nếu thiếu, trang home trống vì
    // .home-reveal không được gắn is-visible (không có ai chạy reveal).
    if (!empty($extraJs) && $extraJs !== 'app') {
        $vcExtraJsPath = BASE_PATH . '/public/assets/js/' . $extraJs . '.js';
        $vcExtraJsVer = @filemtime($vcExtraJsPath) ?: 1;
        $fragmentHtml .= '<script src="/assets/js/' . rawurlencode($extraJs) . '.js?v=' . $vcExtraJsVer . '"></script>';
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

// Bài thông báo popup (type=popup, đã xuất bản) — chỉ render khi user đăng
// nhập; hiện 1 lần mỗi lần nạp trang đầy đủ (login/tải lại), KHÔNG re-trigger
// khi partial-nav (markup nằm ngoài .admin-content nên fragment không đụng tới).
$vcNoticePopups = [];
if (isset($_SESSION['user_id'])) {
    try {
        $vcNoticePopups = (new \App\Models\Post())->getPublishedPopups();
    } catch (\Throwable $e) {
        $vcNoticePopups = [];
    }
}

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

    <?php if (!empty($vcNoticePopups)): ?>
        <!-- Popup thông báo: ngoài .admin-content → partial-nav không đụng tới,
             app.js hiện sau khi preloader ẩn, đóng theo lượt từng bài. -->
        <div id="vc-notice-popups" hidden>
            <?php foreach ($vcNoticePopups as $vcNp): ?>
                <?php
                // Thumbnail admin tải lên (marker ẩn trong content) → làm nền card
                $vcNpThumb = '';
                if (preg_match('/<!--thumbnail:(.*?)-->/is', (string)$vcNp['content'], $vcNpM)) {
                    $vcNpThumb = trim(htmlspecialchars_decode($vcNpM[1]));
                }
                ?>
                <div class="vc-notice-popup" role="dialog" aria-modal="true" aria-label="Thông báo hệ thống">
                    <div class="vc-notice-backdrop" data-vc-notice-close></div>
                    <div class="vc-notice-card<?= $vcNpThumb !== '' ? ' vc-notice-card--bg' : '' ?>"<?php if ($vcNpThumb !== ''): ?> style="background-image: linear-gradient(180deg, rgba(6, 18, 39, 0.38) 0%, rgba(6, 18, 39, 0.76) 45%, rgba(6, 18, 39, 0.95) 100%), url('<?= htmlspecialchars($vcNpThumb, ENT_QUOTES) ?>');"<?php endif; ?>>
                        <div class="vc-notice-head">
                            <span class="vc-notice-badge">📢 THÔNG BÁO</span>
                            <button type="button" class="vc-notice-x" data-vc-notice-close aria-label="Đóng">✕</button>
                        </div>
                        <div class="vc-notice-scroll">
                            <h3 class="vc-notice-title"><?= htmlspecialchars($vcNp['title']) ?></h3>
                            <div class="vc-notice-body"><?= preg_replace('/<!--thumbnail:.*?-->/is', '', (string)$vcNp['content']) ?></div>
                            <div class="vc-notice-date"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($vcNp['created_at']))) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>