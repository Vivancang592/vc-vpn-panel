<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Assets\AssetManager;
use App\AI\Contracts\AIResult;

/**
 * DirectMediaService — chạy TRỰC TIẾP media (ảnh/video/audio) khi admin bấm
 * nút, KHÔNG đi qua hàng đợi vc_ai_tasks (TaskRunner chỉ dùng cho luồng
 * content/queue của Fanpage & tasks kỹ thuật).
 *
 * Ba tab dùng service này: Tạo Ảnh (image), Tạo Video (video), Lời Thoại
 * (dubbing). Mỗi tab có THƯ MỤC LƯU TRỮ RIÊNG dưới public/uploads/ai:
 *   image   → uploads/ai/image/image/<date>/  (kind=image, subdir=image)
 *   video   → uploads/ai/video/<date>/         (kind=video — đã tách sẵn)
 *   dubbing → uploads/ai/audio/dubbing/<date>/ (kind=audio, subdir=dubbing)
 * (metadata['tab'] đánh dấu chủ sở hữu để lọc gallery trong từng tab).
 *
 * Video là LRO (asynchronous): start() trả operation id → poll() lặp tới khi
 * completed (giống resolveVideoLro của TaskRunner nhưng chạy đồng bộ trong
 * request của admin).
 */
final class DirectMediaService
{
    /** Trạng thái LRO video coi là THẤT BẠI (khớp TaskRunner::VIDEO_LRO_FAILED). */
    private const VIDEO_LRO_FAILED = ['failed', 'error', 'cancelled', 'canceled', 'expired'];

    /** @var array<string, mixed> */
    private array $config;

    private AICore $core;

    private AssetManager $assets;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->core = new AICore($config);
        $this->assets = new AssetManager($config);
    }

    // ------------------------------------------------------------------
    // CHẠY TRỰC TIẾP (đồng bộ)
    // ------------------------------------------------------------------

    /**
     * Tạo ẢNH ngay khi bấm nút — module image_generation (IMAGE).
     *
     * @param array<string, mixed> $options size / model (chỉ truyền khi có)
     * @return array{ok: bool, asset: ?array<string, mixed>, error: ?string, model: ?string}
     */
    public function generateImage(string $prompt, array $options = [], ?int $userId = null): array
    {
        $result = $this->core->run('image_generation', ['prompt' => $prompt], $options);

        return $this->persistResult($result, 'image', ['tab' => 'image', 'prompt' => $prompt], $userId, $options);
    }

    /**
     * Tạo LỜI THOẠI (TTS) ngay khi bấm nút — module audio_tts (AUDIO).
     *
     * @param array<string, mixed> $options voice / model
     * @return array{ok: bool, asset: ?array<string, mixed>, error: ?string, model: ?string}
     */
    public function generateDubbing(string $text, array $options = [], ?int $userId = null): array
    {
        $result = $this->core->run('audio_tts', ['text' => $text], $options);

        return $this->persistResult($result, 'audio', ['tab' => 'dubbing', 'prompt' => $text], $userId, $options);
    }

    /**
     * BẮT ĐẦU tạo VIDEO (LRO) — trả về operation id để poll.
     *
     * @param array<string, mixed> $options aspect_ratio / duration_seconds / model
     * @return array{ok: bool, operation_id: ?string, pending: bool, asset: ?array<string, mixed>, error: ?string, model: ?string}
     */
    public function startVideo(string $prompt, array $options = [], ?int $userId = null): array
    {
        $result = $this->core->run('video_generation', ['prompt' => $prompt], $options);

        if (!$result->isOk()) {
            return [
                'ok' => false, 'operation_id' => null, 'pending' => false,
                'asset' => null, 'error' => $result->errorMessage() ?? 'Không khởi tạo được video.',
                'model' => $result->model,
            ];
        }

        // Đồng bộ (provider trả media ngay) → lưu luôn.
        if ($result->files !== []) {
            $saved = $this->saveResultFiles($result, 'video', ['tab' => 'video', 'prompt' => $prompt] + $this->metaFromOptions($options), $userId);
            if ($saved !== null) {
                return ['ok' => true, 'operation_id' => null, 'pending' => false, 'asset' => $saved, 'error' => null, 'model' => $result->model];
            }
        }

        // LRO: trích operation id để admin poll (kết thúc ở poll()).
        $opId = (new ResponseParser())->extractOperationId($result);
        if ($opId === null || $opId === '') {
            return [
                'ok' => false, 'operation_id' => null, 'pending' => false,
                'asset' => null, 'error' => 'Provider không trả về operation id (không poll được).',
                'model' => $result->model,
            ];
        }

        return ['ok' => true, 'operation_id' => $opId, 'pending' => true, 'asset' => null, 'error' => null, 'model' => $result->model];
    }

    /**
     * POLL trạng thái video LRO → ['state' => running|done|failed, asset?, error?].
     *
     * @param array<string, mixed> $extraMeta metadata bổ sung khi lưu (prompt/options từ session job)
     * @return array{state: string, asset: ?array<string, mixed>, error: ?string}
     */
    public function pollVideo(string $operationId, ?int $userId = null, array $extraMeta = []): array
    {
        $result = $this->core->videoStatus($operationId);
        $raw = is_array($result->raw) ? $result->raw : [];
        $status = strtolower(trim((string) ($raw['status'] ?? '')));

        // Provider báo thất bại rõ ràng → dừng (không poll tiếp).
        if (in_array($status, self::VIDEO_LRO_FAILED, true)) {
            return ['state' => 'failed', 'asset' => null, 'error' => 'Provider báo video thất bại (' . $status . ').'];
        }

        // Đã có media hoặc báo completed → lưu file vào thư mục tab video.
        if ($result->files !== [] || $status === 'completed') {
            if ($result->files === []) {
                return ['state' => 'failed', 'asset' => null, 'error' => 'Provider báo completed nhưng không trả file media.'];
            }
            $saved = $this->saveResultFiles($result, 'video', ['tab' => 'video'] + $extraMeta, $userId);
            if ($saved !== null) {
                return ['state' => 'done', 'asset' => $saved, 'error' => null];
            }
            return ['state' => 'failed', 'asset' => null, 'error' => 'Không lưu được file video.'];
        }

        // Chưa xong (queued/running/...) → poll tiếp.
        return ['state' => 'running', 'asset' => null, 'error' => null];
    }

    // ------------------------------------------------------------------
    // THƯ MỤC LƯU TRỮ THEO TAB (hiển thị ngay trong tab)
    // ------------------------------------------------------------------

    /**
     * Danh sách asset của MỘT tab (lọc metadata.tab), mới nhất trước.
     *
     * @return array<int, array<string, mixed>> mỗi phần tử: row asset + '_url', '_meta'
     */
    public function listByTab(string $tab, ?string $kind = null, int $limit = 60): array
    {
        $kind = $kind !== null && $kind !== '' ? $kind : self::kindForTab($tab);

        try {
            $rows = (new \App\Models\AIAsset())->byKind($kind);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = json_decode((string) ($row['metadata'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            if ((string) ($meta['tab'] ?? '') !== $tab) {
                continue;
            }
            $row['_meta'] = $meta;
            $row['_url'] = $this->assets->urlFor((string) ($row['relative_path'] ?? ''));
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Xoá BẤT KỲ asset nào của admin (xem lại/xóa bất cứ lúc nào) — cả khi
     * ref_count > 0 hay không phải orphan: unlink file vật lý + xoá bản ghi.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function forceDelete(int $assetId): array
    {
        $model = new \App\Models\AIAsset();
        try {
            $row = $model->find($assetId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Không đọc được asset.'];
        }

        if (!is_array($row)) {
            return ['ok' => false, 'error' => 'Asset không tồn tại.'];
        }

        // File vật lý (best-effort — file có thể đã bị xoá tay).
        $abs = rtrim($this->rootPath(), '/\\') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, (string) ($row['relative_path'] ?? ''));
        if (is_file($abs)) {
            @unlink($abs);
        }

        if (!$model->delete($assetId)) {
            return ['ok' => false, 'error' => 'Không xoá được bản ghi asset.'];
        }

        return ['ok' => true, 'error' => null];
    }

    // ------------------------------------------------------------------
    // Nội bộ
    // ------------------------------------------------------------------

    /**
     * Lưu các file trong AIResult vào thư mục tab → asset row (hoặc null nếu lỗi).
     *
     * @param array<string, mixed> $extraMeta
     * @return array<string, mixed>|null
     */
    private function saveResultFiles(AIResult $result, string $kind, array $extraMeta, ?int $userId): ?array
    {
        $files = is_array($result->files) ? $result->files : [];
        if ($files === []) {
            return null;
        }

        $tab = (string) ($extraMeta['tab'] ?? '');
        $subdir = preg_match('/^[a-z0-9_-]{1,24}$/', $tab) === 1 && $tab !== 'video' ? $tab : '';

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }
            try {
                if (!empty($file['b64_json']) && is_string($file['b64_json'])) {
                    return $this->assets->saveFromBase64(
                        $file['b64_json'], $kind, null, $extraMeta, $userId, $subdir
                    );
                }
                if (!empty($file['url']) && is_string($file['url'])) {
                    return $this->assets->saveFromUrl(
                        $file['url'], $kind, null, $extraMeta, $userId, $subdir
                    );
                }
            } catch (\Throwable $e) {
                continue; // thử file kế tiếp
            }
        }

        return null;
    }

    /**
     * Gộp result → mảng trả về thống nhất cho controller/view.
     *
     * @param array<string, mixed> $meta metadata asset (luôn chứa 'tab')
     * @param array<string, mixed> $options
     * @return array{ok: bool, asset: ?array<string, mixed>, error: ?string, model: ?string}
     */
    private function persistResult(AIResult $result, string $kind, array $meta, ?int $userId, array $options): array
    {
        if (!$result->isOk()) {
            return ['ok' => false, 'asset' => null, 'error' => $result->errorMessage() ?? 'AI Core xử lý thất bại.', 'model' => $result->model];
        }

        $saved = $this->saveResultFiles($result, $kind, $meta + $this->metaFromOptions($options), $userId);

        if ($saved === null) {
            return ['ok' => false, 'asset' => null, 'error' => 'AI trả về nhưng không lưu được file.', 'model' => $result->model];
        }

        return ['ok' => true, 'asset' => $saved, 'error' => null, 'model' => $result->model];
    }

    /**
     * Ghi các option admin chọn vào metadata asset (size/voice/aspect...).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function metaFromOptions(array $options): array
    {
        $meta = [];
        foreach (['size', 'voice', 'aspect_ratio', 'duration_seconds', 'model'] as $key) {
            if (isset($options[$key]) && $options[$key] !== '' && $options[$key] !== null) {
                $meta[$key] = $options[$key];
            }
        }

        return $meta;
    }

    private static function kindForTab(string $tab): string
    {
        return match ($tab) {
            'image' => 'image',
            'video' => 'video',
            default => 'audio',
        };
    }

    private function rootPath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }
}
