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

<div class="glass-card" style="padding: 1.75rem; width: 100%;">
    <form method="POST" action="/admin/orders/create" style="display: flex; flex-direction: column; gap: 1.25rem;">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <!-- Người Dùng (Cố định theo ID truyền vào) -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Người Dùng (*)</label>
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
                <input type="text" class="glass-input" value="<?= htmlspecialchars($selectedUserText) ?>" readonly style="width: 100%; cursor: not-allowed; opacity: 0.8;">
                <input type="hidden" name="user_id" value="<?= (int)($selectedUserId ?? 0) ?>">
            </div>

            <!-- Chọn Gói Cước -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gói Cước (*)</label>
                <select name="plan_id" id="plan_select" class="glass-input" required style="width: 100%; cursor: pointer;" onchange="updatePriceHint(this)">
                    <option value="">-- Chọn gói cước --</option>
                    <?php if (!empty($plans)): ?>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>" data-price="<?= (float)$p['price'] ?>">
                                <?= htmlspecialchars($p['name']) ?> (<?= isset($formatMoney) ? $formatMoney($p['price']) : number_format($p['price'], 2) ?> / <?= $p['duration_days'] ?> ngày)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <!-- Số Tiền -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Số Tiền Thanh Toán (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>)</label>
                <input type="number" name="amount" id="amount_input" class="glass-input" placeholder="Để trống để dùng giá mặc định của gói" step="any" min="0" style="width: 100%;">
            </div>

            <!-- Trạng Thái Thanh Toán -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái Đơn Hàng (*)</label>
                <select name="payment_status" class="glass-input" required style="width: 100%; cursor: pointer;">
                    <option value="completed" selected>Completed (Đã thanh toán & Cấp gói VPN)</option>
                    <option value="pending">Pending (Chờ thanh toán)</option>
                    <option value="failed">Failed (Thất bại)</option>
                    <option value="cancelled">Cancelled (Đã hủy)</option>
                </select>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">🛒 Khởi Tạo Đơn Hàng</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>