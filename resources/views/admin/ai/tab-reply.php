<?php
/**
 * Tab "Trả Lời Tự Động" (/admin/ai/reply) — quyết định D7:
 * CHỈ hiển thị trạng thái 2 kênh auto-reply + Nội Quy AI + hội thoại gần nhất.
 * KHÔNG có form test chat / gửi tin nhắn thử. Chi tiết hội thoại nằm ở
 * /admin/ai/conversations.
 *
 * Biến: $chatEnabled, $commentEnabled, $conversationStats, $recentSessions,
 *       $csrf_token, $tabConfig, $aiLabels.
 */
$pageTitle = 'Trả Lời Tự Động - Trung Tâm AI';
$activeMenu = 'ai-reply';

$recentSessions = is_array($recentSessions ?? null) ? $recentSessions : [];
$convStats = is_array($conversationStats ?? null) ? $conversationStats : ['web' => 0, 'fanpage' => 0, 'total' => 0];

ob_start();

require __DIR__ . '/_flash.php';
require __DIR__ . '/_tab-header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; align-items: start;">

    <!-- Kênh 1: Chat website -->
    <div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; border-left: 4px solid <?= ($chatEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h2 style="font-size: 1.05rem; font-weight: 700;">💬 Chat Website</h2>
            <span style="font-size: 0.8rem; font-weight: 700; color: <?= ($chatEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
                <?= ($chatEnabled ?? false) ? '● ĐANG BẬT' : '○ ĐANG TẮT' ?>
            </span>
        </div>
        <p style="font-size: 0.82rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
            AI tự trả lời tin nhắn khách trên khung chat website qua module <code>support_chat</code>,
            tuân thủ Nội Quy <code>rules_auto_reply</code> (tối đa 3–4 câu, không markdown, không bịa giá,
            không xin mật khẩu/OTP).
        </p>
        <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">
            Hội thoại nguồn <strong>Website</strong>: <strong style="color: var(--ios-text);"><?= (int) ($convStats['web'] ?? 0) ?></strong>
        </div>
    </div>

    <!-- Kênh 2: Bình luận + Messenger Fanpage -->
    <div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; border-left: 4px solid <?= ($commentEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h2 style="font-size: 1.05rem; font-weight: 700;">📢 Bình Luận Fanpage</h2>
            <span style="font-size: 0.8rem; font-weight: 700; color: <?= ($commentEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
                <?= ($commentEnabled ?? false) ? '● ĐANG BẬT' : '○ ĐANG TẮT' ?>
            </span>
        </div>
        <p style="font-size: 0.82rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
            AI tự trả lời bình luận bài viết fanpage (1–2 câu) qua module <code>fanpage_comment</code>,
            cùng Nội Quy <code>rules_auto_reply</code>. Bật/tắt từng kênh tại
            <a href="/admin/settings" style="color: var(--ios-blue);">Cài Đặt Hệ Thống</a>.
        </p>
        <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">
            Hội thoại nguồn <strong>Facebook</strong>: <strong style="color: var(--ios-text);"><?= (int) ($convStats['fanpage'] ?? 0) ?></strong>
        </div>
    </div>
</div>

<!-- Hội thoại AI đã trả lời -->
<div class="glass-card" style="padding: 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.9rem;">
        <h2 style="font-size: 1.05rem; font-weight: 700;">🗨️ Hội Thoại AI Đã Trả Lời <span style="font-size: 0.75rem; font-weight: 400; color: var(--ios-text-secondary);">— tổng <?= (int) ($convStats['total'] ?? 0) ?> phiên</span></h2>
    </div>

    <?php if ($recentSessions === []): ?>
        <p style="font-size: 0.85rem; color: var(--ios-text-secondary); text-align: center; padding: 1rem;">Chưa có hội thoại nào.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 100px;">Nguồn</th>
                        <th>Khách & tin nhắn cuối</th>
                        <th style="width: 120px; text-align: center;">Cập nhật</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSessions as $s): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= (int) ($s['id'] ?? 0) ?></td>
                            <td style="text-align: center;">
                                <span style="font-size: 0.75rem; font-weight: 700; color: <?= (($s['source'] ?? '') === 'fanpage') ? 'var(--ios-blue)' : 'var(--ios-success)' ?>;">
                                    <?= (($s['source'] ?? '') === 'fanpage') ? 'Facebook' : 'Website' ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars((string) ($s['username'] ?? 'Khách chưa đăng nhập')) ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary); display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars((string) ($s['last_message'] ?? '')) ?></div>
                            </td>
                            <td style="text-align: center; font-size: 0.78rem;"><?= htmlspecialchars(!empty($s['updated_at']) ? date('d/m H:i', strtotime((string) $s['updated_at'])) : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="glass-card" style="padding: 1rem 1.25rem; width: 100%; box-sizing: border-box; border-left: 3px solid var(--ios-blue);">
    <div style="font-size: 0.8rem; color: var(--ios-text-secondary); line-height: 1.7;">
        💡 Nội dung trả lời được điều khiển bởi prompt <code>support_chat</code> / <code>fanpage_comment</code> và
        Nội Quy <code>rules_auto_reply</code>. Sửa tại
        <a href="/admin/ai/settings" style="color: var(--ios-blue);">Cấu Hình AI → Nội Quy Hệ Thống</a>.
        Tab này chỉ theo dõi — không gửi tin nhắn thử theo đúng thiết kế D7.
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
