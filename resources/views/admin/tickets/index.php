<?php
$pageTitle = "Quản Lý Hỗ Trợ - Quản Trị Hệ Thống";
$activeMenu = "tickets";

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
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Danh Sách Yêu Cầu Hỗ Trợ (Tickets)</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Tiếp nhận và phản hồi các yêu cầu trợ giúp kỹ thuật từ thành viên</p>
    </div>
</div>

<!-- Bảng Yêu Cầu Hỗ Trợ -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách Hàng</th>
                    <th>Tiêu Đề</th>
                    <th>Nhân Viên Xử Lý</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tickets)): ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $ticket['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($ticket['user_name'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($ticket['user_email'] ?? '') ?></div>
                            </td>
                            <td style="font-weight: 600; font-size: 0.88rem; color: var(--ios-text);">
                                <?= htmlspecialchars($ticket['subject']) ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php if (!empty($ticket['staff_name'])): ?>
                                    <span style="font-weight: 600; color: var(--ios-blue);"><?= htmlspecialchars($ticket['staff_name']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary); italic">Chưa gán</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'open'        => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'in_progress' => 'background: rgba(0, 122, 255, 0.15); color: var(--ios-blue);',
                                    'resolved'    => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'closed'      => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);'
                                ];
                                $statusText = [
                                    'open'        => 'MỚI TẠO',
                                    'in_progress' => 'ĐANG XỬ LÝ',
                                    'resolved'    => 'ĐÃ GIẢI QUYẾT',
                                    'closed'      => 'ĐÃ ĐÓNG'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$ticket['status'] ?? 'open'] ?? '' ?>">
                                    <?= $statusText[$ticket['status'] ?? 'open'] ?? strtoupper($ticket['status']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/tickets/detail?id=<?= $ticket['id'] ?>" class="action-item">
                                            <span>💬</span> Xem & Phản hồi
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có yêu cầu hỗ trợ nào.</td>
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