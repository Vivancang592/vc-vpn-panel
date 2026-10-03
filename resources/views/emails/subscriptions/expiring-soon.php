<?php
$planDisplay      = $plan_name ?? $planName ?? '';
$rawEndDate       = $end_date ?? $endDate ?? null;
$formattedEndDate = $rawEndDate ? date('d/m/Y H:i', strtotime($rawEndDate)) : '';
$daysDisplay      = $daysLeft ?? ($rawEndDate ? max(1, (int)ceil((strtotime($rawEndDate) - time()) / 86400)) : 0);
$mailTitle        = 'Gói cước sắp hết hạn sử dụng';
$accent           = '#d97706';
$mailBadge        = 'Sắp hết hạn';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào<?= !empty($username) ? ' ' . htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8') : '' ?>,</p>
<p class="mail-lead">Gói cước <strong><?= htmlspecialchars((string)$planDisplay, ENT_QUOTES, 'UTF-8') ?></strong> của bạn sắp hết hạn. Vui lòng gia hạn trước thời điểm hết hạn để không bị gián đoạn kết nối.</p>
<table class="mail-info">
    <tr><td class="k">Gói cước</td><td class="v"><?= htmlspecialchars((string)$planDisplay, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Thời gian còn lại</td><td class="v">Còn <?= (int)$daysDisplay ?> ngày</td></tr>
    <tr><td class="k">Thời điểm hết hạn</td><td class="v"><?= $formattedEndDate !== '' ? htmlspecialchars($formattedEndDate, ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
</table>
<p class="mail-lead">Sau khi hết hạn, dịch vụ sẽ tạm ngừng và kết nối VPN của bạn sẽ bị khóa cho đến khi gói được gia hạn.</p>
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/subscriptions', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Gia Hạn Ngay</a>
<p class="mail-note">Gói cước sẽ được tự động duy trì trạng thái đến thời điểm hết hạn hiển thị ở trên.</p>
<?php require __DIR__ . '/../_footer.php'; ?>