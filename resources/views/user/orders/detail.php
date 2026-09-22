<?php
$pageTitle = 'Chi Tiết Đơn Hàng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$status = $order['payment_status'] ?? 'pending';
$statusLabels = ['pending' => 'Chờ thanh toán', 'completed' => 'Hoàn tất', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy'];
$statusClasses = ['pending' => 'is-pending', 'completed' => 'is-completed', 'failed' => 'is-failed', 'cancelled' => 'is-cancelled'];
$creatorName = $order['creator_username'] ?? $order['username'] ?? 'Bạn';
$approverName = $order['approver_username'] ?? ($status === 'completed' ? 'Tự động qua webhook' : 'Chưa duyệt');
$paymentMethodLabels = ['vietqr' => 'VietQR (Chuyển khoản)', 'balance' => 'Số dư tài khoản'];
$paymentMethod = strtolower((string) ($order['payment_method'] ?? 'vietqr'));
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
			<p class="user-invoice-kicker">HÓA ĐƠN DỊCH VỤ</p>
			<h2>Đơn hàng <?= htmlspecialchars($orderCode) ?></h2>
			<p>Khởi tạo lúc <?= !empty($order['created_at']) ? date('H:i, d/m/Y', strtotime($order['created_at'])) : '-' ?></p>
		</div>
		<span class="user-invoice-status <?= htmlspecialchars($statusClasses[$status] ?? '') ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></span>
	</header>

	<article class="glass-card user-invoice-card">
		<div class="user-invoice-meta">
			<div><span>Mã hóa đơn</span><strong>#<?= $orderId ?></strong></div>
			<div><span>Mã đơn hàng</span><strong><?= htmlspecialchars($orderCode) ?></strong></div>
			<div><span>Phương thức</span><strong><?= htmlspecialchars($paymentMethodLabels[$paymentMethod] ?? strtoupper($paymentMethod)) ?></strong></div>
			<div><span>Trạng thái</span><strong><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></strong></div>
		</div>

		<div class="user-invoice-section-title"><h3>Chi tiết dịch vụ</h3><span>Số lượng</span><span>Thành tiền</span></div>
		<div class="user-invoice-line">
			<div>
				<strong><?= htmlspecialchars($isDeposit ? 'Nạp tiền vào ví' : ($subscriptionId > 0 ? 'Gia hạn gói dịch vụ' : ($order['plan_name'] ?? 'Gói dịch vụ'))) ?></strong>
				<small>
					<?php if ($isDeposit): ?>Cộng số dư sau khi giao dịch được xác nhận.<?php else: ?>
						<?= htmlspecialchars(!empty($order['plan_code']) ? 'Mã gói: ' . $order['plan_code'] : 'Dịch vụ VPN') ?>
						<?= !empty($order['coupon_code']) ? ' | Mã giảm giá: ' . htmlspecialchars($order['coupon_code']) : '' ?>
					<?php endif; ?>
				</small>
			</div>
			<span>1</span>
			<strong><?= htmlspecialchars($formatTotal((float) ($order['total_amount'] ?? 0))) ?></strong>
		</div>

		<div class="user-invoice-total"><span>Tổng thanh toán</span><strong><?= htmlspecialchars($formatTotal((float) ($order['total_amount'] ?? 0))) ?></strong></div>

		<div class="user-invoice-details">
			<div><span>Nội dung chuyển khoản</span><strong><?= htmlspecialchars($transferContent !== '' ? $transferContent : 'Không áp dụng') ?></strong></div>
			<div><span>Người tạo đơn</span><strong><?= htmlspecialchars($creatorName) ?></strong></div>
			<div><span>Người xác nhận</span><strong><?= htmlspecialchars($approverName) ?></strong></div>
			<div><span>IP đặt hàng</span><strong><?= htmlspecialchars($order['purchase_ip'] ?? 'Chưa lưu') ?></strong></div>
			<div><span>Cập nhật lần cuối</span><strong><?= !empty($order['updated_at']) ? date('H:i, d/m/Y', strtotime($order['updated_at'])) : '-' ?></strong></div>
		</div>

		<?php if ($status === 'pending' && $orderId > 0): ?>
			<div class="user-invoice-actions"><a class="glass-btn" href="<?= htmlspecialchars($checkoutUrl) ?>">Thanh toán ngay</a></div>
		<?php endif; ?>
	</article>

</section>

<style>
.user-invoice-header { align-items: flex-start; gap: 20px; }
.user-invoice-kicker { margin: 0 0 6px; color: var(--ios-blue); font-size: .8rem; font-weight: 700; }
.user-invoice-status { display: inline-flex; padding: 7px 12px; border-radius: 6px; font-weight: 700; background: #e7ebf0; color: #536171; }
.user-invoice-status.is-pending { background: #fff3d4; color: #9a6200; }.user-invoice-status.is-completed { background: #dff7e8; color: #197447; }.user-invoice-status.is-failed, .user-invoice-status.is-cancelled { background: #ffe3e3; color: #b32929; }
.user-invoice-card { padding: 26px; }.user-invoice-meta, .user-invoice-details { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.user-invoice-meta { padding-bottom: 24px; border-bottom: 1px solid rgba(90, 105, 125, .18); }.user-invoice-meta span, .user-invoice-details span { display: block; color: var(--ios-gray); font-size: .82rem; margin-bottom: 5px; }.user-invoice-meta strong, .user-invoice-details strong { display: block; overflow-wrap: anywhere; }
.user-invoice-section-title, .user-invoice-line { display: grid; grid-template-columns: minmax(0, 1fr) 110px 145px; gap: 16px; align-items: center; }.user-invoice-section-title { padding: 24px 0 10px; color: var(--ios-gray); font-size: .85rem; }.user-invoice-section-title h3 { color: inherit; font-size: .85rem; margin: 0; }.user-invoice-line { padding: 16px 0 22px; border-bottom: 1px solid rgba(90, 105, 125, .18); }.user-invoice-line small { display: block; margin-top: 5px; color: var(--ios-gray); }.user-invoice-line > :last-child, .user-invoice-section-title > :last-child { text-align: right; }
.user-invoice-total { display: flex; justify-content: flex-end; align-items: baseline; gap: 34px; padding: 20px 0 26px; }.user-invoice-total strong { color: var(--ios-blue); font-size: 1.35rem; }.user-invoice-details { grid-template-columns: repeat(3, 1fr); padding-top: 20px; border-top: 1px solid rgba(90, 105, 125, .18); }.user-invoice-actions { margin-top: 26px; text-align: right; }
@media (max-width: 700px) { .user-invoice-header { flex-direction: column; }.user-invoice-meta, .user-invoice-details { grid-template-columns: repeat(2, 1fr); }.user-invoice-section-title { display: none; }.user-invoice-line { grid-template-columns: minmax(0, 1fr) auto; }.user-invoice-line > :nth-child(2) { display: none; }.user-invoice-total { gap: 16px; }.user-invoice-card { padding: 18px; } }
</style>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
