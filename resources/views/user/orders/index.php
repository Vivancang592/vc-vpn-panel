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
			<p class="user-orders-kicker">LỊCH SỬ MUA HÀNG</p>
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

	<?php if (!empty($_SESSION['error'])): ?>
		<div class="user-orders-alert" role="alert"><?= htmlspecialchars($_SESSION['error']) ?></div>
		<?php unset($_SESSION['error']); ?>
	<?php endif; ?>

	<?php if (!empty($userOrders)): ?>
		<div class="glass-card user-orders-table-wrap">
			<table class="user-orders-table">
				<thead>
					<tr><th>Mã đơn hàng</th><th>Gói dịch vụ</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th><th></th></tr>
				</thead>
				<tbody>
					<?php foreach ($userOrders as $order): ?>
						<?php $status = $order['payment_status'] ?? 'pending'; ?>
						<tr>
							<td data-label="Mã đơn hàng"><code><?= htmlspecialchars($order['order_code'] ?? ('#' . ($order['id'] ?? ''))) ?></code></td>
							<td data-label="Gói dịch vụ"><strong><?= htmlspecialchars($order['plan_name'] ?? ('Gói dịch vụ #' . ($order['plan_id'] ?? ''))) ?></strong></td>
							<td data-label="Tổng tiền" class="user-order-amount"><?= isset($formatMoney) ? $formatMoney($order['total_amount'] ?? 0) : number_format((float) ($order['total_amount'] ?? 0), 2) ?></td>
							<td data-label="Trạng thái"><span class="user-order-status user-order-status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></span></td>
							<td data-label="Ngày tạo"><?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : '-' ?></td>
							<td class="user-order-action"><a href="/orders/detail?id=<?= (int) ($order['id'] ?? 0) ?>">Xem chi tiết</a></td>
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
