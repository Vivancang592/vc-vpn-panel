<?php
// Bắt đầu lưu bộ đệm nội dung
ob_start();

// Hàm định dạng tiền tệ linh hoạt theo Cài Đặt Hệ Thống
$formatPrice = function($amount) use ($settings, $formatMoney) {
    if (isset($formatMoney) && is_callable($formatMoney)) {
        return $formatMoney($amount);
    }
    $symbol = $settings['currency_symbol'] ?? 'đ';
    $position = $settings['currency_position'] ?? 'right';
    $decimals = (int)($settings['currency_decimals'] ?? 0);
    $formatted = number_format((float)$amount, $decimals, '.', ',');
    return $position === 'left' ? $symbol . $formatted : $formatted . ' ' . $symbol;
};

// Tính toán số lượng gói đang hoạt động an toàn
$activeSubCount = 0;
if (!empty($subscriptions) && is_array($subscriptions)) {
    foreach ($subscriptions as $sub) {
        if (isset($sub['status']) && $sub['status'] === 'active') {
            $activeSubCount++;
        }
    }
}

// 1. Chỉ lọc ra các bài viết thuộc loại THÔNG BÁO (notice / faq)
$noticePosts = [];
if (!empty($posts) && is_array($posts)) {
    foreach ($posts as $p) {
        $type = strtolower($p['type'] ?? '');
        if ($type === 'faq' || $type === 'notice') {
            $noticePosts[] = $p;
        }
    }
}

// 2. Lấy ID nhóm máy chủ đầu tiên & lọc gói thuộc nhóm đầu tiên
$firstGroupId = (!empty($serverGroups) && is_array($serverGroups)) ? ($serverGroups[0]['id'] ?? 'all') : 'all';

$firstGroupPlans = [];
if (!empty($plans) && is_array($plans)) {
    foreach ($plans as $plan) {
        if ($firstGroupId === 'all') {
            $firstGroupPlans[] = $plan;
        } else {
            $groupIds = [];
            if (!empty($plan['group_id'])) {
                if (is_array($plan['group_id'])) {
                    $groupIds = $plan['group_id'];
                } else {
                    $decoded = json_decode($plan['group_id'], true);
                    if (is_array($decoded)) $groupIds = $decoded;
                }
            }
            if (empty($groupIds) || in_array((string)$firstGroupId, array_map('strval', $groupIds))) {
                $firstGroupPlans[] = $plan;
            }
        }
    }
}

// Hàm sinh ảnh đại diện SVG ngẫu nhiên chuẩn responsive
function getNoticeFallbackThumb($id) {
    $colors = [
        ['#007aff', '#5856d6'],
        ['#34c759', '#30b0c7'],
        ['#ff9500', '#ff2d55'],
        ['#af52de', '#ff2d55'],
        ['#5856d6', '#007aff']
    ];
    $pair = $colors[(int)$id % count($colors)];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250" viewBox="0 0 400 250" preserveAspectRatio="xMidYMid slice">'
         . '<defs><linearGradient id="g'.$id.'" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="'.$pair[0].'"/>'
         . '<stop offset="100%" stop-color="'.$pair[1].'"/>'
         . '</linearGradient></defs>'
         . '<rect width="100%" height="100%" fill="url(#g'.$id.')"/>'
         . '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="-apple-system, sans-serif" font-size="28" font-weight="bold">THÔNG BÁO</text>'
         . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
?>

<div class="dashboard-header-welcome moonlit-welcome">
    <span class="festival-kicker">KET NOI BAO MAT</span>
    <h1>Chào mừng trở lại, <?= htmlspecialchars($user['username'] ?? 'Thành viên') ?>!</h1>
    <p>Quản lý dịch vụ VPN và theo dõi tài khoản của bạn</p>
</div>

<!-- Phần 2: Slide bài viết thông báo -->
<?php if (!empty($noticePosts)): ?>
<div style="margin-top: 1.5rem;">
    <h3 class="u-section-title">📢 Thông Báo Hệ Thống</h3>
    <div class="glass-card tutorial-slider-container">
        <div class="tutorial-slider-wrapper">
            <div class="tutorial-slider" id="tutorialSlider">
                <?php foreach ($noticePosts as $index => $post): 
                    $thumbUrl = '';
                    
                    if (!empty($post['thumbnail'])) {
                        $thumbUrl = $post['thumbnail'];
                    } elseif (!empty($post['content'])) {
                        $rawContent = htmlspecialchars_decode($post['content']);
                        if (preg_match('/<!--thumbnail:(.*?)-->/i', $rawContent, $mThumb)) {
                            $thumbUrl = trim($mThumb[1]);
                        } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawContent, $mImg)) {
                            $thumbUrl = trim($mImg[1]);
                        }
                    }
                    
                    if (empty($thumbUrl)) {
                        $thumbUrl = getNoticeFallbackThumb($post['id'] ?? $index);
                    }

                    $cleanDesc = strip_tags(htmlspecialchars_decode($post['content'] ?? ''));
                    if (mb_strlen($cleanDesc, 'UTF-8') > 120) {
                        $cleanDesc = mb_substr($cleanDesc, 0, 120, 'UTF-8') . '...';
                    }
                ?>
                    <div class="tutorial-card" data-index="<?= $index ?>">
                        <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="Thumbnail" class="tutorial-thumb">
                        <div class="tutorial-content">
                            <div>
                                <span class="tutorial-badge">THÔNG BÁO</span>
                                <h4 class="tutorial-title" title="<?= htmlspecialchars($post['title'] ?? '') ?>"><?= htmlspecialchars($post['title'] ?? '') ?></h4>
                                <div class="tutorial-desc"><?= htmlspecialchars($cleanDesc) ?></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                                <span style="font-size: 0.75rem; color: var(--ios-text-secondary);">
                                    <?= isset($post['created_at']) ? date('d/m/Y', strtotime($post['created_at'])) : '' ?>
                                </span>
                                <a href="/user/articles/detail?slug=<?= urlencode($post['slug'] ?? '') ?>" class="glass-btn" style="padding: 0.4rem 0.9rem; font-size: 0.8rem; text-decoration: none;">Xem chi tiết &rarr;</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="tutorial-dots" id="tutorialDots">
            <?php foreach ($noticePosts as $index => $post): ?>
                <span class="tutorial-dot <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>)"></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Phần 3: 4 thẻ thống kê chuẩn đẹp -->
<div style="margin-top: 1.5rem;">
    <h3 class="u-section-title">📊 Thống Kê Tài Khoản</h3>
    <div class="dashboard-grid-4">
        <div class="glass-card stat-card-item festival-stat-card">
            <span class="festival-stat-icon festival-stat-moon" aria-hidden="true"></span>
            <div>
                <div class="stat-label">Gói đang chạy</div>
                <div class="stat-value" style="color: var(--ios-success);"><?= $activeSubCount ?></div>
            </div>
            <a href="/subscriptions" class="stat-link">Xem chi tiết</a>
        </div>
        <div class="glass-card stat-card-item festival-stat-card">
            <span class="festival-stat-icon festival-stat-lantern" aria-hidden="true"></span>
            <div>
                <div class="stat-label">Số lượng đơn hàng</div>
                <div class="stat-value"><?= is_array($orders ?? null) ? count($orders) : 0 ?></div>
            </div>
            <a href="/orders" class="stat-link">Xem chi tiết</a>
        </div>
        <div class="glass-card stat-card-item festival-stat-card">
            <span class="festival-stat-icon festival-stat-star" aria-hidden="true"></span>
            <div>
                <div class="stat-label">Ticket hỗ trợ</div>
                <div class="stat-value"><?= is_array($tickets ?? null) ? count($tickets) : 0 ?></div>
            </div>
            <a href="/tickets" class="stat-link">Xem chi tiết</a>
        </div>
        <div class="glass-card stat-card-item festival-stat-card">
            <span class="festival-stat-icon festival-stat-coin" aria-hidden="true"></span>
            <div>
                <div class="stat-label">Số dư tài khoản</div>
                <div class="stat-value"><?= $formatPrice($user['balance'] ?? 0) ?></div>
            </div>
            <a href="/wallet" class="stat-link">Xem chi tiết</a>
        </div>
    </div>
</div>

<!-- Phần 4: Bảng giá mặc định thuộc nhóm đầu tiên -->
<div style="margin-top: 1.5rem;">
    <h3 class="u-section-title u-section-title--lg">Bảng giá gói dịch vụ</h3>
    <div class="u-section-note">
        💡 Bạn muốn tham khảo thêm nhiều gói cước hơn, hãy truy cập trang <a href="/user/plans" style="color: var(--ios-blue); text-decoration: none;">Cửa Hàng</a> của chúng tôi.
    </div>

    <div class="plans-grid" id="plansGrid">
        <?php if (!empty($firstGroupPlans)): ?>
            <?php foreach ($firstGroupPlans as $plan): ?>
                <div class="plan-item-card glass-card" style="display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                    <div>
                        <!-- Tên gói & Giá cước -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15));">
                            <div class="plan-name" style="margin: 0; font-weight: 600;"><span class="plan-name-icon" aria-hidden="true">🛒</span><?= htmlspecialchars($plan['name'] ?? '') ?></div>
                            <div class="plan-price" style="margin: 0; text-align: right; white-space: nowrap;">
                                <?= $formatPrice($plan['price'] ?? 0) ?>
                                <span style="font-size: 0.8rem; font-weight: normal;">/ <?= (int)($plan['duration_days'] ?? 30) ?> ngày</span>
                            </div>
                        </div>

                        <!-- Danh sách Content mô tả -->
                        <ul class="info-list" style="list-style: none; padding: 0; margin: 0 0 0.75rem 0; display: flex; flex-direction: column; gap: 0.4rem; text-align: left !important; width: 100%;">
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
                                    $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><polyline points="20 6 9 17 4 12"></polyline></svg>';

                                    // Quét từ khóa từ mô tả gói
                                    if (strpos($lowerLabel, 'server') !== false || strpos($lowerLabel, 'severs') !== false || strpos($lowerLabel, 'máy chủ') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>';
                                    } elseif (strpos($lowerLabel, 'tốc độ') !== false || strpos($lowerLabel, 'speed') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>';
                                    } elseif (strpos($lowerLabel, 'hỗ trợ') !== false || strpos($lowerLabel, 'support') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
                                    } elseif (strpos($lowerLabel, 'giao thức') !== false || strpos($lowerLabel, 'protocol') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><rect x="2" y="14" width="20" height="8" rx="2"></rect><line x1="6" y1="6" x2="6" y2="14"></line><line x1="18" y1="6" x2="18" y2="14"></line><line x1="12" y1="2" x2="12" y2="14"></line></svg>';
                                    } elseif (strpos($lowerLabel, 'thanh toán') !== false || strpos($lowerLabel, 'payment') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>';
                                    } elseif (strpos($lowerLabel, 'hoàn tiền') !== false || strpos($lowerLabel, 'refund') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>';
                                    } elseif (strpos($lowerLabel, 'bảo mật') !== false || strpos($lowerLabel, 'mã hóa') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>';
                                    } elseif (strpos($lowerLabel, 'game') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><line x1="6" y1="12" x2="10" y2="12"></line><line x1="8" y1="10" x2="8" y2="14"></line><circle cx="15" cy="13" r="1"></circle><circle cx="18" cy="11" r="1"></circle><rect x="2" y="6" width="20" height="12" rx="6"></rect></svg>';
                                    } elseif (strpos($lowerLabel, 'thiết bị') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>';
                                    } elseif (strpos($lowerLabel, 'dung lượng') !== false || strpos($lowerLabel, 'băng thông') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M21 19c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14"></path><path d="M21 5v14"></path></svg>';
                                    } elseif (strpos($lowerLabel, 'dịch vụ') !== false || strpos($lowerLabel, 'tương thích') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';
                                    } elseif (strpos($lowerLabel, 'đặc quyền') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
                                    } elseif (strpos($lowerLabel, 'tiết kiệm') !== false) {
                                        $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:3px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>';
                                    }
                                ?>
                                    <li style="display: flex !important; align-items: flex-start !important; justify-content: flex-start !important; gap: 0.5rem; font-size: 0.88rem; color: var(--ios-text-secondary, #636366); text-align: left !important;">
                                        <?= $iconSvg ?>
                                        <span style="text-align: left !important; flex: 1;"><strong style="color: var(--ios-text, #1c1c1e);"><?= htmlspecialchars($label) ?></strong><?= htmlspecialchars($value) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Khối cố định phía dưới thẻ gói -->
                    <div>
                        <!-- Khối Thiết bị và Lưu lượng nằm CÙNG 1 HÀNG (2 cột) -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15)); font-size: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 0.4rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#34c759" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                <div style="text-align: left;">
                                    <span style="display: block; font-size: 0.75rem; color: var(--ios-text-secondary, #636366); font-weight: 600;">Thiết bị</span>
                                    <strong style="color: #34c759; font-size: 0.9rem;"><?= (int)($plan['max_devices'] ?? 1) ?> thiết bị</strong>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                <div style="text-align: right;">
                                    <span style="display: block; font-size: 0.75rem; color: var(--ios-text-secondary, #636366); font-weight: 600;">Lưu lượng</span>
                                    <strong style="color: #007aff; font-size: 0.9rem;"><?= ((int)($plan['bandwidth_limit_gb'] ?? 0) > 0) ? (int)$plan['bandwidth_limit_gb'] . ' GB' : 'Không giới hạn' ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Khối Icon Hệ Điều Hành CỐ ĐỊNH Ở DƯỚI -->
                        <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; padding: 0.75rem 0 0.25rem; color: #007aff; border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15)); margin-top: 0.75rem;">
                            <span title="iOS / macOS"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.32c.62-.75 1.04-1.8 0.93-2.85-.9.04-1.99.6-2.63 1.35-.58.67-.98 1.74-.84 2.78 1.01.08 2.03-.53 2.54-1.28z"/></svg></span>
                            <span title="Android"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m11.4045-6.02l1.9973-3.4592a.416.416 0 00-.1521-.5676.416.416 0 00-.5676.1521l-2.0223 3.503C15.5898 8.169 13.8552 7.75 12 7.75c-1.8552 0-3.5898.419-5.1368 1.1997L4.8409 5.4467a.416.416 0 00-.5676-.1521.416.416 0 00-.1521.5676l1.9973 3.4592C2.6889 11.2867.3333 14.8872.3333 19h23.3334c0-4.1128-2.3556-7.7133-5.7887-9.6786"/></svg></span>
                            <span title="Windows"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M0 3.449L9.75 2.1v9.451H0zm10.55-1.509L24 0v11.4H10.55zM0 12.6h9.75v9.451L0 20.701zm10.55 0H24V24l-13.45-1.899z"/></svg></span>
                            <span title="Linux"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-3.1 0-5.5 2.2-5.5 5 0 1.3.4 2.5 1.2 3.4-2.1 1.1-3.7 3.2-3.7 5.6 0 2.2 1.1 4.1 2.8 5.3-.7 1.5-1.9 2.9-3.8 3.8-.3.1-.4.4-.3.7.1.3.4.4.7.3 3.3-1.6 5.3-3.7 6.2-5.7.8.2 1.7.4 2.6.4s1.8-.1 2.6-.4c.9 2 2.9 4.1 6.2 5.7.1.1.2.1.3.1.2 0 .4-.1.5-.3.1-.3 0-.6-.3-.7-1.9-.9-3.1-2.3-3.8-3.8 1.7-1.2 2.8-3.1 2.8-5.3 0-2.4-1.6-4.5-3.7-5.6.8-.9 1.2-2.1 1.2-3.4 0-2.8-2.4-5-5.5-5z"/></svg></span>
                        </div>

                        <a href="/checkout?id=<?= (int)($plan['id'] ?? 0) ?>" class="glass-btn" style="width: 100%; text-align: center; text-decoration: none; margin-top: 0.5rem; display: block;">Đăng ký ngay</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: var(--ios-text-secondary); text-align: center; grid-column: 1 / -1; padding: 2rem 0;">Hiện chưa có gói dịch vụ nào cho nhóm này.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Kết thúc bộ đệm nội dung
$content = '<section class="dashboard-page">' . ob_get_clean() . '</section>';

// Nạp layout chính
$showSidebar = true;
require_once __DIR__ . '/../layouts/app.php';
?>