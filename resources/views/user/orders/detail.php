<?php
$pageTitle = 'Chi Tiết Đơn Hàng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$status = $order['payment_status'] ?? 'pending';
$statusLabels = ['pending' => 'Chờ thanh toán', 'completed' => 'Hoàn tất', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy'];
$creatorName = $order['creator_username'] ?? $order['username'] ?? 'Bạn';
$approverName = $order['approver_username'] ?? ($status === 'completed' ? 'Tự động qua webhook' : 'Chưa duyệt');
$paymentMethodLabels = ['vietqr' => 'VietQR (Chuyển khoản)', 'balance' => 'Số dư tài khoản'];
$paymentMethod = strtolower((string) ($order['payment_method'] ?? 'vietqr'));
ob_start();
?>

<section class="user-orders-page">
	<header class="user-orders-header user-orders-detail-header">
		<div>
			<h2 style="color: #020af4; font-family: emoji;">CHI TIẾT ĐƠN HÀNG</h2>
			<h4><?= htmlspecialchars($order['order_code'] ?? ('Đơn hàng #' . ($order['id'] ?? ''))) ?></h4>
			<p>Được tạo lúc <?= !empty($order['created_at']) ? date('H:i, d/m/Y', strtotime($order['created_at'])) : '-' ?></p>
		</div>
	</header>

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
				<div><dt>Cổng thanh toán</dt><dd><?= htmlspecialchars($paymentMethodLabels[$paymentMethod] ?? strtoupper($paymentMethod)) ?></dd></div>
				<div><dt>Trạng thái</dt><dd><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></dd></div>
				<div><dt>IP đặt hàng</dt><dd><?= htmlspecialchars($order['purchase_ip'] ?? 'Chưa lưu') ?></dd></div>
				<div><dt>Người tạo đơn</dt><dd><?= htmlspecialchars($creatorName) ?></dd></div>
				<div><dt>Người duyệt đơn</dt><dd><?= htmlspecialchars($approverName) ?></dd></div>
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
