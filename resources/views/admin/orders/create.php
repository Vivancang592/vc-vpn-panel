<?php
$pageTitle = "Tạo Đơn Hàng Thủ Công - Quản Trị Hệ Thống";
$activeMenu = "orders";

$currencySymbol = $settings['currency_symbol'] ?? 'đ';
$currencyCode   = $settings['currency'] ?? 'VND';

ob_start();
?>

<div style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Tạo Đơn Hàng Thủ Công</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Khởi tạo đơn hàng và tự động cấp gói cước cho thành viên</p>
    </div>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<style>
.admin-order-form-group {
    display: grid;
    grid-template-columns: 240px 1fr;
    align-items: center;
    gap: 1.25rem;
}
.admin-order-form-label {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--ios-text);
}
@media (max-width: 768px) {
    .admin-order-form-group {
        grid-template-columns: 1fr;
        gap: 0.4rem;
        align-items: flex-start;
    }
}
</style>

<div class="glass-card" style="padding: 1.75rem; width: 100%; box-sizing: border-box;">
    <form method="POST" action="/admin/orders/create" style="display: flex; flex-direction: column; gap: 1.25rem;">
        
        <div class="admin-order-form-group">
            <label class="admin-order-form-label">Người Dùng (*)</label>
            <div>
                <?php 
                    $selectedUserText = "ID #" . ($selectedUserId ?? '');
                    if (!empty($users) && !empty($selectedUserId)) {
                        foreach ($users as $u) {
                            if ((int)$u['id'] === (int)$selectedUserId) {
                                $selectedUserText = '#' . $u['id'] . ' - ' . $u['username'] . ' (' . $u['email'] . ')';
                                break;
                            }
                        }
                    }
                ?>
                <input type="text" class="glass-input" value="<?= htmlspecialchars($selectedUserText) ?>" readonly style="width: 100%; cursor: not-allowed; opacity: 0.8; box-sizing: border-box;">
                <input type="hidden" name="user_id" value="<?= (int)($selectedUserId ?? 0) ?>">
            </div>
        </div>

        <div class="admin-order-form-group">
            <label class="admin-order-form-label">Gói Cước (*)</label>
            <div>
                <select name="plan_id" id="plan_select" class="glass-input" required style="width: 100%; cursor: pointer; box-sizing: border-box;" onchange="updatePlanDetails(this)">
                    <option value="">-- Chọn gói cước --</option>
                    <?php if (!empty($plans)): ?>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>" 
                                    data-price="<?= (float)$p['price'] ?>"
                                    data-duration="<?= (int)$p['duration_days'] ?>"
                                    data-bandwidth="<?= (float)($p['bandwidth_limit_gb'] ?? 0) ?>"
                                    data-devices="<?= (int)($p['max_devices'] ?? 1) ?>">
                                <?= htmlspecialchars($p['name']) ?> (<?= isset($formatMoney) ? $formatMoney($p['price']) : number_format($p['price'], 2) ?> / <?= $p['duration_days'] ?> ngày)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <?php if (($_SESSION['role'] ?? '') !== 'staff'): ?>
            <div class="admin-order-form-group">
                <label class="admin-order-form-label">Số Tiền (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>)</label>
                <div>
                    <input type="number" name="amount" id="amount_input" class="glass-input" placeholder="Để trống để dùng giá mặc định của gói" step="any" min="0" style="width: 100%; box-sizing: border-box;">
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-order-form-group">
            <label class="admin-order-form-label">Trạng Thái Đơn Hàng (*)</label>
            <div>
                <select name="payment_status" class="glass-input" required style="width: 100%; cursor: pointer; box-sizing: border-box;">
                    <option value="completed" selected>Completed (Đã thanh toán & Cấp gói VPN)</option>
                    <option value="pending">Pending (Chờ thanh toán)</option>
                    <option value="failed">Failed (Thất bại)</option>
                    <option value="cancelled">Cancelled (Đã hủy)</option>
                </select>
            </div>
        </div>

        <?php if (($_SESSION['role'] ?? '') !== 'staff'): ?>
        <div style="background: rgba(0, 122, 255, 0.05); border: 1px solid rgba(0, 122, 255, 0.2); border-radius: var(--radius-md, 10px); padding: 1.25rem; display: flex; flex-direction: column; gap: 1.15rem;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--ios-blue, #007aff); display: flex; align-items: center; gap: 0.4rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(0, 122, 255, 0.15);">
                <span>⚙️</span> Tùy Chỉnh Thời Hạn, Thiết Bị & Data Cấp Phát
            </div>

            <div class="admin-order-form-group">
                <label class="admin-order-form-label">Ngày Hết Hạn</label>
                <div>
                    <input type="datetime-local" name="end_date" id="end_date_input" class="glass-input" style="width: 100%; box-sizing: border-box;">
                    <small style="color: var(--ios-text-secondary); font-size: 0.75rem; margin-top: 0.25rem; display: block;">Mặc định tự tính theo số ngày của gói</small>
                </div>
            </div>

            <div class="admin-order-form-group">
                <label class="admin-order-form-label">Số Thiết Bị Tối Đa</label>
                <div>
                    <input type="number" name="max_devices" id="devices_input" min="1" max="100" class="glass-input" placeholder="VD: 2" style="width: 100%; box-sizing: border-box;">
                    <small style="color: var(--ios-text-secondary); font-size: 0.75rem; margin-top: 0.25rem; display: block;">Số thiết bị được phép kết nối đồng thời</small>
                </div>
            </div>

            <div class="admin-order-form-group">
                <label class="admin-order-form-label">Data Sử Dụng (GB)</label>
                <div>
                    <input type="number" name="bandwidth_gb" id="bandwidth_input" min="0" step="any" class="glass-input" placeholder="0 = Không giới hạn" style="width: 100%; box-sizing: border-box;">
                    <small style="color: var(--ios-text-secondary); font-size: 0.75rem; margin-top: 0.25rem; display: block;">Dung lượng lưu lượng cấp phát (GB)</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem; font-weight: 700;">🛒 Khởi Tạo Đơn Hàng</button>
        </div>
    </form>
</div>

<script>
function updatePlanDetails(select) {
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const price = selectedOption.getAttribute('data-price') || '';
    const duration = parseInt(selectedOption.getAttribute('data-duration') || '30', 10);
    const bandwidth = selectedOption.getAttribute('data-bandwidth') || '0';
    const devices = selectedOption.getAttribute('data-devices') || '1';

    const amountInput = document.getElementById('amount_input');
    if (amountInput && !amountInput.value) {
        amountInput.placeholder = price;
    }

    const bandwidthInput = document.getElementById('bandwidth_input');
    if (bandwidthInput) {
        bandwidthInput.value = bandwidth;
    }

    const devicesInput = document.getElementById('devices_input');
    if (devicesInput) {
        devicesInput.value = devices;
    }

    const endDateInput = document.getElementById('end_date_input');
    if (endDateInput) {
        const targetDate = new Date();
        targetDate.setDate(targetDate.getDate() + duration);
        const year = targetDate.getFullYear();
        const month = String(targetDate.getMonth() + 1).padStart(2, '0');
        const day = String(targetDate.getDate()).padStart(2, '0');
        const hours = String(targetDate.getHours()).padStart(2, '0');
        const minutes = String(targetDate.getMinutes()).padStart(2, '0');
        endDateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
}
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>