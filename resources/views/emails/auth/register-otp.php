<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 10px; }
        .container { max-width: 550px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; }
        .header { background: linear-gradient(135deg, #2563eb, #1d4ed8); padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; color: #ffffff; }
        .header p { margin: 6px 0 0 0; font-size: 13px; color: #93c5fd; }
        .body { padding: 32px 24px; text-align: center; }
        .otp-box { background: #0f172a; border: 2px dashed #3b82f6; border-radius: 12px; padding: 20px; margin: 24px 0; display: inline-block; width: 80%; }
        .otp-code { font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #60a5fa; }
        .footer { background: #0f172a; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($siteTitle) ?></h1>
            <p style="color: #eaff00;"><?= htmlspecialchars($siteSubtitle) ?></p>
        </div>
        <div class="body">
            <h2 style="font-size: 18px; color: #f1f5f9; margin-top: 0;">Xác Minh Địa Chỉ Email</h2>
            <p style="font-size: 14px; color: #94a3b8; line-height: 1.6;">Cảm ơn bạn đã đăng ký tài khoản. Vui lòng sử dụng mã xác thực bên dưới để hoàn tất thủ tục đăng ký (Mã có hiệu lực trong 5 phút):</p>
            <div class="otp-box">
                <div class="otp-code"><?= htmlspecialchars($code) ?></div>
            </div>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email.</p>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>. All rights reserved.
        </div>
    </div>
</body>
</html>