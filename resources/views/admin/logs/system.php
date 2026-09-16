<?php
$pageTitle = "Nhật Ký Hệ Thống - Quản Trị Hệ Thống";
$activeMenu = "logs";

ob_start();
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Nhật Ký Hoạt Động Hệ Thống</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Theo dõi các hành động quản trị và thao tác thay đổi cấu hình trong hệ thống</p>
    </div>
</div>

<!-- Bảng Nhật Ký Hệ Thống -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tài Khoản</th>
                    <th>Hành Động</th>
                    <th>Mô Tả Chi Tiết</th>
                    <th>Địa Chỉ IP</th>
                    <th>Thời Gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $log['id'] ?></td>
                            <td style="font-weight: 600; font-size: 0.85rem; color: var(--ios-blue);">
                                <?= htmlspecialchars($log['username'] ?? 'Hệ thống') ?>
                            </td>
                            <td>
                                <span style="background: rgba(0, 122, 255, 0.08); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.78rem; color: var(--ios-text);">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--ios-text-secondary); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($log['description'] ?: '-') ?>
                            </td>
                            <td>
                                <code style="background: rgba(0, 0, 0, 0.1); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm); font-size: 0.8rem;">
                                    <?= htmlspecialchars($log['ip_address'] ?: 'N/A') ?>
                                </code>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có nhật ký hoạt động nào.</td>
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