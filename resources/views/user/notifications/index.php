<?php
$pageTitle = 'Thông báo - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$items = isset($notifications) && is_array($notifications) ? $notifications : [];
$unreadCount = (int)($unreadCount ?? 0);
ob_start();
?>
<section class="user-record-page">
    <header class="user-record-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
        <div>
            <h1 style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem; font-weight: 700; margin: 0;">
                <span>🔔 Trung tâm Thông báo</span>
                <?php if ($unreadCount > 0): ?>
                    <span style="font-size: 0.75rem; background: var(--ios-danger, #ff3b30); color: #fff; padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 700;">
                        <?= $unreadCount ?> mới
                    </span>
                <?php endif; ?>
            </h1>
            <p style="margin: 0.25rem 0 0; color: var(--ios-text-secondary); font-size: 0.9rem;">
                Cập nhật đơn hàng, gói cước, hỗ trợ kỹ thuật và biến động dịch vụ.
            </p>
        </div>

        <?php if (!empty($items)): ?>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" action="/notifications/read-all" style="margin: 0;">
                        <button type="submit" class="glass-btn" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: rgba(0, 122, 255, 0.15); border: 1px solid rgba(0, 122, 255, 0.3); color: var(--ios-blue, #007aff); border-radius: var(--radius-sm, 8px); cursor: pointer; font-weight: 600;">
                            ✓ Đánh dấu tất cả đã xem
                        </button>
                    </form>
                <?php endif; ?>
                <form method="POST" action="/notifications/clear" onsubmit="return confirm('Bạn có chắc muốn xóa tất cả thông báo?');" style="margin: 0;">
                    <button type="submit" class="glass-btn" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.3); color: var(--ios-danger, #ff3b30); border-radius: var(--radius-sm, 8px); cursor: pointer; font-weight: 600;">
                        🗑️ Xóa tất cả
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?>
        <div class="user-record-alert <?= !empty($_SESSION['error']) ? 'is-error' : '' ?>">
            <span><?= htmlspecialchars($_SESSION['error'] ?? $_SESSION['success']) ?></span>
            <button type="button" class="alert-close-btn" onclick="this.parentElement.remove();" aria-label="Đóng">&times;</button>
        </div>
        <?php unset($_SESSION['success'], $_SESSION['error']); ?>
    <?php endif; ?>

    <?php if ($items): ?>
        <div class="user-notification-list" style="display: grid; gap: 0.85rem;">
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
                    $borderLeftColor = match ($item['type'] ?? ($item['badge_class'] ?? 'info')) {
                        'success' => 'var(--ios-success, #34c759)',
                        'warning', 'pending' => 'var(--ios-warning, #ff9500)',
                        'danger', 'failed', 'cancelled' => 'var(--ios-danger, #ff3b30)',
                        default   => 'var(--ios-blue, #007aff)',
                    };
                ?>
                <article class="glass-card user-notification-item" style="position: relative; padding: 1rem 2.25rem 1rem 1.25rem; border-left: 4px solid <?= $borderLeftColor ?>; <?= !$isRead ? 'background: rgba(255, 255, 255, 0.35); box-shadow: 0 4px 15px rgba(0, 122, 255, 0.1);' : 'opacity: 0.85;' ?> border-radius: var(--radius-md, 12px);">
                    <!-- Nút X xóa thông báo ở góc phải trên -->
                    <a href="/notifications/delete?id=<?= urlencode($item['id']) ?>" onclick="return confirm('Xóa thông báo này?');" title="Xóa thông báo" style="position: absolute; top: 0.6rem; right: 0.75rem; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; color: var(--ios-text-secondary); text-decoration: none; font-size: 1.2rem; line-height: 1; border-radius: 50%; background: transparent; transition: all 0.2s;" onmouseover="this.style.color='var(--ios-danger, #ff3b30)'; this.style.background='rgba(255,59,48,0.1)';" onmouseout="this.style.color='var(--ios-text-secondary)'; this.style.background='transparent';">
                        &times;
                    </a>

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
                                    Xem chi tiết &rarr;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card user-record-empty" style="text-align: center; padding: 3rem 1.5rem; border-radius: var(--radius-md, 12px);">
            <div style="font-size: 3rem; margin-bottom: 0.75rem;">🔕</div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--ios-text);">Chưa có thông báo nào</h2>
            <p style="color: var(--ios-text-secondary); font-size: 0.9rem; margin: 0;">
                Tất cả cập nhật về đơn hàng, gói cước và phản hồi hỗ trợ sẽ hiển thị tại đây.
            </p>
        </div>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>

