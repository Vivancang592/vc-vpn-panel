<?php

namespace App\Models;

class ChatEvent extends BaseModel
{
    protected string $table = 'vc_chat_events';

    public function track(string $eventName, string $source = 'web', ?int $sessionId = null, ?int $userId = null, ?string $ipAddress = null, array $eventData = []): bool
    {
        $eventName = trim($eventName);
        if ($eventName === '') {
            return false;
        }

        $json = !empty($eventData) ? json_encode($eventData, JSON_UNESCAPED_UNICODE) : null;

        return $this->create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'source' => in_array($source, ['web', 'fanpage'], true) ? $source : 'web',
            'event_name' => mb_substr($eventName, 0, 60),
            'event_data' => $json,
            'ip_address' => $ipAddress,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function countToday(string $eventName): int
    {
        $stmt = self::$db->prepare("SELECT COUNT(*) AS total FROM `{$this->table}` WHERE `event_name` = :event_name AND DATE(`created_at`) = CURDATE()");
        $stmt->execute(['event_name' => $eventName]);
        $row = $stmt->fetch();
        return (int) ($row['total'] ?? 0);
    }

    public function allWithSessionContext(): array
    {
        $stmt = self::$db->prepare(
            "SELECT ce.*, cs.source AS session_source, cs.visitor_token, cs.external_id, u.username, u.email
             FROM `{$this->table}` ce
             LEFT JOIN `vc_chat_sessions` cs ON ce.session_id = cs.id
             LEFT JOIN `vc_users` u ON ce.user_id = u.id
             ORDER BY ce.id DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
