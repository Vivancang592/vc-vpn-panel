<?php
$type = $type ?? 'info';
$message = $message ?? '';

$colors = [
    'success' => ['bg' => 'rgba(52, 199, 89, 0.15)', 'border' => 'rgba(52, 199, 89, 0.3)', 'text' => 'var(--ios-success)'],
    'danger'  => ['bg' => 'rgba(255, 59, 48, 0.15)', 'border' => 'rgba(255, 59, 48, 0.3)', 'text' => 'var(--ios-danger)'],
    'warning' => ['bg' => 'rgba(255, 149, 0, 0.15)', 'border' => 'rgba(255, 149, 0, 0.3)', 'text' => 'var(--ios-warning)'],
    'info'    => ['bg' => 'rgba(0, 122, 255, 0.15)', 'border' => 'rgba(0, 122, 255, 0.3)', 'text' => 'var(--ios-blue)'],
];

$style = $colors[$type] ?? $colors['info'];
?>
<?php if (!empty($message)): ?>
    <div style="background: <?= $style['bg'] ?>; color: <?= $style['text'] ?>; border: 1px solid <?= $style['border'] ?>; padding: 0.85rem 1.25rem; border-radius: var(--radius-md); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); font-size: 0.9rem; font-weight: 500; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
        <span><?= htmlspecialchars($message) ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 1.1rem; cursor: pointer; line-height: 1;">&times;</button>
    </div>
<?php endif; ?>