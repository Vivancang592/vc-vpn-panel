<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model: vc_ai_tool_calls — audit mọi lượt Trợ Lý Admin chạy công cụ.
 *
 * Ghi "best effort": bảng chưa có / DB lỗi KHÔNG được làm hỏng luồng chat,
 * nên mọi truy vấn đều bọc try/catch và trả giá trị mặc định vô hại.
 * Tên cột luôn lấy từ hằng số (không bao giờ từ dữ liệu AI đưa vào).
 */
class AiToolCall extends BaseModel
{
    protected string $table = 'vc_ai_tool_calls';

    /** @var string[] cột được phép ghi — whitelist cho create(). */
    private const WRITABLE = [
        'conversation_id', 'admin_id', 'tool', 'risk', 'args_json',
        'before_json', 'after_json', 'result_json', 'ok', 'error',
        'duration_ms', 'confirmed_by', 'created_at',
    ];

    /**
     * Giới hạn độ dài theo đúng định nghĩa cột trong migration.
     * Cắt chuỗi chứ KHÔNG để MySQL báo "Data too long" rồi mất cả dòng audit.
     *
     * @var array<string, int>
     */
    private const LIMITS = [
        'conversation_id' => 64,
        'tool'            => 64,
        'risk'            => 16,
        'error'           => 512,
        'args_json'       => 65000,
        'before_json'     => 65000,
        'after_json'      => 65000,
        'result_json'     => 65000,
    ];

    /**
     * Cột NOT NULL của bảng: giá trị thay thế khi AI/service truyền null.
     * Truyền null vào cột NOT NULL sẽ làm INSERT chết (SQLSTATE 23000) và
     * mất luôn dòng audit — phải chốt ở đây thay vì trông chờ từng nơi gọi.
     *
     * @var array<string, mixed>
     */
    private const NOT_NULL_DEFAULTS = [
        'conversation_id' => '',
        'tool'            => '',
        'risk'            => 'read',
        'result_json'     => '',
        'ok'              => 0,
        'error'           => '',
        'duration_ms'     => 0,
    ];

    /**
     * Ghi một dòng audit. Trả về id bản ghi (0 khi thất bại — im lặng, không ném).
     *
     * @param array<string, mixed> $data
     */
    public function log(array $data): int
    {
        $row = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, self::WRITABLE, true)) {
                continue;
            }
            if ($value === null && array_key_exists($key, self::NOT_NULL_DEFAULTS)) {
                $value = self::NOT_NULL_DEFAULTS[$key];
            }
            if (is_string($value) && isset(self::LIMITS[$key])) {
                $value = mb_substr($value, 0, self::LIMITS[$key]);
            }
            $row[$key] = $value;
        }
        $row['created_at'] = $row['created_at'] ?? date('Y-m-d H:i:s');

        try {
            if (!$this->create($row)) {
                return 0;
            }
            return (int) $this->lastInsertId();
        } catch (\Throwable $e) {
            // Audit không được làm hỏng luồng chat, nhưng cũng không được im
            // lặng mất dữ liệu — ghi nguyên nhân vào error_log để truy được.
            error_log('[AiToolCall] ghi audit thất bại: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Nhật ký công cụ của một đoạn chat (mới nhất trước), giới hạn bản ghi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forConversation(string $convId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        try {
            $stmt = self::$db->prepare(
                "SELECT * FROM `{$this->table}` WHERE `conversation_id` = :conv ORDER BY `id` DESC LIMIT {$limit}"
            );
            $stmt->execute(['conv' => $convId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Gán người admin đã bấm nút xác nhận cho một hành động đã lên lịch.
     */
    public function markConfirmed(int $id, int $adminId): bool
    {
        if ($id <= 0 || $adminId <= 0) {
            return false;
        }
        try {
            $stmt = self::$db->prepare(
                "UPDATE `{$this->table}` SET `confirmed_by` = :admin WHERE `id` = :id"
            );
            return $stmt->execute(['admin' => $adminId, 'id' => $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Số lượt gọi công cụ theo từng loại rủi ro (đếm nhanh cho UI/kiểm thử).
     *
     * @return array<string, int>
     */
    public function countByRisk(string $convId = ''): array
    {
        try {
            if ($convId !== '') {
                $stmt = self::$db->prepare(
                    "SELECT `risk`, COUNT(*) AS n FROM `{$this->table}` WHERE `conversation_id` = :conv GROUP BY `risk`"
                );
                $stmt->execute(['conv' => $convId]);
            } else {
                $stmt = self::$db->query("SELECT `risk`, COUNT(*) AS n FROM `{$this->table}` GROUP BY `risk`");
            }
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $out[(string) $r['risk']] = (int) $r['n'];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
