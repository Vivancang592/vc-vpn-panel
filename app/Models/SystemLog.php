<?php

namespace App\Models;

class SystemLog extends BaseModel
{
    protected string $table = 'vc_system_logs';

    /**
     * Ghi mới một dòng nhật ký vào CSDL
     */
    public function create(array $data): bool
    {
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = self::$db->prepare("INSERT INTO `{$this->table}` ({$fields}) VALUES ({$placeholders})");
        return $stmt->execute(array_values($data));
    }

    /**
     * Lấy toàn bộ nhật ký hệ thống kèm thông tin người thực hiện
     */
    public function allWithUser(): array
    {
        $stmt = self::$db->prepare("
            SELECT sl.*, u.username
            FROM `{$this->table}` sl
            LEFT JOIN `vc_users` u ON sl.user_id = u.id
            ORDER BY sl.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}