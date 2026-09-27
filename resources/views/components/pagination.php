<?php
/**
 * Partial phân trang dùng chung cho các danh sách khu vực người dùng.
 *
 * Cách dùng 1 (khuyến nghị): truyền $pagination = ['page' => ..., 'pages' => ..., 'total' => ...]
 * Cách dùng 2 (tương thích cũ): $currentPage, $totalPages, $baseUrl
 *
 * URL mặc định được dựng lại từ REQUEST_URI hiện tại (giữ query string,
 * chỉ thay tham số ?page=). Nếu truyền $baseUrl thì dùng $baseUrl . $page.
 * Không render gì khi tổng số trang <= 1.
 */

$pgPage  = (int) ($pagination['page'] ?? ($currentPage ?? 1));
$pgPages = (int) ($pagination['pages'] ?? ($totalPages ?? 1));
$pgTotal = (int) ($pagination['total'] ?? 0);

if ($pgPages <= 1) {
    return;
}

// Dựng URL: tự nhận từ REQUEST_URI, hoặc dùng $baseUrl nếu được truyền.
if (isset($baseUrl) && $baseUrl !== '') {
    $pgUrl = static function (int $page) use ($baseUrl): string {
        return $baseUrl . $page;
    };
} else {
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
    $baseUri = preg_replace('/([?&])page=\d+/', '', $currentUri);
    $baseUri = preg_replace('/\?(?:&|$)/', '?', $baseUri);
    if (substr($baseUri, -1) === '?') {
        $baseUri = substr($baseUri, 0, -1);
    }
    $separator = strpos($baseUri, '?') === false ? '?' : '&';
    $pgUrl = static function (int $page) use ($baseUri, $separator): string {
        return $baseUri . $separator . 'page=' . $page;
    };
}

// Danh sách số trang hiển thị: 1 … gần-trang-hiện-tại … pages
$pgNumbers = [];
if ($pgPages <= 7) {
    $pgNumbers = range(1, $pgPages);
} else {
    $pgNumbers = [1];
    $pgStart = max(2, $pgPage - 1);
    $pgEnd = min($pgPages - 1, $pgPage + 1);
    if ($pgStart > 2) {
        $pgNumbers[] = '…';
    }
    for ($i = $pgStart; $i <= $pgEnd; $i++) {
        $pgNumbers[] = $i;
    }
    if ($pgEnd < $pgPages - 1) {
        $pgNumbers[] = '…';
    }
    $pgNumbers[] = $pgPages;
}
?>
<nav class="u-pagination" aria-label="Phân trang">
    <?php if ($pgPage > 1): ?>
        <a class="u-pagination-btn" href="<?= htmlspecialchars($pgUrl($pgPage - 1)) ?>" rel="prev">‹ Trước</a>
    <?php endif; ?>

    <?php foreach ($pgNumbers as $pgNum): ?>
        <?php if ($pgNum === '…'): ?>
            <span class="u-pagination-ellipsis">…</span>
        <?php elseif ($pgNum === $pgPage): ?>
            <span class="u-pagination-btn is-active" aria-current="page"><?= $pgNum ?></span>
        <?php else: ?>
            <a class="u-pagination-btn" href="<?= htmlspecialchars($pgUrl((int) $pgNum)) ?>"><?= $pgNum ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($pgPage < $pgPages): ?>
        <a class="u-pagination-btn" href="<?= htmlspecialchars($pgUrl($pgPage + 1)) ?>" rel="next">Sau ›</a>
    <?php endif; ?>

    <?php if ($pgTotal > 0): ?>
        <span class="u-pagination-summary">Trang <?= $pgPage ?>/<?= $pgPages ?> · <?= number_format($pgTotal) ?> mục</span>
    <?php endif; ?>
</nav>