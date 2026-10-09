<?php
/**
 * Tab "Trả Lời Tự Động" (/admin/ai/reply) — quyết định D7:
 * CHỈ hiển thị trạng thái 2 kênh auto-reply + Nội Quy AI + hội thoại AI đã
 * trả lời + DANH SÁCH hội thoại (gộp từ trang /admin/ai/conversations).
 * KHÔNG có form test chat / gửi tin nhắn thử.
 *
 * Biến: $chatEnabled, $commentEnabled, $conversationStats,
 *       $convSessions, $convTotal, $convPage, $convPerPage, $convSource,
 *       $convKeyword, $sourceLabels, $statusLabels,
 *       $csrf_token, $tabConfig, $aiLabels.
 */
$pageTitle = 'Trả Lời Tự Động - Trung Tâm AI';
$activeMenu = 'ai-reply';

$convStats = is_array($conversationStats ?? null) ? $conversationStats : ['web' => 0, 'fanpage' => 0, 'total' => 0];

// Danh sách hội thoại (cab mới phía dưới).
$convSessions = is_array($convSessions ?? null) ? $convSessions : [];
$convTotal = (int) ($convTotal ?? 0);
$convPage = max(1, (int) ($convPage ?? 1));
$convPerPage = max(1, (int) ($convPerPage ?? 10));
$convSource = in_array(($convSource ?? ''), ['web', 'fanpage'], true) ? (string) $convSource : '';
$convKeyword = trim((string) ($convKeyword ?? ''));
$sourceLabels = $sourceLabels ?? ['web' => 'Website', 'fanpage' => 'Facebook'];
$statusLabels = $statusLabels ?? ['open' => 'Đang mở', 'handoff' => 'Chờ nhân viên', 'closed' => 'Đã đóng'];

$convTotalPages = $convPerPage > 0 ? (int) ceil($convTotal / $convPerPage) : 1;

// URL phân trang / lọc nguồn / tìm kiếm trên CHÍNH tab này.
$convQueryBase = [];
if ($convSource !== '') {
    $convQueryBase['source'] = $convSource;
}
if ($convKeyword !== '') {
    $convQueryBase['q'] = $convKeyword;
}
$convBuildUrl = static function (array $extra) use ($convQueryBase): string {
    $qs = http_build_query(array_merge($convQueryBase, $extra));
    return '/admin/ai/reply' . ($qs !== '' ? '?' . $qs : '');
};

// URL quay lại sau khi đóng hội thoại (giữ bộ lọc + trang hiện tại).
$convBackParams = array_intersect_key($_GET, array_flip(['source', 'q', 'page']));
$convBackQs = http_build_query($convBackParams);
$convBack = '/admin/ai/reply' . ($convBackQs !== '' ? '?' . $convBackQs : '');

$sourceColor = static function (string $src): string {
    return $src === 'fanpage' ? '#1877F2' : 'var(--ios-blue)';
};

ob_start();

require __DIR__ . '/_flash.php';
require __DIR__ . '/_tab-header.php';
?>

<?php
// Model Trả Lời Tự Động — CHỌN TRỰC TIẾP TRONG TAB (bắt buộc, thiếu model báo ngay).
// Gán override cho CẢ support_chat + fanpage_comment cùng lúc → 2 kênh đồng bộ.
$_replyModels = is_array($replyModels ?? null) ? $replyModels : [];
$_currentModel = trim((string) ($replyModelOverride ?? ''));
$_fpModel = trim((string) ($fpModelOverride ?? ''));
$_modelMismatch = ($_fpModel !== '' && $_currentModel !== '' && $_fpModel !== $_currentModel);
$_catalogKeys = array_map(static fn ($m): string => (string) ($m['model_key'] ?? ''), $_replyModels);
?>
<div class="glass-card" style="padding: 1.1rem 1.3rem; margin-bottom: 1rem; border: 1px solid <?= $_currentModel === '' ? 'var(--ios-danger)' : 'var(--glass-border)' ?>;">
    <form method="post" action="/admin/ai/modules/set-model-override" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? '')) ?>">
        <input type="hidden" name="back" value="/admin/ai/reply">
        <input type="hidden" name="module_key" value="support_chat,fanpage_comment">
        <div style="flex: 1 1 280px; min-width: 220px;">
            <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 0.35rem;">Model <span style="color: var(--ios-danger);">*</span></label>
            <select name="model_override" class="glass-input" style="width: 100%;" required>
                <option value="">— Chọn model trả lời —</option>
                <?php if ($_currentModel !== '' && !in_array($_currentModel, $_catalogKeys, true)): ?>
                    <option value="<?= htmlspecialchars($_currentModel) ?>" selected><?= htmlspecialchars($_currentModel) ?> (không còn trong danh mục)</option>
                <?php endif; ?>
                <?php foreach ($_replyModels as $m): $mk = (string) ($m['model_key'] ?? ''); if ($mk === '') { continue; } ?>
                    <option value="<?= htmlspecialchars($mk) ?>" <?= $mk === $_currentModel ? 'selected' : '' ?>><?= htmlspecialchars((string) ($m['model_name'] ?? $mk)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="glass-btn" style="font-weight: 700; background: var(--ios-blue); color: var(--soc-ink);">Lưu</button>
    </form>
    <?php if ($_currentModel === ''): ?>
        <div style="margin-top: 0.7rem; font-size: 0.85rem; color: var(--ios-danger); font-weight: 700;">▲ Chưa chọn Model — Chatbot và Comment AI CHƯA TRẢ LỜI được. Chọn model rồi bấm Lưu ngay.</div>
    <?php endif; ?>
    <?php if ($_modelMismatch): ?>
        <div style="margin-top: 0.7rem; font-size: 0.83rem; color: var(--ios-danger);">▲ Fanpage Comment đang dùng <b><?= htmlspecialchars($_fpModel) ?></b> khác model tab này — bấm Lưu để đồng bộ cả 2 kênh.</div>
    <?php endif; ?>
    <div style="margin-top: 0.6rem; font-size: 0.78rem; color: var(--text-muted);">Áp dụng CẢ Trả Lời Web + Fanpage Comment — model lấy trực tiếp từ đây, không còn model mặc định ngầm.</div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; align-items: start;">

    <!-- Kênh 1: Chat website -->
    <div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; border-left: 4px solid <?= ($chatEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h2 style="font-size: 1.05rem; font-weight: 700;">Chat Web</h2>
            <span style="font-size: 0.8rem; font-weight: 700; color: <?= ($chatEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
                <?= ($chatEnabled ?? false) ? '● ĐANG BẬT' : '○ ĐANG TẮT' ?>
            </span>
        </div>
        <p style="font-size: 0.82rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
            AI tự trả lời tin nhắn khách trên khung chat website qua module <code>support_chat</code>,
            tuân thủ nội quy trong <code>support_chat</code> (tối đa 3–4 câu, không markdown, không bịa giá,
            không xin mật khẩu/OTP).
        </p>
        <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">
            Hội thoại nguồn <strong>Website</strong>: <strong style="color: var(--ios-text);"><?= (int) ($convStats['web'] ?? 0) ?></strong>
        </div>
    </div>

    <!-- Kênh 2: Bình luận + Messenger Fanpage -->
    <div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; border-left: 4px solid <?= ($commentEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h2 style="font-size: 1.05rem; font-weight: 700;">Bình Luận</h2>
            <span style="font-size: 0.8rem; font-weight: 700; color: <?= ($commentEnabled ?? false) ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;">
                <?= ($commentEnabled ?? false) ? '● ĐANG BẬT' : '○ ĐANG TẮT' ?>
            </span>
        </div>
        <p style="font-size: 0.82rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
            AI tự trả lời bình luận bài viết fanpage (1–2 câu) qua module <code>fanpage_comment</code>,
            cùng nội quy đã gộp sẵn trong prompt. Bật/tắt từng kênh tại
            <a href="/admin/settings" style="color: var(--ios-blue);">Cài Đặt</a>.
        </p>
        <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">
            Hội thoại nguồn <strong>Facebook</strong>: <strong style="color: var(--ios-text);"><?= (int) ($convStats['fanpage'] ?? 0) ?></strong>
        </div>
    </div>
</div>

<!-- Tổng quan hội thoại AI đã trả lời -->
<div class="glass-card" style="padding: 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.9rem;">
        <h2 style="font-size: 1.05rem; font-weight: 700;">Hội Thoại <span style="font-size: 0.75rem; font-weight: 400; color: var(--ios-text-secondary);">— tổng <?= (int) ($convStats['total'] ?? 0) ?> phiên</span></h2>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; font-size: 0.8rem;">
        <?php
        $statusChips = [
            'open'    => ['Đang mở', 'var(--ios-success)'],
            'handoff' => ['Chờ nhân viên', 'var(--ios-warning, #ff9f0a)'],
            'closed'  => ['Đã đóng', 'var(--ios-text-secondary)'],
        ];
        foreach ($statusChips as $stKey => $stMeta):
            $stCount = (int) (($statusStats ?? [])[$stKey] ?? 0);
        ?>
            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.65rem; border-radius: 999px; border: 1px solid <?= $stMeta[1] ?>; color: <?= $stMeta[1] ?>; font-weight: 600;">
                ● <?= htmlspecialchars($stMeta[0]) ?>: <?= $stCount ?>
            </span>
        <?php endforeach; ?>
        <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.65rem; border-radius: 999px; border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); color: var(--ios-text-secondary);">
            WEB: <?= (int) ($convStats['web'] ?? 0) ?> · FB: <?= (int) ($convStats['fanpage'] ?? 0) ?>
        </span>
    </div>
    <p style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-top: 0.6rem;">
        Hội thoại AI trả lời mà khách không phản hồi lại quá 5 phút sẽ <strong>tự động đóng</strong> (bộ nhớ AI được làm mới).
        Hội thoại chuyển nhân viên chờ admin bấm <strong>Đã xử lý</strong> bên dưới.
    </p>
</div>

<!-- CAB: Danh sách hội thoại (gộp từ trang /admin/ai/conversations) -->
<div id="cab-conversations" class="glass-card" style="padding: 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box; scroll-margin-top: 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.9rem;">
        <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Hội Thoại</h2>
        <form method="POST" action="/admin/ai/conversations/delete-closed" style="margin: 0;" onsubmit="return confirm('Xóa vĩnh viễn tất cả hội thoại đã đóng và toàn bộ tin nhắn trong đó? Thao tác này không thể hoàn tác.');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="back" value="<?= htmlspecialchars($convBack) ?>">
            <button type="submit" title="Xóa vĩnh viễn tất cả hội thoại đã đóng" style="padding: 0.4rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-danger); background: transparent; color: var(--ios-danger); font-size: 0.8rem; font-weight: 700; cursor: pointer;">
                Purge
            </button>
        </form>
    </div>

    <!-- Tìm kiếm -->
    <form method="GET" action="/admin/ai/reply" style="display: flex; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.75rem;">
        <input type="text" name="q" value="<?= htmlspecialchars($convKeyword) ?>" placeholder="Tìm theo tên khách hoặc nội dung tin nhắn..." style="flex: 0 1 320px; min-width: 180px; padding: 0.45rem 0.7rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); background: transparent; color: inherit; font-size: 0.85rem;">
        <?php if ($convSource !== ''): ?>
            <input type="hidden" name="source" value="<?= htmlspecialchars($convSource) ?>">
        <?php endif; ?>
        <button type="submit" style="padding: 0.45rem 0.9rem; border-radius: var(--radius-sm); border: none; background: var(--ios-blue); color: var(--soc-ink); font-weight: 600; font-size: 0.85rem; cursor: pointer;">Tìm</button>
        <?php if ($convKeyword !== ''): ?>
            <a href="<?= htmlspecialchars($convBuildUrl(['q' => null])) ?>" style="text-decoration: none; padding: 0.45rem 0.9rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); color: var(--ios-text-secondary); font-size: 0.85rem;">Xoá lọc</a>
        <?php endif; ?>
    </form>

    <!-- Lọc nguồn -->
    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.9rem;">
        <?php
        $convPills = [
            ''         => ['Tất cả nguồn', 'var(--ios-blue)'],
            'web'      => ['Web', 'var(--ios-blue)'],
            'fanpage'  => ['FB', '#1877F2'],
        ];
        foreach ($convPills as $pillSource => $pillMeta):
            $isActive = $convSource === $pillSource;
            $activeColor = $pillSource === 'fanpage' ? '#1877F2' : 'var(--ios-blue)';
        ?>
            <a href="<?= htmlspecialchars($convBuildUrl(['source' => $pillSource === '' ? null : $pillSource, 'page' => null])) ?>"
               style="text-decoration: none; padding: 0.35rem 0.7rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; border: 1px solid <?= $isActive ? $activeColor : 'var(--ios-border, rgba(255,255,255,0.15))' ?>; color: <?= $isActive ? $activeColor : 'var(--ios-text-secondary)' ?>;">
                <?= $pillMeta[0] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <p style="color: var(--ios-text-secondary); font-size: 0.8rem; margin: 0 0 0.6rem;">
        Tổng số hội thoại: <strong><?= (int) $convTotal ?></strong>
    </p>

    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nguồn</th>
                    <th>Người Dùng</th>
                    <th>Số Tin</th>
                    <th>Tin Cuối</th>
                    <th>Trạng Thái</th>
                    <th>Lưu</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($convSessions)): ?>
                    <?php foreach ($convSessions as $s): ?>
                        <?php
                        $src = (string) ($s['source'] ?? 'web');
                        $uid = (int) ($s['user_id'] ?? 0);
                        $uname = trim((string) ($s['username'] ?? ''));
                        $uemail = trim((string) ($s['user_email'] ?? ''));
                        $lastMsg = trim((string) ($s['last_message'] ?? ''));
                        if (mb_strlen($lastMsg) > 90) {
                            $lastMsg = mb_substr($lastMsg, 0, 90) . '…';
                        }
                        $lastRole = (string) ($s['last_role'] ?? '');
                        $stKey = (string) ($s['status'] ?? '');
                        $stLabel = $statusLabels[$stKey] ?? $stKey;
                        $stColor = $stKey === 'open' ? 'var(--ios-success)'
                            : ($stKey === 'handoff' ? 'var(--ios-warning, #ff9f0a)' : 'var(--ios-text-secondary)');
                        $canClose = $stKey !== 'closed';
                        ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= (int) $s['id'] ?></td>
                            <td>
                                <span style="font-weight: 600; font-size: 0.82rem; color: <?= $sourceColor($src) ?>;">
                                    <?= $src === 'fanpage' ? 'FB' : 'WEB' ?> <?= htmlspecialchars($sourceLabels[$src] ?? $src) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.82rem;">
                                <?php if ($uid > 0): ?>
                                    <span style="color: var(--ios-success); font-weight: 600;"><?= vc_admin_icon('USR', 13) ?> <?= htmlspecialchars($uname !== '' ? $uname : ('User #' . $uid)) ?></span>
                                    <div style="color: var(--ios-text-secondary); font-size: 0.72rem;">Đã đăng nhập<?= $uemail !== '' ? ' · ' . htmlspecialchars($uemail) : '' ?></div>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary);">Khách vãng lai</span>
                                    <div style="color: var(--ios-text-secondary); font-size: 0.72rem;">Chưa đăng nhập</div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.82rem;"><?= (int) ($s['message_count'] ?? 0) ?></td>
                            <td style="font-size: 0.8rem; max-width: 300px;">
                                <?php if ($lastMsg !== ''): ?>
                                    <span style="color: var(--ios-text-secondary); font-size: 0.72rem;"><?= $lastRole === 'assistant' ? vc_admin_icon('RPL', 12) . ':' : ($lastRole === 'user' ? vc_admin_icon('USR', 12) . ':' : '') ?></span>
                                    <?= htmlspecialchars($lastMsg) ?>
                                <?php else: ?>
                                    <span style="color: var(--ios-text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem; font-weight: 600; color: <?= $stColor ?>;">
                                <span style="display: inline-flex; align-items: center; gap: 0.3rem;">● <?= htmlspecialchars($stLabel) ?></span>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars((string) ($s['updated_at'] ?? $s['created_at'] ?? '')) ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác" aria-label="Thao tác hội thoại">⋮</button>
                                    <div class="action-menu" style="min-width: 185px; white-space: nowrap;">
                                        <a href="/admin/ai/conversations/detail?id=<?= (int) $s['id'] ?>" class="action-item">
                                            <span>◉</span> Xem
                                        </a>
                                        <?php if ($canClose): ?>
                                            <form method="POST" action="/admin/ai/conversations/close" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                <input type="hidden" name="back" value="<?= htmlspecialchars($convBack) ?>">
                                                <button type="submit" class="action-item" style="font-family: inherit;">
                                                    <span>✓</span> Đã xử lý
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="/admin/ai/conversations/delete" style="margin: 0;" onsubmit="return confirm('Xóa vĩnh viễn hội thoại này và toàn bộ tin nhắn trong đó? Thao tác này không thể hoàn tác.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                <input type="hidden" name="back" value="<?= htmlspecialchars($convBack) ?>">
                                                <button type="submit" class="action-item delete" style="font-family: inherit;">
                                                    <span>✕</span> Xóa
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">
                            Chưa có hội thoại nào<?= $convKeyword !== '' || $convSource !== '' ? ' khớp bộ lọc' : '' ?>. Hội thoại xuất hiện khi khách chat trên website hoặc qua Facebook Fanpage.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($convTotalPages > 1): ?>
        <div style="display: flex; gap: 0.35rem; justify-content: center; margin-top: 1rem; flex-wrap: wrap;">
            <?php for ($p = 1; $p <= $convTotalPages; $p++): ?>
                <a href="<?= htmlspecialchars($convBuildUrl(['page' => $p])) ?>"
                   style="text-decoration: none; padding: 0.3rem 0.65rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; border: 1px solid <?= $p === $convPage ? 'var(--ios-blue)' : 'var(--ios-border, rgba(255,255,255,0.15))' ?>; color: <?= $p === $convPage ? 'var(--ios-blue)' : 'var(--ios-text-secondary)' ?>;">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<div class="glass-card" style="padding: 1rem 1.25rem; width: 100%; box-sizing: border-box; border-left: 3px solid var(--ios-blue);">
    <div style="font-size: 0.8rem; color: var(--ios-text-secondary); line-height: 1.7;">
        Nội dung trả lời được điều khiển bởi prompt <code>support_chat</code> / <code>fanpage_comment</code> và
        Nội quy đã được gộp sẵn trong prompt. Sửa tại
        <a href="/admin/ai/settings" style="color: var(--ios-blue);">AI</a>.
        Tab này chỉ theo dõi — không gửi tin nhắn thử theo đúng thiết kế D7.
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
