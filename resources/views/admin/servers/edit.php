<?php
$pageTitle = "Chỉnh Sửa Máy Chủ - Quản Trị Hệ Thống";
$activeMenu = "servers";

ob_start();
?>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Chỉnh Sửa: <?= htmlspecialchars($server['name'] ?? '') ?></h1>
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
    <form method="POST" action="/admin/servers/edit?id=<?= $server['id'] ?>" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tên Máy Chủ (*)</label>
                <input type="text" id="name" name="name" class="glass-input" value="<?= htmlspecialchars($server['name'] ?? '') ?>" required autofocus style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Nhóm Máy Chủ (*)</label>
                <select id="group_id" name="group_id" class="glass-input" required style="width: 100%; cursor: pointer;">
                    <option value="">-- Chọn Nhóm --</option>
                    <?php if (!empty($groups)): ?>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= $group['id'] ?>" <?= ($server['group_id'] ?? 0) == $group['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mã Quốc Gia (Country Code) (*)</label>
                <input type="text" id="country_code" name="country_code" class="glass-input" value="<?= htmlspecialchars($server['country_code'] ?? '') ?>" maxlength="10" required style="width: 100%; text-transform: uppercase;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Vị Trí (Location) (*)</label>
                <input type="text" id="location" name="location" class="glass-input" value="<?= htmlspecialchars($server['location'] ?? '') ?>" required style="width: 100%;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Địa Chỉ IP (*)</label>
                <input type="text" id="ip_address" name="ip_address" class="glass-input" value="<?= htmlspecialchars($server['ip_address'] ?? '') ?>" required style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Cổng API (API Port)</label>
                <input type="number" id="api_port" name="api_port" class="glass-input" value="<?= htmlspecialchars($server['api_port'] ?? '80') ?>" required style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái</label>
                <select id="status" name="status" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="active" <?= ($server['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active (Hoạt động)</option>
                    <option value="maintenance" <?= ($server['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance (Bảo trì)</option>
                    <option value="offline" <?= ($server['status'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline (Ngoại tuyến)</option>
                </select>
            </div>
        </div>

        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">API Token Kết Nối</label>
            <div style="position: relative; display: flex; align-items: center;">
                <input type="text" id="api_token" name="api_token" class="glass-input" value="<?= htmlspecialchars($server['api_token'] ?? '') ?>" style="width: 100%; font-family: monospace; padding-right: 2.75rem;">
                <button type="button" onclick="generateApiToken()" title="Tạo token ngẫu nhiên" style="position: absolute; right: 0.5rem; width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.1); color: var(--ios-text); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">💾 Cập Nhật Thông Tin</button>
        </div>
    </form>
</div>

<script>
function generateApiToken() {
    const array = new Uint8Array(32);
    window.crypto.getRandomValues(array);
    const token = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    document.getElementById('api_token').value = token;
}
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>