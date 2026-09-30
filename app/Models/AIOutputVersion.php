<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model: vc_ai_output_versions — OUTPUT VERSION (append-only).
 *
 * KHÔNG xoá version. KHÔNG sửa content_snapshot của version đã tạo.
 */
class AIOutputVersion extends BaseModel
{
    protected string $table = 'vc_ai_output_versions';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byOutput(int $outputId): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        $filtered = array_values(array_filter((array) $rows, static function ($row) use ($outputId): bool {
            return is_array($row) && (int) ($row['output_id'] ?? 0) === $outputId;
        }));

        usort($filtered, static fn(array $a, array $b): int => (int) ($a['version_no'] ?? 0) <=> (int) ($b['version_no'] ?? 0));

        return $filtered;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentOf(int $outputId): ?array
    {
        foreach ($this->byOutput($outputId) as $row) {
            if ((int) ($row['is_current'] ?? 0) === 1) {
                return $row;
            }
        }

        return null;
    }

    public function nextVersionNo(int $outputId): int
    {
        $max = 0;

        foreach ($this->byOutput($outputId) as $row) {
            $max = max($max, (int) ($row['version_no'] ?? 0));
        }

        return $max + 1;
    }
}
