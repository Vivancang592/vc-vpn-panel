<?php
$mailTitle = 'Mã xác thực đăng ký tài khoản';
$accent    = '#2563eb';
$mailBadge = 'Xác thực tài khoản';
require __DIR__ . '/../_header.php';
?>
<p class="mail-greet">Kính chào Quý khách,</p>
<p class="mail-lead">Mã xác thực để hoàn tất đăng ký tài khoản trên <strong><?= htmlspecialchars((string)$siteTitle, ENT_QUOTES, 'UTF-8') ?></strong> là:</p>
<div class="mail-otp">
    <span class="code"><?= htmlspecialchars((string)($code ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    <p class="hint">Mã có hiệu lực trong <strong>5 phút</strong>.</p>
</div>
<ul class="mail-tips">
    <li>Không chia sẻ mã này với bất kỳ ai — nhân viên hỗ trợ sẽ không bao giờ hỏi mã OTP của bạn.</li>
    <li>Nếu bạn không yêu cầu đăng ký, hãy bỏ qua email này; không ai có thể tạo tài khoản mà không có mã này.</li>
    <li>Không nhận được mail? Kiểm tra thư mục Spam/Thư rác hoặc gửi lại yêu cầu sau ít phút.</li>
</ul>
<?php require __DIR__ . '/../_footer.php'; ?>