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
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Tác Vụ #<?= (int) ($task['id'] ?? 0) ?></h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            Tác vụ: <strong><?= htmlspecialchars($aiLabels['modules'][(string) ($module['module_key'] ?? '')] ?? (string) ($module['module_key'] ?? ('#' . (int) ($task['module_id'] ?? 0)))) ?></strong>
            · Loại: <strong><?= htmlspecialchars($aiLabels['map']['task_type'][(string) ($task['task_type'] ?? '')] ?? (string) ($task['task_type'] ?? '')) ?></strong>
            · <span style="color: <?= $statusColor ?>; font-weight: 700;">● <?= htmlspecialchars($aiLabels['map']['task_status'][$status] ?? $status) ?></span>
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if (in_array($status, ['pending', 'queued', 'retrying'], true)): ?>
            <form method="POST" action="/admin/ai/tasks/run" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="glass-btn" style="white-space: nowrap;">▶ Chạy Ngay</button>
            </form>
            <form method="POST" action="/admin/ai/tasks/cancel" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn huỷ tác vụ này?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="glass-btn" style="white-space: nowrap;">✕ Huỷ Tác Vụ</button>
            </form>
        <?php endif; ?>
        <a href="/admin/ai" class="glass-btn" style="text-decoration: none; white-space: nowrap;">← Hàng Đợi</a>
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
                'Trạng thái'      => $aiLabels['map']['task_status'][$status] ?? $status,
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

        <?php if (is_array($output)): ?>
            <div style="margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px solid var(--ios-border, rgba(255,255,255,0.08));">
                <div style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.35rem;">Kết Quả Đầu Ra</div>
                <p style="font-size: 0.82rem; color: var(--ios-text-secondary);">
                    Bài viết #<?= (int) $output['id'] ?><?= trim((string) ($output['content_snapshot'] ?? '')) !== '' ? ' — ' . htmlspecialchars(mb_substr((string) $output['content_snapshot'], 0, 90)) . '…' : '' ?>
                </p>
                <a href="/admin/ai/outputs/detail?id=<?= (int) $output['id'] ?>" class="glass-btn" style="font-size: 0.78rem; text-decoration: none;">Xem Bài Viết</a>
            </div>
        <?php endif; ?>
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
                    <th>Mã HTTP</th>
                    <th>Thời Gian (ms)</th>
                    <th>Lúc</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($activities)): ?>
                    <?php foreach ($activities as $i => $activity): ?>
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);"><?= (int) ($activity['attempt_no'] ?? 0) ?></td>
                            <td style="font-weight: 600; font-size: 0.82rem;"><?= htmlspecialchars((string) ($activity['activity_type'] ?? '')) ?></td>
                            <td style="font-size: 0.8rem;"><?= htmlspecialchars((string) ($activity['status'] ?? '—')) ?></td>
                            <td style="font-size: 0.8rem; max-width: 26rem; word-break: break-word;"><?= htmlspecialchars((string) ($activity['message'] ?? '')) ?></td>
                            <td style="font-size: 0.8rem;"><?= $activity['http_status'] !== null ? (int) $activity['http_status'] : '—' ?></td>
                            <td style="font-size: 0.8rem;"><?= $activity['duration_ms'] !== null ? (int) $activity['duration_ms'] : '—' ?></td>
                            <td style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars((string) ($activity['created_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">
                            Chưa có hoạt động nào — tác vụ chưa được tiến trình nền xử lý.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
