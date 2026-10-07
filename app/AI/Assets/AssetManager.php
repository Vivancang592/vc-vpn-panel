<?php

declare(strict_types=1);

namespace App\AI\Assets;

use App\AI\Contracts\AIException;
use App\Models\AIAsset;

/**
 * AssetManager — quản lý file vật lý TẬP TRUNG cho toàn bộ AI Core.
 *
 * Mọi file do AI sinh ra (ảnh/video/audio/khác) BẮT BUỘC đi qua đây.
 * Không module nào được tự ghi file vào public/uploads.
 *
 * Nguyên tắc:
 *  - KHÔNG lưu blob / base64 vào database. DB chỉ giữ metadata + relative_path.
 *  - Mỗi asset có checksum SHA-256 để phát hiện trùng.
 *  - Asset có ref_count / is_orphan nhưng KHÔNG tự xoá file (chưa có cleanup job).
 *  - Đường dẫn lưu luôn là tương đối (relative) để không phụ thuộc host.
 */
final class AssetManager
{
    /** @var array<string, mixed> */
    private array $config;

    private string $rootPath;

    /**
     * @param array<string, mixed> $config Config AI (config/ai.php)
     * @param string|null $rootPath Thư mục gốc project
     */
    public function __construct(array $config = [], ?string $rootPath = null)
    {
        $this->config = $config;
        $this->rootPath = rtrim($rootPath ?? dirname(__DIR__, 3), '/\\');
    }

    /**
     * Lưu nội dung binary thô thành asset.
     *
     * @param array<string, mixed> $meta
     * @param string $subdir Thư mục con riêng (vd: tab 'dubbing') — chỉ [a-z0-9_-].
     * @return array<string, mixed> Thông tin asset (id, relative_path, url, ...)
     */
    public function saveContent(
        string $binary,
        string $kind,
        ?string $originalName = null,
        ?string $mimeType = null,
        array $meta = [],
        ?int $createdBy = null,
        string $subdir = ''
    ): array {
        if ($binary === '') {
            throw new AIException('Nội dung file rỗng, không thể lưu asset.', AIException::VALIDATION);
        }

        $this->assertKind($kind);

        $mimeType = $mimeType ?? $this->detectMimeTypeFromBinary($binary);
        $extension = $this->extensionFor($kind, $mimeType);

        $relativePath = $this->buildRelativePath($kind, $extension, $subdir);
        $absolutePath = $this->absolutePath($relativePath);

        $this->ensureDirectory(dirname($absolutePath));

        if (@file_put_contents($absolutePath, $binary) === false) {
            throw new AIException(
                'Không ghi được file asset: ' . $relativePath,
                AIException::UNKNOWN,
                null,
                ['relative_path' => $relativePath]
            );
        }

        return $this->register(
            $kind,
            $relativePath,
            $originalName,
            $mimeType,
            strlen($binary),
            hash('sha256', $binary),
            $meta,
            $createdBy
        );
    }

    /**
     * Tải nội dung từ URL (media provider trả về) và lưu thành asset.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function saveFromUrl(
        string $url,
        string $kind,
        ?string $originalName = null,
        array $meta = [],
        ?int $createdBy = null,
        string $subdir = ''
    ): array {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new AIException('URL không hợp lệ: ' . substr($url, 0, 200), AIException::VALIDATION);
        }

        [$binary, $mimeType] = $this->download($url);

        return $this->saveContent($binary, $kind, $originalName ?? basename(parse_url($url, PHP_URL_PATH) ?: 'asset'), $mimeType, $meta, $createdBy, $subdir);
    }

    /**
     * Lưu nội dung base64 (media provider trả b64_json) thành asset.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function saveFromBase64(
        string $base64,
        string $kind,
        ?string $originalName = null,
        array $meta = [],
        ?int $createdBy = null,
        string $subdir = ''
    ): array {
        $clean = preg_replace('#^data:[^;]+;base64,#', '', $base64) ?? '';
        $binary = base64_decode($clean, true);

        if ($binary === false || $binary === '') {
            throw new AIException('Dữ liệu base64 không hợp lệ.', AIException::VALIDATION);
        }

        return $this->saveContent($binary, $kind, $originalName, null, $meta, $createdBy, $subdir);
    }

    /**
     * Tăng ref_count khi asset được tham chiếu (output/video/post...).
     */
    public function attach(int $assetId): void
    {
        $this->touchRefCount($assetId, 1);
    }

    /**
     * Giảm ref_count khi asset bị bỏ tham chiếu.
     * KHÔNG xoá file vật lý (chưa có cleanup job ở Phase 5).
     */
    public function detach(int $assetId): void
    {
        $this->touchRefCount($assetId, -1);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $assetId): ?array
    {
        try {
            $row = (new AIAsset())->find($assetId);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * URL công khai tương ứng với đường dẫn tương đối.
     */
    public function urlFor(string $relativePath): string
    {
        $baseUrl = rtrim((string) ($this->config['assets']['base_url'] ?? '/uploads/ai'), '/');
        $sub = ltrim($this->stripBasePath($relativePath), '/');

        return $baseUrl . '/' . $sub;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function download(string $url): array
    {
        $binary = null;
        $mimeType = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            $data = curl_exec($ch);
            $mimeType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $errno = curl_errno($ch);
            curl_close($ch);

            if (is_string($data)) {
                $binary = $data;
            } elseif ($errno !== 0) {
                throw new AIException('Tải media thất bại từ URL (curl errno ' . $errno . ').', AIException::TIMEOUT);
            }
        } else {
            $data = @file_get_contents($url);
            if (is_string($data)) {
                $binary = $data;
            }
        }

        if (!is_string($binary) || $binary === '') {
            throw new AIException('Không tải được media từ URL.', AIException::UNKNOWN);
        }

        return [$binary, $mimeType !== '' ? $mimeType : null];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function register(
        string $kind,
        string $relativePath,
        ?string $originalName,
        ?string $mimeType,
        int $sizeBytes,
        string $checksum,
        array $meta,
        ?int $createdBy
    ): array {
        $record = [
            'asset_kind'    => $kind,
            'relative_path' => $relativePath,
            'original_name' => $originalName,
            'mime_type'     => $mimeType,
            'size_bytes'    => $sizeBytes,
            'checksum'      => $checksum,
            'storage_disk'  => (string) ($this->config['assets']['disk'] ?? 'public'),
            'ref_count'     => 0,
            'is_orphan'     => 0,
            'metadata'      => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by'    => $createdBy,
        ];

        $model = new AIAsset();

        if (!$model->create($record)) {
            throw new AIException('Không tạo được bản ghi asset.', AIException::UNKNOWN);
        }

        $id = $model->lastInsertId();

        return [
            'id'            => $id,
            'asset_kind'    => $kind,
            'relative_path' => $relativePath,
            'absolute_path' => $this->absolutePath($relativePath),
            'url'           => $this->urlFor($relativePath),
            'mime_type'     => $mimeType,
            'size_bytes'    => $sizeBytes,
            'checksum'      => $checksum,
        ];
    }

    private function touchRefCount(int $assetId, int $delta): void
    {
        if ($assetId <= 0) {
            return;
        }

        try {
            $model = new AIAsset();
            $row = $model->find($assetId);
            if (!is_array($row)) {
                return;
            }

            $newCount = max(0, (int) ($row['ref_count'] ?? 0) + $delta);

            $model->update($assetId, [
                'ref_count' => $newCount,
                'is_orphan' => $newCount === 0 ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            // Không để lỗi đếm tham chiếu làm hỏng luồng nghiệp vụ.
        }
    }

    private function assertKind(string $kind): void
    {
        $allowed = (array) ($this->config['assets']['allowed_kinds'] ?? ['image', 'video', 'audio', 'other']);

        if (!in_array($kind, $allowed, true)) {
            throw new AIException(
                'Loại asset không được phép: ' . $kind,
                AIException::VALIDATION,
                null,
                ['asset_kind' => $kind]
            );
        }
    }

    private function buildRelativePath(string $kind, string $extension, string $subdir = ''): string
    {
        $base = trim((string) ($this->config['assets']['base_path'] ?? 'public/uploads/ai'), '/');
        $date = date('Y/m/d');
        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        // Thư mục con riêng theo tab (chỉ [a-z0-9_-], tránh traversal).
        $sub = preg_match('/^[a-z0-9_-]{1,24}$/', $subdir) === 1 ? '/' . $subdir : '';

        return $base . '/' . $kind . $sub . '/' . $date . '/' . $fileName;
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function stripBasePath(string $relativePath): string
    {
        $base = trim((string) ($this->config['assets']['base_path'] ?? 'public/uploads/ai'), '/');
        $normalized = str_replace('\\', '/', $relativePath);

        if (str_starts_with($normalized, $base . '/')) {
            return substr($normalized, strlen($base) + 1);
        }

        return $normalized;
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function detectMimeTypeFromBinary(string $binary): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_buffer($finfo, $binary);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    private function extensionFor(string $kind, ?string $mimeType): string
    {
        // Chỉ suy extension từ MIME map — không tin tên file gốc để tránh lưu script vào webroot.
        $map = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'image/gif'       => 'gif',
            'video/mp4'       => 'mp4',
            'video/webm'      => 'webm',
            'video/quicktime' => 'mov',
            'audio/mpeg'      => 'mp3',
            'audio/mp3'       => 'mp3',
            'audio/wav'       => 'wav',
            'audio/ogg'       => 'ogg',
            'audio/aac'       => 'aac',
        ];

        if ($mimeType !== null && isset($map[strtolower($mimeType)])) {
            return $map[strtolower($mimeType)];
        }

        return match ($kind) {
            'image' => 'png',
            'video' => 'mp4',
            'audio' => 'mp3',
            default => 'bin',
        };
    }
}
