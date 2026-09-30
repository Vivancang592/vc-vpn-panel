<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\Models\Setting;

/**
 * AILogger — ghi log metadata của AI Core.
 *
 * Nguyên tắc bất biến:
 *  - KHÔNG log API key / Authorization header.
 *  - KHÔNG log media (base64, binary).
 *  - KHÔNG log prompt đầy đủ ở mức info (chỉ ghi hash + độ dài ở mức debug).
 *
 * Ghi ra file storage/logs/<log_file> (đã tồn tại convention trong project).
 */
final class AILogger
{
    private string $channel;
    private string $filePath;
    private int $maxMessageLen;

    /**
     * @param array<string, mixed> $config Config AI (config/ai.php)
     * @param string|null $basePath Thư mục gốc project (mặc định suy ra từ __DIR__)
     */
    public function __construct(array $config = [], ?string $basePath = null)
    {
        $logging = is_array($config['logging'] ?? null) ? $config['logging'] : [];

        $this->channel = (string) ($logging['channel'] ?? 'ai_core');
        $this->maxMessageLen = (int) ($logging['max_message_len'] ?? 2000);

        $fileName = (string) ($logging['log_file'] ?? 'ai_core.log');
        $root = $basePath ?? dirname(__DIR__, 3);

        $this->filePath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $fileName;

        $this->ensureDirectory();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    /**
     * Ghi log cho một AIResult (chỉ metadata).
     *
     * @param array<string, mixed> $extra
     */
    public function logResult(string $event, \App\AI\Contracts\AIResult $result, array $extra = []): void
    {
        $payload = array_merge([
            'event'      => $event,
            'ok'         => $result->isOk(),
            'capability' => $result->capability,
            'module'     => $result->module,
            'provider'   => $result->provider,
            'model'      => $result->model,
            'task_id'    => $result->taskId,
            'status'     => $result->status,
            'error_type' => $result->errorType(),
            'files'      => count($result->files),
        ], $extra);

        if ($result->isOk()) {
            $this->info($event, $payload);
            return;
        }

        $this->error($event, $payload);
    }

    public function path(): string
    {
        return $this->filePath;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            '[%s] %s.%s: %s %s%s',
            date('Y-m-d H:i:s'),
            $this->channel,
            $level,
            $this->sanitize($message),
            $context === [] ? '' : json_encode($this->sanitizeContext($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        // Không để lỗi logging làm sập luồng nghiệp vụ.
        @file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX);
    }

    private function sanitize(string $message): string
    {
        if (strlen($message) > $this->maxMessageLen) {
            return substr($message, 0, $this->maxMessageLen) . '...[truncated]';
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sensitive = ['authorization', 'api_key', 'apikey', 'key', 'token', 'secret', 'password', 'b64_json', 'image', 'audio', 'video', 'content'];

        $clean = [];
        foreach ($context as $name => $value) {
            if (in_array(strtolower((string) $name), $sensitive, true)) {
                $clean[$name] = '[redacted]';
                continue;
            }

            if (is_string($value)) {
                $clean[$name] = $this->sanitize($value);
                continue;
            }

            $clean[$name] = is_array($value) ? $this->sanitizeContext($value) : $value;
        }

        return $clean;
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->filePath);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
