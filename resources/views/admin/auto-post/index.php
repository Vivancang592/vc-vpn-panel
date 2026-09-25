<?php
$pageTitle = "Quản Lý Đăng Bài Fanpage Tự Động - Quản Trị Hệ Thống";
$activeMenu = "auto-post";

$cronSecret = $settings['cron_secret_key'] ?? 'VC_VPN_CRON_2027_SECRET';
$cronUrl = '/api/cron/auto-post?key=' . urlencode($cronSecret);

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">📢 Tự Động Đăng Bài Fanpage AI</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Hàng đợi và lịch sử tự động tạo nội dung, sinh ảnh & đăng lên Facebook Fanpage</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= htmlspecialchars($cronUrl) ?>" target="_blank" class="glass-btn" style="text-decoration: none; font-size: 0.85rem; background: rgba(0, 122, 255, 0.15); color: var(--ios-blue);">
            ⚡ Chạy Quét Cron Ngay
        </a>
        <a href="/admin/settings" class="glass-btn" style="text-decoration: none; font-size: 0.85rem;">
            ⚙️ Cấu Hình Prompt & Model
        </a>
        <a href="/admin/auto-post/create" class="glass-btn" style="text-decoration: none; font-weight: 600; background: var(--ios-blue); color: #fff;">
            + Lên Lịch Bài Mới
        </a>
    </div>
</div>

<!-- Thống Kê Nhanh -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; margin-bottom: 1rem;">
    <div class="glass-card" style="padding: 0.9rem 1.1rem;">
        <div style="font-size: 0.75rem; color: var(--ios-text-secondary); font-weight: 600;">Tổng số bài</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--ios-text); margin-top: 0.25rem;"><?= (int)($counts['all'] ?? 0) ?></div>
    </div>
    <div class="glass-card" style="padding: 0.9rem 1.1rem; border-left: 3px solid var(--ios-warning, #f5a623);">
        <div style="font-size: 0.75rem; color: var(--ios-text-secondary); font-weight: 600;">Đang chờ đăng</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--ios-warning, #f5a623); margin-top: 0.25rem;"><?= (int)($counts['pending'] ?? 0) ?></div>
    </div>
    <div class="glass-card" style="padding: 0.9rem 1.1rem; border-left: 3px solid var(--ios-success);">
        <div style="font-size: 0.75rem; color: var(--ios-text-secondary); font-weight: 600;">Đã đăng thành công</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--ios-success); margin-top: 0.25rem;"><?= (int)($counts['published'] ?? 0) ?></div>
    </div>
    <div class="glass-card" style="padding: 0.9rem 1.1rem; border-left: 3px solid var(--ios-danger);">
        <div style="font-size: 0.75rem; color: var(--ios-text-secondary); font-weight: 600;">Thất bại / Lỗi</div>
        <div style="font-size: 1.4rem; font-weight: 700; color: var(--ios-danger); margin-top: 0.25rem;"><?= (int)($counts['failed'] ?? 0) ?></div>
    </div>
</div>

<!-- Bảng Danh Sách Bài Đăng -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 70px;">Hình Ảnh</th>
                    <th>Chủ Đề & Định Hướng AI</th>
                    <th style="text-align: center; width: 140px;">Lịch Đăng</th>
                    <th style="text-align: center; width: 130px;">Trạng Thái</th>
                    <th style="text-align: right; width: 220px;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($posts)): ?>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $post['id'] ?></td>
                            <td>
                                <?php if (!empty($post['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="Post Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid var(--glass-border); cursor: pointer;" onclick="openPreviewModal(<?= htmlspecialchars(json_encode($post, JSON_UNESCAPED_UNICODE)) ?>)">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: rgba(0,0,0,0.05); border-radius: 8px; display: grid; place-items: center; font-size: 1.2rem;" title="Chưa có ảnh">📄</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--ios-text);">
                                    <?= htmlspecialchars($post['topic']) ?>
                                </div>
                                <?php if (!empty($post['content_prompt'])): ?>
                                    <div style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-top: 0.2rem; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">
                                        <span style="font-weight: 600;">Prompt:</span> <?= htmlspecialchars($post['content_prompt']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($post['error_message'])): ?>
                                    <div style="font-size: 0.78rem; color: var(--ios-danger); margin-top: 0.25rem; font-weight: 500;">
                                        ⚠️ Lỗi: <?= htmlspecialchars($post['error_message']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-size: 0.82rem; font-weight: 500;">
                                <div><?= date('H:i', strtotime($post['scheduled_at'])) ?></div>
                                <div style="color: var(--ios-text-secondary); font-size: 0.75rem;"><?= date('d/m/Y', strtotime($post['scheduled_at'])) ?></div>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusStyle = match($post['status']) {
                                    'published' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'generating', 'publishing' => 'background: rgba(0, 122, 255, 0.15); color: var(--ios-blue);',
                                    'failed' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);',
                                    default => 'background: rgba(245, 166, 35, 0.15); color: #f5a623;'
                                };
                                $statusLabel = match($post['status']) {
                                    'published' => 'ĐÃ ĐĂNG',
                                    'generating' => 'ĐANG SINH AI',
                                    'publishing' => 'ĐANG ĐĂNG',
                                    'failed' => 'THẤT BẠI',
                                    'ready' => 'SẴN SÀNG',
                                    default => 'CHỜ ĐĂNG'
                                };
                                ?>
                                <span style="padding: 0.25rem 0.55rem; border-radius: var(--radius-sm); font-size: 0.72rem; font-weight: 700; display: inline-block; <?= $statusStyle ?>">
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.35rem; align-items: center; justify-content: flex-end;">
                                    <!-- Nút Preview giả lập Facebook Card -->
                                    <button type="button" class="glass-btn" style="padding: 0.35rem 0.6rem; font-size: 0.8rem;" title="Xem trước giao diện Facebook" onclick="openPreviewModal(<?= htmlspecialchars(json_encode($post, JSON_UNESCAPED_UNICODE)) ?>)">
                                        👁️ Xem
                                    </button>

                                    <?php if ($post['status'] !== 'published'): ?>
                                        <!-- Nút Đăng Ngay -->
                                        <a href="/admin/auto-post/force-publish?id=<?= $post['id'] ?>" class="glass-btn" style="padding: 0.35rem 0.6rem; font-size: 0.8rem; background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); text-decoration: none;" title="Đăng ngay lập tức" onclick="return confirm('Bạn có chắc muốn kích hoạt AI sinh và đăng bài này lên Fanpage ngay lập tức?');">
                                            🚀 Đăng
                                        </a>

                                        <!-- Nút Sửa -->
                                        <a href="/admin/auto-post/edit?id=<?= $post['id'] ?>" class="glass-btn" style="padding: 0.35rem 0.6rem; font-size: 0.8rem; text-decoration: none;">
                                            ✏️
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($post['status'] === 'failed'): ?>
                                        <!-- Nút Thử Lại -->
                                        <a href="/admin/auto-post/retry?id=<?= $post['id'] ?>" class="glass-btn" style="padding: 0.35rem 0.6rem; font-size: 0.8rem; background: rgba(245, 166, 35, 0.15); color: #f5a623; text-decoration: none;" title="Đặt lại trạng thái">
                                            🔄
                                        </a>
                                    <?php endif; ?>

                                    <!-- Nút Xóa -->
                                    <a href="/admin/auto-post/delete?id=<?= $post['id'] ?>" class="glass-btn" style="padding: 0.35rem 0.6rem; font-size: 0.8rem; background: rgba(255, 59, 48, 0.1); color: var(--ios-danger); text-decoration: none;" onclick="return confirm('Bạn có chắc muốn xóa bài đăng này?');" title="Xóa">
                                        🗑️
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--ios-text-secondary);">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📝</div>
                            <div>Chưa có bài viết nào trong hàng đợi.</div>
                            <div style="margin-top: 0.75rem;">
                                <a href="/admin/auto-post/create" class="glass-btn" style="text-decoration: none; font-size: 0.85rem;">+ Tạo Bài Đăng Mới</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Facebook Live Preview Card -->
<div id="facebookPreviewModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div class="glass-card" style="background: #ffffff; color: #1c1e21; width: 100%; max-width: 520px; border-radius: 12px; overflow: hidden; box-shadow: 0 12px 28px rgba(0,0,0,0.28); border: 1px solid #ced0d4;">
        
        <!-- Header Modal -->
        <div style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e4e6eb; background: #f0f2f5;">
            <div style="font-weight: 700; font-size: 0.95rem; color: #050505;">Xem Trước Bài Viết Facebook</div>
            <button type="button" onclick="closePreviewModal()" style="background: none; border: none; font-size: 1.3rem; cursor: pointer; color: #606770;">&times;</button>
        </div>

        <div style="padding: 1rem; max-height: 80vh; overflow-y: auto;">
            <!-- FB Post Header -->
            <div style="display: flex; gap: 0.6rem; align-items: center; margin-bottom: 0.75rem;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #1877f2; color: #fff; display: grid; place-items: center; font-weight: 700;">
                    VPN
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 0.9rem; color: #050505;">
                        <?= htmlspecialchars($settings['site_name'] ?? 'VC VPN') ?> <span style="color: #1877f2;">✓</span>
                    </div>
                    <div style="font-size: 0.75rem; color: #65676b;" id="modalPostTime">Vừa xong · 🌐</div>
                </div>
            </div>

            <!-- FB Post Body Text -->
            <div id="modalPostContent" style="font-size: 0.92rem; line-height: 1.5; color: #050505; white-space: pre-wrap; margin-bottom: 0.75rem;"></div>

            <!-- FB Post Media / Image -->
            <div id="modalPostMediaContainer" style="margin: -0.5rem -1rem 0.5rem -1rem; display: none;">
                <img id="modalPostImage" src="" alt="Post image" style="width: 100%; max-height: 380px; object-fit: cover; display: block;">
            </div>

            <!-- FB Post Footer Simulated -->
            <div style="border-top: 1px solid #e4e6eb; padding-top: 0.5rem; display: flex; justify-content: space-around; color: #65676b; font-size: 0.85rem; font-weight: 600;">
                <div>👍 Thích</div>
                <div>💬 Bình luận</div>
                <div>↗️ Chia sẻ</div>
            </div>
        </div>
    </div>
</div>

<script>
function openPreviewModal(post) {
    const modal = document.getElementById('facebookPreviewModal');
    const content = document.getElementById('modalPostContent');
    const mediaContainer = document.getElementById('modalPostMediaContainer');
    const image = document.getElementById('modalPostImage');
    const timeEl = document.getElementById('modalPostTime');

    content.textContent = post.generated_content || ('[Nội dung sẽ được AI tự động sinh theo chủ đề: "' + post.topic + '"]');
    
    if (post.image_url) {
        image.src = post.image_url;
        mediaContainer.style.display = 'block';
    } else if (post.image_prompt) {
        mediaContainer.style.display = 'none';
        content.textContent += '\n\n[🖼️ Ảnh sẽ được AI DALL-E 3 tự động sinh theo prompt: "' + post.image_prompt + '"]';
    } else {
        mediaContainer.style.display = 'none';
    }

    if (post.scheduled_at) {
        timeEl.textContent = 'Lên lịch: ' + post.scheduled_at + ' · 🌐';
    }

    modal.style.display = 'flex';
}

function closePreviewModal() {
    document.getElementById('facebookPreviewModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePreviewModal();
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/admin.php';
?>
