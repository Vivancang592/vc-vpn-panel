<?php
$pageTitle = 'Chi tiết hỗ trợ - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$ticket = is_array($ticket ?? null) ? $ticket : [];
$items = is_array($messages ?? null) ? $messages : [];
ob_start();
?>
<section class="user-form-page"><header class="user-record-header"><div><h1><?= htmlspecialchars($ticket['subject'] ?? 'Yêu cầu hỗ trợ') ?></h1><p>Ticket #<?= (int) ($ticket['id'] ?? 0) ?> · <?= htmlspecialchars($ticket['status'] ?? 'open') ?></p></div><a href="/tickets" class="user-text-link">Quay lại</a></header>
<?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?><div class="user-record-alert <?= !empty($_SESSION['error']) ? 'is-error' : '' ?>"><?= htmlspecialchars($_SESSION['error'] ?? $_SESSION['success']) ?></div><?php unset($_SESSION['success'], $_SESSION['error']); endif; ?>
<div class="glass-card user-ticket-thread"><?php foreach ($items as $item): ?><?php $isStaff = in_array($item['role'] ?? '', ['admin', 'staff'], true); ?><article class="user-ticket-message <?= $isStaff ? 'is-staff' : '' ?>"><header><strong><?= htmlspecialchars($item['username'] ?? 'Người dùng') ?></strong><time><?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?></time></header><p><?= nl2br(htmlspecialchars($item['message'] ?? '')) ?></p></article><?php endforeach; ?></div>
<?php if (($ticket['status'] ?? '') !== 'closed'): ?><form class="glass-card user-record-form" method="post" action="/tickets/reply"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= (int) ($ticket['id'] ?? 0) ?>"><label for="reply-message">Phản hồi</label><textarea class="glass-input" id="reply-message" name="message" rows="5" required></textarea><button class="glass-btn" type="submit">Gửi phản hồi</button></form><?php endif; ?></section>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>
