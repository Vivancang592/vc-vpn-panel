<?php
$pageTitle = 'Tải ứng dụng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'admin';
$extraJs = 'app';
$platforms = [
    ['tag' => 'ios-stable', 'name' => 'iPhone & iPad', 'detail' => 'iOS / iPadOS'],
    ['tag' => 'android-stable', 'name' => 'Android', 'detail' => 'APK ARM64'],
    ['tag' => 'windows-stable', 'name' => 'Windows', 'detail' => 'Windows x64'],
    ['tag' => 'macos-stable', 'name' => 'macOS', 'detail' => 'Universal DMG'],
    ['tag' => 'linux-stable', 'name' => 'Linux', 'detail' => 'AppImage x64'],
];
ob_start();
?>

<section class="glass-card" style="padding: 1.5rem;">
    <header style="margin-bottom: 1.5rem;">
        <p style="margin: 0 0 .35rem; color: var(--color-primary); font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;">Ứng dụng kết nối</p>
        <h1 style="margin: 0; font-size: 1.6rem;">Tải ứng dụng VPN</h1>
        <p style="margin: .5rem 0 0; color: var(--ios-text-secondary);">Chọn phiên bản Karing phù hợp với thiết bị của bạn.</p>
    </header>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .8rem;">
        <?php foreach ($platforms as $platform): ?>
            <a href="/client?tag=<?= urlencode($platform['tag']) ?>" data-no-loader style="display: block; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 10px; color: inherit; text-decoration: none;">
                <strong style="display: block; margin-bottom: .35rem; color: var(--ios-blue); font-size: 1rem;"><?= htmlspecialchars($platform['name']) ?></strong>
                <span style="color: var(--ios-text-secondary); font-size: .85rem;"><?= htmlspecialchars($platform['detail']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
