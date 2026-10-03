<?php
$mailTitle = 'Thanh toán thành công';
$accent    = '#16a34a';
$mailBadge = 'Đã thanh toán';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào Quý khách,</p>
<p class="mail-lead"><?= htmlspecialchars((string)($description ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
<table class="mail-info">
    <tr><td class="k">Mã đơn hàng / giao dịch</td><td class="v"><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Số tiền</td><td class="v"><?= htmlspecialchars((string)($amount ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Trạng thái</td><td class="v">Thành công</td></tr>
</table>
<p class="mail-lead">Gói cước (nếu có) đã được kích hoạt và sẵn sàng sử dụng. Bạn có thể theo dõi trạng thái đơn hàng và gói cước trong trang quản lý.</p>
<?php if (rtrim((string)$siteUrl, '/') !== ''): ?>
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/orders', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Xem Đơn Hàng</a>
<?php endif; ?>
<p class="mail-note">Vui lòng giữ mã đơn hàng để đối chiếu khi liên hệ hỗ trợ.</p>
<?php require __DIR__ . '/../_footer.php'; ?>