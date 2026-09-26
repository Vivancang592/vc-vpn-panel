<?php
$pageTitle = 'Tạo yêu cầu hỗ trợ - ' . ($settings['site_title'] ?? 'VC VPN 2027');
ob_start();
?>
<section class="user-record-page" style="width: 100%;">
    <header class="user-orders-header">
        <div class="user-orders-intro">
            <h2 class="u-plans-title">TRUNG TÂM HỖ TRỢ</h2>
            <p style="margin: 0;">
                Mô tả chi tiết vấn đề bạn đang gặp phải để đội ngũ kỹ thuật viên phản hồi nhanh nhất.
            </p>
        </div>
        <div class="user-orders-title-row">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">
                🎟️ Tạo yêu cầu hỗ trợ mới
            </h1>
        </div>
    </header>

    <?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?>
        <div class="user-record-alert <?= !empty($_SESSION['error']) ? 'is-error' : '' ?>">
            <span><?= htmlspecialchars($_SESSION['error'] ?? $_SESSION['success']) ?></span>
            <button type="button" class="alert-close-btn" onclick="this.parentElement.remove();" aria-label="Đóng">&times;</button>
        </div>
        <?php unset($_SESSION['success'], $_SESSION['error']); ?>
    <?php endif; ?>

    <form class="glass-card" method="post" action="/tickets/create" style="padding: 1.5rem; border-radius: var(--radius-md, 12px); display: flex; flex-direction: column; gap: 1.15rem;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        
        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
            <label for="ticket-title" style="font-weight: 700; font-size: 0.9rem; color: var(--ios-text);">
                📌 Tiêu đề yêu cầu <span style="color: var(--ios-danger, #ff3b30);">*</span>
            </label>
            <input class="glass-input" id="ticket-title" name="title" maxlength="255" placeholder="Ví dụ: Cần hỗ trợ cấu hình gói VPN trên iPhone..." required style="width: 100%; border-radius: 8px; padding: 0.75rem; font-size: 0.9rem; box-sizing: border-box;">
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
            <label for="ticket-content" style="font-weight: 700; font-size: 0.9rem; color: var(--ios-text);">
                📝 Chi tiết vấn đề <span style="color: var(--ios-danger, #ff3b30);">*</span>
            </label>
            <textarea class="glass-input" id="ticket-content" name="content" rows="6" placeholder="Vui lòng cung cấp chi tiết sự cố, hình ảnh, hoặc thông báo lỗi bạn gặp phải..." required style="width: 100%; border-radius: 8px; padding: 0.75rem; font-size: 0.9rem; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.25rem;">
            <button class="glass-btn" type="submit" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.65rem 1.5rem; font-size: 0.9rem; font-weight: 700; background: var(--ios-blue, #007aff); color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                <span>🚀</span> Gửi yêu cầu hỗ trợ
            </button>
        </div>
    </form>
</section>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>

