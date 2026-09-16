<?php
$pageTitle = "Nhật Ký Email - Quản Trị Hệ Thống";
$activeMenu = "logs";

ob_start();
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Nhật Ký Gửi Email Hệ Thống</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Theo dõi trạng thái gửi email thông báo, khôi phục mật khẩu và hóa đơn</p>
    </div>
</div>

<!-- Bảng Nhật Ký Email -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email Người Nhận</th>
                    <th>Tiêu Đề Email</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th>Thông Báo Lỗi</th>
                    <th>Thời Gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $log['id'] ?></td>
                            <td style="font-weight: 600; font-size: 0.85rem; color: var(--ios-blue);">
                                <?= htmlspecialchars($log['recipient']) ?>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem; color: var(--ios-text);">
                                <?= htmlspecialchars($log['subject']) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if (($log['status'] ?? 'sent') === 'sent'): ?>
                                    <span style="background: rgba(52, 199, 89, 0.15); color: var(--ios-success); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700;">
                                        ĐÃ GỬI
                                    </span>
                                <?php else: ?>
                                    <span style="background: rgba(255, 59, 48, 0.15); color: var(--ios-danger); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700;">
                                        THẤT BẠI
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-danger); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($log['error_message'] ?: '-') ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có nhật ký gửi email nào.</td>
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