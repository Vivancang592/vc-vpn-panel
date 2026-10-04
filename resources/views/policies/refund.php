<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN PANEL';
$contactEmail = $settings['contact_email'] ?? '';
$pageTitle = 'Chính Sách Hoàn Tiền - ' . $siteTitle;
$metaDescription = 'Chính sách hoàn tiền dịch vụ VPN: điều kiện, thời gian xử lý và các trường hợp được hoàn tiền khi mua gói VPN tại ' . $siteTitle . '.';
$metaKeywords = 'chính sách hoàn tiền, hoàn tiền vpn, refund policy, trả lại tiền, chính sách thanh toán vpn';
$extraCss = 'home';
$extraJs = 'home'; // fragment partial-nav cần home.js (reveal .home-reveal)
ob_start();
?>

<section class="public-page home-page home-subpage home-policy-page">
    <header class="home-subpage-header">
        <h1 class="policy-title">Chính Sách Hoàn Tiền</h1>
        <p class="policy-title-en" lang="en">Refund Policy</p>
        <p class="policy-summary">Quy định tiếp nhận và xử lý yêu cầu hoàn tiền cho các dịch vụ của <?= htmlspecialchars($siteTitle) ?>.</p>
        <p class="policy-summary" lang="en">Rules for submitting and processing refund requests for services provided by <?= htmlspecialchars($siteTitle) ?>.</p>
        <p class="policy-meta">Cập nhật lần cuối: 03/10/2026 &middot; Last updated: 03/10/2026</p>
    </header>

    <div class="policy-layout">
        <nav class="policy-toc" aria-label="Mục lục trang">
            <p class="policy-toc-title">Mục lục &middot; Contents</p>
            <ol>
                <li><a href="#muc-1">1. Nguyên tắc xử lý</a></li>
                <li><a href="#muc-2">2. Trường hợp có thể được xem xét</a></li>
                <li><a href="#muc-3">3. Trường hợp không áp dụng hoàn tiền</a></li>
                <li><a href="#muc-4">4. Cách gửi yêu cầu</a></li>
                <li><a href="#muc-5">5. Phương thức và thời gian hoàn tiền</a></li>
                <li><a href="#muc-6">6. Cập nhật chính sách</a></li>
            </ol>
        </nav>

        <article class="policy-content">
        <section class="policy-section" id="muc-1">
            <h2>1. Nguyên tắc xử lý</h2>
            <p>Chúng tôi xem xét yêu cầu hoàn tiền theo từng trường hợp, dựa trên tình trạng đơn hàng, mức độ sử dụng dịch vụ và nguyên nhân yêu cầu. Việc gửi yêu cầu không đồng nghĩa yêu cầu sẽ được chấp thuận.</p>
            <div class="policy-en" lang="en">
                <h3>1. General Principles</h3>
                <p>We review refund requests case by case, based on the order status, extent of service usage and the reason for the request. Submitting a request does not guarantee approval.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-2">
            <h2>2. Trường hợp có thể được xem xét</h2>
            <ul class="policy-list">
                <li>Thanh toán bị trùng lặp hoặc ghi nhận sai số tiền do lỗi hệ thống.</li>
                <li>Dịch vụ không thể kích hoạt hoặc có lỗi kỹ thuật nghiêm trọng từ phía chúng tôi mà không thể khắc phục trong thời gian hợp lý.</li>
                <li>Gói dịch vụ chưa được sử dụng đáng kể và yêu cầu được gửi sớm sau khi thanh toán.</li>
            </ul>
            <div class="policy-en" lang="en">
                <h3>2. Cases That May Be Considered</h3>
                <ul class="policy-list">
                    <li>Duplicate payments or incorrect amounts recorded due to a system error.</li>
                    <li>The service cannot be activated, or a serious technical fault on our side cannot be remedied within a reasonable time.</li>
                    <li>The plan has not been materially used and the request is submitted shortly after payment.</li>
                </ul>
            </div>
        </section>

        <section class="policy-section" id="muc-3">
            <h2>3. Trường hợp không áp dụng hoàn tiền</h2>
            <ul class="policy-list">
                <li>Dịch vụ đã được sử dụng, đã hết hạn hoặc bị gián đoạn do thiết bị, kết nối mạng hay cấu hình từ phía người dùng.</li>
                <li>Tài khoản bị hạn chế hoặc chấm dứt do vi phạm Điều Khoản Sử Dụng.</li>
                <li>Yêu cầu xuất phát từ việc thay đổi nhu cầu cá nhân sau khi gói đã được kích hoạt và sử dụng.</li>
                <li>Các khoản phí phát sinh từ ngân hàng, cổng thanh toán hoặc chênh lệch tỷ giá ngoài phạm vi kiểm soát của chúng tôi.</li>
            </ul>
            <div class="policy-en" lang="en">
                <h3>3. Non-Refundable Cases</h3>
                <ul class="policy-list">
                    <li>The service has been used, has expired, or was disrupted by user devices, network connections or user configuration.</li>
                    <li>The account was restricted or terminated for violating the Terms of Use.</li>
                    <li>Requests arising from a change of personal need after the plan has been activated and used.</li>
                    <li>Fees charged by banks or payment gateways, or exchange-rate differences beyond our control.</li>
                </ul>
            </div>
        </section>

        <section class="policy-section" id="muc-4">
            <h2>4. Cách gửi yêu cầu</h2>
            <p>Vui lòng gửi yêu cầu qua kênh hỗ trợ, nêu rõ mã đơn hàng, tài khoản đăng ký, thời điểm thanh toán, lý do yêu cầu và bằng chứng liên quan nếu có. Chúng tôi có thể yêu cầu thêm thông tin để xác minh.</p>
            <div class="policy-en" lang="en">
                <h3>4. How to Submit a Request</h3>
                <p>Please send your request through our support channel, stating the order code, registered account, payment time, reason for the request and any supporting evidence. We may ask for additional information for verification.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-5">
            <h2>5. Phương thức và thời gian hoàn tiền</h2>
            <p>Nếu được chấp thuận, khoản hoàn tiền sẽ được xử lý qua phương thức thanh toán phù hợp hoặc theo hướng dẫn của bộ phận hỗ trợ. Thời gian nhận tiền phụ thuộc vào phương thức thanh toán và đơn vị trung gian; các khoản phí không hoàn lại sẽ được thông báo trước khi xử lý.</p>
            <div class="policy-en" lang="en">
                <h3>5. Refund Method and Timeline</h3>
                <p>If approved, refunds are processed through the appropriate payment method or as instructed by our support team. The time to receive funds depends on the payment method and intermediary; any non-refundable fees will be announced before processing.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-6">
            <h2>6. Cập nhật chính sách</h2>
            <p>Chúng tôi có thể cập nhật chính sách này để phản ánh thay đổi về dịch vụ, thanh toán hoặc yêu cầu pháp lý. Chính sách áp dụng là phiên bản được công bố tại thời điểm bạn gửi yêu cầu.</p>
            <div class="policy-en" lang="en">
                <h3>6. Policy Updates</h3>
                <p>We may update this policy to reflect changes in the service, payments or legal requirements. The version published at the time you submit your request applies.</p>
            </div>
        </section>

        <aside class="policy-note">
            <strong>Gửi yêu cầu hoàn tiền:</strong>
            <?php if ($contactEmail !== ''): ?>
                Liên hệ <a class="policy-contact-link" href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a> và cung cấp mã đơn hàng để được hỗ trợ.
            <?php else: ?>
                Vui lòng liên hệ với bộ phận hỗ trợ của <?= htmlspecialchars($siteTitle) ?> và cung cấp mã đơn hàng để được hỗ trợ.
            <?php endif; ?>
            <span lang="en"> Contact our support team and provide your order code.</span>
        </aside>
        </article>
    </div>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>