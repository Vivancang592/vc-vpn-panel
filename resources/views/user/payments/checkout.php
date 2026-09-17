<?php
$pageTitle = 'Thanh Toán Đơn Hàng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$paymentInfo = isset($paymentInstructions) && is_array($paymentInstructions) ? $paymentInstructions : [];
$qrUrl = trim((string) ($paymentInfo['qr_url'] ?? ''));
ob_start();
?>

<section class="user-payment-checkout-page">
    <header class="user-payment-checkout-header">
        <p class="user-plans-kicker">THANH TOÁN ĐƠN HÀNG</p>
        <h1>Quét mã để thanh toán</h1>
        <p>Đơn hàng <strong><?= htmlspecialchars((string) ($order['order_code'] ?? '')) ?></strong> đang chờ thanh toán.</p>
    </header>

    <div id="payment-status-banner" class="user-payment-status-banner" hidden>Đang kiểm tra thanh toán...</div>

    <div class="user-payment-checkout-grid">
        <article class="glass-card user-payment-qr-card">
            <h3 style="text-align: center; color: #0046f6;">CỔNG THANH TOÁN <?= htmlspecialchars((string) ($paymentInfo['name'] ?? '')) ?></h3>
            <p class="user-payment-qr-instruction" style="color: #00e6f6; text-align: center;">Thanh toán đúng số tiền và nội dung để hệ thống tự động xác nhận đơn hàng.</p>
            
            <?php if ($qrUrl !== ''): ?>
                <img src="<?= htmlspecialchars($qrUrl) ?>" alt="Mã QR thanh toán <?= htmlspecialchars((string) ($paymentInfo['name'] ?? '')) ?>" class="user-payment-qr-image">
            <?php else: ?>
                <div class="user-payment-qr-missing">Mã QR chưa được cấu hình.</div>
            <?php endif; ?>
            <p>Quét mã QR bằng ứng dụng thanh toán của bạn.</p>
        </article>

        <article class="glass-card user-payment-order-card">
            <h2>Thông tin thanh toán</h2>
            <dl class="user-order-detail-list">
                <div><dt>Gói dịch vụ</dt><dd><?= htmlspecialchars((string) ($order['plan_name'] ?? 'Gói VPN')) ?></dd></div>
                <div><dt>Cổng thanh toán</dt><dd><?= htmlspecialchars((string) ($paymentInfo['name'] ?? '')) ?></dd></div>
                <?php if (!empty($paymentInfo['bank_name'])): ?><div><dt>Ngân hàng</dt><dd><?= htmlspecialchars((string) $paymentInfo['bank_name']) ?></dd></div><?php endif; ?>
                <?php if (!empty($paymentInfo['account_number'])): ?><div><dt>Số tài khoản</dt><dd><?= htmlspecialchars((string) $paymentInfo['account_number']) ?></dd></div><?php endif; ?>
                <?php if (!empty($paymentInfo['account_name'])): ?><div><dt>Chủ tài khoản</dt><dd><?= htmlspecialchars((string) $paymentInfo['account_name']) ?></dd></div><?php endif; ?>
                <div class="user-payment-transfer-row"><dt>Nội dung chuyển khoản</dt><dd class="user-payment-transfer-content"><span><?= htmlspecialchars((string) ($paymentInfo['transfer_content'] ?? '')) ?></span><button type="button" class="subscription-copy-button" data-copy-value="<?= htmlspecialchars((string) ($paymentInfo['transfer_content'] ?? '')) ?>">Sao chép</button></dd></div>
                <div><dt>Số tiền</dt><dd class="user-order-amount"><?= htmlspecialchars((string) ($paymentInfo['amount_display'] ?? number_format((float) ($order['total_amount'] ?? 0), 2, '.', ','))) ?></dd></div>
            </dl>
            <a href="/orders/detail?id=<?= (int) ($order['id'] ?? 0) ?>" class="user-payment-order-link">Xem trạng thái đơn hàng</a>
        </article>
    </div>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
<script>
(function () {
	var orderId = <?= (int) ($order['id'] ?? 0) ?>;
	var banner = document.getElementById('payment-status-banner');
	if (!orderId || !banner) return;

	var timer = setInterval(function () {
		fetch('/orders/status?id=' + orderId, { headers: { 'Accept': 'application/json' } })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data.status === 'completed') {
					clearInterval(timer);
					banner.textContent = 'Thanh toán thành công! Đang chuyển đến gói dịch vụ...';
					banner.hidden = false;
					banner.classList.add('is-success');
					setTimeout(function () {
						window.location.href = '/orders/detail?id=' + orderId;
					}, 1200);
				} else if (data.status === 'cancelled' || data.status === 'failed') {
					clearInterval(timer);
				}
			})
			.catch(function () {});
	}, 5000);
})();
</script>