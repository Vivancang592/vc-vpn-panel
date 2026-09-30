<?php
/**
 * Partial: khối "Thư mục tab" (gallery) — dạng lưới — dùng chung
 * cho 3 tab media trực tiếp: Tạo Ảnh / Tạo Video / Lồng Tiếng.
 *
 * Biến:
 *  - $gallery         : array<int, array> file thuộc thư mục tab (listByTab).
 *  - $mediaTab        : tên tab ('image'|'video'|'dubbing') — chỉ để hiển thị.
 *  - $mediaDeleteUrl  : URL POST xoá (vd: /admin/ai/image/delete).
 *  - $csrf_token      : inject bởi BaseController::render().
 *
 * Nút Xem/Xoá ẩn mặc định — chỉ hiện khi rê chuột vào card (hoặc khi
 * card đang được focus bằng bàn phím).
 */
$gallery        = is_array($gallery ?? null) ? $gallery : [];
$mediaTab       = (string) ($mediaTab ?? '');
$mediaDeleteUrl = (string) ($mediaDeleteUrl ?? '');
$mediaCsrf      = (string) ($csrf_token ?? '');

/** Nhận diện loại media từ mime_type (fallback: asset_kind). */
$mediaKind = static function (array $item): string {
    $mime = strtolower((string) ($item['mime_type'] ?? ''));
    if (str_starts_with($mime, 'video/')) {
        return 'video';
    }
    if (str_starts_with($mime, 'audio/')) {
        return 'audio';
    }
    if (str_starts_with($mime, 'image/')) {
        return 'image';
    }
    $kind = strtolower((string) ($item['asset_kind'] ?? ''));
    return in_array($kind, ['image', 'video', 'audio'], true) ? $kind : 'file';
};

/** Render 1 file media trong card gallery. */
$mediaTag = static function (string $kind, string $url): string {
    $u = htmlspecialchars($url, ENT_QUOTES);
    return match ($kind) {
        'image' => '<img src="' . $u . '" alt="Kết quả AI" style="width: 100%; height: 100%; object-fit: cover; display: block;">',
        'video' => '<video src="' . $u . '" controls preload="metadata" style="width: 100%; height: 100%; display: block; background: #000;"></video>',
        'audio' => '<audio src="' . $u . '" controls preload="metadata" style="width: 100%;"></audio>',
        default => '<a href="' . $u . '" target="_blank" rel="noopener" style="color: var(--ios-blue); font-size: 0.8rem;">Mở file</a>',
    };
};

$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
};
?>

<!-- Thư mục tab (gallery) — lưới kết quả, nút Xem/Xoá chỉ hiện khi rê/chạm vào card -->
<style>
    .ai-media-card .ai-media-actions { opacity: 0; visibility: hidden; transition: opacity 0.15s ease; }
    .ai-media-card:hover .ai-media-actions,
    .ai-media-card:focus-within .ai-media-actions,
    .ai-media-card.is-open .ai-media-actions { opacity: 1; visibility: visible; }
    /* Nút Xem/Xoá luôn ở GIỮA vùng ảnh/video (overlay), không nằm dưới card. */
    .ai-media-thumb { position: relative; }
    .ai-media-thumb .ai-media-actions {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        background: rgba(0, 0, 0, 0.55);
        z-index: 2;
        /* Backdrop không chặn nút play/seek của video/audio phía dưới;
           chỉ 2 nút bấm thật sự nhận sự kiện chuột/chạm. */
        pointer-events: none;
    }
    .ai-media-thumb .ai-media-actions a,
    .ai-media-thumb .ai-media-actions button { pointer-events: auto; }
    .ai-media-thumb .ai-media-actions .glass-btn { backdrop-filter: none; }
    @media (hover: none) {
        /* Điện thoại không hover được — tap tạo "pseudo-hover" nên phải loại
           card đang mở khỏi rule ẩn, nếu không will is-open bị rule :hover đè. */
        .ai-media-card:hover:not(.is-open) .ai-media-actions { opacity: 0; visibility: hidden; }
        .ai-media-card.is-open .ai-media-actions,
        .ai-media-card:focus-within .ai-media-actions { opacity: 1; visibility: visible; }
    }
</style>
<script>
(function () {
    'use strict';
    /* Script này nằm TRƯỚC phần gallery trong DOM — phải chờ card render xong mới bind. */
    function bindGalleryCards() {
        document.querySelectorAll('.ai-media-card').forEach(function (card) {
            if (card.getAttribute('data-ai-bound') === '1') return;
            card.setAttribute('data-ai-bound', '1');
            card.addEventListener('click', function (e) {
                /* Bỏ qua khi chạm vào nút trong overlay / link / audio player —
                   để trên điện thoại (không hover) tap vào ảnh vẫn mở được overlay. */
                if (e.target.closest('.ai-media-actions') || e.target.closest('a, button, input, select, textarea, audio')) {
                    return;
                }
                /* Video full-thumb: vùng controls native ~48px dưới cùng bấm được
                   mà không bị coi là thao tác mở overlay. */
                var vid = e.target.closest('video');
                if (vid) {
                    var vr = vid.getBoundingClientRect();
                    if (e.clientY > vr.bottom - 48) return;
                }
                var wasOpen = card.classList.contains('is-open');
                document.querySelectorAll('.ai-media-card.is-open').forEach(function (c) { c.classList.remove('is-open'); });
                if (!wasOpen) card.classList.add('is-open');
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindGalleryCards);
    } else {
        bindGalleryCards();
    }
})();
</script>

<div class="glass-card" id="ai-media-gallery" style="padding: 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
        <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0;">📁 Thư Mục Tab<?= $mediaTab !== '' ? ' - ' . htmlspecialchars($mediaTab) : '' ?></h2>
        <span style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= count($gallery) ?> file</span>
    </div>

    <?php if ($gallery === []): ?>
        <p style="font-size: 0.85rem; color: var(--ios-text-secondary); margin: 0;">
            Chưa có file nào. File sinh ra sẽ tự lưu vào thư mục này và hiển thị tại đây (xoá bất cứ lúc nào).
        </p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap: 0.9rem;">
            <?php foreach ($gallery as $item): ?>
                <?php
                    $item   = (array) $item;
                    $id     = (int) ($item['id'] ?? 0);
                    $url    = (string) ($item['_url'] ?? ($item['url'] ?? ''));
                    $kind   = $mediaKind($item);
                    $bytes  = (int) ($item['size_bytes'] ?? 0);
                    $when   = (string) ($item['created_at'] ?? '');
                    $meta   = is_array($item['_meta'] ?? null) ? $item['_meta'] : [];
                    $aspect = (string) ($meta['aspect_ratio'] ?? '');
                    $voice  = (string) ($meta['voice'] ?? '');
                ?>
                <div class="ai-media-card" style="border: 1px solid var(--ios-border, rgba(255,255,255,0.12)); border-radius: var(--radius-sm); overflow: hidden; background: rgba(255,255,255,0.03); display: flex; flex-direction: column;">
                    <div class="ai-media-thumb" style="height: 9.5rem; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if ($kind === 'audio'): ?>
                            <div style="width: 100%; padding: 0 0.6rem; box-sizing: border-box;"><?= $mediaTag('audio', $url) ?></div>
                        <?php else: ?>
                            <?= $mediaTag($kind, $url) ?>
                        <?php endif; ?>
                        <!-- Nút Xem/Xoá: phủ ĐÚNG GIỮA vùng ảnh/video, chỉ hiện khi rê chuột -->
                        <div class="ai-media-actions">
                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"
                               class="glass-btn" style="font-size: 0.74rem; padding: 0.35rem 0.7rem; text-decoration: none;">Xem ↗</a>
                            <?php if ($mediaDeleteUrl !== '' && $id > 0): ?>
                                <form method="POST" action="<?= htmlspecialchars($mediaDeleteUrl) ?>" style="margin: 0;"
                                      onsubmit="return confirm('Xoá file #<?= $id ?> khỏi thư mục tab <?= htmlspecialchars($mediaTab) ?>? Hành động không thể hoàn tác.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($mediaCsrf) ?>">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="glass-btn" style="font-size: 0.74rem; padding: 0.35rem 0.7rem; color: var(--ios-danger);">🗑️ Xoá</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="padding: 0.6rem 0.7rem; display: grid; gap: 0.3rem;">
                        <div style="font-size: 0.76rem; font-weight: 600; word-break: break-all;">
                            #<?= $id ?> · <?= htmlspecialchars($bytes > 0 ? $formatBytes($bytes) : '—') ?>
                        </div>
                        <div style="font-size: 0.72rem; color: var(--ios-text-secondary);">
                            <?= htmlspecialchars($when) ?>
                            <?php if ($aspect !== ''): ?> · <?= htmlspecialchars($aspect) ?><?php endif; ?>
                            <?php if ($voice !== ''): ?> · <?= htmlspecialchars($voice) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
