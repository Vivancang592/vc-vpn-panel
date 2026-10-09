<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AICapability;
use App\AI\Contracts\AIException;

/**
 * RequestBuilder — lắp payload theo capability từ prompt/model/input.
 *
 * KHÔNG gọi API. KHÔNG đọc DB. KHÔNG retry. Chỉ tạo array payload thuần.
 * KiraProvider nhận payload này và map sang HTTP request.
 */
final class RequestBuilder
{
    /**
     * @param array{prompt_id: null, prompt_version_id: null, system: string, user: string, source: string} $prompt
     * @param array{model_key: string, model_id: ?int} $model
     * @param array<string, mixed> $options
     * @return array{payload: array<string, mixed>, options: array<string, mixed>}
     */
    public function build(string $capability, array $prompt, array $model, array $options = []): array
    {
        $normalized = AICapability::normalize($capability);

        $payload = match ($normalized) {
            AICapability::TEXT, AICapability::COMMENT, AICapability::CHAT, AICapability::PUBLISH
                => $this->buildChatPayload($prompt, $model, $options),
            AICapability::IMAGE => $this->buildImagePayload($prompt, $model, $options),
            default => throw new AIException(
                'Capability không được hỗ trợ: ' . $capability,
                AIException::VALIDATION,
                null,
                ['capability' => $capability]
            ),
        };

        $options['capability'] = $normalized;
        $options['model']      = $model['model_key'];
        $options['model_id']   = $model['model_id'];

        return [
            'payload' => $payload,
            'options' => $options,
        ];
    }

    /**
     * @param array{system: string, user: string} $prompt
     * @param array{model_key: string} $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildChatPayload(array $prompt, array $model, array $options): array
    {
        $messages = [];

        $systemText = trim((string) $prompt['system']);

        if ($systemText !== '') {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemText,
            ];
        }

        $messages[] = [
            'role'    => 'user',
            'content' => (string) $prompt['user'],
        ];

        return [
            'messages' => $messages,
        ];
    }

    /**
     * @param array{system: string, user: string} $prompt
     * @param array{model_key: string} $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildImagePayload(array $prompt, array $model, array $options): array
    {
        $payload = [
            'prompt' => $this->withSystemPrompt(
                trim((string) $prompt['user']) !== '' ? (string) $prompt['user'] : (string) $prompt['system'],
                $prompt
            ),
            // Model LUÔN lấy từ ModelResolver ($model['model_key']) — không hard-code.
            // Ưu tiên: admin chọn trong tab → config.model_override → module default
            // → model active đầu tiên khớp capability 'image'.
            // Bỏ field này server sẽ dùng model mặc định của nó (kira-3.5-flash)
            // → API key không có quyền → lỗi 403 "does not have permission".
            // Hợp đồng Kira (SKILL.md): POST /images/generations {prompt, model, aspect_ratio}.
            'model' => (string) $model['model_key'],
        ];

        // Chỉ truyền các tham số do caller cung cấp, KHÔNG đoán thêm size.
        // 'aspect_ratio' = hợp đồng Kira đã xác minh (SKILL.md).
        foreach (['size', 'aspect_ratio', 'n', 'quality', 'style', 'response_format', 'seed'] as $key) {
            if (array_key_exists($key, $options)) {
                $payload[$key] = $options[$key];
            }
        }

        return $payload;
    }

    /**
     * Gắn system prompt vào payload một-kênh (image): chỉ nối khi có system
     * thật; giữ văn bản gốc khi không có gì để thêm.
     *
     * @param array{system: string, user: string} $prompt
     */
    private function withSystemPrompt(string $text, array $prompt): string
    {
        $text   = trim($text);
        $system = trim((string) ($prompt['system'] ?? ''));

        if ($system === '') {
            return $text;
        }

        return $text !== '' ? $system . "\n\n---\n\n" . $text : $system;
    }
}
