<?php
$pageTitle = "Quản Lý Nút Kết Nối - Quản Trị Hệ Thống";
$activeMenu = "nodes";

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

<form id="bulkDeleteForm" method="POST" action="/admin/nodes/bulk-delete">
    <div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Nút Kết Nối (Inbounds)</h1>
            <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Danh sách cổng kết nối và giao thức được đẩy tự động từ các máy chủ VPS</p>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
            <button type="button" onclick="confirmBulkDeleteNodes()" class="glass-btn" style="background: rgba(255, 59, 48, 0.12); color: var(--ios-danger); border-color: rgba(255, 59, 48, 0.2); white-space: nowrap;">
                🗑️ Xóa các mục đã chọn
            </button>
        </div>
    </div>

    <!-- Bảng Nút Kết Nối -->
    <div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
        <div class="table-responsive">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="selectAllNodes" onchange="toggleSelectAllNodes(this)" style="cursor: pointer;">
                        </th>
                        <th>ID</th>
                        <th>Máy Chủ</th>
                        <th style="text-align: center;">Cổng (Port)</th>
                        <th>Giao Thức</th>
                        <th>Mạng (Network)</th>
                        <th style="text-align: center;">TLS</th>
                        <th>SNI / Host</th>
                        <th style="text-align: center;">Trạng Thái</th>
                        <th style="text-align: right;">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($nodes)): ?>
                        <?php foreach ($nodes as $node): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="ids[]" value="<?= $node['id'] ?>" class="node-checkbox" style="cursor: pointer;">
                                </td>
                                <td style="font-weight: 700;">#<?= $node['id'] ?></td>
                                <td style="font-weight: 700; font-size: 0.9rem; color: var(--ios-text);">
                                    <?= htmlspecialchars($node['server_name'] ?? ('Server #' . $node['server_id'])) ?>
                                </td>
                                <td style="text-align: center;">
                                    <code style="background: rgba(0, 122, 255, 0.08); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700; color: var(--ios-blue);">
                                        <?= htmlspecialchars($node['port']) ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); text-transform: uppercase;">
                                        <?= htmlspecialchars($node['protocol']) ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; text-transform: uppercase; color: var(--ios-text-secondary); font-size: 0.85rem;">
                                    <?= htmlspecialchars($node['network']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if (!empty($node['tls'])): ?>
                                        <span style="color: var(--ios-success); font-weight: 700; font-size: 0.85rem;">✓ Bật</span>
                                    <?php else: ?>
                                        <span style="color: var(--ios-text-secondary); font-size: 0.85rem;">✕ Tắt</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--ios-text-secondary);">
                                    <code style="font-size: 0.8rem;"><?= htmlspecialchars($node['sni'] ?: ($node['host'] ?: '-')) ?></code>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                    $statusBadge = [
                                        'active'   => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                        'inactive' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                    ];
                                    ?>
                                    <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$node['status'] ?? 'active'] ?? '' ?>">
                                        <?= strtoupper($node['status'] ?? 'active') ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-dropdown">
                                        <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                        <div class="action-menu" style="min-width: 175px; white-space: nowrap;">
                                            <a href="/admin/nodes/detail?id=<?= $node['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                                <span>👁️</span> Xem chi tiết
                                            </a>
                                            <button type="submit" form="delete-node-form-<?= $node['id'] ?>" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                                <span>🗑️</span> Xóa nút kết nối
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có nút kết nối nào được đồng bộ từ VPS.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Các Form ẩn để xóa từng nút kết nối bằng POST -->
<?php if (!empty($nodes)): ?>
    <?php foreach ($nodes as $node): ?>
        <form id="delete-node-form-<?= $node['id'] ?>" method="POST" action="/admin/nodes/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa dữ liệu nút kết nối này khỏi hệ thống?');" style="display: none;">
            <input type="hidden" name="id" value="<?= $node['id'] ?>">
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>