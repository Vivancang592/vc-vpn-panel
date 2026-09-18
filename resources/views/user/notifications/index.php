<?php
$pageTitle = 'Thông báo - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$items = is_array($notifications ?? null) ? $notifications : [];
ob_start();
?>
<section class="user-record-page"><header class="user-record-header"><div><h1>Thông báo</h1><p>Cập nhật mới nhất về thanh toán và hỗ trợ.</p></div></header>
<?php if ($items): ?><div class="user-notification-list"><?php foreach ($items as $item): ?><article class="glass-card user-notification-item is-<?= htmlspecialchars($item['type'] ?? 'system') ?>"><div><h2><?= htmlspecialchars($item['title'] ?? 'Thông báo hệ thống') ?></h2><p><?= htmlspecialchars($item['message'] ?? '') ?></p></div><time><?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?></time></article><?php endforeach; ?></div><?php else: ?><div class="glass-card user-record-empty"><h2>Chưa có thông báo</h2><p>Các cập nhật giao dịch và hỗ trợ sẽ xuất hiện tại đây.</p></div><?php endif; ?></section>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>
