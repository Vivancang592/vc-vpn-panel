<?php
$authTitle = "Đăng Nhập Tài Khoản";
$authSubtitle = $siteSubtitle ?? "An Toàn - Bảo Mật - Uy Tín";
ob_start();
?>

<form action="/login" method="POST" class="auth-form">
    <!-- CSRF Protection Input Token -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="alert-close" onclick="closeAlert(this)">&times;</button>
        </div>
    <?php endif; ?>

    <div class="form-group assemble-left">
        <label for="username">Tên đăng nhập hoặc Email</label>
        <div class="input-group-custom">
            <span class="input-group-text-custom">👤</span>
            <input type="text" id="username" name="username" class="form-control-custom" placeholder="Email hoặc Username" required autofocus autocomplete="username">
        </div>
    </div>

    <div class="form-group assemble-right">
        <label for="password">Mật khẩu</label>
        <div class="input-group-custom">
            <span class="input-group-text-custom">🔒</span>
            <input type="password" id="password" name="password" class="form-control-custom" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="btn-toggle-pw toggle-password" data-target="password" onclick="togglePasswordVisibility(this)" title="Bật/Tắt hiển thị mật khẩu">👁️</button>
        </div>
    </div>

    <div class="assemble-bottom-1">
        <button type="submit" class="btn-login">ĐĂNG NHẬP</button>
    </div>
</form>

<div class="auth-divider">
    <span>HOẶC</span>
</div>

<div class="assemble-bottom-1">
    <a href="/auth/google" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
            <path d="M17.64 9.2c0-.74-.06-1.28-.19-1.84H9v3.34h4.96c-.1.83-.64 2.08-1.84 2.92l-.01.12 2.67 2.07.18.02c1.7-1.57 2.68-3.88 2.68-6.63z" fill="#4285F4"/>
            <path d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.84-2.21c-.76.53-1.78.9-3.12.9-2.38 0-4.41-1.57-5.13-3.72l-.11.01-2.77 2.15-.03.11C2.47 16.03 5.48 18 9 18z" fill="#34A853"/>
            <path d="M3.87 10.79c-.19-.58-.3-1.19-.3-1.79s.11-1.21.3-1.79l-.01-.13-2.78-2.16-.09.04C.35 6.22 0 7.57 0 9s.35 2.78.99 4.04l2.88-2.25z" fill="#FBBC05"/>
            <path d="M9 3.58c1.32 0 2.5.46 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0 5.48 0 2.47 1.97.99 4.96l2.87 2.23C4.59 5.04 6.62 3.58 9 3.58z" fill="#EA4335"/>
        </svg>
        <span>Đăng nhập bằng Google</span>
    </a>
</div>

<div class="auth-footer assemble-bottom-2">
    <a href="/forgot-password">Quên mật khẩu?</a>
    <span class="divider">|</span>
    <a href="/register">Đăng ký ngay</a>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/auth.php';
?>