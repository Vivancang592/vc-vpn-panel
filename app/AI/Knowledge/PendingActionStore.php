<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

/**
 * PendingActionStore — kho HÀNH ĐỘNG CHỜ XÁC NHẬN (cổng confirm của BƯỚC 1).
 *
 * ToolRegistry đánh dấu tool nào needs_confirm. Thay vì chạy ngay, service
 * phát sinh một "token hành động" có hạn dùng ngắn và đưa vào thẻ xác nhận
 * trong đoạn chat. Admin bấm "Xác nhận thực hiện" → controller claim token
 * rồi mới chạy tool. Admin bấm "Hủy" → token bị loại bỏ.
 *
 * Lưu file JSON (đồng bộ cách AssistantStore đang dùng cho post_saves.json):
 *   storage/assistant/pending_actions.json
 *
 * Bảo đảm:
 *   - Token ngẫu nhiên 128-bit, không suy ra được từ dữ liệu AI.
 *   - TTL mặc định 30 phút; hết hạn là mất hiệu lực.
 *   - claim() XÓA token trước khi trả về → chống bấm đúp/chạy lại 2 lần.
 *   - Ghi có LOCK_EX + flock nên an toàn khi nhiều request đồng thời.
 *   - Không chứa dữ liệu tuyệt đối (args đã được ToolRegistry::redactJson rút gọn).
 */
final class PendingActionStore
{
    /** Hạn dùng token xác nhận (giây). */
    public const TTL_SECONDS = 1800;

    /** Số hành động chờ tối đa giữ trong file (chống phình). */
    private const MAX_PENDING = 200;

    private string $path;
    private int $ttl;

    public function __construct(?string $path = null, ?int $ttl = null)
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 3);
        $this->path = $path !== null && $path !== '' ? $path : $base . '/storage/assistant/pending_actions.json';
        $this->ttl = $ttl !== null && $ttl > 0 ? $ttl : self::TTL_SECONDS;
    }

    /**
     * Phát hành một hành động chờ xác nhận.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $page bối cảnh trang admin đang mở (dùng lại khi xác nhận)
     * @return array{token: string, expires_at: string, tool: string, summary: string}
     */
    public function issue(string $convId, string $tool, array $args, string $risk, string $summary, array $page = []): array
    {
        $token = bin2hex(random_bytes(16));
        $now = time();
        $entry = [
            'token'        => $token,
            'conversation' => $convId,
            'tool'         => $tool,
            'risk'         => $risk,
            'summary'      => mb_substr($summary, 0, 500),
            'args'         => $args,
            'page'         => $page,
            'created_at'   => date('Y-m-d H:i:s', $now),
            'expires_at'   => date('Y-m-d H:i:s', $now + $this->ttl),
            'expires_ts'   => $now + $this->ttl,
        ];

        $this->withLock(function () use ($token, $entry): void {
            $map = $this->readRaw();
            $map[$token] = $entry;
            $this->writeRaw($this->pruneMap($map));
        });

        return [
            'token'      => $token,
            'expires_at' => $entry['expires_at'],
            'tool'       => $tool,
            'summary'    => $entry['summary'],
        ];
    }

    /**
     * Xem hành động chờ (không tiêu dùng). null khi không có / hết hạn.
     *
     * @return array<string, mixed>|null
     */
    public function peek(string $token): ?array
    {
        $entry = $this->readRaw()[$this->safeToken($token)] ?? null;
        if (!is_array($entry)) {
            return null;
        }
        if ((int) ($entry['expires_ts'] ?? 0) < time()) {
            return null;
        }
        return $entry;
    }

    /**
     * Tiêu dùng token: XÓA rồi mới trả về → hành động chỉ chạy được MỘT lần.
     *
     * @return array<string, mixed>|null
     */
    public function claim(string $token): ?array
    {
        $token = $this->safeToken($token);
        $out = null;
        $this->withLock(function () use ($token, &$out): void {
            $map = $this->readRaw();
            $entry = $map[$token] ?? null;
            if (is_array($entry) && (int) ($entry['expires_ts'] ?? 0) >= time()) {
                unset($map[$token]);
                $this->writeRaw($map);
                $out = $entry;
            }
        });
        return $out;
    }

    /**
     * Hủy hành động chờ. Trả về true khi có token để hủy.
     */
    public function cancel(string $token): bool
    {
        $token = $this->safeToken($token);
        $hit = false;
        $this->withLock(function () use ($token, &$hit): void {
            $map = $this->readRaw();
            if (isset($map[$token])) {
                unset($map[$token]);
                $this->writeRaw($map);
                $hit = true;
            }
        });
        return $hit;
    }

    /**
     * Danh sách hành động chờ còn hiệu lực của một đoạn chat (mới nhất trước).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forConversation(string $convId, int $limit = 20): array
    {
        $items = [];
        foreach ($this->readRaw() as $entry) {
            if (is_array($entry)
                && (string) ($entry['conversation'] ?? '') === $convId
                && (int) ($entry['expires_ts'] ?? 0) >= time()) {
                $items[] = $entry;
            }
        }
        usort($items, static fn(array $a, array $b): int =>
            strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($items, 0, max(1, min(100, $limit)));
    }

    /** Dọn token hết hạn (cron/đầu phiên gọi). */
    public function prune(): int
    {
        $removed = 0;
        $this->withLock(function () use (&$removed): void {
            $map = $this->readRaw();
            $clean = $this->pruneMap($map, $removed);
            if ($clean !== $map) {
                $this->writeRaw($clean);
            }
        });
        return $removed;
    }

    // -----------------------------------------------------------------
    // Nội bộ
    // -----------------------------------------------------------------

    /** @param callable():void $fn */
    private function withLock(callable $fn): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $lock = $dir . '/.pending_actions.lock';
        $fh = @fopen($lock, 'c');
        if ($fh === false) {
            $fn();
            return;
        }
        try {
            flock($fh, LOCK_EX);
            $fn();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function readRaw(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($this->path), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, array<string, mixed>> $map */
    private function writeRaw(array $map): void
    {
        @file_put_contents(
            $this->path,
            (string) json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /**
     * Loại token hết hạn + giữ tối đa MAX_PENDING phần mới nhất.
     *
     * @param array<string, array<string, mixed>> $map
     * @param-out int $dropped
     * @return array<string, array<string, mixed>>
     */
    private function pruneMap(array $map, int &$dropped = 0): array
    {
        $before = count($map);
        $now = time();
        $map = array_filter($map, static function ($e) use ($now): bool {
            return is_array($e) && (int) ($e['expires_ts'] ?? 0) >= $now;
        });
        $dropped += $before - count($map);

        if (count($map) > self::MAX_PENDING) {
            uasort($map, static fn(array $a, array $b): int =>
                strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
            $keep = array_slice($map, 0, self::MAX_PENDING, true);
            $dropped += count($map) - count($keep);
            $map = $keep;
        }

        return $map;
    }

    /** Token hợp lệ duy nhất dạng 32 ký tự hex; sai → chuỗi rỗng (không tra). */
    private function safeToken(string $token): string
    {
        $token = strtolower(trim($token));
        return preg_match('/^[a-f0-9]{32}$/', $token) === 1 ? $token : '';
    }
}
