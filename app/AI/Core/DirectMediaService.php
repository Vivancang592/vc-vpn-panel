<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Assets\AssetManager;
use App\AI\Contracts\AIResult;

/**
 * DirectMediaService — chạy TRỰC TIẾP media (ảnh) khi admin bấm nút,
 * KHÔNG đi qua hàng đợi vc_ai_tasks (TaskRunner chỉ dùng cho luồng
 * content/queue của Fanpage & tasks kỹ thuật).
 *
 * Tab Tạo Ảnh dùng service này:
 *   image → uploads/ai/image/image/<date>/  (kind=image, subdir=image)
 * (metadata['tab'] đánh dấu chủ sở hữu để lọc gallery trong từng tab).
 */
final class DirectMediaService
{
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
        $subdir = preg_match('/^[a-z0-9_-]{1,24}$/', $tab) === 1 ? $tab : '';

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
     * Ghi các option admin chọn vào metadata asset (size/model...).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function metaFromOptions(array $options): array
    {
        $meta = [];
        foreach (['size', 'model'] as $key) {
            if (isset($options[$key]) && $options[$key] !== '' && $options[$key] !== null) {
                $meta[$key] = $options[$key];
            }
        }

        return $meta;
    }

    private static function kindForTab(string $tab): string
    {
        return $tab === 'image' ? 'image' : 'other';
    }

    private function rootPath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }
}
