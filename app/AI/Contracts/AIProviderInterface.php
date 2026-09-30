<?php

declare(strict_types=1);

namespace App\AI\Contracts;

/**
 * Hợp đồng (contract) chung cho mọi provider của AI Core.
 *
 * Phase 5 chỉ có một implementation: App\AI\Providers\KiraProvider.
 *
 * Provider CHỈ chịu trách nhiệm transport/API abstraction:
 *  - authentication
 *  - HTTP request
 *  - endpoint mapping
 *  - timeout
 *  - parse response
 *  - map error
 *  - retry theo policy
 *
 * Provider KHÔNG được biết:
 *  - ModuleRegistry / PromptRegistry / business prompt
 *  - AssetManager / FFmpeg / STT
 *  - business workflow
 *
 * Mọi method trả về AIResult (đã chuẩn hóa), không trả raw response.
 * Lỗi được biểu diễn qua AIResult::failure() với error type chuẩn;
 * exception chỉ dùng cho lỗi lập trình/cấu hình không thể tiếp tục.
 */
interface AIProviderInterface
{
    /**
     * Định danh provider (ví dụ: "kira").
     */
    public function key(): string;

    /**
     * Provider có hỗ trợ capability này không.
     */
    public function supports(string $capability): bool;

    /**
     * Chat / text completion (tương thích OpenAI chat-completions).
     *
     * @param array<int, array{role: string, content: mixed}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AIResult;

    /**
     * Sinh ảnh.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function image(array $payload, array $options = []): AIResult;

    /**
     * Tạo video (bất đồng bộ — trả về operation id).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function videoCreate(array $payload, array $options = []): AIResult;

    /**
     * Kiểm tra trạng thái tác vụ video bất đồng bộ.
     *
     * @param array<string, mixed> $options
     */
    public function videoStatus(string $operationId, array $options = []): AIResult;

    /**
     * Text-to-speech (TTS).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function speech(array $payload, array $options = []): AIResult;

    /**
     * Liệt kê model khả dụng.
     *
     * @param array<string, mixed> $options
     */
    public function models(array $options = []): AIResult;
}
