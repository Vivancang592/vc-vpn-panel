<?php
$pageTitle = "Chi Tiết Hỗ Trợ - Quản Trị Hệ Thống";
$activeMenu = "tickets";

ob_start();
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Ticket #<?= $ticket['id'] ?>: <?= htmlspecialchars($ticket['subject']) ?></h1>
    </div>
    <a href="/admin/tickets" class="glass-btn" style="text-decoration: none; white-space: nowrap; flex-shrink: 0;">⬅️ Quay Lại</a>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-success); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
    <!-- Thông Tin Tổng Quan -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Ticket</h2>
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>Khách Hàng:</strong> <?= htmlspecialchars($ticket['user_name'] ?? 'N/A') ?> (<?= htmlspecialchars($ticket['user_email'] ?? '') ?>)</div>
            <div><strong>Nhân Viên Phụ Trách:</strong> <?= htmlspecialchars($ticket['staff_name'] ?? 'Chưa gán') ?></div>
            <div><strong>Ngày Tạo:</strong> <?= date('d/m/Y H:i:s', strtotime($ticket['created_at'])) ?></div>
            <div>
                <strong>Trạng Thái Khởi Tạo:</strong> 
                <?php
                $statusBadge = [
                    'open'        => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                    'in_progress' => 'background: rgba(0, 122, 255, 0.15); color: var(--ios-blue);',
                    'resolved'    => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                    'closed'      => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);'
                ];
                $statusText = [
                    'open'        => 'MỚI TẠO',
                    'in_progress' => 'ĐANG XỬ LÝ',
                    'resolved'    => 'ĐÃ GIẢI QUYẾT',
                    'closed'      => 'ĐÃ ĐÓNG'
                ];
                ?>
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$ticket['status'] ?? 'open'] ?? '' ?>">
                    <?= $statusText[$ticket['status'] ?? 'open'] ?? strtoupper($ticket['status']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Nội dung trao đổi & Phản hồi -->
<div class="glass-card" style="padding: 1.5rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Lịch Sử Trao Đổi</h2>

    <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <?php 
                $isAdmin = in_array($msg['role'] ?? '', ['admin', 'staff'], true);
                ?>
                <div style="padding: 1rem; border-radius: var(--radius-md); background: <?= $isAdmin ? 'rgba(0, 122, 255, 0.08)' : 'rgba(255, 255, 255, 0.05)' ?>; border-left: 4px solid <?= $isAdmin ? 'var(--ios-blue)' : 'var(--glass-border)' ?>;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.85rem;">
                        <div>
                            <strong style="color: <?= $isAdmin ? 'var(--ios-blue)' : 'var(--ios-text)' ?>;"><?= htmlspecialchars($msg['username'] ?? 'Người dùng') ?></strong>
                            <?php if ($isAdmin): ?>
                                <span style="background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); padding: 0.1rem 0.4rem; border-radius: var(--radius-sm); font-size: 0.7rem; font-weight: 700; margin-left: 0.4rem;">QUẢN TRỊ VIÊN</span>
                            <?php endif; ?>
                        </div>
                        <span style="color: var(--ios-text-secondary); font-size: 0.78rem;"><?= date('d/m/Y H:i:s', strtotime($msg['created_at'])) ?></span>
                    </div>
                    <div style="font-size: 0.92rem; line-height: 1.5; color: var(--ios-text); white-space: pre-wrap;"><?= htmlspecialchars($msg['message']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: var(--ios-text-secondary); text-align: center; padding: 1rem;">Chưa có nội dung trao đổi nào.</p>
        <?php endif; ?>
    </div>

    <!-- Form Phản Hồi -->
    <form method="POST" action="/admin/tickets/detail?id=<?= $ticket['id'] ?>" style="display: flex; flex-direction: column; gap: 1rem; border-top: 1px solid var(--glass-border); padding-top: 1.25rem;">
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gửi Phản Hồi Mới</label>
            <textarea name="message" rows="4" class="glass-input" placeholder="Nhập câu trả lời hỗ trợ khách hàng..." style="width: 100%; resize: vertical; line-height: 1.5;"></textarea>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-weight: 600; font-size: 0.85rem;">Cập nhật trạng thái:</label>
                <select name="status" class="glass-input" style="cursor: pointer; padding: 0.4rem 0.8rem;">
                    <option value="open" <?= ($ticket['status'] ?? '') === 'open' ? 'selected' : '' ?>>Mới tạo (Open)</option>
                    <option value="in_progress" <?= ($ticket['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>Đang xử lý (In Progress)</option>
                    <option value="resolved" <?= ($ticket['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Đã giải quyết (Resolved)</option>
                    <option value="closed" <?= ($ticket['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Đóng ticket (Closed)</option>
                </select>
            </div>

            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem; background: var(--ios-blue); color: #fff; border: none; font-weight: 600;">
                💬 Gửi Phản Hồi
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>