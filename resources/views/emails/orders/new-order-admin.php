<?php
$mailTitle     = 'Có đơn hàng mới cần xử lý';
$accent        = '#0b5cab';
$mailBadge     = 'Đơn hàng mới';
$mailFooterNote = 'Bạn nhận được email này vì bạn là quản trị viên của hệ thống ' . (string)($siteTitle ?? '') . '.';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Xin chào Quản trị viên,</p>
<p class="mail-lead">Hệ thống vừa nhận một đơn hàng chờ thanh toán cần được theo dõi và đối soát.</p>
<table class="mail-info">
    <tr><td class="k">Mã đơn hàng</td><td class="v"><?= htmlspecialchars((string)($orderCode ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Khách hàng</td><td class="v"><?= htmlspecialchars((string)($customerName ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Email khách hàng</td><td class="v"><?= htmlspecialchars((string)($customerEmail ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Gói dịch vụ</td><td class="v"><?= htmlspecialchars((string)($planName ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Phương thức thanh toán</td><td class="v"><?= htmlspecialchars((string)($paymentMethod ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Số tiền</td><td class="v"><?= htmlspecialchars((string)($amount ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>
<?php if (!empty($siteUrl)): ?>
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/admin/orders/detail?id=' . (int)($orderId ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Xem Đơn Hàng</a>
<?php endif; ?>
<p class="mail-note">Đơn hàng sẽ tự động bị hủy nếu quá thời gian chờ thanh toán mà chưa được đối soát.</p>
<?php require __DIR__ . '/../_footer.php'; ?>