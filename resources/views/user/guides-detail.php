<?php
$pageTitle = htmlspecialchars($post['title'] ?? 'Hướng dẫn') . ' - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'admin';
$extraJs = 'app';
$relatedPosts = isset($relatedPosts) && is_array($relatedPosts) ? $relatedPosts : [];

$extractThumb = static function (array $item): string {
    $content = htmlspecialchars_decode((string)($item['content'] ?? ''));
    if (preg_match('/<!--thumbnail:(.*?)-->/i', $content, $m)) {
        $thumb = trim((string)$m[1]);
        if ($thumb !== '') {
            return $thumb;
        }
    }
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
        $thumb = trim((string)$m[1]);
        if ($thumb !== '') {
            return $thumb;
        }
    }
    return '/assets/images/favicon.png';
};

ob_start();
?>

<section style="display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 1rem; align-items: start;">
    <article class="glass-card" style="padding: clamp(1.25rem, 4vw, 2.25rem); min-width: 0;">
        <a href="/user/guides" style="display: inline-block; margin-bottom: 1rem; color: var(--ios-blue); font-size: .9rem; font-weight: 600; text-decoration: none;">&larr; Quay lại hướng dẫn</a>
        <p style="margin: 0 0 .4rem; color: var(--ios-text-secondary); font-size: .8rem;">Cập nhật: <?= !empty($post['created_at']) ? date('d/m/Y', strtotime($post['created_at'])) : 'N/A' ?></p>
        <h1 style="margin: 0 0 1.5rem; font-size: clamp(1.45rem, 3vw, 2rem);"><?= htmlspecialchars($post['title'] ?? 'Hướng dẫn') ?></h1>
        <div class="post-content" style="color: var(--ios-text); line-height: 1.75; overflow-wrap: anywhere; word-break: break-word;">
            <?= $post['content'] ?? '' ?>
        </div>
    </article>

    <aside class="glass-card" style="padding: 1rem; position: sticky; top: 74px;">
        <h2 style="margin: 0 0 .8rem; font-size: 1rem;">Bài viết gợi ý</h2>
        <?php if (!empty($relatedPosts)): ?>
            <div style="display: grid; gap: .7rem;">
                <?php foreach ($relatedPosts as $item): ?>
                    <?php $itemSlug = trim((string)($item['slug'] ?? '')); ?>
                    <?php if ($itemSlug === '') { continue; } ?>
                    <a href="/user/guides/detail?slug=<?= urlencode($itemSlug) ?>" style="display: grid; grid-template-columns: 86px minmax(0, 1fr); gap: .6rem; text-decoration: none; color: inherit; align-items: center;">
                        <img src="<?= htmlspecialchars($extractThumb($item)) ?>" alt="<?= htmlspecialchars($item['title'] ?? 'Bài viết') ?>" style="width: 86px; height: 56px; object-fit: cover; border-radius: 7px; border: 1px solid var(--glass-border);">
                        <span style="font-size: .82rem; line-height: 1.45; color: var(--ios-text); display: -webkit-box; line-clamp: 2; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= htmlspecialchars($item['title'] ?? 'Bài viết') ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="margin: 0; color: var(--ios-text-secondary); font-size: .85rem;">Chưa có bài gợi ý khác.</p>
        <?php endif; ?>
    </aside>
</section>

<style>
.post-content img,
.post-content video,
.post-content iframe,
.post-content table {
    max-width: 100% !important;
}

.post-content img,
.post-content video {
    height: auto !important;
    border-radius: 10px;
}

@media (max-width: 920px) {
    section[style*="grid-template-columns: minmax(0, 1fr) 320px"] {
        grid-template-columns: 1fr !important;
    }

    section[style*="grid-template-columns: minmax(0, 1fr) 320px"] aside {
        position: static !important;
    }
}
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
