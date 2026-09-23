<footer class="vc-footer">
    <div class="vc-footer-container">
        <div class="vc-footer-title-wrap">
            <h3 class="vc-footer-title">
                <?= htmlspecialchars($settings['site_title'] ?? 'VC VPN 2027') ?>
            </h3>
            <p class="vc-footer-intro">Kết nối riêng tư, ổn định và không giới hạn.</p>
        </div>

        <?php if (!empty($settings)): ?>
            <nav class="vc-footer-contact-links" aria-label="Kênh hỗ trợ">
                <?php if (!empty($settings['fanpage_url'])): ?>
                    <a href="<?= htmlspecialchars($settings['fanpage_url']) ?>" target="_blank" rel="noopener noreferrer" title="Fanpage hỗ trợ" aria-label="Fanpage hỗ trợ"><span aria-hidden="true">🌐</span></a>
                <?php endif; ?>
                <?php if (!empty($settings['zalo_url'])): ?>
                    <a href="<?= htmlspecialchars($settings['zalo_url']) ?>" target="_blank" rel="noopener noreferrer" title="Zalo hỗ trợ" aria-label="Zalo hỗ trợ"><span aria-hidden="true">💬</span></a>
                <?php endif; ?>
                <?php if (!empty($settings['youtube_url'])): ?>
                    <a href="<?= htmlspecialchars($settings['youtube_url']) ?>" target="_blank" rel="noopener noreferrer" title="YouTube" aria-label="YouTube"><span aria-hidden="true">▶</span></a>
                <?php endif; ?>
                <?php if (!empty($settings['contact_email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($settings['contact_email']) ?>" title="<?= htmlspecialchars($settings['contact_email']) ?>" aria-label="Email hỗ trợ"><span aria-hidden="true">✉</span></a>
                <?php endif; ?>
                <?php if (!empty($settings['telegram_url'])): ?>
                    <a href="<?= htmlspecialchars($settings['telegram_url']) ?>" target="_blank" rel="noopener noreferrer" title="Telegram hỗ trợ" aria-label="Telegram hỗ trợ"><span aria-hidden="true">✈</span></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <nav class="vc-footer-policy-links" aria-label="Chính sách">
            <a href="/terms">Điều khoản</a>
            <a href="/privacy">Quyền riêng tư</a>
            <a href="/refund">Hoàn tiền</a>
        </nav>

        <div class="vc-footer-copyright-wrap">
            <p class="vc-footer-copyright-text">&copy; <?= date('Y') ?> <?= htmlspecialchars($settings['site_title'] ?? 'VC VPN 2027') ?>. All rights reserved.</p>
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