<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?? 'VC VPN 2027' ?></title>
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= time() ?>">
    <?php if (isset($extraCss) && $extraCss !== 'app'): ?>
        <link rel="stylesheet" href="/assets/css/<?= $extraCss ?>.css?v=<?= time() ?>">
    <?php endif; ?>
</head>
<body class="vpn-theme user-festival<?= ($extraCss ?? '') === 'home' ? ' home-festival' : '' ?>">