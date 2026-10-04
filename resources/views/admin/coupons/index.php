<?php
$pageTitle = "Quản Lý Mã Giảm Giá - Quản Trị Hệ Thống";
$activeMenu = "coupons";

$currencySymbol = $settings['currency_symbol'] ?? 'đ';
$currencyCode   = $settings['currency'] ?? 'VND';

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-success); display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['success']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Mã Giảm Giá</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Danh sách tất cả mã khuyến mãi và ưu đãi trong hệ thống</p>
    </div>
    <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
        <a href="/admin/coupons/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">+ Thêm Mã Giảm Giá</a>
    </div>
</div>

<!-- Bảng Mã Giảm Giá -->
<?php if (!empty($coupons) && !empty($users)): ?>
<div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between;">
    <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center;">
        <span style="font-size: 0.85rem; font-weight: 700; color: var(--ios-text);">🎯 Gán riêng theo user:</span>
        <select id="bulkAssignUser" style="padding: 0.45rem 0.7rem; border-radius: var(--radius-sm); background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: var(--ios-text); font-size: 0.85rem; min-width: 180px;">
            <option value="">— Chọn user —</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>">@<?= htmlspecialchars($u['username']) ?> (#<?= (int)$u['id'] ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="glass-btn glass-btn-primary" style="font-size: 0.82rem; padding: 0.45rem 0.9rem;" onclick="bulkSubmit('assign')">Gán mã đã chọn</button>
        <button type="button" class="glass-btn" style="font-size: 0.82rem; padding: 0.45rem 0.9rem;" onclick="bulkSubmit('unassign-user')">Gỡ của user này</button>
        <button type="button" class="glass-btn" style="font-size: 0.82rem; padding: 0.45rem 0.9rem; color: var(--ios-danger);" onclick="bulkSubmit('unassign-all')">Gỡ mọi user (công khai)</button>
    </div>
    <span id="bulkCount" style="font-size: 0.8rem; color: var(--ios-text-secondary);">0 mã được chọn</span>
</div>
<?php endif; ?>

<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th style="width: 36px; text-align: center;"><input type="checkbox" id="selectAllCoupons" title="Chọn tất cả"></th>
                    <th>ID</th>
                    <th>Mã Code</th>
                    <th>Loại Giảm Giá</th>
                    <th>Giá Trị Giảm</th>
                    <th style="text-align: center;">Lượt Sử Dụng</th>
                    <th>Ngày Hết Hạn</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th>Đối Tượng</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($coupons)): ?>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" class="coupon-checkbox" value="<?= (int)$coupon['id'] ?>">
                            </td>
                            <td style="font-weight: 700;">#<?= $coupon['id'] ?></td>
                            <td>
                                <code style="background: rgba(0, 122, 255, 0.08); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700; color: var(--ios-blue); font-size: 0.9rem;">
                                    <?= htmlspecialchars($coupon['code']) ?>
                                </code>
                            </td>
                            <td>
                                <span style="font-weight: 600; font-size: 0.85rem; color: var(--ios-text);">
                                    <?= ($coupon['discount_type'] ?? 'percent') === 'percent' ? 'Phần trăm (%)' : 'Số tiền cố định' ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-success);">
                                <?php if (($coupon['discount_type'] ?? 'percent') === 'percent'): ?>
                                    <?= (float)$coupon['discount_value'] ?>%
                                <?php else: ?>
                                    <?= isset($formatMoney) ? $formatMoney($coupon['discount_value']) : number_format($coupon['discount_value'], 2) ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-size: 0.85rem;">
                                <span style="font-weight: 700; color: var(--ios-text);"><?= (int)($coupon['used_count'] ?? 0) ?></span>
                                <span style="color: var(--ios-text-secondary);">/ <?= !empty($coupon['max_uses']) ? (int)$coupon['max_uses'] : '∞' ?></span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= !empty($coupon['expires_at']) ? date('d/m/Y H:i', strtotime($coupon['expires_at'])) : 'Vĩnh viễn' ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'active'   => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'inactive' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$coupon['status'] ?? 'active'] ?? '' ?>">
                                    <?= strtoupper($coupon['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int)($coupon['assigned_count'] ?? 0) === 0): ?>
                                    <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);">🌐 Công khai</span>
                                <?php else: ?>
                                    <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; background: rgba(0, 122, 255, 0.15); color: var(--ios-blue);">🔒 <?= (int)$coupon['assigned_count'] ?> user</span>
                                    <div style="font-size: 0.72rem; color: var(--ios-text-secondary); margin-top: 2px; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($coupon['assigned_names'] ?? '') ?>">
                                        <?= htmlspecialchars($coupon['assigned_names'] ?? '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                        <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                        <div class="action-menu">
                                            <a href="/admin/coupons/edit?id=<?= $coupon['id'] ?>" class="action-item">
                                                <span>✏️</span> Chỉnh sửa
                                            </a>
                                            <form method="POST" action="/admin/coupons/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa mã giảm giá này?');" style="margin: 0;">
                                                <input type="hidden" name="id" value="<?= $coupon['id'] ?>">
                                                <button type="submit" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                                    <span>🗑️</span> Xóa mã
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có mã giảm giá nào được tạo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const COUPON_CSRF = <?= json_encode($csrf_token ?? '', JSON_UNESCAPED_UNICODE) ?>;
    const selectAll = document.getElementById('selectAllCoupons');
    const bulkCount = document.getElementById('bulkCount');
    const boxes = () => Array.from(document.querySelectorAll('.coupon-checkbox'));

    function refreshCount() {
        if (bulkCount) bulkCount.textContent = boxes().filter(b => b.checked).length + ' mã được chọn';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            boxes().forEach(b => { b.checked = selectAll.checked; });
            refreshCount();
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('coupon-checkbox')) refreshCount();
    });

    // Tạo form động khi submit bulk — tránh lồng <form> với form xóa ở từng dòng
    window.bulkSubmit = function (mode) {
        const ids = boxes().filter(b => b.checked).map(b => b.value);
        if (!ids.length) { alert('Vui lòng chọn ít nhất một mã giảm giá!'); return; }

        const userSel = document.getElementById('bulkAssignUser');
        let url = '/admin/coupons/assign';

        if (mode === 'assign') {
            if (!userSel.value) { alert('Vui lòng chọn user nhận gán!'); return; }
            if (!confirm('Gán ' + ids.length + ' mã giảm giá đã chọn cho @' + userSel.options[userSel.selectedIndex].text + '?')) return;
        } else if (mode === 'unassign-user') {
            if (!userSel.value) { alert('Vui lòng chọn user cần gỡ gán!'); return; }
            url = '/admin/coupons/unassign';
        } else if (mode === 'unassign-all') {
            if (!confirm('Xóa TOÀN BỘ user được gán của ' + ids.length + ' mã đã chọn (đưa về công khai)?')) return;
            url = '/admin/coupons/unassign';
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        const append = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };
        append('csrf_token', COUPON_CSRF);
        ids.forEach(id => append('coupon_ids[]', id));
        if (mode === 'assign' || mode === 'unassign-user') append('user_id', userSel.value);
        document.body.appendChild(form);
        form.submit();
    };
})();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>