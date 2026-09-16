<?php
$sub = $subscription ?? [];
$upload = (int)($sub['upload'] ?? 0);
$download = (int)($sub['download'] ?? 0);
$totalUsed = $upload + $download;
$transferEnable = (int)($sub['transfer_enable'] ?? 0);

$usedGb = round($totalUsed / (1024 * 1024 * 1024), 2);
$totalGb = round($transferEnable / (1024 * 1024 * 1024), 2);

$percentage = ($transferEnable > 0) ? min(100, round(($totalUsed / $transferEnable) * 100, 1)) : 0;
$subUrl = ($baseUrl ?? 'https://domain.com') . '/api/v1/client/subscribe?token=' . ($sub['sub_token'] ?? '');
?>
<div class="glass-card" style="display: flex; flex-direction: column; gap: 1.25rem;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--ios-text);"><?= htmlspecialchars($sub['plan_name'] ?? 'Gói VPN') ?></h3>
            <p style="font-size: 0.8rem; color: var(--ios-text-secondary); margin-top: 0.2rem;">Hạn dùng: <?= htmlspecialchars($sub['end_date'] ?? 'N/A') ?></p>
        </div>
        <div>
            <?php 
            $status = $sub['status'] ?? 'active';
            $label = $status === 'active' ? 'Đang hoạt động' : ($status === 'expired' ? 'Hết hạn' : 'Đã khóa');
            require __DIR__ . '/status-badge.php'; 
            ?>
        </div>
    </div>

    <!-- Bandwidth Progress -->
    <div>
        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--ios-text-secondary);">
            <span>Dung lượng đã dùng</span>
            <span><strong><?= $usedGb ?> GB</strong> / <?= $totalGb > 0 ? $totalGb . ' GB' : '∞' ?></span>
        </div>
        <div style="width: 100%; height: 8px; background: rgba(0, 0, 0, 0.1); border-radius: 999px; overflow: hidden;">
            <div style="width: <?= $percentage ?>%; height: 100%; background: <?= $percentage > 85 ? 'var(--ios-danger)' : 'var(--ios-blue)' ?>; border-radius: 999px; transition: width 0.3s ease;"></div>
        </div>
    </div>

    <!-- Subscription Link -->
    <div style="display: flex; flex-direction: column; gap: 0.4rem;">
        <label style="font-size: 0.8rem; font-weight: 500; color: var(--ios-text-secondary);">Liên kết đăng ký (Subscription URL)</label>
        <div style="display: flex; gap: 0.5rem;">
            <input type="text" readonly value="<?= htmlspecialchars($subUrl) ?>" class="glass-input" style="font-size: 0.8rem; padding: 0.5rem 0.75rem;" id="sub-url-<?= $sub['id'] ?? 0 ?>">
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('sub-url-<?= $sub['id'] ?? 0 ?>').value); alert('Đã sao chép liên kết!');" class="glass-btn" style="padding: 0.5rem 0.8rem; font-size: 0.8rem; white-space: nowrap;">
                Sao Chép
            </button>
        </div>
    </div>
</div>