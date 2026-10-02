<?php
// Version tĩnh theo filemtime: file sửa → đổi URL → tải lại; không sửa → browser cache.
if (!function_exists('vc_asset_ver')) {
    function vc_asset_ver(string $rel): string
    {
        static $cache = [];
        if (!isset($cache[$rel])) {
            $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
            $mtime = @filemtime($base . '/public/assets/' . ltrim($rel, '/'));
            $cache[$rel] = $mtime !== false ? (string)$mtime : (string)time();
        }
        return $cache[$rel];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?? 'VC VPN 2027' ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Dịch vụ VPN cho kết nối riêng tư và dễ quản lý.', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'VC VPN 2027', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription ?? 'Dịch vụ VPN cho kết nối riêng tư và dễ quản lý.', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="vi_VN">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? 'VC VPN 2027', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription ?? 'Dịch vụ VPN cho kết nối riêng tư và dễ quản lý.', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= vc_asset_ver('css/app.css') ?>">
    <?php if (isset($extraCss) && $extraCss !== 'app'): ?>
        <link rel="stylesheet" href="/assets/css/<?= $extraCss ?>.css?v=<?= vc_asset_ver('css/' . $extraCss . '.css') ?>">
    <?php endif; ?>
    <?php if (($pageArea ?? '') === 'user'): ?>
        <!-- Design system mới cho khu vực user (load sau cùng để thắng cascade) -->
        <link rel="stylesheet" href="/assets/css/user.css?v=<?= vc_asset_ver('css/user.css') ?>">
    <?php endif; ?>
</head>
<body class="vpn-theme user-festival<?= ($extraCss ?? '') === 'home' ? ' home-festival' : '' ?><?= ($pageArea ?? '') === 'user' ? ' user-area' : '' ?>">