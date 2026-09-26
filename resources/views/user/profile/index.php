<?php
// Bắt đầu lưu bộ đệm nội dung
ob_start();
?>

<header class="user-orders-header">
    <div class="user-orders-intro"><h2 class="u-plans-title">TÀI KHOẢN</h2><p>Quản lý thông tin cá nhân và bảo mật tài khoản.</p></div>
    <div class="user-orders-title-row"><h1>Tài Khoản</h1></div>
</header>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert-success">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<div class="profile-grid">
        <div class="glass-card">
            <h2 class="profile-header">Thông tin cá nhân</h2>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="username">Tên đăng nhập</label>
                    <input class="glass-input" type="text" id="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Địa chỉ Email</label>
                    <input class="glass-input" type="email" id="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Ngày tham gia</label>
                    <input class="glass-input" type="text" value="<?= isset($user['created_at']) ? date('d/m/Y H:i', strtotime($user['created_at'])) : 'N/A' ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Liên kết Google</label>
                    <input class="glass-input" type="text" value="<?= !empty($user['google_id']) ? 'Đã liên kết' : 'Chưa liên kết' ?>" disabled>
                </div>
            </div>
        </div>

        <div class="glass-card profile-summary" style="align-self: start;">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, var(--ios-blue), #5ac8fa); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; margin: 0 auto 1rem; box-shadow: 0 4px 10px rgba(0,122,255,0.3);">
                    <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
                </div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--ios-text);"><?= htmlspecialchars($user['username'] ?? 'Người dùng') ?></h3>
                <p style="color: var(--ios-text-secondary); margin: 0.3rem 0 0; font-size: 0.85rem;"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                <div style="margin-top: 0.8rem;">
                    <span style="display: inline-block; padding: 0.35rem 0.8rem; background: rgba(0,122,255,0.1); color: var(--ios-blue); border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                        Thành viên <?= ucfirst($user['role'] ?? 'user') ?>
                    </span>
                </div>
            </div>

            <ul class="profile-info-list">
                <li><span style="color: var(--ios-text-secondary); font-weight: 600;">Số dư</span><span style="font-weight: 700; color: var(--ios-text);"><?= isset($formatMoney) ? $formatMoney($user['balance'] ?? 0) : number_format($user['balance'] ?? 0, 2) ?></span></li>
                <li><span style="color: var(--ios-text-secondary); font-weight: 600;">Hoa hồng</span><span style="font-weight: 700; color: var(--ios-text);"><?= isset($formatMoney) ? $formatMoney($user['commission_balance'] ?? 0) : number_format($user['commission_balance'] ?? 0, 2) ?></span></li>
                <li><span style="color: var(--ios-text-secondary); font-weight: 600;">Trạng thái</span><span style="font-weight: 700; color: <?= (($user['status'] ?? '') === 'active') ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;"><?= ucfirst($user['status'] ?? 'unknown') ?></span></li>
            </ul>
        </div>
    </div>

<div class="glass-card" style="width: 100%; box-sizing: border-box; margin-top: 1.2rem;">
    <h2 class="profile-header">Bảo mật tài khoản</h2>
    <form action="/user/profile/update" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

        <div class="form-group">
            <label class="form-label" for="new_password">Mật khẩu mới</label>
            <input class="glass-input" type="password" id="new_password" name="new_password" minlength="6" required placeholder="Nhập mật khẩu mới...">
        </div>
        <div style="text-align: right; margin-top: 1.5rem;">
            <button type="submit" class="glass-btn u-profile-submit">Lưu thay đổi</button>
        </div>
    </form>
    </div>

<?php
// Kết thúc bộ đệm và gán vào biến $content
$content = ob_get_clean();

// Gọi layout chính của app
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>