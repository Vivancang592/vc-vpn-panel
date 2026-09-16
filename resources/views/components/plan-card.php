<?php
$plan = $plan ?? [];
?>
<div class="glass-card" style="display: flex; flex-direction: column; justify-content: space-between; gap: 1.25rem;">
    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--ios-text);"><?= htmlspecialchars($plan['name'] ?? 'Tên Gói') ?></h3>
            <?php if (!empty($plan['is_popular'])): ?>
                <span style="background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); border: 1px solid rgba(0, 122, 255, 0.3);">Phổ biến</span>
            <?php endif; ?>
        </div>
        
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--ios-blue); margin-bottom: 1rem;">
            ¥<?= number_format($plan['price'] ?? 0, 2, '.', ',') ?> <span style="font-size: 0.85rem; font-weight: 400; color: var(--ios-text-secondary);">CNY / <?= $plan['duration_days'] ?? 30 ?> ngày</span>
        </div>

        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.88rem;">
            <li style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="color: var(--ios-success);">✓</span> Dung lượng: <strong><?= isset($plan['bandwidth_limit_gb']) && $plan['bandwidth_limit_gb'] > 0 ? $plan['bandwidth_limit_gb'] . ' GB' : 'Không giới hạn' ?></strong>
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="color: var(--ios-success);">✓</span> Thiết bị tối đa: <strong><?= $plan['max_devices'] ?? 1 ?> thiết bị</strong>
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="color: var(--ios-success);">✓</span> Băng thông tốc độ cao 1Gbps
            </li>
        </ul>
    </div>

    <a href="/user/plans/checkout?id=<?= $plan['id'] ?? 0 ?>" class="glass-btn" style="width: 100%; text-align: center; text-decoration: none;">
        Chọn Gói Này
    </a>
</div>