<?php
$pageTitle = 'Trung Tâm AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-dashboard';

ob_start();

require __DIR__ . '/_flash.php';

$statusColor = static function (string $status): string {
    return match ($status) {
        'completed'  => 'var(--ios-success)',
        'failed'     => 'var(--ios-danger)',
        'processing' => 'var(--ios-warning, #ff9f0a)',
        'retrying'   => 'var(--ios-warning, #ff9f0a)',
        'queued'     => 'var(--ios-blue)',
        default      => 'var(--ios-text-secondary)',
    };
};
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Trang Tổng Quan</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Quản trị phân hệ, mô hình AI, bộ prompt, tác vụ, kết quả và thư viện file của toàn bộ luồng AI</p>
    </div>
</div>

<?php if (!$kiraConfigured): ?>
<div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-warning, #ff9f0a);">
    <strong style="font-size: 0.9rem;">Chưa cấu hình khoá Kira API.</strong>
    <span style="font-size: 0.85rem; color: var(--ios-text-secondary);">
        Hệ thống có thể tạo tác vụ nhưng tiến trình nền sẽ không gọi được nhà cung cấp thật.
        <a href="/admin/ai/settings" style="color: var(--ios-blue);">Vào Cấu Hình AI</a> để thiết lập.
    </span>
</div>
<?php endif; ?>

<!-- Thống kê + model theo khả năng -->
<style>
    .ai-dashboard-summary-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        align-items: stretch;
        margin-bottom: 1.25rem;
        width: 100%;
        box-sizing: border-box;
    }

    @media (max-width: 700px) {
        .ai-dashboard-summary-grid { grid-template-columns: 1fr; }
    }
</style>
<div class="ai-dashboard-summary-grid">
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; margin-bottom: 0.9rem;">
        <h2 style="font-size: 1rem; font-weight: 700; margin: 0;">Thống Kê Hệ Thống AI</h2>
        <span style="font-size: 0.82rem; color: var(--ios-text-secondary); white-space: nowrap;">
            <strong style="color: var(--ios-text);"><?= (int) $modelCount ?></strong> model
        </span>
    </div>
    <div style="display: flex; flex-direction: column; font-size: 0.85rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.55rem 0; border-bottom: 1px solid var(--ios-border, rgba(255,255,255,0.12));">
            <span style="color: var(--ios-text-secondary);">Phân Hệ Đang Bật</span>
            <strong style="color: <?= $modulesEnabled > 0 ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;"><?= (int) $modulesEnabled ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.55rem 0; border-bottom: 1px solid var(--ios-border, rgba(255,255,255,0.12));">
            <span style="color: var(--ios-text-secondary);">Tác Vụ Đang Chờ</span>
            <strong><?= (int) $taskCounts['pending'] + (int) $taskCounts['processing'] + (int) $taskCounts['retrying'] ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.55rem 0; border-bottom: 1px solid var(--ios-border, rgba(255,255,255,0.12));">
            <span style="color: var(--ios-text-secondary);">Tổng Bài Viết</span>
            <strong><?= (int) $articleCount ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.55rem 0 0;">
            <span style="color: var(--ios-text-secondary);">File Trong Thư Viện</span>
            <strong><?= (int) $assetCount ?></strong>
        </div>
    </div>
</div>

    <div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
            <h2 style="font-size: 1rem; font-weight: 700;">Model Theo Khả Năng</h2>
            <a href="#ai-models-catalog" style="font-size: 0.8rem; color: var(--ios-blue); text-decoration: none; white-space: nowrap;">Xem</a>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.45rem; font-size: 0.85rem;">
            <?php if (empty($modelsByCapability)): ?>
                <span style="color: var(--ios-text-secondary);">Chưa có model active (kiểm tra khoá Kira / danh mục).</span>
            <?php else: ?>
                <?php foreach ($modelsByCapability as $capability => $count): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                        <span style="color: var(--ios-text-secondary);"><?= htmlspecialchars($aiLabels['map']['capability'][(string) $capability] ?? (string) $capability) ?></span>
                        <strong><?= (int) $count ?> model</strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; width: 100%; box-sizing: border-box;">

    <!-- Hàng đợi task -->
    <div class="glass-card" style="padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <h2 style="font-size: 1rem; font-weight: 700;">Hàng Đợi Tác Vụ</h2>
            <form method="POST" action="/admin/ai/tasks/drain" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="limit" value="5">
                <button type="submit" class="glass-btn" title="Chạy tối đa 5 tác vụ đến hạn" aria-label="Chạy tiến trình nền"
                    style="width: 2.1rem; height: 2.1rem; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: none; background: transparent; box-shadow: none; color: var(--ios-blue); font-size: 0.9rem;">
                    ▶
                </button>
            </form>
        </div>
        <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
            Hàng đợi <strong>TASK AI</strong> — chủ yếu là <strong>viết bài</strong>; việc <strong>đăng bài</strong> theo lịch quản lý riêng ở <a href="/admin/ai/fanpage" style="color: var(--ios-blue); text-decoration: none;">Nội Dung Fanpage › Danh Sách Bài Viết</a>.
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.85rem;">
            <div style="display: flex; justify-content: space-between;"><span style="color: var(--ios-text-secondary);">Tổng tác vụ</span><strong><?= (int) $taskCounts['total'] ?></strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: var(--ios-text-secondary);">Chờ xử lý</span><strong><?= (int) $taskCounts['pending'] ?></strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: var(--ios-text-secondary);">Đang xử lý</span><strong><?= (int) $taskCounts['processing'] ?></strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: var(--ios-text-secondary);">Chờ thử lại</span><strong><?= (int) $taskCounts['retrying'] ?></strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: var(--ios-text-secondary);">Hoàn tất</span><strong style="color: var(--ios-success);"><?= (int) $taskCounts['completed'] ?></strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: var(--ios-text-secondary);">Thất bại</span><strong style="color: var(--ios-danger);"><?= (int) $taskCounts['failed'] ?></strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: var(--ios-text-secondary);">Đã huỷ</span><strong style="color: var(--ios-text-secondary);"><?= (int) $taskCounts['cancelled'] ?></strong></div>
        </div>

    </div>

    <!-- Trạng thái phân hệ -->
    <div class="glass-card" style="padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h2 style="font-size: 1rem; font-weight: 700;">Trạng Thái Phân Hệ</h2>
            <a href="/admin/ai/settings" style="font-size: 0.8rem; color: var(--ios-blue); text-decoration: none;">Cài đặt</a>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.45rem; font-size: 0.85rem;">
            <?php foreach ($moduleRows as $row): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                    <span style="color: var(--ios-text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars((string) $row['label']) ?></span>
                    <?php if ($row['id'] === null): ?>
                        <span style="font-size: 0.75rem; color: var(--ios-text-secondary); white-space: nowrap;">Chưa đăng ký</span>
                    <?php elseif ($row['db_enabled'] === true): ?>
                        <span style="font-size: 0.75rem; color: var(--ios-success); font-weight: 600; white-space: nowrap;">● Đang bật</span>
                    <?php else: ?>
                        <span style="font-size: 0.75rem; color: var(--ios-danger); font-weight: 600; white-space: nowrap;">○ Đang tắt</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Danh sách model đầy đủ (chuyển từ trang /admin/ai/models — hiển ngay tại Tổng Quan) -->
<?php
$capViDash = static function (?string $cap) use ($aiLabels): string {
    $cap = trim((string) $cap);
    return $cap === '' ? '—' : ($aiLabels['map']['capability'][$cap] ?? $cap);
};
// Model "đang dùng" = đang được một phân hệ chọn làm mặc định (usageByModel).
$modelUsedCount = 0;
foreach ($models as $mItem) {
    if ((int) ($usageByModel[(int) ($mItem['id'] ?? 0)] ?? 0) > 0) {
        $modelUsedCount++;
    }
}
$modelIdleCount = max(0, count($models) - $modelUsedCount);
?>
<style>
    /* Mặc định chỉ hiện model đang dùng — bấm mũi tên thả xuống để xem tất cả. */
    #ai-models-catalog tr.ai-model-idle { display: none; }
    #ai-models-catalog.show-all tr.ai-model-idle { display: table-row; }
    #ai-models-catalog.show-all tr.ai-model-idle-note { display: none; }
</style>
<div id="ai-models-catalog" class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.75rem;">
        <h2 style="font-size: 1rem; font-weight: 700;">Danh Sách Model AI</h2>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <?php if ($modelIdleCount > 0): ?>
                <button type="button" class="glass-btn" id="ai-model-toggle" data-total="<?= (int) count($models) ?>"
                        aria-expanded="false" aria-controls="ai-models-table"
                        style="font-size: 0.82rem; white-space: nowrap;" title="Xem toàn bộ danh mục model">
                    ▼ Hiện tất cả
                </button>
            <?php endif; ?>
            <form method="POST" action="/admin/ai/models/sync" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <button type="submit" class="glass-btn" style="font-size: 0.82rem; white-space: nowrap;">⟳ Đồng Bộ</button>
            </form>
        </div>
    </div>
    <?php if ($modelIdleCount > 0): ?>
    <p style="font-size: 0.78rem; color: var(--ios-text-secondary); margin: 0 0 0.75rem;">
        Đang dùng <strong style="color: var(--ios-success);"><?= (int) $modelUsedCount ?> model </strong>
        - <strong><?= (int) $modelIdleCount ?> model</strong> chưa dùng.
    </p>
    <?php endif; ?>
    <?php if (!$kiraConfigured): ?>
    <div style="padding: 0.75rem 1rem; margin-bottom: 0.75rem; border-left: 4px solid var(--ios-warning, #ff9f0a); background: rgba(255, 159, 10, 0.06); border-radius: var(--radius-sm);">
        <strong style="font-size: 0.85rem;">Chưa cấu hình khoá API.</strong>
        <span style="font-size: 0.82rem; color: var(--ios-text-secondary);">
            Không thể đồng bộ từ nhà cung cấp. <a href="/admin/ai/settings" style="color: var(--ios-blue);">Vào Cấu Hình AI</a>
            rồi bấm nút Đồng Bộ phía trên.
        </span>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="glass-table" id="ai-models-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mã Mô Hình</th>
                    <th>Tên Hiển Thị</th>
                    <th>Khả Năng</th>
                    <th>Nguồn</th>
                    <th>Số Phân Hệ Dùng</th>
                    <th>Trạng Thái</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($models)): ?>
                    <?php foreach ($models as $model): ?>
                        <?php
                        $limits = [];
                        if (!empty($model['limits'])) {
                            $decoded = is_array($model['limits']) ? $model['limits'] : json_decode((string) $model['limits'], true);
                            $limits = is_array($decoded) ? $decoded : [];
                        }
                        $source  = (string) ($limits['source'] ?? 'unknown');
                        $isUsed  = (int) ($usageByModel[(int) ($model['id'] ?? 0)] ?? 0) > 0;
                        ?>
                        <tr class="<?= $isUsed ? '' : 'ai-model-idle' ?>">
                            <td style="font-weight: 700;">#<?= (int) $model['id'] ?></td>
                            <td style="font-weight: 700; font-size: 0.85rem;"><code><?= htmlspecialchars((string) ($model['model_key'] ?? '')) ?></code></td>
                            <td style="font-size: 0.85rem;"><?= htmlspecialchars((string) ($model['model_name'] ?? '')) ?></td>
                            <td>
                                <span style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 600;">
                                    <?= htmlspecialchars($capViDash($model['capability'] ?? '')) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--ios-text-secondary);">
                                <?= htmlspecialchars($aiLabels['map']['model_source'][$source] ?? $source) ?>
                            </td>
                            <td style="font-size: 0.82rem; font-weight: 600;">
                                <?= (int) ($usageByModel[(int) $model['id']] ?? 0) ?>
                            </td>
                            <td>
                                <?php if ((int) ($model['is_active'] ?? 0) === 1): ?>
                                    <span style="color: var(--ios-success); font-weight: 600; font-size: 0.82rem;">● Đang bật</span>
                                <?php else: ?>
                                    <span style="color: var(--ios-danger); font-weight: 600; font-size: 0.82rem;">○ Đang tắt</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" action="/admin/ai/models/toggle" style="margin: 0; display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="id" value="<?= (int) $model['id'] ?>">
                                    <button type="submit" class="glass-btn" style="font-size: 0.78rem; white-space: nowrap;">
                                        <?= (int) ($model['is_active'] ?? 0) === 1 ? '○ Tắt' : '● Bật' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($modelUsedCount === 0 && $modelIdleCount > 0): ?>
                        <tr class="ai-model-idle-note">
                            <td colspan="8" style="text-align: center; padding: 1.25rem; color: var(--ios-text-secondary);">
                                Chưa có model nào đang được phân hệ dùng làm mặc định — bấm ▼ để xem toàn bộ <?= (int) count($models) ?> model.
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">
                            Chưa có mô hình AI nào được cấu hình. Hãy bấm "Đồng Bộ Từ Nhà Cung Cấp" phía trên.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    'use strict';
    var btn = document.getElementById('ai-model-toggle');
    var box = document.getElementById('ai-models-catalog');
    if (!btn || !box) return;
    btn.addEventListener('click', function () {
        var on = box.classList.toggle('show-all');
        btn.setAttribute('aria-expanded', on ? 'true' : 'false');
        btn.textContent = on
            ? '▲ Ẩn model chưa dùng'
            : '▼ Hiện tất cả (' + btn.getAttribute('data-total') + ' model)';
    });
})();
</script>

<!-- Tác vụ gần đây -->
<div class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Tác Vụ Gần Đây</h2>
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Phân Hệ</th>
                    <th>Trạng Thái</th>
                    <th>Ưu Tiên</th>
                    <th>Thử Lại</th>
                    <th>Tạo Lúc</th>
                    <th style="text-align: center;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentTasks)): ?>
                    <?php foreach ($recentTasks as $task): ?>
                        <?php $status = (string) ($task['status'] ?? ''); ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= (int) $task['id'] ?></td>
                            <td style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($aiLabels['map']['task_type'][(string) ($task['task_type'] ?? '')] ?? (string) ($task['task_type'] ?? '')) ?></td>
                            <td>
                                <span style="color: <?= $statusColor($status) ?>; font-weight: 600; font-size: 0.82rem;">● <?= htmlspecialchars($aiLabels['map']['task_status'][$status] ?? $status) ?></span>
                            </td>
                            <td style="font-size: 0.85rem;"><?= (int) ($task['priority'] ?? 0) ?></td>
                            <td style="font-size: 0.85rem;"><?= (int) ($task['retry_count'] ?? 0) ?> / <?= (int) ($task['max_retries'] ?? 0) ?></td>
                            <td style="font-size: 0.82rem; color: var(--ios-text-secondary);"><?= htmlspecialchars((string) ($task['created_at'] ?? '')) ?></td>
                            <td style="text-align: center;">
                                <?php if (in_array($status, ['pending', 'queued', 'retrying', 'processing'], true)): ?>
                                    <form method="post" action="/admin/ai/tasks/cancel" style="display: inline;"
                                          onsubmit="return confirm('Huỷ tác vụ #<?= (int) $task['id'] ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                        <input type="hidden" name="back" value="/admin/ai">
                                        <button type="submit" title="Huỷ tác vụ"
                                                style="border: 1px solid rgba(255,59,48,0.35); background: rgba(255,59,48,0.08); color: #ff3b30; border-radius: 8px; padding: 0.15rem 0.55rem; cursor: pointer; font-weight: 700; line-height: 1.4;">✕</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary); opacity: 0.35; font-size: 0.78rem;" title="Task đã kết thúc, không thể huỷ">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">
                            Chưa có tác vụ nào. Tác vụ được tạo tự động khi bạn nhập chủ đề ở tab <a href="/admin/ai/fanpage" style="color: var(--ios-blue);">Nội Dung Fanpage</a>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($recentActivity)): ?>
<div class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Hoạt Động Gần Đây</h2>
    <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.83rem;">
        <?php foreach ($recentActivity as $activity): ?>
            <div style="display: flex; gap: 0.75rem; align-items: baseline; border-bottom: 1px solid var(--ios-border, rgba(255,255,255,0.06)); padding-bottom: 0.35rem;">
                <span style="color: var(--ios-text-secondary); white-space: nowrap; font-size: 0.78rem;"><?= htmlspecialchars((string) ($activity['created_at'] ?? '')) ?></span>
                <strong style="font-size: 0.8rem; white-space: nowrap;">#<?= (int) ($activity['_task_id'] ?? 0) ?></strong>
                <span style="color: var(--ios-blue); font-size: 0.78rem; white-space: nowrap;"><?= htmlspecialchars($aiLabels['map']['activity_type'][(string) ($activity['activity_type'] ?? '')] ?? (string) ($activity['activity_type'] ?? '')) ?></span>
                <span style="color: var(--ios-text-secondary); overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars((string) ($activity['message'] ?? '')) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
