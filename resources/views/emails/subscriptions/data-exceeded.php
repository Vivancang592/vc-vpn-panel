<?php
$planDisplay  = $plan_name ?? $planName ?? '';
$limitDisplay = $limitGb ?? $limit_gb ?? (isset($transfer_enable) ? round($transfer_enable / (1024 * 1024 * 1024), 2) : '');
$titleDisplay = $siteTitle ?? '';
$urlDisplay   = $siteUrl ?? '#';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 40px 10px; }
        .container { max-width: 550px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; }
        .header { background: linear-gradient(135deg, #ea580c, #c2410c); padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; color: #ffffff; }
        .body { padding: 32px 24px; text-align: center; }
        .alert-icon { font-size: 48px; margin-bottom: 12px; }
        .btn { display: inline-block; padding: 12px 28px; background: #f97316; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px; }
        .footer { background: #0f172a; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cảnh Báo Hết Dung Lượng Data</h1>
        </div>
        <div class="body">
            <div class="alert-icon">⚠️</div>
            <p style="font-size: 15px; color: #cbd5e1; line-height: 1.6;">Gói cước <strong><?= htmlspecialchars($planDisplay) ?></strong> của bạn đã sử dụng hết hạn mức lưu lượng<?= $limitDisplay !== '' ? ' <strong>' . htmlspecialchars((string)$limitDisplay) . ' GB</strong>' : '' ?>.</p>
            <p style="font-size: 13px; color: #94a3b8;">Kết nối VPN sẽ tạm thời ngắt cho đến khi gói cước gia hạn hoặc làm mới chu kỳ.</p>
            <a href="<?= htmlspecialchars($urlDisplay) ?>/plans" class="btn">Nâng Cấp / Gia Hạn Ngay</a>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($titleDisplay) ?>.
        </div>
    </div>
</body>
</html>