<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #172033; margin: 0; padding: 24px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe3ee; }
        .header { background: #0b5cab; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { padding: 24px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 18px 0; }
        .info-table td { padding: 10px; border-bottom: 1px solid #e5eaf1; font-size: 14px; }
        .info-table td:first-child { color: #5b6678; width: 40%; }
        .button { display: inline-block; margin-top: 8px; padding: 11px 16px; background: #0b5cab; color: #ffffff; text-decoration: none; }
        .footer { padding: 16px 24px; background: #f8fafc; color: #6b7280; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Có đơn hàng mới</h1>
        </div>
        <div class="body">
            <p>Hệ thống <strong><?= htmlspecialchars($siteTitle) ?></strong> vừa nhận một đơn hàng chờ thanh toán.</p>
            <table class="info-table">
                <tr><td>Mã đơn hàng</td><td><strong><?= htmlspecialchars($orderCode) ?></strong></td></tr>
                <tr><td>Khách hàng</td><td><?= htmlspecialchars($customerName) ?></td></tr>
                <tr><td>Email khách hàng</td><td><?= htmlspecialchars($customerEmail) ?></td></tr>
                <tr><td>Gói dịch vụ</td><td><?= htmlspecialchars($planName) ?></td></tr>
                <tr><td>Phương thức</td><td><?= htmlspecialchars($paymentMethod) ?></td></tr>
                <tr><td>Số tiền</td><td><strong><?= htmlspecialchars($amount) ?></strong></td></tr>
            </table>
            <?php if ($siteUrl !== ''): ?>
                <a class="button" href="<?= htmlspecialchars(rtrim($siteUrl, '/')) ?>/admin/orders/detail?id=<?= (int) $orderId ?>">Xem đơn hàng</a>
            <?php endif; ?>
        </div>
        <div class="footer">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?></div>
    </div>
</body>
</html>