<?php

namespace App\Models;

class ChatAiCache extends BaseModel
{
    protected string $table = 'vc_chat_ai_cache';

    public function findFresh(string $cacheKey, int $ttlMinutes = 60): ?array
    {
        $ttlMinutes = max(1, min(1440, $ttlMinutes));
        $stmt = self::$db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `cache_key` = :cache_key AND `updated_at` >= DATE_SUB(NOW(), INTERVAL {$ttlMinutes} MINUTE) LIMIT 1"
        );
        $stmt->execute(['cache_key' => $cacheKey]);
        $row = $stmt->fetch();

        if ($row) {
            $this->bumpHits((int) $row['id']);
            return $this->find((int) $row['id']);
        }

        return null;
    }

    public function upsert(string $cacheKey, string $question, string $answer, ?string $provider = null, ?string $model = null): bool
    {
        $stmt = self::$db->prepare(
            "INSERT INTO `{$this->table}` (`cache_key`, `question`, `answer`, `provider`, `model`, `hits`, `created_at`, `updated_at`)
             VALUES (:cache_key, :question, :answer, :provider, :model, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                `question` = VALUES(`question`),
                `answer` = VALUES(`answer`),
                `provider` = VALUES(`provider`),
                `model` = VALUES(`model`),
                `hits` = `hits` + 1,
                `updated_at` = NOW()"
        );

        return $stmt->execute([
            'cache_key' => $cacheKey,
            'question' => mb_substr($question, 0, 1000),
            'answer' => mb_substr($answer, 0, 4000),
            'provider' => $provider,
            'model' => $model,
        ]);
    }

    private function bumpHits(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = self::$db->prepare("UPDATE `{$this->table}` SET `hits` = `hits` + 1, `updated_at` = NOW() WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
    }
}
