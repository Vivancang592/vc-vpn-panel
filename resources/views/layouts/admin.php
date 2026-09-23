<?php 
$extraCss = 'admin';
$extraJs = 'admin';
require_once __DIR__ . '/header.php'; 
?>

<!-- Màn hình chờ Đang tải... -->
<div id="page-preloader">
    <div class="preloader-spinner"></div>
    <div class="preloader-text">Đang tải...</div>
</div>

<div class="admin-app">
    <?php require_once __DIR__ . '/admin-sidebar.php'; ?>

    <?php
    $adminGroups = [
        'infrastructure' => [
            'menus' => ['server-groups', 'servers', 'nodes', 'plans'],
            'label' => 'Quản Lý Hạ Tầng VPN',
            'tabs' => [
                'server-groups' => ['label' => 'Nhóm Máy Chủ', 'icon' => '📁', 'url' => '/admin/server-groups'],
                'servers' => ['label' => 'Máy Chủ', 'icon' => '🖥️', 'url' => '/admin/servers'],
                'nodes' => ['label' => 'Node Inbound', 'icon' => '🌐', 'url' => '/admin/nodes'],
                'plans' => ['label' => 'Gói Cước', 'icon' => '💎', 'url' => '/admin/plans']
            ]
        ],
        'business' => [
            'menus' => ['coupons', 'orders', 'payments', 'subscriptions', 'referrals', 'withdrawals'],
            'label' => 'Quản Lý Kinh Doanh',
            'tabs' => [
                'coupons' => ['label' => 'Mã Giảm Giá', 'icon' => '🏷️', 'url' => '/admin/coupons'],
                'orders' => ['label' => 'Đơn Hàng', 'icon' => '🧾', 'url' => '/admin/orders'],
                'payments' => ['label' => 'Thanh Toán', 'icon' => '💵', 'url' => '/admin/payments'],
                'subscriptions' => ['label' => 'Đăng Ký VPN', 'icon' => '🔑', 'url' => '/admin/subscriptions'],
                'referrals' => ['label' => 'Hoa Hồng', 'icon' => '🤝', 'url' => '/admin/referrals'],
                'withdrawals' => ['label' => 'Rút Tiền', 'icon' => '🏦', 'url' => '/admin/withdrawals']
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
    ?>

    <div class="admin-main-wrapper">
        <?php require_once __DIR__ . '/navbar.php'; ?>
        
        <div class="sidebar-overlay"></div>

        <main class="admin-main">
            <div class="admin-content">
                <?php if ($activeAdminGroup !== null): ?>
                    <div class="admin-group-header">
                        <h1><?= htmlspecialchars($activeAdminGroup['label']) ?></h1>
                    </div>
                    <nav class="admin-group-tabs" aria-label="<?= htmlspecialchars($activeAdminGroup['label']) ?>">
                        <?php foreach ($activeAdminGroup['tabs'] as $tabMenu => $tab): ?>
                            <?php if (($_SESSION['role'] ?? '') === 'staff' && !in_array($tabMenu, ['coupons', 'orders', 'payments', 'subscriptions'], true)) { continue; } ?>
                            <a href="<?= $tab['url'] ?>" class="admin-group-tab <?= $activeMenu === $tabMenu ? 'active' : '' ?>" <?= $activeMenu === $tabMenu ? 'aria-current="page"' : '' ?>>
                                <span><?= $tab['icon'] ?></span> <?= htmlspecialchars($tab['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <?= $content ?? '' ?>
            </div>
            <?php require_once __DIR__ . '/footer.php'; ?>
        </main>
    </div>
</div>