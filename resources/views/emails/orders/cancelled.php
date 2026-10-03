<?php
$mailTitle = 'Đơn hàng đã bị hủy';
$accent    = '#b42318';
$mailBadge = 'Đã hủy';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào Quý khách,</p>
<p class="mail-lead">Đơn hàng <strong><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></strong> đã bị hủy trên hệ thống <strong><?= htmlspecialchars((string)$siteTitle, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
<?php
// Dữ liệu hóa đơn (optional — caller không truyền thì ẩn dòng tương ứng).
$pcTotal    = (string) ($totalAmount ?? '');
$pcDiscount = (string) ($discountAmount ?? '');
$pcCoupon   = (string) ($couponCode ?? '');
$pcPaid     = (string) ($paidAmount ?? '');
$pcGateway  = (string) ($paymentMethod ?? '');
?>
<table class="mail-info">
    <tr><td class="k">Mã đơn hàng</td><td class="v"><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php if ($pcTotal !== ''): ?>
    <tr><td class="k">Tổng tiền</td><td class="v"><?= htmlspecialchars($pcTotal, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    <?php if ($pcDiscount !== ''): ?>
    <tr><td class="k">Khấu trừ<?= $pcCoupon !== '' ? ' (' . htmlspecialchars($pcCoupon, ENT_QUOTES, 'UTF-8') . ')' : '' ?></td><td class="v" style="color:#16a34a">-<?= htmlspecialchars($pcDiscount, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    <?php if ($pcPaid !== ''): ?>
    <tr><td class="k">Số tiền trả</td><td class="v"><strong><?= htmlspecialchars($pcPaid, ENT_QUOTES, 'UTF-8') ?></strong></td></tr>
    <?php endif; ?>
    <?php if ($pcGateway !== ''): ?>
    <tr><td class="k">Cổng thanh toán</td><td class="v"><?= htmlspecialchars($pcGateway, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    <tr><td class="k">Trạng thái</td><td class="v">Đã hủy</td></tr>
</table>
<p class="mail-lead"><?= htmlspecialchars((string)($reason ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
<p class="mail-lead">Khi nào bạn cần, chỉ cần tạo đơn hàng mới là có thể tiếp tục sử dụng dịch vụ.</p>
<?php if (rtrim((string)($siteUrl ?? ''), '/') !== ''): ?>
<div style="text-align:center;">
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/plans', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Tạo Đơn Hàng Mới</a>
</div>
<?php endif; ?>
<p class="mail-note">Nếu bạn không thực hiện việc hủy đơn, vui lòng liên hệ bộ phận hỗ trợ ngay để được kiểm tra bảo mật tài khoản.</p>
<?php require __DIR__ . '/../_footer.php'; ?>