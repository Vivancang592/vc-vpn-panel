<?php
$pageTitle = "Quản Lý Đơn Hàng - Quản Trị Hệ Thống";
$activeMenu = "orders";

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

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Đơn Hàng</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            <?= !empty($filterUser) ? 'Danh sách đơn hàng của người dùng: <strong>' . htmlspecialchars($filterUser['username']) . '</strong> (ID #' . $filterUser['id'] . ')' : 'Danh sách tất cả đơn hàng giao dịch trong hệ thống' ?>
        </p>
    </div>
    <?php if (!empty($userId)): ?>
        <div>
            <a href="/admin/orders" class="glass-btn" style="text-decoration: none; white-space: nowrap; background: rgba(255, 59, 48, 0.1); color: var(--ios-danger);">✕ Xóa lọc tài khoản</a>
        </div>
    <?php endif; ?>
</div>

<!-- Bảng Đơn Hàng -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mã Đơn Hàng</th>
                    <th>Khách Hàng</th>
                    <th>Gói Cước</th>
                    <th>Mã Giảm Giá</th>
                    <th>Tổng Tiền</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $order['id'] ?></td>
                            <td>
                                <code style="background: rgba(0, 122, 255, 0.08); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700; color: var(--ios-blue); font-size: 0.85rem;">
                                    <?= htmlspecialchars($order['order_code']) ?>
                                </code>
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($order['username'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($order['email'] ?? '') ?></div>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem; color: var(--ios-text);">
                                <?= htmlspecialchars($order['plan_name'] ?? ('Gói #' . $order['plan_id'])) ?>
                            </td>
                            <td>
                                <?php if (!empty($order['coupon_code'])): ?>
                                    <span style="background: rgba(255, 149, 0, 0.12); color: var(--ios-warning); padding: 0.15rem 0.45rem; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.75rem;">
                                        <?= htmlspecialchars($order['coupon_code']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary); font-size: 0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-success);">
                                <?= isset($formatMoney) ? $formatMoney($order['total_amount']) : number_format($order['total_amount'], 2) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'completed' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'pending'   => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'failed'    => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);',
                                    'cancelled' => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$order['payment_status'] ?? 'pending'] ?? '' ?>">
                                    <?= strtoupper($order['payment_status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu" style="min-width: 175px; white-space: nowrap;">
                                        <a href="/admin/orders/detail?id=<?= $order['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>👁️</span> Xem chi tiết
                                        </a>

                                        <?php if (($order['payment_status'] ?? '') === 'pending'): ?>
                                            <button type="submit" form="approve-order-form-<?= $order['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-success); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                                <span>✅</span> Duyệt (Hoàn tất)
                                            </button>
                                        <?php endif; ?>

                                        <?php if (($order['payment_status'] ?? '') !== 'cancelled'): ?>
                                            <button type="submit" form="cancel-order-form-<?= $order['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-warning); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                                <span>❌</span> Hủy đơn hàng
                                            </button>
                                        <?php endif; ?>

                                        <button type="submit" form="delete-order-form-<?= $order['id'] ?>" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                            <span>🗑️</span> Xóa đơn hàng
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có đơn hàng nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Các Form ẩn để duyệt, hủy và xóa đơn hàng bằng POST -->
<?php if (!empty($orders)): ?>
    <?php foreach ($orders as $order): ?>
        <?php if (($order['payment_status'] ?? '') === 'pending'): ?>
            <form id="approve-order-form-<?= $order['id'] ?>" method="POST" action="/admin/orders/update-status" onsubmit="return confirm('Duyệt thành công đơn hàng này và cấp gói dịch vụ cho người dùng?');" style="display: none;">
                <input type="hidden" name="id" value="<?= $order['id'] ?>">
                <input type="hidden" name="status" value="completed">
                <?php if (!empty($userId)): ?>
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if (($order['payment_status'] ?? '') !== 'cancelled'): ?>
            <form id="cancel-order-form-<?= $order['id'] ?>" method="POST" action="/admin/orders/update-status" onsubmit="return confirm('Hủy đơn hàng này?');" style="display: none;">
                <input type="hidden" name="id" value="<?= $order['id'] ?>">
                <input type="hidden" name="status" value="cancelled">
                <?php if (!empty($userId)): ?>
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <form id="delete-order-form-<?= $order['id'] ?>" method="POST" action="/admin/orders/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn đơn hàng này?');" style="display: none;">
            <input type="hidden" name="id" value="<?= $order['id'] ?>">
            <?php if (!empty($userId)): ?>
                <input type="hidden" name="user_id" value="<?= $userId ?>">
            <?php endif; ?>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>