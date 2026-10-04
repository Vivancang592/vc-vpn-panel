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
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center;">
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

        <!-- Đối Tượng Áp Dụng (gán riêng cho user) -->
        <div style="background: rgba(0, 122, 255, 0.04); border: 1px solid rgba(0, 122, 255, 0.15); border-radius: var(--radius-md); padding: 1.1rem 1.25rem; width: 100%; box-sizing: border-box;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--ios-text); margin-bottom: 0.3rem;">🎯 Đối Tượng Áp Dụng</div>
            <div style="font-size: 0.82rem; color: var(--ios-text-secondary); margin-bottom: 0.85rem; line-height: 1.5;">
                Để trống = <strong>mã công khai</strong> (mọi user đều dùng được). Gán user = <strong>chỉ user được chọn</strong> mới áp dụng được mã này (trang thanh toán &amp; AI chat).
            </div>
            <div id="assignedChips" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.85rem; align-items: center; min-height: 30px;">
                <?php if (!empty($assigned_users)): ?>
                    <?php foreach ($assigned_users as $uid => $uname): ?>
                        <span class="assigned-chip" data-user="<?= (int)$uid ?>" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.6rem; border-radius: 999px; background: rgba(0, 122, 255, 0.12); color: var(--ios-blue); font-size: 0.8rem; font-weight: 600;">
                            🔒 @<?= htmlspecialchars($uname) ?>
                            <button type="button" onclick="removeAssigned(this)" title="Gỡ gán" style="background: none; border: none; color: var(--ios-blue); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0;">&times;</button>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span id="noAssignHint" style="font-size: 0.8rem; color: var(--ios-text-secondary); font-style: italic;">Đang công khai — chưa gán user riêng nào.</span>
                <?php endif; ?>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center;">
                <select id="assignUserSelect" class="glass-input" style="min-width: 220px; max-width: 320px; cursor: pointer;">
                    <option value="">— Chọn user —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">@<?= htmlspecialchars($u['username']) ?> (#<?= (int)$u['id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="glass-btn glass-btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="assignUser()">Gán user này</button>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">💾 Cập Nhật Thông Tin</button>
        </div>
    </form>
</div>

<script>
(function () {
    const ASSIGN_CSRF = <?= json_encode($csrf_token ?? '', JSON_UNESCAPED_UNICODE) ?>;
    const COUPON_ID   = <?= (int)($coupon['id'] ?? 0) ?>;

    function postAssign(action, userId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        const append = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };
        append('csrf_token', ASSIGN_CSRF);
        append('coupon_ids[]', COUPON_ID);
        append('user_id', userId);
        append('back', 'edit');
        append('coupon_id', COUPON_ID);
        document.body.appendChild(form);
        form.submit();
    }

    window.assignUser = function () {
        const sel = document.getElementById('assignUserSelect');
        if (!sel.value) { alert('Vui lòng chọn user cần gán!'); return; }
        if (document.querySelector('.assigned-chip[data-user="' + sel.value + '"]')) {
            alert('User này đã được gán rồi!');
            return;
        }
        postAssign('/admin/coupons/assign', sel.value);
    };

    window.removeAssigned = function (btn) {
        const chip = btn.closest('.assigned-chip');
        if (!chip) return;
        if (!confirm('Gỡ user này khỏi mã giảm giá?')) return;
        postAssign('/admin/coupons/unassign', chip.dataset.user);
    };
})();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>