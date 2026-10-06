<?php
$pageTitle = 'Bài Viết #' . (int) ($article['id'] ?? 0) . ' - Quản Trị Hệ Thống';
$activeMenu = 'ai-outputs';

ob_start();

require __DIR__ . '/_flash.php';

$postStatusInfo = $postStatus[$article['post_status'] ?? 'unscheduled'] ?? ['Bài viết', 'var(--ios-text-secondary)'];
$hasPrompt = trim((string) ($article['image_prompt'] ?? '')) !== '';
$moduleLabel = $aiLabels['modules'][(string) ($module['module_key'] ?? '')] ?? (string) ($module['module_name'] ?? $module['module_key'] ?? ('#' . (int) ($article['module_id'] ?? 0)));

$scheduleNote = '';
if (($article['post_status'] ?? '') === 'scheduled' && !empty($article['scheduled_at'])) {
    $scheduleNote = ' · Hẹn đăng ' . $article['scheduled_at'];
} elseif (($article['post_status'] ?? '') === 'published' && !empty($article['published_at'])) {
    $scheduleNote = ' · Đã đăng ' . $article['published_at'];
} elseif (($article['post_status'] ?? '') === 'failed' && !empty($article['error_message'])) {
    $scheduleNote = ' · ' . $article['error_message'];
}
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Bài Viết #<?= (int) ($article['id'] ?? 0) ?></h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            Tác vụ <a href="/admin/ai/tasks/detail?id=<?= (int) ($article['task_id'] ?? 0) ?>" style="color: var(--ios-blue); text-decoration: none;">#<?= (int) ($article['task_id'] ?? 0) ?></a>
            · <?= htmlspecialchars($moduleLabel) ?>
            · <span style="color: <?= $postStatusInfo[1] ?>; font-weight: 700;">● <?= htmlspecialchars($postStatusInfo[0]) ?></span><?= htmlspecialchars($scheduleNote) ?>
        </p>
    </div>
    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
        <a href="/admin/ai/fanpage" class="glass-btn" style="text-decoration: none; white-space: nowrap;">← Danh Sách</a>
    </div>
</div>

<!-- Xếp DỌC: nội dung bài viết ở trên, prompt tạo ảnh ở dưới -->
<div style="display: grid; grid-template-columns: 1fr; gap: 1rem; width: 100%; box-sizing: border-box;">

    <!-- Nội dung bài viết -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.4rem; word-break: break-word;"><?= htmlspecialchars((string) ($article['title'] ?? '')) ?></h2>
        <div style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-bottom: 0.85rem;">
            Tạo <?= htmlspecialchars((string) ($article['created_at'] ?? '')) ?> · Cập nhật <?= htmlspecialchars((string) ($article['updated_at'] ?? '')) ?> · <?= (int) ($article['version_count'] ?? 1) ?> phiên bản
        </div>

        <?php if (trim((string) ($article['body'] ?? '')) !== ''): ?>
            <pre style="background: rgba(0,0,0,0.2); padding: 0.75rem; border-radius: var(--radius-sm); overflow-x: auto; white-space: pre-wrap; word-break: break-word; font-size: 0.85rem; line-height: 1.55; margin: 0; max-height: 34rem;"><?= htmlspecialchars((string) $article['body']) ?></pre>
        <?php else: ?>
            <p style="font-size: 0.85rem; color: var(--ios-text-secondary);">Bài viết này không có nội dung văn bản.</p>
        <?php endif; ?>
    </div>

    <!-- Prompt tạo ảnh + trạng thái đăng -->
    <div class="glass-card" style="padding: 1.25rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem;">
            <h2 style="font-size: 1rem; font-weight: 700; margin: 0; word-break: break-word;">Prompt Tạo Ảnh</h2>
            <?php if ($hasPrompt): ?>
                <button type="button" class="js-copy-prompt" title="Copy prompt tạo ảnh" aria-label="Copy prompt tạo ảnh"
                        style="background: none; border: none; box-shadow: none; padding: 0.15rem 0.25rem; margin: 0; cursor: pointer; font-size: 1.1rem; line-height: 1; color: var(--ios-text-secondary); flex-shrink: 0;"
                        data-copy-value="<?= htmlspecialchars((string) $article['image_prompt'], ENT_QUOTES) ?>"
                        data-copy-label="📋">⧉</button>
            <?php endif; ?>
        </div>

        <?php if ($hasPrompt): ?>
            <pre style="background: rgba(0,0,0,0.2); padding: 0.75rem; border-radius: var(--radius-sm); overflow-x: auto; white-space: pre-wrap; word-break: break-word; font-size: 0.82rem; margin: 0; max-height: 18rem;"><?= htmlspecialchars((string) $article['image_prompt']) ?></pre>
        <?php else: ?>
            <p style="font-size: 0.85rem; color: var(--ios-text-secondary);">Bài viết này không có prompt tạo ảnh.</p>
        <?php endif; ?>

        <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--ios-border, rgba(255,255,255,0.08)); font-size: 0.82rem;">
            <div style="margin-bottom: 0.35rem;">
                <strong>Trạng thái đăng:</strong>
                <span style="color: <?= $postStatusInfo[1] ?>; font-weight: 700;">● <?= htmlspecialchars($postStatusInfo[0]) ?></span>
            </div>
            <?php if ((int) ($article['schedule_id'] ?? 0) > 0): ?>
                <?php if (!empty($article['scheduled_at'])): ?>
                    <div style="color: var(--ios-text-secondary);">Hẹn đăng: <?= htmlspecialchars((string) $article['scheduled_at']) ?></div>
                <?php endif; ?>
                <?php if (!empty($article['published_at'])): ?>
                    <div style="color: var(--ios-text-secondary);">Đã đăng: <?= htmlspecialchars((string) $article['published_at']) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div style="color: var(--ios-text-secondary);">Chưa lên lịch — dùng menu thao tác ở danh sách bài viết để hẹn giờ đăng.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
