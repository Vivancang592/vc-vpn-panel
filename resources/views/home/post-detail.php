<?php
$pageTitle = htmlspecialchars($post['title'] ?? 'Chi Tiết Bài Viết') . " - " . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'home';
$extraJs = 'home'; // fragment partial-nav cần home.js (reveal .home-reveal)
$relatedPosts = isset($relatedPosts) && is_array($relatedPosts) ? $relatedPosts : [];

$extractThumb = static function (array $item, int $index = 0): string {
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

    $colorPairs = [
        ['#0ea5e9', '#0284c7'],
        ['#22c55e', '#16a34a'],
        ['#8b5cf6', '#7c3aed'],
        ['#f59e0b', '#d97706'],
        ['#ec4899', '#db2777'],
    ];
    $pair = $colorPairs[$index % count($colorPairs)];
    $title = htmlspecialchars((string)($item['title'] ?? 'BAI VIET'), ENT_QUOTES, 'UTF-8');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540" viewBox="0 0 960 540" preserveAspectRatio="xMidYMid slice">'
         . '<defs><linearGradient id="relpost' . $index . '" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="' . $pair[0] . '"/><stop offset="100%" stop-color="' . $pair[1] . '"/>'
         . '</linearGradient></defs>'
         . '<rect width="100%" height="100%" fill="url(#relpost' . $index . ')"/>'
         . '<path d="M0 430 C220 340 360 530 560 440 S860 340 960 400 V540 H0Z" fill="#fff" fill-opacity=".12"/>'
         . '<text x="56" y="94" fill="#ffffff" font-family="sans-serif" font-size="30" font-weight="700">BAI VIET</text>'
         . '<text x="56" y="146" fill="#ffffff" font-family="sans-serif" font-size="22" font-weight="500">' . $title . '</text>'
         . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
};

ob_start();
?>

<div class="public-page home-page home-subpage home-post-page post-detail-wrapper">

    <?php if (!empty($post)): ?>
        <section class="user-record-page">
            <header class="user-orders-header">
                <div class="user-orders-intro">
                    <h2 class="u-plans-title">CHI TIẾT BÀI VIẾT</h2>
                    <p style="margin: 0;">Đọc chi tiết nội dung bài viết.</p>
                </div>
            </header>

            <div class="u-content-detail">
                <article class="u-content-main">
                    <header class="u-content-head">
                        <h1><?= htmlspecialchars($post['title']) ?></h1>
                        <span class="u-content-date"><?= !empty($post['created_at']) ? date('d/m/Y', strtotime($post['created_at'])) : 'N/A' ?></span>
                    </header>
                    <hr>
                    <div class="post-content">
                        <?= $post['content'] ?>
                    </div>
                </article>

                <aside class="u-content-aside">
                    <h2>Bài viết gợi ý</h2>
                    <?php $visibleRelatedPosts = array_slice($relatedPosts, 0, 6); ?>
                    <?php if (!empty($visibleRelatedPosts)): ?>
                        <div class="u-suggest-list">
                            <?php foreach ($visibleRelatedPosts as $ridx => $item): ?>
                                <?php $itemSlug = trim((string)($item['slug'] ?? '')); ?>
                                <?php if ($itemSlug === '') { continue; } ?>
                                <a class="u-suggest-card" href="/post-detail?slug=<?= urlencode($itemSlug) ?>">
                                    <img src="<?= htmlspecialchars($extractThumb($item, (int)$ridx)) ?>" alt="<?= htmlspecialchars($item['title'] ?? 'Bài viết') ?>">
                                    <span><?= htmlspecialchars($item['title'] ?? 'Bài viết') ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="u-suggest-empty">Chưa có bài viết gợi ý.</p>
                    <?php endif; ?>
                </aside>
            </div>
        </section>
    <?php else: ?>
        <div class="glass-card" style="text-align: center; padding: 3rem; color: var(--ios-text-secondary);">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">⚠️</div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Không Tìm Thấy Bài Viết</h2>
            <p style="font-size: 0.9rem; margin-bottom: 1.5rem;">Bài viết này không tồn tại hoặc đã bị ẩn khỏi hệ thống.</p>
            <a href="/faq" class="glass-btn" style="text-decoration: none;">Xem Các Bài Viết Khác</a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>