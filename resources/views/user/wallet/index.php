<?php
$minimumDeposit = (float)($settings['min_deposit'] ?? $settings['min_deposit_amount'] ?? 10);
$formatAmount = isset($formatMoney)
	? $formatMoney
	: static fn($amount): string => number_format((float)$amount, 2) . ' ' . ($settings['currency_symbol'] ?? '¥');

ob_start();
?>

<div class="wallet-page-header">
	<h2 style="color: #020af4; font-family: emoji; text-align: center;">VÍ TIỀN CỦA BẠN</h2>
	<p style="text-align: center;">Theo dõi số dư và nạp tiền vào tài khoản.</p>
</div>

<?php if (!empty($_SESSION['error'])): ?>
	<div class="wallet-alert error">
		<?= htmlspecialchars($_SESSION['error']) ?>
	</div>
	<?php unset($_SESSION['error']); ?>
<?php endif; ?>

<section class="wallet-overview" aria-label="Tổng quan ví tiền">
	<div class="glass-card wallet-balance-card">
		<div class="wallet-balance-label">Số dư khả dụng</div>
		<div class="wallet-balance-value"><?= $formatAmount($user['balance'] ?? 0) ?></div>
		<p class="wallet-balance-note">Số dư được dùng để thanh toán và gia hạn dịch vụ VPN.</p>
	</div>
	<div class="glass-card wallet-history-card">
		<div>
			<h2>Lịch sử giao dịch</h2>
			<p>Xem các khoản nạp tiền và giao dịch đã được ghi nhận.</p>
		</div>
		<a href="/payments" class="glass-btn" style="text-align: center; text-decoration: none;">Xem giao dịch</a>
	</div>
</section>

<section class="glass-card wallet-deposit-card" aria-labelledby="wallet-deposit-heading">
	<h2 id="wallet-deposit-heading">Nạp tiền vào ví</h2>
	<p>Chọn nhanh số tiền hoặc nhập số tiền bạn muốn nạp.</p>

	<form action="/checkout" method="GET">
		<input type="hidden" name="type" value="deposit">

		<div class="wallet-quick-amounts" aria-label="Chọn nhanh số tiền nạp">
			<?php foreach ([50, 100, 200, 500] as $amount): ?>
				<button type="button" class="wallet-quick-amount" data-amount="<?= $amount ?>"><?= $formatAmount($amount) ?></button>
			<?php endforeach; ?>
		</div>

		<div class="wallet-form-row">
			<div class="wallet-form-group">
				<label for="deposit-amount">Số tiền nạp</label>
				<input class="glass-input" type="number" id="deposit-amount" name="amount" min="<?= htmlspecialchars((string)$minimumDeposit) ?>" step="0.01" required placeholder="Nhập số tiền">
				<small>Tối thiểu <?= $formatAmount($minimumDeposit) ?></small>
			</div>
			<button type="submit" class="glass-btn">Tiếp tục nạp tiền</button>
		</div>
	</form>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>
