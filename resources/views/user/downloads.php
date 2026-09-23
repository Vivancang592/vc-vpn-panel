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

<section class="user-record-page">
    <header class="user-orders-header">
        <div class="user-orders-intro">
            <h2 style="color: #020af4; font-family: emoji;">ỨNG DỤNG KẾT NỐI</h2>
            <p style="margin: 0;">Chọn đúng phiên bản Karing theo hệ điều hành thiết bị của bạn.</p>
        </div>
        <div class="user-orders-title-row">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">📲 Tải ứng dụng VPN</h1>
        </div>
    </header>

    <div class="glass-card" style="padding: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .8rem;">
        <?php foreach ($platforms as $platform): ?>
                <a href="/client?tag=<?= urlencode($platform['tag']) ?>" data-no-loader style="display: block; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 10px; color: inherit; text-decoration: none; transition: transform .2s ease, box-shadow .2s ease;">
                    <strong style="display: block; margin-bottom: .35rem; color: var(--ios-blue); font-size: 1rem;"><?= htmlspecialchars($platform['name']) ?></strong>
                    <span style="color: var(--ios-text-secondary); font-size: .85rem;"><?= htmlspecialchars($platform['detail']) ?></span>
                </a>
        <?php endforeach; ?>
        </div>

        <p style="margin: 1rem 0 0; color: var(--ios-text-secondary); font-size: .85rem; line-height: 1.55;">
            Lưu ý: Ứng dụng được cung cấp từ nguồn chính thức theo từng nền tảng. Nếu tải không thành công, hãy thử lại sau vài phút hoặc đổi mạng.
        </p>
    </div>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../layouts/app.php';
