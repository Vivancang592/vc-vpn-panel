<?php
$pageTitle = 'Hội Thoại AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-conversations';

ob_start();

require __DIR__ . '/_flash.php';

$sourceLabels = $sourceLabels ?? ['web' => 'Website', 'fanpage' => 'Facebook'];
$statusLabels = $statusLabels ?? ['open' => 'Đang mở', 'handoff' => 'Chờ nhân viên', 'closed' => 'Đã đóng'];

$sourceColor = static function (string $source): string {
    return $source === 'fanpage' ? '#1877F2' : 'var(--ios-blue)';
};

$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$queryBase = [];
if (($sourceFilter ?? '') !== '') {
    $queryBase['source'] = $sourceFilter;
}
if (($keyword ?? '') !== '') {
    $queryBase['q'] = $keyword;
}
$buildUrl = static function (array $extra) use ($queryBase): string {
    $params = array_merge($queryBase, $extra);
    $qs = http_build_query($params);
    return '/admin/ai/conversations' . ($qs !== '' ? '?' . $qs : '');
};
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Hội Thoại AI</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            Đọc lại bình luận, tin nhắn và đoạn chat mà AI đã trả lời. Phân biệt rõ nguồn
            <strong>Website</strong> và <strong>Facebook</strong>, cùng trạng thái khách đã đăng nhập hay chưa.
        </p>
    </div>
</div>

<form method="GET" action="/admin/ai/conversations" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem;">
    <input type="text" name="q" value="<?= htmlspecialchars((string) ($keyword ?? '')) ?>" placeholder="Tìm theo tên khách hoặc nội dung tin nhắn..." style="flex: 1 1 260px; min-width: 200px; padding: 0.45rem 0.7rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); background: transparent; color: inherit; font-size: 0.85rem;">
    <?php if (($sourceFilter ?? '') !== ''): ?>
        <input type="hidden" name="source" value="<?= htmlspecialchars((string) $sourceFilter) ?>">
    <?php endif; ?>
    <button type="submit" style="padding: 0.45rem 0.9rem; border-radius: var(--radius-sm); border: none; background: var(--ios-blue); color: #fff; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Tìm</button>
    <?php if (($keyword ?? '') !== ''): ?>
        <a href="<?= htmlspecialchars($buildUrl(['q' => null])) ?>" style="text-decoration: none; padding: 0.45rem 0.9rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); color: var(--ios-text-secondary); font-size: 0.85rem;">Xoá lọc</a>
    <?php endif; ?>
</form>

<div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1rem;">
    <a href="<?= htmlspecialchars($buildUrl(['source' => null, 'page' => null])) ?>"
       style="text-decoration: none; padding: 0.35rem 0.7rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; border: 1px solid <?= ($sourceFilter ?? '') === '' ? 'var(--ios-blue)' : 'var(--ios-border, rgba(255,255,255,0.15))' ?>; color: <?= ($sourceFilter ?? '') === '' ? 'var(--ios-blue)' : 'var(--ios-text-secondary)' ?>;">
        Tất cả nguồn
    </a>
    <a href="<?= htmlspecialchars($buildUrl(['source' => 'web', 'page' => null])) ?>"
       style="text-decoration: none; padding: 0.35rem 0.7rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; border: 1px solid <?= ($sourceFilter ?? '') === 'web' ? 'var(--ios-blue)' : 'var(--ios-border, rgba(255,255,255,0.15))' ?>; color: <?= ($sourceFilter ?? '') === 'web' ? 'var(--ios-blue)' : 'var(--ios-text-secondary)' ?>;">
        🌐 Website
    </a>
    <a href="<?= htmlspecialchars($buildUrl(['source' => 'fanpage', 'page' => null])) ?>"
       style="text-decoration: none; padding: 0.35rem 0.7rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; border: 1px solid <?= ($sourceFilter ?? '') === 'fanpage' ? '#1877F2' : 'var(--ios-border, rgba(255,255,255,0.15))' ?>; color: <?= ($sourceFilter ?? '') === 'fanpage' ? '#1877F2' : 'var(--ios-text-secondary)' ?>;">
        📘 Facebook
    </a>
</div>

<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <p style="color: var(--ios-text-secondary); font-size: 0.82rem; margin: 0 0 0.75rem;">
        Tổng số hội thoại: <strong><?= (int) $total ?></strong>
    </p>
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nguồn</th>
                    <th>Người Dùng</th>
                    <th>Số Tin</th>
                    <th>Tin Cuối</th>
                    <th>Trạng Thái</th>
                    <th>Cập Nhật</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sessions)): ?>
                    <?php foreach ($sessions as $s): ?>
                        <?php
                        $src = (string) ($s['source'] ?? 'web');
                        $uid = (int) ($s['user_id'] ?? 0);
                        $uname = trim((string) ($s['username'] ?? ''));
                        $uemail = trim((string) ($s['user_email'] ?? ''));
                        $lastMsg = trim((string) ($s['last_message'] ?? ''));
                        if (mb_strlen($lastMsg) > 90) {
                            $lastMsg = mb_substr($lastMsg, 0, 90) . '…';
                        }
                        $lastRole = (string) ($s['last_role'] ?? '');
                        ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= (int) $s['id'] ?></td>
                            <td>
                                <span style="font-weight: 600; font-size: 0.82rem; color: <?= $sourceColor($src) ?>;">
                                    <?= $src === 'fanpage' ? '📘' : '🌐' ?> <?= htmlspecialchars($sourceLabels[$src] ?? $src) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.82rem;">
                                <?php if ($uid > 0): ?>
                                    <span style="color: var(--ios-success); font-weight: 600;">👤 <?= htmlspecialchars($uname !== '' ? $uname : ('User #' . $uid)) ?></span>
                                    <div style="color: var(--ios-text-secondary); font-size: 0.72rem;">Đã đăng nhập<?= $uemail !== '' ? ' · ' . htmlspecialchars($uemail) : '' ?></div>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary);">Khách vãng lai</span>
                                    <div style="color: var(--ios-text-secondary); font-size: 0.72rem;">Chưa đăng nhập</div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.82rem;"><?= (int) ($s['message_count'] ?? 0) ?></td>
                            <td style="font-size: 0.8rem; max-width: 320px;">
                                <?php if ($lastMsg !== ''): ?>
                                    <span style="color: var(--ios-text-secondary); font-size: 0.72rem;"><?= $lastRole === 'assistant' ? '🤖 AI:' : ($lastRole === 'user' ? '👤 Khách:' : '') ?></span>
                                    <?= htmlspecialchars($lastMsg) ?>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem;"><?= htmlspecialchars($statusLabels[(string) ($s['status'] ?? '')] ?? (string) ($s['status'] ?? '')) ?></td>
                            <td style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars((string) ($s['updated_at'] ?? $s['created_at'] ?? '')) ?></td>
                            <td style="text-align: right;">
                                <a href="/admin/ai/conversations/detail?id=<?= (int) $s['id'] ?>"
                                   style="text-decoration: none; padding: 0.3rem 0.6rem; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 600; border: 1px solid var(--ios-blue); color: var(--ios-blue);">
                                    Xem
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">
                            Chưa có hội thoại nào. Hội thoại xuất hiện khi khách chat trên website hoặc qua Facebook Fanpage.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="display: flex; gap: 0.35rem; justify-content: center; margin-top: 1rem; flex-wrap: wrap;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= htmlspecialchars($buildUrl(['page' => $p])) ?>"
                   style="text-decoration: none; padding: 0.3rem 0.65rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; border: 1px solid <?= $p === (int) $page ? 'var(--ios-blue)' : 'var(--ios-border, rgba(255,255,255,0.15))' ?>; color: <?= $p === (int) $page ? 'var(--ios-blue)' : 'var(--ios-text-secondary)' ?>;">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
