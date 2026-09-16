<?php
$pageTitle = 'Xác Nhận Gói Dịch Vụ - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$selectedPlan = isset($plan) && is_array($plan) ? $plan : [];
$bandwidth = (int) ($selectedPlan['bandwidth_limit_gb'] ?? 0);
$duration = max(1, (int) ($selectedPlan['duration_days'] ?? 30));
$devices = max(1, (int) ($selectedPlan['max_devices'] ?? 1));
$paymentGateways = isset($paymentGateways) && is_array($paymentGateways) ? $paymentGateways : [];
$pendingOrder = isset($pendingOrder) && is_array($pendingOrder) ? $pendingOrder : null;
$couponPreview = isset($couponPreview) && is_array($couponPreview) ? $couponPreview : null;
$price = (float) ($selectedPlan['price'] ?? 0);
$discountAmount = ($couponPreview && ($couponPreview['valid'] ?? false)) ? (float) ($couponPreview['discount_amount'] ?? 0) : 0;
$finalAmount = ($couponPreview && ($couponPreview['valid'] ?? false)) ? (float) ($couponPreview['final_amount'] ?? $price) : $price;
$currentCoupon = ($couponPreview['coupon_code'] ?? '');
ob_start();
?>

<section class="user-checkout-page">
	<header class="user-checkout-header">
		<p class="user-plans-kicker">XÁC NHẬN ĐĂNG KÝ</p>
		<h1>Thanh toán gói dịch vụ</h1>
		<p>Kiểm tra thông tin và chọn cổng thanh toán để hoàn tất đăng ký.</p>
	</header>

	<?php if (!empty($_SESSION['error'])): ?>
		<div class="user-plans-alert" role="alert"><?= htmlspecialchars($_SESSION['error']) ?></div>
		<?php unset($_SESSION['error']); ?>
	<?php endif; ?>

	<?php if ($pendingOrder !== null): ?>
		<div class="user-checkout-pending-notice" role="status">
			<div>
				<span class="user-checkout-pending-flag">Lưu ý:</span>
				Bạn đang có đơn <strong><?= htmlspecialchars((string) ($pendingOrder['order_code'] ?? '')) ?></strong> (¥<?= number_format((float) ($pendingOrder['total_amount'] ?? 0), 2, '.', ',') ?>) chưa thanh toán. Vui lòng
				<a href="/payment/checkout?order=<?= (int) ($pendingOrder['id'] ?? 0) ?>">hoàn tất</a> hoặc
				<form method="post" action="/orders/cancel">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
					<input type="hidden" name="order_id" value="<?= (int) ($pendingOrder['id'] ?? 0) ?>">
					<button type="submit">hủy đơn</button>
				</form>
				đơn này để tiếp tục mua gói mới.
			</div>
		</div>
	<?php endif; ?>

	<form method="post" action="/checkout" class="user-checkout-shell" data-checkout-form data-original-amount="<?= htmlspecialchars(number_format($price, 2, '.', '')) ?>">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
		<input type="hidden" name="plan_id" value="<?= (int) ($selectedPlan['id'] ?? 0) ?>">
		<input type="hidden" name="checkout_action" value="create_order" id="checkout-action-field">

		<div class="user-checkout-grid">
			<article class="glass-card user-checkout-tab user-checkout-info-tab">
				<div class="user-checkout-tab-head">
					<span class="user-checkout-tab-badge">TAB THÔNG TIN</span>
					<h2><?= htmlspecialchars($selectedPlan['name'] ?? 'Gói VPN') ?></h2>
				</div>

				<p class="user-checkout-description"><?= !empty($selectedPlan['description']) ? nl2br(htmlspecialchars($selectedPlan['description'])) : 'Gói kết nối VPN an toàn cho nhu cầu của bạn.' ?></p>

				<dl class="user-plan-features user-checkout-features">
					<div><dt>Thời hạn</dt><dd><?= $duration ?> ngày</dd></div>
					<div><dt>Dung lượng</dt><dd><?= $bandwidth > 0 ? number_format($bandwidth) . ' GB' : 'Không giới hạn' ?></dd></div>
					<div><dt>Thiết bị</dt><dd><?= $devices ?> thiết bị</dd></div>
				</dl>

				<div class="user-checkout-coupon-wrap">
					<label for="coupon_code">Mã giảm giá <span>(không bắt buộc)</span></label>
					<div class="user-checkout-coupon-row">
						<input id="coupon_code" name="coupon_code" type="text" class="glass-input" maxlength="50" autocomplete="off" placeholder="Nhập mã giảm giá" value="<?= htmlspecialchars((string)$currentCoupon) ?>">
						<button type="button" class="user-checkout-coupon-button" data-coupon-apply>Xác nhận mã</button>
					</div>
					<div class="user-checkout-coupon-feedback-row" <?= $discountAmount > 0 ? '' : 'hidden' ?> data-coupon-feedback-row>
						<p class="user-checkout-coupon-feedback is-success" data-coupon-feedback role="status" aria-live="polite"><?= $discountAmount > 0 ? 'Mã giảm giá đã được áp dụng.' : '' ?></p>
						<button type="button" class="user-checkout-coupon-remove" data-coupon-remove title="Bỏ mã giảm giá" aria-label="Bỏ mã giảm giá">&times;</button>
					</div>
				</div>

				<div class="user-checkout-total">
					<span>Tổng gốc</span>
					<strong>¥<?= number_format($price, 2, '.', ',') ?></strong>
				</div>
				<div class="user-checkout-total user-checkout-discount-total">
					<span>Giảm giá</span>
					<strong data-checkout-discount>-¥<?= number_format($discountAmount, 2, '.', ',') ?></strong>
				</div>
				<div class="user-checkout-total user-checkout-final-total">
					<span>Cần thanh toán</span>
					<strong data-checkout-final>¥<?= number_format($finalAmount, 2, '.', ',') ?></strong>
				</div>
			</article>

			<article class="glass-card user-checkout-tab user-checkout-pay-tab">
				<div class="user-checkout-tab-head">
					<span class="user-checkout-tab-badge">TAB THANH TOÁN</span>
					<h2>Chọn cổng thanh toán</h2>
				</div>

				<div class="user-checkout-gateway-list" role="radiogroup" aria-label="Cổng thanh toán">
					<?php if (!empty($paymentGateways)): ?>
						<?php foreach ($paymentGateways as $index => $gateway): ?>
							<label class="user-checkout-gateway-item">
								<input type="radio" name="payment_gateway" value="<?= htmlspecialchars((string)$gateway['id']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
								<span>
									<strong><?= htmlspecialchars((string)($gateway['name'] ?? 'Cổng thanh toán')) ?></strong>
									<small><?= htmlspecialchars((string)($gateway['hint'] ?? '')) ?></small>
								</span>
							</label>
						<?php endforeach; ?>
					<?php else: ?>
						<p class="user-checkout-no-gateway">Chưa có cổng thanh toán nào được bật trong phần cài đặt.</p>
					<?php endif; ?>
				</div>

				<p class="user-checkout-note">Sau khi xác nhận, đơn đăng ký sẽ được tạo và hệ thống xử lý theo cổng thanh toán bạn chọn.</p>
				<button type="submit" class="user-checkout-submit" onclick="document.getElementById('checkout-action-field').value='create_order';" <?= empty($paymentGateways) || $pendingOrder !== null ? 'disabled' : '' ?>>Xác nhận thanh toán</button>
			</article>
		</div>
	</form>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
