<?php
$pageTitle = ($settings['site_name'] ?? 'VC VPN 2027') . " - Dịch Vụ VPN Tốc Độ Cao";
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
        <div class="home-hero-grid"><div>
            <span class="home-eyebrow"><span class="home-dot"></span>KẾT NỐI AN TOÀN, KHÔNG GIỚI HẠN</span>
            <h1 class="home-title">Internet riêng tư.<br><strong>Trải nghiệm liền mạch.</strong></h1>
            <p class="home-lead">VPN tốc độ cao giúp bạn làm việc, giải trí và truy cập Internet với sự riêng tư trên mọi thiết bị.</p>
            <div class="home-actions"><a class="home-button home-button-primary" href="#bang-gia">Khám phá gói dịch vụ</a><a class="home-button home-button-secondary" href="<?= $loggedIn ? '/user/dashboard' : '/register' ?>"><?= $loggedIn ? 'Bảng điều khiển' : 'Tạo tài khoản' ?></a></div>
        </div><aside class="home-status home-glass"><div class="home-status-title">Trạng thái hạ tầng</div><div class="home-status-item"><span><span class="home-dot"></span>Hệ thống</span><strong>Ổn định</strong></div><div class="home-status-item"><span>Hỗ trợ</span><strong>24/7</strong></div><div class="home-status-item"><span>Nền tảng</span><strong>Đa thiết bị</strong></div></aside></div>
    </section>
    <div class="home-features">
        <article class="home-feature home-reveal"><div class="home-feature-icon">⚡</div><h3>Tốc độ tối ưu</h3><p>Phù hợp cho công việc, xem nội dung chất lượng cao và kết nối hằng ngày.</p></article>
        <article class="home-feature home-reveal"><div class="home-feature-icon">◈</div><h3>Riêng tư mặc định</h3><p>Giao thức hiện đại bảo vệ phiên truy cập trên mạng công cộng.</p></article>
        <article class="home-feature home-reveal"><div class="home-feature-icon">⌘</div><h3>Đa nền tảng</h3><p>Sử dụng trên iOS, Android, Windows, macOS và Linux.</p></article>
        <article class="home-feature home-reveal"><div class="home-feature-icon">✦</div><h3>Dễ bắt đầu</h3><p>Chọn gói, nhận cấu hình và kết nối trong vài bước ngắn gọn.</p></article>
    </div>
    <section id="bang-gia" class="home-pricing home-reveal"><div class="home-pricing-header"><div><h2>Chọn gói phù hợp với bạn</h2><p>Dữ liệu giá được cập nhật trực tiếp từ hệ thống.</p></div>
        <?php if ($groups): ?><div class="home-tabs" role="tablist"><?php $i = 0; foreach ($groups as $name => $items): ?><button class="home-tab" type="button" role="tab" aria-controls="home-panel-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= htmlspecialchars($name) ?></button><?php $i++; endforeach; ?></div><?php endif; ?></div>
        <?php if ($groups): ?><?php $i = 0; foreach ($groups as $name => $items): ?><div class="home-tab-panel" id="home-panel-<?= $i ?>" <?= $i ? 'hidden' : '' ?>><div class="home-plan-grid"><?php foreach ($items as $plan): ?><?php $checkout = '/checkout?id=' . (int) $plan['id']; ?><article class="home-plan home-reveal"><div class="home-plan-heading"><h3><?= htmlspecialchars($plan['name']) ?></h3><div class="home-plan-price"><?= isset($formatMoney) ? $formatMoney($plan['price']) : number_format($plan['price'], 0, ',', '.') . ' đ' ?><small>| <?= (int) $plan['duration_days'] ?> ngày</small></div></div><ul class="home-plan-list"><li><?= (int) ($plan['bandwidth_limit_gb'] ?? 0) ?: 'Không giới hạn' ?> <?= !empty($plan['bandwidth_limit_gb']) ? 'GB dữ liệu' : 'dữ liệu' ?></li><li>Tối đa <?= (int) ($plan['max_devices'] ?? 1) ?> thiết bị</li><?php if (!empty($plan['description'])): ?><li><?= htmlspecialchars($plan['description']) ?></li><?php endif; ?></ul><a class="home-button home-button-secondary" href="<?= $loggedIn ? $checkout : '/login?redirect=' . urlencode($checkout) ?>">Chọn gói này</a></article><?php endforeach; ?></div></div><?php $i++; endforeach; ?><?php else: ?><div class="home-empty">Gói dịch vụ đang được cập nhật.</div><?php endif; ?>
    </section>
    <section id="cau-hoi-thuong-gap" class="home-faq home-reveal" aria-labelledby="home-faq-title">
        <div class="home-faq-heading">
            <h2 id="home-faq-title">Câu hỏi thường gặp</h2>
            <p>Thông tin cần biết trước khi bắt đầu sử dụng dịch vụ VPN.</p>
        </div>
        <div class="home-faq-list">
            <details class="home-faq-item" open>
                <summary>VPN hoạt động như thế nào?</summary>
                <p>VPN tạo một kết nối được mã hóa giữa thiết bị và máy chủ, giúp bảo vệ dữ liệu khi bạn truy cập Internet.</p>
            </details>
            <details class="home-faq-item">
                <summary>Tôi có thể sử dụng trên bao nhiêu thiết bị?</summary>
                <p>Mỗi gói hỗ trợ số thiết bị riêng. Bạn có thể xem giới hạn cụ thể trong thông tin của gói cước trước khi đăng ký.</p>
            </details>
            <details class="home-faq-item">
                <summary>Sau khi thanh toán, tôi nhận cấu hình ở đâu?</summary>
                <p>Thông tin kết nối sẽ xuất hiện trong bảng điều khiển tài khoản ngay sau khi đơn hàng được kích hoạt.</p>
            </details>
            <details class="home-faq-item">
                <summary>Tôi cần hỗ trợ khi không kết nối được?</summary>
                <p>Hãy tạo ticket trong tài khoản để đội ngũ hỗ trợ kiểm tra cấu hình và trạng thái dịch vụ cho bạn.</p>
            </details>
        </div>
    </section>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>