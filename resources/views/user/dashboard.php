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

// Gói active gần nhất cho block "Gói của tôi"
$nearestSub = null;
$nearestSubDaysLeft = 0;
$nearestUsedGb = 0.0;
$nearestLimitGb = 0.0;
$nearestUsagePercent = 0;
if (!empty($subscriptions) && is_array($subscriptions)) {
    foreach ($subscriptions as $sub) {
        if (($sub['status'] ?? '') !== 'active' || strtotime($sub['end_date'] ?? '') < time()) {
            continue;
        }
        if ($nearestSub === null || strtotime($sub['end_date']) < strtotime($nearestSub['end_date'] ?? '')) {
            $nearestSub = $sub;
        }
    }
}
if ($nearestSub !== null) {
    $nearestSubDaysLeft = (int) floor((strtotime($nearestSub['end_date']) - time()) / 86400);
    $usedBytes = (float) ($nearestSub['upload'] ?? 0) + (float) ($nearestSub['download'] ?? 0);
    $limitBytes = (float) ($nearestSub['transfer_enable'] ?? 0);
    $nearestUsedGb = $usedBytes / 1073741824;
    $nearestLimitGb = $limitBytes / 1073741824;
    $nearestUsagePercent = $limitBytes > 0 ? min(100, ($usedBytes / $limitBytes) * 100) : 0;
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

$dashboardPlans = is_array($plans ?? null) ? array_values($plans) : [];
if (!empty($dashboardPlans)) {
    shuffle($dashboardPlans);
    $dashboardPlans = array_slice($dashboardPlans, 0, 6);
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
    <span class="festival-kicker">KẾT NỐI BẢO MẬT</span>
    <h1>Chào mừng trở lại, <?= htmlspecialchars($user['username'] ?? 'Thành viên') ?>!</h1>
    <p>Quản lý dịch vụ VPN và theo dõi tài khoản của bạn</p>
</div>

<!-- Phần 2: Gói của tôi (gói active gần nhất) -->
<?php if (!empty($nearestSub)): ?>
<div class="glass-card u-my-plan u-mt-lg">
    <div class="u-my-plan-head">
        <div class="u-my-plan-info">
            <span class="u-my-plan-kicker">GÓI ĐANG CHẠY</span>
            <h3 class="u-my-plan-name"><?= htmlspecialchars($nearestSub['plan_name'] ?? ('Gói dịch vụ #' . ($nearestSub['plan_id'] ?? ''))) ?></h3>
            <p class="u-my-plan-meta">
                Hạn dùng: <strong><?= !empty($nearestSub['end_date']) ? date('d/m/Y', strtotime($nearestSub['end_date'])) : '-' ?></strong>
                <span class="u-my-plan-badge <?= $nearestSubDaysLeft <= 7 ? 'is-warning' : 'is-ok' ?>">Còn <?= max(0, $nearestSubDaysLeft) ?> ngày</span>
            </p>
        </div>
        <div class="u-my-plan-actions">
            <a class="glass-btn" href="/subscriptions/detail?id=<?= (int) ($nearestSub['id'] ?? 0) ?>">Kết nối</a>
            <a class="glass-btn" href="/checkout?type=renewal&amp;subscription=<?= (int) ($nearestSub['id'] ?? 0) ?>">Gia hạn</a>
        </div>
    </div>
    <div class="u-my-plan-usage">
        <div class="user-subscription-progress" aria-label="Đã sử dụng <?= round($nearestUsagePercent) ?>%"><span style="width: <?= $nearestUsagePercent ?>%"></span></div>
        <p class="u-my-plan-usage-text">Đã dùng <strong><?= number_format($nearestUsedGb, 2) ?> GB</strong><?= $nearestLimitGb > 0 ? ' / ' . number_format($nearestLimitGb, 2) . ' GB' : ' / Không giới hạn' ?> · <a href="/subscriptions">Tất cả gói của tôi</a></p>
    </div>
</div>
<?php endif; ?>

<!-- Thao tác nhanh -->
<div class="u-quick-actions" style="margin-top: 1rem;">
    <a href="/payments/deposit" class="glass-btn">💳 Nạp tiền</a>
    <a href="/user/plans" class="glass-btn">🛒 Mua gói dịch vụ</a>
    <a href="/tickets/create" class="glass-btn">🎫 Tạo ticket hỗ trợ</a>
</div>

<style>
    .dashboard-overview {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.25rem;
        margin-top: 1.5rem;
    }

    .dashboard-statistics {
        grid-column: 2;
        grid-row: 1;
        min-width: 0;
    }

    .dashboard-statistics .dashboard-grid-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-notices {
        grid-column: 1;
        grid-row: 1;
        min-width: 0;
    }

    /* Không có thông báo → khối Thống Kê trải hết 2 cột, 4 thẻ về một hàng.
       Pure CSS (:has) — không đụng markup hay logic PHP. */
    @media (min-width: 901px) {
        .dashboard-overview:not(:has(.dashboard-notices)) .dashboard-statistics {
            grid-column: 1 / -1;
        }

        .dashboard-overview:not(:has(.dashboard-notices)) .dashboard-statistics .dashboard-grid-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .dashboard-pricing-retired {
        display: none;
    }

    @media (max-width: 900px) {
        .dashboard-overview {
            grid-template-columns: minmax(0, 1fr);
        }

        .dashboard-statistics,
        .dashboard-notices {
            grid-column: 1;
            grid-row: auto;
        }
    }

    @media (max-width: 560px) {
        .dashboard-statistics .dashboard-grid-4 {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    .dashboard-notices .notice-slider-container {
        position: relative;
        overflow: hidden;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .dashboard-notices .tutorial-slider {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .dashboard-notices .tutorial-slider::-webkit-scrollbar {
        display: none;
    }

    .dashboard-notices .notice-slider-arrow {
        position: absolute;
        top: 50%;
        z-index: 2;
        display: grid;
        width: 2.25rem;
        height: 2.25rem;
        place-items: center;
        border: 0;
        border-radius: 50%;
        background: rgba(6, 18, 39, 0.72);
        color: #fff;
        cursor: pointer;
        font-size: 1.35rem;
        line-height: 1;
        opacity: 0;
        pointer-events: none;
        transform: translateY(-50%);
        transition: background 160ms ease, opacity 160ms ease, transform 160ms ease;
    }

    .dashboard-notices .notice-slider-container:hover .notice-slider-arrow,
    .dashboard-notices .notice-slider-container:focus-within .notice-slider-arrow {
        opacity: 1;
        pointer-events: auto;
    }

    .dashboard-notices .notice-slider-arrow:hover,
    .dashboard-notices .notice-slider-arrow:focus-visible {
        background: rgba(0, 122, 255, 0.92);
        transform: translateY(-50%) scale(1.08);
    }

    .dashboard-notices .notice-slider-arrow--previous { left: 0.75rem; }
    .dashboard-notices .notice-slider-arrow--next { right: 0.75rem; }

    .dashboard-notices .tutorial-card.notice-feature-card {
        position: relative;
        display: block;
        min-height: 310px;
        padding: 0;
        overflow: hidden;
        isolation: isolate;
        border-radius: 10px;
        background: #10213a;
    }

    .dashboard-notices .notice-feature-image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 280ms ease;
    }

    .dashboard-notices .notice-feature-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(6, 18, 39, 0.05) 18%, rgba(6, 18, 39, 0.3) 47%, rgba(6, 18, 39, 0.96) 100%);
        transition: background 180ms ease;
    }

    @media (hover: hover) {
        .dashboard-notices .tutorial-card.notice-feature-card:hover .notice-feature-image {
            transform: scale(1.04);
        }

        .dashboard-notices .tutorial-card.notice-feature-card:hover::after {
            background: linear-gradient(180deg, rgba(6, 18, 39, 0.02) 18%, rgba(6, 18, 39, 0.24) 47%, rgba(6, 18, 39, 0.92) 100%);
        }
    }

    .dashboard-notices .notice-hot-badge,
    .dashboard-notices .notice-feature-content,
    .dashboard-notices .tutorial-dots {
        position: relative;
        z-index: 1;
    }

    .dashboard-notices .notice-hot-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin: 1rem;
        padding: 0.35rem 0.7rem;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.94);
        color: #252525;
        font-size: 0.9rem;
        font-weight: 800;
    }

    .dashboard-notices .notice-feature-content {
        position: absolute;
        right: 1rem;
        bottom: 1rem;
        left: 1rem;
        color: #fff;
    }

    .dashboard-notices .notice-feature-kicker {
        margin: 0 0 0.4rem;
        color: rgba(255, 255, 255, 0.84);
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .dashboard-notices .notice-feature-title {
        display: -webkit-box;
        margin: 0;
        overflow: hidden;
        color: #fff;
        font-size: 1.15rem;
        font-weight: 750;
        line-height: 1.28;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
    }

    .dashboard-notices .notice-feature-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.9rem;
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.76rem;
    }

    .dashboard-notices .notice-feature-source {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dashboard-notices .notice-feature-link {
        flex: 0 0 auto;
        color: #fff;
        font-weight: 700;
        text-decoration: none;
    }

    .dashboard-notices .tutorial-dots {
        position: absolute;
        right: 0;
        bottom: 0.5rem;
        left: 0;
        gap: 0.35rem;
        margin: 0;
    }

    .dashboard-notices .tutorial-dot {
        width: 8px;
        height: 8px;
        background: rgba(255, 255, 255, 0.55);
    }

    .dashboard-notices .tutorial-dot.active {
        width: 8px;
        background: #54c878;
        box-shadow: none;
    }

    .dashboard-plan-section {
        margin-top: 1.5rem;
    }

    .dashboard-plan-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }

    .dashboard-plan-card {
        display: flex;
        flex-direction: column;
        min-width: 0;
        padding: 1rem;
        transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
    }

    .dashboard-plan-card:hover {
        transform: translateY(-4px);
        border-color: rgba(0, 122, 255, 0.55);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.2);
    }

    .dashboard-plan-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15));
    }

    .dashboard-plan-card h4 {
        margin: 0;
        color: var(--ios-text);
        font-size: 1rem;
    }

    .dashboard-plan-name {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        min-width: 0;
    }

    .dashboard-plan-name-icon {
        display: inline-grid;
        flex: 0 0 auto;
        width: 1.9rem;
        height: 1.9rem;
        place-items: center;
        border-radius: 6px;
        background: rgba(0, 122, 255, 0.14);
        color: var(--ios-blue);
        font-size: 1rem;
    }

    .dashboard-plan-price {
        flex: 0 0 auto;
        color: var(--ios-success);
        font-size: 0.92rem;
        font-weight: 750;
        text-align: right;
    }

    .dashboard-plan-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem;
        margin: 0.9rem 0;
        color: var(--ios-text-secondary, #636366);
        font-size: 0.8rem;
    }

    .dashboard-plan-meta strong {
        display: block;
        margin-top: 0.15rem;
        color: var(--ios-text);
        font-size: 0.85rem;
    }

    .dashboard-plan-features {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0.4rem;
        margin: 0 0 0.9rem;
        padding: 0;
        color: var(--ios-text-secondary, #636366);
        font-size: 0.82rem;
        line-height: 1.35;
        list-style: none;
    }

    .dashboard-plan-features li {
        display: flex;
        gap: 0.45rem;
        min-width: 0;
    }

    .dashboard-plan-feature-icon {
        flex: 0 0 auto;
        color: var(--ios-success);
        font-weight: 800;
    }

    .dashboard-plan-stock {
        margin-top: auto;
        color: var(--ios-text-secondary, #636366);
        font-size: 0.78rem;
    }

    .dashboard-plan-stock.is-sold-out {
        color: var(--ios-danger);
    }

    .dashboard-plan-stock.is-in-stock {
        color: var(--ios-success);
    }

    @media (max-width: 900px) {
        .dashboard-plan-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 560px) {
        .dashboard-notices .tutorial-card.notice-feature-card {
            min-height: 300px;
        }

        .dashboard-notices .notice-feature-title {
            font-size: 1.05rem;
        }

        .dashboard-plan-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="dashboard-overview">
<!-- Phần 3: 4 thẻ thống kê chuẩn đẹp -->
<div class="dashboard-statistics">
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
                <div class="stat-sub">Hoa hồng: <?= $formatPrice($user['commission_balance'] ?? 0) ?></div>
            </div>
            <a href="/wallet" class="stat-link">Xem chi tiết</a>
        </div>
    </div>
</div>

<!-- Section bảng giá cũ được thay bằng trang /user/plans. -->
<?php if (false): ?>
<div class="dashboard-pricing-retired" aria-hidden="true">
    <h3 class="u-section-title u-section-title--lg">Bảng giá gói dịch vụ</h3>
    <div class="u-section-note">
        💡 Bạn muốn tham khảo thêm nhiều gói cước hơn, hãy truy cập trang <a href="/user/plans" style="color: var(--ios-blue); text-decoration: none;">Cửa Hàng</a> của chúng tôi.
    </div>

    <div class="plans-grid" id="plansGrid">
        <?php $dashboardPlans = array_slice($firstGroupPlans, 0, 3); ?>
        <?php if (!empty($dashboardPlans)): ?>
            <?php foreach ($dashboardPlans as $plan): ?>
                <div class="plan-item-card glass-card" style="display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                    <div>
                        <!-- Tên gói & Giá cước -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15));">
                            <div class="plan-name" style="margin: 0; font-weight: 600;"><span class="plan-name-icon" aria-hidden="true">🛒</span><?= htmlspecialchars($plan['name'] ?? '') ?></div>
                            <div class="plan-price">
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
    $line = trim($line);

    // Icon admin tự gõ đầu dòng (VD: "⚡ Tốc độ: 1000 Mbps") — không tự bắt từ khóa
    $iconToken = '';
    if (preg_match('/^([^\p{L}\p{N}]+)\s*/u', $line, $iconMatch)) {
        $iconToken = trim($iconMatch[1]);
        $line = substr($line, strlen($iconMatch[0]));
    }

    $parts = explode(':', $line, 2);
    $label = trim($parts[0]);
    $value = isset($parts[1]) ? ': ' . trim($parts[1]) : '';

    // Icon mặc định (Check) khi admin chưa gõ icon đầu dòng
    $iconSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="u-fig-icon"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    if ($iconToken !== '') {
        $iconSvg = '<span aria-hidden="true" class="u-fig-icon" style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; font-size: 13px; line-height: 1;">' . htmlspecialchars($iconToken) . '</span>';
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
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#34c759" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="u-noshrink"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                <div style="text-align: left;">
                                    <span class="u-stat-label">Thiết bị</span>
                                    <strong style="color: #34c759; font-size: 0.9rem;"><?= (int)($plan['max_devices'] ?? 1) ?> thiết bị</strong>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="u-noshrink"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                <div style="text-align: right;">
                                    <span class="u-stat-label">Lưu lượng</span>
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
<?php endif; ?>

<?php
if (!empty($noticePosts)): ?>
<div class="dashboard-notices">
    <h3 class="u-section-title">📢 Thông Báo Hệ Thống</h3>
    <div class="notice-slider-container">
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
                    <div class="tutorial-card notice-feature-card" data-index="<?= $index ?>">
                        <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="<?= htmlspecialchars($post['title'] ?? 'Thông báo hệ thống') ?>" class="notice-feature-image">
                        <span class="notice-hot-badge">🔥 Tin nóng</span>
                        <div class="notice-feature-content">
                            <p class="notice-feature-kicker">Thông báo hệ thống</p>
                            <h4 class="notice-feature-title" title="<?= htmlspecialchars($post['title'] ?? '') ?>"><?= htmlspecialchars($post['title'] ?? '') ?></h4>
                            <div class="notice-feature-meta">
                                <span class="notice-feature-source"><?= isset($post['created_at']) ? date('d/m/Y', strtotime($post['created_at'])) : '' ?></span>
                                <a href="/user/articles/detail?slug=<?= urlencode($post['slug'] ?? '') ?>" class="notice-feature-link">Xem chi tiết</a>
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
        <?php if (count($noticePosts) > 1): ?>
            <button type="button" class="notice-slider-arrow notice-slider-arrow--previous" data-notice-slider-previous aria-label="Thông báo trước" title="Thông báo trước">&larr;</button>
            <button type="button" class="notice-slider-arrow notice-slider-arrow--next" data-notice-slider-next aria-label="Thông báo tiếp theo" title="Thông báo tiếp theo">&rarr;</button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>

<?php if (!empty($dashboardPlans)): ?>
<section class="dashboard-plan-section" aria-labelledby="dashboard-plan-heading">
    <div style="margin-bottom: 1.25rem; text-align: center;">
        <h3 class="u-section-title u-section-title--lg" id="dashboard-plan-heading">Bảng Giá Gói Dịch Vụ</h3>
        <p style="margin: 0; color: var(--ios-text-secondary, #636366); font-size: 0.88rem;">Danh sách các gói dịch vụ đang mở đăng ký.</p>
    </div>
    <div class="dashboard-plan-grid">
        <?php foreach ($dashboardPlans as $plan): ?>
            <?php
            $stockQuantity = $plan['stock_quantity'] ?? null;
            $isSoldOut = $stockQuantity !== null && (int) $stockQuantity <= 0;
            $featureLines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($plan['description'] ?? '')))));
            ?>
            <article class="glass-card user-plan-card dashboard-plan-card" style="display: flex; flex-direction: column; justify-content: space-between; height: 100%; box-sizing: border-box; width: 100%;">
                <div>
                    <div class="user-plan-card-heading" style="width: 100%; display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15));">
                        <div class="user-plan-name" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <span class="user-plan-title-icon" aria-hidden="true">&#128722;</span>
                            <div>
                                <h2 style="margin: 0; font-size: 1.1rem; font-weight: 600;"> <?= htmlspecialchars($plan['name'] ?? 'Gói VPN') ?></h2>
                                <div class="dashboard-plan-stock <?= $isSoldOut ? 'is-sold-out' : ($stockQuantity !== null ? 'is-in-stock' : '') ?>" style="margin-top: 0.25rem;">
                                    <?= $stockQuantity === null ? 'Không giới hạn số lượng' : ($isSoldOut ? 'Đã hết hàng' : 'Còn ' . number_format((int) $stockQuantity) . ' suất') ?>
                                </div>
                            </div>
                        </div>
                        <div class="user-plan-price-duration" style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.1rem;"><strong><?= $formatPrice($plan['price'] ?? 0) ?></strong><span><?= (int) ($plan['duration_days'] ?? 30) ?> ngày</span></div>
                    </div>

                    <ul class="info-list" style="list-style: none; padding: 0; margin: 0 0 0.75rem 0; display: flex; flex-direction: column; gap: 0.4rem; text-align: left !important; width: 100%;">
                        <?php foreach ($featureLines as $featureLine): ?>
                            <?php
                            $featureIconToken = '';
                            if (preg_match('/^([^\p{L}\p{N}]+)\s*/u', $featureLine, $fIconMatch)) {
                                $featureIconToken = trim($fIconMatch[1]);
                                $featureLine = substr($featureLine, strlen($fIconMatch[0]));
                            }
                            $featureIconSvg = $featureIconToken !== ''
                                ? '<span aria-hidden="true" class="u-fig-icon" style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; font-size: 13px; line-height: 1;">' . htmlspecialchars($featureIconToken) . '</span>'
                                : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="u-fig-icon"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                            $featureParts = explode(':', $featureLine, 2);
                            $featureLabel = trim($featureParts[0]);
                            $featureValue = isset($featureParts[1]) ? ': ' . trim($featureParts[1]) : '';
                            ?>
                            <li style="display: flex !important; align-items: flex-start !important; justify-content: flex-start !important; gap: 0.5rem; font-size: 0.88rem; color: var(--ios-text-secondary, #636366); text-align: left !important; width: 100%;">
                                <?= $featureIconSvg ?>
                                <span style="text-align: left !important; flex: 1;"><strong style="color: var(--ios-text, #1c1c1e);"><?= htmlspecialchars($featureLabel) ?></strong><?= htmlspecialchars($featureValue) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15)); font-size: 0.85rem;">
                        <div style="display: flex; align-items: flex-start; gap: 0.4rem; justify-content: flex-start;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#34c759" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="u-noshrink" style="margin-top: 0.1rem;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                            <div style="text-align: left;"><span style="display: block; font-size: 0.75rem; color: var(--ios-text-secondary, #636366); font-weight: 600;">Thiết bị</span><strong style="color: #34c759; font-size: 0.9rem;"><?= max(1, (int) ($plan['max_devices'] ?? 1)) ?> thiết bị</strong></div>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 0.4rem; justify-content: flex-end;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#007aff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="u-noshrink" style="margin-top: 0.1rem;"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            <div style="text-align: right;"><span style="display: block; font-size: 0.75rem; color: var(--ios-text-secondary, #636366); font-weight: 600;">Lưu lượng</span><strong style="color: #007aff; font-size: 0.9rem;"><?= (int) ($plan['bandwidth_limit_gb'] ?? 0) > 0 ? number_format((int) $plan['bandwidth_limit_gb']) . ' GB' : 'Không giới hạn' ?></strong></div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; padding: 0.75rem 0 0.25rem; color: #007aff; border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.15)); margin-top: 0.75rem;">
                        <span title="iOS / macOS"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.32c.62-.75 1.04-1.8 0.93-2.85-.9.04-1.99.6-2.63 1.35-.58.67-.98 1.74-.84 2.78 1.01.08 2.03-.53 2.54-1.28z"/></svg></span>
                        <span title="Android"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m11.4045-6.02l1.9973-3.4592a.416.416 0 00-.1521-.5676.416.416 0 00-.5676.1521l-2.0223 3.503C15.5898 8.169 13.8552 7.75 12 7.75c-1.8552 0-3.5898.419-5.1368 1.1997L4.8409 5.4467a.416.416 0 00-.5676-.1521.416.416 0 00-.1521.5676l1.9973 3.4592C2.6889 11.2867.3333 14.8872.3333 19h23.3334c0-4.1128-2.3556-7.7133-5.7887-9.6786"/></svg></span>
                        <span title="Windows"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M0 3.449L9.75 2.1v9.451H0zm10.55-1.509L24 0v11.4H10.55zM0 12.6h9.75v9.451L0 20.701zm10.55 0H24V24l-13.45-1.899z"/></svg></span>
                        <span title="Linux"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-3.1 0-5.5 2.2-5.5 5 0 1.3.4 2.5 1.2 3.4-2.1 1.1-3.7 3.2-3.7 5.6 0 2.2 1.1 4.1 2.8 5.3-.7 1.5-1.9 2.9-3.8 3.8-.3.1-.4.4-.3.7.1.3.4.4.7.3 3.3-1.6 5.3-3.7 6.2-5.7.8.2 1.7.4 2.6.4s1.8-.1 2.6-.4c.9 2 2.9 4.1 6.2 5.7.1.1.2.1.3.1.2 0 .4-.1.5-.3.1-.3 0-.6-.3-.7-1.9-.9-3.1-2.3-3.8-3.8 1.7-1.2 2.8-3.1 2.8-5.3 0-2.4-1.6-4.5-3.7-5.6.8-.9 1.2-2.1 1.2-3.4 0-2.8-2.4-5-5.5-5z"/></svg></span>
                    </div>
                    <?php if ($isSoldOut): ?>
                        <span class="glass-btn" aria-disabled="true" style="width: 100%; margin-top: 0.75rem; opacity: 0.55; cursor: not-allowed; text-align: center; display: block;">Đã hết hàng</span>
                    <?php else: ?>
                        <a href="/checkout?id=<?= (int) ($plan['id'] ?? 0) ?>" class="glass-btn" style="width: 100%; margin-top: 0.75rem; text-align: center; text-decoration: none; display: block;">Chọn gói này</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php
// Kết thúc bộ đệm nội dung
$content = '<section class="dashboard-page">' . ob_get_clean() . '</section>';

// Nạp layout chính
$showSidebar = true;
require_once __DIR__ . '/../layouts/app.php';
?>