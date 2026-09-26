<?php
$pageTitle = htmlspecialchars($post['title'] ?? 'Hướng dẫn') . ' - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'admin';
$extraJs = 'app';
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
    $title = htmlspecialchars((string)($item['title'] ?? 'HUONG DAN'), ENT_QUOTES, 'UTF-8');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540" viewBox="0 0 960 540" preserveAspectRatio="xMidYMid slice">'
         . '<defs><linearGradient id="relguide' . $index . '" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="' . $pair[0] . '"/><stop offset="100%" stop-color="' . $pair[1] . '"/>'
         . '</linearGradient></defs>'
         . '<rect width="100%" height="100%" fill="url(#relguide' . $index . ')"/>'
         . '<path d="M0 430 C220 340 360 530 560 440 S860 340 960 400 V540 H0Z" fill="#fff" fill-opacity=".12"/>'
         . '<text x="56" y="94" fill="#ffffff" font-family="sans-serif" font-size="30" font-weight="700">HUONG DAN</text>'
         . '<text x="56" y="146" fill="#ffffff" font-family="sans-serif" font-size="22" font-weight="500">' . $title . '</text>'
         . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
};

ob_start();
?>

<section class="user-record-page">
    <header class="user-orders-header">
        <div class="user-orders-intro">
            <h2 class="u-plans-title">CHI TIẾT HƯỚNG DẪN</h2>
            <p style="margin: 0;">Đọc chi tiết nội dung bài viết.</p>
        </div>
    </header>

    <div class="u-content-detail">
    <article class="u-content-main" style="min-width: 0; padding: .25rem 0 1rem;">
        <header style="display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <h1 style="margin: 0; font-size: clamp(1.45rem, 3vw, 2rem); line-height: 1.3;"><?= htmlspecialchars($post['title'] ?? 'Hướng dẫn') ?></h1>
            <span style="white-space: nowrap; color: var(--ios-text-secondary); font-size: .82rem; padding-bottom: .2rem;">
                <?= !empty($post['created_at']) ? date('d/m/Y', strtotime($post['created_at'])) : 'N/A' ?>
            </span>
        </header>
        <hr style="border: none; border-top: 1px solid var(--glass-border); margin: 1rem 0 1.4rem;">
        <div class="post-content" style="color: var(--ios-text); line-height: 1.75; overflow-wrap: anywhere; word-break: break-word;">
            <?= $post['content'] ?? '' ?>
        </div>
    </article>

    <aside class="u-content-aside" style="padding: 0 0 0 1.25rem; position: sticky; top: 74px;">
        <h2 style="margin: 0 0 .8rem; font-size: 1rem;">Bài viết gợi ý</h2>
        <?php if (!empty($relatedPosts)): ?>
            <div style="display: grid; gap: .7rem;">
                <?php foreach ($relatedPosts as $ridx => $item): ?>
                    <?php $itemSlug = trim((string)($item['slug'] ?? '')); ?>
                    <?php if ($itemSlug === '') { continue; } ?>
                    <a href="/user/guides/detail?slug=<?= urlencode($itemSlug) ?>" style="display: grid; grid-template-columns: 86px minmax(0, 1fr); gap: .6rem; text-decoration: none; color: inherit; align-items: center;">
                        <img src="<?= htmlspecialchars($extractThumb($item, (int)$ridx)) ?>" alt="<?= htmlspecialchars($item['title'] ?? 'Bài viết') ?>" style="width: 86px; height: 56px; object-fit: cover; border-radius: 7px; border: 1px solid var(--glass-border);">
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
    </div>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
