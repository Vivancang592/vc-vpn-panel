<?php
$authTitle = "Quên Mật Khẩu";
$authSubtitle = "Nhập email và mã OTP để đặt lại mật khẩu";
ob_start();
?>

<form action="/forgot-password" method="POST" class="auth-form" id="forgotForm">
    <!-- CSRF Protection Input Token -->
    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="alert-close" onclick="closeAlert(this)">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="alert-close" onclick="closeAlert(this)">&times;</button>
        </div>
    <?php endif; ?>

    <div id="ajax-alert" class="alert" style="display: none;"></div>

    <div class="form-group assemble-left">
        <label for="email">Địa chỉ Email đã đăng ký</label>
        <div class="input-group-custom">
            <span class="input-group-text-custom">✉️</span>
            <input type="email" id="email" name="email" class="form-control-custom" placeholder="email@domain.com" required autofocus autocomplete="email">
        </div>
    </div>

    <!-- Khối Nhập Mã Xác Thực OTP Email -->
    <div class="form-group assemble-right">
        <label for="otp_code">Mã xác thực OTP (Gửi qua Email)</label>
        <div class="input-group-custom" style="display: flex; gap: 8px;">
            <span class="input-group-text-custom">🔑</span>
            <input type="text" id="otp_code" name="otp_code" class="form-control-custom" placeholder="6 chữ số" maxlength="6" required style="flex: 1;">
            <button type="button" id="btnSendOtp" class="btn-toggle-pw btn-send-otp" onclick="sendOtpCode('/forgot-password/send-otp')">Gửi mã</button>
        </div>
    </div>

    <div class="form-group assemble-left">
        <label for="password">Mật khẩu mới</label>
        <div class="input-group-custom">
            <span class="input-group-text-custom">🔒</span>
            <input type="password" id="password" name="password" class="form-control-custom" placeholder="••••••••" required autocomplete="new-password">
            <button type="button" class="btn-toggle-pw toggle-password" data-target="password" onclick="togglePasswordVisibility(this)" title="Bật/Tắt hiển thị mật khẩu">👁️</button>
        </div>
    </div>

    <div class="assemble-bottom-1">
        <button type="submit" class="btn-login">CẬP NHẬT MẬT KHẨU</button>
    </div>
</form>

<div class="auth-footer assemble-bottom-2">
    <a href="/login">Quay lại Đăng nhập</a>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/auth.php';
?>