<?php
$pageTitle = 'Bộ Prompt AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-settings';

ob_start();

require __DIR__ . '/_flash.php';

if ($promptKey === '' && !empty($defaultKeys)) {
    $promptKey = (string) $defaultKeys[0];
}

/** Tên tiếng Việt của bộ prompt (không hiển thị key thô). */
$promptVi = $aiLabels['prompts'][$promptKey] ?? $promptKey;

/** Nội dung hiện hành: file → mặc định. */
$rawSystem = (string) ($rawTemplate['system'] ?? '');
$rawUser   = (string) ($rawTemplate['user'] ?? '');
$rawSource = (string) ($rawTemplate['source'] ?? 'none');
$sourceVi  = [
    'file'    => 'Đang đọc từ FILE trong storage/prompts',
    'default' => 'Đang dùng mẫu mặc định của hệ thống',
    'none'    => 'Chưa có nội dung',
][$rawSource] ?? $rawSource;

$tplSystem = (string) ($defaultTemplate['system'] ?? '');
$tplUser   = (string) ($defaultTemplate['user'] ?? '');
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word; margin-bottom: 0.35rem;">Prompt <?= htmlspecialchars($promptVi) ?></h1>
    <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
        Mã nội bộ: <code><?= htmlspecialchars($promptKey) ?></code>
        &nbsp;·&nbsp; <?= htmlspecialchars($sourceVi) ?>
    </p>
</div>

<!-- Chọn bộ prompt -->
<div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <form method="GET" action="/admin/ai/prompts/create" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <label style="font-size: 0.85rem; color: var(--ios-text-secondary);">Chọn nội quy:</label>
        <select name="prompt_key" onchange="this.form.submit()" style="padding: 0.45rem 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); background: transparent; color: inherit; font-size: 0.85rem;">
            <?php foreach ($defaultKeys as $key): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $key === $promptKey ? 'selected' : '' ?>><?= htmlspecialchars($aiLabels['prompts'][$key] ?? $key) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="glass-btn" style="font-size: 0.8rem;">Chọn</button></noscript>
        <?php if (!empty($variables)): ?>
            <span style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                Biến bắt buộc: <?php foreach ($variables as $var): ?><code>{{<?= htmlspecialchars($var) ?>}}</code> <?php endforeach; ?>
            </span>
        <?php endif; ?>
    </form>
</div>

<!-- Soạn thảo prompt (lưu vào FILE — nguồn duy nhất) -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; margin-bottom: 1rem; border-left: 4px solid var(--ios-blue);">
    <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem;">📄 Soạn Thảo Prompt</h2>
    <div style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem; word-break: break-all;">
        <div>Đường dẫn file: <code><?= htmlspecialchars((string) ($filePath ?? '')) ?></code></div>
        <div>
            Trạng thái:
            <?php if (!empty($fileExists)): ?>
                <span style="color: var(--ios-success); font-weight: 600;">● Đã có file</span>
                <?php if (!empty($fileModified)): ?>
                    <span>· Cập nhật lần cuối: <?= htmlspecialchars((string) $fileModified) ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span style="color: var(--ios-text-secondary); font-weight: 600;">○ Chưa có file — <?= htmlspecialchars($sourceVi) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" action="/admin/ai/prompts/save-file">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" name="prompt_key" value="<?= htmlspecialchars($promptKey) ?>">

        <div style="margin-bottom: 0.75rem;">
            <label style="display: block; font-size: 0.8rem; color: var(--ios-text-secondary); margin-bottom: 0.25rem;">Chỉ dẫn cho AI</label>
            <textarea name="system_prompt" rows="16" required style="width: 100%; padding: 0.6rem 0.7rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); background: transparent; color: inherit; font-size: 0.85rem; font-family: ui-monospace, monospace; box-sizing: border-box; resize: vertical; min-height: 16rem; max-height: 40rem; line-height: 1.5;"><?= htmlspecialchars($rawSystem !== '' ? $rawSystem : $tplSystem) ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="glass-btn" style="white-space: nowrap;">💾 Lưu Prompt</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
