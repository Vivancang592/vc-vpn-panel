<?php
$pageTitle = ($settings['site_name'] ?? 'VC VPN PANEL') . " - Dịch Vụ VPN Tốc Độ Cao & Bảo Mật Kết Nối";
$extraCss = 'home';
$extraJs = 'home';
ob_start();
?>

<?php
$groups = [];
foreach (($plans ?? []) as $plan) {
    $planGroups = $plan['group_names'] ?? [];
    if (!$planGroups) {
        $planGroups = ['Gói tiêu chuẩn'];
    }
    foreach ($planGroups as $group) {
        $groups[$group][] = $plan;
    }
}
$loggedIn = !empty($_SESSION['user_id']);
?>

<section class="home-hero home-reveal">
    <div class="home-hero-grid">
        <div>
            <span class="home-eyebrow"><span class="home-dot"></span>KẾT NỐI AN TOÀN, KHÔNG GIỚI HẠN</span>
            <h1 class="home-title">Internet riêng tư.<br><strong>Trải nghiệm liền mạch.</strong></h1>
            <p class="home-lead">Giải pháp VPN tốc độ cao giúp mã hóa dữ liệu, bảo vệ sự riêng tư và tối ưu hóa kết nối Internet trên mọi thiết bị iOS, Android, Windows, macOS.</p>
            <div class="home-actions">
                <a class="home-button home-button-primary" href="#bang-gia">Khám phá gói dịch vụ</a>
                <a class="home-button home-button-secondary" href="<?= $loggedIn ? '/user/dashboard' : '/register' ?>"><?= $loggedIn ? 'Bảng điều khiển' : 'Tạo tài khoản' ?></a>
            </div>
        </div>
        <aside class="home-status home-glass">
            <div class="home-status-title">Trạng thái hạ tầng VPN</div>
            <div class="home-status-item"><span><span class="home-dot"></span>Hệ thống máy chủ</span><strong>Ổn định 99.9%</strong></div>
            <div class="home-status-item"><span>Hỗ trợ kỹ thuật</span><strong>24/7</strong></div>
            <div class="home-status-item"><span>Hệ điều hành</span><strong>Đa nền tảng</strong></div>
        </aside>
    </div>
</section>

<!-- Tính Năng Nổi Bật (Cam Kết Chất Lượng) -->
<section class="home-features-section home-reveal">
    <div class="home-section-heading">
        <h2>Cam Kết Chất Lượng Dịch Vụ VPN</h2>
        <p>Hạ tầng mạng cao cấp giúp bạn làm việc từ xa, học trực tuyến và giải trí an toàn.</p>
    </div>
    <div class="home-features-grid">
        <article class="feature-box">
            <div class="feature-icon-wrapper">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
            </div>
            <h3>Giảm Độ Trễ & Tăng Tốc</h3>
            <p>Tối ưu hóa đường truyền quốc tế, giảm giật lag khi chơi game, xem video 4K và họp trực tuyến.</p>
        </article>
        <article class="feature-box">
            <div class="feature-icon-wrapper">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
            <h3>Bảo Mật & Mã Hóa Dữ Liệu</h3>
            <p>Mã hóa toàn bộ dữ liệu truy cập trên Wi-Fi công cộng, ngăn chặn theo dõi và rò rỉ thông tin cá nhân.</p>
        </article>
        <article class="feature-box">
            <div class="feature-icon-wrapper">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
            </div>
            <h3>Máy Chủ Quốc Tế Ổn Định</h3>
            <p>Hạ tầng máy chủ VPN thông suốt, băng thông không giới hạn với tốc độ tải lên/xuất cực nhanh.</p>
        </article>
        <article class="feature-box">
            <div class="feature-icon-wrapper">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
            </div>
            <h3>Hỗ Trợ Đa Nền Tảng</h3>
            <p>Dễ dàng cài đặt và kết nối ứng dụng trên iOS, Android, Windows, macOS cũng như Linux.</p>
        </article>
    </div>
</section>

<!-- Bảng Giá Dịch Vụ VPN -->
<section id="bang-gia" class="home-pricing home-reveal">
    <div class="home-pricing-header">
        <div>
            <h2>Bảng Giá Gói Dịch Vụ VPN</h2>
            <p>Lựa chọn gói cước VPN phù hợp nhất với nhu cầu sử dụng của bạn.</p>
        </div>
        <?php if ($groups): ?>
            <div class="home-tabs" role="tablist">
                <?php $i = 0; foreach ($groups as $name => $items): ?>
                    <button class="home-tab" type="button" role="tab" aria-controls="home-panel-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= htmlspecialchars($name) ?></button>
                <?php $i++; endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($groups): ?>
        <?php $i = 0; foreach ($groups as $name => $items): ?>
            <div class="home-tab-panel" id="home-panel-<?= $i ?>" <?= $i ? 'hidden' : '' ?>>
                <div class="home-plan-grid">
                    <?php foreach ($items as $plan): ?>
                        <?php $checkout = '/checkout?id=' . (int) $plan['id']; ?>
                        <article class="plan-card-vip">
                            <header class="plan-header">
                                <div class="plan-header-row">
                                    <h3 class="plan-title">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="9" cy="21" r="1"></circle>
                                            <circle cx="20" cy="21" r="1"></circle>
                                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                        </svg>
                                        <span><?= htmlspecialchars($plan['name']) ?></span>
                                    </h3>
                                    <div class="price-val">
                                        <?= isset($formatMoney) ? $formatMoney($plan['price']) : number_format($plan['price'], 0, ',', '.') . ' đ' ?>
                                    </div>
                                </div>
                                <div class="plan-duration-label">
                                    Thời hạn: <?= (int) $plan['duration_days'] ?> Ngày
                                </div>
                            </header>

                            <div class="feature-list">
                                <ul class="plan-feature-items">
    <?php if (!empty($plan['description'])): ?>
        <?php 
        $lines = explode("\n", trim($plan['description'])); 
        foreach ($lines as $line):
            if (trim($line) === '') continue;
            $parts = explode(':', $line, 2);
            $label = trim($parts[0]);
            $value = isset($parts[1]) ? ': ' . trim($parts[1]) : '';
            $lowerLabel = mb_strtolower($label);
            
            // Icon mặc định (Check)
            $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg>';

            // Quét đầy đủ các từ khóa theo đúng logic file mẫu welcome.php
            if (strpos($lowerLabel, 'server') !== false || strpos($lowerLabel, 'severs') !== false || strpos($lowerLabel, 'máy chủ') !== false) {
                // Icon Máy chủ / Server
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>';
            } elseif (strpos($lowerLabel, 'tốc độ') !== false || strpos($lowerLabel, 'speed') !== false) {
                // Icon Tốc độ
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>';
            } elseif (strpos($lowerLabel, 'hỗ trợ') !== false || strpos($lowerLabel, 'support') !== false) {
                // Icon Hỗ trợ (Cài đặt / Bánh răng)
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
            } elseif (strpos($lowerLabel, 'giao thức') !== false || strpos($lowerLabel, 'protocol') !== false) {
                // Icon Giao thức (Router / Kết nối)
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><rect x="2" y="14" width="20" height="8" rx="2"></rect><line x1="6" y1="6" x2="6" y2="14"></line><line x1="18" y1="6" x2="18" y2="14"></line><line x1="12" y1="2" x2="12" y2="14"></line></svg>';
            } elseif (strpos($lowerLabel, 'thanh toán') !== false || strpos($lowerLabel, 'payment') !== false) {
                // Icon Thanh toán (Thẻ thanh toán)
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>';
            } elseif (strpos($lowerLabel, 'hoàn tiền') !== false || strpos($lowerLabel, 'refund') !== false) {
                // Icon Hoàn tiền (Xoay vòng / Đổi trả)
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>';
            } elseif (strpos($lowerLabel, 'bảo mật') !== false || strpos($lowerLabel, 'mã hóa') !== false) {
                // Icon Bảo mật (Khiên bảo vệ)
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>';
            } elseif (strpos($lowerLabel, 'game') !== false) {
                // Icon Game / Chơi game
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><line x1="6" y1="12" x2="10" y2="12"></line><line x1="8" y1="10" x2="8" y2="14"></line><circle cx="15" cy="13" r="1"></circle><circle cx="18" cy="11" r="1"></circle><rect x="2" y="6" width="20" height="12" rx="6"></rect></svg>';
            } elseif (strpos($lowerLabel, 'thiết bị') !== false) {
                // Icon Thiết bị / Màn hình
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>';
            } elseif (strpos($lowerLabel, 'dung lượng') !== false || strpos($lowerLabel, 'băng thông') !== false) {
                // Icon Dung lượng / Cơ sở dữ liệu
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M21 19c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14"></path><path d="M21 5v14"></path></svg>';
            } elseif (strpos($lowerLabel, 'dịch vụ') !== false || strpos($lowerLabel, 'tương thích') !== false) {
                // Icon Dịch vụ / Quả địa cầu
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';
            } elseif (strpos($lowerLabel, 'đặc quyền') !== false) {
                // Icon Đặc quyền / Ngôi sao
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
            } elseif (strpos($lowerLabel, 'tiết kiệm') !== false) {
                // Icon Tiết kiệm / Thẻ giảm giá
                $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>';
            }
        ?>
            <li>
                <?= $iconSvg ?>
                <span><strong><?= htmlspecialchars($label) ?></strong><?= htmlspecialchars($value) ?></span>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Kết nối tốc độ cao không giới hạn</span>
        </li>
    <?php endif; ?>
</ul>

                                <div class="plan-spec-grid">
                                    <div class="spec-item">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#34c759" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                        <div>
                                            <span class="spec-label">Thiết bị</span>
                                            <span class="spec-value text-success"><?= (int) ($plan['max_devices'] ?? 1) ?> thiết bị</span>
                                        </div>
                                    </div>
                                    <div class="spec-item">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                        <div>
                                            <span class="spec-label">Lưu lượng</span>
                                            <span class="spec-value text-blue"><?= (int) ($plan['bandwidth_limit_gb'] ?? 0) ? $plan['bandwidth_limit_gb'] . ' GB' : 'Không giới hạn' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="plan-os-platforms">
                                    <span title="iOS / iPadOS / macOS">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.32c.62-.75 1.04-1.8 0.93-2.85-.9.04-1.99.6-2.63 1.35-.58.67-.98 1.74-.84 2.78 1.01.08 2.03-.53 2.54-1.28z"/></svg>
                                    </span>
                                    <span title="Android APK">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m11.4045-6.02l1.9973-3.4592a.416.416 0 00-.1521-.5676.416.416 0 00-.5676.1521l-2.0223 3.503C15.5898 8.169 13.8552 7.75 12 7.75c-1.8552 0-3.5898.419-5.1368 1.1997L4.8409 5.4467a.416.416 0 00-.5676-.1521.416.416 0 00-.1521.5676l1.9973 3.4592C2.6889 11.2867.3333 14.8872.3333 19h23.3334c0-4.1128-2.3556-7.7133-5.7887-9.6786"/></svg>
                                    </span>
                                    <span title="Windows PC">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M0 3.449L9.75 2.1v9.451H0zm10.55-1.509L24 0v11.4H10.55zM0 12.6h9.75v9.451L0 20.701zm10.55 0H24V24l-13.45-1.899z"/></svg>
                                    </span>
                                    <span title="Linux">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-3.1 0-5.5 2.2-5.5 5 0 1.3.4 2.5 1.2 3.4-2.1 1.1-3.7 3.2-3.7 5.6 0 2.2 1.1 4.1 2.8 5.3-.7 1.5-1.9 2.9-3.8 3.8-.3.1-.4.4-.3.7.1.3.4.4.7.3 3.3-1.6 5.3-3.7 6.2-5.7.8.2 1.7.4 2.6.4s1.8-.1 2.6-.4c.9 2 2.9 4.1 6.2 5.7.1.1.2.1.3.1.2 0 .4-.1.5-.3.1-.3 0-.6-.3-.7-1.9-.9-3.1-2.3-3.8-3.8 1.7-1.2 2.8-3.1 2.8-5.3 0-2.4-1.6-4.5-3.7-5.6.8-.9 1.2-2.1 1.2-3.4 0-2.8-2.4-5-5.5-5z"/></svg>
                                    </span>
                                </div>
                            </div>

                            <a class="btn-buy" href="<?= $loggedIn ? $checkout : '/login?redirect=' . urlencode($checkout) ?>">ĐĂNG KÝ GÓI NÀY</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php $i++; endforeach; ?>
    <?php else: ?>
        <div class="home-empty">Gói dịch vụ đang được cập nhật.</div>
    <?php endif; ?>
</section>

<!-- Câu Hỏi Thường Gặp (Accordion Mượt) -->
<section id="cau-hoi-thuong-gap" class="home-faq home-reveal" aria-labelledby="home-faq-title">
    <div class="home-faq-heading">
        <h2 id="home-faq-title">Câu Hỏi Thường Gặp</h2>
        <p>Giải đáp thắc mắc phổ biến về dịch vụ VPN và cách thức cài đặt.</p>
    </div>
    <div class="home-faq-list">
        <details class="home-faq-item" open>
            <summary>VPN hoạt động như thế nào và có an toàn không?</summary>
            <div class="faq-content">
                <div class="faq-content-inner">
                    <p>VPN (Virtual Private Network) khởi tạo đường truyền mã hóa giữa thiết bị của bạn và hệ thống máy chủ VPN. Mọi dữ liệu cá nhân truyền qua Internet sẽ được bảo vệ tuyệt đối khỏi hacker, nhà mạng và các nguy cơ theo dõi trên mạng Wi-Fi công cộng.</p>
                </div>
            </div>
        </details>
        <details class="home-faq-item">
            <summary>Tôi có thể sử dụng 1 tài khoản trên bao nhiêu thiết bị?</summary>
            <div class="faq-content">
                <div class="faq-content-inner">
                    <p>Mỗi gói VPN được thiết lập số lượng thiết bị kết nối đồng thời cụ thể (từ 1 đến nhiều thiết bị). Bạn có thể dễ dàng kiểm tra chi tiết thông số này ngay trong bảng giá hoặc bảng điều khiển sau khi đăng ký.</p>
                </div>
            </div>
        </details>
        <details class="home-faq-item">
            <summary>Sau khi thanh toán thành công, tôi nhận cấu hình ở đâu?</summary>
            <div class="faq-content">
                <div class="faq-content-inner">
                    <p>Đơn hàng sau khi thanh toán thành công sẽ được kích hoạt tự động. Mã cấu hình và liên kết tải ứng dụng sẽ xuất hiện ngay lập tức tại mục "Gói Đăng Ký Của Tôi" trong bảng điều khiển tài khoản.</p>
                </div>
            </div>
        </details>
        <details class="home-faq-item">
            <summary>Tôi cần hỗ trợ kỹ thuật khi không thể kết nối?</summary>
            <div class="faq-content">
                <div class="faq-content-inner">
                    <p>Đội ngũ kỹ thuật trực 24/7 luôn sẵn sàng hỗ trợ bạn. Bạn chỉ cần vào mục "Ticket Hỗ Trợ" trong trang cá nhân để gửi yêu cầu, kỹ thuật viên sẽ kiểm tra và hướng dẫn xử lý ngay lập tức.</p>
                </div>
            </div>
        </details>
    </div>
</section>

<?php
$content = '<div class="public-page home-page">' . ob_get_clean() . '</div>';
require_once __DIR__ . '/../layouts/app.php';
?>