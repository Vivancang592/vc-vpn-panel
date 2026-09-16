<?php
$pageTitle = "Nhật Ký Truy Cập - Quản Trị Hệ Thống";
$activeMenu = "logs";

ob_start();
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Nhật Ký Truy Cập & Đăng Nhập</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Lịch sử truy cập, đăng nhập và xác thực của các thành viên</p>
    </div>
</div>

<!-- Bảng Nhật Ký Truy Cập -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Thành Viên</th>
                    <th>Hành Động</th>
                    <th>Địa Chỉ IP</th>
                    <th>Thiết Bị / Trình Duyệt (User Agent)</th>
                    <th>Thời Gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $log['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($log['username'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($log['email'] ?? '') ?></div>
                            </td>
                            <td>
                                <span style="background: rgba(52, 199, 89, 0.12); color: var(--ios-success); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.78rem;">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td>
                                <code style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.8rem;">
                                    <?= htmlspecialchars($log['ip_address']) ?>
                                </code>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($log['user_agent'] ?: '-') ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có dữ liệu truy cập nào.</td>
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