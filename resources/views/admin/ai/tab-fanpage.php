<?php
/**
 * Tab "Nội Dung Fanpage" (/admin/ai/fanpage) — hợp nhất 3 luồng:
 *   1. Giao AI Core viết bài theo chủ đề (POST /admin/ai/articles/store-topics
 *      → AiTaskController::storeTopics — xếp hàng đợi session; AJAX
 *      POST /admin/ai/articles/write-next → writeNext viết TUẦN TỰ 1 bài/lần).
 *   2. (ĐÃ GỠ TOÀN BỘ) Chiến dịch đăng tự động + hàng đợi vc_scheduled_posts —
 *      việc LÊN LỊCH một bài AI giờ nằm ở CAB Danh Sách Bài Viết dưới đây.
 *   3. (CHUYỂN TỪ TRANG outputs.php CỦ) Danh Sách Bài Viết + popup lên lịch —
 *      trước đây ở /admin/ai/outputs riêng; URL cũ giờ redirect về tab này,
 *      xem chi tiết vẫn mở thẳng resources/views/admin/ai/output-detail.php.
 *
 * Giao diện 3 CAB trên cùng trang: (1) form giao việc (2) tiến trình viết
 * tuần tự (3) Danh Sách Bài Viết (tabs Tất cả / Đang lên lịch / Đã đăng).
 *
 * Biến: $csrf_token, $tabConfig, $aiLabels, $writeQueue (hàng đợi session),
 *       $articles, $filter, $tabs, $postStatus, $imageGallery (danh sách bài).
 */
$pageTitle = 'Nội Dung Fanpage - Trung Tâm AI';
$activeMenu = 'ai-fanpage';

$writeQueue = is_array($writeQueue ?? null) ? $writeQueue : null;
$fpQueueJson = json_encode($writeQueue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

ob_start();

require __DIR__ . '/_flash.php';
require __DIR__ . '/_tab-header.php';
?>

<!-- ============ CAB 1: FORM GIAO VIỆC (ở trên) ============ -->
<div class="glass-card" style="padding: 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box; border-left: 4px solid var(--ios-blue);">
    <h2 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.35rem;">✍️ Giao AI Viết Bài Theo Chủ Đề</h2>
    <p style="font-size: 0.8rem; color: var(--ios-text-secondary); margin-bottom: 0.9rem;">
        Nhập nhiều chủ đề, AI sẽ viết từng bài một theo Nội Quy AI. Bài hoàn tất sẽ chuyển sang Danh Sách Bài Viết — vào đó để xem, copy prompt tạo ảnh hoặc hẹn giờ đăng lên fanpage.
    </p>
    <form action="/admin/ai/articles/store-topics" method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; align-items: start;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem;">Danh sách chủ đề <span style="color: var(--ios-danger);">*</span></label>
            <textarea name="topics" rows="4" class="glass-input" style="width: 100%; font-size: 0.88rem; line-height: 1.6; resize: vertical; min-height: 6rem;" placeholder="Giảm lag khi chơi game&#10;Bảo mật wifi quán cafe&#10;VPN 4G cho điện thoại" required></textarea>
            <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.35rem;">Mỗi dòng hoặc dấu phẩy phân cách một chủ đề · tối đa 50</div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.82rem; margin-bottom: 0.3rem;">Số bài mỗi chủ đề <span style="font-weight: 400; color: var(--ios-text-secondary);">(1–10)</span></label>
                <input type="number" name="per_topic" class="glass-input" value="1" min="1" max="10" style="width: 100%;">
            </div>
            <button type="submit" class="glass-btn" style="width: 100%; justify-content: center; font-weight: 700; background: var(--ios-blue); color: #fff;">🤖 Viết Bài</button>
        </div>
    </form>
</div><!-- /CAB 1 -->

<!-- ============ CAB 2: TIẾN TRÌNH VIẾT TUẦN TỰ (phía dưới form) ============ -->
<div id="fp-pane-progress">
    <div class="glass-card" style="padding: 1.25rem; margin-bottom: 1rem; width: 100%; box-sizing: border-box; border-left: 4px solid var(--ios-warning, #ff9f0a);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.5rem;">
            <h2 style="font-size: 1.05rem; font-weight: 700;">📋 Tiến Trình Viết Bài</h2>
        </div>
        <div style="font-size: 0.78rem; margin-bottom: 0.85rem; color: var(--ios-text-secondary);">
            Trạng thái hàng đợi: Đang viết, Đang chờ, Lỗi. Bài đã hoàn tất sẽ tự biến khỏi hàng và chuyển sang Danh Sách Bài Viết.
        </div>

        <div id="fp-rows" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>

        <div id="fp-empty" style="font-size: 0.85rem; color: var(--ios-text-secondary); padding: 1rem 0;">
            Chưa có chủ đề nào chờ viết. Nhập chủ đề ở ô phía trên và bấm nút Giao AI Viết Bài — bảng tiến trình sẽ hiển thị tại đây.
        </div>

        <div id="fp-summary" style="display: none; font-size: 0.85rem; margin-top: 0.85rem; padding-top: 0.8rem; border-top: 1px dashed var(--ios-border); color: var(--ios-text-secondary);"></div>

        <div id="fp-error" style="display: none; margin-top: 0.7rem; font-size: 0.82rem; color: var(--ios-danger); font-weight: 600;"></div>

        <div style="margin-top: 0.9rem; display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <button type="button" id="fp-start" class="glass-btn" style="font-weight: 700; background: var(--ios-blue); color: #fff; display: none;">▶️ Bắt Đầu Viết</button>
        </div>
    </div>
</div><!-- /fp-pane-progress -->

<!-- ============ CAB 3: DANH SÁCH BÀI VIẾT (chuyển từ trang outputs.php) ============ -->
<div id="fp-articles" style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.85rem;">
        <div>
            <h2 style="font-size: 1.05rem; font-weight: 700; word-break: break-word;">📚 Danh Sách Bài Viết</h2>
            <p style="color: var(--ios-text-secondary); font-size: 0.82rem; margin-top: 0.2rem;">
                Bài viết do AI Core sinh ra. Xem chi tiết, sao chép prompt tạo ảnh, lên lịch đăng lên fanpage hoặc xóa bài.
            </p>
        </div>
        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <?php foreach ($tabs as $tabKey => $tabLabel): ?>
                <?php $tabHref = $tabKey === '' ? '/admin/ai/fanpage' : '/admin/ai/fanpage?tab=' . urlencode($tabKey); ?>
                <a href="<?= htmlspecialchars($tabHref) ?>"
                   style="text-decoration: none; padding: 0.35rem 0.7rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; border: 1px solid <?= $filter === $tabKey ? 'var(--ios-blue)' : 'var(--ios-border, rgba(255,255,255,0.15))' ?>; color: <?= $filter === $tabKey ? 'var(--ios-blue)' : 'var(--ios-text-secondary)' ?>;">
                    <?= htmlspecialchars($tabLabel) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php $statusInfo = static function (string $status) use ($postStatus): array {
        return $postStatus[$status] ?? ['Bài viết', 'var(--ios-text-secondary)'];
    } ?>

    <div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box; margin-bottom: 0; border-left: 4px solid var(--ios-success, #30d158);">
        <div class="table-responsive">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Bài Viết</th>
                        <th>Trạng Thái</th>
                        <th>Ngày Lên Lịch</th>
                        <th>Cập Nhật</th>
                        <th style="text-align: right;">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($articles)): ?>
                        <?php foreach ($articles as $article): ?>
                            <?php
                            $ps = $statusInfo((string) ($article['post_status'] ?? 'unscheduled'));
                            $hasPrompt = trim((string) ($article['image_prompt'] ?? '')) !== '';
                            $scheduleAt = trim((string) ($article['scheduled_at'] ?? ''));
                            ?>
                            <tr>
                                <td style="font-weight: 700;">#<?= (int) $article['id'] ?></td>
                                <td style="font-size: 0.82rem; max-width: 380px;">
                                    <a href="/admin/ai/outputs/detail?id=<?= (int) $article['id'] ?>" style="color: var(--ios-text); text-decoration: none; font-weight: 600; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars((string) $article['title']) ?>">
                                        <?= htmlspecialchars((string) $article['title']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span style="color: <?= $ps[1] ?>; font-weight: 600; font-size: 0.82rem;">
                                        ● <?= htmlspecialchars($ps[0]) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.78rem; color: var(--ios-text-secondary); white-space: nowrap;">
                                    <?php if ($scheduleAt !== ''): ?>
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($scheduleAt))) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars((string) ($article['updated_at'] ?? '')) ?></td>
                                <td style="text-align: right;">
                                    <div class="action-dropdown" style="display: inline-block;">
                                        <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                        <div class="action-menu" style="min-width: 210px; white-space: nowrap;">
                                            <a href="/admin/ai/outputs/detail?id=<?= (int) $article['id'] ?>" class="action-item">
                                                <span>👁️</span> Xem bài viết
                                            </a>
                                            <?php if ($hasPrompt): ?>
                                                <button type="button" class="action-item js-copy-prompt"
                                                        data-copy-value="<?= htmlspecialchars((string) $article['image_prompt'], ENT_QUOTES) ?>"
                                                        data-copy-label="🖼️ Copy prompt"
                                                        style="font-family: inherit; width: 100%; text-align: left;">
                                                    <span>🖼️</span> Copy prompt
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="action-item js-schedule-open"
                                                    data-output-id="<?= (int) $article['id'] ?>"
                                                    data-output-title="<?= htmlspecialchars((string) $article['title'], ENT_QUOTES) ?>"
                                                    data-output-image="<?= htmlspecialchars((string) ($article['scheduled_image_url'] ?? ''), ENT_QUOTES) ?>"
                                                    style="font-family: inherit; width: 100%; text-align: left;">
                                                <span>🗓️</span> Lên lịch
                                            </button>
                                            <form method="POST" action="/admin/ai/outputs/delete" style="margin: 0;"
                                                  onsubmit="return confirm('Xóa bài viết này? Toàn bộ phiên bản và lịch đăng liên quan sẽ bị xóa.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <input type="hidden" name="output_id" value="<?= (int) $article['id'] ?>">
                                                <button type="submit" class="action-item" style="font-family: inherit; width: 100%; text-align: left; color: var(--ios-danger);">
                                                    <span>🗑️</span> Xóa
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">
                            <?php if ($filter === 'scheduled'): ?>
                                Không có bài viết nào đang lên lịch.
                            <?php elseif ($filter === 'published'): ?>
                                Chưa có bài viết nào được đăng lên fanpage.
                            <?php else: ?>
                                Chưa có bài viết nào. Bài viết được tạo khi tiến trình AI xử lý tác vụ thành công.
                            <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /fp-articles -->

<!-- ===== JS: HÀNG ĐỢI VIẾT TUẦN TỰ ===== -->
<script>
(function () {
    'use strict';

    var CSRF  = <?= json_encode($csrf_token ?? '', JSON_UNESCAPED_UNICODE) ?>;
    var QUEUE = <?= $fpQueueJson ?: 'null' ?>;
    var items = (QUEUE && Array.isArray(QUEUE.items)) ? QUEUE.items : [];
    var running = false;
    var doneCount = 0;

    var rowsEl     = document.getElementById('fp-rows');
    var emptyEl    = document.getElementById('fp-empty');
    var summaryEl  = document.getElementById('fp-summary');
    var errorEl    = document.getElementById('fp-error');
    var startBtn   = document.getElementById('fp-start');

    /* ---------- Render hàng tiến trình ----------
     * Bài "waiting" đầu tiên hiển thị ĐANG VIẾT (khi đang chạy),
     * các bài còn lại ĐANG CHỜ, bài done/failed biến mất khỏi hàng. */
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderRows() {
        var pending = items.filter(function (it) { return it.status === 'waiting' || it.status === 'writing'; });
        var failed  = items.filter(function (it) { return it.status === 'failed'; });
        doneCount   = items.filter(function (it) { return it.status === 'done'; }).length;
        var html = '';
        var position = 0;

        items.forEach(function (it, idx) {
            if (it.status === 'done') { return; } // xong → biến mất khỏi thanh tiến trình
            position++;

            var isWriting = (it.status === 'writing') ||
                (running && it.status === 'waiting' && position === 1 && items.filter(function (x) { return x.status === 'waiting'; })[0] === it);

            var chip, rowBg;
            if (it.status === 'failed') {
                chip = '<span style="color: var(--ios-danger); font-weight: 700;">❌ Lỗi</span>';
                rowBg = 'rgba(255, 69, 58, 0.08);';
            } else if (isWriting) {
                chip = '<span style="color: var(--ios-blue); font-weight: 700;">🖊️ <span class="fp-blink">Đang viết…</span></span>';
                rowBg = 'rgba(10, 132, 255, 0.10);';
            } else {
                chip = '<span style="color: var(--ios-warning, #ff9f0a); font-weight: 600;">⏳ Đang chờ</span>';
                rowBg = 'transparent;';
            }

            var err = (it.status === 'failed' && it.error)
                ? '<div style="font-size: 0.76rem; color: var(--ios-danger); margin-top: 0.2rem;">' + esc(it.error) + '</div>' : '';

            html += '<div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.6rem 0.85rem; border-radius: 10px; background:' + rowBg + ';">' +
                '<div style="font-weight: 700; font-size: 0.8rem; color: var(--ios-text-secondary); min-width: 2.2rem;">#' + (idx + 1) + '</div>' +
                '<div style="flex: 1; font-size: 0.86rem; min-width: 0;">' + esc(it.topic) + err + '</div>' +
                '<div style="font-size: 0.8rem; white-space: nowrap;">' + chip + '</div>' +
                '</div>';
        });

        rowsEl.innerHTML = html;
        emptyEl.style.display = items.length ? 'none' : '';
        summaryEl.style.display = (items.length && (doneCount > 0 || failed.length)) ? '' : 'none';
        if (summaryEl.style.display === '') {
            summaryEl.innerHTML = '✅ <strong>' + doneCount + '</strong> bài đã hoàn tất' +
                (failed.length ? ' · ❌ <strong style="color: var(--ios-danger);">' + failed.length + '</strong> bài lỗi' : '') +
                ' · ⏳ <strong>' + pending.filter(function (x) { return x.status === 'waiting'; }).length + '</strong> bài chờ';
        }
    }

    /* ---------- Gọi write-next: đúng 1 bài / 1 lần ---------- */
    function postWriteNext() {
        var body = new URLSearchParams();
        body.append('csrf_token', CSRF);
        return fetch('/admin/ai/articles/write-next', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    /* Vòng tuần tự: await từng bài → article xong biến mất → viết bài kế. */
    function pump() {
        if (running) { return; }

        var targetIndex = -1;
        for (var i = 0; i < items.length; i++) {
            if (items[i].status === 'waiting') { targetIndex = i; break; }
        }

        if (targetIndex < 0) {
            // Hết hàng đợi: không có banner riêng — hàng trống + summary là đủ (đã xoá 🎉).
            return;
        }

        running = true;
        items[targetIndex].status = 'writing';       // hiện "Đang viết" ngay trên hàng
        errorEl.style.display = 'none';
        renderRows();

        function markWaiting() {
            for (var j = 0; j < items.length; j++) {
                if (items[j].status === 'writing') { items[j].status = 'waiting'; return; }
            }
        }

        postWriteNext().then(function (res) {
            running = false;

            if (!res || !res.ok) {
                markWaiting();
                errorEl.textContent = '⚠️ ' + ((res && res.message) ? res.message : 'Không gọi được write-next.');
                errorEl.style.display = '';
                renderRows();
                return;
            }

            if (res.queue && Array.isArray(res.queue.items)) { items = res.queue.items; QUEUE = res.queue; }

            if (res.busy) {
                // Có lời gọi khác đang giữ "đang viết" — chờ rồi poll tiếp.
                renderRows();
                setTimeout(pump, 3000);
                return;
            }

            // writeNext đã set done/failed — render lại (hàng xong biến mất).
            renderRows();

            if (res.finished) {
                // Đã xoá banner 🎉 — khi xong chỉ cần hàng trống + summary.
                startBtn.style.display = 'none';
                // Viết xong hàng đợi → cuộn mượt xuống Danh Sách Bài Viết xem kết quả.
                var listEl = document.getElementById('fp-articles');
                if (listEl) listEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            if (items.some(function (it) { return it.status === 'waiting'; })) {
                setTimeout(pump, 250);   // viết tiếp bài kế tiếp — tuần tự
            }
        }).catch(function () {
            running = false;
            markWaiting();
            errorEl.textContent = '⚠️ Mất kết nối tới máy chủ — bấm "▶️ Bắt Đầu Viết" để tiếp tục.';
            errorEl.style.display = '';
            startBtn.style.display = '';
            renderRows();
        });
    }

    startBtn.addEventListener('click', function () {
        startBtn.style.display = 'none';
        // Bấm viết bài → cuộn mượt tới cab Tiến Trình để xem AI đang viết.
        var progEl = document.getElementById('fp-pane-progress');
        if (progEl) progEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        pump();
    });

    /* ---------- Boot ---------- */
    items.forEach(function (it) { if (it.status === 'done') { doneCount++; } });

    renderRows();

    // Tự chạy khi hàng đợi còn bài chờ (sau khi submit form → quay lại trang này).
    if (items.some(function (it) { return it.status === 'waiting'; }) && !running) {
        setTimeout(pump, 400);
    }
})();
</script>
<style>
.fp-blink { animation: fpBlink 1.1s ease-in-out infinite; }
@keyframes fpBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
</style>

<!-- ===== POPUP LÊN LỊCH ĐĂNG BÀI (chuyển từ outputs.php) ===== -->
<div id="schedule-popup" style="display: none; position: fixed; inset: 0; z-index: 950; background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(6px); align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-card" style="width: 100%; max-width: 380px; padding: 1.5rem;">
        <h3 style="margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700;">Lên lịch đăng bài</h3>
        <p id="schedule-popup-title" style="margin: 0 0 1rem; font-size: 0.82rem; color: var(--ios-text-secondary); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"></p>
        <form method="POST" action="/admin/ai/outputs/schedule" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="output_id" id="schedule-popup-output" value="">
            <div style="margin-bottom: 0.85rem;">
                <label for="schedule-popup-date" style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem;">Ngày đăng</label>
                <input type="date" id="schedule-popup-date" name="schedule_date" required
                       style="width: 100%; padding: 0.55rem 0.7rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.18)); background: var(--ios-bg-secondary, rgba(255,255,255,0.06)); color: var(--ios-text); font-family: inherit; font-size: 0.85rem;">
            </div>
            <div style="margin-bottom: 1.1rem;">
                <label for="schedule-popup-time" style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem;">Giờ đăng</label>
                <input type="time" id="schedule-popup-time" name="schedule_time" required
                       style="width: 100%; padding: 0.55rem 0.7rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.18)); background: var(--ios-bg-secondary, rgba(255,255,255,0.06)); color: var(--ios-text); font-family: inherit; font-size: 0.85rem;">
            </div>
            <div style="margin-bottom: 1.1rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem;">Ảnh đăng cùng <span style="font-weight: 500; color: var(--ios-text-secondary);">— từ thư mục tab Tạo Ảnh</span></label>
                <input type="hidden" name="image_url" id="schedule-popup-image" value="">
                <?php $pickerItems = array_values(array_filter((array) ($imageGallery ?? []), static fn($it): bool => trim((string) ($it['_url'] ?? '')) !== '')); ?>
                <?php if (empty($pickerItems)): ?>
                    <p style="margin: 0; font-size: 0.78rem; color: var(--ios-text-secondary);">
                        Chưa có ảnh nào trong thư mục tab Tạo Ảnh — article sẽ tự sinh ảnh theo prompt.
                    </p>
                <?php else: ?>
                    <div id="schedule-popup-images" style="display: flex; gap: 0.4rem; flex-wrap: wrap; max-height: 132px; overflow-y: auto; padding: 2px;">
                        <button type="button" class="sched-img-opt is-active" data-url="" title="Không đính kèm ảnh"
                                style="width: 56px; height: 56px; font-size: 0.62rem; line-height: 1.15; color: var(--ios-text-secondary); background: var(--ios-bg-secondary, rgba(255,255,255,0.06)); border: 2px solid var(--ios-blue); border-radius: var(--radius-sm); cursor: pointer; padding: 2px;">
                            Không<br>ảnh
                        </button>
                        <?php foreach ($pickerItems as $img): ?>
                            <?php
                            $imgUrl  = (string) $img['_url'];
                            $imgName = trim((string) ($img['original_name'] ?? ''));
                            if ($imgName === '') {
                                $imgName = basename((string) ($img['relative_path'] ?? $imgUrl));
                            }
                            ?>
                            <button type="button" class="sched-img-opt" data-url="<?= htmlspecialchars($imgUrl, ENT_QUOTES) ?>" title="<?= htmlspecialchars($imgName, ENT_QUOTES) ?>"
                                    style="width: 56px; height: 56px; padding: 0; overflow: hidden; border: 2px solid transparent; border-radius: var(--radius-sm); cursor: pointer; background: var(--ios-bg-secondary, rgba(255,255,255,0.06));">
                                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($imgName) ?>" loading="lazy"
                                     style="width: 100%; height: 100%; object-fit: cover; display: block;">
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin: 0.35rem 0 0; font-size: 0.72rem; color: var(--ios-text-secondary);">Nhấn một ảnh để đính kèm (hoặc "Không ảnh"). Cron sẽ đăng kèm ảnh này thay vì tự sinh ảnh.</p>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 0.6rem; justify-content: flex-end;">
                <button type="button" class="glass-btn" id="schedule-popup-cancel" style="font-size: 0.82rem; cursor: pointer;">Hủy</button>
                <button type="submit" class="glass-btn glass-btn-primary" style="font-size: 0.82rem; cursor: pointer;">Lưu bài viết</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const popup = document.getElementById('schedule-popup');
        const outputField = document.getElementById('schedule-popup-output');
        const titleField = document.getElementById('schedule-popup-title');
        const dateField = document.getElementById('schedule-popup-date');
        const timeField = document.getElementById('schedule-popup-time');
        const imageField = document.getElementById('schedule-popup-image');

        // Đóng TẤT CẢ menu 3 chấm — popup mở thì menu thao tác phải tự ẩn.
        function closeActionMenus() {
            document.querySelectorAll('.action-menu.show').forEach(function (m) { m.classList.remove('show'); });
            document.querySelectorAll('.action-btn.active').forEach(function (b) { b.classList.remove('active'); });
        }

        // Chọn/bỏ chọn ảnh trong popup (classList + viền xanh).
        // Nếu URL không còn trong lưới ảnh → tự bỏ chọn (giữ "Không ảnh").
        function selectImage(value) {
            value = value || '';
            const opts = document.querySelectorAll('.sched-img-opt');
            let found = value === '';
            opts.forEach(function (opt) {
                if ((opt.dataset.url || '') === value) found = true;
            });
            if (!found && opts.length > 0) value = '';
            if (imageField) imageField.value = value;
            opts.forEach(function (opt) {
                const on = (opt.dataset.url || '') === value;
                opt.classList.toggle('is-active', on);
                opt.style.borderColor = on ? 'var(--ios-blue)' : 'transparent';
            });
        }

        document.querySelectorAll('.sched-img-opt').forEach(function (opt) {
            opt.addEventListener('click', function () { selectImage(opt.dataset.url || ''); });
        });

        function openPopup(id, title, imageUrl) {
            closeActionMenus();

            outputField.value = id;
            titleField.textContent = title;
            selectImage(imageUrl || '');

            const now = new Date();
            now.setMinutes(now.getMinutes() + 10);
            const pad = function (n) { return String(n).padStart(2, '0'); };
            dateField.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
            dateField.min = new Date().getFullYear() + '-' + pad(new Date().getMonth() + 1) + '-' + pad(new Date().getDate());
            timeField.value = pad(now.getHours()) + ':' + pad(now.getMinutes());

            popup.style.display = 'flex';
        }

        function closePopup() {
            popup.style.display = 'none';
        }

        document.querySelectorAll('.js-schedule-open').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                openPopup(btn.dataset.outputId || '', btn.dataset.outputTitle || '', btn.dataset.outputImage || '');
            });
        });

        document.getElementById('schedule-popup-cancel').addEventListener('click', closePopup);
        popup.addEventListener('click', function (e) {
            if (e.target === popup) closePopup();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && popup.style.display === 'flex') closePopup();
        });
    })();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
