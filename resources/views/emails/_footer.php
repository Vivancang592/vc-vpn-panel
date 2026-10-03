<?php
/**
 * Frame footer dùng chung cho toàn bộ email (song song với _header.php).
 * Tùy chọn: $mailFooterNote — thay đổi câu giải thích lý do nhận email.
 */
$footerNote = $mailFooterNote ?? ('Bạn nhận được email này vì có hoạt động liên quan đến tài khoản của bạn trên hệ thống ' . ($siteTitle ?? '') . '.');
$siteUrlR   = rtrim((string)($siteUrl ?? ''), '/');
?>
    </div>
    <div class="mail-footer">
        <p style="margin: 0 0 6px;"><?= htmlspecialchars((string)$footerNote, ENT_QUOTES, 'UTF-8') ?></p>
        <p style="margin: 0;">
            <?php if ($siteUrlR !== ''): ?>
                <a href="<?= htmlspecialchars($siteUrlR, ENT_QUOTES, 'UTF-8') ?>">Trang chủ</a> ·
            <?php endif; ?>
            <?php if ($siteUrlR !== ''): ?>
                <a href="<?= htmlspecialchars($siteUrlR, ENT_QUOTES, 'UTF-8') ?>/terms">Điều khoản sử dụng</a> ·
                <a href="<?= htmlspecialchars($siteUrlR, ENT_QUOTES, 'UTF-8') ?>/privacy">Chính sách riêng tư</a><?php if (!empty($contactEmail)): ?> ·<?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($contactEmail)): ?>
                <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>">Liên hệ hỗ trợ</a>
            <?php endif; ?>
        </p>
        <p style="margin: 8px 0 0;">&copy; <?= date('Y') ?> <?= htmlspecialchars((string)($siteTitle ?? ''), ENT_QUOTES, 'UTF-8') ?> · Đây là email tự động, vui lòng không phản hồi trực tiếp.</p>
    </div>
</div>
</body>
</html>
