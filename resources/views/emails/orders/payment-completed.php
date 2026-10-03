<?php
$mailTitle = 'Thanh toán thành công';
$accent    = '#16a34a';
$mailBadge = 'Đã thanh toán';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào Quý khách,</p>
<p class="mail-lead"><?= htmlspecialchars((string)($description ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
<?php
// Dữ liệu hóa đơn (optional — caller không truyền thì ẩn dòng tương ứng).
$pcTotal    = (string) ($totalAmount ?? '');
$pcDiscount = (string) ($discountAmount ?? '');
$pcCoupon   = (string) ($couponCode ?? '');
$pcPaid     = (string) ($paidAmount ?? ($amount ?? ''));
$pcGateway  = (string) ($paymentMethod ?? '');
?>
<table class="mail-info">
    <tr><td class="k">Mã đơn hàng / giao dịch</td><td class="v"><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php if ($pcTotal !== ''): ?>
    <tr><td class="k">Tổng tiền</td><td class="v"><?= htmlspecialchars($pcTotal, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    <?php if ($pcDiscount !== ''): ?>
    <tr><td class="k">Khấu trừ<?= $pcCoupon !== '' ? ' (' . htmlspecialchars($pcCoupon, ENT_QUOTES, 'UTF-8') . ')' : '' ?></td><td class="v" style="color:#16a34a">-<?= htmlspecialchars($pcDiscount, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    <tr><td class="k">Số tiền trả</td><td class="v"><strong><?= htmlspecialchars($pcPaid, ENT_QUOTES, 'UTF-8') ?></strong></td></tr>
    <?php if ($pcGateway !== ''): ?>
    <tr><td class="k">Cổng thanh toán</td><td class="v"><?= htmlspecialchars($pcGateway, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    <tr><td class="k">Trạng thái</td><td class="v">Thành công</td></tr>
</table>
<p class="mail-lead">Gói cước (nếu có) đã được kích hoạt và sẵn sàng sử dụng. Bạn có thể theo dõi trạng thái đơn hàng và gói cước trong trang quản lý.</p>
<?php if (rtrim((string)($siteUrl ?? ''), '/') !== ''): ?>
<div style="text-align:center;">
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/orders', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Xem Đơn Hàng</a>
</div>
<?php endif; ?>
<p class="mail-note">Vui lòng giữ mã đơn hàng để đối chiếu khi liên hệ hỗ trợ.</p>
<?php require __DIR__ . '/../_footer.php'; ?>