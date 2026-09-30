<?php

namespace App\Models;

class ScheduledPost extends BaseModel
{
    protected string $table = 'vc_scheduled_posts';

    /**
     * Lấy dòng hàng đợi đang gắn với một Bài Viết AI (vc_ai_outputs).
     *
     * @return array<string, mixed>|null
     */
    public function findByOutput(int $outputId): ?array
    {
        if ($outputId <= 0) {
            return null;
        }

        $stmt = self::$db->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `output_id` = :output_id
            ORDER BY `id` DESC
            LIMIT 1
        ");
        $stmt->execute(['output_id' => $outputId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Lên lịch (hoặc CẬP NHẬT lịch) cho một Bài Viết AI.
     *
     * Tạo mới nếu bài chưa có dòng hàng đợi; nếu đã có thì ghi đè lịch và
     * đưa lại về trạng thái 'pending' để cron tự đăng (kể cả khi lần trước lỗi).
     *
     * image_url (tùy chọn): ảnh từ thư mục tab Tạo Ảnh để cron đăng kèm.
     * KHÔNG truyền (null) = bỏ ảnh đã chọn trước đó → cron tự sinh ảnh theo prompt.
     *
     * @param array<string, mixed> $data topic | generated_content | image_prompt | image_url | scheduled_at | output_id | created_by
     */
    public function scheduleForOutput(array $data): bool
    {
        $outputId = (int) ($data['output_id'] ?? 0);
        if ($outputId <= 0) {
            return false;
        }

        $existing = $this->findByOutput($outputId);

        $imageUrl = ($data['image_url'] ?? null) !== null && trim((string) $data['image_url']) !== ''
            ? trim((string) $data['image_url'])
            : null;

        $base = [
            'topic'             => (string) ($data['topic'] ?? ''),
            'generated_content' => ($data['generated_content'] ?? null) !== null ? (string) $data['generated_content'] : null,
            'image_prompt'      => ($data['image_prompt'] ?? null) !== null && trim((string) $data['image_prompt']) !== '' ? (string) $data['image_prompt'] : null,
            'image_url'         => $imageUrl,
            'scheduled_at'      => (string) ($data['scheduled_at'] ?? ''),
            'status'            => 'pending',
            'retry_count'       => 0,
            'error_message'     => null,
        ];

        if ($existing !== null) {
            // Bài đã đăng rồi mà lên lịch lại → coi như một lượt đăng mới.
            // (image_url đã nằm trong $base — null nếu admin bỏ chọn ảnh.)
            if ((string) ($existing['status'] ?? '') === 'published') {
                $base['published_at']     = null;
                $base['facebook_post_id'] = null;
            }

            return $this->update((int) $existing['id'], $base);
        }

        return $this->create(array_merge($base, [
            'output_id'  => $outputId,
            'created_by' => (int) ($data['created_by'] ?? 0) ?: null,
        ]));
    }

    /**
     * Xoá dòng hàng đợi gắn với một Bài Viết AI (khi xoá bài).
     */
    public function deleteByOutput(int $outputId): int
    {
        if ($outputId <= 0) {
            return 0;
        }

        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `output_id` = :output_id");
        $stmt->execute(['output_id' => $outputId]);

        return $stmt->rowCount();
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
}
