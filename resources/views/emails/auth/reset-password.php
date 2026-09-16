<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 10px; }
        .container { max-width: 550px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; }
        .header { background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; color: #ffffff; }
        .body { padding: 32px 24px; text-align: center; }
        .otp-code { font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #f87171; background: #0f172a; border-radius: 10px; padding: 12px 24px; display: inline-block; margin: 16px 0; }
        .footer { background: #0f172a; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($siteTitle) ?></h1>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #e2ff02;">Khôi Phục Mật Khẩu Tài Khoản</p>
        </div>
        <div class="body">
            <p style="font-size: 14px; color: #94a3b8; line-height: 1.6;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Nhập mã OTP bên dưới để tiến hành thiết lập mật khẩu mới:</p>
            <div class="otp-code"><?= htmlspecialchars($code) ?></div>
            <p style="font-size: 13px; color: #64748b;">Mã OTP có hiệu lực trong vòng 5 phút.</p>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>.
        </div>
    </div>
</body>
</html>