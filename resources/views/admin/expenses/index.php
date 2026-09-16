<?php
$pageTitle = "Quản Lý Chi Phí - Quản Trị Hệ Thống";
$activeMenu = "expenses";

ob_start();

$totalExpenseAmount = array_sum(array_column($expenses ?? [], 'amount'));
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

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Chi Phí Vận Hành</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Thống kê chi phí máy chủ, hạ tầng và vận hành hệ thống</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <div class="glass-card" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
            Tổng chi: <strong style="color: var(--ios-danger); font-size: 1rem;"><?= isset($formatMoney) ? $formatMoney($totalExpenseAmount) : number_format($totalExpenseAmount, 2) ?></strong>
        </div>
        <a href="/admin/expenses/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">+ Thêm Khoản Chi</a>
    </div>
</div>

<!-- Bảng Chi Phí -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Khoản Chi</th>
                    <th>Danh Mục</th>
                    <th>Số Tiền</th>
                    <th>Ngày Chi</th>
                    <th>Ghi Chú</th>
                    <th>Ngày Tạo</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($expenses)): ?>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $expense['id'] ?></td>
                            <td style="font-weight: 700; font-size: 0.88rem; color: var(--ios-text);">
                                <?= htmlspecialchars($expense['title']) ?>
                            </td>
                            <td>
                                <span style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 600;">
                                    <?= htmlspecialchars($expense['category'] ?: 'Khác') ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-danger);">
                                <?= isset($formatMoney) ? $formatMoney($expense['amount']) : number_format($expense['amount'], 2) ?>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem;">
                                <?= date('d/m/Y', strtotime($expense['expense_date'])) ?>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--ios-text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($expense['note'] ?: '-') ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($expense['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/expenses/edit?id=<?= $expense['id'] ?>" class="action-item">
                                            <span>✏️</span> Chỉnh sửa
                                        </a>
                                        <form method="POST" action="/admin/expenses/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa khoản chi này?');" style="margin: 0;">
                                            <input type="hidden" name="id" value="<?= $expense['id'] ?>">
                                            <button type="submit" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <span>🗑️</span> Xóa khoản chi
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có khoản chi phí nào được ghi nhận.</td>
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