<?php
$mailTitle = 'Mã xác thực đặt lại mật khẩu';
$accent    = '#dc2626';
$mailBadge = 'Bảo mật tài khoản';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào Quý khách,</p>
<p class="mail-lead">Bạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu tài khoản trên <strong><?= htmlspecialchars((string)$siteTitle, ENT_QUOTES, 'UTF-8') ?></strong>. Mã xác thực là:</p>
<div class="mail-otp">
    <span class="code"><?= htmlspecialchars((string)($code ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    <p class="hint">Mã có hiệu lực trong <strong>5 phút</strong>.</p>
</div>
<ul class="mail-tips">
    <li>Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này và không chia sẻ mã với bất kỳ ai.</li>
    <li>Nên đổi mật khẩu ngay và không sử dụng lại mật khẩu cũ nếu bạn cho rằng tài khoản đã bị lộ.</li>
    <li>Không nhận được mail? Kiểm tra thư mục Spam/Thư rác hoặc gửi lại yêu cầu sau ít phút.</li>
</ul>
<?php require __DIR__ . '/../_footer.php'; ?>