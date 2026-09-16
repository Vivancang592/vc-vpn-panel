<?php
$pageTitle = "Chỉnh Sửa Nhóm Máy Chủ - Quản Trị Hệ Thống";
$activeMenu = "server-groups";

ob_start();
?>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Chỉnh Sửa Nhóm: <?= htmlspecialchars($group['name'] ?? '') ?></h1>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="glass-card" style="padding: 1.75rem; width: 100%;">
    <form method="POST" action="/admin/server-groups/edit?id=<?= $group['id'] ?>" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tên Nhóm Máy Chủ (*)</label>
            <input type="text" id="name" name="name" class="glass-input" value="<?= htmlspecialchars($group['name'] ?? '') ?>" required autofocus style="width: 100%;">
        </div>

        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mô Tả</label>
            <textarea id="description" name="description" rows="4" class="glass-input" style="width: 100%; resize: vertical;"><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
        </div>

        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái</label>
            <select id="status" name="status" class="glass-input" style="width: 100%; cursor: pointer;">
                <option value="active" <?= ($group['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active (Hoạt động)</option>
                <option value="inactive" <?= ($group['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Khóa)</option>
            </select>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">💾 Cập Nhật Thông Tin</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>