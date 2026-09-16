<?php
$currentPage = (int)($currentPage ?? 1);
$totalPages = (int)($totalPages ?? 1);
$baseUrl = $baseUrl ?? '?page=';

if ($totalPages > 1):
?>
<div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap;">
    <?php if ($currentPage > 1): ?>
        <a href="<?= $baseUrl . ($currentPage - 1) ?>" class="glass-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(255, 255, 255, 0.2); color: var(--ios-text);">&laquo; Trước</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="<?= $baseUrl . $i ?>" class="glass-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; <?= $i === $currentPage ? 'background: var(--ios-blue); color: #fff;' : 'background: rgba(255, 255, 255, 0.15); color: var(--ios-text); border: 1px solid var(--glass-border);' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="<?= $baseUrl . ($currentPage + 1) ?>" class="glass-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(255, 255, 255, 0.2); color: var(--ios-text);">Sau &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>