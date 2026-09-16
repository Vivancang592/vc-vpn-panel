<?php
$planDisplay      = $plan_name ?? $planName ?? '';
$rawEndDate       = $end_date ?? $endDate ?? null;
$formattedEndDate = $rawEndDate ? date('d/m/Y H:i', strtotime($rawEndDate)) : '';
$daysDisplay      = $daysLeft ?? ($rawEndDate ? max(1, (int)ceil((strtotime($rawEndDate) - time()) / 86400)) : 0);
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
        .header { background: linear-gradient(135deg, #eab308, #ca8a04); padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; color: #ffffff; }
        .body { padding: 32px 24px; text-align: center; }
        .days-badge { font-size: 18px; font-weight: 700; color: #fef08a; background: #854d0e; padding: 8px 16px; border-radius: 20px; display: inline-block; margin: 12px 0; }
        .btn { display: inline-block; padding: 12px 28px; background: #eab308; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 700; margin-top: 20px; }
        .footer { background: #0f172a; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gói Cước Sắp Hết Hạn</h1>
        </div>
        <div class="body">
            <p style="font-size: 15px; color: #cbd5e1;">Gói cước <strong><?= htmlspecialchars($planDisplay) ?></strong> của bạn sắp hết hạn sử dụng.</p>
            <div class="days-badge">Chỉ còn <?= htmlspecialchars((string)$daysDisplay) ?> ngày (Hết hạn: <?= htmlspecialchars($formattedEndDate) ?>)</div>
            <p style="font-size: 13px; color: #94a3b8;">Vui lòng gia hạn trước thời gian trên để tránh gián đoạn dịch vụ VPN.</p>
            <a href="<?= htmlspecialchars($urlDisplay) ?>/subscriptions" class="btn">Gia Hạn Ngay</a>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($titleDisplay) ?>.
        </div>
    </div>
</body>
</html>