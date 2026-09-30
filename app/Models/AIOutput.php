<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model: vc_ai_outputs — OUTPUT (kết quả trừu tượng của task).
 *
 * current_version_id / approved_version_id / final_version_id là con trỏ
 * (chỉ INDEX, không FK). Việc cập nhật con trỏ do OutputHandler thực hiện.
 */
class AIOutput extends BaseModel
{
    protected string $table = 'vc_ai_outputs';

    /**
     * @return array<string, mixed>|null
     */
    public function findByTask(int $taskId): ?array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ((array) $rows as $row) {
            if (is_array($row) && (int) ($row['task_id'] ?? 0) === $taskId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Danh sách BÀI VIẾT AI cho trang /admin/ai/outputs.
     *
     * Mỗi dòng = 1 output văn bản (bài AI đã viết) kèm:
     *  - title  : topic lấy từ payload của task tạo ra bài;
     *  - content: snapshot của version hiện tại (current_version_id);
     *  - trạng thái đăng SUY RA từ vc_scheduled_posts (liên kết qua output_id):
     *      không có dòng hàng đợi  → "Chưa lên lịch"
     *      pending/generating/...  → "Đang lên lịch"
     *      published               → "Đã đăng"
     *
     * @param string $filter '' = tất cả | 'scheduled' = đang lên lịch | 'published' = đã đăng
     * @return array<int, array<string, mixed>>
     */
    public function articleList(string $filter = ''): array
    {
        $where = "WHERE o.`output_type` = 'text'";

        if ($filter === 'scheduled') {
            // Bài có dòng hàng đợi nhưng CHƯA đăng thành công (kể cả lỗi).
            $where .= " AND s.`id` IS NOT NULL AND s.`status` <> 'published'";
        } elseif ($filter === 'published') {
            $where .= " AND s.`status` = 'published'";
        }

        return $this->fetchArticles($where);
    }

    /**
     * Một bài viết duy nhất (chi tiết / lên lịch / xóa).
     *
     * @return array<string, mixed>|null
     */
    public function articleById(int $outputId): ?array
    {
        if ($outputId <= 0) {
            return null;
        }

        $rows = $this->fetchArticles('WHERE o.`id` = :id', ['id' => $outputId]);

        return $rows[0] ?? null;
    }

    /**
     * Truy vấn dùng chung cho danh sách / chi tiết bài viết.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchArticles(string $where, array $params = []): array
    {
        $sql = "
            SELECT
                o.`id`,
                o.`task_id`,
                o.`module_id`,
                o.`output_type`,
                o.`version_count`,
                o.`created_at`,
                o.`updated_at`,
                CASE WHEN t.`payload` IS NOT NULL AND JSON_VALID(t.`payload`)
                     THEN JSON_UNQUOTE(JSON_EXTRACT(t.`payload`, '$.topic'))
                END AS `topic`,
                v.`content_snapshot`,
                s.`id`             AS `schedule_id`,
                s.`status`         AS `schedule_status`,
                s.`scheduled_at`   AS `scheduled_at`,
                s.`image_url`      AS `scheduled_image_url`,
                s.`published_at`   AS `published_at`,
                s.`error_message`  AS `error_message`
            FROM `vc_ai_outputs` o
            LEFT JOIN `vc_ai_tasks` t
                ON t.`id` = o.`task_id`
            LEFT JOIN `vc_ai_output_versions` v
                ON v.`id` = o.`current_version_id`
            LEFT JOIN `vc_scheduled_posts` s
                ON s.`id` = (
                    SELECT s2.`id` FROM `vc_scheduled_posts` s2
                    WHERE s2.`output_id` = o.`id`
                    ORDER BY s2.`id` DESC
                    LIMIT 1
                )
            {$where}
            ORDER BY o.`updated_at` DESC
            LIMIT 100
        ";

        try {
            if ($params !== []) {
                $stmt = self::$db->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = self::$db->query($sql);
            }
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
