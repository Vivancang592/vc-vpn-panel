<?php
$pageTitle = "Chỉnh Sửa Mã Giảm Giá - Quản Trị Hệ Thống";
$activeMenu = "coupons";

$currencySymbol = $settings['currency_symbol'] ?? 'đ';
$currencyCode   = $settings['currency'] ?? 'VND';

ob_start();
?>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Chỉnh Sửa: <?= htmlspecialchars($coupon['code'] ?? '') ?></h1>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="glass-card" style="padding: 1.75rem; width: 100%;">
    <form method="POST" action="/admin/coupons/edit?id=<?= $coupon['id'] ?>" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mã Giảm Giá (Code) (*)</label>
                <input type="text" name="code" class="glass-input" value="<?= htmlspecialchars($coupon['code'] ?? '') ?>" required style="width: 100%; text-transform: uppercase;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Loại Giảm Giá (*)</label>
                <select name="discount_type" class="glass-input" required style="width: 100%; cursor: pointer;">
                    <option value="percent" <?= ($coupon['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Giảm theo phần trăm (%)</option>
                    <option value="fixed" <?= ($coupon['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Giảm số tiền cố định (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>)</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giá Trị Giảm (*)</label>
                <input type="number" name="discount_value" class="glass-input" value="<?= (float)($coupon['discount_value'] ?? 0) ?>" required min="0" step="any" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giới Hạn Lượt Dùng (Tối Đa)</label>
                <input type="number" name="max_uses" class="glass-input" value="<?= (int)($coupon['max_uses'] ?? 0) ?>" min="0" style="width: 100%;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Ngày Hết Hạn</label>
                <input type="datetime-local" name="expires_at" class="glass-input" value="<?= !empty($coupon['expires_at']) ? date('Y-m-d\TH:i', strtotime($coupon['expires_at'])) : '' ?>" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái</label>
                <select name="status" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="active" <?= ($coupon['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active (Hoạt động)</option>
                    <option value="inactive" <?= ($coupon['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Khóa)</option>
                </select>
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