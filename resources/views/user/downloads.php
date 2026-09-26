<?php
$pageTitle = 'Tải ứng dụng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'admin';
$extraJs = 'app';
$platforms = [
    ['tag' => 'ios-stable', 'name' => 'iPhone & iPad', 'detail' => 'iOS / iPadOS', 'color' => '#8E8E93', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.36 12.78c.02-2.1 1.72-3.11 1.8-3.16-1-1.44-2.53-1.64-3.07-1.66-1.3-.13-2.55.77-3.21.77-.67 0-1.69-.75-2.78-.73-1.43.02-2.75.83-3.48 2.11-1.49 2.59-.38 6.42 1.07 8.52.71 1.03 1.55 2.18 2.65 2.14 1.07-.04 1.47-.69 2.76-.69 1.28 0 1.65.69 2.77.67 1.15-.02 1.87-1.04 2.57-2.08.81-1.19 1.14-2.34 1.16-2.4-.03-.01-2.23-.86-2.25-3.41zM14.3 6.02c.58-.71.98-1.69.87-2.67-.84.04-1.86.56-2.47 1.26-.54.62-1.02 1.62-.89 2.57.94.07 1.9-.47 2.49-1.16z"/></svg>'],
    ['tag' => 'android-stable', 'name' => 'Android', 'detail' => 'APK ARM64', 'color' => '#3DDC84', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85-.29-.15-.65-.06-.83.22l-1.88 3.24a11.46 11.46 0 0 0-8.94 0L5.65 5.67c-.19-.29-.58-.38-.87-.2-.28.18-.37.54-.22.83L6.4 9.48A10.81 10.81 0 0 0 1 18h22a10.81 10.81 0 0 0-5.4-8.52zM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5z"/></svg>'],
    ['tag' => 'windows-stable', 'name' => 'Windows', 'detail' => 'Windows x64', 'color' => '#0078D4', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 5.55l7.77-1.07v6.19H3V5.55zm0 12.9v-6.18h7.77v6.27L3 17.42v1.03zm8.77 1.14L21 20.5V13.7h-9.23v5.89zm0-13.63V12.9H21V3.5l-9.23 1.28v1.24z"/></svg>'],
    ['tag' => 'macos-stable', 'name' => 'macOS', 'detail' => 'Universal DMG', 'color' => '#A2AAAD', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.36 12.78c.02-2.1 1.72-3.11 1.8-3.16-1-1.44-2.53-1.64-3.07-1.66-1.3-.13-2.55.77-3.21.77-.67 0-1.69-.75-2.78-.73-1.43.02-2.75.83-3.48 2.11-1.49 2.59-.38 6.42 1.07 8.52.71 1.03 1.55 2.18 2.65 2.14 1.07-.04 1.47-.69 2.76-.69 1.28 0 1.65.69 2.77.67 1.15-.02 1.87-1.04 2.57-2.08.81-1.19 1.14-2.34 1.16-2.4-.03-.01-2.23-.86-2.25-3.41zM14.3 6.02c.58-.71.98-1.69.87-2.67-.84.04-1.86.56-2.47 1.26-.54.62-1.02 1.62-.89 2.57.94.07 1.9-.47 2.49-1.16z"/></svg>'],
    ['tag' => 'linux-stable', 'name' => 'Linux', 'detail' => 'AppImage x64', 'color' => '#FCC624', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.5 2c-.8 0-1.6.5-2 1.3-.6 1.1-.5 2.6-.9 3.6-.5 1.2-1.7 2.2-2.6 3.6-.9 1.4-1.4 3.1-1.3 4.7.1 1.4.7 2.7 1.6 3.7.5.5 1.1.9 1.8 1.1-.1.6-.3 1.3-.7 1.9-.5.8-1.3 1.4-2.1 1.8-.4.2-.6.6-.5 1 .1.4.5.7.9.7 1.3 0 2.6-.5 3.6-1.4.7-.6 1.2-1.4 1.5-2.3.3.9.8 1.7 1.5 2.3 1 .9 2.3 1.4 3.6 1.4.4 0 .8-.3.9-.7.1-.4-.1-.8-.5-1-.8-.4-1.6-1-2.1-1.8-.4-.6-.6-1.3-.7-1.9.7-.2 1.3-.6 1.8-1.1.9-1 1.5-2.3 1.6-3.7.1-1.6-.4-3.3-1.3-4.7-.9-1.4-2.1-2.4-2.6-3.6-.4-1-.3-2.5-.9-3.6C14.1 2.5 13.3 2 12.5 2zm-1.7 4.1c.2-.5.3-1.1.4-1.6.3.2.5.6.6 1 .1.5.1 1 .1 1.5l-.1 1.1-1 .2-.9-.9.9-1.3zm3.4 0 .9 1.3-.9.9-1-.2V7c0-.5 0-1 .1-1.5.1-.4.3-.8.6-1 .1.5.2 1.1.3 1.6zM8.6 11c.3 0 .6.1.9.2.3.2.5.4.7.7.2.3.4.5.7.6.3.1.6.2.9.1h.1c.4 0 .7-.2.9-.5.2-.3.3-.7.3-1.1v-.4c-.3-.2-.7-.3-1.1-.3H11c-.5 0-1 .1-1.5.2-.4.2-.8.4-1.1.7-.1 0-.2.1-.3.1-.3 0-.4-.2-.4-.5 0-.4.3-.6.7-.7.1 0 .3-.1.6-.1zm6.8 0c.3 0 .5.1.6.1.4.1.7.3.7.7 0 .3-.1.5-.4.5-.1 0-.2-.1-.3-.1-.3-.3-.7-.5-1.1-.7-.5-.1-1-.2-1.5-.2h-.4c-.4 0-.8.1-1.1.3v.4c0 .4.1.8.3 1.1.2.3.5.5.9.5.1 0 .2 0 .3-.1.3-.1.5-.3.7-.6.2-.3.4-.5.7-.6.3-.1.6-.2.9-.2zm-3.4 1.9c.6 0 1.2.1 1.7.4.5.3.9.7 1.1 1.2.2.5.3 1.1.2 1.7-.1.6-.4 1.1-.8 1.6-.4.4-.9.7-1.4.8-.5.2-1.1.1-1.6-.1-.5-.2-.9-.6-1.2-1.1-.3-.5-.4-1.1-.3-1.7.1-.6.4-1.1.8-1.6.4-.4.9-.7 1.5-.8.1 0 .2 0 .4 0zm-3.6.4c.2.4.5.7.9.9-.3.6-.5 1.3-.5 2 0 .7.2 1.4.5 2-.5.1-1 .3-1.4.7-.5.5-.8 1.2-.8 1.9 0-.4-.2-.8-.4-1.1-.3-.4-.7-.7-1.1-.8.3-.4.5-.9.7-1.4.2-.5.3-1.1.3-1.7 0-.6-.1-1.2-.4-1.7-.1-.3-.3-.5-.5-.7.3-.4.7-.6 1.1-.8.3-.1.7-.1 1-.1.2 0 .4 0 .6.1.1.1.2.2.4.4zm7.2 0c.1-.2.2-.3.4-.4.2-.1.4-.1.6-.1.3 0 .7 0 1 .1.4.2.8.4 1.1.8-.2.2-.4.4-.5.7-.3.5-.4 1.1-.4 1.7 0 .6.1 1.2.3 1.7.2.5.4 1 .7 1.4-.4.1-.8.4-1.1.8-.2.3-.4.7-.4 1.1 0-.7-.3-1.4-.8-1.9-.4-.4-.9-.6-1.4-.7 0-.7-.2-1.4-.5-2 0-.7-.2-1.4-.5-2z"/></svg>'],
];
ob_start();
?>

<section class="user-record-page">
    <header class="user-orders-header">
        <div class="user-orders-intro">
            <h2 class="u-plans-title">ỨNG DỤNG KẾT NỐI</h2>
            <p style="margin: 0;">Chọn đúng phiên bản Karing theo hệ điều hành thiết bị của bạn.</p>
        </div>
        <div class="user-orders-title-row">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">📲 Tải ứng dụng VPN</h1>
        </div>
    </header>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .8rem;">
    <?php foreach ($platforms as $platform): ?>
                <a href="/client?tag=<?= urlencode($platform['tag']) ?>" data-no-loader class="u-dl-card" style="display: flex; align-items: center; gap: .85rem; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 12px; color: inherit; text-decoration: none; transition: transform .2s ease, box-shadow .2s ease;">
                    <span style="flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 12px; background: <?= htmlspecialchars($platform['color']) ?>1A; color: <?= htmlspecialchars($platform['color']) ?>; border: 1px solid <?= htmlspecialchars($platform['color']) ?>33;">
                        <span style="display: flex; width: 26px; height: 26px;"><?= $platform['icon'] ?></span>
                    </span>
                    <span>
                        <strong style="display: block; margin-bottom: .35rem; color: var(--ios-blue); font-size: 1rem;"><?= htmlspecialchars($platform['name']) ?></strong>
                        <span style="color: var(--ios-text-secondary); font-size: .85rem;"><?= htmlspecialchars($platform['detail']) ?></span>
                    </span>
                </a>
    <?php endforeach; ?>
    </div>

    <div style="display: flex; align-items: flex-start; gap: .7rem; margin-top: 1.1rem; padding: .9rem 1rem; background: rgba(255, 159, 10, .12); border: 1px solid rgba(255, 159, 10, .45); border-left: 4px solid #ff9f0a; border-radius: 10px;">
        <span style="font-size: 1.1rem; line-height: 1.4;">⚠️</span>
        <p style="margin: 0; color: var(--ios-text); font-size: .88rem; font-weight: 600; line-height: 1.6;">
            <strong style="color: #ff9f0a;">Lưu ý:</strong> Các ứng dụng kết nối là phần mềm của bên thứ ba và được phát triển bởi nhà phát triển tương ứng. Vui lòng tải đúng phiên bản cho hệ điều hành đang sử dụng và tham khảo hướng dẫn của nhà phát triển khi cài đặt.
        </p>
    </div>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../layouts/app.php';
