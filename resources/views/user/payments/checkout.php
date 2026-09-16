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

    <div class="user-payment-checkout-grid">
        <article class="glass-card user-payment-qr-card">
            <span class="user-checkout-tab-badge"><?= htmlspecialchars((string) ($paymentInfo['name'] ?? 'Thanh toán')) ?></span>
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
                <div><dt>Nội dung chuyển khoản</dt><dd class="user-payment-transfer-content"><?= htmlspecialchars((string) ($paymentInfo['transfer_content'] ?? '')) ?></dd></div>
                <div><dt>Số tiền</dt><dd class="user-order-amount">¥<?= number_format((float) ($order['total_amount'] ?? 0), 2, '.', ',') ?></dd></div>
            </dl>
            <p class="user-payment-checkout-note">Thanh toán đúng số tiền và nội dung để hệ thống tự động xác nhận đơn hàng.</p>
            <a href="/orders/detail?id=<?= (int) ($order['id'] ?? 0) ?>" class="user-payment-order-link">Xem trạng thái đơn hàng</a>
        </article>
    </div>
</section>

<?php
$content = ob_get_clean();
$showSidebar = true;
require_once __DIR__ . '/../../layouts/app.php';
?>