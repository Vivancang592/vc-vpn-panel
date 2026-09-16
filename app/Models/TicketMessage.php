<?php

namespace App\Models;

class TicketMessage extends BaseModel
{
    protected string $table = 'vc_ticket_messages';

    /**
     * Lấy danh sách tin nhắn của 1 ticket kèm thông tin người gửi
     */
    public function getByTicketId(int $ticketId): array
    {
        $stmt = self::$db->prepare("
            SELECT m.*, u.username, u.role, u.full_name
            FROM `{$this->table}` m
            LEFT JOIN `vc_users` u ON m.sender_id = u.id
            WHERE m.ticket_id = :ticket_id
            ORDER BY m.id ASC
        ");
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Thêm phản hồi mới vào ticket
     */
    public function create(array $data): bool
    {
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = self::$db->prepare("INSERT INTO `{$this->table}` ({$fields}) VALUES ({$placeholders})");
        return $stmt->execute(array_values($data));
    }
}