<?php
/**
 * Tab "Tạo Ảnh" (/admin/ai/image) — form prompt + kết quả + thư mục tab.
 *
 * Biến: $csrf_token, $tabConfig, $aiLabels, $imageModels (capability=image),
 *        $gallery (file thư mục tab), $sizes.
 * POST /admin/ai/image/generate → AiImageController::generate (đồng bộ).
 */
$pageTitle = 'Tạo Ảnh AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-image';

$imageModels = is_array($imageModels ?? null) ? $imageModels : [];
$sizes       = is_array($sizes ?? null) && $sizes !== [] ? $sizes : ['1024x1024'];

/** Nhãn hiển thị cho từng size (kèm tỉ lệ). */
$sizeLabels = [
    '1024x1024' => '1024×1024 - vuông 1:1',
    '1344x768'  => '1344×768 - ngang 16:9',
    '768x1344'  => '768×1344 - dọc 9:16',
];

ob_start();

require __DIR__ . '/_flash.php';
require __DIR__ . '/_tab-header.php';
?>

<!-- Form nhập prompt trực tiếp — không bọc thẻ, KHÔNG thêm tiêu đề giữa (trùng header trên) -->
<form action="/admin/ai/image/generate" method="POST" data-no-loader style="display: grid; gap: 0.9rem; width: 100%; box-sizing: border-box; margin-bottom: 1rem;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <div>
        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem;">Prompt mô tả ảnh <span style="color: var(--ios-danger);">*</span></label>
        <textarea name="prompt" rows="4" maxlength="5000" class="glass-input" style="width: 100%; font-size: 0.88rem; line-height: 1.6; resize: vertical; min-height: 6rem;" placeholder="Ví dụ: Cận cảnh ly cà phê bốc khói trên bàn gỗ, ánh nắng buổi sáng, phong cách chân thực..." required></textarea>
        <span style="font-size: 0.72rem; color: var(--ios-text-secondary);">Tối đa 5000 ký tự.</span>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.9rem; align-items: end;">
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Kích thước</label>
            <select name="size" class="glass-input" style="width: 100%;">
                <?php foreach ($sizes as $size): ?>
                    <?php $size = (string) $size; ?>
                    <option value="<?= htmlspecialchars($size) ?>" <?= $size === '1024x1024' ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sizeLabels[$size] ?? $size) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Chọn Model</label>
            <select name="model" class="glass-input" style="width: 100%;" required>
                <option value="">— Chọn model —</option>
                <?php foreach ($imageModels as $m): ?>
                    <option value="<?= htmlspecialchars((string) ($m['model_key'] ?? '')) ?>"><?= htmlspecialchars((string) ($m['model_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="glass-btn" data-busy-label="Đang tạo ảnh..." style="justify-self: start; justify-content: center; font-weight: 700; background: var(--ios-blue); color: var(--soc-ink); white-space: nowrap;">Tạo</button>
    </div>
</form>

<script>
// GIỮ LỰA CHỌN MODEL THEO TỪNG TAB (localStorage, không ghi SQL): chọn 1 lần →
// F5/viết lại vẫn giữ; nếu model đã lưu không còn trong dropdown thì để trống.
(function () {
    var KEY = 'vc_ai_model_image';
    var sels = document.querySelectorAll('select[name="model"]');
    if (sels.length !== 1) return;
    var sel = sels[0];
    var saved = '';
    try { saved = localStorage.getItem(KEY) || ''; } catch (e) {}
    if (saved) {
        var opts = sel.options;
        for (var i = 0; i < opts.length; i++) {
            if (opts[i].value === saved) { sel.value = saved; break; }
        }
    }
    sel.addEventListener('change', function () {
        try { localStorage.setItem(KEY, sel.value); } catch (e) {}
    });
})();
</script>

<?php
$mediaTab       = 'image';
$mediaDeleteUrl = '/admin/ai/image/delete';
require __DIR__ . '/_media-gallery.php';
?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
