<?php
$pageTitle = 'Gói Dịch Vụ - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$availablePlans = isset($plans) && is_array($plans) ? $plans : [];
$activeServerGroups = isset($serverGroups) && is_array($serverGroups) ? $serverGroups : [];
ob_start();
?>

<section class="user-plans-page">
	<header class="user-plans-header">
		<h2 style="color: #020af4; font-family: emoji;">CHỌN GÓI DỊCH VỤ PHÙ HỢP VỚI BẠN</h2>
		<p>Kết nối ổn định, bảo mật và linh hoạt trên mọi thiết bị.</p>
	</header>

	<?php if (!empty($_SESSION['error'])): ?>
		<div class="user-plans-alert" role="alert"><?= htmlspecialchars($_SESSION['error']) ?></div>
		<?php unset($_SESSION['error']); ?>
	<?php endif; ?>

	<?php if (!empty($availablePlans)): ?>
		<div class="user-plan-group-tabs" role="tablist" aria-label="Nhóm máy chủ">
			<button type="button" class="user-plan-group-tab is-active" data-plan-group-filter="all" role="tab" aria-selected="true">Tất cả</button>
			<?php foreach ($activeServerGroups as $group): ?>
				<button type="button" class="user-plan-group-tab" data-plan-group-filter="<?= (int) ($group['id'] ?? 0) ?>" role="tab" aria-selected="false"><?= htmlspecialchars($group['name'] ?? 'Nhóm máy chủ') ?></button>
			<?php endforeach; ?>
		</div>

		<div class="user-plans-grid" data-plan-grid>
			<?php foreach ($availablePlans as $plan): ?>
				<?php
				$planId = (int) ($plan['id'] ?? 0);
				$bandwidth = (int) ($plan['bandwidth_limit_gb'] ?? 0);
				$duration = max(1, (int) ($plan['duration_days'] ?? 30));
				$devices = max(1, (int) ($plan['max_devices'] ?? 1));
				$groupIds = isset($plan['group_ids']) && is_array($plan['group_ids']) ? array_map('intval', $plan['group_ids']) : [];
				?>
				<article class="glass-card user-plan-card" data-plan-group-ids="<?= htmlspecialchars(implode(',', $groupIds)) ?>">
					<div class="user-plan-card-heading">
						<div class="user-plan-name">
							<span class="user-plan-title-icon" aria-hidden="true">&#128722;</span>
							<h2><?= htmlspecialchars($plan['name'] ?? 'Gói VPN') ?></h2>
						</div>
						<div class="user-plan-price-duration"><strong>¥<?= number_format((float) ($plan['price'] ?? 0), 2, '.', ',') ?></strong><span>/</span><span><?= $duration ?> ngày</span></div>
					</div>
					<dl class="user-plan-features user-plan-primary-features">
						<div><dt>Dung lượng</dt><span>:</span><dd><?= $bandwidth > 0 ? number_format($bandwidth) . ' GB' : 'Không giới hạn' ?></dd></div>
						<div><dt>Thiết bị</dt><span>:</span><dd><?= $devices ?> thiết bị</dd></div>
					</dl>

					<?php if (!empty($plan['description'])): ?>
						<p class="user-plan-description"><?= nl2br(htmlspecialchars($plan['description'])) ?></p>
					<?php endif; ?>

					<a href="/checkout?id=<?= $planId ?>" class="user-plan-select-link">Chọn gói này</a>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<div class="glass-card user-plans-empty">
			<h2>Chưa có gói dịch vụ</h2>
			<p>Hiện chưa có gói VPN nào đang mở đăng ký. Vui lòng quay lại sau.</p>
		</div>
	<?php endif; ?>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
