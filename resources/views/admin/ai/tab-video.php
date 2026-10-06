<?php
/**
 * Tab "Tạo Video" (/admin/ai/video) — form NHẬP PROMPT TRỰC TIẾP.
 *
 * Biến: $csrf_token, $tabConfig, $aiLabels, $videoModels (model capability=video),
 *       $gallery (file thư mục tab), $job (LRO đang chạy).
 * POST /admin/ai/video/generate → AiVideoController::generate (trực tiếp + LRO poll).
 */
$pageTitle = 'Tạo Video AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-video';

$videoModels = is_array($videoModels ?? null) ? $videoModels : [];
$job         = is_array($job ?? null) ? $job : null;

ob_start();

require __DIR__ . '/_flash.php';
require __DIR__ . '/_tab-header.php';
?>

<!-- Form nhập prompt trực tiếp — không bọc thẻ, KHÔNG thêm tiêu đề giữa (trùng header trên) -->
<form action="/admin/ai/video/generate" method="POST" enctype="multipart/form-data" data-no-loader style="display: grid; gap: 0.9rem; width: 100%; box-sizing: border-box; margin-bottom: 1rem;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <div>
        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem;">Prompt mô tả video <span style="color: var(--ios-danger);">*</span></label>
        <textarea name="prompt" rows="4" maxlength="5000" class="glass-input" style="width: 100%; font-size: 0.88rem; line-height: 1.6; resize: vertical; min-height: 6rem;" placeholder="Ví dụ: Cận cảnh ly cà phê bốc khói trên bàn gỗ, ánh nắng buổi sáng, máy quay chuyển động chậm..." required></textarea>
        <span style="font-size: 0.72rem; color: var(--ios-text-secondary);">Tối đa 5000 ký tự. Nội quy AI đã được gộp sẵn trong System Prompt.</span>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.9rem; align-items: end;">
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Tỉ lệ khung hình</label>
            <select name="aspect_ratio" class="glass-input" style="width: 100%;">
                <option value="16:9" selected>16:9 - ngang</option>
                <option value="9:16">9:16 - dọc</option>
                <option value="1:1">1:1 - vuông</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Thời lượng (s)</label>
            <input type="number" name="duration_seconds" class="glass-input" value="6" min="1" max="60" step="1" style="width: 100%;">
        </div>
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Chọn Model</label>
            <select name="model" class="glass-input" style="width: 100%;" required>
                <option value="">— Chọn model —</option>
                <?php foreach ($videoModels as $m): ?>
                    <option value="<?= htmlspecialchars((string) ($m['model_key'] ?? '')) ?>"><?= htmlspecialchars((string) ($m['model_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Ảnh minh hoạ</label>
            <input type="file" name="reference_image" accept="image/*" class="glass-input" style="width: 100%; padding: 0.3rem;">
        </div>
        <button type="submit" class="glass-btn" data-busy-label="Đang tạo video..." style="justify-self: start; justify-content: center; font-weight: 700; background: var(--ios-blue); color: var(--soc-ink); white-space: nowrap;">Tạo</button>
    </div>
</form>

<script>
// GIỮ LỰA CHỌN MODEL THEO TỪNG TAB (localStorage, không ghi SQL): chọn 1 lần →
// F5/viết lại vẫn giữ; nếu model đã lưu không còn trong dropdown thì để trống.
(function () {
    var KEY = 'vc_ai_model_video';
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
$mediaTab       = 'video';
$mediaDeleteUrl = '/admin/ai/video/delete';
require __DIR__ . '/_media-gallery.php';
?>

<!-- Job LRO đang chạy → poll /admin/ai/video/poll mỗi 5s -->
<?php if ($job !== null): ?>
<div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box; border-left: 4px solid var(--ios-warning, #ff9f0a);" id="video-job-box">
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <span style="font-size: 1rem;">◷</span>
        <strong style="font-size: 0.9rem;">Video đang được nhà cung cấp xử lý (LRO)</strong>
        <span style="font-size: 0.78rem; color: var(--ios-text-secondary);">
            op <code><?= htmlspecialchars((string) ($job['operation_id'] ?? '')) ?></code> · bắt đầu <?= htmlspecialchars((string) ($job['started_at'] ?? '')) ?>
        </span>
        <span style="font-size: 0.8rem; color: var(--ios-blue);" id="video-job-status">Đang poll...</span>
    </div>
    <p style="font-size: 0.78rem; color: var(--ios-text-secondary); margin: 0.5rem 0 0;">
        Trang tự kiểm tra mỗi 5 giây — có thể mất 1–5 phút. Đóng tab cũng không mất job (lưu ở phiên).
    </p>
</div>
<script>
(function () {
    'use strict';
    const box    = document.getElementById('video-job-box');
    const status = document.getElementById('video-job-status');
    const csrf   = <?= json_encode((string) ($csrf_token ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    let stopped  = false;

    async function poll() {
        if (stopped) return;
        try {
            const res = await fetch('/admin/ai/video/poll', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body:    'csrf_token=' + encodeURIComponent(csrf)
            });
            const data = await res.json().catch(function () { return {}; });
            const state = data.state || (res.ok ? 'running' : 'failed');

            if (state === 'done') {
                stopped = true;
                if (status) status.textContent = 'Hoàn tất!';
                const flash = typeof data.flash === 'string' ? data.flash : 'Video đã hoàn tất.';
                if (status) status.textContent = flash;
                // Video xong → set anchor trước khi reload: trang tự cuộn xuống cab Thư Mục.
                window.location.hash = 'ai-media-gallery';
                setTimeout(function () { window.location.reload(); }, 1200);
                return;
            }
            if (state === 'failed') {
                stopped = true;
                if (status) {
                    status.textContent = 'Thất bại: ' + (data.error || 'lỗi không rõ');
                    status.style.color = 'var(--ios-danger)';
                }
                if (box) box.style.borderLeftColor = 'var(--ios-danger)';
                return;
            }
            if (status) status.textContent = 'Đang poll... ' + new Date().toLocaleTimeString();
        } catch (e) {
            if (status) status.textContent = 'Mất kết nối — thử lại...';
        }
        setTimeout(poll, 5000);
    }

    setTimeout(poll, 5000);
})();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
