<?php
/**
 * Partial: header chung cho 6 tab chức năng AI (Phase C).
 *
 * Biến:
 *  - $tabConfig  : mảng từ AiBaseController::tabConfig() — null thì render nothing.
 *                  Bao gồm: title, subtitle, module_key, module_row, models
 *                  (TẤT CẢ model active theo key — không lọc capability),
 *                  model_keys (cho datalist).
 *  - $csrf_token : inject bởi BaseController::render().
 *
 * Nội dung: tiêu đề + phụ đề + trạng thái module. Nút Prompt/Nội Quy đã BỎ
 * (prompt kỹ thuật + nội quy được sửa từ trang Cấu Hình AI).
 */
if (empty($tabConfig) || !is_array($tabConfig)) {
    return;
}

$title      = (string) ($tabConfig['title'] ?? '');
$subtitle   = (string) ($tabConfig['subtitle'] ?? '');
$moduleKey  = (string) ($tabConfig['module_key'] ?? '');
$moduleRow  = is_array($tabConfig['module_row'] ?? null) ? $tabConfig['module_row'] : null;
$models     = is_array($tabConfig['models'] ?? null) ? $tabConfig['models'] : [];
$modelKeys  = is_array($tabConfig['model_keys'] ?? null) ? $tabConfig['model_keys'] : [];

// URL quay lại sau submit (safeBack() bên controller sẽ tái kiểm tra prefix).
$back = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$back = is_string($back) && str_starts_with($back, '/admin/ai/') ? $back : '';

// Model thủ công hiện tại (config JSON → model_override).
$currentOverride = '';
if ($moduleRow !== null && !empty($moduleRow['config'])) {
    $decoded = json_decode((string) $moduleRow['config'], true);
    if (is_array($decoded) && isset($decoded['model_override'])) {
        $currentOverride = trim((string) $decoded['model_override']);
    }
}
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 16rem; flex: 1;">
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;"><?= htmlspecialchars($title) ?></h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($subtitle) ?></p>
        <?php if ($moduleKey !== ''): ?>
            <div style="margin-top: 0.4rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; font-size: 0.78rem;">
                <code style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.15rem 0.5rem; border-radius: var(--radius-sm); font-weight: 600;"><?= htmlspecialchars($moduleKey) ?></code>
                <?php if ($moduleRow === null): ?>
                    <span style="color: var(--ios-warning, #ff9f0a); font-weight: 600;">Chưa đồng bộ module — đồng bộ tại Cấu Hình AI</span>
                <?php elseif ((int) ($moduleRow['is_enabled'] ?? 0) !== 1): ?>
                    <span style="color: var(--ios-danger); font-weight: 600;">Module đang TẮT</span>
                <?php else: ?>
                    <span style="color: var(--ios-success); font-weight: 600;">Module đang bật</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
