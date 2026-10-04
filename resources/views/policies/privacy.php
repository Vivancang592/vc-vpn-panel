<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN PANEL';
$contactEmail = $settings['contact_email'] ?? '';
$pageTitle = 'Chính Sách Quyền Riêng Tư - ' . $siteTitle;
$metaDescription = 'Chính sách quyền riêng tư: cách ' . $siteTitle . ' thu thập, sử dụng và bảo vệ dữ liệu cá nhân, cookie và thông tin thanh toán của bạn.';
$metaKeywords = 'chính sách quyền riêng tư, privacy policy, bảo mật dữ liệu, bảo vệ thông tin cá nhân, chính sách cookie';
$extraCss = 'home';
$extraJs = 'home'; // fragment partial-nav cần home.js (reveal .home-reveal)
ob_start();
?>

<section class="public-page home-page home-subpage home-policy-page">
    <header class="home-subpage-header">
        <h1 class="policy-title">Chính Sách Quyền Riêng Tư</h1>
        <p class="policy-title-en" lang="en">Privacy Policy</p>
        <p class="policy-summary">Cách <?= htmlspecialchars($siteTitle) ?> thu thập, sử dụng và bảo vệ thông tin của bạn.</p>
        <p class="policy-summary" lang="en">How <?= htmlspecialchars($siteTitle) ?> collects, uses and protects your information.</p>
        <p class="policy-meta">Cập nhật lần cuối: 03/10/2026 &middot; Last updated: 03/10/2026</p>
    </header>

    <div class="policy-layout">
        <nav class="policy-toc" aria-label="Mục lục trang">
            <p class="policy-toc-title">Mục lục &middot; Contents</p>
            <ol>
                <li><a href="#muc-1">1. Phạm vi áp dụng</a></li>
                <li><a href="#muc-2">2. Thông tin chúng tôi có thể thu thập</a></li>
                <li><a href="#muc-3">3. Mục đích sử dụng thông tin</a></li>
                <li><a href="#muc-4">4. Chia sẻ thông tin</a></li>
                <li><a href="#muc-5">5. Lưu trữ và bảo mật</a></li>
                <li><a href="#muc-6">6. Quyền của bạn</a></li>
                <li><a href="#muc-7">7. Cookie và công nghệ tương tự</a></li>
                <li><a href="#muc-8">8. Cập nhật chính sách</a></li>
            </ol>
        </nav>

        <article class="policy-content">
        <section class="policy-section" id="muc-1">
            <h2>1. Phạm vi áp dụng</h2>
            <p>Chính sách này áp dụng cho thông tin cá nhân và dữ liệu kỹ thuật được thu thập khi bạn đăng ký, truy cập hoặc sử dụng dịch vụ của chúng tôi.</p>
            <div class="policy-en" lang="en">
                <h3>1. Scope</h3>
                <p>This policy applies to personal information and technical data collected when you register for, access or use our services.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-2">
            <h2>2. Thông tin chúng tôi có thể thu thập</h2>
            <ul class="policy-list">
                <li>Thông tin tài khoản như tên người dùng, họ tên, email và thông tin bạn tự cung cấp.</li>
                <li>Thông tin giao dịch cần thiết để xác nhận thanh toán, kích hoạt và hỗ trợ gói dịch vụ.</li>
                <li>Nhật ký kết nối (địa chỉ IP nguồn, thời điểm truy cập, inbound sử dụng) phục vụ giới hạn số thiết bị, bảo mật và xử lý sự cố.</li>
                <li>Dữ liệu kỹ thuật và nhật ký vận hành cần thiết để bảo mật, phòng chống gian lận, xử lý lỗi và duy trì dịch vụ.</li>
                <li>Thông tin trao đổi khi bạn liên hệ với bộ phận hỗ trợ.</li>
            </ul>
            <div class="policy-en" lang="en">
                <h3>2. Information We May Collect</h3>
                <ul class="policy-list">
                    <li>Account information such as username, full name, email and details you provide.</li>
                    <li>Transaction information required to confirm payment, activate and support the service.</li>
                    <li>Connection logs (source IP address, access timestamps, inbound used) for device-limit enforcement, security and troubleshooting.</li>
                    <li>Technical data and operational logs necessary for security, fraud prevention, error handling and service maintenance.</li>
                    <li>Correspondence when you contact our support team.</li>
                </ul>
            </div>
        </section>

        <section class="policy-section" id="muc-3">
            <h2>3. Mục đích sử dụng thông tin</h2>
            <p>Chúng tôi sử dụng thông tin để tạo và quản lý tài khoản, cung cấp dịch vụ, xử lý thanh toán, hỗ trợ khách hàng, bảo vệ hệ thống, phát hiện hành vi lạm dụng và thực hiện nghĩa vụ pháp lý khi cần thiết.</p>
            <div class="policy-en" lang="en">
                <h3>3. How We Use Information</h3>
                <p>We use information to create and manage accounts, deliver the service, process payments, support customers, protect the system, detect abuse, and fulfil legal obligations when required.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-4">
            <h2>4. Chia sẻ thông tin</h2>
            <p>Chúng tôi không bán thông tin cá nhân của bạn. Thông tin chỉ có thể được chia sẻ ở mức cần thiết với nhà cung cấp hạ tầng, cổng thanh toán hoặc đối tác hỗ trợ vận hành; theo yêu cầu hợp pháp của cơ quan có thẩm quyền; hoặc khi cần bảo vệ quyền, tài sản và an toàn của người dùng hay hệ thống.</p>
            <div class="policy-en" lang="en">
                <h3>4. Sharing of Information</h3>
                <p>We do not sell your personal information. Information may be shared only to the extent necessary with infrastructure providers, payment gateways or operational partners; in response to lawful requests from competent authorities; or where needed to protect the rights, property and safety of users or the system.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-5">
            <h2>5. Lưu trữ và bảo mật</h2>
            <p>Thông tin được lưu giữ trong thời gian cần thiết để thực hiện các mục đích nêu trên, giải quyết tranh chấp và đáp ứng nghĩa vụ pháp lý. Chúng tôi áp dụng các biện pháp kỹ thuật và tổ chức hợp lý để hạn chế truy cập, sử dụng hoặc tiết lộ trái phép.</p>
            <div class="policy-en" lang="en">
                <h3>5. Retention and Security</h3>
                <p>Information is retained for as long as necessary to fulfil the purposes described above, resolve disputes and meet legal obligations. We apply reasonable technical and organizational measures to limit unauthorized access, use or disclosure.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-6">
            <h2>6. Quyền của bạn</h2>
            <p>Tùy theo quy định pháp luật áp dụng, bạn có thể yêu cầu truy cập, chỉnh sửa hoặc xóa thông tin cá nhân của mình. Một số dữ liệu có thể cần được lưu giữ để bảo mật, phòng chống gian lận hoặc đáp ứng nghĩa vụ pháp lý.</p>
            <div class="policy-en" lang="en">
                <h3>6. Your Rights</h3>
                <p>Subject to applicable law, you may request access to, correction of, or deletion of your personal information. Certain data may need to be retained for security, fraud prevention or legal compliance.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-7">
            <h2>7. Cookie và công nghệ tương tự</h2>
            <p>Chúng tôi có thể sử dụng cookie hoặc công nghệ tương tự cần thiết cho đăng nhập, duy trì phiên làm việc và cải thiện trải nghiệm. Bạn có thể quản lý cookie trong trình duyệt, tuy nhiên một số chức năng có thể không hoạt động đầy đủ nếu bạn tắt chúng.</p>
            <div class="policy-en" lang="en">
                <h3>7. Cookies and Similar Technologies</h3>
                <p>We may use cookies or similar technologies required for login, session management and experience improvement. You can manage cookies in your browser, although some features may not function properly if you disable them.</p>
            </div>
        </section>

        <section class="policy-section" id="muc-8">
            <h2>8. Cập nhật chính sách</h2>
            <p>Chính sách này có thể được cập nhật khi dịch vụ hoặc quy định pháp luật thay đổi. Phiên bản mới nhất luôn được công bố trên trang này.</p>
            <div class="policy-en" lang="en">
                <h3>8. Policy Updates</h3>
                <p>This policy may be updated when the service or applicable regulations change. The latest version is always published on this page.</p>
            </div>
        </section>

        <aside class="policy-note">
            <strong>Yêu cầu về dữ liệu cá nhân:</strong>
            <?php if ($contactEmail !== ''): ?>
                Gửi yêu cầu tới <a class="policy-contact-link" href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a> từ địa chỉ email đã đăng ký để chúng tôi xác minh và hỗ trợ.
            <?php else: ?>
                Vui lòng liên hệ với bộ phận hỗ trợ của <?= htmlspecialchars($siteTitle) ?> từ địa chỉ email đã đăng ký để được xác minh và hỗ trợ.
            <?php endif; ?>
            <span lang="en"> Send data requests from your registered email address so we can verify and assist you.</span>
        </aside>
        </article>
    </div>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>