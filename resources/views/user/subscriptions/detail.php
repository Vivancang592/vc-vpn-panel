<?php
$pageTitle = 'Chi Tiết Gói Dịch Vụ - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$status = $subscription['status'] ?? 'expired';
$statusLabels = ['active' => 'Đang hoạt động', 'expired' => 'Hết hạn', 'suspended' => 'Tạm dừng', 'cancelled' => 'Đã hủy'];
$usedBytes = (float) ($subscription['upload'] ?? 0) + (float) ($subscription['download'] ?? 0);
$limitBytes = (float) ($subscription['transfer_enable'] ?? 0);
$usagePercent = $limitBytes > 0 ? min(100, ($usedBytes / $limitBytes) * 100) : 0;
$formatTraffic = static function (float $bytes): string {
	if ($bytes < 1073741824) {
		return number_format($bytes / 1048576, 2) . ' MB';
	}

	return number_format($bytes / 1073741824, 2) . ' GB';
};
$usageText = $formatTraffic($usedBytes) . ' / ' . ($limitBytes > 0 ? $formatTraffic($limitBytes) : 'Không giới hạn');
$connectionUrl = isset($subscriptionUrl) ? $subscriptionUrl : '';
$inboundLinks = isset($inboundLinks) && is_array($inboundLinks) ? $inboundLinks : [];
$qrCodeDataUri = isset($qrCodeDataUri) ? $qrCodeDataUri : '';
$canConnect = $status === 'active' && !empty($subscription['end_date']) && strtotime($subscription['end_date']) >= time();
ob_start();
?>

<section class="user-subscriptions-page">
	<header class="user-subscriptions-detail-header">
		<h2 style="color: #020af4; font-family: emoji;">CHI TIẾT GÓI DỊCH VỤ</h2>
		<h1 style ="color: #03b0f5"><span>Gói: </span><?= htmlspecialchars($subscription['plan_name'] ?? ('Gói dịch vụ #' . ($subscription['plan_id'] ?? ''))) ?></h1>
	</header>

	<div class="user-subscription-detail-grid">
		<article class="glass-card user-subscription-detail-card">
			<h2><span class="user-subscription-title-icon" aria-hidden="true">&#8645;</span>Lưu lượng</h2>
			<div class="user-subscription-detail-traffic"><strong><?= htmlspecialchars($usageText) ?></strong><span>lưu lượng đã sử dụng</span></div>
			<div class="user-subscription-progress"><span style="width: <?= $usagePercent ?>%"></span></div>
			<p class="user-subscription-progress-label"><?= htmlspecialchars($usageText) ?></p>
			<dl class="user-subscription-detail-list">
				<div><dt>Đã tải lên</dt><dd><?= $formatTraffic((float) ($subscription['upload'] ?? 0)) ?></dd></div>
				<div><dt>Đã tải xuống</dt><dd><?= $formatTraffic((float) ($subscription['download'] ?? 0)) ?></dd></div>
				<div><dt>Thiết bị trực tuyến</dt><dd><?= (int) ($subscription['online_devices'] ?? 0) ?><?= !empty($subscription['device_limit']) ? ' / ' . (int) $subscription['device_limit'] : '' ?></dd></div>
			</dl>
		</article>
		<article class="glass-card user-subscription-detail-card">
			<h2><span class="user-subscription-title-icon" aria-hidden="true">&#9432;</span>Thông tin dịch vụ</h2>
			<dl class="user-subscription-detail-list">
				<div><dt>Mã gói</dt><dd><?= htmlspecialchars($subscription['plan_code'] ?? '-') ?></dd></div>
				<div><dt>Trạng thái</dt><dd><span class="user-subscription-status user-subscription-status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></span></dd></div>
				<div><dt>Ngày bắt đầu</dt><dd><?= !empty($subscription['start_date']) ? date('d/m/Y', strtotime($subscription['start_date'])) : '-' ?></dd></div>
				<div><dt>Ngày hết hạn</dt><dd><?= !empty($subscription['end_date']) ? date('d/m/Y', strtotime($subscription['end_date'])) : '-' ?></dd></div>
				<div><dt>IP kết nối cuối</dt><dd><?= htmlspecialchars($subscription['last_used_ip'] ?? 'Chưa kết nối') ?></dd></div>
				<div><dt>Đơn hàng</dt><dd><?= htmlspecialchars($subscription['order_code'] ?? 'Cấp trực tiếp') ?></dd></div>
			</dl>
		</article>
	</div>

	<section class="user-subscription-connection-section">
		<h2><span class="user-subscription-title-icon" aria-hidden="true">&#128268;</span>Kết nối thiết bị</h2>
		<?php if ($canConnect): ?>
			<div class="glass-card user-subscription-connection-card">
				<div class="user-subscription-connection-content">
					<label for="subscription-url">Liên kết đăng ký</label>
					<div class="user-subscription-copy-row">
						<input id="subscription-url" type="text" value="<?= htmlspecialchars($connectionUrl) ?>" readonly>
						<button type="button" class="glass-btn subscription-copy-button" data-copy-value="<?= htmlspecialchars($connectionUrl) ?>">Sao chép</button>
					</div>
					<div class="user-subscription-app-actions">
						<a href="karing://install-config?url=<?= urlencode($connectionUrl) ?>" data-no-loader>Mở Karing</a>
						<button type="button" class="subscription-qr-toggle" data-qr-modal-open="subscription-qr-modal" aria-controls="subscription-qr-modal">Lấy mã QR</button>
					</div>
					<p class="user-subscription-security-note">Không chia sẻ liên kết hoặc mã QR này vì chúng cấp quyền dùng cấu hình VPN của bạn.</p>
				</div>
			</div>
			<div id="subscription-qr-modal" class="subscription-qr-modal" role="dialog" aria-modal="true" aria-labelledby="subscription-qr-title" hidden>
				<div class="subscription-qr-modal-dialog">
					<button type="button" class="subscription-qr-modal-close" data-qr-modal-close aria-label="Đóng mã QR">&times;</button>
					<h3 id="subscription-qr-title">Mã QR kết nối</h3>
					<div class="user-subscription-qr">
						<?php if ($qrCodeDataUri !== ''): ?>
							<img src="<?= htmlspecialchars($qrCodeDataUri) ?>" alt="Mã QR liên kết đăng ký VPN">
						<?php else: ?>
							<p>Chưa thể tạo mã QR. Vui lòng chạy composer install trên máy chủ.</p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="user-subscription-inbounds">
				<h3><span class="user-subscription-title-icon" aria-hidden="true">&#8801;</span>Danh sách inbound của gói <span><?= count($inboundLinks) ?></span></h3>
				<?php if (!empty($inboundLinks)): ?>
					<?php foreach ($inboundLinks as $inbound): ?>
						<div class="glass-card user-subscription-inbound-item">
							<div class="user-subscription-inbound-heading"><div><strong><?= htmlspecialchars($inbound['name']) ?></strong><span><?= htmlspecialchars($inbound['protocol']) ?></span></div><button type="button" class="subscription-copy-link" data-copy-value="<?= htmlspecialchars($inbound['link']) ?>">Sao chép</button></div>
							<code><?= htmlspecialchars($inbound['link']) ?></code>
						</div>
					<?php endforeach; ?>
				<?php else: ?>
					<p class="user-subscription-empty-inbounds">Chưa có inbound hoạt động cho gói dịch vụ này.</p>
				<?php endif; ?>
			</div>
		<?php else: ?>
			<div class="user-subscriptions-alert">Gói dịch vụ đã hết hạn, bị tạm dừng hoặc đã hủy nên hiện chưa thể kết nối.</div>
		<?php endif; ?>
	</section>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>
