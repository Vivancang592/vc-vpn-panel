<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

/**
 * AssistantStore — lưu hội thoại/trợ lý admin dạng FILE (không dùng bảng DB).
 *
 * Cấu trúc:
 *   storage/assistant/
 *     chats/{conv_id}.jsonl            1 dòng = 1 message JSON (append + LOCK_EX)
 *     attachments/{conv_id}/{...}      ảnh/file đính kèm (KHÔNG tính vào 1GB)
 *     index.json                       metadata sidebar hội thoại
 *     plans/{plan_id}.md               kế hoạch AI tạo (tách khỏi chat)
 *     plans-index.json                 metadata kế hoạch
 *
 * Quy tắc 1GB: sau mỗi lần ghi, file JSONL >= MAX_BYTES → cắt giữ lại 50%
 * dòng CUỐI (gần nhất), ghi lại nguyên tử (.tmp + rename) — đúng yêu cầu
 * "phình đến 1GB thì xoá một nửa đoạn chat cũ".
 *
 * XOÁ hội thoại = xoá file JSONL + thư mục đính kèm + entry index.
 * Mọi ID đều được kiểm tra regex chống path traversal.
 */
final class AssistantStore
{
    /** 1GB — ngưỡng tự cắt hội thoại. */
    public const MAX_BYTES = 1073741824;

    private string $baseDir;
    private int $maxBytes;

    public function __construct(?string $baseDir = null, ?int $maxBytes = null)
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 3);
        $this->baseDir = $baseDir !== null && $baseDir !== '' ? $baseDir : $base . '/storage/assistant';
        $this->maxBytes = $maxBytes !== null && $maxBytes > 0 ? $maxBytes : self::MAX_BYTES;
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    // ------------------------------------------------------------------
    // Hội thoại (chat)
    // ------------------------------------------------------------------

    /**
     * Danh sách hội thoại, mới nhất trước.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listConversations(): array
    {
        $items = $this->loadJson($this->baseDir . '/index.json');
        $items = is_array($items['conversations'] ?? null) ? $items['conversations'] : [];

        $out = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $out[] = $item;
            }
        }

        usort($out, static fn(array $a, array $b): int =>
            strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

        return $out;
    }

    /**
     * Tạo hội thoại mới (rỗng). Trả về metadata.
     *
     * @return array<string, mixed>
     */
    public function createConversation(string $model = ''): array
    {
        $id = 'c' . bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');

        $meta = [
            'id'          => $id,
            'title'       => 'Đoạn chat mới',
            'model'       => $model,
            'created_at'  => $now,
            'updated_at'  => $now,
            'msg_count'   => 0,
        ];

        $file = $this->chatPath($id);
        $this->ensureDir(dirname($file));
        @file_put_contents($file, '');

        $index = $this->indexData();
        $index['conversations'][] = $meta;
        $this->saveIndex($index);

        return $meta;
    }

    /**
     * Ghi 1 message vào hội thoại (append nguyên tử + tự cắt nếu >= 1GB).
     *
     * Tự đặt tiêu đề hội thoại từ message user đầu tiên.
     *
     * @param array<string, mixed> $message  {role, content, attachments?, ts?}
     * @return array<string, mixed> metadata hội thoại sau khi ghi
     */
    public function append(string $convId, array $message): array
    {
        if (!$this->validId($convId)) {
            throw new \InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }

        $file = $this->chatPath($convId);
        $this->ensureDir(dirname($file));

        if (!is_file($file)) {
            @file_put_contents($file, '');
        }

        $record = [
            'role'    => (string) ($message['role'] ?? 'user'),
            'content' => (string) ($message['content'] ?? ''),
            'ts'      => (string) ($message['ts'] ?? date('Y-m-d H:i:s')),
        ];

        if (!empty($message['attachments']) && is_array($message['attachments'])) {
            $record['attachments'] = array_values(array_filter($message['attachments'], 'is_array'));
        }

        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            throw new \RuntimeException('Không encode được message JSON.');
        }

        $ok = @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        if ($ok === false) {
            throw new \RuntimeException('Không ghi được file hội thoại.');
        }

        $this->trimIfOverLimit($file);

        // Cập nhật index: số message + tiêu đề từ message user đầu + thời gian.
        $index = $this->indexData();
        foreach ($index['conversations'] as $i => $meta) {
            if ((string) ($meta['id'] ?? '') !== $convId) {
                continue;
            }

            $index['conversations'][$i]['msg_count'] = (int) ($meta['msg_count'] ?? 0) + 1;
            $index['conversations'][$i]['updated_at'] = date('Y-m-d H:i:s');

            if (($record['role'] ?? '') === 'user'
                && (string) ($meta['title'] ?? '') === 'Đoạn chat mới'
                && trim($record['content']) !== '') {
                $title = trim(preg_replace('/\s+/u', ' ', $record['content']) ?? '');
                if (mb_strlen($title) > 70) {
                    $title = mb_substr($title, 0, 70) . '…';
                }
                $index['conversations'][$i]['title'] = $title !== '' ? $title : $meta['title'];
            }

            $this->saveIndex($index);

            return $index['conversations'][$i];
        }

        // Hội thoại chưa có trong index (file cũ) → thêm vào.
        $meta = [
            'id'         => $convId,
            'title'      => 'Đoạn chat mới',
            'model'      => '',
            'created_at' => $record['ts'],
            'updated_at' => $record['ts'],
            'msg_count'  => 1,
        ];
        $index['conversations'][] = $meta;
        $this->saveIndex($index);

        return $meta;
    }

    /**
     * Đọc toàn bộ message của hội thoại.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messages(string $convId): array
    {
        if (!$this->validId($convId)) {
            return [];
        }

        $file = $this->chatPath($convId);
        if (!is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $out = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            // JSON tool thô/bị cắt (lỗi cũ finish_reason=length): loại khỏi cả
            // prompt lẫn lịch sử hiển thị — model không học theo, admin không thấy JSON rác.
            if (($decoded['role'] ?? '') === 'assistant'
                && is_string($decoded['content'] ?? '')
                && preg_match('/\{\s*"tool"\s*:/u', $decoded['content']) === 1) {
                continue;
            }

            $out[] = $decoded;
        }

        return $out;
    }

    /**
     * Đổi tên / đổi model của hội thoại.
     *
     * @return bool true nếu tìm thấy và cập nhật
     */
    public function updateConversation(string $convId, array $patch): bool
    {
        if (!$this->validId($convId)) {
            return false;
        }

        $index = $this->indexData();
        foreach ($index['conversations'] as $i => $meta) {
            if ((string) ($meta['id'] ?? '') !== $convId) {
                continue;
            }
            foreach (['title', 'model', 'active_plan'] as $key) {
                if (isset($patch[$key]) && is_string($patch[$key]) && trim($patch[$key]) !== '') {
                    $index['conversations'][$i][$key] = trim($patch[$key]);
                }
            }
            $this->saveIndex($index);

            return true;
        }

        return false;
    }

    /**
     * XOÁ hội thoại: file JSONL + thư mục đính kèm + entry index.
     *
     * @return bool true nếu tồn tại và đã xoá
     */
    public function deleteConversation(string $convId): bool
    {
        if (!$this->validId($convId)) {
            return false;
        }

        $file = $this->chatPath($convId);
        $existed = is_file($file);

        if ($existed) {
            @unlink($file);
        }

        $this->deleteDir($this->attachmentsDir($convId));

        $index = $this->indexData();
        $before = count($index['conversations']);
        $index['conversations'] = array_values(array_filter(
            $index['conversations'],
            static fn($m): bool => is_array($m) && (string) ($m['id'] ?? '') !== $convId
        ));
        $changed = count($index['conversations']) !== $before;
        if ($changed) {
            $this->saveIndex($index);
        }

        return $existed || $changed;
    }

    // ------------------------------------------------------------------
    // Đính kèm
    // ------------------------------------------------------------------

    /**
     * Lưu file đính kèm của hội thoại. Trả về entry để ghi vào message.
     *
     * @param array{name: string, tmp_name: string, error: int, size: int} $file $_FILES element
     * @param string $mime MIME do client khai báo (chỉ để phân loại ảnh)
     * @return array<string, mixed> {name, path, kind, mime, size}
     */
    public function saveAttachment(string $convId, array $file, string $mime): array
    {
        if (!$this->validId($convId)) {
            throw new \InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('File upload không hợp lệ.');
        }

        $original = $this->safeBasename((string) ($file['name'] ?? 'file'));
        if ($original === '') {
            $original = 'file';
        }

        $dir = $this->attachmentsDir($convId);
        $this->ensureDir($dir);

        $seq = count(glob($dir . '/*') ?: []) + 1;
        $stored = sprintf('%03d_%s', $seq, $original);
        $dest = $dir . '/' . $stored;

        if (!@move_uploaded_file($tmp, $dest)) {
            // Fallback cho môi trường test (không phải upload thật).
            if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) {
                throw new \RuntimeException('Không lưu được file đính kèm.');
            }
        }
        @chmod($dest, 0644);

        $kind = str_starts_with($mime, 'image/') ? 'image' : 'file';

        return [
            'name' => $original,
            'path' => $convId . '/' . $stored,
            'kind' => $kind,
            'mime' => $mime,
            'size' => (int) (@filesize($dest) ?: 0),
        ];
    }

    /**
     * Đọc nội dung file văn bản đính kèm (đưa vào prompt cho AI).
     * Trả null nếu không đọc được / không phải file đã lưu hợp lệ.
     */
    public function readAttachmentText(string $convId, string $relPath): ?string
    {
        $abs = $this->attachmentAbsPath($convId, $relPath);
        if ($abs === null || !is_file($abs)) {
            return null;
        }
        $raw = @file_get_contents($abs);

        return is_string($raw) ? $raw : null;
    }

    /**
     * Đường dẫn tuyệt đối AN TOÀN của file đính kèm (chặn path traversal).
     */
    public function attachmentAbsPath(string $convId, string $relPath): ?string
    {
        if (!$this->validId($convId)) {
            return null;
        }

        $relPath = str_replace('\\', '/', $relPath);
        if (str_contains($relPath, '..') || !str_starts_with($relPath, $convId . '/')) {
            return null;
        }

        $abs = $this->baseDir . '/attachments/' . $relPath;
        $real = realpath($abs);
        $realBase = realpath($this->baseDir . '/attachments');

        if ($real === false || $realBase === false) {
            return null;
        }

        return str_starts_with($real, $realBase) ? $real : null;
    }

    // ------------------------------------------------------------------
    // Kế hoạch (tách riêng khỏi chat)
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPlans(): array
    {
        $items = $this->loadJson($this->baseDir . '/plans-index.json');
        $items = is_array($items['plans'] ?? null) ? $items['plans'] : [];

        $out = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $out[] = $item;
            }
        }

        usort($out, static fn(array $a, array $b): int =>
            strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return $out;
    }

    /**
     * Lưu kế hoạch AI tạo (file .md riêng). Trả về metadata.
     *
     * @return array<string, mixed>
     */
    public function savePlan(string $title, string $kind, string $content, string $reqId = ''): array
    {
        $id = 'p' . bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');

        $dir = $this->baseDir . '/plans';
        $this->ensureDir($dir);

        $file = $dir . '/' . $id . '.md';
        if (@file_put_contents($file, $content) === false) {
            throw new \RuntimeException('Không lưu được file kế hoạch.');
        }
        @chmod($file, 0644);

        $meta = [
            'id'         => $id,
            'title'      => trim($title) !== '' ? trim($title) : 'Kế hoạch ' . $now,
            'kind'       => in_array($kind, ['general', 'content', 'sales', 'other'], true) ? $kind : 'other',
            'created_at' => $now,
            'bytes'      => (int) (@filesize($file) ?: 0),
        ];
        if (preg_match('/^[a-z0-9\-]{8,64}$/', $reqId) === 1) {
            $meta['req_id'] = $reqId;
        }

        $index = $this->loadJson($this->baseDir . '/plans-index.json');
        $index['plans'] = is_array($index['plans'] ?? null) ? $index['plans'] : [];
        $index['plans'][] = $meta;
        $this->savePlansIndex($index);

        return $meta;
    }

    /**
     * @return array{meta: array<string, mixed>, content: string}|null
     */
    public function readPlan(string $planId): ?array
    {
        if (!$this->validId($planId)) {
            return null;
        }

        $file = $this->baseDir . '/plans/' . $planId . '.md';
        if (!is_file($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if (!is_string($content)) {
            return null;
        }

        foreach ($this->listPlans() as $meta) {
            if ((string) ($meta['id'] ?? '') === $planId) {
                return ['meta' => $meta, 'content' => $content];
            }
        }

        return ['meta' => ['id' => $planId, 'title' => $planId, 'kind' => 'other', 'created_at' => ''], 'content' => $content];
    }

    /**
     * Ghi đè nội dung kế hoạch có sẵn (AI sửa từ khung chat).
     * Giữ nguyên id/title/kind, cập nhật bytes + updated_at trong index.
     */
    public function updatePlan(string $planId, string $content): bool
    {
        if (!$this->validId($planId)) {
            return false;
        }
        $file = $this->baseDir . '/plans/' . $planId . '.md';
        if (!is_file($file)) {
            return false;
        }
        if (@file_put_contents($file, $content) === false) {
            return false;
        }
        @chmod($file, 0644);

        $now = date('Y-m-d H:i:s');
        $index = $this->loadJson($this->baseDir . '/plans-index.json');
        $plans = is_array($index['plans'] ?? null) ? $index['plans'] : [];
        $changed = false;
        foreach ($plans as $i => $meta) {
            if (is_array($meta) && (string) ($meta['id'] ?? '') === $planId) {
                $index['plans'][$i]['bytes'] = (int) (@filesize($file) ?: 0);
                $index['plans'][$i]['updated_at'] = $now;
                $changed = true;
                break;
            }
        }
        if ($changed) {
            $this->savePlansIndex($index);
        }

        return true;
    }

    public function deletePlan(string $planId): bool
    {
        if (!$this->validId($planId)) {
            return false;
        }

        $file = $this->baseDir . '/plans/' . $planId . '.md';
        $existed = is_file($file);
        if ($existed) {
            @unlink($file);
        }

        $index = $this->loadJson($this->baseDir . '/plans-index.json');
        $plans = is_array($index['plans'] ?? null) ? $index['plans'] : [];
        $before = count($plans);
        $index['plans'] = array_values(array_filter(
            $plans,
            static fn($m): bool => is_array($m) && (string) ($m['id'] ?? '') !== $planId
        ));
        $changed = count($index['plans']) !== $before;
        if ($changed) {
            $this->savePlansIndex($index);
        }

        return $existed || $changed;
    }

    /** Đánh dấu yêu cầu tạo kế hoạch đã bị admin hủy (marker cho lần lưu tới). */
    public function markPlanCancelled(string $reqId): void
    {
        if (preg_match('/^[a-z0-9\-]{8,64}$/', $reqId) !== 1) {
            return;
        }
        $dir = $this->baseDir . '/tmp';
        $this->ensureDir($dir);
        @file_put_contents($dir . '/cancel-' . $reqId, '1');
        // Dọn marker cũ (> 1 giờ) không để tích tụ.
        foreach (glob($dir . '/cancel-*') ?: [] as $old) {
            $mtime = @filemtime($old);
            if ($mtime !== false && $mtime < time() - 3600) {
                @unlink($old);
            }
        }
    }

    public function isPlanCancelled(string $reqId): bool
    {
        if (preg_match('/^[a-z0-9\-]{8,64}$/', $reqId) !== 1) {
            return false;
        }
        return is_file($this->baseDir . '/tmp/cancel-' . $reqId);
    }

    /** Xóa kế hoạch đã lưu (nếu có) khớp req_id — xử lý cancel đến trễ sau khi lưu xong. */
    public function deletePlanByReqId(string $reqId): bool
    {
        if (preg_match('/^[a-z0-9\-]{8,64}$/', $reqId) !== 1) {
            return false;
        }
        $index = $this->loadJson($this->baseDir . '/plans-index.json');
        $plans = is_array($index['plans'] ?? null) ? $index['plans'] : [];
        foreach ($plans as $meta) {
            if (is_array($meta) && (string) ($meta['req_id'] ?? '') === $reqId) {
                return $this->deletePlan((string) ($meta['id'] ?? ''));
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Nội bộ
    // ------------------------------------------------------------------

    private function chatPath(string $convId): string
    {
        return $this->baseDir . '/chats/' . $convId . '.jsonl';
    }

    private function attachmentsDir(string $convId): string
    {
        return $this->baseDir . '/attachments/' . $convId;
    }

    /** ID an toàn: sinh bằng random_hex nên chỉ nhận [a-z0-9_-]. */
    private function validId(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_-]{3,63}$/', $id);
    }

    private function safeBasename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N}._ -]/u', '', $name) ?? '';
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        return mb_strlen($name) > 120 ? mb_substr($name, -120) : $name;
    }

    /**
     * Cắt hội thoại khi đạt 1GB: giữ lại 50% dòng CUỐI (gần nhất),
     * ghi lại nguyên tử.
     */
    private function trimIfOverLimit(string $file): void
    {
        $size = @filesize($file);
        if ($size === false || $size < $this->maxBytes) {
            return;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return;
        }

        $lines = array_values(array_filter(explode("\n", $raw), static fn($l): bool => trim($l) !== ''));
        $keep = (int) ceil(count($lines) / 2);
        $kept = array_slice($lines, -$keep);

        $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, implode("\n", $kept) . "\n") === false) {
            @unlink($tmp);
            return;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    /** @return array<string, mixed> */
    private function indexData(): array
    {
        $data = $this->loadJson($this->baseDir . '/index.json');
        if (!is_array($data['conversations'] ?? null)) {
            $data['conversations'] = [];
        }

        return $data;
    }

    private function saveIndex(array $data): void
    {
        $this->writeJson($this->baseDir . '/index.json', $data);
    }

    private function savePlansIndex(array $data): void
    {
        $this->writeJson($this->baseDir . '/plans-index.json', $data);
    }

    /** @return array<string, mixed> */
    private function loadJson(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeJson(string $file, array $data): void
    {
        $this->ensureDir(dirname($file));

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('Không encode JSON index.');
        }

        $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json) === false) {
            @unlink($tmp);
            throw new \RuntimeException('Không ghi được index.');
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Không ghi được index (rename).');
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Không tạo được thư mục: ' . $dir);
        }
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $item) {
            if (is_file($item)) {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }
}
