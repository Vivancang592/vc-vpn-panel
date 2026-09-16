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
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mã Code</th>
                    <th>Loại Giảm Giá</th>
                    <th>Giá Trị Giảm</th>
                    <th style="text-align: center;">Lượt Sử Dụng</th>
                    <th>Ngày Hết Hạn</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($coupons)): ?>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
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
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có mã giảm giá nào được tạo.</td>
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