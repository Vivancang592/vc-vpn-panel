<?php
$settings = isset($settings) && is_array($settings) ? $settings : [];
$siteTitle = (string)($settings['site_title'] ?? 'VC VPN 2027');

$fanpageUrl = trim((string)($settings['fanpage_url'] ?? ''));
$zaloUrl = trim((string)($settings['zalo_url'] ?? ''));
$youtubeUrl = trim((string)($settings['youtube_url'] ?? ''));
$wechatId = trim((string)($settings['wechat_id'] ?? ''));
$contactEmail = trim((string)($settings['contact_email'] ?? ''));

$hasContactLinks = $fanpageUrl !== '' || $zaloUrl !== '' || $youtubeUrl !== '' || $wechatId !== '' || $contactEmail !== '';

$normalizeUrl = static function (string $value, string $prefix = ''): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }
    return $prefix !== '' ? $prefix . ltrim($value, '/') : $value;
};

$fanpageHref = $normalizeUrl($fanpageUrl, 'https://');
$youtubeHref = $normalizeUrl($youtubeUrl, 'https://');
$zaloHref = $normalizeUrl($zaloUrl, 'https://zalo.me/');
$chatbotEnabled = (($settings['ai_chatbot_enabled'] ?? '1') === '1');
$chatbotTitle = trim((string) ($settings['site_title'] ?? 'VC VPN'));
$chatbotTitle = $chatbotTitle !== '' ? $chatbotTitle : 'VC VPN';
?>

<footer class="vc-footer">
    <div class="vc-footer-container">

        <div class="vc-footer-title-wrap">
            <h3 class="vc-footer-title">
                <?= htmlspecialchars($siteTitle) ?>
            </h3>
        </div>

        <?php if ($hasContactLinks): ?>
            <nav class="vc-footer-contact-links" aria-label="Nền tảng hỗ trợ" style="display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: .55rem; margin-top: .65rem;">
                <?php if ($fanpageHref !== ''): ?>
                    <a href="<?= htmlspecialchars($fanpageHref) ?>" target="_blank" rel="noopener noreferrer" title="Facebook Fanpage" aria-label="Facebook Fanpage" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(0,123,255,.35); border-radius: 6px; color: #0056b3; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><path d="M14 8h3V4h-3c-3.3 0-5 2-5 5v3H6v4h3v6h4v-6h3.1l.9-4H13V9c0-.6.4-1 1-1Z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($zaloHref !== ''): ?>
                    <a href="<?= htmlspecialchars($zaloHref) ?>" target="_blank" rel="noopener noreferrer" title="Zalo" aria-label="Zalo" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(40,167,69,.35); border-radius: 6px; color: #1e7e34; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><path d="M3 5h18v14H3z"/><path d="M7 8h5l-5 8h5M14 8v8M17 8v8" fill="#fff"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($youtubeHref !== ''): ?>
                    <a href="<?= htmlspecialchars($youtubeHref) ?>" target="_blank" rel="noopener noreferrer" title="YouTube" aria-label="YouTube" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(220,53,69,.35); border-radius: 6px; color: #dc3545; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><path d="M21 7.1a2.7 2.7 0 0 0-1.9-1.9C17.4 4.7 12 4.7 12 4.7s-5.4 0-7.1.5A2.7 2.7 0 0 0 3 7.1 28 28 0 0 0 2.5 12c0 1.7.2 3.3.5 4.9a2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.1.5 7.1.5s5.4 0 7.1-.5a2.7 2.7 0 0 0 1.9-1.9c.3-1.6.5-3.2.5-4.9s-.2-3.3-.5-4.9Z"/><path d="m10 15.5 5-3.5-5-3.5z" fill="#fff"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($contactEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" title="Email" aria-label="Email" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(111,66,193,.35); border-radius: 6px; color: #6f42c1; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($wechatId !== ''): ?>
                    <span title="WeChat ID" style="display: inline-flex; align-items: center; gap: .3rem; padding: 0 .55rem; height: 34px; border: 1px solid rgba(23,162,184,.35); border-radius: 6px; color: #117a8b; font-size: .8rem; font-weight: 600;">
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="currentColor"><path d="M9.5 3C5.36 3 2 5.8 2 9.25c0 1.92 1.04 3.63 2.68 4.77L4 17l3.22-1.61c.72.2 1.48.31 2.28.31 4.14 0 7.5-2.8 7.5-6.25S13.64 3 9.5 3Zm-2 5.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm4 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/><path d="M16.5 10C13.46 10 11 12.01 11 14.5S13.46 19 16.5 19c.56 0 1.1-.08 1.6-.23L21 20l-.86-2.2c1.13-.85 1.86-2.03 1.86-3.3 0-2.49-2.46-4.5-5.5-4.5Zm-1.5 3a.75.75 0 1 1 0 1.5A.75.75 0 0 1 15 13Zm3 0a.75.75 0 1 1 0 1.5A.75.75 0 0 1 18 13Z"/></svg>
                        <?= htmlspecialchars($wechatId) ?>
                    </span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <nav class="vc-footer-policy-links" aria-label="Chính sách" style="display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: .5rem; margin-top: .55rem;">
            <a href="/terms" class="vc-color-blue" style="padding: .35rem .62rem; border: 1px solid rgba(0,0,0,.14); border-radius: 5px; text-decoration: none; font-size: .72rem; font-weight: 700;">Điều Khoản Dịch Vụ</a>
            <a href="/privacy" class="vc-color-green" style="padding: .35rem .62rem; border: 1px solid rgba(0,0,0,.14); border-radius: 5px; text-decoration: none; font-size: .72rem; font-weight: 700;">Quyền Riêng Tư</a>
            <a href="/refund" class="vc-color-orange" style="padding: .35rem .62rem; border: 1px solid rgba(0,0,0,.14); border-radius: 5px; text-decoration: none; font-size: .72rem; font-weight: 700;">Chính Sách Hoàn Tiền</a>
        </nav>

        <div class="vc-footer-copyright-wrap">
            <p class="vc-footer-copyright-text">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<?php if ($chatbotEnabled): ?>
    <div id="vc-chatbot" data-enabled="1" data-page="<?= htmlspecialchars((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)) ?>">
        <button id="vc-chatbot-toggle" type="button" aria-label="Mở trợ lý AI" style="position: fixed; right: 16px; bottom: 16px; z-index: 2500; width: 56px; height: 56px; border: none; border-radius: 50%; cursor: pointer; background: linear-gradient(135deg, #0a84ff, #34c759); color: #fff; box-shadow: 0 10px 24px rgba(10,132,255,.32); font-size: 24px;">💬</button>
        <div id="vc-chatbot-panel" hidden style="position: fixed; right: 16px; bottom: 82px; z-index: 2500; width: min(360px, calc(100vw - 20px)); background: #fff; border: 1px solid rgba(0,0,0,.12); border-radius: 14px; box-shadow: 0 18px 32px rgba(0,0,0,.2); overflow: hidden;">
            <div style="padding: 10px 12px; background: linear-gradient(135deg, #0a84ff, #34c759); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: space-between;">
                <span>Tro ly AI - <?= htmlspecialchars($chatbotTitle) ?></span>
                <button id="vc-chatbot-close" type="button" aria-label="Đóng" style="border: none; background: transparent; color: #fff; font-size: 18px; cursor: pointer;">×</button>
            </div>
            <div id="vc-chatbot-messages" style="height: 300px; overflow-y: auto; padding: 10px; background: #f6f9ff;"></div>
            <form id="vc-chatbot-form" style="display: flex; gap: 8px; padding: 10px; border-top: 1px solid rgba(0,0,0,.08); background: #fff;">
                <input id="vc-chatbot-input" type="text" placeholder="Nhập câu hỏi của bạn..." style="flex: 1; border: 1px solid rgba(0,0,0,.15); border-radius: 8px; padding: 10px; font-size: 14px;" maxlength="500">
                <button type="submit" style="border: none; border-radius: 8px; background: #0a84ff; color: #fff; padding: 0 12px; cursor: pointer; font-weight: 600;">Gửi</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Luôn tải app.js với tham số xóa cache -->
<script src="/assets/js/app.js?v=<?= time() ?>"></script>

<!-- Chỉ tải extraJs nếu khác file app.js -->
<?php if (isset($extraJs) && $extraJs !== 'app'): ?>
    <script src="/assets/js/<?= $extraJs ?>.js?v=<?= time() ?>"></script>
<?php endif; ?>
</body>
</html>