<?php
$extraCss = 'admin';
$extraJs = 'admin';
// Đánh dấu shell Admin → header.php thêm body class `admin-area`.
// admin.css scope `body.admin-area:has(.admin-app)` chỉ match trang Admin;
// trang user/public (layouts/app.php cũng dùng wrapper .admin-app) KHÔNG có
// class này → theme SOC không rò rỉ sang khu user.
$vcAdminShell = true;

/* --- Nhóm tab admin + trang hiện tại (tính TRƯỚC khi render) --- */
$adminGroups = [
    'infrastructure' => [
        'menus' => ['server-groups', 'servers', 'nodes', 'plans'],
        'label' => 'Quản Lý Hạ Tầng VPN',
        'tabs' => [
            'server-groups' => ['label' => 'Nhóm Máy Chủ', 'icon' => 'GRP', 'url' => '/admin/server-groups'],
            'servers' => ['label' => 'Máy Chủ', 'icon' => 'SRV', 'url' => '/admin/servers'],
            'nodes' => ['label' => 'Node Inbound', 'icon' => 'NOD', 'url' => '/admin/nodes'],
            'plans' => ['label' => 'Gói Cước', 'icon' => 'PLN', 'url' => '/admin/plans']
        ]
    ],
    'business' => [
        'menus' => ['coupons', 'orders', 'payments', 'subscriptions', 'referrals', 'withdrawals'],
        'label' => 'Quản Lý Kinh Doanh',
        'tabs' => [
            'coupons' => ['label' => 'Mã Giảm Giá', 'icon' => 'CPN', 'url' => '/admin/coupons'],
            'orders' => ['label' => 'Đơn Hàng', 'icon' => 'ORD', 'url' => '/admin/orders'],
            'payments' => ['label' => 'Thanh Toán', 'icon' => 'PAY', 'url' => '/admin/payments'],
            'subscriptions' => ['label' => 'Đăng Ký VPN', 'icon' => 'SUB', 'url' => '/admin/subscriptions'],
            'referrals' => ['label' => 'Hoa Hồng', 'icon' => 'REF', 'url' => '/admin/referrals'],
            'withdrawals' => ['label' => 'Rút Tiền', 'icon' => 'WDR', 'url' => '/admin/withdrawals']
        ]
    ],
    'ai' => [
        // menus = danh sách id đầy đủ (kể cả trang kỹ thuật không còn là tab
        // như ai-tasks/ai-models/ai-conversations/ai-outputs —
        // ai-modules/ai-prompts đã gộp/bỏ, ai-assets đã XOÁ, xem
        // /admin/ai/settings) để group highlight đúng activeMenu khi Admin
        // truy cập từ dashboard. KHÔNG dùng để hiển thị tab.
        'menus' => ['ai-dashboard', 'ai-image', 'ai-tasks', 'ai-video', 'ai-dubbing', 'ai-fanpage', 'ai-reply', 'ai-settings', 'ai-models', 'ai-conversations', 'ai-outputs', 'ai-assistant'],
        'label' => 'Trung Tâm AI',
        // 8 tab chức năng — trang kỹ thuật truy cập từ dashboard.
        'tabs' => [
            'ai-dashboard' => ['label' => 'Tổng Quan Hệ Thống AI', 'icon' => 'OVW', 'url' => '/admin/ai'],
            'ai-image'     => ['label' => 'Tạo Ảnh', 'icon' => 'IMG', 'url' => '/admin/ai/image'],
            'ai-video'     => ['label' => 'Tạo Video', 'icon' => 'VID', 'url' => '/admin/ai/video'],
            'ai-dubbing'   => ['label' => 'Lời Thoại', 'icon' => 'DUB', 'url' => '/admin/ai/dubbing'],
            'ai-fanpage'   => ['label' => 'Nội Dung Fanpage', 'icon' => 'FPG', 'url' => '/admin/ai/fanpage'],
            'ai-reply'     => ['label' => 'Trả Lời Tự Động', 'icon' => 'RPL', 'url' => '/admin/ai/reply'],
            'ai-settings'  => ['label' => 'Cấu Hình AI', 'icon' => 'CFG', 'url' => '/admin/ai/settings']
        ]
    ]
];
$activeMenu = $activeMenu ?? '';
$activeAdminGroup = null;
foreach ($adminGroups as $group) {
    if (in_array($activeMenu, $group['menus'], true)) {
        $activeAdminGroup = $group;
        break;
    }
}

/* Khoảnh header + tab nhóm (dùng cho trang đầy đủ và fragment partial) */
$renderGroupTabs = function () use ($activeAdminGroup, $activeMenu) {
    if ($activeAdminGroup === null) {
        return '';
    }
    ob_start();
    ?>
    <div class="admin-group-header">
        <h1><?= htmlspecialchars($activeAdminGroup['label']) ?></h1>
    </div>
    <nav class="admin-group-tabs" aria-label="<?= htmlspecialchars($activeAdminGroup['label']) ?>">
        <?php foreach ($activeAdminGroup['tabs'] as $tabMenu => $tab): ?>
            <a href="<?= $tab['url'] ?>" class="admin-group-tab <?= $activeMenu === $tabMenu ? 'active' : '' ?>" <?= $activeMenu === $tabMenu ? 'aria-current="page"' : '' ?>>
                <span><?= $tab['icon'] ?></span> <?= htmlspecialchars($tab['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
    return ob_get_clean();
};

/* --- PARTIAL-LOAD: admin.js tải fragment JSON (header X-VC-Partial) →
 * chỉ trả về nội dung .admin-content + sidebar, KHÔNG render trang đầy đủ.
 *Điều hướng nội bộ trong /admin vì thế không reload trang, không nháy
 * màn hình (xem public/assets/js/admin.js). --- */

// Toast trung tâm: xây MỘT lần, dùng cho cả 2 nhánh (partial JSON + full page)
ob_start();
require __DIR__ . '/_toast.php';
$vcToastHtml = ob_get_clean();

if (($_SERVER['HTTP_X_VC_PARTIAL'] ?? '') === '1') {
    ob_start();
    echo $vcToastHtml;    // toast hiện trên trang đích khi fragment thay .admin-content
    echo $renderGroupTabs();
    echo $content ?? '';
    $fragmentMain = ob_get_clean();

    ob_start();
    require __DIR__ . '/admin-sidebar.php';
    $fragmentSidebar = ob_get_clean();

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'title' => $pageTitle ?? 'VC VPN PANEL',
        'activeMenu' => $activeMenu,
        'html' => $fragmentMain,
        'sidebar' => $fragmentSidebar,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/header.php';
?>

<!-- Màn hình chờ Đang tải... -->
<div id="page-preloader">
    <div class="preloader-spinner"></div>
    <div class="preloader-text">Đang tải...</div>
</div>

<div class="admin-app">
    <?php require_once __DIR__ . '/admin-sidebar.php'; ?>

    <div class="admin-main-wrapper">
        <?php require_once __DIR__ . '/navbar.php'; ?>
        
        <div class="sidebar-overlay"></div>

        <main class="admin-main">
            <div class="admin-content">
                <?= $vcToastHtml ?>
                <?= $renderGroupTabs() ?>
                <?= $content ?? '' ?>
            </div>
            <?php require_once __DIR__ . '/_assistant_bubble.php'; // Bong bóng trợ lý — chỉ nhánh trang đầy đủ (fragment JSON đã exit ở nhánh trên) ?>
            <?php require_once __DIR__ . '/footer.php'; ?>
        </main>
    </div>
</div>