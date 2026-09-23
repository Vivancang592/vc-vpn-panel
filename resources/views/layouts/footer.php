<?php
$settings = isset($settings) && is_array($settings) ? $settings : [];
$siteTitle = (string)($settings['site_title'] ?? 'VC VPN 2027');

$fanpageUrl = trim((string)($settings['fanpage_url'] ?? ''));
$zaloUrl = trim((string)($settings['zalo_url'] ?? ''));
$youtubeUrl = trim((string)($settings['youtube_url'] ?? ''));
$telegramUrl = trim((string)($settings['telegram_url'] ?? ''));
$contactEmail = trim((string)($settings['contact_email'] ?? ''));

$hasContactLinks = $fanpageUrl !== '' || $zaloUrl !== '' || $youtubeUrl !== '' || $telegramUrl !== '' || $contactEmail !== '';
?>

<footer class="vc-footer">
    <div class="vc-footer-container">
        <div class="vc-footer-title-wrap">
            <h3 class="vc-footer-title">
                <?= htmlspecialchars($siteTitle) ?>
            </h3>
            <p class="vc-footer-intro">Kết nối riêng tư, ổn định và không giới hạn.</p>
        </div>

        <?php if ($hasContactLinks): ?>
            <nav class="vc-footer-contact-links" aria-label="Kênh hỗ trợ">
                <?php if ($fanpageUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($fanpageUrl) ?>" target="_blank" rel="noopener noreferrer" title="Facebook Fanpage" aria-label="Facebook Fanpage"><svg class="footer-platform-icon footer-platform-facebook" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4h-3c-3.3 0-5 2-5 5v3H6v4h3v6h4v-6h3.1l.9-4H13V9c0-.6.4-1 1-1Z"/></svg></a>
                <?php endif; ?>
                <?php if ($zaloUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($zaloUrl) ?>" target="_blank" rel="noopener noreferrer" title="Zalo hỗ trợ" aria-label="Zalo hỗ trợ"><svg class="footer-platform-icon footer-platform-zalo" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3z"/><path d="M7 8h5l-5 8h5M14 8v8M17 8v8"/></svg></a>
                <?php endif; ?>
                <?php if ($youtubeUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($youtubeUrl) ?>" target="_blank" rel="noopener noreferrer" title="YouTube" aria-label="YouTube"><svg class="footer-platform-icon footer-platform-youtube" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 7.1a2.7 2.7 0 0 0-1.9-1.9C17.4 4.7 12 4.7 12 4.7s-5.4 0-7.1.5A2.7 2.7 0 0 0 3 7.1 28 28 0 0 0 2.5 12c0 1.7.2 3.3.5 4.9a2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.1.5 7.1.5s5.4 0 7.1-.5a2.7 2.7 0 0 0 1.9-1.9c.3-1.6.5-3.2.5-4.9s-.2-3.3-.5-4.9Z"/><path d="m10 15.5 5-3.5-5-3.5z" class="footer-platform-cutout"/></svg></a>
                <?php endif; ?>
                <?php if ($contactEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" title="<?= htmlspecialchars($contactEmail) ?>" aria-label="Email hỗ trợ"><svg class="footer-platform-icon footer-platform-email" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                <?php endif; ?>
                <?php if ($telegramUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($telegramUrl) ?>" target="_blank" rel="noopener noreferrer" title="Telegram hỗ trợ" aria-label="Telegram hỗ trợ"><svg class="footer-platform-icon footer-platform-telegram" viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-3.1 17.5-6.1-5-3.4 3.3.6-4.7 8.7-8.4-10.8 7L2.7 11 21 3Z"/></svg></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <nav class="vc-footer-policy-links" aria-label="Chính sách">
            <a href="/terms">Điều khoản</a>
            <a href="/privacy">Quyền riêng tư</a>
            <a href="/refund">Hoàn tiền</a>
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