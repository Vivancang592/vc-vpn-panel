<?php
$server = $server ?? [];
$status = strtolower($server['status'] ?? 'offline');

$statusColors = [
    'active' => 'var(--ios-success)',
    'maintenance' => 'var(--ios-warning)',
    'offline' => 'var(--ios-danger)'
];
$dotColor = $statusColors[$status] ?? 'var(--ios-danger)';
?>
<div class="glass-card" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem;">
    <div style="display: flex; align-items: center; gap: 0.85rem;">
        <div style="position: relative; width: 12px; height: 12px;">
            <span style="position: absolute; width: 12px; height: 12px; border-radius: 50%; background-color: <?= $dotColor ?>;"></span>
            <?php if ($status === 'active'): ?>
                <span style="position: absolute; width: 12px; height: 12px; border-radius: 50%; background-color: <?= $dotColor ?>; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite; opacity: 0.75;"></span>
            <?php endif; ?>
        </div>
        <div>
            <div style="font-weight: 600; font-size: 0.95rem; color: var(--ios-text);">
                <?= htmlspecialchars($server['name'] ?? 'Server') ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                📍 <?= htmlspecialchars($server['location'] ?? 'N/A') ?> (<?= strtoupper(htmlspecialchars($server['country_code'] ?? 'VN')) ?>)
            </div>
        </div>
    </div>

    <div style="text-align: right;">
        <?php 
        $status = $server['status'] ?? 'offline';
        $label = $status === 'active' ? 'Hoạt động' : ($status === 'maintenance' ? 'Bảo trì' : 'Ngoại tuyến');
        require __DIR__ . '/status-badge.php'; 
        ?>
    </div>
</div>

<style>
@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}
</style>