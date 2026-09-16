<?php
$pageTitle = "Chỉnh Sửa Bài Viết - Quản Trị Hệ Thống";
$activeMenu = "posts";

// Lấy đường dẫn ảnh thumbnail từ mã ẩn và làm sạch nội dung trước khi đưa vào TinyMCE
$existingThumb = '';
$cleanContent = $post['content'] ?? '';

if (!empty($post['content'])) {
    if (preg_match('/<!--thumbnail:(.*?)-->/i', $post['content'], $matches)) {
        $existingThumb = $matches[1];
        $cleanContent = preg_replace('/<!--thumbnail:.*?-->/i', '', $cleanContent);
    }
}

ob_start();
?>

<!-- Thư viện TinyMCE 6.8.3 miễn phí -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>

<style>
/* Cấu hình hiển thị TinyMCE trên nền giao diện Glassmorphism */
.tox-tinymce-aux { z-index: 999999 !important; }
.tox-tinymce { border-radius: var(--radius-md) !important; border: 1px solid var(--glass-border) !important; }
</style>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Chỉnh Sửa Bài Viết #<?= $post['id'] ?></h1>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="glass-card" style="padding: 1.75rem; width: 100%;">
    <form method="POST" action="/admin/posts/edit?id=<?= $post['id'] ?>" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tiêu Đề Bài Viết (*)</label>
            <input type="text" name="title" class="glass-input" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required style="width: 100%;">
        </div>

        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Thay Đổi Ảnh Đại Diện Từ Máy Tính (Để trống nếu giữ ảnh hiện tại)</label>
            <input type="file" name="thumbnail" accept="image/*" class="glass-input" style="width: 100%; padding: 0.4rem;">
            <?php if (!empty($existingThumb)): ?>
                <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 0.8rem; color: var(--ios-text-secondary);">Ảnh hiện tại:</span>
                    <img src="<?= htmlspecialchars($existingThumb) ?>" alt="Current Thumb" style="height: 40px; border-radius: 4px; border: 1px solid var(--glass-border);">
                </div>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Đường Dẫn Tĩnh (Slug)</label>
                <input type="text" name="slug" class="glass-input" value="<?= htmlspecialchars($post['slug'] ?? '') ?>" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Phân Loại (*)</label>
                <select name="type" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="tutorial" <?= ($post['type'] ?? '') === 'tutorial' ? 'selected' : '' ?>>Hướng dẫn (Tutorial)</option>
                    <option value="faq" <?= ($post['type'] ?? '') === 'faq' ? 'selected' : '' ?>>Thông báo (Notice)</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái (*)</label>
                <select name="status" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Xuất bản (Published)</option>
                    <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Bản nháp (Draft)</option>
                    <option value="hidden" <?= ($post['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Ẩn bài viết (Hidden)</option>
                </select>
            </div>
        </div>

        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Nội Dung Bài Viết (*)</label>
            <textarea id="post_content" name="content" rows="12" class="glass-input" placeholder="Soạn thảo nội dung bài viết ở đây..." style="width: 100%; resize: vertical; line-height: 1.5;"><?= htmlspecialchars($cleanContent) ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">💾 Cập Nhật Bài Viết</button>
        </div>
    </form>
</div>

<script>
tinymce.init({
    selector: '#post_content',
    height: 420,
    menubar: false,
    promotion: false, 
    branding: false,  
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount',
    toolbar: 'undo redo | blocks | bold italic textcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | removeformat | code fullscreen',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.5; } img { max-width: 100%; height: auto; } iframe { max-width: 100%; }',
    setup: function(editor) {
        editor.on('change', function() {
            editor.save(); 
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>