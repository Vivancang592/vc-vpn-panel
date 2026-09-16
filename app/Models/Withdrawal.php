<?php

namespace App\Models;

class Withdrawal extends BaseModel
{
    protected string $table = 'vc_withdrawals';

    /**
     * Lấy danh sách yêu cầu rút tiền kèm thông tin thành viên
     */
    public function allWithDetails(): array
    {
        $stmt = self::$db->prepare("
            SELECT w.*, 
                   u.username, u.email, u.commission_balance
            FROM `{$this->table}` w
            LEFT JOIN `vc_users` u ON w.user_id = u.id
            ORDER BY w.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết yêu cầu rút tiền theo ID kèm thông tin thành viên
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = self::$db->prepare("
            SELECT w.*, 
                   u.username, u.email, u.commission_balance, u.full_name
            FROM `{$this->table}` w
            LEFT JOIN `vc_users` u ON w.user_id = u.id
            WHERE w.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Cập nhật trạng thái yêu cầu rút tiền
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = self::$db->prepare("UPDATE `{$this->table}` SET `status` = :status WHERE `id` = :id");
        return $stmt->execute([
            'status' => $status,
            'id'     => $id
        ]);
    }
}