<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Assets\AssetManager;
use App\AI\Contracts\AIException;
use App\AI\Contracts\AIResult;

/**
 * OutputHandler — quản lý vòng đời output & version.
 *
 * Quy tắc bất biến:
 *  - Version là APPEND-ONLY: không bao giờ ghi đè, không bao giờ xoá.
 *  - Mỗi lần tạo bản mới → version_no tăng, previous_version_id trỏ bản trước.
 *  - Chỉ MỘT version có is_current = 1 tại mỗi thời điểm.
 *  - is_approved / is_final do luồng review quyết định (ở phase sau).
 *  - Đường dẫn file luôn đi qua AssetManager, không ghi trực tiếp.
 */
final class OutputHandler
{
    private AssetManager $assets;

    public function __construct(?AssetManager $assets = null)
    {
        $this->assets = $assets ?? new AssetManager();
    }

    /**
     * Tạo output mới gắn với task.
     *
     * @return int output id
     */
    public function createOutput(int $taskId, int $moduleId, string $outputType, ?int $createdBy = null): int
    {
        $model = new \App\Models\AIOutput();

        $ok = $model->create([
            'task_id'      => $taskId,
            'module_id'    => $moduleId,
            'output_type'  => $outputType,
            'review_status' => 'generated',
            'version_count' => 0,
            'created_by'   => $createdBy,
        ]);

        if (!$ok) {
            throw new AIException('Không tạo được bản ghi output.', AIException::UNKNOWN, null, ['task_id' => $taskId]);
        }

        return $model->lastInsertId();
    }

    /**
     * Thêm một version mới cho output (append-only).
     *
     * @param array<string, mixed> $data
     * @return array{version_id: int, version_no: int, asset_id: ?int}
     */
    public function addVersion(int $outputId, array $data): array
    {
        $outputModel = new \App\Models\AIOutput();
        $output = $outputModel->find($outputId);

        if (!is_array($output)) {
            throw new AIException('Output không tồn tại: ' . $outputId, AIException::NOT_FOUND);
        }

        $previousVersionId = $this->currentVersionId($outputId);
        $versionNo = $this->nextVersionNo($outputId);

        $assetId = null;

        // Nếu version có media → lưu tập trung qua AssetManager.
        $asset = $data['asset'] ?? null;
        if (is_array($asset) && $asset !== []) {
            $assetId = $this->storeAsset($asset);
        }

        $versionModel = new \App\Models\AIOutputVersion();

        $ok = $versionModel->create([
            'output_id'           => $outputId,
            'version_no'          => $versionNo,
            'previous_version_id' => $previousVersionId,
            'content_snapshot'    => $data['content'] ?? null,
            'asset_id'            => $assetId,
            'provider'            => $data['provider'] ?? null,
            'model'               => $data['model'] ?? null,
            'review_status'       => $data['review_status'] ?? 'generated',
            'is_current'          => 1,
            'is_approved'         => 0,
            'is_final'            => 0,
            'requested_by'        => $data['requested_by'] ?? null,
            'meta'                => !empty($data['meta'])
                ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
        ]);

        if (!$ok) {
            throw new AIException('Không tạo được output version.', AIException::UNKNOWN, null, ['output_id' => $outputId]);
        }

        $versionId = $versionModel->lastInsertId();

        // Đảm bảo chỉ một version là current.
        $this->markOnlyCurrent($outputId, $versionId);

        // Cập nhật con trỏ + bộ đếm trên output (chỉ INDEX, không FK).
        $outputModel->update($outputId, [
            'current_version_id' => $versionId,
            'version_count'      => (int) ($output['version_count'] ?? 0) + 1,
        ]);

        return [
            'version_id' => $versionId,
            'version_no' => $versionNo,
            'asset_id'   => $assetId,
        ];
    }

    /**
     * Thêm version từ một AIResult (text hoặc media).
     *
     * @param array<string, mixed> $extra
     * @return array{version_id: int, version_no: int, asset_id: ?int}
     */
    public function addVersionFromResult(int $outputId, AIResult $result, string $kind, array $extra = []): array
    {
        $data = array_merge([
            'content'  => $result->content,
            'provider' => $result->provider,
            'model'    => $result->model,
        ], $extra);

        // Media: ưu tiên URL, sau đó base64. Luôn qua AssetManager.
        $file = $result->files[0] ?? null;
        if (is_array($file)) {
            if (!empty($file['url']) && is_string($file['url'])) {
                $data['asset'] = ['url' => $file['url'], 'kind' => $kind];
            } elseif (!empty($file['b64_json']) && is_string($file['b64_json'])) {
                $data['asset'] = ['base64' => $file['b64_json'], 'kind' => $kind];
            }
        }

        return $this->addVersion($outputId, $data);
    }

    /**
     * Ghi nhận review cho version (không xoá version cũ).
     */
    public function review(int $outputId, int $versionId, string $status, ?string $note = null, ?int $reviewedBy = null): void
    {
        $versionModel = new \App\Models\AIOutputVersion();

        $versionModel->update($versionId, [
            'review_status' => $status,
            'review_note'   => $note,
            'reviewed_by'   => $reviewedBy,
            'reviewed_at'   => date('Y-m-d H:i:s'),
        ]);

        $outputModel = new \App\Models\AIOutput();
        $patch = ['review_status' => $status];

        if ($status === 'approved') {
            $patch['approved_version_id'] = $versionId;
            $versionModel->update($versionId, ['is_approved' => 1]);
        }

        if ($status === 'completed') {
            $patch['final_version_id'] = $versionId;
            $versionModel->update($versionId, ['is_final' => 1]);
        }

        $outputModel->update($outputId, $patch);
    }

    public function currentVersionId(int $outputId): ?int
    {
        $row = $this->findVersionBy($outputId, 'is_current', 1);

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentVersion(int $outputId): ?array
    {
        return $this->findVersionBy($outputId, 'is_current', 1);
    }

    public function nextVersionNo(int $outputId): int
    {
        $max = 0;

        foreach ($this->versionsOf($outputId) as $row) {
            $max = max($max, (int) ($row['version_no'] ?? 0));
        }

        return $max + 1;
    }

    /**
     * Danh sách version của output, sắp xếp theo version_no.
     *
     * @return array<int, array<string, mixed>>
     */
    public function versionsOf(int $outputId): array
    {
        try {
            $rows = (new \App\Models\AIOutputVersion())->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        $filtered = [];
        foreach ((array) $rows as $row) {
            if (is_array($row) && (int) ($row['output_id'] ?? 0) === $outputId) {
                $filtered[] = $row;
            }
        }

        usort($filtered, static fn(array $a, array $b): int => (int) $a['version_no'] <=> (int) $b['version_no']);

        return $filtered;
    }

    /**
     * Chỉ giữ duy nhất $keepVersionId có is_current = 1.
     */
    private function markOnlyCurrent(int $outputId, int $keepVersionId): void
    {
        $model = new \App\Models\AIOutputVersion();

        foreach ($this->versionsOf($outputId) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id === $keepVersionId) {
                continue;
            }

            if ((int) ($row['is_current'] ?? 0) === 1) {
                $model->update($id, ['is_current' => 0]);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findVersionBy(int $outputId, string $column, mixed $value): ?array
    {
        foreach ($this->versionsOf($outputId) as $row) {
            if (($row[$column] ?? null) == $value) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $asset
     */
    private function storeAsset(array $asset): int
    {
        $kind = (string) ($asset['kind'] ?? 'other');

        if (!empty($asset['base64']) && is_string($asset['base64'])) {
            $saved = $this->assets->saveFromBase64($asset['base64'], $kind, $asset['original_name'] ?? null, $asset['meta'] ?? []);
        } elseif (!empty($asset['url']) && is_string($asset['url'])) {
            $saved = $this->assets->saveFromUrl($asset['url'], $kind, $asset['original_name'] ?? null, $asset['meta'] ?? []);
        } elseif (!empty($asset['content']) && is_string($asset['content'])) {
            $saved = $this->assets->saveContent($asset['content'], $kind, $asset['original_name'] ?? null, $asset['mime_type'] ?? null, $asset['meta'] ?? []);
        } else {
            throw new AIException('Dữ liệu asset không hợp lệ (thiếu url/base64/content).', AIException::VALIDATION);
        }

        $assetId = (int) ($saved['id'] ?? 0);
        $this->assets->attach($assetId);

        return $assetId;
    }
}
