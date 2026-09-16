<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN 2027';
$contactEmail = $settings['contact_email'] ?? '';
$pageTitle = 'Chính Sách Quyền Riêng Tư - ' . $siteTitle;
ob_start();
?>

<div class="policy-page">
    <header class="glass-card policy-hero">
        <span class="policy-eyebrow">Pháp lý</span>
        <h1 class="policy-title">Chính Sách Quyền Riêng Tư</h1>
        <p class="policy-summary">Cách <?= htmlspecialchars($siteTitle) ?> thu thập, sử dụng và bảo vệ thông tin của bạn.</p>
        <p class="policy-meta">Cập nhật lần cuối: 14/09/2026</p>
    </header>

    <article class="glass-card policy-content">
        <section class="policy-section">
            <h2>1. Phạm vi áp dụng</h2>
            <p>Chính sách này áp dụng cho thông tin cá nhân và dữ liệu kỹ thuật được thu thập khi bạn đăng ký, truy cập hoặc sử dụng dịch vụ của chúng tôi.</p>
        </section>

        <section class="policy-section">
            <h2>2. Thông tin chúng tôi có thể thu thập</h2>
            <ul class="policy-list">
                <li>Thông tin tài khoản như tên người dùng, họ tên, email và thông tin bạn tự cung cấp.</li>
                <li>Thông tin giao dịch cần thiết để xác nhận thanh toán, kích hoạt và hỗ trợ gói dịch vụ.</li>
                <li>Dữ liệu kỹ thuật và nhật ký vận hành cần thiết để bảo mật, phòng chống gian lận, xử lý lỗi và duy trì dịch vụ.</li>
                <li>Thông tin trao đổi khi bạn liên hệ với bộ phận hỗ trợ.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2>3. Mục đích sử dụng thông tin</h2>
            <p>Chúng tôi sử dụng thông tin để tạo và quản lý tài khoản, cung cấp dịch vụ, xử lý thanh toán, hỗ trợ khách hàng, bảo vệ hệ thống, phát hiện hành vi lạm dụng và thực hiện nghĩa vụ pháp lý khi cần thiết.</p>
        </section>

        <section class="policy-section">
            <h2>4. Chia sẻ thông tin</h2>
            <p>Chúng tôi không bán thông tin cá nhân của bạn. Thông tin chỉ có thể được chia sẻ ở mức cần thiết với nhà cung cấp hạ tầng, cổng thanh toán hoặc đối tác hỗ trợ vận hành; theo yêu cầu hợp pháp của cơ quan có thẩm quyền; hoặc khi cần bảo vệ quyền, tài sản và an toàn của người dùng hay hệ thống.</p>
        </section>

        <section class="policy-section">
            <h2>5. Lưu trữ và bảo mật</h2>
            <p>Thông tin được lưu giữ trong thời gian cần thiết để thực hiện các mục đích nêu trên, giải quyết tranh chấp và đáp ứng nghĩa vụ pháp lý. Chúng tôi áp dụng các biện pháp kỹ thuật và tổ chức hợp lý để hạn chế truy cập, sử dụng hoặc tiết lộ trái phép.</p>
        </section>

        <section class="policy-section">
            <h2>6. Quyền của bạn</h2>
            <p>Tùy theo quy định pháp luật áp dụng, bạn có thể yêu cầu truy cập, chỉnh sửa hoặc xóa thông tin cá nhân của mình. Một số dữ liệu có thể cần được lưu giữ để bảo mật, phòng chống gian lận hoặc đáp ứng nghĩa vụ pháp lý.</p>
        </section>

        <section class="policy-section">
            <h2>7. Cookie và công nghệ tương tự</h2>
            <p>Chúng tôi có thể sử dụng cookie hoặc công nghệ tương tự cần thiết cho đăng nhập, duy trì phiên làm việc và cải thiện trải nghiệm. Bạn có thể quản lý cookie trong trình duyệt, tuy nhiên một số chức năng có thể không hoạt động đầy đủ nếu bạn tắt chúng.</p>
        </section>

        <section class="policy-section">
            <h2>8. Cập nhật chính sách</h2>
            <p>Chính sách này có thể được cập nhật khi dịch vụ hoặc quy định pháp luật thay đổi. Phiên bản mới nhất luôn được công bố trên trang này.</p>
        </section>

        <aside class="policy-note">
            <strong>Yêu cầu về dữ liệu cá nhân:</strong>
            <?php if ($contactEmail !== ''): ?>
                Gửi yêu cầu tới <a class="policy-contact-link" href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a> từ địa chỉ email đã đăng ký để chúng tôi xác minh và hỗ trợ.
            <?php else: ?>
                Vui lòng liên hệ với bộ phận hỗ trợ của <?= htmlspecialchars($siteTitle) ?> từ địa chỉ email đã đăng ký để được xác minh và hỗ trợ.
            <?php endif; ?>
        </aside>
    </article>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
