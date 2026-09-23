<?php
$pageTitle = 'Hướng dẫn sử dụng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'admin';
$extraJs = 'app';
ob_start();
?>

<section class="glass-card" style="padding: 1.5rem;">
    <header style="margin-bottom: 1.5rem;">
        <p style="margin: 0 0 .35rem; color: var(--color-primary); font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;">Trung tâm hỗ trợ</p>
        <h1 style="margin: 0; font-size: 1.6rem;">Hướng dẫn sử dụng VPN</h1>
        <p style="margin: .5rem 0 0; color: var(--ios-text-secondary);">Xem hướng dẫn cài đặt và sử dụng dịch vụ từ khu vực tài khoản của bạn.</p>
    </header>

    <?php if (!empty($posts)): ?>
        <div style="display: grid; gap: .8rem;">
            <?php foreach ($posts as $post): ?>
                <article style="padding: 1rem; border: 1px solid var(--glass-border); border-radius: 10px;">
                    <h2 style="margin: 0 0 .45rem; font-size: 1.05rem;">
                        <a href="/user/guides/detail?slug=<?= urlencode($post['slug'] ?? '') ?>" style="color: var(--ios-blue); text-decoration: none;">
                            <?= htmlspecialchars($post['title'] ?? 'Hướng dẫn') ?>
                        </a>
                    </h2>
                    <p style="margin: 0; color: var(--ios-text-secondary); font-size: .9rem; line-height: 1.6;">
                        <?= htmlspecialchars(mb_strimwidth(trim(strip_tags($post['content'] ?? '')), 0, 180, '...')) ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="margin: 0; color: var(--ios-text-secondary);">Chưa có hướng dẫn nào được xuất bản.</p>
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
