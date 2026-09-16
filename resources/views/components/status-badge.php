<?php
$status = strtolower($status ?? 'inactive');
$label = $label ?? ucfirst($status);

$badgeStyles = [
    'active'     => ['bg' => 'rgba(52, 199, 89, 0.15)', 'color' => 'var(--ios-success)', 'border' => 'rgba(52, 199, 89, 0.3)'],
    'completed'  => ['bg' => 'rgba(52, 199, 89, 0.15)', 'color' => 'var(--ios-success)', 'border' => 'rgba(52, 199, 89, 0.3)'],
    'success'    => ['bg' => 'rgba(52, 199, 89, 0.15)', 'color' => 'var(--ios-success)', 'border' => 'rgba(52, 199, 89, 0.3)'],
    'approved'   => ['bg' => 'rgba(52, 199, 89, 0.15)', 'color' => 'var(--ios-success)', 'border' => 'rgba(52, 199, 89, 0.3)'],
    
    'pending'    => ['bg' => 'rgba(255, 149, 0, 0.15)', 'color' => 'var(--ios-warning)', 'border' => 'rgba(255, 149, 0, 0.3)'],
    'in_progress'=> ['bg' => 'rgba(255, 149, 0, 0.15)', 'color' => 'var(--ios-warning)', 'border' => 'rgba(255, 149, 0, 0.3)'],
    'maintenance'=> ['bg' => 'rgba(255, 149, 0, 0.15)', 'color' => 'var(--ios-warning)', 'border' => 'rgba(255, 149, 0, 0.3)'],

    'inactive'   => ['bg' => 'rgba(142, 142, 147, 0.15)', 'color' => 'var(--ios-text-secondary)', 'border' => 'rgba(142, 142, 147, 0.3)'],
    'expired'    => ['bg' => 'rgba(142, 142, 147, 0.15)', 'color' => 'var(--ios-text-secondary)', 'border' => 'rgba(142, 142, 147, 0.3)'],
    'closed'     => ['bg' => 'rgba(142, 142, 147, 0.15)', 'color' => 'var(--ios-text-secondary)', 'border' => 'rgba(142, 142, 147, 0.3)'],

    'banned'     => ['bg' => 'rgba(255, 59, 48, 0.15)', 'color' => 'var(--ios-danger)', 'border' => 'rgba(255, 59, 48, 0.3)'],
    'failed'     => ['bg' => 'rgba(255, 59, 48, 0.15)', 'color' => 'var(--ios-danger)', 'border' => 'rgba(255, 59, 48, 0.3)'],
    'cancelled'  => ['bg' => 'rgba(255, 59, 48, 0.15)', 'color' => 'var(--ios-danger)', 'border' => 'rgba(255, 59, 48, 0.3)'],
    'rejected'   => ['bg' => 'rgba(255, 59, 48, 0.15)', 'color' => 'var(--ios-danger)', 'border' => 'rgba(255, 59, 48, 0.3)'],
    'offline'    => ['bg' => 'rgba(255, 59, 48, 0.15)', 'color' => 'var(--ios-danger)', 'border' => 'rgba(255, 59, 48, 0.3)'],
];

$style = $badgeStyles[$status] ?? ['bg' => 'rgba(0, 122, 255, 0.15)', 'color' => 'var(--ios-blue)', 'border' => 'rgba(0, 122, 255, 0.3)'];
?>
<span style="display: inline-flex; align-items: center; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>; border: 1px solid <?= $style['border'] ?>; white-space: nowrap;">
    <?= htmlspecialchars($label) ?>
</span>