<?php
/**
 * Trang Trợ Lý AI Admin — trang mỏng.
 *
 * Toàn bộ giao diện chat đã chuyển sang BÔNG BÓNG nổi toàn trang admin
 * (resources/views/layouts/_assistant_bubble.php, nhúng ở layouts/admin.php).
 * Trang này chỉ giữ landing giải thích + tự mở bong bóng toàn màn hình.
 */
$pageTitle = 'Trợ Lý Admin - Quản Trị Hệ Thống';
$activeMenu = 'ai-assistant';

ob_start();
?>

<style>
    .va-landing {
        max-width: 640px; margin: 7vh auto; text-align: center;
        padding: 2.2rem 2rem; background: var(--glass-bg);
        border: 1px solid var(--glass-border); border-radius: var(--radius-lg);
        backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur);
    }
    .va-landing .va-ico { font-size: 2.6rem; margin-bottom: 0.5rem; }
    .va-landing h1 { margin: 0 0 0.6rem; font-size: 1.35rem; }
    .va-landing p { margin: 0 0 1.1rem; font-size: 0.9rem; line-height: 1.6; color: var(--ios-text-secondary); }
    .va-landing .va-note { font-size: 0.78rem; margin: 1.1rem 0 0; }
    .va-landing code { background: rgba(0, 122, 255, 0.12); padding: 0.08em 0.35em; border-radius: 4px; }
    .va-open {
        padding: 0.65rem 1.5rem; border: none; border-radius: var(--radius-sm);
        background: var(--ios-blue); color: var(--soc-ink); font-size: 0.92rem; font-weight: 700;
        cursor: pointer;
    }
    .va-open:hover { filter: brightness(1.1); }
    .va-feats { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; margin-top: 1rem; }
    .va-feat {
        font-size: 0.75rem; padding: 0.3rem 0.7rem; border-radius: 999px;
        border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.05);
    }
</style>

<div class="va-landing">
    <div class="va-ico">AI</div>
    <h1>Trợ Lý Admin</h1>
    <p>
        Trợ lý giờ là <b>bong bóng nổi toàn trang admin</b> — xuất hiện ở góc dưới phải
        trên MỌI trang quản trị. Nhấp vào để mở popup toàn màn hình; trợ lý biết chính xác
        bạn đang ở trang nào và có thể đọc/sửa nội dung trang theo yêu cầu của bạn
        (riêng phần key, mật khẩu, bảo mật thì tuyệt đối không đụng tới).
    </p>
    <button type="button" class="va-open" id="va-open-asst">Trợ Lý</button>
    <div class="va-feats">
        <span class="va-feat">Lịch sử</span>
        <span class="va-feat">Kế hoạch</span>
        <span class="va-feat">Export</span>
        <span class="va-feat">Phân loại</span>
        <span class="va-feat">▸ Biết trang đang mở</span>
    </div>
    <p class="va-note">
        Lịch sử lưu trong <code>storage/assistant/</code> · Model do API key mở khóa ·
        Bong bóng vẫn sống sót khi bạn điều hướng giữa các trang admin.
    </p>
</div>

<script>
(function () {
    'use strict';
    function openAsst() {
        if (window.VCAsst && window.VCAsst.ready) { window.VCAsst.open(); return true; }
        return false;
    }
    // Trang đầy đủ: bubble script chạy SAU script này → chờ sự kiện vc-asst-ready.
    // Điều hướng fragment: bubble đã có sẵn → mở ngay.
    if (!openAsst()) {
        document.addEventListener('vc-asst-ready', function () { openAsst(); }, { once: true });
    }
    var btn = document.getElementById('va-open-asst');
    if (btn) {
        btn.addEventListener('click', function () {
            if (!openAsst()) document.addEventListener('vc-asst-ready', function () { openAsst(); }, { once: true });
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
