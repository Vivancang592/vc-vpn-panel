<?php
$pageTitle = 'Chi tiết hỗ trợ - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$ticket = isset($ticket) && is_array($ticket) ? $ticket : [];
$items = isset($messages) && is_array($messages) ? $messages : [];
$status = $ticket['status'] ?? 'open';

$statusLabels = [
    'open'        => 'Đang chờ hỗ trợ',
    'in_progress' => 'Đang xử lý',
    'resolved'    => 'Đã giải quyết',
    'closed'      => 'Đã đóng'
];
$statusColors = [
    'open'        => ['bg' => 'rgba(255, 149, 0, 0.15)', 'color' => 'var(--ios-warning, #ff9500)', 'border' => 'rgba(255, 149, 0, 0.3)'],
    'in_progress' => ['bg' => 'rgba(0, 122, 255, 0.15)',   'color' => 'var(--ios-blue, #007aff)',   'border' => 'rgba(0, 122, 255, 0.3)'],
    'resolved'    => ['bg' => 'rgba(52, 199, 89, 0.15)',  'color' => 'var(--ios-success, #34c759)', 'border' => 'rgba(52, 199, 89, 0.3)'],
    'closed'      => ['bg' => 'rgba(142, 142, 147, 0.15)', 'color' => '#8e8e93',                   'border' => 'rgba(142, 142, 147, 0.3)']
];
$currentBadge = $statusColors[$status] ?? $statusColors['open'];
$currentLabel = $statusLabels[$status] ?? ucfirst($status);

ob_start();
?>
<section class="user-record-page" style="width: 100%;">
    <header class="user-orders-header">
        <div class="user-orders-intro"><h2 style="color: #020af4; font-family: emoji;">CHI TIẾT YÊU CẦU HỖ TRỢ</h2></div>
        <div class="user-orders-title-row">
            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
                <span style="font-size: 0.8rem; font-weight: 700; background: <?= $currentBadge['bg'] ?>; color: <?= $currentBadge['color'] ?>; border: 1px solid <?= $currentBadge['border'] ?>; padding: 0.2rem 0.65rem; border-radius: 9999px;">
                    <?= htmlspecialchars($currentLabel) ?>
                </span>
                <span style="font-size: 0.85rem; color: var(--ios-text-secondary); font-weight: 600;">
                    Mã Ticket: #<?= (int) ($ticket['id'] ?? 0) ?>
                </span>
            </div>
            <h1 style="font-size: 1.8rem; font-weight: 700; margin: .3rem 0;">
                <?= htmlspecialchars($ticket['subject'] ?? 'Yêu cầu hỗ trợ') ?>
            </h1>
            <p style="margin: 0.35rem 0 0; color: var(--ios-text-secondary); font-size: 0.85rem;">
                🕒 Tạo lúc: <?= !empty($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : '-' ?>
                <?php if (!empty($ticket['staff_name'])): ?>
                    · 👤 Phụ trách: <strong><?= htmlspecialchars($ticket['staff_name']) ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?>
        <div class="user-record-alert <?= !empty($_SESSION['error']) ? 'is-error' : '' ?>">
            <span><?= htmlspecialchars($_SESSION['error'] ?? $_SESSION['success']) ?></span>
            <button type="button" class="alert-close-btn" onclick="this.parentElement.remove();" aria-label="Đóng">&times;</button>
        </div>
        <?php unset($_SESSION['success'], $_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Khung Chat Hội Thoại -->
    <div class="glass-card" style="padding: 1.25rem; margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 1rem; border-radius: var(--radius-md, 12px); min-height: 200px;">
        <?php if (!empty($items)): ?>
            <?php foreach ($items as $item): ?>
                <?php 
                    $isAdmin = ($item['role'] ?? '') === 'admin';
                    $isMe = (int)($item['sender_id'] ?? 0) === (int)($_SESSION['user_id'] ?? 0);
                ?>
                <div style="display: flex; flex-direction: column; align-items: <?= ($isAdmin) ? 'flex-start' : 'flex-end' ?>; width: 100%;">
                    <!-- Tên & thời gian -->
                    <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.25rem; font-size: 0.78rem; color: var(--ios-text-secondary);">
                        <?php if ($isAdmin): ?>
                            <span style="font-weight: 700; color: var(--ios-blue, #007aff); display: inline-flex; align-items: center; gap: 0.2rem;">
                                🛡️ <?= htmlspecialchars($item['username'] ?? 'Kỹ thuật viên') ?>
                                <span style="font-size: 0.65rem; background: rgba(0, 122, 255, 0.15); border: 1px solid rgba(0, 122, 255, 0.3); padding: 0.1rem 0.4rem; border-radius: 4px;">Hỗ trợ</span>
                            </span>
                        <?php else: ?>
                            <span style="font-weight: 700; color: var(--ios-text);">
                                <?= $isMe ? 'Bạn' : htmlspecialchars($item['username'] ?? 'Người dùng') ?>
                            </span>
                        <?php endif; ?>
                        <span>•</span>
                        <time><?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?></time>
                    </div>

                    <!-- Nội dung tin nhắn bong bóng -->
                    <div style="max-width: 82%; padding: 0.85rem 1.1rem; border-radius: 14px; font-size: 0.9rem; line-height: 1.55; word-break: break-word; <?= $isAdmin ? 'background: rgba(0, 122, 255, 0.1); border: 1px solid rgba(0, 122, 255, 0.25); color: var(--ios-text); border-top-left-radius: 3px;' : 'background: rgba(255, 255, 255, 0.35); border: 1px solid var(--glass-border); color: var(--ios-text); border-top-right-radius: 3px;' ?>">
                        <?= nl2br(htmlspecialchars($item['message'] ?? '')) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; color: var(--ios-text-secondary); padding: 2rem 1rem;">
                Chưa có nội dung trao đổi nào trong ticket này.
            </div>
        <?php endif; ?>
    </div>

    <!-- Form gửi phản hồi -->
    <?php if ($status !== 'closed'): ?>
        <form class="glass-card" method="post" action="/tickets/reply" style="padding: 1.25rem; border-radius: var(--radius-md, 12px); display: flex; flex-direction: column; gap: 0.75rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="ticket_id" value="<?= (int) ($ticket['id'] ?? 0) ?>">
            
            <label for="reply-message" style="font-weight: 700; font-size: 0.9rem; color: var(--ios-text); display: flex; align-items: center; gap: 0.4rem;">
                💬 Gửi phản hồi mới
            </label>
            
            <textarea class="glass-input" id="reply-message" name="message" rows="4" placeholder="Nhập câu hỏi hoặc phản hồi chi tiết của bạn tại đây..." required style="width: 100%; border-radius: 8px; padding: 0.75rem; font-size: 0.9rem; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 0.25rem;">
                <button class="glass-btn" type="submit" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.4rem; font-size: 0.9rem; font-weight: 700; background: var(--ios-blue, #007aff); color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                    <span>✈️</span> Gửi phản hồi
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="glass-card" style="text-align: center; padding: 1.25rem; color: var(--ios-text-secondary); font-size: 0.9rem; border-radius: var(--radius-md, 12px);">
            🔒 Ticket này đã được đóng. Nếu cần hỗ trợ thêm, bạn vui lòng tạo ticket mới.
        </div>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>

