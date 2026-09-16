<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 10px; }
        .container { max-width: 550px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; }
        .header { background: linear-gradient(135deg, #16a34a, #15803d); padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; color: #ffffff; }
        .body { padding: 32px 24px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #0f172a; border-radius: 10px; overflow: hidden; }
        .info-table td { padding: 12px 16px; font-size: 14px; border-bottom: 1px solid #1e293b; }
        .info-table tr:last-child td { border-bottom: none; }
        .btn { display: block; width: 200px; margin: 24px auto 0; padding: 12px 0; text-align: center; background: #22c55e; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { background: #0f172a; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kích Hoạt Dịch Vụ Thành Công</h1>
        </div>
        <div class="body">
            <p style="font-size: 14px; color: #94a3b8;">Đơn hàng của bạn đã được thanh toán và kích hoạt thành công trên hệ thống <strong><?= htmlspecialchars($siteTitle) ?></strong>.</p>
            <table class="info-table">
                <tr>
                    <td style="color: #64748b;">Mã đơn hàng:</td>
                    <td style="font-weight: 600; color: #f8fafc;"><?= htmlspecialchars($orderCode) ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Gói cước:</td>
                    <td style="font-weight: 600; color: #4ade80;"><?= htmlspecialchars($planName) ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Số tiền:</td>
                    <td style="font-weight: 600; color: #f8fafc;"><?= htmlspecialchars($amount) ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Hạn sử dụng:</td>
                    <td style="font-weight: 600; color: #f8fafc;"><?= htmlspecialchars($endDate) ?></td>
                </tr>
            </table>
            <a href="<?= htmlspecialchars($siteUrl ?? '#') ?>/subscriptions" class="btn">Quản Lý Gói Cước</a>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>.
        </div>
    </div>
</body>
</html>