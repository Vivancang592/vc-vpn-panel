<?php
$modalId = $modalId ?? 'modal-' . uniqid();
$title = $title ?? 'Thông báo';
$content = $content ?? '';
?>
<div id="<?= $modalId ?>" class="glass-modal-overlay" style="display: none; position: fixed; inset: 0; z-index: 999; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-card" style="width: 100%; max-width: 500px; background: var(--glass-bg); border-radius: var(--radius-lg); box-shadow: var(--glass-shadow); padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; animation: modalIn 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);">
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.75rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;"><?= htmlspecialchars($title) ?></h3>
            <button type="button" onclick="document.getElementById('<?= $modalId ?>').style.display='none'" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="font-size: 0.95rem; color: var(--ios-text);">
            <?= $content ?>
        </div>
        <?php if (isset($footer)): ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--glass-border); padding-top: 0.75rem;">
                <?= $footer ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
</style>