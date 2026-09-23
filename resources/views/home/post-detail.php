<?php
$pageTitle = htmlspecialchars($post['title'] ?? 'Chi Tiết Hướng Dẫn') . " - " . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'home';
ob_start();
?>

<style>
/* Hiệu ứng lắp ráp khi mở trang (Assembly Entrance) */
@keyframes assembleIn {
    0% {
        opacity: 0;
        transform: translateY(35px) scale(0.95);
        filter: blur(6px);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
        filter: blur(0);
    }
}

.post-detail-wrapper {
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
}
.post-detail-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 2rem; align-items: start; }
.post-article { min-width: 0; padding: .5rem 0 2rem; }
.related-posts { min-width: 0; height: fit-content; border-left: 1px solid var(--glass-border); padding-left: 1.5rem; }
.related-posts a { display: block; padding: .8rem 0; border-bottom: 1px solid var(--glass-border); color: var(--ios-blue); text-decoration: none; font-weight: 600; overflow-wrap: anywhere; word-break: break-word; }
.post-content { overflow-wrap: anywhere; }
.post-content img, .post-content video, .post-content iframe { max-width: 100%; height: auto; }
.post-content table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; }
.post-content pre { max-width: 100%; overflow-x: auto; padding: 1rem; border-radius: 10px; }

/* Tối ưu hóa Responsive cho màn hình nhỏ (Mobile & Tablet) */
@media (max-width: 768px) {
    .post-detail-layout { grid-template-columns: 1fr; }
    .post-article { padding-top: 0; }
    .related-posts { border-top: 1px solid var(--glass-border); border-left: 0; padding: 1rem 0 0; }
    .post-title {
        font-size: 1.4rem !important;
    }
    .post-meta {
        gap: 0.75rem !important;
        font-size: 0.75rem !important;
    }
}
</style>

<div class="public-page home-page home-subpage home-post-page post-detail-wrapper">

    <?php if (!empty($post)): ?>
        <div class="post-detail-layout">
        <article class="post-article">
            <!-- Tiêu đề bài viết -->
            <h1 class="post-title" style="font-size: 1.8rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; color: var(--ios-text);">
                <?= htmlspecialchars($post['title']) ?>
            </h1>

            <!-- Thông tin bổ sung -->
            <div class="post-meta" style="display: flex; gap: 1.25rem; font-size: 0.82rem; color: var(--ios-text-secondary); border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <span>📅 Ngày đăng: <?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></span>
                <span>👤 Tác giả: <?= htmlspecialchars($post['author_name'] ?? 'Ban Quản Trị') ?></span>
                <span>🏷️ Chuyên mục: <strong style="color: var(--ios-blue);"><?= strtoupper(htmlspecialchars($post['type'] ?? 'news')) ?></strong></span>
            </div>

            <!-- Nội dung chi tiết bài viết -->
            <div class="post-content" style="font-size: 1rem; line-height: 1.7; color: var(--ios-text); word-break: break-word;">
                <?= $post['content'] ?>
            </div>
        </article>
        <aside class="related-posts">
            <h3>Bài viết khác</h3>
            <?php $visibleRelatedPosts = array_slice($relatedPosts ?? [], 0, 6); ?>
            <?php if (!empty($visibleRelatedPosts)): ?>
                <?php foreach ($visibleRelatedPosts as $related): ?>
                    <a href="/post-detail?slug=<?= urlencode($related['slug']) ?>"><?= htmlspecialchars($related['title']) ?></a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="related-posts-empty">Không có bài viết nào.</p>
            <?php endif; ?>
        </aside>
        </div>
    <?php else: ?>
        <div class="glass-card" style="text-align: center; padding: 3rem; color: var(--ios-text-secondary);">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">⚠️</div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Không Tìm Thấy Bài Viết</h2>
            <p style="font-size: 0.9rem; margin-bottom: 1.5rem;">Bài viết hướng dẫn này không tồn tại hoặc đã bị ẩn khỏi hệ thống.</p>
            <a href="/faq" class="glass-btn" style="text-decoration: none;">Xem Các Hướng Dẫn Khác</a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>