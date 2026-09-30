<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model: vc_ai_task_activities — ACTIVITY (nhật ký tiến trình, append-only).
 */
class AITaskActivity extends BaseModel
{
    protected string $table = 'vc_ai_task_activities';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byTask(int $taskId): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        $filtered = array_values(array_filter((array) $rows, static function ($row) use ($taskId): bool {
            return is_array($row) && (int) ($row['task_id'] ?? 0) === $taskId;
        }));

        usort($filtered, static fn(array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));

        return $filtered;
    }
}
