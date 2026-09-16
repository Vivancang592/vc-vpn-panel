<?php
$pageTitle = "Sửa Người Dùng - Quản Trị Hệ Thống";
$activeMenu = "users";

$currencySymbol = $settings['currency_symbol'] ?? 'đ';
$currencyCode   = $settings['currency'] ?? 'VND';

ob_start();
?>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Chỉnh Sửa: #<?= htmlspecialchars($user['username']) ?></h1>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div class="glass-card" style="padding: 1.75rem; width: 100%;">
    <form method="POST" action="/admin/users/edit?id=<?= $user['id'] ?>" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tên Đăng Nhập</label>
                <input type="text" class="glass-input" value="<?= htmlspecialchars($user['username']) ?>" disabled style="opacity: 0.7; width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Email</label>
                <input type="email" class="glass-input" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity: 0.7; width: 100%;">
            </div>
        </div>

        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mật Khẩu Mới (Để trống nếu không đổi)</label>
            <input type="password" name="new_password" class="glass-input" placeholder="Nhập mật khẩu mới..." style="width: 100%;">
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Vai Trò</label>
                <select name="role" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái</label>
                <select name="status" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Số Dư Chính (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>)</label>
                <input type="number" name="balance" class="glass-input" value="<?= (float)$user['balance'] ?>" step="any" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Số Dư Hoa Hồng (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>)</label>
                <input type="number" name="commission_balance" class="glass-input" value="<?= (float)$user['commission_balance'] ?>" step="any" style="width: 100%;">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">💾 Cập Nhật Thông Tin</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>