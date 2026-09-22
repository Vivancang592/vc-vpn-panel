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
		<div class="user-invoice-total"><span>Tổng thanh toán</span><strong><?= htmlspecialchars($formatTotal((float) ($order['total_amount'] ?? 0))) ?></strong></div>

		<h3 class="user-invoice-section-heading">Thông tin hóa đơn</h3>
		<div class="user-invoice-rows">
			<div class="user-invoice-row"><span>ID đơn hàng</span><strong>#<?= $orderId ?></strong></div>
			<div class="user-invoice-row"><span>Mã đơn hàng</span><strong><?= htmlspecialchars($orderCode) ?></strong></div>
			<div class="user-invoice-row"><span>ID người dùng</span><strong>#<?= (int) ($order['user_id'] ?? 0) ?></strong></div>
			<div class="user-invoice-row"><span>Loại đơn</span><strong><?= htmlspecialchars($isDeposit ? 'Nạp tiền vào ví' : ($subscriptionId > 0 ? 'Gia hạn gói dịch vụ' : 'Mua gói dịch vụ')) ?></strong></div>
			<div class="user-invoice-row"><span>Gói dịch vụ</span><strong><?= htmlspecialchars($isDeposit ? 'Không áp dụng' : ($order['plan_name'] ?? 'Gói dịch vụ')) ?></strong></div>
			<div class="user-invoice-row"><span>ID gói dịch vụ</span><strong><?= !empty($order['plan_id']) ? '#' . (int) $order['plan_id'] : 'Không áp dụng' ?></strong></div>
			<div class="user-invoice-row"><span>ID gói đang gia hạn</span><strong><?= $subscriptionId > 0 ? '#' . $subscriptionId : 'Không áp dụng' ?></strong></div>
			<div class="user-invoice-row"><span>Mã giảm giá</span><strong><?= htmlspecialchars($order['coupon_code'] ?? 'Không áp dụng') ?></strong></div>
			<div class="user-invoice-row"><span>ID mã giảm giá</span><strong><?= !empty($order['coupon_id']) ? '#' . (int) $order['coupon_id'] : 'Không áp dụng' ?></strong></div>
			<div class="user-invoice-row"><span>Tổng tiền</span><strong class="user-invoice-money"><?= htmlspecialchars($formatTotal((float) ($order['total_amount'] ?? 0))) ?></strong></div>
			<div class="user-invoice-row"><span>Phương thức thanh toán</span><strong><?= htmlspecialchars($paymentMethodLabels[$paymentMethod] ?? strtoupper($paymentMethod)) ?></strong></div>
			<div class="user-invoice-row"><span>Nội dung chuyển khoản</span><strong><?= htmlspecialchars($transferContent !== '' ? $transferContent : 'Không áp dụng') ?></strong></div>
			<div class="user-invoice-row"><span>Trạng thái thanh toán</span><strong><?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?></strong></div>
			<div class="user-invoice-row"><span>IP đặt hàng</span><strong><?= htmlspecialchars($order['purchase_ip'] ?? 'Chưa lưu') ?></strong></div>
			<div class="user-invoice-row"><span>ID người tạo</span><strong><?= !empty($order['created_by']) ? '#' . (int) $order['created_by'] : 'Không áp dụng' ?></strong></div>
			<div class="user-invoice-row"><span>Người tạo đơn</span><strong><?= htmlspecialchars($creatorName) ?></strong></div>
			<div class="user-invoice-row"><span>ID người duyệt</span><strong><?= !empty($order['approved_by']) ? '#' . (int) $order['approved_by'] : 'Chưa duyệt' ?></strong></div>
			<div class="user-invoice-row"><span>Người xác nhận</span><strong><?= htmlspecialchars($approverName) ?></strong></div>
			<div class="user-invoice-row"><span>Thời gian tạo</span><strong><?= !empty($order['created_at']) ? date('H:i, d/m/Y', strtotime($order['created_at'])) : '-' ?></strong></div>
			<div class="user-invoice-row"><span>Cập nhật lần cuối</span><strong><?= !empty($order['updated_at']) ? date('H:i, d/m/Y', strtotime($order['updated_at'])) : '-' ?></strong></div>
		</div>

		<?php if ($status === 'pending' && $orderId > 0): ?>
			<div class="user-invoice-actions"><a class="glass-btn" href="<?= htmlspecialchars($checkoutUrl) ?>">Thanh toán ngay</a></div>
		<?php endif; ?>
	</article>

</section>

<style>
.user-invoice-header { align-items: center; flex-direction: column; gap: 10px; text-align: center; }
.user-invoice-kicker { margin: 0 0 6px; color: var(--ios-blue); font-size: .8rem; font-weight: 700; }
.user-invoice-status { display: inline-flex; padding: 7px 12px; border-radius: 6px; font-weight: 700; background: #e7ebf0; color: #536171; }
.user-invoice-status.is-pending { background: #fff3d4; color: #9a6200; }.user-invoice-status.is-completed { background: #dff7e8; color: #197447; }.user-invoice-status.is-failed, .user-invoice-status.is-cancelled { background: #ffe3e3; color: #b32929; }
.user-invoice-card { max-width: 860px; margin: 0 auto; padding: 28px; }.user-invoice-total { display: flex; justify-content: space-between; align-items: baseline; padding: 0 0 22px; border-bottom: 2px solid rgba(90, 105, 125, .18); }.user-invoice-total span { color: var(--ios-gray); }.user-invoice-total strong, .user-invoice-money { color: var(--ios-blue); font-size: 1.3rem; }.user-invoice-section-heading { margin: 26px 0 8px; font-size: 1rem; }.user-invoice-rows { border-top: 1px solid rgba(90, 105, 125, .18); }.user-invoice-row { display: grid; grid-template-columns: minmax(180px, .8fr) minmax(0, 1.2fr); gap: 24px; padding: 13px 0; border-bottom: 1px solid rgba(90, 105, 125, .14); align-items: start; }.user-invoice-row span { color: var(--ios-gray); }.user-invoice-row strong { text-align: right; overflow-wrap: anywhere; }.user-invoice-actions { margin-top: 26px; text-align: right; }
@media (max-width: 700px) { .user-invoice-card { padding: 18px; }.user-invoice-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 14px; }.user-invoice-row strong { font-size: .92rem; }.user-invoice-total strong { font-size: 1.15rem; } }
</style>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
