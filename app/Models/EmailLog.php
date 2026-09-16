<?php

namespace App\Models;

class EmailLog extends BaseModel
{
    protected string $table = 'vc_email_logs';

    /**
     * Lấy toàn bộ nhật ký gửi email
     */
    public function all(): array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` ORDER BY `id` DESC");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}