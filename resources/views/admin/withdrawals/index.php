<?php
$pageTitle = "Quản Lý Rút Tiền - Quản Trị Hệ Thống";
$activeMenu = "withdrawals";

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
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Yêu Cầu Rút Tiền</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Danh sách yêu cầu rút tiền hoa hồng từ các thành viên trong hệ thống</p>
    </div>
</div>

<!-- Bảng Yêu Cầu Rút Tiền -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách Hàng</th>
                    <th>Số Tiền Rút</th>
                    <th>Ngân Hàng</th>
                    <th>Số Tài Khoản</th>
                    <th>Tên Tài Khoản</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($withdrawals)): ?>
                    <?php foreach ($withdrawals as $w): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $w['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($w['username'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($w['email'] ?? '') ?></div>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-success);">
                                <?= isset($formatMoney) ? $formatMoney($w['amount']) : number_format($w['amount'], 2) ?>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem; color: var(--ios-text);">
                                <?= htmlspecialchars($w['bank_name']) ?>
                            </td>
                            <td>
                                <code style="background: rgba(0, 122, 255, 0.08); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700; color: var(--ios-blue); font-size: 0.85rem;">
                                    <?= htmlspecialchars($w['bank_account_number']) ?>
                                </code>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">
                                <?= htmlspecialchars($w['bank_account_name']) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'approved' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'pending'  => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'rejected' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$w['status'] ?? 'pending'] ?? '' ?>">
                                    <?= strtoupper($w['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($w['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/withdrawals/detail?id=<?= $w['id'] ?>" class="action-item">
                                            <span>👁️</span> Xem chi tiết
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có yêu cầu rút tiền nào.</td>
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