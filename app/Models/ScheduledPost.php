<?php

namespace App\Models;

class ScheduledPost extends BaseModel
{
    protected string $table = 'vc_scheduled_posts';

    /**
     * Lấy danh sách tất cả bài đăng theo phân trang / thứ tự mới nhất
     */
    public function getAllWithAuthor(int $limit = 50, int $offset = 0): array
    {
        $stmt = self::$db->prepare("
            SELECT sp.*, u.username AS author_name
            FROM `{$this->table}` sp
            LEFT JOIN `vc_users` u ON sp.created_by = u.id
            ORDER BY sp.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy các bài viết đến hạn xử lý (status = 'pending' và scheduled_at <= NOW())
     */
    public function getPendingQueue(int $limit = 5): array
    {
        $stmt = self::$db->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `status` = 'pending' 
              AND `scheduled_at` <= NOW()
              AND `retry_count` < 3
            ORDER BY `scheduled_at` ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Đánh dấu bài viết bắt đầu sinh dữ liệu / khóa tránh xử lý trùng
     */
    public function markAsGenerating(int $id): bool
    {
        $stmt = self::$db->prepare("
            UPDATE `{$this->table}`
            SET `status` = 'generating', `updated_at` = NOW()
            WHERE `id` = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Đánh dấu bài viết đã đăng thành công lên Facebook
     */
    public function markAsPublished(int $id, string $facebookPostId, ?string $generatedContent = null, ?string $imageUrl = null, ?array $metaData = null): bool
    {
        $fields = [
            '`status` = :status',
            '`published_at` = NOW()',
            '`facebook_post_id` = :facebook_post_id',
            '`error_message` = NULL',
            '`updated_at` = NOW()'
        ];
        $params = [
            'id' => $id,
            'status' => 'published',
            'facebook_post_id' => $facebookPostId
        ];

        if ($generatedContent !== null) {
            $fields[] = '`generated_content` = :generated_content';
            $params['generated_content'] = $generatedContent;
        }
        if ($imageUrl !== null) {
            $fields[] = '`image_url` = :image_url';
            $params['image_url'] = $imageUrl;
        }
        if ($metaData !== null) {
            $fields[] = '`meta_data` = :meta_data';
            $params['meta_data'] = json_encode($metaData, JSON_UNESCAPED_UNICODE);
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Đánh dấu bài viết bị lỗi và tăng retry_count
     */
    public function markAsFailed(int $id, string $errorMessage): bool
    {
        $stmt = self::$db->prepare("
            UPDATE `{$this->table}`
            SET `status` = 'failed',
                `retry_count` = `retry_count` + 1,
                `error_message` = :error_message,
                `updated_at` = NOW()
            WHERE `id` = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Cập nhật lại trạng thái thành pending để thử lại
     */
    public function retry(int $id): bool
    {
        $stmt = self::$db->prepare("
            UPDATE `{$this->table}`
            SET `status` = 'pending',
                `retry_count` = 0,
                `error_message` = NULL,
                `updated_at` = NOW()
            WHERE `id` = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Đếm tổng số bài theo từng trạng thái
     */
    public function getCountsByStatus(): array
    {
        $stmt = self::$db->query("
            SELECT `status`, COUNT(*) as `total`
            FROM `{$this->table}`
            GROUP BY `status`
        ");
        $results = $stmt->fetchAll() ?: [];
        $counts = [
            'all' => 0,
            'pending' => 0,
            'generating' => 0,
            'ready' => 0,
            'publishing' => 0,
            'published' => 0,
            'failed' => 0
        ];
        foreach ($results as $row) {
            $st = $row['status'] ?? '';
            $cnt = (int)($row['total'] ?? 0);
            if (isset($counts[$st])) {
                $counts[$st] = $cnt;
            }
            $counts['all'] += $cnt;
        }
        return $counts;
    }
}
