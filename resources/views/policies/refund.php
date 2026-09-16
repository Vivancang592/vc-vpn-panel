<?php
$siteTitle = $settings['site_title'] ?? 'VC VPN 2027';
$contactEmail = $settings['contact_email'] ?? '';
$pageTitle = 'Chính Sách Hoàn Tiền - ' . $siteTitle;
ob_start();
?>

<div class="policy-page">
    <header class="glass-card policy-hero">
        <span class="policy-eyebrow">Thanh toán</span>
        <h1 class="policy-title">Chính Sách Hoàn Tiền</h1>
        <p class="policy-summary">Quy định tiếp nhận và xử lý yêu cầu hoàn tiền cho các dịch vụ của <?= htmlspecialchars($siteTitle) ?>.</p>
        <p class="policy-meta">Cập nhật lần cuối: 14/09/2026</p>
    </header>

    <article class="glass-card policy-content">
        <section class="policy-section">
            <h2>1. Nguyên tắc xử lý</h2>
            <p>Chúng tôi xem xét yêu cầu hoàn tiền theo từng trường hợp, dựa trên tình trạng đơn hàng, mức độ sử dụng dịch vụ và nguyên nhân yêu cầu. Việc gửi yêu cầu không đồng nghĩa yêu cầu sẽ được chấp thuận.</p>
        </section>

        <section class="policy-section">
            <h2>2. Trường hợp có thể được xem xét</h2>
            <ul class="policy-list">
                <li>Thanh toán bị trùng lặp hoặc ghi nhận sai số tiền do lỗi hệ thống.</li>
                <li>Dịch vụ không thể kích hoạt hoặc có lỗi kỹ thuật nghiêm trọng từ phía chúng tôi mà không thể khắc phục trong thời gian hợp lý.</li>
                <li>Gói dịch vụ chưa được sử dụng đáng kể và yêu cầu được gửi sớm sau khi thanh toán.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2>3. Trường hợp không áp dụng hoàn tiền</h2>
            <ul class="policy-list">
                <li>Dịch vụ đã được sử dụng, đã hết hạn hoặc bị gián đoạn do thiết bị, kết nối mạng hay cấu hình từ phía người dùng.</li>
                <li>Tài khoản bị hạn chế hoặc chấm dứt do vi phạm Điều Khoản Sử Dụng.</li>
                <li>Yêu cầu xuất phát từ việc thay đổi nhu cầu cá nhân sau khi gói đã được kích hoạt và sử dụng.</li>
                <li>Các khoản phí phát sinh từ ngân hàng, cổng thanh toán hoặc chênh lệch tỷ giá ngoài phạm vi kiểm soát của chúng tôi.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2>4. Cách gửi yêu cầu</h2>
            <p>Vui lòng gửi yêu cầu qua kênh hỗ trợ, nêu rõ mã đơn hàng, tài khoản đăng ký, thời điểm thanh toán, lý do yêu cầu và bằng chứng liên quan nếu có. Chúng tôi có thể yêu cầu thêm thông tin để xác minh.</p>
        </section>

        <section class="policy-section">
            <h2>5. Phương thức và thời gian hoàn tiền</h2>
            <p>Nếu được chấp thuận, khoản hoàn tiền sẽ được xử lý qua phương thức thanh toán phù hợp hoặc theo hướng dẫn của bộ phận hỗ trợ. Thời gian nhận tiền phụ thuộc vào phương thức thanh toán và đơn vị trung gian; các khoản phí không hoàn lại sẽ được thông báo trước khi xử lý.</p>
        </section>

        <section class="policy-section">
            <h2>6. Cập nhật chính sách</h2>
            <p>Chúng tôi có thể cập nhật chính sách này để phản ánh thay đổi về dịch vụ, thanh toán hoặc yêu cầu pháp lý. Chính sách áp dụng là phiên bản được công bố tại thời điểm bạn gửi yêu cầu.</p>
        </section>

        <aside class="policy-note">
            <strong>Gửi yêu cầu hoàn tiền:</strong>
            <?php if ($contactEmail !== ''): ?>
                Liên hệ <a class="policy-contact-link" href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a> và cung cấp mã đơn hàng để được hỗ trợ.
            <?php else: ?>
                Vui lòng liên hệ với bộ phận hỗ trợ của <?= htmlspecialchars($siteTitle) ?> và cung cấp mã đơn hàng để được hỗ trợ.
            <?php endif; ?>
        </aside>
    </article>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
