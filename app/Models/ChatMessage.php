<?php

namespace App\Models;

class ChatMessage extends BaseModel
{
    protected string $table = 'vc_chat_messages';

    public function add(int $sessionId, string $role, string $content, ?string $provider = null, ?string $model = null): bool
    {
        $content = trim($content);
        if ($sessionId <= 0 || $content === '') {
            return false;
        }

        return $this->create([
            'session_id' => $sessionId,
            'role' => in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'user',
            'content' => mb_substr($content, 0, 4000),
            'provider' => $provider,
            'model' => $model,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getRecentBySession(int $sessionId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `session_id` = :session_id ORDER BY `id` DESC LIMIT {$limit}");
        $stmt->execute(['session_id' => $sessionId]);
        $rows = $stmt->fetchAll() ?: [];

        return array_reverse($rows);
    }

    public function getLastBySession(int $sessionId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `session_id` = :session_id ORDER BY `id` DESC LIMIT 1");
        $stmt->execute(['session_id' => $sessionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Tổng số tin của 1 phiên — dùng để biết cửa sổ `getRecentBySession()`
     * đang trỏ vào đoạn nào của cuộc trò chuyện (BƯỚC 3.1 tóm tắt luân chuyển).
     */
    public function countBySession(int $sessionId): int
    {
        if ($sessionId <= 0) {
            return 0;
        }
        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `session_id` = :session_id");
        $stmt->execute(['session_id' => $sessionId]);

        return (int) $stmt->fetchColumn();
    }
}
