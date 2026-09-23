<?php
$pageTitle = "Thông Báo Hệ Thống - Quản Trị";
$activeMenu = "dashboard";
$items = isset($notifications) && is_array($notifications) ? $notifications : [];
$unreadCount = (int)($unreadCount ?? 0);

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

<div style="margin-bottom: 1.25rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
            <span>🔔 Thông Báo Quản Trị</span>
            <?php if ($unreadCount > 0): ?>
                <span style="font-size: 0.75rem; background: var(--ios-danger, #ff3b30); color: #fff; padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 700;">
                    <?= $unreadCount ?> mới
                </span>
            <?php endif; ?>
        </h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem; margin-top: 0.25rem;">
            Tổng hợp các đơn hàng chờ duyệt, yêu cầu hỗ trợ mới và lệnh rút tiền cần xử lý.
        </p>
    </div>

    <?php if (!empty($items)): ?>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" action="/admin/notifications/read-all" style="margin: 0;">
                    <button type="submit" class="glass-btn" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: rgba(0, 122, 255, 0.15); border: 1px solid rgba(0, 122, 255, 0.3); color: var(--ios-blue, #007aff); border-radius: var(--radius-sm, 8px); cursor: pointer; font-weight: 600;">
                        ✓ Đánh dấu tất cả đã xem
                    </button>
                </form>
            <?php endif; ?>
            <?php if (($_SESSION['role'] ?? '') !== 'staff'): ?>
                <form method="POST" action="/admin/notifications/clear" onsubmit="return confirm('Bạn có chắc muốn xóa tất cả thông báo?');" style="margin: 0;">
                    <button type="submit" class="glass-btn" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.3); color: var(--ios-danger, #ff3b30); border-radius: var(--radius-sm, 8px); cursor: pointer; font-weight: 600;">
                        🗑️ Xóa tất cả
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($items)): ?>
    <div style="display: grid; gap: 0.85rem; width: 100%; box-sizing: border-box;">
        <?php foreach ($items as $item): ?>
            <?php
                $isRead = !empty($item['is_read']);
                $badgeClass = $item['badge_class'] ?? 'info';
                $badgeBg = match ($badgeClass) {
                    'success' => 'rgba(52, 199, 89, 0.15)',
                    'warning' => 'rgba(255, 149, 0, 0.15)',
                    'danger'  => 'rgba(255, 59, 48, 0.15)',
                    default   => 'rgba(0, 122, 255, 0.15)',
                };
                $badgeColor = match ($badgeClass) {
                    'success' => 'var(--ios-success, #34c759)',
                    'warning' => 'var(--ios-warning, #ff9500)',
                    'danger'  => 'var(--ios-danger, #ff3b30)',
                    default   => 'var(--ios-blue, #007aff)',
                };
                $borderLeftColor = match ($item['category'] ?? 'system') {
                    'order'      => '#ff9500',
                    'ticket'     => '#007aff',
                    'withdrawal' => '#ff2d55',
                    default      => '#5856d6',
                };
            ?>
            <article class="glass-card user-notification-item" style="position: relative; padding: 1rem 2.25rem 1rem 1.25rem; border-left: 4px solid <?= $borderLeftColor ?>; <?= !$isRead ? 'background: rgba(255, 255, 255, 0.35); box-shadow: 0 4px 15px rgba(0, 122, 255, 0.1);' : 'opacity: 0.85;' ?> border-radius: var(--radius-md, 12px);">
                <!-- Nút X xóa thông báo ở góc phải trên -->
                <?php if (($_SESSION['role'] ?? '') !== 'staff'): ?>
                    <a href="/admin/notifications/delete?id=<?= urlencode($item['id']) ?>" onclick="return confirm('Xóa thông báo này?');" title="Xóa thông báo" style="position: absolute; top: 0.6rem; right: 0.75rem; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; color: var(--ios-text-secondary); text-decoration: none; font-size: 1.2rem; line-height: 1; border-radius: 50%; background: transparent; transition: all 0.2s;" onmouseover="this.style.color='var(--ios-danger, #ff3b30)'; this.style.background='rgba(255,59,48,0.1)';" onmouseout="this.style.color='var(--ios-text-secondary)'; this.style.background='transparent';">
                        &times;
                    </a>
                <?php endif; ?>

                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.35rem; padding-right: 1.5rem;">
                        <?php if (!$isRead): ?>
                            <span style="font-size: 0.65rem; background: var(--ios-blue, #007aff); color: #fff; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 700; text-transform: uppercase;">
                                Mới
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($item['badge'])): ?>
                            <span style="font-size: 0.75rem; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; border: 1px solid <?= $badgeColor ?>33; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 600;">
                                <?= htmlspecialchars($item['badge']) ?>
                            </span>
                        <?php endif; ?>
                        <h2 style="font-size: 1rem; font-weight: 700; margin: 0; color: var(--ios-text);">
                            <?= htmlspecialchars($item['title'] ?? 'Thông báo hệ thống') ?>
                        </h2>
                    </div>
                    <p style="margin: 0.3rem 0 0.5rem; color: var(--ios-text-secondary); font-size: 0.875rem; line-height: 1.5;">
                        <?= htmlspecialchars($item['message'] ?? '') ?>
                    </p>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.85rem; margin-top: 0.5rem; flex-wrap: wrap;">
                        <time style="color: var(--ios-text-secondary); font-size: 0.78rem;">
                            🕒 <?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?>
                        </time>
                        <?php if (!empty($item['link'])): ?>
                            <a href="<?= htmlspecialchars($item['link']) ?>" style="font-size: 0.8rem; color: var(--ios-blue, #007aff); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.2rem; margin-left: auto;">
                                Đi tới xử lý &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="glass-card user-record-empty" style="text-align: center; padding: 3rem 1.5rem; border-radius: var(--radius-md, 12px); width: 100%; box-sizing: border-box;">
        <div style="font-size: 3rem; margin-bottom: 0.75rem;">🔕</div>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--ios-text);">Không có việc chờ xử lý</h2>
        <p style="color: var(--ios-text-secondary); font-size: 0.9rem; margin: 0;">
            Tất cả đơn hàng, ticket hỗ trợ và yêu cầu rút tiền đều đã được xử lý hoàn tất.
        </p>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
