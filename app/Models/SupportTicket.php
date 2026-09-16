<?php

namespace App\Models;

class SupportTicket extends BaseModel
{
    protected string $table = 'vc_support_tickets';

    /**
     * Lấy danh sách ticket hỗ trợ theo user_id
     */
    public function getByUserId(int $userId): array
    {
        $stmt = self::$db->prepare("
            SELECT t.*, 
                   u1.username AS user_name, u1.email AS user_email,
                   u2.username AS staff_name
            FROM `{$this->table}` t
            LEFT JOIN `vc_users` u1 ON t.user_id = u1.id
            LEFT JOIN `vc_users` u2 ON t.assigned_staff_id = u2.id
            WHERE t.user_id = :user_id
            ORDER BY t.id DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy danh sách tất cả ticket hỗ trợ kèm thông tin khách hàng và nhân viên xử lý
     */
    public function allWithDetails(): array
    {
        $stmt = self::$db->prepare("
            SELECT t.*, 
                   u1.username AS user_name, u1.email AS user_email,
                   u2.username AS staff_name
            FROM `{$this->table}` t
            LEFT JOIN `vc_users` u1 ON t.user_id = u1.id
            LEFT JOIN `vc_users` u2 ON t.assigned_staff_id = u2.id
            ORDER BY t.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết 1 ticket theo ID
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = self::$db->prepare("
            SELECT t.*, 
                   u1.username AS user_name, u1.email AS user_email,
                   u2.username AS staff_name
            FROM `{$this->table}` t
            LEFT JOIN `vc_users` u1 ON t.user_id = u1.id
            LEFT JOIN `vc_users` u2 ON t.assigned_staff_id = u2.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Cập nhật trạng thái và/hoặc gán nhân viên phụ trách
     */
    public function updateTicket(int $id, array $data): bool
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $values = array_values($data);
        $values[] = $id;

        $stmt = self::$db->prepare("UPDATE `{$this->table}` SET {$set} WHERE id = ?");
        return $stmt->execute($values);
    }
}