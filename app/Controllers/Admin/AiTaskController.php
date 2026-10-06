<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\AI\Contracts\AIException;
use App\AI\Core\TaskRunner;
use App\Models\AIModel;
use App\Models\AIModule;
use App\Models\AIOutput;
use App\Models\AITask;
use App\Models\AITaskActivity;

/**
 * AiTaskController — điều phối tác vụ AI (/admin/ai/tasks/*).
 *
 * Đây là "producer" chính thức đầu tiên của AI Core:
 *   Admin UI (form) → TaskRunner::request() → vc_ai_tasks
 *                                ↓
 *                      worker (tools/ai_worker.php hoặc nút "Chạy task")
 *                                ↓
 *                      TaskRunner::process() → AICore → KiraProvider
 *
 * Trang danh sách GET /admin/ai/tasks + POST /admin/ai/tasks/store đã GỠ
 * (không còn đường vào UI): giờ dùng dashboard /admin/ai + chi tiết
 * /admin/ai/tasks/detail. Các route còn lại (detail/run/cancel/drain +
 * storeTopics/writeNext của tab Fanpage) giữ nguyên.
 *
 * KHÔNG dùng SELFTEST_MODULE, KHÔNG fixture: chỉ tạo task cho 6 module thật
 * đã đăng ký trong ModuleRegistry + vc_ai_modules.
 */
final class AiTaskController extends AiBaseController
{
    /**
     * PRODUCER: tab Nội Dung Fanpage (/admin/ai/fanpage) — admin nhập DANH SÁCH
     * CHỦ ĐỀ + số lượng bài mỗi chủ đề → XẾP HÀNG VIẾT TUẦN TỰ vào session
     * (KHÔNG tạo hàng loạt task — AI KHÔNG được viết cùng lúc).
     *
     * Tab "Tiến Trình" của Fanpage sẽ gọi writeNext() lần lượt: mỗi lần đúng
     * MỘT bài → tạo task → chạy đồng bộ → xong bài thì hiện ở Danh Sách Bài
     * Viết (/admin/ai/outputs) rồi mới viết bài kế tiếp.
     *
     * Giữ F19: mọi tạo task vẫn ở controller này (storeTopics + writeNext).
     */
    public function storeTopics(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/fanpage');
        }

        $rawTopics = trim((string) ($_POST['topics'] ?? ''));

        // Tách chủ đề theo dòng mới HOẶC dấu phẩy (admin nhập "1,2,3 chủ đề").
        $topics = preg_split('/[\r\n,]+/u', $rawTopics) ?: [];
        $topics = array_values(array_filter(array_map('trim', $topics), static fn(string $t): bool => $t !== ''));

        if ($topics === []) {
            $this->flash('Vui lòng nhập ít nhất một chủ đề bài viết.', 'danger', '/admin/ai/fanpage');
            return;
        }

        if (count($topics) > 50) {
            $this->flash('Tối đa 50 chủ đề mỗi lần. Hiện nhập: ' . count($topics), 'danger', '/admin/ai/fanpage');
            return;
        }

        $perTopic = (int) ($_POST['per_topic'] ?? 1);
        if ($perTopic < 1 || $perTopic > 10) {
            $perTopic = 1;
        }

        $moduleKey = 'content_article';

        // CHỈ tạo khi module thật đã đăng ký + bật (không fixture).
        $module = $this->moduleRegistry()->get($moduleKey);
        if ($module === null) {
            $this->flash('Module content_article chưa được đăng ký trong hệ thống.', 'danger', '/admin/ai/fanpage');
            return;
        }

        // CHỌN MODEL BẮT BUỘC — chưa chọn / chọn sai → báo ngay, không xếp hàng.
        $modelKey = trim((string) ($_POST['model'] ?? ''));
        if ($modelKey === '') {
            $this->flash('Vui lòng chọn Model AI trước khi giao việc viết bài.', 'danger', '/admin/ai/fanpage');
            return;
        }

        $modelRow = (new AIModel())->findByKey($modelKey);
        if ($modelRow === null) {
            $this->flash('Model "' . $modelKey . '" không có trong danh mục model của hệ thống.', 'danger', '/admin/ai/fanpage');
            return;
        }

        $allowedCaps = $this->modelCapabilitiesForModule((string) ($this->moduleRegistry()->capabilityOf($moduleKey) ?? 'text'));
        if ($allowedCaps !== [] && !in_array((string) ($modelRow['capability'] ?? ''), $allowedCaps, true)) {
            $this->flash(
                'Model "' . $modelKey . '" (capability: ' . (string) ($modelRow['capability'] ?? '?')
                . ') không hợp lệ cho module viết bài (cần: ' . implode(' / ', $allowedCaps) . ').',
                'danger',
                '/admin/ai/fanpage'
            );
            return;
        }

        // XẾP HÀNG (session) — writeNext() sẽ viết TỪNG bài một theo thứ tự.
        $batchId = bin2hex(random_bytes(8));
        $items   = [];

        foreach ($topics as $topic) {
            for ($i = 1; $i <= $perTopic; $i++) {
                $items[] = [
                    'topic'          => $topic,
                    'status'         => 'waiting', // waiting → writing → done|failed
                    'task_id'        => 0,
                    'error'          => '',
                    'started_at'     => 0,
                    // Idempotency RIÊNG cho từng bài: bài trùng chủ đề vẫn viết
                    // riêng (khác batch), không bị TaskRunner "tái sử dụng" chung.
                    'idempotency_key' => sha1($batchId . '|' . count($items)),
                ];
            }
        }

        $_SESSION['article_queue'] = [
            'id'         => $batchId,
            // Model admin chọn cho batch này — writeNext truyền vào task options.
            'model'      => $modelKey,
            'items'      => $items,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $this->logActivity(
            'ai_task_create_bulk',
            sprintf(
                'Viết bài: %d chủ đề × %d bài (model %s) → xếp %d bài vào hàng đợi VIẾT TUẦN TỰ (batch %s).',
                count($topics),
                $perTopic,
                $modelKey,
                count($items),
                $batchId
            )
        );

        $this->flash(
            sprintf('Đã xếp %d bài — AI sẽ viết tuần tự từng bài. Mở tab "Tiến Trình" để theo dõi.', count($items)),
            'success',
            // Gắn anchor: quay lại trang là tự cuộn tới cab Tiến Trình (hàng đợi tự chạy).
            '/admin/ai/fanpage?tab=progress&auto=1#fp-pane-progress'
        );
    }

    /**
     * Viết TIẾP đúng MỘT bài trong hàng đợi (session `article_queue`).
     *
     * Endpoint AJAX của tab "Tiến Trình" (POST /admin/ai/articles/write-next):
     *   - Chọn bài `waiting` đầu tiên → gắn `writing` → TẠO task
     *     `content_article` → chạy ĐỒNG BỘ qua TaskRunner::process.
     *   - Trả JSON queue updated: bài xong → `done` (biến mất khỏi progress,
     *     output hiện ở /admin/ai/outputs), lỗi → `failed` (giữ hàng + lý do).
     *   - JS gọi lại liên tiếp tới khi không còn bài `waiting` ⇒ CHỈ MỘT BÀI
     *     được viết tại một thời điểm (không bao giờ viết song song).
     *
     * Nhả khoá session trước khi chạy AI dài để trang khác không bị treo.
     */
    public function writeNext(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['ok' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
        }

        // AI đồng bộ có thể mất 1–3 phút/bài — không giới hạn thời gian chạy.
        @set_time_limit(0);

        $queue = $_SESSION['article_queue'] ?? null;
        if (!is_array($queue) || !isset($queue['items']) || !is_array($queue['items']) || $queue['items'] === []) {
            $this->json(['ok' => false, 'message' => 'Không có hàng đợi viết bài nào.']);
        }

        $queueId = (string) ($queue['id'] ?? '');
        $now     = time();
        $busy    = false;

        // Bài đang `writing` còn hạn → đang được lời gọi khác chạy (đợi);
        // quá hạn (request cũ bị crash) → reset về `waiting` để viết lại.
        foreach ($queue['items'] as $i => $item) {
            if ((string) ($item['status'] ?? '') !== 'writing') {
                continue;
            }

            if ($now - (int) ($item['started_at'] ?? 0) < 900) {
                $busy = true;
            } else {
                $queue['items'][$i]['status'] = 'waiting';
                $queue['items'][$i]['error']  = '';
            }
        }

        if ($busy) {
            $this->json(['ok' => true, 'busy' => true, 'finished' => false, 'queue' => $queue]);
        }

        // Lấy bài `waiting` ĐẦU TIÊN (viết tuần tự theo thứ tự admin nhập).
        $idx = null;
        foreach ($queue['items'] as $i => $item) {
            if ((string) ($item['status'] ?? '') === 'waiting') {
                $idx = (int) $i;
                break;
            }
        }

        if ($idx === null) {
            $this->json(['ok' => true, 'busy' => false, 'finished' => true, 'queue' => $queue]);
        }

        // Gắn `writing` + nhả khoá session trước khi chạy AI dài.
        $queue['items'][$idx]['status']     = 'writing';
        $queue['items'][$idx]['started_at'] = $now;
        $queue['updated_at']                = $now;
        $_SESSION['article_queue']          = $queue;

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        session_write_close();

        $topic      = (string) ($queue['items'][$idx]['topic'] ?? '');
        $taskId     = 0;
        $runStatus  = 'failed';
        $message    = '';

        // Model admin chọn ở form giao việc (lưu trong queue) → truyền vào task
        // để ModelResolver dùng ĐÚNG model đã chọn (không còn model mặc định ngầm).
        $queueModel = trim((string) ($queue['model'] ?? ''));
        $taskOptions = ['priority' => 5];
        if ($queueModel !== '') {
            $taskOptions['model'] = $queueModel;
        }

        try {
            $outcome = $this->runner()->request(
                'content_article',
                ['topic' => $topic],
                $taskOptions,
                (string) ($queue['items'][$idx]['idempotency_key'] ?? '') ?: null,
                $userId
            );

            $taskId = (int) ($outcome['id'] ?? 0);

            // Ghi task_id + đọc cờ huỷ NGAY (mở session ngắn) để cab "Tiến Trình"
            // huỷ được bài đang viết — nếu admin vừa bấm ✕ → đóng task luôn,
            // không chạy AI.
            $cancelledMid = false;
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $mid = $_SESSION['article_queue'] ?? null;
            if (is_array($mid) && (string) ($mid['id'] ?? '') === $queueId && isset($mid['items'][$idx])) {
                $mid['items'][$idx]['task_id'] = $taskId;
                $cancelledMid = (string) ($mid['items'][$idx]['status'] ?? '') === 'cancelled';
                $_SESSION['article_queue'] = $mid;
            }
            session_write_close();

            if ($cancelledMid && $taskId > 0) {
                // Đóng task ngay (chưa claim → claim() cũng sẽ từ chối 'cancelled').
                $this->runner()->dispatcher()->finish($taskId, 'cancelled', [
                    'message' => 'Admin huỷ từ cab Tiến Trình Viết Bài.',
                ]);
                $runStatus = 'cancelled';
                $message    = 'Đã huỷ theo yêu cầu.';
            } else {
                $run    = $this->runner()->process($taskId, $this->adminWorkerId('article-queue'));
                $runStatus = (string) ($run['status'] ?? 'failed');
                $message   = (string) ($run['message'] ?? '');
            }
        } catch (AIException $e) {
            $message = $e->getMessage();
            $this->logActivity('ai_task_create_failed', 'writeNext: ' . $message);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->logActivity('ai_task_create_failed', 'writeNext: ' . $message);
        }

        // Mở lại session để ghi kết quả (không ghi đè hàng đợi mới nếu admin
        // đã submit lại batch khác trong lúc chờ AI).
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $fresh      = $_SESSION['article_queue'] ?? null;
        $okRun      = $runStatus === TaskRunner::RESULT_PROCESSED;
        $queueOut   = is_array($fresh) && (string) ($fresh['id'] ?? '') === $queueId ? $fresh : $queue;
        $curStatus  = null;

        if (is_array($fresh) && (string) ($fresh['id'] ?? '') === $queueId) {
            $curStatus = (string) ($fresh['items'][$idx]['status'] ?? '');
            $fresh['items'][$idx]['task_id'] = $taskId;

            // Admin huỷ giữa chừng (queue-cancel) → GIỮ 'cancelled', không ghi
            // đè bằng done/failed.
            if ($curStatus !== 'cancelled') {
                $fresh['items'][$idx]['status'] = $okRun ? 'done' : 'failed';

                if (!$okRun) {
                    $fresh['items'][$idx]['error'] = $message !== ''
                        ? $message
                        : ('Trạng thái task: ' . $runStatus);
                }
            }

            $fresh['updated_at']       = time();
            $_SESSION['article_queue'] = $fresh;
            $queueOut                  = $fresh;
        }

        // Bài bị huỷ giữa chừng (queue-cancel) → log "đã huỷ", KHÔNG ghi "lỗi"
        // dù runner trả lock-lost/do-runner từ chối ghi đè task đã đóng.
        $cancelledMidRun = $runStatus === 'cancelled' || $curStatus === 'cancelled';

        $this->logActivity(
            'ai_article_write',
            sprintf(
                'Viết bài %d "%s" → %s (task #%d%s).',
                $idx + 1,
                mb_substr($topic, 0, 60),
                $okRun ? 'hoàn tất' : ($cancelledMidRun ? 'đã huỷ' : 'lỗi'),
                $taskId,
                ($okRun || $cancelledMidRun) ? '' : ': ' . mb_substr($message, 0, 120)
            )
        );

        $finished = true;
        foreach ($queueOut['items'] as $item) {
            if (in_array((string) ($item['status'] ?? ''), ['waiting', 'writing'], true)) {
                $finished = false;
                break;
            }
        }

        $this->json(['ok' => true, 'busy' => false, 'finished' => $finished, 'queue' => $queueOut]);
    }

    public function detail(): void
    {
        $taskId = (int) ($_GET['id'] ?? 0);

        $taskModel = new AITask();
        $task = $taskModel->find($taskId);

        if (!is_array($task)) {
            $this->flash('Task không tồn tại.', 'danger', '/admin/ai');
            return;
        }

        $activities = [];
        try {
            $activities = (array) (new AITaskActivity())->byTask($taskId);
        } catch (\Throwable $e) {
            $activities = [];
        }

        $output = null;
        try {
            $output = (new AIOutput())->findByTask($taskId);
        } catch (\Throwable $e) {
            $output = null;
        }

        $module = null;
        try {
            $module = (new AIModule())->find((int) ($task['module_id'] ?? 0));
        } catch (\Throwable $e) {
            $module = null;
        }

        // Bài viết đầy đủ KÈM hàng đợi đăng (vc_scheduled_posts) — task chỉ
        // phản ánh VIẾT bài; ĐĂNG bài là chuỗi trạng thái riêng (post_status).
        $article = null;
        if (is_array($output) && (int) ($output['id'] ?? 0) > 0) {
            try {
                $full = (new AIOutput())->articleById((int) $output['id']);
                if (is_array($full)) {
                    $article = $this->decorate($full);
                }
            } catch (\Throwable $e) {
                $article = null;
            }
        }

        $this->render('admin.ai.task-detail', [
            'activeMenu' => 'ai-tasks',
            'pageTitle'  => 'Task #' . $taskId . ' - Quản Trị Hệ Thống',
            'task'       => $task,
            'activities' => $activities,
            'output'     => is_array($output) ? $output : null,
            'article'    => $article,
            'postStatus' => self::POST_STATUS,
            'module'     => is_array($module) ? $module : null,
            'payload'    => $this->decode($task['payload'] ?? null),
            'params'     => $this->decode($task['params'] ?? null),
        ]);
    }

    /**
     * Chạy MỘT task bằng TaskRunner (thay vì đợi worker CLI).
     */
    public function run(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai');
        }

        $taskId = (int) ($_POST['id'] ?? 0);

        $taskModel = new AITask();
        $task = $taskModel->find($taskId);

        if (!is_array($task)) {
            $this->flash('Task không tồn tại.', 'danger', '/admin/ai');
            return;
        }

        try {
            $outcome = $this->runner()->process($taskId, $this->adminWorkerId('manual'));
        } catch (\Throwable $e) {
            $this->logActivity('ai_task_run_failed', $e->getMessage());
            $this->flash('Lỗi khi chạy task: ' . $e->getMessage(), 'danger', '/admin/ai/tasks/detail?id=' . $taskId);
            return;
        }

        $type = (string) ($outcome['status'] ?? '');
        $message = (string) ($outcome['message'] ?? '');

        $this->logActivity('ai_task_run', sprintf('Chạy task #%d → %s.', $taskId, $type));

        $flashType = match ($type) {
            TaskRunner::RESULT_PROCESSED => 'success',
            TaskRunner::RESULT_RETRY     => 'warning',
            TaskRunner::RESULT_FAILED    => 'danger',
            default                      => 'warning',
        };

        $this->flash(
            sprintf('[%s] %s', strtoupper($type), $message),
            $flashType === 'warning' ? 'danger' : $flashType,
            '/admin/ai/tasks/detail?id=' . $taskId
        );
    }

    /**
     * Chạy một lượt worker (drain hàng đợi) ngay trên web.
     */
    public function drain(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai');
        }

        $limit = (int) ($_POST['limit'] ?? 5);
        if ($limit < 1 || $limit > 50) {
            $limit = 5;
        }

        try {
            $stats = $this->runner()->drain($this->adminWorkerId('drain'), $limit);
        } catch (\Throwable $e) {
            $this->logActivity('ai_task_drain_failed', $e->getMessage());
            $this->flash('Lỗi khi chạy worker: ' . $e->getMessage(), 'danger', '/admin/ai');
            return;
        }

        $this->logActivity(
            'ai_task_drain',
            sprintf('Chạy worker (limit %d): quét %d, xử lý %d, retry %d, thất bại %d, bỏ qua %d.', $limit, $stats['scanned'], $stats['processed'], $stats['retry'], $stats['failed'], $stats['skipped'])
        );

        $this->flash(
            sprintf(
                'Worker hoàn tất: quét %d, xử lý %d, thử lại %d, thất bại %d, bỏ qua %d.',
                $stats['scanned'],
                $stats['processed'],
                $stats['retry'],
                $stats['failed'],
                $stats['skipped']
            ),
            $stats['failed'] > 0 ? 'danger' : 'success',
            '/admin/ai'
        );
    }

    /**
     * Huỷ task đang chờ (KHÔNG huỷ task đang được worker giữ).
     */
    public function cancel(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai');
            return;
        }

        $taskId = (int) ($_POST['id'] ?? 0);

        $taskModel = new AITask();
        $task = $taskModel->find($taskId);

        if (!is_array($task)) {
            $this->flash('Task không tồn tại.', 'danger', '/admin/ai');
            return;
        }

        $status = (string) ($task['status'] ?? '');

        // 'processing' cũng cho huỷ: finish() không mang lock_token sẽ ghi trực tiếp
        // (đóng task + nhả lock) — worker đang chạy dính FENCING nên không thể
        // ghi 'completed' đè lên sau đó (TaskRunner: stale_write_blocked).
        if (!in_array($status, ['pending', 'queued', 'retrying', 'processing'], true)) {
            $this->flash(
                'Chỉ có thể huỷ task đang chờ/đang chạy (pending/queued/retrying/processing). Trạng thái hiện tại: ' . $status,
                'danger',
                '/admin/ai/tasks/detail?id=' . $taskId
            );
            return;
        }

        $this->runner()->dispatcher()->finish($taskId, 'cancelled', [
            'message' => 'Admin huỷ task từ giao diện quản trị.',
        ]);

        $this->logActivity('ai_task_cancel', 'Huỷ task #' . $taskId . '.');

        // Cho phép quay về trang gọi (vd. dashboard Hàng Đợi) thay vì luôn về detail.
        $back   = (string) ($_POST['back'] ?? '');
        $target = ($back !== '' && str_starts_with($back, '/admin/ai'))
            ? $back
            : ('/admin/ai/tasks/detail?id=' . $taskId);
        $this->flash('Đã huỷ task #' . $taskId . '.', 'success', $target);
    }

    /**
     * POST /admin/ai/articles/queue-cancel  (AJAX — nút ✕ trên cab "Tiến Trình Viết Bài")
     *
     * Huỷ MỘT item trong phiên soạn bài:
     * - item đang 'writing': đóng task server đang chạy (theo task_id hoặc
     *   idempotency_key) — FENCING bảo vệ: worker dính stillOwned() nên sẽ
     *   bỏ qua ghi 'completed' đè lên 'cancelled'.
     * - item 'waiting'/'failed': chỉ bỏ khỏi hàng đợi (chưa có task nào chạy).
     *
     * ⚠️ Giữ nguyên id + vị trí item (CHỈ đổi status → 'cancelled'): writeNext()
     * và pump() trong JS đều thao tác theo $idx — không splice/reindex để
     * writeNext không ghi đè done/failed lên item đã huỷ và không nhầm index.
     */
    public function queueCancel(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['ok' => false, 'message' => 'Phiên đăng nhập không hợp lệ.'], 400);
            return;
        }

        $idx     = (int) ($_POST['index'] ?? -1);
        $queueId = (string) ($_POST['queue_id'] ?? '');

        if ($idx < 0 || $queueId === '') {
            $this->json(['ok' => false, 'message' => 'Thiếu chỉ số item hoặc phiên hàng đợi.'], 400);
            return;
        }

        $queue = $_SESSION['article_queue'] ?? null;
        if (!is_array($queue) || (string) ($queue['id'] ?? '') !== $queueId || !isset($queue['items'][$idx])) {
            $this->json(['ok' => false, 'message' => 'Hàng đợi không tồn tại (đã chạy xong hoặc phiên mới).'], 404);
            return;
        }

        $item = $queue['items'][$idx];
        $prev = (string) ($item['status'] ?? '');

        if ($prev === 'done') {
            $this->json(['ok' => false, 'message' => 'Bài đã hoàn tất, không thể huỷ.'], 409);
            return;
        }

        // Đánh dấu TRƯỚC (giữ nguyên index) → writeNext() đang chạy dở sẽ thấy
        // 'cancelled' và KHÔNG ghi đè done/failed sau khi AI xong.
        $queue['items'][$idx]['status'] = 'cancelled';
        $queue['items'][$idx]['error']  = '';
        $_SESSION['article_queue'] = $queue;

        // Tìm task server (task_id đã ghi trong writeNext, hoặc tra theo idempotency_key).
        $taskId = (int) ($item['task_id'] ?? 0);
        if ($taskId <= 0 && ($item['idempotency_key'] ?? '') !== '') {
            $found = (new AITask())->findByIdempotencyKey((string) $item['idempotency_key']);
            if (is_array($found)) {
                $taskId = (int) ($found['id'] ?? 0);
            }
        }

        $closed = false;
        if ($taskId > 0) {
            $task = (new AITask())->find($taskId);
            $ts   = is_array($task) ? (string) ($task['status'] ?? '') : '';
            if (in_array($ts, ['pending', 'queued', 'retrying', 'processing'], true)) {
                $this->runner()->dispatcher()->finish($taskId, 'cancelled', [
                    'message' => 'Admin huỷ từ cab Tiến Trình Viết Bài.',
                ]);
                $closed = true;
            }
            // completed → tôn trọng kết quả (bài đã xong trong lúc bấm ✕).
        }

        $this->logActivity(
            'ai_article_write',
            sprintf(
                'Huỷ item #%d khỏi hàng đợi soạn bài "%s"%s.',
                $idx + 1,
                mb_substr((string) $item['topic'], 0, 60),
                $closed ? ' (đã đóng task #' . $taskId . ')' : ''
            )
        );

        $this->json(['ok' => true, 'cancelled' => $closed]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
