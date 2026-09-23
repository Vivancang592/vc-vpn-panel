<?php
$pageTitle = htmlspecialchars($post['title'] ?? 'Hướng dẫn') . ' - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'admin';
$extraJs = 'app';
ob_start();
?>

<article class="glass-card" style="padding: clamp(1.25rem, 4vw, 2.25rem);">
    <a href="/user/guides" style="display: inline-block; margin-bottom: 1rem; color: var(--ios-blue); font-size: .9rem; font-weight: 600; text-decoration: none;">&larr; Quay lại hướng dẫn</a>
    <p style="margin: 0 0 .4rem; color: var(--ios-text-secondary); font-size: .8rem;">Cập nhật: <?= !empty($post['created_at']) ? date('d/m/Y', strtotime($post['created_at'])) : 'N/A' ?></p>
    <h1 style="margin: 0 0 1.5rem; font-size: clamp(1.45rem, 3vw, 2rem);"><?= htmlspecialchars($post['title'] ?? 'Hướng dẫn') ?></h1>
    <div class="post-content" style="color: var(--ios-text); line-height: 1.75; overflow-wrap: anywhere;">
        <?= $post['content'] ?? '' ?>
    </div>
</article>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
