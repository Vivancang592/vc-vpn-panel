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
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= time() ?>">
    <?php if (isset($extraCss) && $extraCss !== 'app'): ?>
        <link rel="stylesheet" href="/assets/css/<?= $extraCss ?>.css?v=<?= time() ?>">
    <?php endif; ?>
</head>
<body class="vpn-theme user-festival<?= ($extraCss ?? '') === 'home' ? ' home-festival' : '' ?>">