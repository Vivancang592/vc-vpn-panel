<?php
$pageTitle = 'Chi Tiết Đơn Hàng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$status = $order['payment_status'] ?? 'pending';
$statusLabels = ['pending' => 'Chờ thanh toán', 'completed' => 'Hoàn tất', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy'];
ob_start();
?>

<section class="user-orders-page">
	<header class="user-orders-header user-orders-detail-header">
		<div>
			<p class="user-orders-kicker">CHI TIẾT ĐƠN HÀNG</p>
			<h1><?= htmlspecialchars($order['order_code'] ?? ('Đơn hàng #' . ($order['id'] ?? ''))) ?></h1>
			<p>Được tạo lúc <?= !empty($order['created_at']) ? date('H:i, d/m/Y', strtotime($order['created_at'])) : '-' ?></p>
		</div>
	</header>

	<?php if ($status === 'pending'): ?>
		<div class="user-checkout-pending-notice" role="status">
			<div>
				<span class="user-checkout-pending-flag">Lưu ý:</span>
				Đơn hàng này chưa thanh toán. Vui lòng
				<a href="/payment/checkout?order=<?= (int) ($order['id'] ?? 0) ?>">thanh toán ngay</a> hoặc
				<form method="post" action="/orders/cancel" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này?');">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
					<input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
					<button type="submit">hủy đơn</button>
				</form>
				đơn này trước khi tạo đơn hàng mới.
			</div>
		</div>
	<?php endif; ?>

	<div class="user-order-detail-grid">
		<article class="glass-card user-order-detail-card user-order-detail-card-<?= htmlspecialchars($status) ?>">
			<h2>Thông tin gói dịch vụ</h2>
			<dl class="user-order-detail-list">
				<div><dt>Gói dịch vụ</dt><dd><?= htmlspecialchars($order['plan_name'] ?? ('Gói dịch vụ #' . ($order['plan_id'] ?? ''))) ?></dd></div>
				<div><dt>Mã gói</dt><dd><?= htmlspecialchars($order['plan_code'] ?? '-') ?></dd></div>
				<div><dt>Mã giảm giá</dt><dd><?= htmlspecialchars($order['coupon_code'] ?? 'Không áp dụng') ?></dd></div>
			</dl>
		</article>
		<article class="glass-card user-order-detail-card user-order-detail-card-<?= htmlspecialchars($status) ?>">
			<h2>Thanh toán</h2>
			<dl class="user-order-detail-list">
				<div><dt>Tổng tiền</dt><dd class="user-order-amount"><?= isset($formatMoney) ? $formatMoney($order['total_amount'] ?? 0) : number_format((float) ($order['total_amount'] ?? 0), 2) ?></dd></div>
				<div><dt>Trạng thái</dt><dd><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></dd></div>
				<div><dt>Cập nhật</dt><dd><?= !empty($order['updated_at']) ? date('H:i, d/m/Y', strtotime($order['updated_at'])) : '-' ?></dd></div>
			</dl>
		</article>
	</div>

</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
