<?php
$pageTitle = "Chi Tiết Bài Viết - Quản Trị Hệ Thống";
$activeMenu = "posts";

ob_start();
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;"><?= htmlspecialchars($post['title'] ?? 'N/A') ?></h1>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
        <a href="/admin/posts/edit?id=<?= $post['id'] ?>" class="glass-btn" style="text-decoration: none; white-space: nowrap;">✏️ Chỉnh Sửa</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
    <!-- Thông Tin Bài Viết -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Chung</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>Mã Bài Viết:</strong> #<?= $post['id'] ?></div>
            <div><strong>Tác Giả:</strong> <?= htmlspecialchars($post['author_name'] ?? 'N/A') ?> (ID #<?= $post['author_id'] ?>)</div>
            <div>
                <strong>Đường Dẫn Tĩnh:</strong> 
                <code style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700;">
                    /<?= htmlspecialchars($post['slug']) ?>
                </code>
            </div>
            <div>
                <strong>Phân Loại:</strong> 
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
            </div>
            <div>
                <strong>Trạng Thái:</strong> 
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
            </div>
            <div><strong>Ngày Tạo:</strong> <?= date('d/m/Y H:i:s', strtotime($post['created_at'])) ?></div>
        </div>
    </div>
</div>

<!-- Nội dung chi tiết bài viết -->
<div class="glass-card" style="padding: 1.5rem; width: 100%;">
    <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Nội Dung Chi Tiết</h2>
    <div style="font-size: 0.95rem; line-height: 1.6; color: var(--ios-text); white-space: pre-wrap;">
        <?= htmlspecialchars($post['content'] ?? '') ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>