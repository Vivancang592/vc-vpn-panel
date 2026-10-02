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
     * Nhật ký của MỘT task — truy vấn WHERE task_id (bảng append-only có thể
     * rất lớn; trước đây getAll() + lọc PHP gây quét toàn bảng mỗi lần xem).
     *
     * @return array<int, array<string, mixed>>
     */
    public function byTask(int $taskId): array
    {
        try {
            $stmt = self::$db->prepare(
                'SELECT * FROM `vc_ai_task_activities` WHERE `task_id` = :task_id ORDER BY `id` ASC'
            );
            $stmt->execute(['task_id' => $taskId]);

            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
