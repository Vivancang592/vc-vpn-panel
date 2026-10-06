<?php
$pageTitle = 'Tác Vụ #' . (int) ($task['id'] ?? 0) . ' - Quản Trị Hệ Thống';
$activeMenu = 'ai-tasks';

ob_start();

require __DIR__ . '/_flash.php';

$status = (string) ($task['status'] ?? '');
$statusColor = match ($status) {
    'completed'  => 'var(--ios-success)',
    'failed'     => 'var(--ios-danger)',
    'processing' => 'var(--ios-warning, #ff9f0a)',
    'retrying'   => 'var(--ios-warning, #ff9f0a)',
    'queued'     => 'var(--ios-blue)',
    default      => 'var(--ios-text-secondary)',
};

// 2 TRẠNG THÁI TÁCH BIỆT (luồng hiện tại):
//  - task       = hàng đợi VIẾT bài (vc_ai_tasks);
//  - article    = hàng đợi ĐĂNG bài (vc_scheduled_posts → post_status).
$isWriteTask = (string) ($task['task_type'] ?? '') === 'content_article';
$postStatusInfo = is_array($article)
    ? ($postStatus[$article['post_status'] ?? 'unscheduled'] ?? ['Bài viết', 'var(--ios-text-secondary)'])
    : null;
$scheduleNote = '';
if (is_array($article)) {
    if (($article['post_status'] ?? '') === 'scheduled' && !empty($article['scheduled_at'])) {
        $scheduleNote = ' · Hẹn đăng ' . $article['scheduled_at'];
    } elseif (($article['post_status'] ?? '') === 'published' && !empty($article['published_at'])) {
        $scheduleNote = ' · Đã đăng ' . $article['published_at'];
    } elseif (($article['post_status'] ?? '') === 'failed' && !empty($article['error_message'])) {
        $scheduleNote = ' · ' . $article['error_message'];
    }
}

$json = static function ($value): string {
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }
    if (!is_array($value)) {
        return '';
    }
    return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

// Nhãn tiếng Việt cho activity_type (map dùng chung với dashboard).
$activityLabel = static fn(string $type): string =>
    (string) ($aiLabels['map']['activity_type'][$type] ?? $type);

// Meta JSON của activity → chuỗi hiển thị; ẩn lock_token (không lộ token chống ghi đè).
$activityMeta = static function ($meta): string {
    if (is_string($meta) && $meta !== '') {
        $decoded = json_decode($meta, true);
        $meta = is_array($decoded) ? $decoded : null;
    }
    if (!is_array($meta) || $meta === []) {
        return '';
    }
    unset($meta['lock_token']);

    return $meta === []
        ? ''
        : (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Tác Vụ #<?= (int) ($task['id'] ?? 0) ?></h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            Tác vụ: <strong><?= htmlspecialchars($aiLabels['modules'][(string) ($module['module_key'] ?? '')] ?? (string) ($module['module_key'] ?? ('#' . (int) ($task['module_id'] ?? 0)))) ?></strong>
            · Loại: <strong><?= htmlspecialchars($aiLabels['map']['task_type'][(string) ($task['task_type'] ?? '')] ?? (string) ($task['task_type'] ?? '')) ?></strong>
        </p>
        <?php if ($isWriteTask): ?>
            <p style="font-size: 0.85rem; color: var(--ios-text-secondary); margin-top: 0.3rem;">
                ✎ Viết bài:
                <strong style="color: <?= $statusColor ?>;">● <?= htmlspecialchars($aiLabels['map']['task_status'][$status] ?? $status) ?></strong>
                <?php if (is_array($article)): ?>
                    · Đăng bài:
                    <strong style="color: <?= $postStatusInfo[1] ?>;">● <?= htmlspecialchars($postStatusInfo[0]) ?></strong><?= htmlspecialchars($scheduleNote) ?>
                <?php endif; ?>
            </p>
            <p style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.3rem;">
                Task theo dõi việc <strong>viết bài</strong> - việc <strong>đăng bài</strong> theo lịch quản lý ở <a href="/admin/ai/fanpage" style="color: var(--ios-blue); text-decoration: none;">Fanpage › DS</a>.
            </p>
        <?php else: ?>
            <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
                <span style="color: <?= $statusColor ?>; font-weight: 700;">● <?= htmlspecialchars($aiLabels['map']['task_status'][$status] ?? $status) ?></span>
            </p>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if (in_array($status, ['pending', 'queued', 'retrying'], true)): ?>
            <form method="POST" action="/admin/ai/tasks/run" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="glass-btn" style="white-space: nowrap;">Chạy</button>
            </form>
            <form method="POST" action="/admin/ai/tasks/cancel" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn huỷ tác vụ này?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="glass-btn" style="white-space: nowrap;">Hủy</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($status === 'failed' && !empty($task['error_message'])): ?>
<div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-danger);">
    <strong style="font-size: 0.88rem;">Lỗi: <?= htmlspecialchars((string) ($task['error_code'] ?? '')) ?></strong>
    <p style="font-size: 0.83rem; color: var(--ios-text-secondary); margin-top: 0.35rem; word-break: break-word;"><?= htmlspecialchars((string) $task['error_message']) ?></p>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; width: 100%; box-sizing: border-box;">

    <!-- Thông tin -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Thông Tin Tác Vụ</h2>
        <div style="display: flex; flex-direction: column; gap: 0.45rem; font-size: 0.84rem;">
            <?php
            $info = [
                ($isWriteTask ? 'Trạng thái viết' : 'Trạng thái') => $aiLabels['map']['task_status'][$status] ?? $status,
                'Ưu tiên'         => (string) (int) ($task['priority'] ?? 0),
                'Số lần đã thử'   => (string) (int) ($task['attempt_count'] ?? 0),
                'Số lần thử lại'  => (int) ($task['retry_count'] ?? 0) . ' / ' . (int) ($task['max_retries'] ?? 0),
                'Thử lại sau'     => (string) ($task['retry_after'] ?? '—'),
                'Máy đang giữ'    => (string) ($task['locked_by'] ?? '—'),
                'Giữ từ lúc'      => (string) ($task['locked_at'] ?? '—'),
                'Bắt đầu'         => (string) ($task['started_at'] ?? '—'),
                'Kết thúc'        => (string) ($task['finished_at'] ?? '—'),
                'Khoá chống trùng' => (string) ($task['idempotency_key'] ?? '—'),
                'Người tạo'       => (string) (int) ($task['created_by'] ?? 0),
                'Tạo lúc'         => (string) ($task['created_at'] ?? '—'),
            ];
            if (is_array($article)) {
                $info['Trạng thái đăng'] = trim($postStatusInfo[0] . $scheduleNote);
            }
            ?>
            <?php foreach ($info as $label => $value): ?>
                <div style="display: flex; justify-content: space-between; gap: 0.75rem; border-bottom: 1px solid var(--ios-border, rgba(255,255,255,0.06)); padding-bottom: 0.3rem;">
                    <span style="color: var(--ios-text-secondary); white-space: nowrap;"><?= htmlspecialchars($label) ?></span>
                    <span style="font-weight: 600; text-align: right; word-break: break-all; font-size: 0.8rem;"><?= htmlspecialchars($value) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Dữ liệu đầu vào -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Dữ Liệu &amp; Tham Số</h2>
        <div style="font-size: 0.78rem;">
            <div style="color: var(--ios-text-secondary); margin-bottom: 0.25rem;">Dữ liệu yêu cầu</div>
            <pre style="background: rgba(0,0,0,0.2); padding: 0.65rem; border-radius: var(--radius-sm); overflow-x: auto; margin: 0 0 0.75rem 0;"><?= htmlspecialchars($json($task['payload'] ?? null)) ?></pre>
            <div style="color: var(--ios-text-secondary); margin-bottom: 0.25rem;">Tham số bổ sung</div>
            <pre style="background: rgba(0,0,0,0.2); padding: 0.65rem; border-radius: var(--radius-sm); overflow-x: auto; margin: 0;"><?= htmlspecialchars($json($task['params'] ?? null)) ?></pre>
        </div>
    </div>
</div>

<!-- Activity log -->
<div class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Nhật Ký Xử Lý (<?= count($activities) ?>)</h2>
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>Lần Thử</th>
                    <th>Hoạt Động</th>
                    <th>Trạng Thái</th>
                    <th>Thông Điệp</th>
                    <th>Mã Lỗi</th>
                    <th>Mã HTTP</th>
                    <th>Thời Gian (ms)</th>
                    <th>Lúc</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($activities)): ?>
                    <?php foreach ($activities as $activity): ?>
                        <?php
                        $aType    = (string) ($activity['activity_type'] ?? '');
                        $aStatus  = (string) ($activity['status'] ?? '');
                        $aError   = (string) ($activity['error_code'] ?? '');
                        $aRetryMs = $activity['retry_in_ms'] ?? null;
                        $aMeta    = $activityMeta($activity['meta'] ?? null);
                        $aHttp    = $activity['http_status'] ?? null;
                        $aDur     = $activity['duration_ms'] ?? null;
                        ?>
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);"><?= (int) ($activity['attempt_no'] ?? 0) ?></td>
                            <td style="font-size: 0.82rem;">
                                <span style="font-weight: 600;"><?= htmlspecialchars($activityLabel($aType)) ?></span>
                                <br><small style="color: var(--ios-text-secondary); font-weight: 400;"><?= htmlspecialchars($aType) ?></small>
                            </td>
                            <td style="font-size: 0.8rem;"><?= htmlspecialchars($aiLabels['map']['task_status'][$aStatus] ?? ($aStatus !== '' ? $aStatus : '—')) ?></td>
                            <td style="font-size: 0.8rem; max-width: 24rem; word-break: break-word;"><?= htmlspecialchars((string) ($activity['message'] ?? '')) ?></td>
                            <td style="font-size: 0.78rem; max-width: 18rem; word-break: break-word;">
                                <?php if ($aError !== ''): ?>
                                    <strong style="color: var(--ios-danger);"><?= htmlspecialchars($aError) ?></strong>
                                <?php else: ?>—<?php endif; ?>
                                <?php if ($aRetryMs !== null && (int) $aRetryMs > 0): ?>
                                    <br><small style="color: var(--ios-text-secondary);">thử lại sau <?= (int) $aRetryMs >= 1000 ? round((int) $aRetryMs / 1000, 1) . 's' : (int) $aRetryMs . 'ms' ?></small>
                                <?php endif; ?>
                                <?php if ($aMeta !== ''): ?>
                                    <br><code style="display: inline-block; margin-top: 0.25rem; font-size: 0.7rem; color: var(--ios-text-secondary); white-space: pre-wrap;"><?= htmlspecialchars($aMeta) ?></code>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem;"><?= $aHttp !== null ? (int) $aHttp : '—' ?></td>
                            <td style="font-size: 0.8rem;"><?= $aDur !== null ? (int) $aDur : '—' ?></td>
                            <td style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars((string) ($activity['created_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">
                            Chưa có hoạt động nào — tác vụ chưa được tiến trình nền xử lý.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (!in_array($status, ['completed', 'failed', 'cancelled'], true)): ?>
<script>
    // Task chưa kết thúc → tự làm mới sau 5 giây để nhật ký / trạng thái cập nhật.
    setTimeout(function () { window.location.reload(); }, 5000);
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
