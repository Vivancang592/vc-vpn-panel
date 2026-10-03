<?php
$planDisplay      = $plan_name ?? $planName ?? '';
$rawEndDate       = $end_date ?? $endDate ?? null;
$formattedEndDate = $rawEndDate ? date('d/m/Y H:i', strtotime($rawEndDate)) : '';
$mailTitle        = 'Gói cước đã hết hạn sử dụng';
$accent           = '#64748b';
$mailBadge        = 'Hết hạn';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào<?= !empty($username) ? ' ' . htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8') : '' ?>,</p>
<p class="mail-lead">Gói cước <strong><?= htmlspecialchars((string)$planDisplay, ENT_QUOTES, 'UTF-8') ?></strong> của bạn trên hệ thống <strong><?= htmlspecialchars((string)$siteTitle, ENT_QUOTES, 'UTF-8') ?></strong> đã hết hạn sử dụng<?= $formattedEndDate !== '' ? ' vào lúc <strong>' . htmlspecialchars($formattedEndDate, ENT_QUOTES, 'UTF-8') . '</strong>' : '' ?>. Dịch vụ đã tạm ngừng và kết nối VPN bị khóa cho đến khi bạn gia hạn.</p>
<table class="mail-info">
    <tr><td class="k">Gói cước</td><td class="v"><?= htmlspecialchars((string)$planDisplay, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Thời điểm hết hạn</td><td class="v"><?= $formattedEndDate !== '' ? htmlspecialchars($formattedEndDate, ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
    <tr><td class="k">Trạng thái</td><td class="v">Đã ngừng hoạt động</td></tr>
</table>
<p class="mail-lead">Bạn có thể đăng ký gói mới hoặc gia hạn để tiếp tục sử dụng dịch vụ ngay lập tức.</p>
<a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/plans', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Đăng Ký / Gia Hạn Ngay</a>
<p class="mail-note">Nếu bạn cho rằng email này là lỗi, vui lòng liên hệ bộ phận hỗ trợ kèm mã đơn hàng để được kiểm tra.</p>
<?php require __DIR__ . '/../_footer.php'; ?>