<?php
$pageTitle = "Thêm Mã Giảm Giá Mới - Quản Trị Hệ Thống";
$activeMenu = "coupons";

$currencySymbol = $settings['currency_symbol'] ?? 'đ';
$currencyCode   = $settings['currency'] ?? 'VND';

ob_start();
?>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Thêm Mã Giảm Giá Mới</h1>
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
    <form method="POST" action="/admin/coupons/create" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mã Giảm Giá (Code) (*)</label>
                <input type="text" name="code" class="glass-input" required placeholder="Nhập mã (ví dụ: TET2027, KM50...)" autofocus style="width: 100%; text-transform: uppercase;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Loại Giảm Giá (*)</label>
                <select name="discount_type" class="glass-input" required style="width: 100%; cursor: pointer;">
                    <option value="percent">Giảm theo phần trăm (%)</option>
                    <option value="fixed">Giảm số tiền cố định (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>)</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giá Trị Giảm (*)</label>
                <input type="number" name="discount_value" class="glass-input" required placeholder="Nhập % hoặc số tiền..." min="0" step="any" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giới Hạn Lượt Dùng (Tối Đa)</label>
                <input type="number" name="max_uses" class="glass-input" value="0" min="0" placeholder="0 = Không giới hạn" style="width: 100%;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Ngày Hết Hạn</label>
                <input type="datetime-local" name="expires_at" class="glass-input" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái</label>
                <select name="status" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="active" selected>Active (Hoạt động)</option>
                    <option value="inactive">Inactive (Khóa)</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gán Riêng Cho User</label>
                <select name="assigned_user_ids[]" multiple size="4" class="glass-input" style="width: 100%;">
                    <?php foreach (($users ?? []) as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">@<?= htmlspecialchars($u['username']) ?> (#<?= (int)$u['id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.35rem;">Bỏ trống = công khai (mọi user dùng được). Chọn = chỉ user đó. Giữ Ctrl/⌘ để chọn nhiều.</div>
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gói Áp Dụng</label>
                <?php
                $plansList = array_filter($plans ?? [], static function ($p) {
                    return ($p['status'] ?? '') === 'active'
                        && ($p['stock_quantity'] === null || (int)$p['stock_quantity'] > 0);
                });
                ?>
                <select name="plan_ids[]" multiple size="4" class="glass-input" style="width: 100%;">
                    <?php foreach ($plansList as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?> — <?= number_format((float)$p['price'], 0, '.', ',') ?>đ</option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.35rem;">Bỏ trống = áp dụng mọi gói. Chỉ hiện gói còn hàng. Giữ Ctrl/⌘ để chọn nhiều.</div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">➕ Tạo Mã Giảm Giá</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>