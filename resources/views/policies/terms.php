<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN 2027';
$contactEmail = $settings['contact_email'] ?? '';
$pageTitle = 'Điều Khoản Sử Dụng - ' . $siteTitle;
ob_start();
?>

<div class="policy-page">
    <header class="glass-card policy-hero">
        <span class="policy-eyebrow">Pháp lý</span>
        <h1 class="policy-title">Điều Khoản Sử Dụng</h1>
        <p class="policy-summary">Các điều kiện áp dụng khi bạn truy cập hoặc sử dụng dịch vụ của <?= htmlspecialchars($siteTitle) ?>.</p>
        <p class="policy-meta">Cập nhật lần cuối: 14/09/2026</p>
    </header>

    <article class="glass-card policy-content">
        <section class="policy-section">
            <h2>1. Chấp nhận điều khoản</h2>
            <p>Khi tạo tài khoản, truy cập hoặc sử dụng dịch vụ, bạn xác nhận đã đọc, hiểu và đồng ý với các điều khoản này. Nếu không đồng ý, vui lòng không tiếp tục sử dụng dịch vụ.</p>
        </section>

        <section class="policy-section">
            <h2>2. Tài khoản và trách nhiệm của bạn</h2>
            <ul class="policy-list">
                <li>Cung cấp thông tin chính xác, đầy đủ và cập nhật khi đăng ký tài khoản.</li>
                <li>Giữ bảo mật mật khẩu, mã xác thực và thông tin truy cập; không chia sẻ tài khoản khi chưa được cho phép.</li>
                <li>Thông báo ngay cho chúng tôi nếu phát hiện việc sử dụng tài khoản trái phép.</li>
                <li>Chịu trách nhiệm đối với mọi hoạt động diễn ra thông qua tài khoản của mình.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2>3. Sử dụng dịch vụ hợp pháp</h2>
            <p>Bạn cam kết sử dụng dịch vụ phù hợp với pháp luật hiện hành và không xâm phạm quyền, lợi ích hợp pháp của tổ chức hoặc cá nhân khác.</p>
            <ul class="policy-list">
                <li>Không sử dụng dịch vụ để phát tán mã độc, thư rác, lừa đảo, tấn công hệ thống hoặc thực hiện hành vi trái pháp luật.</li>
                <li>Không khai thác, gây quá tải, dò quét hoặc can thiệp trái phép vào hạ tầng và hệ thống của dịch vụ.</li>
                <li>Không bán lại, cho thuê, chuyển nhượng hoặc chia sẻ quyền truy cập trái với gói dịch vụ đã đăng ký.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2>4. Thanh toán và thay đổi dịch vụ</h2>
            <p>Giá, quyền lợi, chu kỳ sử dụng và phương thức thanh toán của từng gói được hiển thị tại thời điểm đặt hàng. Chúng tôi có thể điều chỉnh giá, tính năng hoặc hạ tầng để vận hành dịch vụ; thay đổi quan trọng sẽ được công bố hợp lý trước khi áp dụng khi có thể.</p>
        </section>

        <section class="policy-section">
            <h2>5. Tạm ngừng hoặc chấm dứt dịch vụ</h2>
            <p>Chúng tôi có thể tạm ngừng hoặc chấm dứt quyền truy cập khi có căn cứ cho thấy tài khoản vi phạm điều khoản, gây rủi ro an toàn, hoặc theo yêu cầu hợp pháp của cơ quan có thẩm quyền. Việc này không loại trừ các quyền và biện pháp xử lý khác theo pháp luật.</p>
        </section>

        <section class="policy-section">
            <h2>6. Giới hạn trách nhiệm</h2>
            <p>Dịch vụ được cung cấp trên cơ sở nỗ lực hợp lý để duy trì tính ổn định và an toàn. Tuy nhiên, chúng tôi không bảo đảm dịch vụ luôn không gián đoạn hoặc không có lỗi do các yếu tố ngoài khả năng kiểm soát, như sự cố mạng, thiết bị của người dùng hoặc sự kiện bất khả kháng.</p>
        </section>

        <section class="policy-section">
            <h2>7. Thay đổi điều khoản</h2>
            <p>Chúng tôi có thể cập nhật điều khoản này để phù hợp với thay đổi của dịch vụ hoặc quy định pháp luật. Bản cập nhật có hiệu lực khi được đăng trên trang này, trừ khi có thông báo khác.</p>
        </section>

        <aside class="policy-note">
            <strong>Liên hệ:</strong>
            <?php if ($contactEmail !== ''): ?>
                Nếu có câu hỏi về điều khoản, vui lòng liên hệ <a class="policy-contact-link" href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.
            <?php else: ?>
                Nếu có câu hỏi về điều khoản, vui lòng liên hệ với bộ phận hỗ trợ của <?= htmlspecialchars($siteTitle) ?>.
            <?php endif; ?>
        </aside>
    </article>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
