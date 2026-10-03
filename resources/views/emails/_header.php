<?php
/**
 * Frame header dùng chung cho toàn bộ email (MailService::renderTemplate).
 * Biến bắt buộc: $mailTitle. Tùy chọn: $accent (màu chủ đề), $mailBadge (nhãn).
 * Biến từ renderTemplate: $siteTitle, $siteSubtitle, $siteUrl, $contactEmail.
 */
$accent     = $accent ?? '#0b5cab';
$mailTitle  = $mailTitle ?? ($siteTitle ?? '');
$mailBadge  = $mailBadge ?? '';
$siteTitle  = $siteTitle ?? 'VC VPN PANEL';
$esc        = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 5px; background: #f4f7fb; color: #1f2937; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .mail-card { width: 100%; margin: 0; background: transparent; border: none; border-radius: 0; }
        .mail-top { background: <?= $accent ?>; padding: 24px 20px; text-align: center; border-radius: 10px; }
        .mail-brand { margin: 0 0 6px; font-size: 11.5px; letter-spacing: .14em; text-transform: uppercase; color: rgba(255, 255, 255, .85); }
        .mail-top h1 { margin: 0; font-size: 21px; color: #ffffff; line-height: 1.35; }
        .mail-body { padding: 26px 24px; font-size: 14.5px; line-height: 1.65; }
        .mail-badge { display: inline-block; margin-bottom: 14px; padding: 5px 13px; border-radius: 999px; background: <?= $accent ?>14; color: <?= $accent ?>; font-size: 11.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; border: 1px solid <?= $accent ?>33; }
        .mail-greet { margin: 0 0 10px; font-size: 15.5px; font-weight: 700; color: #111827; }
        .mail-lead { margin: 0 0 6px; color: #374151; }
        .mail-info { width: 100%; border-collapse: collapse; margin: 16px 0; border: 1px solid #e8edf4; border-radius: 10px; overflow: hidden; }
        .mail-info td { padding: 11px 14px; font-size: 14px; border-bottom: 1px solid #eef2f7; background: #fbfcfe; }
        .mail-info tr:last-child td { border-bottom: none; }
        .mail-info td.k { color: #6b7280; width: 44%; }
        .mail-info td.v { font-weight: 700; color: #111827; }
        .mail-tips { margin: 14px 0 0; padding-left: 1.2rem; color: #4b5563; font-size: 13.5px; }
        .mail-tips li { margin: 5px 0; }
        .mail-btn { display: inline-block; margin-top: 18px; padding: 12px 26px; background: <?= $accent ?>; color: #ffffff; text-decoration: none; border-radius: 9px; font-weight: 700; font-size: 14.5px; }
        .mail-note { margin-top: 18px; padding: 12px 14px; background: #f8fafc; border: 1px solid #e8edf4; border-left: 3px solid <?= $accent ?>; border-radius: 8px; color: #4b5563; font-size: 13px; }
        .mail-otp { margin: 18px 0 6px; text-align: center; }
        .mail-otp .code { display: inline-block; padding: 14px 30px; background: #f1f5f9; border: 2px dashed <?= $accent ?>; border-radius: 12px; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: <?= $accent ?>; }
        .mail-otp .hint { margin-top: 8px; font-size: 12.5px; color: #6b7280; }
        .mail-footer { padding: 18px 20px; margin-top: 16px; background: #f8fafc; border-top: 1px solid #e8edf4; border-radius: 10px; color: #6b7280; font-size: 12px; line-height: 1.75; text-align: center; }
        .mail-footer a { color: #0b5cab; text-decoration: none; }
    </style>
</head>
<body>
<div class="mail-card">
    <div class="mail-top">
        <p class="mail-brand"><?= $esc($siteTitle) ?><?= !empty($siteSubtitle) ? ' · ' . $esc($siteSubtitle) : '' ?></p>
        <h1><?= $esc($mailTitle) ?></h1>
    </div>
    <div class="mail-body">
        <?php if ($mailBadge !== ''): ?>
            <span class="mail-badge"><?= $esc($mailBadge) ?></span>
        <?php endif; ?>
