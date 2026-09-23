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
			<h2 style="color: #020af4; font-family: emoji;">CHI TIẾT ĐƠN HÀNG</h2>
			<h4><?= htmlspecialchars($orderCode) ?></h4>
			<p>Được tạo lúc <?= !empty($order['created_at']) ? date('H:i, d/m/Y', strtotime($order['created_at'])) : '-' ?></p>
		</div>
		<span class="user-invoice-status <?= htmlspecialchars($statusClasses[$status] ?? '') ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></span>
	</header>

	<article class="glass-card user-invoice-card">
		<div class="user-invoice-total"><span>Tổng thanh toán</span><strong><?= htmlspecialchars($formatTotal((float) ($order['total_amount'] ?? 0))) ?></strong></div>

		<div class="user-invoice-table-grid">
			<section class="user-invoice-table-section">
				<h3 class="user-invoice-section-heading">Thông tin đơn hàng</h3>
				<div class="user-invoice-rows">
			<div class="user-invoice-row"><span>ID đơn hàng</span><strong>#<?= $orderId ?></strong></div>
			<div class="user-invoice-row"><span>Mã đơn hàng</span><strong><?= htmlspecialchars($orderCode) ?></strong></div>
			<div class="user-invoice-row"><span>ID người dùng</span><strong>#<?= (int) ($order['user_id'] ?? 0) ?></strong></div>
			<div class="user-invoice-row"><span>Loại đơn</span><strong><?= htmlspecialchars($isDeposit ? 'Nạp tiền vào ví' : ($subscriptionId > 0 ? 'Gia hạn gói dịch vụ' : 'Mua gói dịch vụ')) ?></strong></div>
			<div class="user-invoice-row"><span>Trạng thái thanh toán</span><strong class="user-invoice-status-value <?= htmlspecialchars($statusClasses[$status] ?? '') ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></strong></div>

				<h3 class="user-invoice-section-heading">Dịch vụ và ưu đãi</h3>
			<div class="user-invoice-row"><span>Gói dịch vụ</span><strong><?= htmlspecialchars($isDeposit ? 'Không áp dụng' : ($order['plan_name'] ?? 'Gói dịch vụ')) ?></strong></div>
			<div class="user-invoice-row"><span>ID gói dịch vụ</span><strong><?= !empty($order['plan_id']) ? '#' . (int) $order['plan_id'] : 'Không áp dụng' ?></strong></div>
			<div class="user-invoice-row"><span>ID gói đang gia hạn</span><strong><?= $subscriptionId > 0 ? '#' . $subscriptionId : 'Không áp dụng' ?></strong></div>
			<div class="user-invoice-row"><span>Mã giảm giá</span><strong><?= htmlspecialchars($order['coupon_code'] ?? 'Không áp dụng') ?></strong></div>
			<div class="user-invoice-row"><span>ID mã giảm giá</span><strong><?= !empty($order['coupon_id']) ? '#' . (int) $order['coupon_id'] : 'Không áp dụng' ?></strong></div>
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
			<div class="user-invoice-row"><span>ID người tạo</span><strong><?= !empty($order['created_by']) ? '#' . (int) $order['created_by'] : 'Không áp dụng' ?></strong></div>
			<div class="user-invoice-row"><span>Người tạo đơn</span><strong><?= htmlspecialchars($creatorName) ?></strong></div>
			<div class="user-invoice-row"><span>ID người duyệt</span><strong><?= !empty($order['approved_by']) ? '#' . (int) $order['approved_by'] : 'Chưa duyệt' ?></strong></div>
			<div class="user-invoice-row"><span>Người xác nhận</span><strong><?= htmlspecialchars($approverName) ?></strong></div>
			<div class="user-invoice-row"><span>Thời gian tạo</span><strong><?= !empty($order['created_at']) ? date('H:i, d/m/Y', strtotime($order['created_at'])) : '-' ?></strong></div>
			<div class="user-invoice-row"><span>Cập nhật lần cuối</span><strong><?= !empty($order['updated_at']) ? date('H:i, d/m/Y', strtotime($order['updated_at'])) : '-' ?></strong></div>
				</div>
			</section>
		</div>

		<?php if ($status === 'pending' && $orderId > 0): ?>
			<div class="user-invoice-actions"><a class="glass-btn" href="<?= htmlspecialchars($checkoutUrl) ?>">Thanh toán ngay</a></div>
		<?php endif; ?>
	</article>

</section>

<style>
.user-invoice-status { display: inline-flex; padding: 7px 12px; border-radius: 6px; font-weight: 700; background: #e7ebf0; color: #536171; }
.user-invoice-status.is-pending { background: #fff3d4; color: #9a6200; }.user-invoice-status.is-completed { background: #dff7e8; color: #197447; }.user-invoice-status.is-failed, .user-invoice-status.is-cancelled { background: #ffe3e3; color: #b32929; }
.user-invoice-card { max-width: 1120px; margin: 0 auto; padding: 0; overflow: hidden; border: 1px solid rgba(46, 68, 100, .16); box-shadow: 0 16px 42px rgba(36, 52, 80, .12); }.user-invoice-total { display: flex; justify-content: space-between; align-items: center; margin: 26px 30px 0; padding: 18px 20px; background: #102a43; color: #fff; border-radius: 6px; }.user-invoice-total span { font-size: .9rem; font-weight: 600; opacity: .78; }.user-invoice-total strong, .user-invoice-money { color: #0b57d0; font-size: 1.3rem; }.user-invoice-total strong { color: #fff; font-size: 1.45rem; }.user-invoice-table-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 30px; padding: 0 30px 22px; }.user-invoice-section-heading { margin: 30px 0 6px; padding-bottom: 9px; color: #172033; font-size: .82rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; border-bottom: 2px solid #d8e5f7; }.user-invoice-row { display: grid; grid-template-columns: minmax(130px, .85fr) minmax(0, 1.15fr); gap: 18px; padding: 12px 14px; border-bottom: 1px solid rgba(90, 105, 125, .12); align-items: center; }.user-invoice-row:nth-of-type(even) { background: rgba(235, 241, 249, .52); }.user-invoice-row span { color: #66768b; font-size: .9rem; }.user-invoice-row strong { color: #172033; text-align: right; overflow-wrap: anywhere; }.user-invoice-money { font-weight: 800; }.user-invoice-status-value { justify-self: end; padding: 4px 9px; border-radius: 5px; font-size: .82rem; }.user-invoice-status-value.is-pending { background: #fff3d4; color: #9a6200; }.user-invoice-status-value.is-completed { background: #dff7e8; color: #197447; }.user-invoice-status-value.is-failed, .user-invoice-status-value.is-cancelled { background: #ffe3e3; color: #b32929; }.user-invoice-actions { margin: 4px 30px 30px; text-align: right; }
@media (max-width: 800px) { .user-invoice-card { padding: 0; }.user-invoice-total { margin: 20px 18px 0; padding: 16px; }.user-invoice-table-grid { grid-template-columns: 1fr; gap: 0; padding: 0 18px 18px; }.user-invoice-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 14px; padding: 12px 8px; }.user-invoice-row strong { font-size: .88rem; }.user-invoice-total strong { font-size: 1.12rem; }.user-invoice-actions { margin: 4px 18px 22px; } }
</style>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
