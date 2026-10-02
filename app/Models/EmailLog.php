<?php

namespace App\Models;

class EmailLog extends BaseModel
{
    protected string $table = 'vc_email_logs';

    /**
     * Lấy nhật ký gửi email (hỗ trợ phân trang).
     */
    public function all(?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY `id` DESC";
        $paginated = $limit !== null && $limit > 0;
        if ($paginated) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = self::$db->prepare($sql);
        if ($paginated) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}