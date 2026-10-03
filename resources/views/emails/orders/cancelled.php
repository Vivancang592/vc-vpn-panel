<?php
$mailTitle = 'Đơn hàng đã bị hủy';
$accent    = '#b42318';
$mailBadge = 'Đã hủy';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào Quý khách,</p>
<p class="mail-lead">Đơn hàng <strong><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></strong> đã bị hủy trên hệ thống <strong><?= htmlspecialchars((string)$siteTitle, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
<table class="mail-info">
    <tr><td class="k">Mã đơn hàng</td><td class="v"><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Trạng thái</td><td class="v">Đã hủy</td></tr>
</table>
<p class="mail-lead"><?= htmlspecialchars((string)($reason ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
<p class="mail-lead">Khi nào bạn cần, chỉ cần tạo đơn hàng mới là có thể tiếp tục sử dụng dịch vụ.</p>
<?php if (rtrim((string)$siteUrl, '/') !== ''): ?>
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/plans', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Tạo Đơn Hàng Mới</a>
<?php endif; ?>
<p class="mail-note">Nếu bạn không thực hiện việc hủy đơn, vui lòng liên hệ bộ phận hỗ trợ ngay để được kiểm tra bảo mật tài khoản.</p>
<?php require __DIR__ . '/../_footer.php'; ?>