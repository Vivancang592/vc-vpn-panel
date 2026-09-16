<?php
$pageTitle = "Nhật Ký MacroDroid - Quản Trị Hệ Thống";
$activeMenu = "logs";

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Nhật Ký MacroDroid Webhook</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Kiểm tra toàn bộ dữ liệu phản hồi từ thiết bị nạp tự động WeChat Pay</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="/admin/logs/macrodroid" class="glass-btn" style="text-decoration: none; white-space: nowrap;">🔄 Tải lại Log</a>
        <form method="POST" action="/admin/logs/macrodroid/clear" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ nội dung nhật ký này?');" style="margin: 0;">
            <button type="submit" class="glass-btn" style="white-space: nowrap; background: rgba(255, 59, 48, 0.15); color: var(--ios-danger); border: none; cursor: pointer; padding: 0.5rem 1rem; font-size: 0.85rem;">🗑️ Xóa Log</button>
        </form>
    </div>
</div>

<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <?php if (!empty(trim($logContent))): ?>
        <pre style="background: #1e1e1e; color: #00ff66; padding: 1.25rem; border-radius: var(--radius-md, 8px); font-family: 'Courier New', Courier, monospace; font-size: 0.85rem; line-height: 1.5; overflow-x: auto; max-height: 600px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;"><?= htmlspecialchars($logContent) ?></pre>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem 1rem; color: var(--ios-text-secondary);">
            <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem;">File nhật ký hiện tại đang trống.</p>
            <p style="font-size: 0.85rem;">Các yêu cầu Webhook mới từ MacroDroid sẽ tự động xuất hiện tại đây.</p>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>