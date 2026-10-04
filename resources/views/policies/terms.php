<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN PANEL';
$contactEmail = $settings['contact_email'] ?? '';
$pageTitle = 'Điều Khoản Sử Dụng - ' . $siteTitle;
$metaDescription = 'Điều khoản sử dụng dịch vụ VPN: quy định tài khoản, thanh toán, hoàn tiền, giới hạn số thiết bị, trách nhiệm người dùng và nghĩa vụ của ' . $siteTitle . '.';
$metaKeywords = 'điều khoản sử dụng, điều khoản dịch vụ, terms of service, chính sách vpn, quy định sử dụng vpn';
$extraCss = 'home';
$extraJs = 'home'; // fragment partial-nav cần home.js (reveal .home-reveal)
ob_start();
?>

<section class="public-page home-page home-subpage home-policy-page">
    <header class="home-subpage-header">
        <h1 class="policy-title">Điều Khoản Sử Dụng</h1>
        <p class="policy-title-en" lang="en">Terms of Use</p>
        <p class="policy-summary">Các điều kiện áp dụng khi bạn truy cập hoặc sử dụng dịch vụ của <?= htmlspecialchars($siteTitle) ?>.</p>
        <p class="policy-summary" lang="en">These terms apply when you access or use the services provided by <?= htmlspecialchars($siteTitle) ?>.</p>
        <p class="policy-meta">Cập nhật lần cuối: 03/10/2026 &middot; Last updated: 03/10/2026</p>
    </header>

    <div class="policy-layout">
        <nav class="policy-toc" aria-label="Mục lục trang">
            <p class="policy-toc-title">Mục lục &middot; Contents</p>
            <ol>
                <li><a href="#muc-1">1. Chấp nhận điều khoản</a></li>
                <li><a href="#muc-2">2. Tài khoản và trách nhiệm của bạn</a></li>
                <li><a href="#muc-3">3. Sử dụng dịch vụ hợp pháp</a></li>
                <li><a href="#muc-4">4. Thanh toán và thay đổi dịch vụ</a></li>
                <li><a href="#muc-5">5. Tạm ngừng hoặc chấm dứt dịch vụ</a></li>
                <li><a href="#muc-6">6. Giới hạn trách nhiệm</a></li>
                <li><a href="#muc-7">7. Giới hạn số thiết bị và thông báo qua email</a></li>
                <li><a href="#muc-8">8. Thay đổi điều khoản</a></li>
            </ol>
        </nav>

        <article class="policy-content">
        <section class="policy-section" id="muc-1">
            <h2>1. Chấp nhận điều khoản</h2>
            <p>Khi tạo tài khoản, truy cập hoặc sử dụng dịch vụ, bạn xác nhận đã đọc, hiểu và đồng ý với các điều khoản này. Nếu không đồng ý, vui lòng không tiếp tục sử dụng dịch vụ.</p>
            <div class="policy-en" lang="en">
                <h3>1. Acceptance of Terms</h3>
                <p>By creating an account, accessing or using the service, you acknowledge that you have read, understood and agree to these terms. If you do not agree, please discontinue use of the service.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-2">
            <h2>2. Tài khoản và trách nhiệm của bạn</h2>
            <ul class="policy-list">
                <li>Cung cấp thông tin chính xác, đầy đủ và cập nhật khi đăng ký tài khoản.</li>
                <li>Giữ bảo mật mật khẩu, mã xác thực và thông tin truy cập; không chia sẻ tài khoản khi chưa được cho phép.</li>
                <li>Thông báo ngay cho chúng tôi nếu phát hiện việc sử dụng tài khoản trái phép.</li>
                <li>Chịu trách nhiệm đối với mọi hoạt động diễn ra thông qua tài khoản của mình.</li>
            </ul>
            <div class="policy-en" lang="en">
                <h3>2. Your Account and Responsibilities</h3>
                <ul class="policy-list">
                    <li>Provide accurate, complete and up-to-date information when registering.</li>
                    <li>Keep your password, authentication codes and access details confidential; do not share your account without permission.</li>
                    <li>Notify us immediately if you detect any unauthorized use of your account.</li>
                    <li>Take responsibility for all activities conducted through your account.</li>
                </ul>
            </div>
        </section>

        <section class="policy-section" id="muc-3">
            <h2>3. Sử dụng dịch vụ hợp pháp</h2>
            <p>Bạn cam kết sử dụng dịch vụ phù hợp với pháp luật hiện hành và không xâm phạm quyền, lợi ích hợp pháp của tổ chức hoặc cá nhân khác.</p>
            <ul class="policy-list">
                <li>Không sử dụng dịch vụ để phát tán mã độc, thư rác, lừa đảo, tấn công hệ thống hoặc thực hiện hành vi trái pháp luật.</li>
                <li>Không khai thác, gây quá tải, dò quét hoặc can thiệp trái phép vào hạ tầng và hệ thống của dịch vụ.</li>
                <li>Không bán lại, cho thuê, chuyển nhượng hoặc chia sẻ quyền truy cập trái với gói dịch vụ đã đăng ký.</li>
            </ul>
            <div class="policy-en" lang="en">
                <h3>3. Lawful Use of the Service</h3>
                <p>You agree to use the service in compliance with applicable laws and not to infringe the rights or lawful interests of any organization or individual.</p>
                <ul class="policy-list">
                    <li>Do not use the service to distribute malware, spam, fraud, launch attacks, or carry out any unlawful activity.</li>
                    <li>Do not exploit, overload, scan, or gain unauthorized access to the service infrastructure and systems.</li>
                    <li>Do not resell, rent, transfer, or otherwise share access contrary to the plan you have subscribed to.</li>
                </ul>
            </div>
        </section>

        <section class="policy-section" id="muc-4">
            <h2>4. Thanh toán và thay đổi dịch vụ</h2>
            <p>Giá, quyền lợi, chu kỳ sử dụng và phương thức thanh toán của từng gói được hiển thị tại thời điểm đặt hàng. Chúng tôi có thể điều chỉnh giá, tính năng hoặc hạ tầng để vận hành dịch vụ; thay đổi quan trọng sẽ được công bố hợp lý trước khi áp dụng khi có thể.</p>
            <div class="policy-en" lang="en">
                <h3>4. Payments and Service Changes</h3>
                <p>Prices, benefits, billing cycles and payment methods for each plan are displayed at the time of ordering. We may adjust pricing, features or infrastructure to operate the service; material changes will be reasonably announced in advance whenever possible.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-5">
            <h2>5. Tạm ngừng hoặc chấm dứt dịch vụ</h2>
            <p>Chúng tôi có thể tạm ngừng hoặc chấm dứt quyền truy cập khi có căn cứ cho thấy tài khoản vi phạm điều khoản, gây rủi ro an toàn, hoặc theo yêu cầu hợp pháp của cơ quan có thẩm quyền. Việc này không loại trừ các quyền và biện pháp xử lý khác theo pháp luật.</p>
            <div class="policy-en" lang="en">
                <h3>5. Suspension or Termination</h3>
                <p>We may suspend or terminate access where there is evidence that an account violates these terms, poses a security risk, or when required by a competent authority. This does not exclude other rights and remedies available under the law.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-6">
            <h2>6. Giới hạn trách nhiệm</h2>
            <p>Dịch vụ được cung cấp trên cơ sở nỗ lực hợp lý để duy trì tính ổn định và an toàn. Tuy nhiên, chúng tôi không bảo đảm dịch vụ luôn không gián đoạn hoặc không có lỗi do các yếu tố ngoài khả năng kiểm soát, như sự cố mạng, thiết bị của người dùng hoặc sự kiện bất khả kháng.</p>
            <div class="policy-en" lang="en">
                <h3>6. Limitation of Liability</h3>
                <p>The service is provided on a best-efforts basis to maintain stability and safety. However, we do not guarantee that the service will be uninterrupted or error-free due to factors beyond our control, such as network incidents, user devices, or force majeure events.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-7">
            <h2>7. Giới hạn số thiết bị và thông báo qua email</h2>
            <p>Mỗi gói dịch vụ quy định số thiết bị (số địa chỉ IP) đồng thời được phép kết nối. Khi hệ thống phát hiện số kết nối vượt quá hạn mức của gói, một cơ chế khóa mạng tạm thời (mặc định 60 giây) sẽ được áp dụng để bảo vệ tài khoản và hạ tầng. Hệ thống đồng thời gửi email thông báo đến địa chỉ đăng ký của bạn. Việc kết nối vượt hạn mức lặp lại có thể bị xem xét theo mục 2 và mục 5.</p>
            <div class="policy-en" lang="en">
                <h3>7. Device Limit and Email Notifications</h3>
                <p>Each plan defines the maximum number of simultaneous devices (source IP addresses) allowed. When the system detects connections exceeding your plan's limit, a temporary network lock (60 seconds by default) is applied to protect your account and infrastructure. The system also sends an email notification to your registered address. Repeatedly exceeding the limit may be reviewed under sections 2 and 5.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-8">
            <h2>8. Thay đổi điều khoản</h2>
            <p>Chúng tôi có thể cập nhật điều khoản này để phù hợp với thay đổi của dịch vụ hoặc quy định pháp luật. Bản cập nhật có hiệu lực khi được đăng trên trang này, trừ khi có thông báo khác.</p>
            <div class="policy-en" lang="en">
                <h3>8. Changes to Terms</h3>
                <p>We may update these terms to reflect changes in the service or applicable regulations. An update takes effect when posted on this page, unless otherwise announced.</p>
            </div>
        </section>

        <aside class="policy-note">
            <strong>Liên hệ:</strong>
            <?php if ($contactEmail !== ''): ?>
                Nếu có câu hỏi về điều khoản, vui lòng liên hệ <a class="policy-contact-link" href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.
            <?php else: ?>
                Nếu có câu hỏi về điều khoản, vui lòng liên hệ với bộ phận hỗ trợ của <?= htmlspecialchars($siteTitle) ?>.
            <?php endif; ?>
            <span lang="en"> For questions about these terms, please contact our support team.</span>
        </aside>
        </article>
    </div>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>