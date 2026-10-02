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
                                            <?php if ((int) ($article['task_id'] ?? 0) > 0): ?>
                                                <a href="/admin/ai/tasks/detail?id=<?= (int) $article['task_id'] ?>" class="action-item">
                                                    <span>📋</span> Tác vụ #<?= (int) $article['task_id'] ?>
                                                </a>
                                            <?php endif; ?>
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
     * các bài còn lại ĐANG CHỜ, bài done biến mất khỏi hàng.
     * Bài lỗi giữ lại + ✕ huỷ; bài đã huỷ hiện ❌ Đã huỷ + ✕ dọn khỏi hàng. */
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderRows() {
        // Batch hết việc (không còn bài chờ/đang viết) → dòng "❌ Đã huỷ" TỰ
        // dọn khỏi hàng (giữ trong summary để admin vẫn thấy số đã huỷ).
        var batchIdle = !items.some(function (it) { return it.status === 'waiting' || it.status === 'writing'; });
        if (!running && batchIdle) {
            items.forEach(function (it) { if (it.status === 'cancelled') { it.dismissed = true; } });
        }

        var pending = items.filter(function (it) { return it.status === 'waiting' || it.status === 'writing'; });
        var failed  = items.filter(function (it) { return it.status === 'failed'; });
        var cancelled = items.filter(function (it) { return it.status === 'cancelled'; });
        doneCount   = items.filter(function (it) { return it.status === 'done'; }).length;
        var html = '';
        var position = 0;

        items.forEach(function (it, idx) {
            if (it.status === 'done' || it.dismissed) { return; } // xong/dọn → biến mất khỏi thanh tiến trình
            position++;

            var isWriting = (it.status === 'writing') ||
                (running && it.status === 'waiting' && position === 1 && items.filter(function (x) { return x.status === 'waiting'; })[0] === it);

            var chip, rowBg;
            if (it.status === 'cancelled') {
                chip = '<span style="color: var(--ios-text-secondary); font-weight: 700;">❌ Đã huỷ</span>';
                rowBg = 'rgba(142, 142, 147, 0.10);';
            } else if (it.status === 'failed') {
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

            // ✕ huỷ / dọn: writing + waiting + failed → gọi server; cancelled → chỉ dọn khỏi hàng.
            var act = '';
            if (it.status === 'writing' || it.status === 'waiting' || it.status === 'failed') {
                act = '<button type="button" class="fp-cancel" data-idx="' + idx + '" title="Huỷ bài này"' +
                    ' style="border: 1px solid rgba(255,59,48,0.35); background: rgba(255,59,48,0.08); color: #ff3b30; border-radius: 8px; padding: 0.1rem 0.5rem; cursor: pointer; font-weight: 700; font-size: 0.8rem; line-height: 1.5;">✕</button>';
            } else if (it.status === 'cancelled') {
                act = '<button type="button" class="fp-cancel" data-idx="' + idx + '" title="Dọn khỏi hàng"' +
                    ' style="border: 1px solid var(--ios-border, rgba(255,255,255,0.18)); background: transparent; color: var(--ios-text-secondary); border-radius: 8px; padding: 0.1rem 0.5rem; cursor: pointer; font-size: 0.8rem; line-height: 1.5;">✕</button>';
            }

            html += '<div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.6rem 0.85rem; border-radius: 10px; background:' + rowBg + ';">' +
                '<div style="font-weight: 700; font-size: 0.8rem; color: var(--ios-text-secondary); min-width: 2.2rem;">#' + (idx + 1) + '</div>' +
                '<div style="flex: 1; font-size: 0.86rem; min-width: 0;">' + esc(it.topic) + err + '</div>' +
                '<div style="font-size: 0.8rem; white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">' + chip + act + '</div>' +
                '</div>';
        });

        rowsEl.innerHTML = html;
        emptyEl.style.display = items.length ? 'none' : '';
        summaryEl.style.display = (items.length && (doneCount > 0 || failed.length || cancelled.length)) ? '' : 'none';
        if (summaryEl.style.display === '') {
            summaryEl.innerHTML = '✅ <strong>' + doneCount + '</strong> bài đã hoàn tất' +
                (failed.length ? ' · ❌ <strong style="color: var(--ios-danger);">' + failed.length + '</strong> bài lỗi' : '') +
                (cancelled.length ? ' · ❌ <strong style="color: var(--ios-text-secondary);">' + cancelled.length + '</strong> bài đã huỷ' : '') +
                ' · ⏳ <strong>' + pending.filter(function (x) { return x.status === 'waiting'; }).length + '</strong> bài chờ';
        }
    }

    /* ---------- ✕ Huỷ 1 bài trong hàng đợi (POST queue-cancel) ---------- */
    function cancelItem(idx) {
        var it = items[idx];
        if (!it || it.dismissed) { return Promise.resolve(); }
        if (it.status === 'cancelled') {
            // Đã huỷ rồi → ✕ = DỌN khỏi hàng (chỉ bỏ qua khi render; GIỮ nguyên
            // index trong mảng để writeNext/pump không bị lệch số thứ tự).
            it.dismissed = true;
            renderRows();
            return Promise.resolve();
        }
        var label = String(it.topic || '').slice(0, 60);
        if (!confirm('Huỷ bài "' + label + '"?')) {
            return Promise.resolve();
        }
        var body = new URLSearchParams();
        body.append('csrf_token', CSRF);
        body.append('queue_id', String(QUEUE && QUEUE.id ? QUEUE.id : ''));
        body.append('index', String(idx));
        return fetch('/admin/ai/articles/queue-cancel', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res && res.ok) {
                items[idx].status = 'cancelled';
                items[idx].error = '';
            } else {
                errorEl.textContent = '⚠️ ' + ((res && res.message) ? res.message : 'Không huỷ được bài này.');
                errorEl.style.display = '';
            }
            renderRows();
        }).catch(function () {
            errorEl.textContent = '⚠️ Mất kết nối — không huỷ được bài.';
            errorEl.style.display = '';
        });
    }

    document.getElementById('fp-rows').addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.fp-cancel') : null;
        if (!btn) { return; }
        var idx = parseInt(btn.getAttribute('data-idx'), 10);
        if (items[idx] && items[idx].status === 'writing' && running) {
            cancelCurrent(idx);   // đang viết → dừng pump + đóng task server
        } else {
            cancelItem(idx);      // chờ/lỗi/đã huỷ → bỏ khỏi hàng đợi
        }
    });

    /* Huỷ bài ĐANG VIẾT (khi pump đang chờ write-next trả về): dừng vòng lặp
     * + đóng task server → response write-next về sẽ thấy status 'cancelled'. */
    function cancelCurrent(writingIdx) {
        running = false;
        cancelItem(writingIdx).then(function () {
            renderRows();
            // KHÔNG hiện "▶️ Bắt Đầu Viết" ở đây: write-next vẫn còn trong luồng —
            // khi nó trả về, pump tự viết bài chờ kế tiếp (hoặc finished ẩn nút).
            // Nút chỉ hiện khi pump thật sự đứng (lỗi/mất kết nối) ở dưới.
        });
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

    /* ---------- Nạp lại cab Danh Sách Bài Viết (AJAX — không cần F5) ----------
     * Lấy HTML trang hiện tại → CHỈ thay nội dung #fp-articles → bind lại menu
     * 3 chấm / copy prompt / nút Lên lịch cho row mới (binder đã global hóa). */
    function refreshArticles() {
        var host = document.getElementById('fp-articles');
        if (!host) { return; }

        // Gỡ menu 3 chấm đã bị kéo ra <body> (thuộc row cũ sắp bị thay).
        document.querySelectorAll('body > .action-menu').forEach(function (m) {
            if (!m._triggerBtn || host.contains(m._triggerBtn)) { m.remove(); }
        });

        fetch(window.location.pathname + window.location.search, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.ok ? r.text() : null; }).then(function (html) {
            if (!html) { return; }
            var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('fp-articles');
            if (!fresh) { return; }
            host.innerHTML = fresh.innerHTML;
            if (typeof window.fpBindScheduleOpen === 'function') { window.fpBindScheduleOpen(host); }
            if (typeof window.vcBindActionMenus === 'function') { window.vcBindActionMenus(host); }
            if (typeof window.vcBindCopyButtons === 'function') { window.vcBindCopyButtons(host); }
        }).catch(function () { /* mạng lỗi → giữ list cũ, không ảnh hưởng tiến trình */ });
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
        startBtn.style.display = 'none';             // đang chạy → ẩn nút Bắt Đầu
        items[targetIndex].status = 'writing';       // hiện "Đang viết" ngay trên hàng
        errorEl.style.display = 'none';
        renderRows();
        // Ghi nhớ index đang viết → ✕ trên hàng "Đang viết" huỷ được cả bài này.
        var writingIdx = targetIndex;

        function markWaiting() {
            for (var j = 0; j < items.length; j++) {
                if (items[j].status === 'writing') { items[j].status = 'waiting'; return; }
            }
        }

        postWriteNext().then(function (res) {
            running = false;
            var prevDone = items.filter(function (it) { return it.status === 'done'; }).length;

            if (!res || !res.ok) {
                markWaiting();
                errorEl.textContent = '⚠️ ' + ((res && res.message) ? res.message : 'Không gọi được write-next.');
                errorEl.style.display = '';
                startBtn.style.display = '';   // pump đứng → cho phép bấm Bắt Đầu lại
                renderRows();
                return;
            }

            // Giữ cờ dismissed (dòng đã dọn) — queue không splice nên index ổn định.
            if (res.queue && Array.isArray(res.queue.items)) {
                var oldItems = items;
                items = res.queue.items;
                items.forEach(function (it, i) {
                    if (oldItems[i] && oldItems[i].dismissed) it.dismissed = true;
                });
                QUEUE = res.queue;
            }

            if (res.busy) {
                // Có lời gọi khác đang giữ "đang viết" — chờ rồi poll tiếp.
                renderRows();
                setTimeout(pump, 3000);
                return;
            }

            // writeNext đã set done/failed — render lại (hàng xong biến mất).
            renderRows();

            // Bài vừa hoàn tất → nạp lại cab Danh Sách Bài Viết ngay (không cần F5).
            if (doneCount > prevDone) {
                refreshArticles();
            }

            if (res.finished) {
                // Đã xoá banner 🎉 — khi xong chỉ cần hàng trống + summary.
                startBtn.style.display = 'none';
                refreshArticles();   // nạp chắc chắn 1 lần cuối (kể cả batch huỷ/ lỗi)
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
<!-- z-index 1020 > .admin-sidebar (1010): overlay phải phủ trọn màn hình, kể cả khu vực sidebar -->
<div id="schedule-popup" style="display: none; position: fixed; inset: 0; z-index: 1020; background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(6px); align-items: center; justify-content: center; padding: 1rem;">
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
                        Chưa có ảnh nào trong thư mục tab Tạo Ảnh — bài sẽ được đăng chỉ có chữ (không ảnh).
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
                    <p style="margin: 0.35rem 0 0; font-size: 0.72rem; color: var(--ios-text-secondary);">Nhấn một ảnh để đính kèm (hoặc "Không ảnh"). Cron đăng đúng nội dung + ảnh đã chọn, không tự sinh ảnh.</p>
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

        // Bọc thành hàm toàn cục: refreshArticles() nạp lại HTML cab Danh Sách
        // Bài Viết sẽ gọi fpBindScheduleOpen(root) để bind nút Lên lịch mới.
        window.fpBindScheduleOpen = function (root) {
            (root || document).querySelectorAll('.js-schedule-open').forEach(function (btn) {
                if (btn.dataset.schedBound === '1') { return; }
                btn.dataset.schedBound = '1';
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openPopup(btn.dataset.outputId || '', btn.dataset.outputTitle || '', btn.dataset.outputImage || '');
                });
            });
        };
        window.fpBindScheduleOpen(document);

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
