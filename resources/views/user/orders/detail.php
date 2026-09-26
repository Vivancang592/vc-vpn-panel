<?php
$pageTitle = 'Chi Tiết Đơn Hàng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$status = $order['payment_status'] ?? 'pending';
$statusLabels = ['pending' => 'Chờ thanh toán', 'completed' => 'Hoàn tất', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy'];
$statusClasses = ['pending' => 'is-pending', 'completed' => 'is-completed', 'failed' => 'is-failed', 'cancelled' => 'is-cancelled'];
$creatorName = $order['creator_username'] ?? $order['username'] ?? 'Bạn';
$paymentMethodLabels = ['vietqr' => 'VietQR (Chuyển khoản)', 'balance' => 'Số dư tài khoản'];
$paymentMethod = strtolower((string) ($order['payment_method'] ?? 'vietqr'));
$isBalancePayment = $paymentMethod === 'balance';
$approverName = $isBalancePayment ? 'Hệ thống' : ($order['approver_username'] ?? ($status === 'completed' ? 'Tự động qua webhook' : 'Chưa duyệt'));
$orderId = (int) ($order['id'] ?? 0);
$subscriptionId = (int) ($order['subscription_id'] ?? 0);
$isDeposit = empty($order['plan_id']);
$orderCode = (string) ($order['order_code'] ?? ('DH' . $orderId));
$transferContent = trim((string) ($order['transfer_content'] ?? ''));
$formatMoney = $formatMoney ?? null;
$formatTotal = static function (float $amount) use ($formatMoney): string {
    return isset($formatMoney) && is_callable($formatMoney)
        ? $formatMoney($amount)
        : number_format($amount, 0, '.', ',') . ' đ';
};
$checkoutUrl = '/payment/checkout?order=' . $orderId
    . ($subscriptionId > 0 ? '&renewal=' . $subscriptionId : '');
ob_start();
?>

<section class="user-orders-page">
	<header class="user-orders-header user-orders-detail-header user-invoice-header">
		<div>
			<h2 class="u-plans-title">CHI TIẾT ĐƠN HÀNG</h2>
			<h4><?= htmlspecialchars($orderCode) ?></h4>
			<p>Được tạo lúc <?= !empty($order['created_at']) ? date('H:i, d/m/Y', strtotime($order['created_at'])) : '-' ?></p>
		</div>
	</header>

	<article class="glass-card user-invoice-card">
		<div class="user-invoice-total"><span>Tổng thanh toán</span><strong><?= htmlspecialchars($formatTotal((float) ($order['total_amount'] ?? 0))) ?></strong></div>

		<div class="user-invoice-table-grid">
			<section class="user-invoice-table-section">
				<h3 class="user-invoice-section-heading">Thông tin đơn hàng</h3>
				<div class="user-invoice-rows">
			<div class="user-invoice-row"><span>Mã đơn hàng</span><strong><?= htmlspecialchars($orderCode) ?></strong></div>
			<div class="user-invoice-row"><span>Loại đơn</span><strong><?= htmlspecialchars($isDeposit ? 'Nạp tiền vào ví' : ($subscriptionId > 0 ? 'Gia hạn gói dịch vụ' : 'Mua gói dịch vụ')) ?></strong></div>
			<div class="user-invoice-row"><span>Trạng thái thanh toán</span><strong class="user-invoice-status-value <?= htmlspecialchars($statusClasses[$status] ?? '') ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></strong></div>

				<h3 class="user-invoice-section-heading">Dịch vụ và ưu đãi</h3>
			<div class="user-invoice-row"><span>Gói dịch vụ</span><strong><?= htmlspecialchars($isDeposit ? 'Không áp dụng' : ($order['plan_name'] ?? 'Gói dịch vụ')) ?></strong></div>
			<div class="user-invoice-row"><span>Mã giảm giá</span><strong><?= htmlspecialchars($order['coupon_code'] ?? 'Không áp dụng') ?></strong></div>
				</div>
			</section>

			<section class="user-invoice-table-section">
				<h3 class="user-invoice-section-heading">Thanh toán</h3>
				<div class="user-invoice-rows">
			<div class="user-invoice-row"><span>Tổng tiền</span><strong class="user-invoice-money"><?= htmlspecialchars($formatTotal((float) ($order['total_amount'] ?? 0))) ?></strong></div>
			<div class="user-invoice-row"><span>Phương thức thanh toán</span><strong><?= htmlspecialchars($paymentMethodLabels[$paymentMethod] ?? strtoupper($paymentMethod)) ?></strong></div>
			<?php if (!$isBalancePayment): ?>
			<div class="user-invoice-row"><span>Nội dung chuyển khoản</span><strong><?= htmlspecialchars($transferContent !== '' ? $transferContent : 'Không áp dụng') ?></strong></div>
			<?php endif; ?>
			<div class="user-invoice-row"><span>IP đặt hàng</span><strong><?= htmlspecialchars($order['purchase_ip'] ?? 'Chưa lưu') ?></strong></div>

				<h3 class="user-invoice-section-heading">Đối soát</h3>
			<div class="user-invoice-row"><span>Người tạo đơn</span><strong><?= htmlspecialchars($creatorName) ?></strong></div>
			<div class="user-invoice-row"><span>Người xác nhận</span><strong><?= htmlspecialchars($approverName) ?></strong></div>
			<div class="user-invoice-row"><span>Cập nhật lần cuối</span><strong><?= !empty($order['updated_at']) ? date('H:i, d/m/Y', strtotime($order['updated_at'])) : '-' ?></strong></div>
				</div>
			</section>
		</div>

		<?php if ($status === 'pending' && $orderId > 0): ?>
			<div class="user-invoice-actions"><a class="glass-btn" href="<?= htmlspecialchars($checkoutUrl) ?>">Thanh toán ngay</a></div>
		<?php endif; ?>
	</article>

</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
