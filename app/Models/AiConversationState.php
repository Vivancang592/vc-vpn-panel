<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model: vc_ai_conversation_state — trạng thái ngữ cảnh dài của đoạn chat.
 *
 * Lưu bản tóm tắt tích lũy (rolling summary) của những tin cũ nhất, để khi
 * hội thoại vượt ngân sách token, phần bị cắt vẫn còn "đại não" — trợ lý
 * nhớ toàn bộ cuộc trò chuyện từ đầu.
 *
 * Mọi truy vấn bọc try/catch: chưa migrate hoặc DB lỗi thì luồng chat vẫn chạy
 * (chỉ mất tính năng tóm tắt).
 */
class AiConversationState extends BaseModel
{
    protected string $table = 'vc_ai_conversation_state';

    /**
     * Đọc trạng thái của một đoạn chat. Trả null khi chưa có dòng nào.
     *
     * @return array{id: int, conversation_id: string, summary: string, covered_through: int, tokens_in: int}|null
     */
    public function forConversation(string $convId): ?array
    {
        if ($convId === '') {
            return null;
        }
        try {
            $stmt = self::$db->prepare(
                "SELECT `id`, `conversation_id`, `summary`, `covered_through`, `tokens_in`
                 FROM `{$this->table}` WHERE `conversation_id` = :conv LIMIT 1"
            );
            $stmt->execute(['conv' => $convId]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'conversation_id' => (string) $row['conversation_id'],
            'summary' => (string) ($row['summary'] ?? ''),
            'covered_through' => (int) ($row['covered_through'] ?? 0),
            'tokens_in' => (int) ($row['tokens_in'] ?? 0),
        ];
    }

    /**
     * Ghi/hiệu chỉnh trạng thái (UPSERT theo conversation_id).
     */
    public function save(string $convId, string $summary, int $coveredThrough, int $tokensIn = 0): bool
    {
        if ($convId === '') {
            return false;
        }
        $summary = mb_substr($summary, 0, 60000);
        try {
            $stmt = self::$db->prepare(
                "INSERT INTO `{$this->table}`
                    (`conversation_id`, `summary`, `covered_through`, `tokens_in`, `updated_at`)
                 VALUES (:conv, :summary, :covered, :tokens, :now)
                 ON DUPLICATE KEY UPDATE
                    `summary` = VALUES(`summary`),
                    `covered_through` = VALUES(`covered_through`),
                    `tokens_in` = VALUES(`tokens_in`),
                    `updated_at` = VALUES(`updated_at`)"
            );
            return $stmt->execute([
                'conv' => $convId,
                'summary' => $summary,
                'covered' => max(0, $coveredThrough),
                'tokens' => max(0, $tokensIn),
                'now' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Chỉ cập nhật ước lượng token lượt gần nhất (không đụng summary).
     */
    public function touchTokens(string $convId, int $tokensIn): bool
    {
        if ($convId === '') {
            return false;
        }
        try {
            $stmt = self::$db->prepare(
                "UPDATE `{$this->table}` SET `tokens_in` = :tokens, `updated_at` = :now
                 WHERE `conversation_id` = :conv"
            );
            return $stmt->execute([
                'tokens' => max(0, $tokensIn),
                'now' => date('Y-m-d H:i:s'),
                'conv' => $convId,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Xóa trạng thái khi đoạn chat bị xóa (dọn rác, im lặng khi lỗi).
     */
    public function forget(string $convId): bool
    {
        if ($convId === '') {
            return false;
        }
        try {
            $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `conversation_id` = :conv");
            return $stmt->execute(['conv' => $convId]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
