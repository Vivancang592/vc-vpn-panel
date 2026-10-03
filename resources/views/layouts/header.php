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
    <?php
    $siteName = $settings['site_title'] ?? 'VC VPN PANEL';
    $pageTitle = $pageTitle ?? $siteName;
    $metaDescription = $metaDescription ?? 'Dịch vụ VPN uy tín giúp ẩn IP, mã hóa kết nối và bảo vệ quyền riêng tư trên mọi thiết bị. Bảng giá minh bạch, hướng dẫn cài đặt chi tiết, hỗ trợ 24/7.';
    $metaKeywords = $metaKeywords ?? 'dịch vụ vpn, vpn uy tín, ẩn ip, vpn giá rẻ, hướng dẫn vpn, tải ứng dụng vpn, bảo mật mạng, vc vpn panel';
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $reqScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $canonicalUrl = $reqScheme . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . $reqPath;
    $ogImage = $reqScheme . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . '/assets/images/icon-512.png';
    ?>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="author" content="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#061225">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <!-- Favicon đầy đủ đường dẫn mặc định (Superman mark) -->
    <link rel="icon" type="image/svg+xml" href="/assets/images/icon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/assets/images/favicon-48.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileImage" content="/assets/images/icon-512.png">
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