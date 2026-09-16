<?php
$pageTitle = "Quản Lý Máy Chủ - Quản Trị Hệ Thống";
$activeMenu = "servers";

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
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Máy Chủ</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Quản lý hạ tầng máy chủ và thông số API kết nối</p>
    </div>
    <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
        <a href="/admin/servers/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">+ Thêm Máy Chủ Mới</a>
    </div>
</div>

<!-- Bảng Máy Chủ -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Máy Chủ</th>
                    <th>Nhóm</th>
                    <th>Quốc Gia</th>
                    <th>Vị Trí</th>
                    <th>IP Address</th>
                    <th>API Port</th>
                    <th>Trạng Thái</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($servers)): ?>
                    <?php foreach ($servers as $server): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $server['id'] ?></td>
                            <td style="font-weight: 700; font-size: 0.9rem; color: var(--ios-text);"><?= htmlspecialchars($server['name']) ?></td>
                            <td style="color: var(--ios-text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($server['group_name'] ?? 'Chưa phân nhóm') ?></td>
                            <td>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); text-transform: uppercase;">
                                    <?= htmlspecialchars($server['country_code']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--ios-text);"><?= htmlspecialchars($server['location']) ?></td>
                            <td style="font-size: 0.85rem;">
                                <code style="background: rgba(0, 122, 255, 0.08); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 600; color: var(--ios-text);">
                                    <?= htmlspecialchars($server['ip_address']) ?>
                                </code>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($server['api_port']) ?></td>
                            <td>
                                <?php
                                $statusBadge = [
                                    'active'      => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'maintenance' => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'offline'     => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$server['status'] ?? 'active'] ?? '' ?>">
                                    <?= strtoupper($server['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/servers/detail?id=<?= $server['id'] ?>" class="action-item">
                                            <span>👁️</span> Xem chi tiết
                                        </a>
                                        <form method="POST" action="/admin/servers/sync" onsubmit="return confirm('Xác nhận tạo task đồng bộ tất cả gói active thuộc nhóm này sang máy chủ?');" style="margin: 0;">
                                            <input type="hidden" name="id" value="<?= $server['id'] ?>">
                                            <button type="submit" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-blue); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <span>🔄</span> Đồng bộ
                                            </button>
                                        </form>
                                        <a href="/admin/servers/edit?id=<?= $server['id'] ?>" class="action-item">
                                            <span>✏️</span> Chỉnh sửa
                                        </a>
                                        <form method="POST" action="/admin/servers/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa máy chủ này?');" style="margin: 0;">
                                            <input type="hidden" name="id" value="<?= $server['id'] ?>">
                                            <button type="submit" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <span>🗑️</span> Xóa máy chủ
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có máy chủ nào được khởi tạo.</td>
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