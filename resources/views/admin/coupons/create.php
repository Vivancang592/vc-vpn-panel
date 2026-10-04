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

        <!-- Đối Tượng Áp Dụng (gán riêng cho user) -->
        <div style="background: rgba(0, 122, 255, 0.04); border: 1px solid rgba(0, 122, 255, 0.15); border-radius: var(--radius-md); padding: 1.1rem 1.25rem; width: 100%; box-sizing: border-box;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--ios-text); margin-bottom: 0.3rem;">🎯 Đối Tượng Áp Dụng</div>
            <div style="font-size: 0.82rem; color: var(--ios-text-secondary); margin-bottom: 0.85rem; line-height: 1.5;">
                Bỏ trống = <strong>mã công khai</strong> (mọi user đều dùng được). Chọn user = <strong>chỉ user được chọn</strong> mới áp dụng được mã này (trang thanh toán &amp; AI chat).
            </div>
            <select name="assigned_user_ids[]" multiple size="6" class="glass-input" style="width: 100%; max-width: 420px;">
                <?php foreach (($users ?? []) as $u): ?>
                    <option value="<?= (int)$u['id'] ?>">@<?= htmlspecialchars($u['username']) ?> (#<?= (int)$u['id'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.4rem;">Giữ Ctrl (⌘ trên Mac) để chọn nhiều user.</div>
        </div>

        <!-- Gói Áp Dụng Mã -->
        <div style="background: rgba(255, 149, 0, 0.05); border: 1px solid rgba(255, 149, 0, 0.18); border-radius: var(--radius-md); padding: 1.1rem 1.25rem; width: 100%; box-sizing: border-box;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--ios-text); margin-bottom: 0.3rem;">📦 Gói Áp Dụng Mã</div>
            <div style="font-size: 0.82rem; color: var(--ios-text-secondary); margin-bottom: 0.85rem; line-height: 1.5;">
                Bỏ chọn tất cả = mã áp dụng cho <strong>mọi gói dịch vụ</strong>. Chọn gói = <strong>chỉ gói được chọn</strong> mới áp dụng mã này.
            </div>
            <?php $plansList = $plans ?? []; ?>
            <?php if (empty($plansList)): ?>
                <div style="font-size: 0.82rem; color: var(--ios-text-secondary); font-style: italic;">Chưa có gói dịch vụ nào trong hệ thống — mã sẽ áp dụng cho tất cả.</div>
            <?php else: ?>
                <div style="display: flex; flex-wrap: wrap; gap: 0.6rem 1.25rem;">
                    <?php foreach ($plansList as $p): ?>
                        <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.85rem; color: var(--ios-text); cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="plan_ids[]" value="<?= (int)$p['id'] ?>" style="accent-color: #ff9500; width: 16px; height: 16px; cursor: pointer;">
                            <?= htmlspecialchars($p['name']) ?>
                            <span style="color: var(--ios-text-secondary); font-size: 0.75rem;"><?= number_format((float)$p['price'], 0, '.', ',') ?>đ</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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