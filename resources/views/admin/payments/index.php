<?php
$pageTitle = "Quản Lý Thanh Toán - Quản Trị Hệ Thống";
$activeMenu = "payments";

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
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
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Thanh Toán</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Lịch sử giao dịch nạp tiền và thanh toán đơn hàng trong hệ thống</p>
    </div>
</div>

<!-- Bảng Lịch Sử Thanh Toán -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách Hàng</th>
                    <th>Loại Giao Dịch</th>
                    <th>Mã Đơn Hàng</th>
                    <th>Phương Thức</th>
                    <th>Mã Giao Dịch</th>
                    <th>Số Tiền</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th>Thời Gian</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $payment['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($payment['username'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($payment['email'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php if (($payment['type'] ?? 'payment') === 'deposit'): ?>
                                    <span style="background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700;">
                                        NẠP TIỀN
                                    </span>
                                <?php else: ?>
                                    <span style="background: rgba(88, 86, 214, 0.15); color: #5856D6; padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700;">
                                        THANH TOÁN
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($payment['order_code'])): ?>
                                    <code style="background: rgba(0, 122, 255, 0.08); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.8rem; color: var(--ios-text);">
                                        <?= htmlspecialchars($payment['order_code']) ?>
                                    </code>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary); font-size: 0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: var(--ios-text);">
                                <?= htmlspecialchars($payment['payment_method']) ?>
                            </td>
                            <td>
                                <?php if (!empty($payment['transaction_id'])): ?>
                                    <code style="background: rgba(0, 0, 0, 0.1); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); font-size: 0.8rem;">
                                        <?= htmlspecialchars($payment['transaction_id']) ?>
                                    </code>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary); font-size: 0.8rem;">Tự động</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-success);">
                                <?= isset($formatMoney) ? $formatMoney($payment['amount']) : number_format($payment['amount'], 2) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'success' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'pending' => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'failed'  => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$payment['status'] ?? 'pending'] ?? '' ?>">
                                    <?= strtoupper($payment['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/payments/detail?id=<?= $payment['id'] ?>" class="action-item">
                                            <span>👁️</span> Xem chi tiết
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có lịch sử thanh toán nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>