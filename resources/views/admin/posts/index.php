<?php
$pageTitle = "Quản Lý Bài Viết - Quản Trị Hệ Thống";
$activeMenu = "posts";

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Bài Viết & Tin Tức</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Danh sách bài viết tin tức, hướng dẫn và câu hỏi thường gặp</p>
    </div>
    <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
        <a href="/admin/posts/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">+ Thêm Bài Viết</a>
    </div>
</div>

<!-- Bảng Bài Viết -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tiêu Đề</th>
                    <th>Phân Loại</th>
                    <th>Tác Giả</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($posts)): ?>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $post['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.88rem; color: var(--ios-text);"><?= htmlspecialchars($post['title']) ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary); font-family: monospace;"><?= htmlspecialchars($post['slug']) ?></div>
                            </td>
                            <td>
                                <?php
                                $typeBadge = [
                                    'news'     => ['label' => 'TIN TỨC', 'style' => 'background: rgba(0, 122, 255, 0.15); color: var(--ios-blue);'],
                                    'tutorial' => ['label' => 'HƯỚNG DẪN', 'style' => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);'],
                                    'faq'      => ['label' => 'FAQ', 'style' => 'background: rgba(175, 82, 222, 0.15); color: #af52de;']
                                ];
                                $t = $typeBadge[$post['type'] ?? 'news'] ?? $typeBadge['news'];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $t['style'] ?>">
                                    <?= $t['label'] ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem;">
                                <?= htmlspecialchars($post['author_name'] ?? 'N/A') ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'published' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'draft'     => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'hidden'    => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$post['status'] ?? 'published'] ?? '' ?>">
                                    <?= strtoupper($post['status'] ?? 'published') ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($post['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu">
                                        <a href="/admin/posts/detail?id=<?= $post['id'] ?>" class="action-item">
                                            <span>👁️</span> Xem chi tiết
                                        </a>
                                        <a href="/admin/posts/edit?id=<?= $post['id'] ?>" class="action-item">
                                            <span>✏️</span> Chỉnh sửa
                                        </a>
                                        <form method="POST" action="/admin/posts/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bài viết này?');" style="margin: 0;">
                                            <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                            <button type="submit" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <span>🗑️</span> Xóa bài viết
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có bài viết nào được tạo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>