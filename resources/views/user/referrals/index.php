<?php
$pageTitle = 'Giới thiệu - ' . ($settings['site_title'] ?? 'VC VPN PANEL');
$items = isset($commissions) && is_array($commissions) ? $commissions : [];
$refCode = (string) ($user['ref_code'] ?? '');
$minWithdrawal = (float) ($minWithdrawal ?? ($settings['min_withdrawal'] ?? 0));
$pendingWithdrawal = (float) ($pendingWithdrawal ?? 0);
$availableCommission = (float) ($availableCommission ?? max(0, (float)($user['commission_balance'] ?? 0) - $pendingWithdrawal));

// Tạo link giới thiệu đầy đủ
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$refLink = $refCode !== '' ? ($protocol . $host . '/register?ref=' . urlencode($refCode)) : '';

ob_start();
?>
<section class="user-record-page" style="width: 100%;">
	<header class="user-orders-header">
		<div class="user-orders-intro">
			<h2 class="u-plans-title">TIẾP THỊ LIÊN KẾT</h2>
			<p style="margin: 0;">
				Chia sẻ liên kết giới thiệu để nhận hoa hồng trọn đời từ các đơn hàng thành công.
			</p>
		</div>
		<div class="user-orders-title-row">
			<h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">
				<span>🤝 Tiếp thị liên kết & Hoa hồng</span>
			</h1>
		</div>
	</header>

	<?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?>
		<div class="user-record-alert <?= !empty($_SESSION['error']) ? 'is-error' : '' ?>">
			<span><?= htmlspecialchars($_SESSION['error'] ?? $_SESSION['success']) ?></span>
			<button type="button" class="alert-close-btn" onclick="this.parentElement.remove();" aria-label="Đóng">&times;</button>
		</div>
		<?php unset($_SESSION['success'], $_SESSION['error']); ?>
	<?php endif; ?>

	<!-- Thẻ hiển thị Mã và Link giới thiệu -->
	<div class="glass-card user-ref-card" style="padding: 1.25rem; margin-bottom: 1.25rem; border-radius: var(--radius-md, 12px); display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; align-items: center;">
		<div style="display: flex; flex-direction: column; gap: 0.35rem;">
			<span style="font-size: 0.8rem; color: var(--ios-text-secondary); font-weight: 600; text-transform: uppercase;">Mã giới thiệu của bạn</span>
			<div style="display: flex; align-items: center; gap: 0.5rem;">
				<strong style="font-size: 1.35rem; color: var(--ios-blue, #007aff); letter-spacing: 1px;"><?= htmlspecialchars($refCode ?: 'Chưa tạo') ?></strong>
				<?php if ($refCode !== ''): ?>
					<button type="button" class="glass-btn" data-copy-value="<?= htmlspecialchars($refCode) ?>" style="padding: 0.3rem 0.65rem; font-size: 0.78rem;">Sao chép mã</button>
				<?php endif; ?>
			</div>
		</div>

		<div style="display: flex; flex-direction: column; gap: 0.35rem;">
			<span style="font-size: 0.8rem; color: var(--ios-text-secondary); font-weight: 600; text-transform: uppercase;">Liên kết chia sẻ trực tiếp</span>
			<div style="display: flex; align-items: center; gap: 0.5rem;">
				<input type="text" readonly value="<?= htmlspecialchars($refLink) ?>" class="glass-input" style="flex: 1; padding: 0.45rem 0.65rem; font-size: 0.85rem; border-radius: 6px;">
				<?php if ($refLink !== ''): ?>
					<button type="button" class="glass-btn" data-copy-value="<?= htmlspecialchars($refLink) ?>" style="padding: 0.45rem 0.8rem; font-size: 0.85rem; font-weight: 600; white-space: nowrap;">Sao chép link</button>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Thống kê hoa hồng -->
	<div class="user-record-stats" style="margin-bottom: 1.25rem;">
		<div class="glass-card" style="border-left: 4px solid var(--ios-success, #34c759);">
			<span>Hoa hồng khả dụng</span>
			<strong style="color: var(--ios-success, #34c759);"><?= isset($formatMoney) ? $formatMoney($availableCommission) : number_format($availableCommission) . 'đ' ?></strong>
			<?php if ($pendingWithdrawal > 0): ?>
				<small style="color: var(--ios-warning, #ff9500); font-size: 0.75rem; margin-top: 0.2rem; display: block;">
					(Đang chờ duyệt: <?= isset($formatMoney) ? $formatMoney($pendingWithdrawal) : number_format($pendingWithdrawal) . 'đ' ?>)
				</small>
			<?php endif; ?>
		</div>
		<div class="glass-card" style="border-left: 4px solid var(--ios-blue, #007aff);">
			<span>Lượt đơn hàng hoa hồng</span>
			<strong style="color: var(--ios-blue, #007aff);"><?= (int) ($pagination['total'] ?? count($items)) ?></strong>
		</div>
	</div>

	<!-- Thể lệ hoa hồng (minh bạch) -->
	<div class="glass-card" style="padding: 1.25rem; margin-bottom: 1.25rem; border-radius: var(--radius-md, 12px);">
		<div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.85rem;">
			<h2 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--ios-text);">📖 Thể lệ hoa hồng</h2>
			<a class="glass-btn" href="/withdrawals" style="padding: 0.45rem 0.9rem; font-size: 0.85rem; white-space: nowrap;">Lịch sử rút tiền</a>
		</div>
		<div class="glass-card user-record-table-wrap u-m0">
			<table class="user-record-table">
				<thead>
					<tr>
						<th>Hạng mục</th>
						<th>Quy định</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td data-label="Hạng mục">Tỷ lệ hoa hồng</td>
						<td data-label="Quy định"><?= htmlspecialchars(rtrim(rtrim(number_format((float) ($settings['referral_commission_rate'] ?? 10), 2), '0'), '.')) ?>% giá trị mỗi đơn hàng thành công</td>
					</tr>
					<tr>
						<td data-label="Hạng mục">Thời điểm cộng</td>
						<td data-label="Quy định">Ngay khi đơn hàng của người được giới thiệu được xác nhận thanh toán</td>
					</tr>
					<tr>
						<td data-label="Hạng mục">Rút về số dư</td>
						<td data-label="Quy định">Tối thiểu <?= isset($formatMoney) ? $formatMoney($minWithdrawal) : number_format($minWithdrawal) . 'đ' ?> — cộng ngay tức thì, không cần duyệt</td>
					</tr>
					<tr>
						<td data-label="Hạng mục">Rút về ngân hàng</td>
						<td data-label="Quy định">Tối thiểu <?= isset($formatMoney) ? $formatMoney($minWithdrawal) : number_format($minWithdrawal) . 'đ' ?> — duyệt trong 24–48 giờ làm việc</td>
					</tr>
					<tr>
						<td data-label="Hạng mục">Hoa hồng khả dụng</td>
						<td data-label="Quy định">Số dư hoa hồng trừ đi các yêu cầu đang chờ duyệt</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Form Yêu Cầu Rút Hoa Hồng -->
	<div id="withdraw-form-card" class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem; border-radius: var(--radius-md, 12px);">
		<h2 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.4rem; color: var(--ios-text); display: flex; align-items: center; gap: 0.4rem;">
			<span>💳 Yêu cầu rút tiền hoa hồng</span>
		</h2>
		<p style="margin: 0 0 1.25rem; font-size: 0.85rem; color: var(--ios-text-secondary);">
			Bạn có thể chuyển hoa hồng trực tiếp vào số dư ví để mua gói hoặc rút về tài khoản ngân hàng (Tối thiểu: <?= isset($formatMoney) ? $formatMoney($minWithdrawal) : number_format($minWithdrawal) . 'đ' ?>).
		</p>

		<form method="post" action="/withdrawals/create" data-confirm-submit="Xác nhận gửi yêu cầu rút hoa hồng?" style="display: flex; flex-direction: column; gap: 1rem;">
			<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
				<div>
					<label for="withdraw-type" style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--ios-text);">Hình thức rút tiền</label>
					<select id="withdraw-type" name="withdraw_type" class="glass-input" style="width: 100%; padding: 0.65rem; border-radius: 8px; font-size: 0.9rem;" onchange="document.getElementById('bank-info-group').style.display = (this.value === 'bank' ? 'grid' : 'none');">
						<option value="balance">Rút về Số Dư Tài Khoản</option>
						<option value="bank" selected>Rút về Tài Khoản Ngân Hàng</option>
					</select>
				</div>

				<div>
					<label for="withdrawal-amount" style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--ios-text);">Số tiền muốn rút</label>
					<input class="glass-input" id="withdrawal-amount" name="amount" type="number" min="<?= htmlspecialchars((string) $minWithdrawal) ?>" max="<?= htmlspecialchars((string) $availableCommission) ?>" step="1" placeholder="Nhập số tiền..." required style="width: 100%; padding: 0.65rem; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
				</div>
			</div>

			<!-- Nhóm thông tin ngân hàng -->
			<div id="bank-info-group" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
				<div>
					<label for="bank-name" style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--ios-text);">Ngân hàng / Ví</label>
					<input class="glass-input" id="bank-name" name="bank_name" maxlength="100" placeholder="VD: MBBank, Vietcombank, Momo..." style="width: 100%; padding: 0.65rem; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
				</div>
				<div>
					<label for="bank-account-number" style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--ios-text);">Số tài khoản</label>
					<input class="glass-input" id="bank-account-number" name="bank_account_number" maxlength="50" placeholder="VD: 0987654321" style="width: 100%; padding: 0.65rem; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
				</div>
				<div>
					<label for="bank-account-name" style="display: block; font-weight: 700; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--ios-text);">Tên chủ tài khoản</label>
					<input class="glass-input" id="bank-account-name" name="bank_account_name" maxlength="100" placeholder="VD: NGUYEN VAN A" style="width: 100%; padding: 0.65rem; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
				</div>
			</div>

			<div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
				<button class="glass-btn" type="submit" style="padding: 0.65rem 1.5rem; font-size: 0.9rem; font-weight: 700; background: var(--ios-blue, #007aff); color: #fff; border: none; border-radius: 8px; cursor: pointer;">
					<span>🚀</span> Gửi yêu cầu
				</button>
			</div>
		</form>
	</div>

	<!-- Lịch sử hoa hồng -->
	<h2 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.85rem; color: var(--ios-text);">
		📋 Lịch sử nhận hoa hồng
	</h2>

	<?php if ($items): ?>
		<div class="glass-card user-record-table-wrap">
			<table class="user-record-table">
				<thead>
					<tr>
						<th>Người được giới thiệu</th>
						<th>Đơn hàng</th>
						<th>Tỷ lệ</th>
						<th>Hoa hồng nhận được</th>
						<th>Thời gian</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($items as $item): ?>
						<tr>
							<td data-label="Người được giới thiệu"><strong><?= htmlspecialchars($item['referred_username'] ?? '-') ?></strong></td>
							<td data-label="Đơn hàng"><code><?= htmlspecialchars($item['order_code'] ?? '-') ?></code></td>
							<td data-label="Tỷ lệ"><?= (float) ($item['commission_rate'] ?? 0) ?>%</td>
							<td data-label="Hoa hồng" class="user-record-amount">+<?= isset($formatMoney) ? $formatMoney($item['commission_amount'] ?? 0) : number_format((float)($item['commission_amount'] ?? 0)) . 'đ' ?></td>
							<td data-label="Thời gian"><?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else: ?>
		<div class="glass-card user-record-empty" style="text-align: center; padding: 2.5rem 1.5rem; border-radius: var(--radius-md, 12px);">
			<h2>Chưa có hoa hồng</h2>
			<p>Khi người bạn giới thiệu đăng ký và mua gói dịch vụ thành công, hoa hồng sẽ hiển thị tại đây.</p>
		</div>
	<?php endif; ?>
	<?php if (!empty($pagination)) require __DIR__ . '/../../components/pagination.php'; ?>
</section>
<?php $content = ob_get_clean(); $showSidebar = true; require_once __DIR__ . '/../../layouts/app.php'; ?>

