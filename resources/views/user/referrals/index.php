<?php
$pageTitle = 'Giới thiệu - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$items = is_array($commissions ?? null) ? $commissions : [];
$refCode = (string) ($user['ref_code'] ?? '');
ob_start();
?>
<section class="user-record-page">
	<header class="user-record-header"><div><h1>Giới thiệu</h1><p>Chia sẻ mã giới thiệu và theo dõi hoa hồng của bạn.</p></div></header>
	<div class="glass-card user-referral-code"><span>Mã giới thiệu</span><strong><?= htmlspecialchars($refCode ?: 'Chưa có mã') ?></strong><?php if ($refCode !== ''): ?><button type="button" class="glass-btn" data-copy-value="<?= htmlspecialchars($refCode) ?>">Sao chép</button><?php endif; ?></div>
	<div class="user-record-stats"><div class="glass-card"><span>Hoa hồng khả dụng</span><strong><?= $formatMoney($user['commission_balance'] ?? 0) ?></strong></div><div class="glass-card"><span>Đơn có hoa hồng</span><strong><?= count($items) ?></strong></div></div>
	<?php if ($items): ?><div class="glass-card user-record-table-wrap"><table class="user-record-table"><thead><tr><th>Người được giới thiệu</th><th>Đơn hàng</th><th>Tỷ lệ</th><th>Hoa hồng</th><th>Thời gian</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td data-label="Người được giới thiệu"><?= htmlspecialchars($item['referred_username'] ?? '-') ?></td><td data-label="Đơn hàng"><code><?= htmlspecialchars($item['order_code'] ?? '-') ?></code></td><td data-label="Tỷ lệ"><?= (float) ($item['commission_rate'] ?? 0) ?>%</td><td data-label="Hoa hồng" class="user-record-amount">+<?= $formatMoney($item['commission_amount'] ?? 0) ?></td><td data-label="Thời gian"><?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="glass-card user-record-empty"><h2>Chưa có hoa hồng</h2><p>Hoa hồng phát sinh từ đơn hàng thành công sẽ hiển thị tại đây.</p></div><?php endif; ?>
</section>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>
