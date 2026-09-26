<?php
$pageTitle = 'Gói Đã Mua - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$userSubscriptions = isset($subscriptions) && is_array($subscriptions) ? $subscriptions : [];
$statusLabels = ['active' => 'Đang hoạt động', 'expired' => 'Hết hạn', 'suspended' => 'Tạm dừng', 'cancelled' => 'Đã hủy'];
$activeCount = count(array_filter($userSubscriptions, static fn($subscription) => ($subscription['status'] ?? '') === 'active' && strtotime($subscription['end_date'] ?? '') >= time()));
ob_start();
?>

<section class="user-subscriptions-page">
	<header class="user-subscriptions-header">
		<div class="user-subscriptions-intro">
			<h2 class="u-plans-title">DỊCH VỤ VPN</h2>
			<p>Quản lý dung lượng, thời hạn và cấu hình kết nối của bạn.</p>
		</div>
		<div class="user-subscriptions-title-row">
			<h1><span class="user-subscription-title-icon" aria-hidden="true">&#128737;</span>Gói đã mua <span class="user-subscriptions-count"><?= count($userSubscriptions) ?></span></h1>
			<a href="/user/plans" class="glass-btn user-subscriptions-buy-link">Mua gói dịch vụ</a>
		</div>
	</header>

	<?php if (!empty($_SESSION['error'])): ?>
		<div class="user-subscriptions-alert" role="alert"><?= htmlspecialchars($_SESSION['error']) ?></div>
		<?php unset($_SESSION['error']); ?>
	<?php endif; ?>

	<?php if (!empty($userSubscriptions)): ?>
		<div class="user-subscriptions-grid">
			<?php foreach ($userSubscriptions as $subscription): ?>
				<?php
				$status = $subscription['status'] ?? 'expired';
				$usedBytes = (float) ($subscription['upload'] ?? 0) + (float) ($subscription['download'] ?? 0);
				$limitBytes = (float) ($subscription['transfer_enable'] ?? 0);
				$usagePercent = $limitBytes > 0 ? min(100, ($usedBytes / $limitBytes) * 100) : 0;
				$isActive = $status === 'active' && strtotime($subscription['end_date'] ?? '') >= time();
				$displayStatus = $isActive ? 'active' : $status;
				?>
				<article class="glass-card user-subscription-card user-subscription-card-status-<?= htmlspecialchars($displayStatus) ?>">
					<div class="user-subscription-card-top">
						<div>
							<p class="user-subscription-code"><?= htmlspecialchars($subscription['plan_code'] ?? ('GÓI #' . ($subscription['plan_id'] ?? ''))) ?></p>
							<h2><?= htmlspecialchars($subscription['plan_name'] ?? ('Gói dịch vụ #' . ($subscription['plan_id'] ?? ''))) ?></h2>
						</div>
						<span class="user-subscription-status user-subscription-status-<?= htmlspecialchars($displayStatus) ?>"><?= htmlspecialchars($statusLabels[$displayStatus] ?? ucfirst($status)) ?></span>
					</div>
					<div class="user-subscription-usage">
						<div><span>Lưu lượng đã dùng</span><strong><?= number_format($usedBytes / 1073741824, 2) ?> GB<?= $limitBytes > 0 ? ' / ' . number_format($limitBytes / 1073741824, 2) . ' GB' : ' / Không giới hạn' ?></strong></div>
						<div class="user-subscription-progress" aria-label="Đã sử dụng <?= round($usagePercent) ?>%"><span style="width: <?= $usagePercent ?>%"></span></div>
					</div>
					<div class="user-subscription-meta"><span>Hết hạn</span><strong><?= !empty($subscription['end_date']) ? date('d/m/Y', strtotime($subscription['end_date'])) : '-' ?></strong></div>
					<div class="user-subscription-actions">
						<a href="/subscriptions/detail?id=<?= (int) ($subscription['id'] ?? 0) ?>" class="user-subscription-detail-button">Chi tiết</a>
						<?php if ($status !== 'cancelled'): ?>
							<a href="/checkout?type=renewal&amp;subscription=<?= (int) ($subscription['id'] ?? 0) ?>" class="user-subscription-detail-button">Gia hạn</a>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<div class="glass-card user-subscriptions-empty">
			<h2>Bạn chưa có gói dịch vụ</h2>
			<p>Chọn một gói VPN phù hợp để bắt đầu kết nối an toàn.</p>
			<a href="/user/plans" class="glass-btn">Xem gói dịch vụ</a>
		</div>
	<?php endif; ?>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>
