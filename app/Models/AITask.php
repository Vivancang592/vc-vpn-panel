<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model: vc_ai_tasks — TASK (đơn vị công việc AI).
 *
 * Model này chỉ truy vấn dữ liệu. Việc claim/lock (nguyên tử) nằm ở
 * App\AI\Core\TaskDispatcher (dùng UPDATE có điều kiện).
 */
class AITask extends BaseModel
{
    protected string $table = 'vc_ai_tasks';

    /**
     * Xóa task đã kết thúc sau thời gian lưu giữ; activity tự xóa theo FK.
     * Output vẫn được giữ, với task_id chuyển thành NULL.
     */
    public function pruneFinishedOlderThan(int $hours): int
    {
        $hours = max(1, $hours);
        $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));

        $stmt = self::$db->prepare(
            "DELETE FROM `{$this->table}`
             WHERE `status` IN ('completed', 'failed', 'cancelled')
               AND `finished_at` IS NOT NULL
               AND `finished_at` < :cutoff"
        );
        $stmt->execute(['cutoff' => $cutoff]);

        return $stmt->rowCount();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $key): ?array
    {
        try {
            $rows = $this->getAll();
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
     * @param array<int, string> $statuses
     * @return array<int, array<string, mixed>>
     */
    public function byStatus(array $statuses): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter((array) $rows, static function ($row) use ($statuses): bool {
            return is_array($row) && in_array((string) ($row['status'] ?? ''), $statuses, true);
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byModule(int $moduleId): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter((array) $rows, static function ($row) use ($moduleId): bool {
            return is_array($row) && (int) ($row['module_id'] ?? 0) === $moduleId;
        }));
    }
}
