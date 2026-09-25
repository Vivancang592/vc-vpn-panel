<?php
$pageTitle = "Lên Lịch Bài Đăng Mới - Quản Trị Hệ Thống";
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

<div style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">➕ Lên Lịch Đăng Bài Fanpage AI</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Tự động sinh nội dung viral, thiết kế ảnh qua DALL-E & đăng tự động</p>
    </div>
    <a href="/admin/auto-post" class="glass-btn" style="text-decoration: none; font-size: 0.85rem;">← Quay Lại</a>
</div>

<form action="/admin/auto-post/create" method="POST" enctype="multipart/form-data">
    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; align-items: start;">
        
        <!-- Cột Trái: Nội dung & Prompts -->
        <div class="glass-card" style="padding: 1.25rem;">
            
            <!-- 1. Chủ đề bài viết -->
            <div style="margin-bottom: 1.1rem;">
                <label data-hint="Chủ đề chính để AI bám sát và triển khai bài viết." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Chủ Đề / Tiêu Đề Bài Viết <span style="color: var(--ios-danger);">*</span>
                </label>
                <input type="text" id="post_topic" name="topic" class="glass-input" placeholder="Ví dụ: Ưu đãi 50% gói VPN 4G tốc độ cao đón lễ 30/4" style="width: 100%; font-weight: 600;" required>
            </div>

            <!-- 2. Định hướng Prompt AI -->
            <div style="margin-bottom: 1.1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label data-hint="Hướng dẫn thêm cho AI về đối tượng mục tiêu, khuyến mãi hoặc văn phong." style="font-weight: 600; font-size: 0.85rem;">
                        Định Hướng Nội Dung Thêm Cho AI (Tùy chọn)
                    </label>
                    <button type="button" id="btnAiGenerate" class="glass-btn" style="font-size: 0.78rem; padding: 0.25rem 0.6rem; background: rgba(0, 122, 255, 0.12); color: var(--ios-blue); border-color: rgba(0, 122, 255, 0.3);">
                        ⚡ AI Viết Thử Ngay
                    </button>
                </div>
                <textarea id="post_content_prompt" name="content_prompt" rows="3" class="glass-input" style="width: 100%; font-size: 0.85rem;" placeholder="Ví dụ: Nêu bật tính năng chơi game Ping 15ms không giật lag, kèm mã giảm giá 'GAMING2027', kêu gọi nhắn tin fanpage..."></textarea>
            </div>

            <!-- 3. Nội dung bài viết (Có thể chỉnh sửa tay hoặc để AI sinh khi đến giờ) -->
            <div style="margin-bottom: 1.1rem;">
                <label data-hint="Nếu bạn để trống, AI sẽ tự động sinh nội dung vào đúng thời điểm bài được đăng." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Nội Dung Bài Viết (Tự sinh hoặc Biên tập trước)
                </label>
                <textarea id="post_generated_content" name="generated_content" rows="9" class="glass-input" style="width: 100%; font-size: 0.88rem; line-height: 1.5; font-family: inherit;" placeholder="Để trống nếu muốn AI tự động sinh khi đến lịch đăng, hoặc bấm 'AI Viết Thử Ngay' ở trên để xem trước..."></textarea>
            </div>

            <!-- 4. Prompt Sinh Ảnh AI -->
            <div style="margin-bottom: 1.1rem;">
                <label data-hint="Mô tả hình ảnh bằng tiếng Anh hoặc tiếng Việt để AI DALL-E 3 vẽ ảnh." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Prompt Sinh Ảnh AI (DALL-E 3)
                </label>
                <textarea id="post_image_prompt" name="image_prompt" rows="2" class="glass-input" style="width: 100%; font-size: 0.85rem;" placeholder="Ví dụ: A futuristic high-speed cybersecurity VPN shield with neon blue fiber optic lights in cyberpunk style, 4k ultra-detailed"></textarea>
            </div>

        </div>

        <!-- Cột Phải: Hình Ảnh & Lên Lịch -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            
            <!-- Box Lên Lịch & Đăng Ngay -->
            <div class="glass-card" style="padding: 1.25rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.85rem;">⏰ Thời Gian Đăng Bài</h3>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.4rem;">Ngày & Giờ Xuất Bản</label>
                    <input type="datetime-local" name="scheduled_at" class="glass-input" value="<?= date('Y-m-d\TH:i') ?>" style="width: 100%;">
                </div>

                <div style="margin-bottom: 1.25rem; padding: 0.75rem; background: rgba(0, 122, 255, 0.06); border-radius: var(--radius-sm); border: 1px solid rgba(0, 122, 255, 0.15);">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--ios-blue);">
                        <input type="checkbox" name="publish_now" value="1" style="width: 16px; height: 16px; cursor: pointer;">
                        Đăng Lên Fanpage Ngay Lập Tức
                    </label>
                    <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.3rem;">
                        Hệ thống sẽ gọi AI và đẩy trực tiếp lên Facebook sau khi bấm Lưu.
                    </div>
                </div>

                <button type="submit" class="glass-btn" style="width: 100%; padding: 0.75rem; font-weight: 700; background: var(--ios-blue); color: #fff; cursor: pointer;">
                    💾 Lưu & Lên Lịch Đăng
                </button>
            </div>

            <!-- Box Tải Ảnh Thủ Công / Preview Ảnh -->
            <div class="glass-card" style="padding: 1.25rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.85rem;">🖼️ Ảnh Minh Họa</h3>
                
                <div style="margin-bottom: 0.85rem;">
                    <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.4rem;">Tải Ảnh Từ Máy Tính</label>
                    <input type="file" name="image_file" accept="image/*" class="glass-input" style="width: 100%; font-size: 0.8rem;" onchange="previewSelectedImage(event)">
                </div>

                <div style="margin-bottom: 0.85rem;">
                    <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.4rem;">Hoặc Đường Dẫn Ảnh (URL / Path)</label>
                    <input type="text" id="post_image_url" name="image_url" class="glass-input" placeholder="/uploads/posts/... hoặc https://..." style="width: 100%; font-size: 0.8rem;">
                </div>

                <div id="imagePreviewContainer" style="display: none; margin-top: 0.75rem; text-align: center;">
                    <img id="imagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 180px; border-radius: 8px; border: 1px solid var(--glass-border); object-fit: cover;">
                </div>
            </div>

        </div>

    </div>
</form>

<script>
document.getElementById('btnAiGenerate').addEventListener('click', async function() {
    const topic = document.getElementById('post_topic').value.trim();
    const contentPrompt = document.getElementById('post_content_prompt').value.trim();
    const imagePrompt = document.getElementById('post_image_prompt').value.trim();

    if (!topic) {
        alert('Vui lòng nhập Chủ đề bài viết trước khi bấm AI Viết Thử.');
        document.getElementById('post_topic').focus();
        return;
    }

    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ AI Đang Viết...';

    try {
        const formData = new FormData();
        formData.append('topic', topic);
        formData.append('content_prompt', contentPrompt);
        formData.append('image_prompt', imagePrompt);

        const res = await fetch('/admin/auto-post/ajax-generate', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.ok) {
            document.getElementById('post_generated_content').value = data.content;
            if (data.image_url) {
                document.getElementById('post_image_url').value = data.image_url;
                document.getElementById('imagePreview').src = data.image_url;
                document.getElementById('imagePreviewContainer').style.display = 'block';
            }
        } else {
            alert('Lỗi: ' + (data.message || 'Không thể sinh nội dung'));
        }
    } catch (e) {
        alert('Có lỗi xảy ra trong quá trình gọi AI: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

function previewSelectedImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreviewContainer').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/admin.php';
?>
