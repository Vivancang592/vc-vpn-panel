<?php
$pageTitle = "Quản Lý Hoa Hồng & Giới Thiệu - Quản Trị Hệ Thống";
$activeMenu = "referrals";

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
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Lịch Sử Hoa Hồng Tiếp Thị</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Danh sách biến động hoa hồng từ chương trình giới thiệu thành viên</p>
    </div>
</div>

<!-- Bảng Lịch Sử Hoa Hồng -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Người Nhận Hoa Hồng</th>
                    <th>Người Được Giới Thiệu</th>
                    <th>Mã Đơn Hàng</th>
                    <th style="text-align: center;">Tỷ Lệ</th>
                    <th>Tiền Hoa Hồng</th>
                    <th>Thời Gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($commissions)): ?>
                    <?php foreach ($commissions as $comm): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $comm['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.85rem; color: var(--ios-blue);"><?= htmlspecialchars($comm['referrer_username'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($comm['referrer_email'] ?? '') ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($comm['referred_username'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($comm['referred_email'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php if (!empty($comm['order_code'])): ?>
                                    <a href="/admin/orders/detail?id=<?= $comm['order_id'] ?>" style="text-decoration: none;">
                                        <code style="background: rgba(0, 122, 255, 0.08); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700; color: var(--ios-blue); font-size: 0.8rem;">
                                            #<?= htmlspecialchars($comm['order_code']) ?>
                                        </code>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary); font-size: 0.8rem;">#<?= $comm['order_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: rgba(255, 149, 0, 0.15); color: var(--ios-warning); padding: 0.15rem 0.45rem; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.78rem;">
                                    <?= (float)$comm['commission_rate'] ?>%
                                </span>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-success);">
                                +<?= isset($formatMoney) ? $formatMoney($comm['commission_amount']) : number_format($comm['commission_amount'], 2) ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($comm['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có lịch sử phát sinh hoa hồng nào.</td>
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