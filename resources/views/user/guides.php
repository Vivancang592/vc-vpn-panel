<?php
$pageTitle = 'Hướng dẫn sử dụng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'admin';
$extraJs = 'app';

$safeExcerpt = static function (string $html, int $limit = 180): string {
    $plain = trim(strip_tags($html));
    if ($plain === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($plain, 'UTF-8') > $limit
            ? mb_substr($plain, 0, $limit, 'UTF-8') . '...'
            : $plain;
    }

    return strlen($plain) > $limit
        ? substr($plain, 0, $limit) . '...'
        : $plain;
};

$getGuideThumbnail = static function (array $post, int $index = 0): string {
    $rawContent = htmlspecialchars_decode((string)($post['content'] ?? ''));

    if (preg_match('/<!--thumbnail:(.*?)-->/i', $rawContent, $matches)) {
        $thumb = trim((string)$matches[1]);
        if ($thumb !== '') {
            return $thumb;
        }
    }

    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawContent, $matches)) {
        $thumb = trim((string)$matches[1]);
        if ($thumb !== '') {
            return $thumb;
        }
    }

    $colorPairs = [
        ['#0ea5e9', '#0284c7'],
        ['#22c55e', '#16a34a'],
        ['#8b5cf6', '#7c3aed'],
        ['#f59e0b', '#d97706'],
        ['#ec4899', '#db2777']
    ];
    $pair = $colorPairs[$index % count($colorPairs)];
    $title = htmlspecialchars((string)($post['title'] ?? 'HUONG DAN'), ENT_QUOTES, 'UTF-8');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540" viewBox="0 0 960 540" preserveAspectRatio="xMidYMid slice">'
         . '<defs><linearGradient id="guide' . $index . '" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="' . $pair[0] . '"/><stop offset="100%" stop-color="' . $pair[1] . '"/>'
         . '</linearGradient></defs>'
         . '<rect width="100%" height="100%" fill="url(#guide' . $index . ')"/>'
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
            <h2 style="color: #020af4; font-family: emoji;">TRUNG TÂM HƯỚNG DẪN</h2>
            <p style="margin: 0;">Xem nhanh tài liệu cài đặt và sử dụng VPN từ khu vực tài khoản của bạn.</p>
        </div>
        <div class="user-orders-title-row">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">📘 Hướng dẫn sử dụng VPN</h1>
        </div>
    </header>

    <?php if (!empty($posts)): ?>
        <div style="display: grid; gap: .8rem;">
            <?php foreach ($posts as $idx => $post): ?>
                <?php $slug = trim((string)($post['slug'] ?? '')); ?>
                <?php $thumb = $getGuideThumbnail($post, (int)$idx); ?>
                <article class="glass-card" style="padding: .9rem; border: 1px solid var(--glass-border); border-radius: 10px; display: flex; flex-wrap: wrap; gap: .9rem; align-items: start;">
                    <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($post['title'] ?? 'Hướng dẫn') ?>" style="width: 100%; max-width: 180px; flex: 1 1 180px; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 8px; border: 1px solid var(--glass-border);">
                    <div style="min-width: 0; flex: 2 1 260px;">
                        <h2 style="margin: 0 0 .45rem; font-size: 1.05rem; line-height: 1.35;">
                            <a href="<?= $slug !== '' ? '/user/guides/detail?slug=' . urlencode($slug) : '#' ?>" style="color: var(--ios-blue); text-decoration: none;">
                                <?= htmlspecialchars($post['title'] ?? 'Hướng dẫn') ?>
                            </a>
                        </h2>
                        <p style="margin: 0; color: var(--ios-text-secondary); font-size: .9rem; line-height: 1.6;">
                            <?= htmlspecialchars($safeExcerpt((string)($post['content'] ?? ''), 180)) ?>
                        </p>
                        <div style="margin-top: .55rem; font-size: .78rem; color: var(--ios-text-secondary);">
                            <?= !empty($post['created_at']) ? 'Cập nhật: ' . date('d/m/Y', strtotime((string)$post['created_at'])) : '' ?>
                        </div>
                        <div style="margin-top: .65rem; display: flex; justify-content: flex-end;">
                            <a href="<?= $slug !== '' ? '/user/guides/detail?slug=' . urlencode($slug) : '#' ?>" class="glass-btn" style="text-decoration: none; padding: .45rem .95rem; font-size: .82rem;">
                                Xem chi tiết
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card user-record-empty" style="text-align: center; padding: 2.5rem 1.25rem;">
            <div style="font-size: 2rem; margin-bottom: .5rem;">📚</div>
            <p style="margin: 0; color: var(--ios-text-secondary);">Chưa có hướng dẫn nào được xuất bản.</p>
        </div>
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../layouts/app.php';
