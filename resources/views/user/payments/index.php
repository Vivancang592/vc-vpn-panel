<?php
$pageTitle = 'Giao dịch - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$items = is_array($payments ?? null) ? $payments : [];
$labels = ['pending' => 'Chờ xử lý', 'success' => 'Thành công', 'failed' => 'Thất bại'];
ob_start();
?>
<section class="user-record-page">
	<header class="user-orders-header">
		<div class="user-orders-intro"><h2 class="u-plans-title">LỊCH SỬ GIAO DỊCH</h2><p>Theo dõi các khoản nạp tiền, mua gói và gia hạn.</p></div>
		<div class="user-orders-title-row"><h1>Giao dịch</h1><a class="glass-btn" href="/checkout?type=deposit">Nạp tiền</a></div>
	</header>
	<?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?><div class="user-record-alert <?= !empty($_SESSION['error']) ? 'is-error' : '' ?>"><span><?= htmlspecialchars($_SESSION['error'] ?? $_SESSION['success']) ?></span><button type="button" class="alert-close-btn" onclick="this.parentElement.remove();" aria-label="Đóng">&times;</button></div><?php unset($_SESSION['success'], $_SESSION['error']); endif; ?>
	<?php if ($items): ?><div class="glass-card user-record-table-wrap"><table class="user-record-table"><thead><tr><th>Mã giao dịch</th><th>Loại</th><th>Số tiền</th><th>Trạng thái</th><th>Thời gian</th><th style="text-align: right;">Thao tác</th></tr></thead><tbody><?php foreach ($items as $item): ?><?php $status = $item['status'] ?? 'pending'; $isDeposit = ($item['type'] ?? '') === 'deposit'; ?><tr><td data-label="Mã giao dịch"><code><?= htmlspecialchars($item['transaction_id'] ?? ('#' . ($item['id'] ?? ''))) ?></code></td><td data-label="Loại"><?= $isDeposit ? 'Nạp tiền' : (!empty($item['subscription_id']) ? 'Gia hạn' : 'Thanh toán') ?></td><td data-label="Số tiền" class="user-record-amount"><?= $formatMoney($item['amount'] ?? 0) ?></td><td data-label="Trạng thái"><span class="user-record-status is-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($labels[$status] ?? $status) ?></span></td><td data-label="Thời gian"><?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?></td><td class="user-record-action" style="text-align: right;"><?php if ($status === 'pending'): ?><div class="action-dropdown"><button type="button" class="action-btn" title="Thao tác">⋮</button><div class="action-menu" style="min-width: 155px; white-space: nowrap;"><a href="/payment/checkout?<?= $isDeposit ? 'deposit=' . (int) $item['id'] : (!empty($item['subscription_id']) ? 'renewal=' . (int) $item['subscription_id'] . '&payment=' . (int) $item['id'] : 'order=' . (int) $item['order_id']) ?>" class="action-item" style="color: var(--ios-blue);"><span>💳</span> Thanh toán ngay</a><?php if ($isDeposit): ?><form method="post" action="/payments/deposit/cancel" style="margin: 0;" data-confirm-submit="Bạn có chắc muốn hủy giao dịch nạp tiền này?"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="payment_id" value="<?= (int) $item['id'] ?>"><button type="submit" class="action-item cancel" style="color: var(--ios-danger);"><span>❌</span> Hủy giao dịch</button></form><?php endif; ?></div></div><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="glass-card user-record-empty"><h2>Chưa có giao dịch</h2><p>Các khoản nạp tiền và thanh toán của bạn sẽ xuất hiện tại đây.</p></div><?php endif; ?>
</section>

<script>
var paymentIds = <?= json_encode(
	array_map(
		static fn(array $item): int => (int) ($item['id'] ?? 0),
		$items
	)
) ?>;

document.querySelectorAll('.user-record-table').forEach(function (table) {
	var headerRow = table.querySelector('thead tr');

	if (!headerRow || headerRow.querySelector('[data-payment-id-column]')) {
		return;
	}

	var header = document.createElement('th');
	header.textContent = 'ID';
	header.setAttribute('data-payment-id-column', '');
	headerRow.insertBefore(header, headerRow.firstChild);

	table.querySelectorAll('tbody tr').forEach(function (row, index) {
		var transactionCell = row.querySelector('td');

		if (!transactionCell) {
			return;
		}

		var idCell = document.createElement('td');
		idCell.setAttribute('data-label', 'ID');
		idCell.textContent = '#' + String(paymentIds[index] || 0);
		row.insertBefore(idCell, transactionCell);
	});
});
</script>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>
