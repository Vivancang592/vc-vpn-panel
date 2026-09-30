<?php

declare(strict_types=1);

namespace App\AI\Contracts;

/**
 * Kết quả chuẩn hóa của AI Core.
 *
 * Mọi module nghiệp vụ CHỈ nhận AIResult, KHÔNG nhận raw API response.
 * Provider chịu trách nhiệm map response thô về cấu trúc này.
 */
final class AIResult
{
    public bool $ok;
    public ?string $capability;
    public ?string $module;
    public ?string $provider;
    public ?string $model;
    public ?string $content;
    /** @var array<int, array<string, mixed>> */
    public array $files;
    public ?int $taskId;
    public ?string $status;
    public array $usage;
    public ?array $error;
    public array $raw;

    public function __construct(array $data = [])
    {
        $this->ok = (bool) ($data['ok'] ?? false);
        $this->capability = $data['capability'] ?? null;
        $this->module = $data['module'] ?? null;
        $this->provider = $data['provider'] ?? null;
        $this->model = $data['model'] ?? null;
        $this->content = $data['content'] ?? null;
        $this->files = $data['files'] ?? [];
        $this->taskId = isset($data['task_id']) ? (int) $data['task_id'] : null;
        $this->status = $data['status'] ?? null;
        $this->usage = $data['usage'] ?? [];
        $this->error = $data['error'] ?? null;
        $this->raw = $data['raw'] ?? [];
    }

    public static function success(array $data = []): self
    {
        return new self(array_merge($data, ['ok' => true]));
    }

    public static function failure(string $errorType, string $message, array $context = [], array $data = []): self
    {
        return new self(array_merge($data, [
            'ok'    => false,
            'error' => array_merge([
                'type'    => $errorType,
                'message' => $message,
            ], $context),
        ]));
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function errorType(): ?string
    {
        return $this->error['type'] ?? null;
    }

    public function errorMessage(): ?string
    {
        return $this->error['message'] ?? null;
    }

    /**
     * Biểu diễn an toàn để log / debug (không chứa media hoặc secret).
     */
    public function toArray(): array
    {
        return [
            'ok'         => $this->ok,
            'capability' => $this->capability,
            'module'     => $this->module,
            'provider'   => $this->provider,
            'model'      => $this->model,
            'content'    => is_string($this->content) && strlen($this->content) > 500
                ? substr($this->content, 0, 500) . '...[truncated]'
                : $this->content,
            'files'      => $this->files,
            'task_id'    => $this->taskId,
            'status'     => $this->status,
            'usage'      => $this->usage,
            'error'      => $this->error,
        ];
    }
}
