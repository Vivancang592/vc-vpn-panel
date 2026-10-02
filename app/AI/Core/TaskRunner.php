<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AIException;
use App\AI\Contracts\AIResult;

/**
 * TaskRunner — bộ điều phối (glue) nối các thành phần đã có của AI Core
 * thành MỘT vòng đời công việc AI durable:
 *
 *   REQUEST  → TaskDispatcher::create()   (idempotency_key, status 'pending')
 *   TASK     → vc_ai_tasks                (hàng đợi thật, cột có sẵn)
 *   CLAIM    → TaskDispatcher::claim()    (CAS + lock_token + crash recovery)
 *   PROCESS  → AICore::run()              (pipeline module → prompt → model)
 *   PROVIDER → KiraProvider               (transport/API)
 *   OUTPUT   → OutputHandler              (vc_ai_outputs + version, append-only)
 *   ASSET    → AssetManager               (file thật, KHÔNG blob trong DB)
 *   FINISH   → TaskDispatcher::finish()   (FENCE bằng chính lock_token)
 *
 * NGUYÊN TẮC (bám sát source hiện có, không thêm hạ tầng):
 *  - KHÔNG tự chạy nền, KHÔNG daemon, KHÔNG queue/worker mới, KHÔNG migration.
 *    Trigger = HTTP cron sẵn có (`TaskRunner::drain()` — batch có giới hạn)
 *    hoặc gọi trực tiếp một task (`TaskRunner::process()`).
 *  - KHÔNG gọi provider trực tiếp — mọi lời gọi AI đi qua AICore.
 *  - FENCING dùng CHÍNH `lock_token` của TaskDispatcher (KHÔNG tạo cơ chế lock
 *    thứ hai). Trước khi ghi output luôn kiểm tra còn giữ lock; mất lock →
 *    fail-closed (không ghi, ghi activity 'stale_write_blocked').
 *  - Lỗi tạm thời (theo RetryPolicy) → scheduleRetry (backoff + retry_count);
 *    lỗi vĩnh viễn / cạn lượt → finish('failed').
 *  - Thành công → task finish('completed'); OUTPUT nằm ở review_status='generated'
 *    (chờ duyệt). Tách đúng 2 tầng: vòng đời THỰC THI của task vs vòng đời REVIEW
 *    của output (review KHÔNG thuộc phase này).
 *
 * Class này KHÔNG chứa prompt nghiệp vụ, KHÔNG hard-code model, KHÔNG đọc
 * HTTP request, KHÔNG biết chi tiết module nào làm gì.
 */
final class TaskRunner
{
    /** Task đã chạy xong một lượt và sinh output (đang chờ review). */
    public const RESULT_PROCESSED = 'processed';
    /** Không giành được task, hoặc mất lock trước khi ghi → bỏ qua an toàn. */
    public const RESULT_SKIPPED = 'skipped';
    /** Lỗi tạm thời → đã lên lịch retry. */
    public const RESULT_RETRY = 'retry';
    /** Lỗi vĩnh viễn hoặc đã cạn lượt retry. */
    public const RESULT_FAILED = 'failed';

    /** Trạng thái task được coi là "đến hạn xử lý" trong hàng đợi. */
    private const DUE_STATUSES = ['pending', 'queued', 'retrying'];

    /**
     * Trạng thái LRO của provider coi là "CHƯA XONG" (chờ poll tiếp).
     * Ngoài danh sách này (và không có file) → chờ tới khi timeout tổng.
     */
    private const VIDEO_LRO_FAILED = ['failed', 'error', 'cancelled', 'canceled', 'expired'];

    /** @var array<string, mixed> */
    private array $config;

    private ?AICore $core;
    private TaskDispatcher $tasks;
    private OutputHandler $output;
    private RetryPolicy $retry;
    private ErrorHandler $errors;
    private ModuleRegistry $modules;
    private ResponseParser $responses;

    /**
     * @param array<string, mixed> $config Config AI (config/ai.php)
     */
    public function __construct(
        array $config = [],
        ?AICore $core = null,
        ?TaskDispatcher $tasks = null,
        ?OutputHandler $output = null,
        ?ModuleRegistry $modules = null
    ) {
        $this->config    = $config;
        $this->core      = $core;
        $this->tasks     = $tasks ?? new TaskDispatcher($config);
        $this->output    = $output ?? new OutputHandler();
        $this->retry     = new RetryPolicy($config);
        $this->errors    = new ErrorHandler();
        $this->modules   = $modules ?? new ModuleRegistry();
        $this->responses = new ResponseParser();
    }

    public function dispatcher(): TaskDispatcher
    {
        return $this->tasks;
    }

    public function outputHandler(): OutputHandler
    {
        return $this->output;
    }

    // -----------------------------------------------------------------
    // REQUEST → TASK
    // -----------------------------------------------------------------

    /**
     * Ghi danh một YÊU CẦU AI thành task durable (bước REQUEST → TASK).
     *
     * - Idempotent: cùng (module, payload) hoặc cùng idempotency_key → cùng task.
     * - Yêu cầu module đã được đăng ký trong `vc_ai_modules` (Admin bật) và có
     *   định nghĩa trong ModuleRegistry; nếu thiếu → AIException::CONFIG
     *   (fail-fast, KHÔNG tự đoán/seed hộ).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return array{id: int, created: bool, idempotency_key: string}
     */
    public function request(
        string $moduleKey,
        array $payload = [],
        array $options = [],
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        ?int $moduleId = null
    ): array {
        if ($this->modules->get($moduleKey) === null) {
            throw new AIException(
                'Module không tồn tại: ' . $moduleKey,
                AIException::VALIDATION,
                null,
                ['module' => $moduleKey]
            );
        }

        $resolvedModuleId = $moduleId ?? $this->persistedModuleId($moduleKey);
        if ($resolvedModuleId === null || $resolvedModuleId <= 0) {
            throw new AIException(
                'Module "' . $moduleKey . '" chưa được đăng ký/bật trong vc_ai_modules.',
                AIException::CONFIG,
                null,
                ['module' => $moduleKey]
            );
        }

        $idempotencyKey = $idempotencyKey ?? (new InputHandler($this->modules))->idempotencyKey($moduleKey, $payload);
        $taskType = (string) ($options['task_type'] ?? $moduleKey);

        return $this->tasks->create(
            $resolvedModuleId,
            $taskType,
            $payload,
            $options,
            isset($options['model_id']) ? (int) $options['model_id'] : null,
            $idempotencyKey,
            $createdBy
        );
    }

    // -----------------------------------------------------------------
    // CLAIM → PROCESS → PROVIDER → OUTPUT → ASSET → FINISH
    // -----------------------------------------------------------------

    /**
     * Chạy trọn vòng đời cho MỘT task bằng một worker.
     *
     * @return array<string, mixed> Kết quả có khoá 'status' (một trong các RESULT_*).
     */
    public function process(int $taskId, string $workerId): array
    {
        // G6 — CHƯA TỚI HẠN RETRY: kiểm tra TRƯỚC khi claim. TaskDispatcher::claim()
        // (schema hiện tại, không migration) không lọc retry_after, nên một lời gọi
        // TRỰC TIẾP (không qua drain) có thể xử lý retry quá sớm. Kiểm tra trước
        // claim để KHÔNG đụng vào trạng thái task khi chưa đến hạn (fail-safe, không
        // đổi semantics của claim(); drain() cũng đã lọc ở danh sách ứng viên).
        $snapshot = $this->tasks->find($taskId);
        if (is_array($snapshot) && !$this->retryDue($snapshot)) {
            return $this->outcome(
                self::RESULT_SKIPPED,
                $taskId,
                'Chưa tới hạn retry (retry_after > now) — bỏ qua, chờ lần quét sau.'
            );
        }

        // CLAIM (CAS + lock_token + crash recovery nằm trong TaskDispatcher).
        $claimed = $this->tasks->claim($taskId, $workerId);

        if ($claimed === null) {
            return $this->outcome(
                self::RESULT_SKIPPED,
                $taskId,
                'Không giành được task (đang được worker khác xử lý hoặc đã kết thúc).'
            );
        }

        $token     = (string) ($claimed['lock_token'] ?? '');
        $moduleId  = (int) ($claimed['module_id'] ?? 0);
        $moduleKey = $this->moduleKeyOf($moduleId);
        $payload   = $this->decode($claimed['payload'] ?? null);
        $options   = $this->decode($claimed['params'] ?? null);
        $attempt   = max(1, (int) ($claimed['attempt_count'] ?? 1));

        if ($moduleKey === null) {
            $this->tasks->finish($taskId, 'failed', [
                'lock_token'    => $token,
                'attempt_no'    => $attempt,
                'error_code'    => AIException::CONFIG,
                'error_message' => 'Không xác định được module hợp lệ cho task (module_id=' . $moduleId . ').',
                'message'       => 'Task thiếu module hợp lệ trong vc_ai_modules.',
            ]);

            return $this->outcome(self::RESULT_FAILED, $taskId, 'Không xác định được module.');
        }

        // VIDEO LRO — lần POLL (params đã có operation id từ lượt trước): gọi
        // videoStatus() thay vì chạy lại pipeline (tránh tạo trùng video mới).
        $isVideoLroPoll = $moduleKey === 'video_generation'
            && isset($options['kira_operation_id'])
            && is_string($options['kira_operation_id'])
            && $options['kira_operation_id'] !== '';

        // PROCESS → PROVIDER (toàn bộ pipeline nằm trong AICore; lỗi đã được
        // ErrorHandler chuẩn hoá thành AIResult nên hiếm khi ném ra ngoài).
        $runStarted = microtime(true);

        try {
            $result = $isVideoLroPoll
                ? $this->core()->videoStatus((string) $options['kira_operation_id'], ['module' => $moduleKey])
                : $this->core()->run($moduleKey, $payload, $options);
        } catch (\Throwable $e) {
            $result = $this->errors->handle($e, ['module' => $moduleKey]);
        }

        // FENCE (fail-closed): nếu đã mất lock → KHÔNG ghi output/asset.
        if (!$this->stillOwned($taskId, $token)) {
            $this->tasks->logActivity(
                $taskId,
                'stale_write_blocked',
                null,
                'Bỏ qua ghi output: lock_token không còn hợp lệ (task đã bị thu hồi).',
                ['extra' => ['module' => $moduleKey]]
            );

            return $this->outcome(
                self::RESULT_SKIPPED,
                $taskId,
                'Mất lock trước khi ghi output — bỏ qua để bảo toàn dữ liệu.'
            );
        }

        if (!$result->isOk()) {
            return $this->handleFailure($taskId, $token, $attempt, $result);
        }

        // VIDEO LRO (Long-Running Operation): provider trả HTTP 200 nhưng media
        // CHƯA xong (raw.status chưa 'completed' hoặc chưa có file). Phân xử:
        //  - pending  → requeue về 'queued' (retry_after = +poll_seconds), lần
        //    claim sau sẽ poll videoStatus(operation_id). KHÔNG tính retry_count.
        //  - timeout  → vượt video_lro_timeout_seconds → finish('failed', TIMEOUT).
        //  - xong     → đi tiếp ghi output như mọi module khác.
        if ($moduleKey === 'video_generation') {
            $lro = $this->resolveVideoLro($result, $options, (string) ($claimed['started_at'] ?? ''));

            if ($lro['action'] === 'wait') {
                $ok = $this->tasks->requeue(
                    $taskId,
                    $token,
                    (int) ($this->config['task']['video_poll_seconds'] ?? 60),
                    $lro['params']
                );

                return $ok
                    ? $this->outcome(
                        self::RESULT_SKIPPED,
                        $taskId,
                        'Video đang được provider xử lý (LRO) — task về hàng đợi chờ poll.',
                        ['operation_id' => $lro['params']['kira_operation_id'], 'polls' => $lro['params']['lro_polls']]
                    )
                    : $this->outcome(
                        self::RESULT_SKIPPED,
                        $taskId,
                        'Mất lock khi requeue LRO — bỏ qua để bảo toàn dữ liệu.'
                    );
            }

            if ($lro['action'] === 'timeout') {
                $this->tasks->finish($taskId, 'failed', [
                    'lock_token'    => $token,
                    'attempt_no'    => $attempt,
                    'error_code'    => AIException::TIMEOUT,
                    'error_message' => 'Video LRO vượt thời gian chờ tối đa ('
                        . (int) ($this->config['task']['video_lro_timeout_seconds'] ?? 1800) . 's).',
                    'message'       => 'Provider chưa trả video xong trong thời hạn cho phép.',
                ]);

                return $this->outcome(
                    self::RESULT_FAILED,
                    $taskId,
                    'Video LRO vượt thời gian chờ tối đa.',
                    ['operation_id' => (string) ($options['kira_operation_id'] ?? '')]
                );
            }

            if ($lro['action'] === 'fail') {
                $this->tasks->finish($taskId, 'failed', [
                    'lock_token'    => $token,
                    'attempt_no'    => $attempt,
                    'error_code'    => AIException::CLIENT_ERROR,
                    'error_message' => 'Provider báo LRO thất bại: status="' . $lro['status'] . '"',
                    'message'       => 'Provider báo tác vụ video thất bại (không thể retry).',
                ]);

                return $this->outcome(
                    self::RESULT_FAILED,
                    $taskId,
                    'Provider báo tác vụ video thất bại (status=' . $lro['status'] . ').',
                    ['operation_id' => (string) ($options['kira_operation_id'] ?? '')]
                );
            }
        }

        // OUTPUT → ASSET (media đi qua AssetManager; text lưu content_snapshot).
        $outputType = (string) ($this->modules->outputKindOf($moduleKey) ?? 'text');

        try {
            $written = $this->writeOutput($taskId, $moduleId, $outputType, $result);
        } catch (\Throwable $e) {
            return $this->handleFailure(
                $taskId,
                $token,
                $attempt,
                $this->errors->handle($e, ['module' => $moduleKey, 'stage' => 'output'])
            );
        }

        // FENCE LẦN 2 (G1, fail-closed): writeOutput có thể mất thời gian (tải file
        // media qua AssetManager). Nếu trong khoảng đó lock đã bị thu hồi (task bị
        // worker khác reclaim), KHÔNG ghi trạng thái 'completed' — nếu không sẽ
        // "đóng" nhầm một task mà worker mới đang xử lý. Output/version đã ghi là
        // append-only và idempotent (writeOutput tái sử dụng output cũ) nên an toàn.
        if (!$this->stillOwned($taskId, $token)) {
            $this->tasks->logActivity(
                $taskId,
                'stale_write_blocked',
                null,
                'Bỏ qua finish(): mất lock trong lúc ghi output (task đã bị thu hồi).',
                ['extra' => ['module' => $moduleKey, 'stage' => 'finish']]
            );

            return $this->outcome(
                self::RESULT_SKIPPED,
                $taskId,
                'Mất lock trong lúc ghi output — không ghi trạng thái kết thúc.'
            );
        }

        // FINISH có FENCE theo lock_token.
        // LƯU Ý (bám đúng schema, KHÔNG migration): vòng đời THỰC THI của task kết
        // thúc ở 'completed'. Trạng thái "chờ duyệt" thuộc TẦNG OUTPUT
        // (vc_ai_outputs.review_status = 'generated', đã có trong ENUM) — KHÔNG
        // dùng 'awaiting_review' cho vc_ai_tasks vì ENUM của bảng này không chứa
        // giá trị đó (STRICT mode sẽ từ chối).
        // duration_ms = thời gian chạy pipeline (provider + ghi output) — bơm vào
        // activity 'finished' để cột "Thời Gian (ms)" của Nhật Ký Xử Lý có dữ liệu.
        $this->tasks->finish($taskId, 'completed', [
            'lock_token'  => $token,
            'attempt_no'  => $attempt,
            'duration_ms' => (int) round((microtime(true) - $runStarted) * 1000),
            'message'     => 'Sinh output thành công; output đang ở trạng thái generated chờ duyệt.',
        ]);

        return $this->outcome(self::RESULT_PROCESSED, $taskId, 'Đã sinh output (output ở trạng thái generated, chờ duyệt).', [
            'output_id'  => $written['output_id'],
            'version_id' => $written['version_id'],
            'asset_id'   => $written['asset_id'],
            'provider'   => $result->provider,
            'model'      => $result->model,
        ]);
    }

    /**
     * Quét một lượt các task ĐẾN HẠN và xử lý tuần tự (dùng cho HTTP cron sẵn có).
     *
     * KHÔNG phải worker nền — đây là một batch đồng bộ có giới hạn, chạy trong
     * request hiện tại, để lại task chưa xử lý cho lần quét sau.
     *
     * @return array<string, mixed>
     */
    public function drain(string $workerId, int $limit = 5): array
    {
        $limit = max(1, $limit);

        $stats = [
            'scanned'  => 0,
            self::RESULT_PROCESSED => 0,
            self::RESULT_RETRY     => 0,
            self::RESULT_FAILED    => 0,
            self::RESULT_SKIPPED   => 0,
            'details'  => [],
        ];

        foreach ($this->dueCandidates($limit) as $candidate) {
            $taskId = (int) ($candidate['id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $stats['scanned']++;
            $outcome = $this->process($taskId, $workerId);
            $status = (string) ($outcome['status'] ?? '');

            if (isset($stats[$status]) && is_int($stats[$status])) {
                $stats[$status]++;
            }

            $stats['details'][] = $outcome;
        }

        return $stats;
    }

    // -----------------------------------------------------------------
    // Nội bộ
    // -----------------------------------------------------------------

    /**
     * Phân xử kết quả video bất đồng bộ (LRO) từ provider.
     *
     * Provider trả HTTP 200 ngay nhưng media có thể CHƯA xong:
     *  - Lượt đầu (chưa có operation id trong params): trích id từ raw
     *    (ResponseParser::extractOperationId). Không trích được → coi như kết quả
     *    đồng bộ ("done") — đi tiếp ghi output như module thường.
     *  - Đã có id + chưa xong: 'wait' (requeue poll) nếu còn trong hạn tổng,
     *    'timeout' nếu vượt hạn; provider báo thất bại rõ ràng → 'fail'.
     *  - Có file media (url/b64) HOẶC raw.status = 'completed' → 'done'.
     *
     * @param array<string, mixed> $options      Params (JSON cột `params`) của task.
     * @param string|int|null     $startedAt    Thời điểm task bắt đầu xử lý LẦN ĐẦU
     *                                          (cột started_at, do claim() ghi 1 lần).
     * @return array{action: 'wait'|'done'|'timeout'|'fail', status: string, params: array<string, mixed>}
     */
    private function resolveVideoLro(AIResult $result, array $options, int|string|null $startedAt): array
    {
        $raw          = is_array($result->raw) ? $result->raw : [];
        $rawStatus    = strtolower(trim((string) ($raw['status'] ?? '')));
        $operationId  = isset($options['kira_operation_id']) && is_string($options['kira_operation_id'])
            ? $options['kira_operation_id']
            : ($this->responses->extractOperationId($result) ?? '');
        $hasMedia     = $result->files !== [];

        // Không xác định được trạng thái bất đồng bộ → kết quả đồng bộ, đi tiếp.
        if ($operationId === '' && $rawStatus === '') {
            return ['action' => 'done', 'status' => '', 'params' => []];
        }

        if ($operationId !== '') {
            $params = $options;
            $params['kira_operation_id'] = $operationId;
            $params['lro_polls'] = (int) ($options['lro_polls'] ?? 0);
        } else {
            // Không có operation id nhưng có raw.status → chỉ quyết định bằng status.
            $params = [];
        }

        // Provider báo THẤT BẠI rõ ràng → kết thúc vĩnh viễn (không retry, không poll).
        if (in_array($rawStatus, self::VIDEO_LRO_FAILED, true)) {
            return ['action' => 'fail', 'status' => $rawStatus, 'params' => $params];
        }

        // Provider báo HOÀN TẤT hoặc đã trả media → xong.
        if ($rawStatus === 'completed' || $hasMedia) {
            return ['action' => 'done', 'status' => $rawStatus, 'params' => $params];
        }

        // CHƯA XONG nhưng KHÔNG có operation id → không thể poll. Coi như kết quả
        // đồng bộ để đi tiếp ghi output (tránh requeue chạy lại pipeline tạo
        // video trùng lặp). KHÔNG fail — provider vẫn trả HTTP 200.
        if ($operationId === '') {
            return ['action' => 'done', 'status' => $rawStatus, 'params' => []];
        }

        // CHƯA XONG (queued/running/processing/...) → kiểm tra hạn tổng rồi requeue.
        $timeout   = (int) ($this->config['task']['video_lro_timeout_seconds'] ?? 1800);
        $startedTs = $startedAt !== '' && $startedAt !== 0 ? strtotime((string) $startedAt) : false;

        // started_at phải được claim() ghi; nếu lạ (false/0) thì coi như vừa bắt đầu.
        $elapsed = $startedTs === false || $startedTs === 0 ? 0 : (time() - $startedTs);

        if ($elapsed > $timeout) {
            return ['action' => 'timeout', 'status' => $rawStatus, 'params' => $params];
        }

        $params['lro_polls'] = (int) ($params['lro_polls'] ?? 0) + 1;

        return ['action' => 'wait', 'status' => $rawStatus, 'params' => $params];
    }

    /**
     * Ghi output + version (append-only) cho một task đã có kết quả AI.
     *
     * Idempotent với crash recovery: nếu output của task đã tồn tại (lượt trước
     * chết sau khi createOutput), tái sử dụng thay vì tạo trùng.
     *
     * @return array{output_id: int, version_id: int, asset_id: ?int}
     */
    private function writeOutput(int $taskId, int $moduleId, string $outputType, AIResult $result): array
    {
        if ($moduleId <= 0) {
            throw new AIException('Không xác định được module_id để ghi output.', AIException::CONFIG);
        }

        $outputId = $this->existingOutputId($taskId);
        if ($outputId === null) {
            $outputId = $this->output->createOutput($taskId, $moduleId, $outputType, null);
        }

        $assetKind = in_array($outputType, ['image', 'video', 'audio'], true) ? $outputType : 'other';

        $version = $this->output->addVersionFromResult($outputId, $result, $assetKind);

        return [
            'output_id'  => $outputId,
            'version_id' => (int) ($version['version_id'] ?? 0),
            'asset_id'   => isset($version['asset_id']) ? (int) $version['asset_id'] : null,
        ];
    }

    /**
     * Xử lý lỗi provider: retry nếu lỗi tạm thời, ngược lại kết thúc 'failed'.
     *
     * @return array<string, mixed>
     */
    private function handleFailure(int $taskId, string $token, int $attempt, AIResult $result): array
    {
        $type    = $result->errorType() ?? AIException::UNKNOWN;
        $message = (string) ($result->errorMessage() ?? 'Lỗi không xác định.');
        $policy  = $this->retry->default();

        // http_status/duration lấy từ context provider ném vào AIResult::error —
        // trước đây không ai truyền meta → 2 cột "Mã HTTP"/"Thời Gian" luôn là "—".
        $errCtx = is_array($result->error) ? $result->error : [];
        $this->tasks->logActivity($taskId, 'provider_error', null, $message, [
            'error_code'  => $type,
            'attempt_no'  => $attempt,
            'http_status' => isset($errCtx['http_status']) ? (int) $errCtx['http_status'] : null,
            'duration_ms' => isset($errCtx['duration_ms']) ? (int) $errCtx['duration_ms'] : null,
        ]);

        $retryable = $this->errors->isRetryable($type, $policy);
        $fatal     = $this->errors->isFatal($type);

        if ($retryable && !$fatal) {
            $scheduled = $this->tasks->scheduleRetry($taskId, $type, $message, $this->retry, $attempt, $token);

            if ($scheduled) {
                return $this->outcome(self::RESULT_RETRY, $taskId, 'Lỗi tạm thời — đã lên lịch retry.', [
                    'error_type' => $type,
                ]);
            }

            // scheduleRetry đã tự finish('failed') khi cạn lượt (hoặc bị chặn bởi fencing).
            return $this->outcome(self::RESULT_FAILED, $taskId, 'Đã cạn lượt retry.', ['error_type' => $type]);
        }

        $this->tasks->finish($taskId, 'failed', [
            'lock_token'    => $token,
            'attempt_no'    => $attempt,
            'duration_ms'   => isset($errCtx['duration_ms']) ? (int) $errCtx['duration_ms'] : null,
            'error_code'    => $type,
            'error_message' => $message,
            'message'       => $fatal
                ? 'Lỗi cấu hình/không thể retry.'
                : 'Lỗi không thể retry.',
        ]);

        return $this->outcome(self::RESULT_FAILED, $taskId, $message, ['error_type' => $type]);
    }

    /**
     * Danh sách task đến hạn (đọc bằng BaseModel::getAll rồi lọc ở PHP — không
     * thêm query builder). Sắp xếp: priority nhỏ trước, cùng priority thì cũ trước.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dueCandidates(int $limit): array
    {
        try {
            $rows = (new \App\Models\AITask())->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $out = [];

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!in_array((string) ($row['status'] ?? ''), self::DUE_STATUSES, true)) {
                continue;
            }

            // Chưa tới hạn retry (so sánh chuỗi 'Y-m-d H:i:s' là đủ và ổn định).
            $retryAfter = (string) ($row['retry_after'] ?? '');
            if ($retryAfter !== '' && $retryAfter > $now) {
                continue;
            }

            $out[] = $row;
        }

        usort($out, static function (array $a, array $b): int {
            $pa = (int) ($a['priority'] ?? 5);
            $pb = (int) ($b['priority'] ?? 5);

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        return array_slice($out, 0, $limit);
    }

    /**
     * Module key của task theo module_id (đọc bảng cấu hình vc_ai_modules).
     */
    private function moduleKeyOf(int $moduleId): ?string
    {
        if ($moduleId <= 0) {
            return null;
        }

        try {
            $row = (new \App\Models\AIModule())->find($moduleId);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $key = (string) ($row['module_key'] ?? '');

        return $key !== '' ? $key : null;
    }

    /**
     * module_id đã lưu của module_key (dùng cho bước REQUEST).
     */
    private function persistedModuleId(string $moduleKey): ?int
    {
        try {
            $row = (new \App\Models\AIModule())->findByKey($moduleKey);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $id = (int) ($row['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * output_id đã tồn tại cho task (idempotent với crash recovery).
     */
    private function existingOutputId(int $taskId): ?int
    {
        try {
            $row = (new \App\Models\AIOutput())->findByTask($taskId);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $id = (int) ($row['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Task còn do token này sở hữu (dùng chính lock_token hiện có, KHÔNG lock mới).
     */
    private function stillOwned(int $taskId, string $token): bool
    {
        return $token !== '' && $this->tasks->isClaimedBy($taskId, $token);
    }

    /**
     * Task ĐÃ tới hạn retry chưa (dùng cho G6).
     *
     * LƯU Ý: KHÔNG thể dựa vào `status` của row đã claim, vì claim() đã đổi
     * status thành 'processing' TRƯỚC khi trả row về. Tín hiệu đáng tin là
     * `retry_after`: chỉ task do scheduleRetry đặt lịch mới có giá trị này, và
     * claim()/finish() KHÔNG xoá nó. Vì vậy: nếu `retry_after` rỗng (task mới,
     * chưa từng retry) → luôn đến hạn; nếu có giá trị → chỉ đến hạn khi
     * `retry_after <= now`. So sánh chuỗi 'Y-m-d H:i:s' (đồng bộ timezone
     * server/DB) là đủ và ổn định.
     *
     * @param array<string, mixed> $claimed
     */
    private function retryDue(array $claimed): bool
    {
        $retryAfter = (string) ($claimed['retry_after'] ?? '');

        return $retryAfter === '' || $retryAfter <= date('Y-m-d H:i:s');
    }

    private function core(): AICore
    {
        if ($this->core === null) {
            $this->core = new AICore($this->config);
        }

        return $this->core;
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

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function outcome(string $status, int $taskId, string $message, array $extra = []): array
    {
        return array_merge([
            'status'  => $status,
            'task_id' => $taskId,
            'message' => $message,
        ], $extra);
    }
}
