<?php
$pageTitle = 'Tải Ứng Dụng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'home';
ob_start();
?>
<div class="glass-card" style="max-width: 820px; margin: 0 auto; padding: 2rem;">
    <h1 style="margin-bottom: .75rem;">Tải ứng dụng</h1>
    <p style="color: var(--ios-text-secondary); margin-bottom: 1.5rem;">Cài ứng dụng phù hợp với thiết bị, sau đó đăng nhập để nhận cấu hình VPN của bạn.</p>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
        <a class="glass-btn" href="https://apps.apple.com/" target="_blank" rel="noopener">iPhone & iPad</a>
        <a class="glass-btn" href="https://play.google.com/store" target="_blank" rel="noopener">Android</a>
        <a class="glass-btn" href="https://github.com/2dust/v2rayN/releases" target="_blank" rel="noopener">Windows</a>
        <a class="glass-btn" href="https://github.com/2dust/v2rayN/releases" target="_blank" rel="noopener">macOS & Linux</a>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
