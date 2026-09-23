<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;color:#172033;font-family:Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dbe3ee;">
        <div style="padding:24px;background:#b42318;color:#ffffff;text-align:center;">
            <h1 style="margin:0;font-size:22px;">Đơn hàng đã bị hủy</h1>
        </div>
        <div style="padding:24px;">
            <p>Đơn hàng <strong><?= htmlspecialchars($orderCode) ?></strong> đã bị hủy.</p>
            <p><?= htmlspecialchars($reason) ?></p>
            <p>Bạn có thể tạo một đơn hàng mới khi cần.</p>
        </div>
        <div style="padding:16px 24px;background:#f8fafc;color:#6b7280;font-size:12px;text-align:center;">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>
        </div>
    </div>
</body>
</html>