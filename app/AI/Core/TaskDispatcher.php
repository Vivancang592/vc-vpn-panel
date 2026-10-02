<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AIException;

/**
 * TaskDispatcher — vòng đời task AI: tạo / claim / lock / retry / hoàn tất.
 *
 * Ở Phase 5 KHÔNG có worker nền, KHÔNG có queue service. Class này chỉ cung cấp
 * cơ chế an toàn để task có thể được xử lý đồng thời mà không trùng lặp
 * (lock token + idempotency key) — sẵn sàng cho worker ở phase sau.
 *
 * CRASH RECOVERY (không cần sweeper/queue mới):
 *   - claim() là compare-and-set nguyên tử; ngoài nhánh claim thường còn có nhánh
 *     THU HỒI task 'processing' khi lock đã stale (locked_at < now - lock_stale_seconds).
 *   - Mỗi lần claim sinh lock_token mới → token cũ mất hiệu lực.
 *   - finish()/scheduleRetry() nhận lock_token để FENCE: worker cũ (token đã đổi)
 *     không thể ghi đè kết quả của worker mới (fail-closed).
 *   - touch() gia hạn lock cho task dài hơi (heartbeat), isClaimedBy() để worker
 *     tự kiểm tra quyền sở hữu trước khi ghi.
 *   - Task ở trạng thái kết thúc (completed/failed/cancelled/approved/rejected)
 *     KHÔNG bao giờ bị thu hồi.
 *
 * Mọi thao tác trạng thái đều ghi vào vc_ai_task_activities (append-only).
 */
final class TaskDispatcher
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Tạo task mới. Nếu idempotency_key đã tồn tại → trả về task cũ (không tạo trùng).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     * @return array{id: int, created: bool, idempotency_key: string}
     */
    public function create(
        int $moduleId,
        string $taskType,
        array $payload = [],
        array $params = [],
        ?int $modelId = null,
        ?string $idempotencyKey = null,
        ?int $createdBy = null
    ): array {
        $model = new \App\Models\AITask();

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return [
                    'id'              => (int) $existing['id'],
                    'created'         => false,
                    'idempotency_key' => $idempotencyKey,
                ];
            }
        }

        $maxRetries = (int) ($this->config['task']['default_max_retries'] ?? 3);
        $priority = (int) ($this->config['task']['default_priority'] ?? 5);

        // JSON_INVALID_UTF8_SUBSTITUTE: cột payload/params là JSON NOT NULL. Nếu input
        // từ Admin chứa byte không phải UTF-8 (hiếm, nhưng có thể do client gửi sai
        // encoding) thì json_encode() thất bại → NULL → MySQL báo lỗi "Invalid JSON
        // text" khó hiểu. Thay byte lỗi bằng U+FFFD để luôn ghi được một JSON hợp lệ.
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

        $columns = [
            'module_id'         => $moduleId,
            'model_id'          => $modelId,
            'task_type'         => $taskType,
            'status'            => 'pending',
            'idempotency_key'   => $idempotencyKey !== '' ? $idempotencyKey : null,
            'payload'           => $payload === [] ? null : json_encode($payload, $jsonFlags),
            'params'            => $params === [] ? null : json_encode($params, $jsonFlags),
            'priority'          => $priority,
            'attempt_count'     => 0,
            'retry_count'       => 0,
            'max_retries'       => $maxRetries,
            'created_by'        => $createdBy,
        ];

        // G4: chèn có "NHẬN NUÔI" khi đụng UNIQUE(idempotency_key) do TƯƠNG TRANH.
        $persist = $this->persistNewTask($model, $columns, $idempotencyKey);

        if ($persist['adopted']) {
            // Task đã được một request song song tạo giữa lúc pre-check và INSERT —
            // KHÔNG tạo trùng, trả về đúng task đang tồn tại (đúng hợp đồng idempotent).
            return [
                'id'              => $persist['id'],
                'created'         => false,
                'idempotency_key' => (string) $idempotencyKey,
            ];
        }

        $this->logActivity($persist['id'], 'created', 'pending', 'Task được tạo.');

        return [
            'id'              => $persist['id'],
            'created'         => true,
            'idempotency_key' => (string) $idempotencyKey,
        ];
    }

    /**
     * Chèn row task mới; xử lý TƯƠNG TRANH idempotency.
     *
     * Nếu INSERT đụng UNIQUE(idempotency_key) (SQLSTATE 23000) — nghĩa là row đã
     * được tạo giữa lúc pre-check `findByIdempotencyKey()` và câu INSERT này — thì
     * đọc lại row đang tồn tại và "nhận nuôi" nó thay vì ném PDOException ra ngoài.
     * Như vậy lời hứa của create() ("cùng key → cùng task, không tạo trùng") đúng
     * NGAY CẢ khi có tương tranh, mà KHÔNG nới lỏng ràng buộc UNIQUE của DB.
     *
     * Nếu không phải lỗi UNIQUE, hoặc không truy tìm được row đang tồn tại → ném lại
     * nguyên PDOException để không che giấu lỗi thật.
     *
     * @param array<string, mixed> $columns
     * @return array{id: int, adopted: bool}
     */
    private function persistNewTask(\App\Models\AITask $model, array $columns, ?string $idempotencyKey): array
    {
        try {
            if (!$model->create($columns)) {
                throw new AIException('Không tạo được task AI.', AIException::UNKNOWN);
            }

            return ['id' => $model->lastInsertId(), 'adopted' => false];
        } catch (\PDOException $e) {
            if ($idempotencyKey !== null && $idempotencyKey !== '' && (string) $e->getCode() === '23000') {
                $existing = $this->findByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    return ['id' => (int) $existing['id'], 'adopted' => true];
                }
            }

            throw $e;
        }
    }

    /**
     * Claim task để xử lý (đảm bảo an toàn khi nhiều worker chạy song song).
     *
     * CƠ CHẾ: một câu UPDATE có điều kiện (compare-and-set) — chỉ worker nào UPDATE
     * thành công (rowCount = 1) mới sở hữu task. Gồm 2 nhánh:
     *   (A) Nhánh claim thường: task đang chờ xử lý ('pending','queued','retrying')
     *       và không còn giữ lock cũ còn hiệu lực.
     *   (B) Nhánh THU HỒI (crash recovery): task đang 'processing' nhưng lock ĐÃ STALE
     *       (locked_at < now - lock_stale_seconds) → worker chết giữa chừng, cho phép
     *       worker khác giành lại. Đây là lý do tồn tại của lock_stale_seconds.
     *
     * Mỗi lần claim (kể cả thu hồi) đều sinh lock_token MỚI (uuid4) và tăng attempt_count.
     * Token mới làm vô hiệu token của worker cũ: worker cũ muốn ghi kết quả phải đi qua
     * finish()/touch() có kiểm tra lock_token (fencing) nên KHÔNG thể ghi đè.
     *
     * KHÔNG thu hồi task đã ở trạng thái kết thúc (completed/failed/cancelled/approved/
     * rejected) vì các trạng thái đó không nằm trong predicate.
     *
     * @return array<string, mixed>|null Task đã claim, null nếu không giành được.
     */
    public function claim(int $taskId, string $workerId): ?array
    {
        $pdo = $this->pdo();
        $staleSeconds = (int) ($this->config['task']['lock_stale_seconds'] ?? 900);
        $token = $this->uuid4();
        $staleCutoff = date('Y-m-d H:i:s', time() - $staleSeconds);
        $now = date('Y-m-d H:i:s');

        // Đọc trạng thái trước khi claim (chỉ để ghi log chính xác claim vs thu hồi).
        // An toàn với tương tranh: nếu worker khác chen vào, UPDATE bên dưới sẽ rowCount=0.
        $priorStmt = $pdo->prepare("SELECT `status` FROM `vc_ai_tasks` WHERE `id` = :id LIMIT 1");
        $priorStmt->execute(['id' => $taskId]);
        $priorStatus = $priorStmt->fetchColumn();
        $isReclaimAttempt = ((string) $priorStatus === 'processing');

        $sql = "UPDATE `vc_ai_tasks`
                   SET `status` = 'processing',
                       `locked_at` = :now,
                       `locked_by` = :worker,
                       `lock_token` = :token,
                       `started_at` = COALESCE(`started_at`, :now2),
                       `attempt_count` = `attempt_count` + 1
                 WHERE `id` = :id
                   AND (
                         (
                           `status` IN ('pending', 'queued', 'retrying')
                           AND (`lock_token` IS NULL OR `locked_at` IS NULL OR `locked_at` < :cutoff)
                         )
                         OR
                         (
                           `status` = 'processing'
                           AND `locked_at` IS NOT NULL
                           AND `locked_at` < :cutoff
                         )
                   )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'now'      => $now,
            'now2'     => $now,
            'worker'   => $workerId,
            'token'    => $token,
            'id'       => $taskId,
            'cutoff'   => $staleCutoff,
        ]);

        if ($stmt->rowCount() !== 1) {
            return null;
        }

        $task = (new \App\Models\AITask())->find($taskId);

        if (is_array($task)) {
            $this->logActivity(
                $taskId,
                'claimed',
                'processing',
                $isReclaimAttempt
                    ? 'Worker ' . $workerId . ' đã THU HỒI task (lock cũ đã stale).'
                    : 'Worker ' . $workerId . ' đã nhận task.',
                [
                    'attempt_no' => (int) ($task['attempt_count'] ?? 1),
                    'extra'      => ['reclaimed' => $isReclaimAttempt, 'lock_token' => $token],
                ]
            );
        }

        return is_array($task) ? $task : null;
    }

    /**
     * Đánh dấu completed / failed / cancelled / awaiting_review / approved / rejected.
     *
     * G5 — LƯU Ý ENUM (không đổi ở phase này): cột `vc_ai_tasks.status` trong schema
     * hiện tại CHỈ chứa 7 giá trị
     *   ('pending','queued','processing','completed','failed','cancelled','retrying').
     * Ba giá trị 'awaiting_review','approved','rejected' nằm trong DANH SÁCH CHO PHÉP
     * của hàm này nhưng CHƯA có trong ENUM của bảng → dưới sql_mode STRICT sẽ bị MySQL
     * từ chối. Vì vậy CALLER (TaskRunner) CHỈ được finish task với các trạng thái có
     * thật trong ENUM; 'completed'/'failed'/'cancelled'/'retrying' là hợp lệ. Ba giá
     * trị review kia là API dự phòng cho phase sau (khi có migration hợp lệ). Danh sách
     * cho phép KHÔNG được nới thêm cho tới khi ENUM được cập nhật bằng migration.
     *
     * FENCING (chống worker cũ ghi đè): nếu $meta['lock_token'] được truyền, lệnh ghi
     * chỉ thực hiện khi task VẪN đang 'processing' và `lock_token` còn ĐÚNG token đó
     * (compare-and-set). Nếu task đã bị worker khác thu hồi (token đã đổi) → KHÔNG ghi
     * gì, chỉ ghi activity 'stale_write_blocked' (fail-closed).
     *
     * Nếu KHÔNG truyền lock_token (worker chưa hỗ trợ / admin / system), giữ nguyên
     * hành vi cũ (ghi trực tiếp theo id) để tương thích ngược.
     *
     * @param array<string, mixed> $meta Có thể kèm khoá 'lock_token' để bật fencing.
     */
    public function finish(int $taskId, string $status, array $meta = []): void
    {
        $allowed = ['completed', 'failed', 'cancelled', 'awaiting_review', 'approved', 'rejected'];

        if (!in_array($status, $allowed, true)) {
            throw new AIException('Trạng thái kết thúc không hợp lệ: ' . $status, AIException::VALIDATION);
        }

        $patch = [
            'status'      => $status,
            'finished_at' => date('Y-m-d H:i:s'),
            'locked_at'   => null,
            'locked_by'   => null,
            'lock_token'  => null,
        ];

        if (!empty($meta['error_code'])) {
            $patch['error_code'] = (string) $meta['error_code'];
        }

        if (!empty($meta['error_message'])) {
            $patch['error_message'] = (string) $meta['error_message'];
        }

        $fenceToken = isset($meta['lock_token']) ? (string) $meta['lock_token'] : '';

        if ($fenceToken !== '') {
            if (!$this->fencedFinish($taskId, $fenceToken, $patch)) {
                $this->logActivity(
                    $taskId,
                    'stale_write_blocked',
                    null,
                    'Bỏ qua ghi trạng thái kết thúc: lock_token không còn hợp lệ (task đã bị thu hồi hoặc đã kết thúc).',
                    ['extra' => ['attempted_status' => $status]]
                );

                return;
            }
        } else {
            (new \App\Models\AITask())->update($taskId, $patch);
        }

        $this->logActivity(
            $taskId,
            'finished',
            $status,
            (string) ($meta['message'] ?? 'Task kết thúc với trạng thái ' . $status . '.'),
            [
                'attempt_no'  => $meta['attempt_no'] ?? null,
                'error_code'  => $meta['error_code'] ?? null,
                'http_status' => $meta['http_status'] ?? null,
                'duration_ms' => $meta['duration_ms'] ?? null,
            ]
        );

        (new \App\Models\AITask())->pruneFinishedOlderThan(24);
    }

    /**
     * Ghi trạng thái kết thúc có FENCING theo lock_token (compare-and-set).
     *
     * @param array<string, mixed> $patch Các cột cần set (KHÔNG chứa 'id').
     * @return bool true nếu ghi thành công (đúng chủ sở hữu), false nếu bị chặn.
     */
    private function fencedFinish(int $taskId, string $fenceToken, array $patch): bool
    {
        $pdo = $this->pdo();

        $sets = [];
        foreach (array_keys($patch) as $column) {
            $sets[] = "`{$column}` = :{$column}";
        }

        // Điều kiện fencing dùng placeholder RIÊNG (:fence_token) để không đụng
        // placeholder 'lock_token' trong mệnh đề SET (đang set về NULL).
        $sql = "UPDATE `vc_ai_tasks`
                   SET " . implode(', ', $sets) . "
                 WHERE `id` = :id
                   AND `status` = 'processing'
                   AND `lock_token` = :fence_token";

        $params = $patch;
        $params['id'] = $taskId;
        $params['fence_token'] = $fenceToken;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() === 1;
    }

    /**
     * Gia hạn lock (heartbeat) cho worker đang giữ task.
     *
     * Chỉ cập nhật `locked_at` khi task vẫn 'processing' và (nếu truyền token) token còn khớp.
     * Dùng để task dài hơi không bị coi là stale. Phase 5 chưa có worker nền nên đây là
     * API sẵn sàng cho phase sau.
     *
     * @return bool true nếu gia hạn được.
     */
    public function touch(int $taskId, string $workerId, ?string $lockToken = null): bool
    {
        $pdo = $this->pdo();

        $sql = "UPDATE `vc_ai_tasks`
                   SET `locked_at` = :now
                 WHERE `id` = :id
                   AND `status` = 'processing'
                   AND `locked_by` = :worker";

        $params = [
            'now'    => date('Y-m-d H:i:s'),
            'id'     => $taskId,
            'worker' => $workerId,
        ];

        if ($lockToken !== null && $lockToken !== '') {
            $sql .= " AND `lock_token` = :token";
            $params['token'] = $lockToken;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 1) {
            return true;
        }

        // rowCount = 0 có thể do (a) KHÔNG còn là chủ sở hữu, hoặc (b) vẫn là chủ nhưng
        // `locked_at` mới trùng giá trị cũ (MySQL không tính là "changed row"). Phân biệt
        // bằng cách kiểm tra quyền sở hữu — nếu vẫn sở hữu thì coi như gia hạn thành công.
        return $this->isOwnedBy($taskId, $workerId, $lockToken);
    }

    /**
     * Kiểm tra worker (và token nếu có) còn là chủ sở hữu task 'processing' hay không.
     */
    private function isOwnedBy(int $taskId, string $workerId, ?string $lockToken): bool
    {
        try {
            $sql = "SELECT 1 FROM `vc_ai_tasks`
                     WHERE `id` = :id AND `status` = 'processing' AND `locked_by` = :worker";
            $params = ['id' => $taskId, 'worker' => $workerId];

            if ($lockToken !== null && $lockToken !== '') {
                $sql .= " AND `lock_token` = :token";
                $params['token'] = $lockToken;
            }

            $sql .= ' LIMIT 1';

            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Kiểm tra một lock_token còn là chủ sở hữu hợp lệ của task hay không.
     *
     * Worker nên gọi trước khi thực hiện bước ghi kết quả tốn kém để tránh ghi
     * đè lên kết quả của worker đã thu hồi task.
     */
    public function isClaimedBy(int $taskId, string $lockToken): bool
    {
        if ($lockToken === '') {
            return false;
        }

        try {
            $stmt = $this->pdo()->prepare(
                "SELECT 1 FROM `vc_ai_tasks`
                  WHERE `id` = :id AND `status` = 'processing' AND `lock_token` = :token
                  LIMIT 1"
            );
            $stmt->execute(['id' => $taskId, 'token' => $lockToken]);

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Lên lịch retry với backoff (ghi retry_count, retry_after).
     *
     * FENCING: nếu truyền $lockToken (worker đang giữ task) thì thao tác chuyển sang
     * 'retrying' chỉ thực hiện khi token còn hợp lệ (compare-and-set) — worker cũ không
     * thể đẩy task đã bị thu hồi về 'retrying'.
     *
     * @return bool true nếu còn lượt retry, false nếu đã cạn (hoặc bị chặn bởi fencing).
     */
    public function scheduleRetry(
        int $taskId,
        string $errorType,
        string $message,
        RetryPolicy $policy,
        int $attempt,
        ?string $lockToken = null
    ): bool {
        $model = new \App\Models\AITask();
        $task = $model->find($taskId);

        if (!is_array($task)) {
            return false;
        }

        $retryCount = (int) ($task['retry_count'] ?? 0);
        $maxRetries = (int) ($task['max_retries'] ?? 3);

        if ($retryCount >= $maxRetries) {
            $this->finish($taskId, 'failed', [
                'error_code'    => $errorType,
                'error_message' => $message,
                'message'       => 'Đã cạn số lần retry.',
                'lock_token'    => $lockToken,
                'attempt_no'    => $attempt,
            ]);

            return false;
        }

        $delayMs = $policy->default() === []
            ? 0
            : $policy->delayFor($policy->default(), $attempt);

        $retryAfter = date('Y-m-d H:i:s', time() + (int) ceil($delayMs / 1000));

        $patch = [
            'status'        => 'retrying',
            'retry_count'   => $retryCount + 1,
            'retry_after'   => $retryAfter,
            'error_code'    => $errorType,
            'error_message' => $message,
            'locked_at'     => null,
            'locked_by'     => null,
            'lock_token'    => null,
        ];

        if ($lockToken !== null && $lockToken !== '') {
            if (!$this->fencedFinish($taskId, $lockToken, $patch)) {
                $this->logActivity(
                    $taskId,
                    'stale_write_blocked',
                    null,
                    'Bỏ qua lên lịch retry: lock_token không còn hợp lệ (task đã bị thu hồi).',
                    ['extra' => ['attempted_status' => 'retrying']]
                );

                return false;
            }
        } else {
            $model->update($taskId, $patch);
        }

        $this->logActivity($taskId, 'retry_scheduled', 'retrying', $message, [
            'error_code'  => $errorType,
            'attempt_no'  => $attempt,
            'retry_in_ms' => $delayMs,
        ]);

        return true;
    }

    /**
     * Đưa task đang 'processing' về 'queued' để chờ poll LRO (Long-Running Operation).
     *
     * Dùng cho video bất đồng bộ: provider trả operation id ngay (HTTP 200) nhưng
     * media chưa sẵn sàng. Worker KHÔNG finish() (vì 'queued' không nằm trong danh
     * sách trạng thái kết thúc của finish()) mà requeue() — task quay lại hàng đợi,
     * lần claim sau sẽ gọi videoStatus(operation_id) để poll tiếp.
     *
     * KHÁC scheduleRetry():
     *  - KHÔNG tăng retry_count / attempt_count (đây KHÔNG phải lỗi).
     *  - KHÔNG ghi error_code/error_message.
     *  - retry_after = now + poll_seconds (đặt lịch poll, dueCandidates() tôn trọng).
     *  - Lưu $meta (operation id, số lần poll...) vào cột `params` (JSON) để lần
     *    poll sau đọc lại mà không cần gọi provider lần nữa.
     *
     * FENCING: chỉ requeue khi task vẫn 'processing' VÀ lock_token còn khớp
     * (compare-and-set) — worker cũ mất lock không thể đẩy task về hàng đợi.
     *
     * @param array<string, mixed> $meta Dữ liệu bổ sung ghi vào cột `params`.
     * @return bool true nếu requeue thành công (worker còn giữ lock).
     */
    public function requeue(int $taskId, string $lockToken, int $pollSeconds, array $meta = []): bool
    {
        $pdo = $this->pdo();

        $sql = "UPDATE `vc_ai_tasks`
                   SET `status`      = 'queued',
                       `retry_after` = :retry_after,
                       `params`      = :params,
                       `locked_at`   = NULL,
                       `locked_by`   = NULL,
                       `lock_token`  = NULL
                 WHERE `id` = :id
                   AND `status` = 'processing'
                   AND `lock_token` = :fence_token";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'retry_after'  => date('Y-m-d H:i:s', time() + max(1, $pollSeconds)),
            'params'       => $meta === [] ? null : json_encode(
                $meta,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ),
            'id'           => $taskId,
            'fence_token'  => $lockToken,
        ]);

        if ($stmt->rowCount() !== 1) {
            $this->logActivity(
                $taskId,
                'stale_write_blocked',
                null,
                'Bỏ qua requeue LRO: lock_token không còn hợp lệ (task đã bị thu hồi).',
                ['extra' => ['attempted_status' => 'queued']]
            );

            return false;
        }

        $this->logActivity(
            $taskId,
            'video_lro_wait',
            'queued',
            'Provider chưa trả media xong — task về hàng đợi chờ poll LRO.',
            ['extra' => ['poll_after_seconds' => max(1, $pollSeconds)]]
        );

        return true;
    }

    /**
     * Ghi một dòng activity (append-only).
     *
     * @param array<string, mixed> $meta
     */
    public function logActivity(int $taskId, string $activityType, ?string $status, string $message, array $meta = []): void
    {
        try {
            $model = new \App\Models\AITaskActivity();

            $row = [
                'task_id'       => $taskId,
                'attempt_no'    => max(1, (int) ($meta['attempt_no'] ?? 1)),
                'activity_type' => $activityType,
                'status'        => $status,
                'message'       => $message,
            ];

            if (isset($meta['error_code'])) {
                $row['error_code'] = (string) $meta['error_code'];
            }

            if (isset($meta['duration_ms'])) {
                $row['duration_ms'] = (int) $meta['duration_ms'];
            }

            if (isset($meta['http_status'])) {
                $row['http_status'] = (int) $meta['http_status'];
            }

            if (isset($meta['retry_in_ms'])) {
                $row['retry_in_ms'] = (int) $meta['retry_in_ms'];
            }

            if (!empty($meta['extra']) && is_array($meta['extra'])) {
                $row['meta'] = json_encode($meta['extra'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $model->create($row);
        } catch (\Throwable $e) {
            // Activity là thông tin phụ trợ — không được làm hỏng luồng chính.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $key): ?array
    {
        try {
            $rows = (new \App\Models\AITask())->getAll();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ((array) $rows as $row) {
            if (is_array($row) && (string) ($row['idempotency_key'] ?? '') === $key) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $taskId): ?array
    {
        try {
            $row = (new \App\Models\AITask())->find($taskId);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Giải phóng lock (gọi thủ công khi muốn xoá chủ sở hữu lock).
     *
     * LƯU Ý: chỉ xoá `locked_at`/`locked_by`/`lock_token`, KHÔNG đổi `status`.
     * Do đó nếu task đang 'processing', nó vẫn ở 'processing' nhưng KHÔNG còn lock.
     * Crash recovery KHÔNG dựa vào method này: task 'processing' bị bỏ quên sẽ tự được
     * thu hồi bởi claim() sau khi lock stale (xem claim()). release() chủ yếu dùng cho
     * admin/thao tác thủ công.
     */
    public function release(int $taskId): void
    {
        (new \App\Models\AITask())->update($taskId, [
            'locked_at'  => null,
            'locked_by'  => null,
            'lock_token' => null,
        ]);
    }

    /**
     * Lấy PDO của BaseModel (tạo kết nối nếu chưa có).
     *
     * BaseModel::getPdo() khởi tạo kết nối bằng `new static()` — trên lớp abstract
     * BaseModel điều này gây lỗi nếu đây là lần chạm DB ĐẦU TIÊN trong tiến trình.
     * Vì BaseModel là file bất khả xâm phạm, ta "mồi" kết nối bằng cách instantiate
     * một model cụ thể (AITask) trước — sau lần đầu đây là no-op.
     */
    private function pdo(): \PDO
    {
        new \App\Models\AITask();

        return \App\Models\BaseModel::getPdo();
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
