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
            <?php foreach ($posts as $post): ?>
                <?php $slug = trim((string)($post['slug'] ?? '')); ?>
                <article class="glass-card" style="padding: 1rem; border: 1px solid var(--glass-border); border-radius: 10px;">
                    <h2 style="margin: 0 0 .45rem; font-size: 1.05rem;">
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
