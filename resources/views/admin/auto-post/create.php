<?php
$pageTitle = "Tạo Chiến Dịch Bài Đăng AI - Quản Trị Hệ Thống";
$activeMenu = "auto-post";

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">🚀 Giao Việc Cho AI Lên Chiến Dịch Bài Đăng</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Nhập các chủ đề cốt lõi, AI sẽ tự động sinh hàng loạt bài viết đa góc nhìn kèm lịch đăng và prompt vẽ ảnh.</p>
    </div>
    <a href="/admin/auto-post" class="glass-btn" style="text-decoration: none; font-size: 0.85rem;">← Hàng Đợi Bài Viết</a>
</div>

<form id="campaignForm" action="/admin/auto-post/create" method="POST">
    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; align-items: start;">
        
        <!-- Cột Trái: Chủ đề cốt lõi & Hướng dẫn -->
        <div class="glass-card" style="padding: 1.25rem;">
            
            <!-- 1. Danh sách chủ đề cốt lõi -->
            <div style="margin-bottom: 1.25rem;">
                <label data-hint="Nhập danh sách các từ khóa, gói cước hoặc chủ đề bạn muốn AI triển khai thành chiến dịch." style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;">
                    Danh Sách Chủ Đề Cốt Lõi / Từ Khóa Chiến Dịch <span style="color: var(--ios-danger);">*</span>
                </label>
                <textarea id="core_topics" name="core_topics" rows="8" class="glass-input" style="width: 100%; font-size: 0.9rem; line-height: 1.6; font-family: inherit;" placeholder="Ví dụ:&#10;1. Giảm lag, hạ Ping khi chơi game Liên Quân, Free Fire, PUBG&#10;2. Bí quyết xem Netflix 4K, Youtube mượt mà khi cáp quang biển đứt&#10;3. Gói VPN 4G data không giới hạn chỉ từ 20k/tháng&#10;4. Bảo mật kết nối wifi công cộng quán cafe, bảo vệ thẻ ngân hàng&#10;5. Hướng dẫn cài đặt VPN 1 chạm trên iPhone và Android" required></textarea>
                <div style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-top: 0.35rem;">
                    💡 Mẹo: Bạn có thể nhập 3 - 10 gạch đầu dòng, AI sẽ tự động phân tích và viết từng bài theo góc nhìn độc lập, hấp dẫn.
                </div>
            </div>

            <!-- 2. Định hướng / Yêu cầu văn phong bổ sung -->
            <div style="margin-bottom: 1rem;">
                <label data-hint="Các yêu cầu đặc biệt như mã giảm giá, văn phong hài hước, hoặc nhóm khách hàng mục tiêu." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Ghi Chú / Yêu Cầu Bổ Sung Cho AI (Tùy chọn)
                </label>
                <textarea name="custom_instruction" rows="4" class="glass-input" style="width: 100%; font-size: 0.85rem; line-height: 1.5;" placeholder="Ví dụ: Giọng văn thân thiện trẻ trung, gắn mã giảm giá 'VCVPN2027' giảm 20%, kêu gọi khách inbox Fanpage nhận mã test 3 ngày miễn phí..."></textarea>
            </div>

        </div>

        <!-- Cột Phải: Thông số chiến dịch & Lịch xuất bản -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            
            <div class="glass-card" style="padding: 1.25rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem; color: var(--ios-blue);">
                    ⚙️ Thiết Lập Lịch Tự Động
                </h3>
                
                <!-- Số lượng bài viết -->
                <div style="margin-bottom: 1rem;">
                    <label data-hint="Số lượng bài viết AI sẽ tạo ra trong chiến dịch này (tối đa 20 bài)." style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.4rem;">
                        Số Lượng Bài Viết Cần Tạo
                    </label>
                    <input type="number" name="post_count" class="glass-input" value="5" min="1" max="20" style="width: 100%; font-weight: 700; font-size: 1rem;">
                </div>

                <!-- Ngày giờ bắt đầu đăng -->
                <div style="margin-bottom: 1rem;">
                    <label data-hint="Thời điểm bài viết đầu tiên trong chiến dịch được đăng lên Facebook." style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.4rem;">
                        Ngày Giờ Bắt Đầu Đăng
                    </label>
                    <input type="datetime-local" name="start_time" class="glass-input" value="<?= date('Y-m-d\TH:i', strtotime('+15 minutes')) ?>" style="width: 100%;">
                </div>

                <!-- Tần suất đăng -->
                <div style="margin-bottom: 1.5rem;">
                    <label data-hint="Khoảng cách thời gian giữa các bài đăng liên tiếp trong chiến dịch." style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.4rem;">
                        Tần Suất Đăng
                    </label>
                    <select name="frequency" class="glass-input" style="width: 100%; cursor: pointer;">
                        <option value="6h">Mỗi 6 tiếng 1 bài</option>
                        <option value="12h">Mỗi 12 tiếng 1 bài (2 bài / ngày)</option>
                        <option value="24h" selected>Mỗi ngày 1 bài (Cách 24 tiếng)</option>
                        <option value="48h">2 ngày 1 bài (Cách 48 tiếng)</option>
                        <option value="72h">3 ngày 1 bài (Cách 72 tiếng)</option>
                    </select>
                </div>

                <!-- Nút Submit -->
                <button type="submit" id="btnSubmitCampaign" class="glass-btn" style="width: 100%; padding: 0.85rem; font-weight: 700; font-size: 0.95rem; background: #0a84ff; color: #fff; border: none; cursor: pointer; border-radius: var(--radius-sm);">
                    🚀 Yêu Cầu AI Lên Chiến Dịch
                </button>

                <div id="campaignLoading" style="display: none; margin-top: 0.85rem; font-size: 0.8rem; color: var(--ios-blue); text-align: center; font-weight: 600;">
                    ⏳ AI đang phân tích chủ đề, sáng tạo nội dung & tạo prompt ảnh... Vui lòng đợi trong giây lát!
                </div>
            </div>

            <!-- Box Thông Tin Thêm -->
            <div class="glass-card" style="padding: 1rem; background: rgba(0, 122, 255, 0.04); border-left: 3px solid var(--ios-blue);">
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--ios-blue); margin-bottom: 0.35rem;">
                    🤖 Cơ chế tự động hóa
                </div>
                <div style="font-size: 0.78rem; color: var(--ios-text-secondary); line-height: 1.5;">
                    Sau khi AI hoàn tất việc lên chiến dịch, các bài viết sẽ tự động xếp vào hàng đợi. Cronjob sẽ kích hoạt đăng bài lên Fanpage theo đúng ngày giờ đã tính toán.
                </div>
            </div>

        </div>

    </div>
</form>

<script>
document.getElementById('campaignForm').addEventListener('submit', function (e) {
    const topics = document.getElementById('core_topics').value.trim();
    if (!topics) {
        alert('Vui lòng nhập danh sách chủ đề cốt lõi!');
        e.preventDefault();
        return;
    }

    const btn = document.getElementById('btnSubmitCampaign');
    const loading = document.getElementById('campaignLoading');
    btn.disabled = true;
    btn.style.opacity = '0.6';
    btn.textContent = '⏳ Đang Xử Lý Chiến Dịch...';
    loading.style.display = 'block';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/admin.php';
?>
