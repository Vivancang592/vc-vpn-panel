<?php

declare(strict_types=1);

namespace App\AI\Core;

/**
 * PromptFileStore — kho prompt dạng FILE (admin sửa trực tiếp).
 *
 * Mỗi prompt key tương ứng MỘT file: storage/prompts/{key}.txt
 * Định dạng file (dễ đọc, admin sửa tay được):
 *
 *   === SYSTEM ===
 *   <system prompt>
 *   === USER ===
 *   <user template>
 *
 * Quy tắc:
 *  - File là nguồn ưu tiên cao nhất khi AI đọc prompt (file → DB → mặc định).
 *  - Ghi file là thao tác nguyên tử (.tmp rồi rename) để tránh hỏng prompt.
 *  - Prompt admin nhập là tiếng Việt, KHÔNG chứa API key/secret.
 *  - Không ném lỗi ra ngoài khi đọc; trả null để tầng trên fallback.
 *
 * Lớp này THUẦN file I/O, không gọi DB, không gọi provider.
 */
final class PromptFileStore
{
    public const SYSTEM_MARK = '=== SYSTEM ===';
    public const USER_MARK   = '=== USER ===';

    /**
     * Từ khoá nhạy cảm — chặn admin vô tình dán key vào prompt.
     *
     * @var array<int, string>
     */
    private const SECRET_PATTERNS = [
        'sk-',            // OpenAI/OpenRouter
        'AIza',           // Google API key
        'kira_',          // Kira key
        'api_key',
        'apikey',
        'access_token',
        'page_access_token',
        'app_secret',
        'bearer ',
    ];

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 3);
        $this->dir = $dir !== null && $dir !== '' ? $dir : $base . '/storage/prompts';
    }

    public function directory(): string
    {
        return $this->dir;
    }

    public function path(string $key): string
    {
        return $this->dir . '/' . $this->safeKey($key) . '.txt';
    }

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    /**
     * Đọc prompt từ file. Trả null nếu chưa có file hoặc file hỏng.
     *
     * @return array{system: string, user: string}|null
     */
    public function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        return $this->parse($raw);
    }

    /**
     * Ghi prompt ra file (nguyên tử). Trả false nếu thất bại.
     */
    public function write(string $key, string $system, string $user): bool
    {
        if (!$this->ensureDir()) {
            return false;
        }

        $content = self::SYSTEM_MARK . "\n"
            . rtrim($system) . "\n\n"
            . self::USER_MARK . "\n"
            . rtrim($user) . "\n";

        $path = $this->path($key);
        $tmp  = $path . '.tmp.' . bin2hex(random_bytes(4));

        if (@file_put_contents($tmp, $content) === false) {
            return false;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        @chmod($path, 0644);

        return true;
    }

    /**
     * Xoá file prompt (để hệ thống quay về DB/mặc định).
     */
    public function delete(string $key): bool
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    /**
     * Danh sách prompt key đã có file.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }

        $keys = [];
        foreach ((array) glob($this->dir . '/*.txt') as $file) {
            $keys[] = basename((string) $file, '.txt');
        }

        sort($keys);

        return $keys;
    }

    /**
     * Thời điểm sửa gần nhất của file (hiển thị ở UI).
     */
    public function modifiedAt(string $key): ?string
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $ts = @filemtime($path);

        return $ts !== false ? date('d/m/Y H:i', (int) $ts) : null;
    }

    /**
     * Gieo file mặc định cho mọi prompt key chưa có file.
     *
     * @param array<string, array{system: string, user: string}> $defaults
     * @return array<int, string> danh sách key vừa tạo
     */
    public function seed(array $defaults): array
    {
        $created = [];

        foreach ($defaults as $key => $tpl) {
            if ($this->exists((string) $key)) {
                continue;
            }
            if ($this->write((string) $key, (string) $tpl['system'], (string) $tpl['user'])) {
                $created[] = (string) $key;
            }
        }

        return $created;
    }

    /**
     * Phát hiện nội dung chứa secret (admin nhập key vào prompt).
     *
     * @return string|null từ khoá phát hiện, null nếu sạch
     */
    public function detectSecret(string $text): ?string
    {
        $lower = mb_strtolower($text, 'UTF-8');

        foreach (self::SECRET_PATTERNS as $needle) {
            if (str_contains($lower, mb_strtolower($needle, 'UTF-8'))) {
                return $needle;
            }
        }

        return null;
    }

    /**
     * @return array{system: string, user: string}
     */
    private function parse(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $hasMarks = str_contains($raw, self::SYSTEM_MARK) || str_contains($raw, self::USER_MARK);

        if (!$hasMarks) {
            // File cũ/đơn giản: toàn bộ nội dung là system prompt.
            return ['system' => trim($raw), 'user' => ''];
        }

        $system = '';
        $user   = '';

        if (preg_match('/' . preg_quote(self::SYSTEM_MARK, '/') . '\s*(.*?)(?=' . preg_quote(self::USER_MARK, '/') . '|$)/s', $raw, $m)) {
            $system = trim($m[1]);
        }

        if (preg_match('/' . preg_quote(self::USER_MARK, '/') . '\s*(.*)$/s', $raw, $m)) {
            $user = trim($m[1]);
        }

        return ['system' => $system, 'user' => $user];
    }

    private function safeKey(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);

        return $safe !== null && $safe !== '' ? $safe : 'unknown';
    }

    private function ensureDir(): bool
    {
        if (is_dir($this->dir)) {
            return true;
        }

        return @mkdir($this->dir, 0775, true) || is_dir($this->dir);
    }
}
