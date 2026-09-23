<?php

namespace App\Models;

class ChatSession extends BaseModel
{
    protected string $table = 'vc_chat_sessions';

    public function findByVisitor(string $visitorToken, string $source = 'web'): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `visitor_token` = :visitor_token AND `source` = :source LIMIT 1");
        $stmt->execute([
            'visitor_token' => $visitorToken,
            'source' => $source
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getOrCreate(string $visitorToken, string $source = 'web', ?int $userId = null, ?string $externalId = null): ?array
    {
        $existing = $this->findByVisitor($visitorToken, $source);
        if ($existing) {
            $updates = [];
            if ($userId !== null && $userId > 0 && empty($existing['user_id'])) {
                $updates['user_id'] = $userId;
            }
            if ($externalId !== null && $externalId !== '' && empty($existing['external_id'])) {
                $updates['external_id'] = $externalId;
            }
            if (!empty($updates)) {
                $this->update((int) $existing['id'], $updates);
                return $this->find((int) $existing['id']);
            }
            return $existing;
        }

        $ok = $this->create([
            'user_id' => $userId,
            'visitor_token' => $visitorToken,
            'source' => $source,
            'external_id' => $externalId,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if (!$ok) {
            return null;
        }

        return $this->find($this->lastInsertId());
    }
}
