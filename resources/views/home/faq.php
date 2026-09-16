<?php
$pageTitle = "Hướng Dẫn & Câu Hỏi Thường Gặp - " . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'home';

// Nhóm các bài viết theo Thể Loại (type)
$groupedPosts = [];
$search = trim($_GET['q'] ?? '');
if (!empty($posts) && is_array($posts)) {
    foreach ($posts as $post) {
        if ($search !== '' && stripos(($post['title'] ?? '') . ' ' . strip_tags($post['content'] ?? ''), $search) === false) {
            continue;
        }
        $type = !empty($post['type']) ? strtoupper($post['type']) : 'HƯỚNG DẪN CHUNG';
        $groupedPosts[$type][] = $post;
    }
}

function getTutorialFallbackThumbnail($postId): string
{
    $colorPairs = [
        ['#1677ff', '#0b4c8c'],
        ['#22a66f', '#087f9a'],
        ['#e59a21', '#d1495b'],
        ['#7a5af8', '#c24186'],
        ['#0f766e', '#1677ff']
    ];
    $pair = $colorPairs[(int) $postId % count($colorPairs)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="500" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMid slice">'
         . '<defs><linearGradient id="tutorial' . $postId . '" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="' . $pair[0] . '"/><stop offset="100%" stop-color="' . $pair[1] . '"/>'
         . '</linearGradient></defs><rect width="100%" height="100%" fill="url(#tutorial' . $postId . ')"/>'
         . '<path d="M0 390 C260 320 390 520 660 405 S970 280 1200 365 V500 H0Z" fill="#ffffff" fill-opacity=".12"/>'
         . '<text x="72" y="100" fill="#ffffff" font-family="sans-serif" font-size="34" font-weight="700">HƯỚNG DẪN</text>'
         . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

ob_start();
?>

<div style="text-align: center; margin-bottom: 2.5rem;">
    <h1 style="font-size: 2rem; font-weight: 700;">Hướng Dẫn & Câu Hỏi Thường Gặp</h1>
    <p style="color: var(--ios-text-secondary); margin-top: 0.5rem;">Giải đáp các thắc mắc và hướng dẫn chi tiết cách sử dụng dịch vụ VPN</p>
    <form action="/faq" method="get" style="max-width: 560px; margin: 1.25rem auto 0; display: flex; gap: .5rem;">
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm hướng dẫn..." style="flex: 1; padding: .7rem .9rem; border: 1px solid var(--glass-border); border-radius: 10px; background: var(--glass-bg); color: var(--ios-text);">
        <button type="submit" class="glass-btn" style="padding: .7rem 1rem;">Tìm kiếm</button>
    </form>
</div>

<!-- Đã bỏ max-width: 900px, chuyển sang rộng linh hoạt toàn màn hình (width: 100%) -->
<div style="width: 100%; margin: 0 auto;">
    <?php if (!empty($groupedPosts)): ?>
        <?php 
        $cardIndex = 0;
        foreach ($groupedPosts as $typeGroup => $groupPosts): 
        ?>
            <section style="margin-bottom: 2.5rem;">
                <h2 class="faq-group-header">
                    <span>📌</span> <?= htmlspecialchars($typeGroup) ?>
                </h2>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($groupPosts as $itemIndex => $post): ?>
                        <?php
                        $cardIndex++;
                        $animationDelay = number_format($cardIndex * 0.08, 2);
                        $rawContent = htmlspecialchars_decode($post['content'] ?? '');
                        $thumbnailUrl = '';
                        if (preg_match('/<!--thumbnail:(.*?)-->/i', $rawContent, $matches)) {
                            $thumbnailUrl = trim($matches[1]);
                        } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawContent, $matches)) {
                            $thumbnailUrl = trim($matches[1]);
                        }
                        if ($thumbnailUrl === '') {
                            $thumbnailUrl = getTutorialFallbackThumbnail($post['id'] ?? $cardIndex);
                        }
                        $summary = preg_replace('/<!--thumbnail:.*?-->/i', '', $rawContent);
                        ?>
                        <div class="glass-card faq-card" style="animation-delay: <?= $animationDelay ?>s;">
                            <img src="<?= htmlspecialchars($thumbnailUrl) ?>" alt="<?= htmlspecialchars($post['title'] ?? '') ?>" class="faq-card-thumbnail">
                            <div class="faq-card-body">
                                <div>
                                    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--ios-blue);">
                                        <a href="/post-detail?slug=<?= urlencode($post['slug']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= ($itemIndex + 1) ?>. <?= htmlspecialchars($post['title']) ?>
                                        </a>
                                    </h3>
                                    <p style="color: var(--ios-text-secondary); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5; display: -webkit-box; line-clamp: 2; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= htmlspecialchars(strip_tags($summary)) ?>
                                    </p>
                                </div>
                                <div style="display: flex; gap: 1rem; font-size: 0.78rem; color: var(--ios-text-secondary); border-top: 1px solid var(--glass-border); padding-top: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap;">
                                    <span>📅 Cập nhật: <?= date('d/m/Y', strtotime($post['created_at'])) ?></span>
                                    <span>🏷️ Chuyên mục: <?= htmlspecialchars($typeGroup) ?></span>
                                </div>
                                <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                                    <a href="/post-detail?slug=<?= urlencode($post['slug']) ?>" class="glass-btn" style="font-size: 0.85rem; padding: 0.5rem 1rem; text-decoration: none; box-sizing: border-box;">
                                        Xem Chi Tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="glass-card" style="text-align: center; padding: 3rem; color: var(--ios-text-secondary);">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📑</div>
            <p style="font-size: 1rem; margin-bottom: 0;">Hiện chưa có bài viết hướng dẫn nào được xuất bản.</p>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>