<?php
$mailTitle = 'Tạm ngắt kết nối do vượt số thiết bị';
$accent    = '#dc2626';
$mailBadge = 'Bảo mật tài khoản';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào<?= !empty($username) ? ' ' . htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8') : '' ?>,</p>
<p class="mail-lead">Hệ thống phát hiện có <strong><?= (int)$ip_count ?> thiết bị / IP đang kết nối</strong> vượt quá giới hạn của gói <strong><?= htmlspecialchars((string)$plan_name, ENT_QUOTES, 'UTF-8') ?></strong> (cho phép tối đa <strong><?= (int)$max_devices ?> thiết bị</strong>). Để bảo vệ tài khoản, kết nối của bạn đã tạm thời bị ngắt.</p>
<table class="mail-info">
    <tr><td class="k">Gói cước</td><td class="v"><?= htmlspecialchars((string)$plan_name, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td class="k">Số thiết bị cho phép</td><td class="v"><?= (int)$max_devices ?> thiết bị</td></tr>
    <tr><td class="k">Số IP đang kết nối</td><td class="v"><?= (int)$ip_count ?> IP</td></tr>
    <tr><td class="k">Thời gian tạm ngắt</td><td class="v"><?= (int)$lock_seconds ?> giây (tự mở lại)</td></tr>
</table>
<p class="mail-lead">Kết nối sẽ <strong>tự động được mở lại sau <?= (int)$lock_seconds ?> giây</strong>. Nếu bạn tiếp tục vượt quá giới hạn, cơ chế khóa sẽ tiếp tục được áp dụng.</p>
<ul class="mail-tips">
    <li>Đóng kết nối trên các thiết bị không còn sử dụng để số thiết bị trở lại trong hạn mức.</li>
    <li>Không chia sẻ tài khoản ngoài gói đăng ký — đây là vi phạm Điều Khoản Sử Dụng.</li>
    <li>Nếu bạn cần sử dụng nhiều thiết bị hơn, hãy nâng cấp gói có số thiết bị phù hợp.</li>
</ul>
<div style="text-align:center;"><a href="<?= htmlspecialchars(rtrim((string)$siteUrl, '/') . '/subscriptions', ENT_QUOTES, 'UTF-8') ?>" class="mail-btn">Kiểm Tra Thiết Bị Đang Kết Nối</a></div>
<p class="mail-note">Thông báo này được gửi tối đa 1 lần mỗi 30 phút. Nếu số thiết bị đã trở lại hạn mức, bạn không cần thực hiện thêm thao tác nào.</p>
<?php require __DIR__ . '/../_footer.php'; ?>
