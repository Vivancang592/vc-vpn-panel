<?php
$mailTitle = 'Kích hoạt dịch vụ thành công';
$accent    = '#16a34a';
$mailBadge = 'Đã kích hoạt';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào Quý khách,</p>
<p class="mail-lead">Đơn hàng của bạn đã được thanh toán và kích hoạt thành công trên hệ thống <strong><?= htmlspecialchars((string)$siteTitle, ENT_QUOTES, 'UTF-8') ?></strong>. Bạn có thể kết nối VPN ngay bây giờ.</p>
<table class="mail-info">
    <tr><td class="k">Mã đơn hàng</td><td class="v"><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Gói cước</td><td class="v"><?= htmlspecialchars((string)($planName ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Số tiền</td><td class="v"><?= htmlspecialchars((string)($amount ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Hạn sử dụng</td><td class="v"><?= htmlspecialchars((string)($endDate ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>
<p class="mail-lead">Thông tin kết nối (UUID, nhóm server) luôn được hiển thị trong trang quản lý gói cước của bạn.</p>
<?php if (rtrim((string)($siteUrl ?? ''), '/') !== ''): ?>
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/subscriptions', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Quản Lý Gói Cước</a>
<?php endif; ?>
<p class="mail-note">Cần hỗ trợ cài đặt? Truy cập trang hướng dẫn hoặc liên hệ bộ phận hỗ trợ của chúng tôi.</p>
<?php require __DIR__ . '/../_footer.php'; ?>