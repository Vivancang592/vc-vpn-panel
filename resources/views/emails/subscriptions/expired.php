<?php
$planDisplay      = $plan_name ?? $planName ?? '';
$rawEndDate       = $end_date ?? $endDate ?? null;
$formattedEndDate = $rawEndDate ? date('d/m/Y H:i', strtotime($rawEndDate)) : '';
$titleDisplay     = $siteTitle ?? '';
$urlDisplay       = $siteUrl ?? '#';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 10px; }
        .container { max-width: 550px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; }
        .header { background: linear-gradient(135deg, #475569, #334155); padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; color: #ffffff; }
        .body { padding: 32px 24px; text-align: center; }
        .btn { display: inline-block; padding: 12px 28px; background: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px; }
        .footer { background: #0f172a; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gói Cước Đã Hết Hạn</h1>
        </div>
        <div class="body">
            <p style="font-size: 15px; color: #cbd5e1;">Gói cước <strong><?= htmlspecialchars($planDisplay) ?></strong> của bạn đã hết hạn sử dụng vào ngày <strong><?= htmlspecialchars($formattedEndDate) ?></strong>.</p>
            <p style="font-size: 13px; color: #94a3b8;">Các kết nối qua Node VPN thuộc gói cước này đã tạm dừng hoạt động.</p>
            <a href="<?= htmlspecialchars($urlDisplay) ?>/plans" class="btn">Đăng Ký Gói Mới</a>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($titleDisplay) ?>.
        </div>
    </div>
</body>
</html>