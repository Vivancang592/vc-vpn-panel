<?php
$settings = isset($settings) && is_array($settings) ? $settings : [];
$siteTitle = (string)($settings['site_title'] ?? 'VC VPN 2027');

$fanpageUrl = trim((string)($settings['fanpage_url'] ?? ''));
$zaloUrl = trim((string)($settings['zalo_url'] ?? ''));
$youtubeUrl = trim((string)($settings['youtube_url'] ?? ''));
$telegramUrl = trim((string)($settings['telegram_url'] ?? ''));
$contactEmail = trim((string)($settings['contact_email'] ?? ''));
$wechatId = trim((string)($settings['wechat_id'] ?? ''));

$hasContactLinks = $fanpageUrl !== '' || $zaloUrl !== '' || $youtubeUrl !== '' || $telegramUrl !== '' || $contactEmail !== '';
?>

<footer class="vc-footer">
    <div class="vc-footer-container">

        <div class="vc-footer-title-wrap">
            <h3 class="vc-footer-title">
                <?= htmlspecialchars($siteTitle) ?>
            </h3>
        </div>

        <div class="vc-footer-links-grid">

            <div class="vc-footer-col">
                <h4>Mạng Xã Hội</h4>
                <?php if ($hasContactLinks): ?>
                    <div class="vc-footer-col-content">
                        <?php if ($fanpageUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($fanpageUrl) ?>" target="_blank" rel="noopener noreferrer" class="vc-color-blue">
                                <span>🌐</span> Fanpage Hỗ Trợ
                            </a>
                        <?php endif; ?>

                        <?php if ($zaloUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($zaloUrl) ?>" target="_blank" rel="noopener noreferrer" class="vc-color-green">
                                <span>💬</span> Zalo Hỗ Trợ
                            </a>
                        <?php endif; ?>

                        <?php if ($youtubeUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($youtubeUrl) ?>" target="_blank" rel="noopener noreferrer" class="vc-color-red">
                                <span>▶</span> Youtube
                            </a>
                        <?php endif; ?>

                        <?php if ($telegramUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($telegramUrl) ?>" target="_blank" rel="noopener noreferrer" class="vc-color-purple">
                                <span>✈</span> Telegram
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vc-footer-col">
                <h4>Chính Sách</h4>
                <div class="vc-footer-col-content">
                    <a href="/terms" class="vc-color-blue">Điều Khoản Dịch Vụ</a>
                    <a href="/privacy" class="vc-color-green">Quyền Riêng Tư</a>
                    <a href="/refund" class="vc-color-orange">Chính Sách Hoàn Tiền</a>
                </div>
            </div>

            <div class="vc-footer-col">
                <h4>Liên Hệ Hỗ Trợ</h4>
                <?php if ($contactEmail !== '' || $wechatId !== ''): ?>
                    <div class="vc-footer-col-content">
                        <?php if ($contactEmail !== ''): ?>
                            <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="vc-color-purple">
                                <span>📧</span> <?= htmlspecialchars($contactEmail) ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($wechatId !== ''): ?>
                            <span class="wechat-text vc-color-darkgreen">
                                <span>💬</span> WeChat: <?= htmlspecialchars($wechatId) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

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