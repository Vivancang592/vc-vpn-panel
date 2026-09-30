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
     * @param array{prompt_id: null, prompt_version_id: null, system: string, user: string, source: string, rules?: string} $prompt
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
            AICapability::VIDEO => $this->buildVideoPayload($prompt, $model, $options),
            AICapability::AUDIO => $this->buildAudioPayload($prompt, $model, $options),
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
     * @param array{system: string, user: string, rules?: string} $prompt
     * @param array{model_key: string} $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildChatPayload(array $prompt, array $model, array $options): array
    {
        $messages = [];

        // Nội quy AI (quy định chức năng) đứng trước, prompt kỹ thuật sau.
        $systemText = trim((string) ($prompt['rules'] ?? ''));
        if ($systemText !== '' && trim((string) $prompt['system']) !== '') {
            $systemText .= "\n\n" . (string) $prompt['system'];
        } else {
            $systemText = trim((string) $prompt['system']);
        }

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
     * @param array{system: string, user: string, rules?: string} $prompt
     * @param array{model_key: string} $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildImagePayload(array $prompt, array $model, array $options): array
    {
        $payload = [
            'prompt' => $this->withRules(
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
     * Video: chỉ dùng tham số đã xác minh. KHÔNG thêm video-reference/edit.
     *
     * Nội quy AI (prompt['rules']) được nối vào prompt để model video
     * tuân thủ quy định ngay cả khi không có channel system riêng.
     *
     * @param array{system: string, user: string, rules?: string} $prompt
     * @param array{model_key: string} $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildVideoPayload(array $prompt, array $model, array $options): array
    {
        $payload = [
            'prompt' => $this->withRules((string) $prompt['user'], $prompt),
            // SKILL.md (đã xác minh): POST /videos/generations cần "model".
            'model'  => $model['model_key'],
        ];

        // 'duration_seconds' khớp contract Kira đã xác minh (SKILL.md):
        // POST /videos/generations {prompt, aspect_ratio, duration_seconds, model}.
        foreach (['duration_seconds', 'aspect_ratio', 'resolution', 'fps', 'seed'] as $key) {
            if (array_key_exists($key, $options)) {
                $payload[$key] = $options[$key];
            }
        }

        return $payload;
    }

    /**
     * @param array{system: string, user: string, rules?: string} $prompt
     * @param array{model_key: string} $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildAudioPayload(array $prompt, array $model, array $options): array
    {
        $payload = [
            'input' => $this->withRules((string) $prompt['user'], $prompt),
            // SKILL.md (đã xác minh): POST /audio/speech cần "model" bắt buộc.
            'model' => $model['model_key'],
        ];

        // voice bắt buộc thuộc tầng caller (không hard-code ở đây).
        foreach (['voice', 'format', 'speed'] as $key) {
            if (array_key_exists($key, $options)) {
                $payload[$key] = $options[$key];
            }
        }

        return $payload;
    }

    /**
     * Gắn nội quy AI vào payload một-kênh (image/video/audio): chỉ nối khi
     * có nội quy thật; giữ văn bản gốc khi không có gì để thêm.
     *
     * @param array{system: string, user: string, rules?: string} $prompt
     */
    private function withRules(string $text, array $prompt): string
    {
        $text    = trim($text);
        $rules   = trim((string) ($prompt['rules'] ?? ''));
        $system  = trim((string) ($prompt['system'] ?? ''));

        $prefixes = array_values(array_filter([$rules, $system], static fn(string $s): bool => $s !== ''));

        if ($prefixes === []) {
            return $text;
        }

        $head = implode("\n\n", $prefixes);

        return $text !== '' ? $head . "\n\n---\n\n" . $text : $head;
    }
}
