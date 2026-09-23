<?php
$settings = isset($settings) && is_array($settings) ? $settings : [];
$siteTitle = (string)($settings['site_title'] ?? 'VC VPN 2027');

$fanpageUrl = trim((string)($settings['fanpage_url'] ?? ''));
$zaloUrl = trim((string)($settings['zalo_url'] ?? ''));
$youtubeUrl = trim((string)($settings['youtube_url'] ?? ''));
$wechatId = trim((string)($settings['wechat_id'] ?? ''));
$contactEmail = trim((string)($settings['contact_email'] ?? ''));

$hasContactLinks = $fanpageUrl !== '' || $zaloUrl !== '' || $youtubeUrl !== '' || $wechatId !== '' || $contactEmail !== '';

$normalizeUrl = static function (string $value, string $prefix = ''): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }
    return $prefix !== '' ? $prefix . ltrim($value, '/') : $value;
};

$fanpageHref = $normalizeUrl($fanpageUrl, 'https://');
$youtubeHref = $normalizeUrl($youtubeUrl, 'https://');
$zaloHref = $normalizeUrl($zaloUrl, 'https://zalo.me/');
?>

<footer class="vc-footer">
    <div class="vc-footer-container">

        <div class="vc-footer-title-wrap">
            <h3 class="vc-footer-title">
                <?= htmlspecialchars($siteTitle) ?>
            </h3>
        </div>

        <?php if ($hasContactLinks): ?>
            <nav class="vc-footer-contact-links" aria-label="Nền tảng hỗ trợ" style="display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: .55rem; margin-top: .65rem;">
                <?php if ($fanpageHref !== ''): ?>
                    <a href="<?= htmlspecialchars($fanpageHref) ?>" target="_blank" rel="noopener noreferrer" title="Fanpage" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(0,123,255,.35); border-radius: 6px; color: #0056b3; text-decoration: none; font-size: .95rem;">🌐</a>
                <?php endif; ?>
                <?php if ($zaloHref !== ''): ?>
                    <a href="<?= htmlspecialchars($zaloHref) ?>" target="_blank" rel="noopener noreferrer" title="Zalo" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(40,167,69,.35); border-radius: 6px; color: #1e7e34; text-decoration: none; font-size: .95rem;">💬</a>
                <?php endif; ?>
                <?php if ($youtubeHref !== ''): ?>
                    <a href="<?= htmlspecialchars($youtubeHref) ?>" target="_blank" rel="noopener noreferrer" title="Youtube" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(220,53,69,.35); border-radius: 6px; color: #dc3545; text-decoration: none; font-size: .95rem;">▶</a>
                <?php endif; ?>
                <?php if ($contactEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" title="Email" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(111,66,193,.35); border-radius: 6px; color: #6f42c1; text-decoration: none; font-size: .95rem;">📧</a>
                <?php endif; ?>
                <?php if ($wechatId !== ''): ?>
                    <span title="WeChat ID" style="display: inline-flex; align-items: center; gap: .3rem; padding: 0 .55rem; height: 34px; border: 1px solid rgba(23,162,184,.35); border-radius: 6px; color: #117a8b; font-size: .8rem; font-weight: 600;">💬 <?= htmlspecialchars($wechatId) ?></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <nav class="vc-footer-policy-links" aria-label="Chính sách" style="display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: .5rem; margin-top: .55rem;">
            <a href="/terms" class="vc-color-blue" style="padding: .35rem .62rem; border: 1px solid rgba(0,0,0,.14); border-radius: 5px; text-decoration: none; font-size: .72rem; font-weight: 700;">Điều Khoản Dịch Vụ</a>
            <a href="/privacy" class="vc-color-green" style="padding: .35rem .62rem; border: 1px solid rgba(0,0,0,.14); border-radius: 5px; text-decoration: none; font-size: .72rem; font-weight: 700;">Quyền Riêng Tư</a>
            <a href="/refund" class="vc-color-orange" style="padding: .35rem .62rem; border: 1px solid rgba(0,0,0,.14); border-radius: 5px; text-decoration: none; font-size: .72rem; font-weight: 700;">Chính Sách Hoàn Tiền</a>
        </nav>

        <div class="vc-footer-copyright-wrap">
            <p class="vc-footer-copyright-text">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Luôn tải app.js với tham số xóa cache -->
<script src="/assets/js/app.js?v=<?= time() ?>"></script>

<!-- Chỉ tải extraJs nếu khác file app.js -->
<?php if (isset($extraJs) && $extraJs !== 'app'): ?>
    <script src="/assets/js/<?= $extraJs ?>.js?v=<?= time() ?>"></script>
<?php endif; ?>
</body>
</html>