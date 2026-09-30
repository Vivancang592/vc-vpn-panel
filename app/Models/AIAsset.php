<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model: vc_ai_assets — ASSET (metadata file vật lý, KHÔNG chứa blob/base64).
 *
 * File thật nằm trên disk. Model chỉ tra cứu metadata.
 */
class AIAsset extends BaseModel
{
    protected string $table = 'vc_ai_assets';

    /**
     * @return array<string, mixed>|null
     */
    public function findByChecksum(string $checksum): ?array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ((array) $rows as $row) {
            if (is_array($row) && (string) ($row['checksum'] ?? '') === $checksum) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPath(string $relativePath): ?array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ((array) $rows as $row) {
            if (is_array($row) && (string) ($row['relative_path'] ?? '') === $relativePath) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function orphans(): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter((array) $rows, static function ($row): bool {
            return is_array($row) && (int) ($row['is_orphan'] ?? 0) === 1;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byKind(string $kind): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter((array) $rows, static function ($row) use ($kind): bool {
            return is_array($row) && (string) ($row['asset_kind'] ?? '') === $kind;
        }));
    }
}
