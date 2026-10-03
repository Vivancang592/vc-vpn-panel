<?php
$planDisplay  = $plan_name ?? $planName ?? '';
$limitDisplay = $limitGb ?? $limit_gb ?? (isset($transfer_enable) ? round($transfer_enable / (1024 * 1024 * 1024), 2) : '');
$mailTitle    = 'Đã hết hạn mức lưu lượng dữ liệu';
$accent       = '#ea580c';
$mailBadge    = 'Hết dung lượng';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào<?= !empty($username) ? ' ' . htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8') : '' ?>,</p>
<p class="mail-lead">Gói cước <strong><?= htmlspecialchars((string)$planDisplay, ENT_QUOTES, 'UTF-8') ?></strong> của bạn đã sử dụng hết hạn mức lưu lượng<?= $limitDisplay !== '' ? ' (<strong>' . htmlspecialchars((string)$limitDisplay, ENT_QUOTES, 'UTF-8') . ' GB</strong>)' : '' ?>. Kết nối VPN tạm thời bị ngắt để tránh phát sinh chi phí ngoài kế hoạch.</p>
<table class="mail-info">
    <tr><td class="k">Gói cước</td><td class="v"><?= htmlspecialchars((string)$planDisplay, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Hạn mức lưu lượng</td><td class="v"><?= $limitDisplay !== '' ? htmlspecialchars((string)$limitDisplay, ENT_QUOTES, 'UTF-8') . ' GB' : '—' ?></td></tr>
    <tr><td class="k">Trạng thái</td><td class="v">Tạm ngừng do hết dung lượng</td></tr>
</table>
<p class="mail-lead">Bạn có thể nâng cấp lên gói lớn hơn hoặc chờ chu kỳ làm mới dữ liệu (nếu gói có hỗ trợ) để tiếp tục sử dụng.</p>
<div style="text-align:center;"><a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/plans', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Nâng Cấp / Gia Hạn Ngay</a></div>
<p class="mail-note">Cần hỗ trợ thêm? Liên hệ bộ phận hỗ trợ kèm mã đơn hàng để được kiểm tra gói cước nhanh chóng.</p>
<?php require __DIR__ . '/../_footer.php'; ?>