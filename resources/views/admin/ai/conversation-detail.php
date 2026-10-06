<?php
$pageTitle = 'Chi Tiết Hội Thoại AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-conversations';

ob_start();

require __DIR__ . '/_flash.php';

$session = $session ?? [];
$messages = $messages ?? [];
$events = $events ?? [];

$source = (string) ($session['source'] ?? 'web');
$sourceLabel = $sourceLabel ?? ($source === 'fanpage' ? 'Facebook' : 'Website');
$sourceColor = $source === 'fanpage' ? '#1877F2' : 'var(--ios-blue)';

$uid = (int) ($session['user_id'] ?? 0);
$loggedIn = $uid > 0;
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">
            Hội Thoại #<?= (int) ($session['id'] ?? 0) ?>
        </h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            Toàn bộ nội dung AI đã trả lời, kèm nguồn hội thoại và thông tin người dùng.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <?php if ((string) ($session['status'] ?? '') !== 'closed'): ?>
            <form method="POST" action="/admin/ai/conversations/close" style="display: inline-block; margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="id" value="<?= (int) ($session['id'] ?? 0) ?>">
                <input type="hidden" name="back" value="/admin/ai/conversations/detail?id=<?= (int) ($session['id'] ?? 0) ?>">
            </form>
        <?php endif; ?>
        <a href="/admin/ai/reply#cab-conversations" style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: var(--radius-sm); font-size: 0.82rem; font-weight: 600; border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); color: var(--ios-blue);">
            Danh sách
        </a>
    </div>
</div>

<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; margin-bottom: 1rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div>
            <div style="color: var(--ios-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em;">Nguồn hội thoại</div>
            <div style="font-weight: 700; color: <?= $sourceColor ?>; margin-top: 0.2rem;">
                <?= $source === 'fanpage' ? 'FB' : 'WEB' ?> <?= htmlspecialchars($sourceLabel) ?>
            </div>
            <div style="color: var(--ios-text-secondary); font-size: 0.75rem; margin-top: 0.2rem;">
                <?= $source === 'fanpage' ? 'Facebook (Messenger / bình luận)' : 'Khung chat trên website' ?>
            </div>
        </div>
        <div>
            <div style="color: var(--ios-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em;">Người dùng</div>
            <?php if ($loggedIn): ?>
                <div style="font-weight: 700; color: var(--ios-success); margin-top: 0.2rem;">USR <?= htmlspecialchars((string) ($userName !== null && $userName !== '' ? $userName : ('User #' . $uid))) ?></div>
                <div style="color: var(--ios-text-secondary); font-size: 0.75rem; margin-top: 0.2rem;">
                    Đã đăng nhập<?= ($userEmail ?? '') !== '' ? ' • ' . htmlspecialchars((string) $userEmail) : '' ?>
                </div>
            <?php else: ?>
                <div style="font-weight: 700; margin-top: 0.2rem; color: var(--ios-text-secondary);">Khách vãng lai</div>
                <div style="color: var(--ios-text-secondary); font-size: 0.75rem; margin-top: 0.2rem;">Chưa đăng nhập</div>
            <?php endif; ?>
        </div>
        <div>
            <div style="color: var(--ios-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em;">Trạng thái</div>
            <div style="font-weight: 700; margin-top: 0.2rem;"><?= htmlspecialchars($statusLabels[(string) ($session['status'] ?? '')] ?? (string) ($session['status'] ?? '')) ?></div>
        </div>
        <div>
            <div style="color: var(--ios-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em;">Mã định danh</div>
            <div style="font-size: 0.78rem; margin-top: 0.2rem; word-break: break-all;">
                <?php if (!empty($session['visitor_token'])): ?>
                    visitor: <code><?= htmlspecialchars(mb_substr((string) $session['visitor_token'], 0, 24)) ?></code>
                <?php endif; ?>
                <?php if (!empty($session['external_id'])): ?>
                    <br>fanpage: <code><?= htmlspecialchars((string) $session['external_id']) ?></code>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div style="color: var(--ios-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em;">Thời gian</div>
            <div style="font-size: 0.8rem; margin-top: 0.2rem;">
                Tạo: <?= htmlspecialchars((string) ($session['created_at'] ?? '')) ?><br>
                Cập nhật: <?= htmlspecialchars((string) ($session['updated_at'] ?? '')) ?>
            </div>
        </div>
    </div>
</div>

<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; margin-bottom: 1rem;">
    <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.85rem;">Nội dung hội thoại</h2>

    <?php if (!empty($messages)): ?>
        <div style="display: flex; flex-direction: column; gap: 0.6rem;">
            <?php foreach ($messages as $m): ?>
                <?php
                $role = (string) ($m['role'] ?? 'user');
                $isUser = $role === 'user';
                $isSystem = $role === 'system';
                $bg = $isUser ? 'rgba(10,132,255,0.08)' : ($isSystem ? 'rgba(120,120,128,0.10)' : 'rgba(52,199,89,0.08)');
                $border = $isUser ? 'rgba(10,132,255,0.35)' : ($isSystem ? 'rgba(120,120,128,0.30)' : 'rgba(52,199,89,0.35)');
                ?>
                <div style="background: <?= $bg ?>; border: 1px solid <?= $border ?>; border-radius: 10px; padding: 0.65rem 0.85rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
                        <span style="font-weight: 700; font-size: 0.8rem;">
                            <?= $isUser ? 'USR' : ($isSystem ? 'SYS' : 'AI') ?>
                            <?= htmlspecialchars($roleLabels[$role] ?? $role) ?>
                        </span>
                        <span style="color: var(--ios-text-secondary); font-size: 0.72rem;">
                            <?php if (!$isUser && !$isSystem): ?>
                                <?= htmlspecialchars((string) ($m['provider'] ?? '')) ?>
                                <?= ($m['model'] ?? '') !== '' ? ' • ' . htmlspecialchars((string) $m['model']) : '' ?>
                                &nbsp;
                            <?php endif; ?>
                            <?= htmlspecialchars((string) ($m['created_at'] ?? '')) ?>
                        </span>
                    </div>
                    <div style="font-size: 0.85rem; line-height: 1.55; white-space: pre-wrap; word-break: break-word;"><?= htmlspecialchars((string) ($m['content'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Hội thoại này chưa có tin nhắn nào.</p>
    <?php endif; ?>
</div>

<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.85rem;">Sự kiện AI</h2>

    <?php if (!empty($events)): ?>
        <div class="table-responsive">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sự kiện</th>
                        <th>Dữ liệu</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <tr>
                            <td style="font-weight: 600;">#<?= (int) ($ev['id'] ?? 0) ?></td>
                            <td style="font-size: 0.82rem;"><code><?= htmlspecialchars((string) ($ev['event_name'] ?? '')) ?></code></td>
                            <td style="font-size: 0.78rem; max-width: 420px; word-break: break-word;">
                                <?= htmlspecialchars(mb_substr((string) ($ev['event_data'] ?? ''), 0, 300)) ?>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars((string) ($ev['created_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Chưa ghi nhận sự kiện AI nào cho hội thoại này.</p>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
