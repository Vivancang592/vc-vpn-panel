<?php
$pageTitle = "Quản Lý Gói Cước - Quản Trị Hệ Thống";
$activeMenu = "plans";

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
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Gói Cước</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Danh sách các gói dịch vụ VPN, giá bán (<?= htmlspecialchars($currencyCode) ?>) và thông số giới hạn</p>
    </div>
    <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
        <a href="/admin/plans/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">+ Thêm Gói Cước</a>
    </div>
</div>

<!-- Bảng Gói Cước -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Gói Cước</th>
                    <th>Mã Code</th>
                    <th>Nhóm Server</th>
                    <th>Giá Bán (<?= htmlspecialchars($currencyCode) ?>)</th>
                    <th style="text-align: center;">Thời Hạn</th>
                    <th style="text-align: center;">Dung Lượng</th>
                    <th style="text-align: center;">Thiết Bị</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($plans)): ?>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $plan['id'] ?></td>
                            <td style="font-weight: 700; font-size: 0.9rem; color: var(--ios-text);"><?= htmlspecialchars($plan['name']) ?></td>
                            <td>
                                <code style="background: rgba(0, 122, 255, 0.08); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700; color: var(--ios-text);">
                                    <?= htmlspecialchars($plan['code']) ?>
                                </code>
                            </td>
                            <td style="color: var(--ios-text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($plan['group_name'] ?? 'Chưa phân nhóm') ?></td>
                            <td style="font-weight: 700; color: var(--ios-success);">
                                <?= isset($formatMoney) ? $formatMoney($plan['price']) : number_format($plan['price'], 2) ?>
                            </td>
                            <td style="text-align: center; font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($plan['duration_days']) ?> Ngày</td>
                            <td style="text-align: center; font-weight: 600; font-size: 0.85rem;">
                                <?= ($plan['bandwidth_limit_gb'] > 0) ? htmlspecialchars($plan['bandwidth_limit_gb']) . ' GB' : '<span style="color: var(--ios-success); font-weight: 700;">Không giới hạn</span>' ?>
                            </td>
                            <td style="text-align: center; font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($plan['max_devices']) ?> Máy</td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'active'   => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'inactive' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$plan['status'] ?? 'active'] ?? '' ?>">
                                    <?= strtoupper($plan['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/plans/edit?id=<?= $plan['id'] ?>" class="action-item">
                                            <span>✏️</span> Chỉnh sửa
                                        </a>
                                        <form method="POST" action="/admin/plans/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa gói cước này?');" style="margin: 0;">
                                            <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                                            <button type="submit" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <span>🗑️</span> Xóa gói cước
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có gói cước nào được tạo.</td>
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