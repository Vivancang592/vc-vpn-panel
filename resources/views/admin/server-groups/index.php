<?php
$pageTitle = "Quản Lý Nhóm Máy Chủ - Quản Trị Hệ Thống";
$activeMenu = "server-groups";

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
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Nhóm Máy Chủ</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Quản lý và phân loại các cụm server cho người dùng</p>
    </div>
    <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
        <a href="/admin/server-groups/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">+ Thêm Nhóm Mới</a>
    </div>
</div>

<!-- Bảng Nhóm Máy Chủ -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Nhóm</th>
                    <th>Mô Tả</th>
                    <th>Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($groups)): ?>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $group['id'] ?></td>
                            <td style="font-weight: 700; font-size: 0.9rem; color: var(--ios-text);"><?= htmlspecialchars($group['name']) ?></td>
                            <td style="color: var(--ios-text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($group['description'] ?? 'Chưa có mô tả') ?></td>
                            <td>
                                <?php
                                $statusBadge = [
                                    'active'   => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'inactive' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$group['status'] ?? 'active'] ?? '' ?>">
                                    <?= strtoupper($group['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= !empty($group['created_at']) ? date('d/m/Y H:i', strtotime($group['created_at'])) : 'N/A' ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/server-groups/edit?id=<?= $group['id'] ?>" class="action-item">
                                            <span>✏️</span> Chỉnh sửa
                                        </a>
                                        <form method="POST" action="/admin/server-groups/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhóm máy chủ này?');" style="margin: 0;">
                                            <input type="hidden" name="id" value="<?= $group['id'] ?>">
                                            <button type="submit" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <span>🗑️</span> Xóa nhóm
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có nhóm máy chủ nào được tạo.</td>
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