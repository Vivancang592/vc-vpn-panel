<?php
/**
 * Tab "Lồng Tiếng" (/admin/ai/dubbing) — form NHẬP LỜI THOẠI TRỰC TIẾP.
 *
 * Biến: $csrf_token, $tabConfig, $aiLabels, $voices (giọng Kira), $voiceSamples (URL demo giọng),
 *       $ttsModels (model capability=audio), $gallery (file thư mục tab).
 * POST /admin/ai/dubbing/generate → AiDubbingController::generate (đồng bộ).
 */
$pageTitle = 'Lồng Tiếng AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-dubbing';

$voices       = is_array($voices ?? null) ? $voices : [];
$voiceSamples = is_array($voiceSamples ?? null) ? $voiceSamples : [];
$ttsModels    = is_array($ttsModels ?? null) ? $ttsModels : [];

ob_start();

require __DIR__ . '/_flash.php';
require __DIR__ . '/_tab-header.php';
?>

<!-- Form nhập lời thoại trực tiếp — không bọc thẻ, KHÔNG thêm tiêu đề giữa (trùng header trên) -->
<form action="/admin/ai/dubbing/generate" method="POST" data-no-loader style="display: grid; gap: 0.9rem; width: 100%; box-sizing: border-box; margin-bottom: 1rem;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <div>
        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem;">Lời thoại <span style="color: var(--ios-danger);">*</span></label>
        <textarea name="text" rows="4" maxlength="10000" class="glass-input" style="width: 100%; font-size: 0.88rem; line-height: 1.6; resize: vertical; min-height: 6rem;" placeholder="Ví dụ: Bạn đang mất thời gian chờ game load? VPN của chúng tôi giúp giảm lag ngay hôm nay..." required></textarea>
        <span style="font-size: 0.72rem; color: var(--ios-text-secondary);">Tối đa 10000 ký tự. Nội Quy AI (rules_video_dubbing) ở header tự động được áp dụng.</span>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.9rem; align-items: end;">
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Chọn Giọng đọc</label>
            <div style="display: flex; gap: 0.45rem; align-items: stretch;">
                <select name="voice" id="dubbing-voice" class="glass-input" style="flex: 1; min-width: 0; width: auto;">
                    <?php foreach ($voices as $key => $label): ?>
                        <option value="<?= htmlspecialchars((string) $key) ?>" <?= $key === 'alloy' ? 'selected' : '' ?>><?= htmlspecialchars((string) $key) ?> - <?= htmlspecialchars((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="dubbing-voice-preview" title="Nghe thử giọng này" aria-label="Nghe thử giọng này" style="flex: 0 0 auto; width: 2.2rem; border: none; background: transparent; color: var(--ios-blue, #0a84ff); cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: filter .15s ease, transform .15s ease;">🔊</button>
            </div>
            <audio id="dubbing-voice-audio" style="display: none;"></audio>
        </div>
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Chọn Model TTS</label>
            <select name="model" class="glass-input" style="width: 100%;">
                <option value="">Mặc định của module</option>
                <?php foreach ($ttsModels as $m): ?>
                    <option value="<?= htmlspecialchars((string) ($m['model_key'] ?? '')) ?>" <?= !empty($m['is_default']) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($m['model_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="glass-btn" data-busy-label="Đang tạo lời thoại..." style="justify-self: start; justify-content: center; font-weight: 700; background: var(--ios-blue); color: #fff; white-space: nowrap;">🎙️ Tạo Lời Thoại</button>
    </div>
</form>

<!-- Nút nghe thử giọng phóng to khi bấm — CSS gom trong public/assets/css/admin.css -->
<script>
(function () {
    // Bản demo giọng CHÍNH THỨC của Kira (public/assets/audio/voice-samples/) —
    // nghe thử không gọi TTS nên không tốn lượt & không dính lỗi 403 của API key.
    var samples = <?= json_encode($voiceSamples, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var sel   = document.getElementById('dubbing-voice');
    var btn   = document.getElementById('dubbing-voice-preview');
    var audio = document.getElementById('dubbing-voice-audio');
    if (!sel || !btn || !audio) return;

    var ICON       = '🔊';
    var ICON_PAUSE = '⏸';
    var current    = '';
    var flashTimer = null;

    function setIcon(icon, title) {
        btn.textContent = icon;
        btn.title = title || 'Nghe thử giọng này';
    }
    function resetIcon() {
        if (flashTimer) { clearTimeout(flashTimer); flashTimer = null; }
        setIcon(audio.paused ? ICON : ICON_PAUSE);
    }
    function showErr(text) {
        if (flashTimer) clearTimeout(flashTimer);
        setIcon('⚠️', text);
        flashTimer = setTimeout(resetIcon, 3000);
    }

    function play(url) {
        var p = audio.play();
        if (p && typeof p.catch === 'function') {
            p.catch(function (err) { showErr('Không phát được bản nghe thử: ' + err.message); });
        }
    }

    btn.addEventListener('click', function () {
        var voice = sel.value;
        var url = samples[voice];
        if (!url) {
            showErr('Chưa có bản nghe thử cho giọng này.');
            return;
        }
        // Bấm lại đúng giọng đang phát → tạm dừng/tiếp tục.
        if (current === voice && audio.getAttribute('src')) {
            if (audio.paused) play(url); else audio.pause();
            return;
        }
        current = voice;
        audio.setAttribute('src', url);
        play(url);
    });

    audio.addEventListener('play', function () { setIcon(ICON_PAUSE, 'Tạm dừng bản nghe thử'); });
    audio.addEventListener('pause', function () { setIcon(ICON); });
    audio.addEventListener('ended', function () { setIcon(ICON); });
    audio.addEventListener('error', function () { showErr('Tệp âm thanh lỗi hoặc không tồn tại.'); });

    sel.addEventListener('change', function () {
        if (!audio.paused) audio.pause();
        resetIcon();
    });
})();
</script>

<?php
$mediaTab       = 'dubbing';
$mediaDeleteUrl = '/admin/ai/dubbing/delete';
require __DIR__ . '/_media-gallery.php';
?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
