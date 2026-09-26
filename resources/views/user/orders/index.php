<?php
$pageTitle = 'Đơn Hàng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$statusLabels = [
	'pending' => 'Chờ thanh toán',
	'completed' => 'Hoàn tất',
	'failed' => 'Thất bại',
	'cancelled' => 'Đã hủy'
];
$userOrders = isset($orders) && is_array($orders) ? $orders : [];
$completedCount = count(array_filter($userOrders, static fn($order) => ($order['payment_status'] ?? '') === 'completed'));
$pendingCount = count(array_filter($userOrders, static fn($order) => ($order['payment_status'] ?? '') === 'pending'));
$cancelledCount = count(array_filter($userOrders, static fn($order) => ($order['payment_status'] ?? '') === 'cancelled'));
$expiredCount = count(array_filter($userOrders, static fn($order) => ($order['payment_status'] ?? '') === 'failed'));
ob_start();
?>

<section class="user-orders-page">
	<header class="user-orders-header">
		<div class="user-orders-intro">
			<h2 class="u-plans-title">LỊCH SỬ MUA HÀNG</h2>
			<p>Theo dõi trạng thái thanh toán và gói dịch vụ đã đăng ký.</p>
		</div>
		<div class="user-orders-title-row">
			<h1>Đơn hàng <span class="user-orders-total-count"><?= count($userOrders) ?></span></h1>
			<a href="/user/plans" class="glass-btn user-orders-new-link">Mua gói dịch vụ</a>
		</div>
	</header>

	<div class="user-order-stats" aria-label="Tổng quan đơn hàng">
		<div class="glass-card user-order-stat user-order-stat-completed"><span>Đã hoàn tất</span><strong><?= $completedCount ?></strong></div>
		<div class="glass-card user-order-stat user-order-stat-pending"><span>Đang chờ</span><strong><?= $pendingCount ?></strong></div>
		<div class="glass-card user-order-stat user-order-stat-cancelled"><span>Đã hủy</span><strong><?= $cancelledCount ?></strong></div>
		<div class="glass-card user-order-stat user-order-stat-expired"><span>Hết hạn</span><strong><?= $expiredCount ?></strong></div>
	</div>

	<?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?>
		<div class="user-record-alert <?= !empty($_SESSION['error']) ? 'is-error' : '' ?>">
			<span><?= htmlspecialchars($_SESSION['error'] ?? $_SESSION['success']) ?></span>
			<button type="button" class="alert-close-btn" onclick="this.parentElement.remove();" aria-label="Đóng">&times;</button>
		</div>
		<?php unset($_SESSION['success'], $_SESSION['error']); ?>
	<?php endif; ?>

	<?php if (!empty($userOrders)): ?>
		<div class="glass-card user-orders-table-wrap">
			<table class="user-orders-table">
				<thead>
					<tr><th>ID</th><th>Mã đơn hàng</th><th>Gói dịch vụ</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th><th style="text-align: right;">Thao tác</th></tr>
				</thead>
				<tbody>
					<?php foreach ($userOrders as $order): ?>
						<?php $status = $order['payment_status'] ?? 'pending'; ?>
						<tr>
							<td data-label="ID"><code>#<?= (int) ($order['id'] ?? 0) ?></code></td>
							<td data-label="Mã đơn hàng"><code><?= htmlspecialchars($order['order_code'] ?? ('#' . ($order['id'] ?? ''))) ?></code></td>
							<td data-label="Gói dịch vụ">
								<?php if (!empty($order['plan_id'])): ?>
									<strong><?= htmlspecialchars($order['plan_name'] ?? ('Gói dịch vụ #' . ($order['plan_id'] ?? ''))) ?></strong>
								<?php else: ?>
									<strong style="color: var(--ios-blue);">💰 Nạp tiền vào ví</strong>
								<?php endif; ?>
							</td>
							<td data-label="Tổng tiền" class="user-order-amount"><?= isset($formatMoney) ? $formatMoney($order['total_amount'] ?? 0) : number_format((float) ($order['total_amount'] ?? 0), 2) ?></td>
							<td data-label="Trạng thái"><span class="user-order-status user-order-status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></span></td>
							<td data-label="Ngày tạo"><?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : '-' ?></td>
							<td class="user-order-action" style="text-align: right;">
								<div class="action-dropdown">
									<button type="button" class="action-btn" title="Thao tác">⋮</button>
									<div class="action-menu" style="min-width: 165px; white-space: nowrap;">
										<a href="/orders/detail?id=<?= (int) ($order['id'] ?? 0) ?>" class="action-item">
											<span>👁️</span> Xem chi tiết
										</a>
										<?php if ($status === 'pending'): ?>
											<a href="/payment/checkout?order=<?= (int) ($order['id'] ?? 0) ?>" class="action-item" style="color: var(--ios-blue);">
												<span>💳</span> Thanh toán ngay
											</a>
											<form method="post" action="/orders/cancel" style="margin: 0;" data-confirm-submit="Bạn có chắc chắn muốn hủy đơn hàng này không?">
												<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
												<input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
												<button type="submit" class="action-item cancel" style="color: var(--ios-danger);">
													<span>❌</span> Hủy đơn hàng
												</button>
											</form>
										<?php endif; ?>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else: ?>
		<div class="glass-card user-orders-empty">
			<h2>Chưa có đơn hàng</h2>
			<p>Gói dịch vụ bạn mua sẽ được hiển thị tại đây.</p>
			<a href="/user/plans" class="glass-btn">Xem gói dịch vụ</a>
		</div>
	<?php endif; ?>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>
