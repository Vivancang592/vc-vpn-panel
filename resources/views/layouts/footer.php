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

// Trạng thái đăng nhập + tên người dùng hiển thị rõ ràng trên khung chat.
$chatUser = is_array($currentUser ?? null) ? $currentUser : [];
$chatIsLoggedIn = !empty($chatUser['id']) || !empty($_SESSION['user_id']);
$chatUserName = trim((string) ($chatUser['name'] ?? ''));
if ($chatUserName === '') {
    $chatUserName = trim((string) ($chatUser['username'] ?? ''));
}
if ($chatUserName === '') {
    $chatUserName = trim((string) ($_SESSION['name'] ?? $_SESSION['username'] ?? ''));
}
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
                    <a href="<?= htmlspecialchars($fanpageHref) ?>" target="_blank" rel="noopener noreferrer" title="Facebook Fanpage" aria-label="Facebook Fanpage" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(24,119,242,.45); border-radius: 6px; color: #1877F2; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><path d="M24 12.073C24 5.446 18.627.073 12 .073S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073Z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($zaloHref !== ''): ?>
                    <a href="<?= htmlspecialchars($zaloHref) ?>" target="_blank" rel="noopener noreferrer" title="Zalo" aria-label="Zalo" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(0,104,255,.45); border-radius: 6px; color: #0068FF; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#0068FF"/><text x="12" y="16.2" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="8.4" font-weight="700" fill="#fff">Zalo</text></svg>
                    </a>
                <?php endif; ?>
                <?php if ($youtubeHref !== ''): ?>
                    <a href="<?= htmlspecialchars($youtubeHref) ?>" target="_blank" rel="noopener noreferrer" title="YouTube" aria-label="YouTube" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(255,0,0,.45); border-radius: 6px; color: #FF0000; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814ZM9.545 15.568V8.432L15.818 12l-6.273 3.568Z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($contactEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" title="Email" aria-label="Email" style="display: inline-grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(111,66,193,.35); border-radius: 6px; color: #6f42c1; text-decoration: none;">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                    </a>
                <?php endif; ?>
                <?php if ($wechatId !== ''): ?>
                    <span title="WeChat ID" style="display: inline-flex; align-items: center; gap: .3rem; padding: 0 .55rem; height: 34px; border: 1px solid rgba(7,193,96,.45); border-radius: 6px; color: #07C160; font-size: .8rem; font-weight: 600;">
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

<?php
// Bong bóng chat CHỈ hiển thị ở trang công khai + trang user.
// Trang admin ($extraJs = 'admin') ẩn hoàn toàn — admin xem hội thoại
// qua cab "Hội Thoại" trong Trung Tâm AI thay vì chat popup.
$showChatbot = $chatbotEnabled && (($extraJs ?? '') !== 'admin');
?>
<?php if ($showChatbot): ?>
    <div id="vc-chatbot" data-enabled="1" data-site-title="<?= htmlspecialchars($chatbotTitle) ?>" data-page="<?= htmlspecialchars((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)) ?>" data-logged-in="<?= $chatIsLoggedIn ? '1' : '0' ?>" data-user-name="<?= htmlspecialchars($chatUserName) ?>" style="position: relative; z-index: 2147483647;">
        <button id="vc-chatbot-toggle" type="button" aria-label="Mở trợ lý AI">💬</button>
        <div id="vc-chatbot-panel" hidden class="vc-chatbot-panel-wrapper">
            <div class="vc-chatbot-header">
                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                    <span style="display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: #34c759; box-shadow: 0 0 8px #34c759; flex: 0 0 auto;"></span>
                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Trợ lý AI - <?= htmlspecialchars($chatbotTitle) ?></span>
                </div>
                <button id="vc-chatbot-close" type="button" aria-label="Đóng">✕</button>
            </div>
            <div id="vc-chatbot-messages"></div>
            <form id="vc-chatbot-form" data-no-loader="true">
                <input id="vc-chatbot-input" type="text" placeholder="Nhập câu hỏi của bạn..." maxlength="500" autocomplete="off">
                <button type="submit">Gửi</button>
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