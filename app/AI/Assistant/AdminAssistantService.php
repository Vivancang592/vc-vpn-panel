<?php

declare(strict_types=1);

namespace App\AI\Assistant;

use App\AI\Contracts\AIResult;
use App\AI\Core\AICore;
use App\AI\Core\PromptRegistry;
use App\AI\Knowledge\AdminStatsTools;
use App\AI\Knowledge\AssistantStore;
use App\Models\Setting;

/**
 * Trợ Lý Admin — chat AI với protocol công cụ dạng JSON text.
 *
 * Kira KHÔNG hỗ trợ tools param (đã probe P0) → service tự nhúng khối mô tả
 * công cụ vào system prompt, parse JSON {"tool","args"} trong câu trả lời,
 * chạy hàm whitelist, rồi feed kết quả vào vòng hội thoại (tối đa 3 lượt gọi
 * AI mỗi lần gửi — giữ ý nhịp rate-limit 6 request/khung của key).
 *
 * Lịch sử chat lưu JSONL qua AssistantStore; trao đổi nội bộ công cụ KHÔNG
 * lưu (số liệu được tra lại mỗi lần cần → không trả lời số cũ).
 */
final class AdminAssistantService
{
    /** Tổng số lượt gọi AI cho một lần gửi (1 lần đầu + tối đa 2 lần tiếp tool). */
    private const MAX_AI_CALLS = 3;

    /** Reasoning model cần headroom — max_tokens thấp → content rỗng. */
    private const MAX_TOKENS = 2048;

    private AssistantStore $store;
    private PromptRegistry $prompts;

    public function __construct(private AICore $core, ?AssistantStore $store = null, ?PromptRegistry $prompts = null)
    {
        $this->store = $store ?? new AssistantStore();
        $this->prompts = $prompts ?? new PromptRegistry();
    }

    /**
     * Gửi một lượt chat.
     *
     * @param array<int, array{name: string, text?: string, image_data_uri?: string}> $attachments
     * @return array{ok: bool, reply?: string, error?: string}
     */
    public function send(string $convId, string $model, string $userText, array $attachments = []): array
    {
        if (trim($userText) === '' && $attachments === []) {
            return ['ok' => false, 'error' => 'Nội dung tin nhắn trống.'];
        }

        $storedText = $this->buildStoredUserText($userText, $attachments);
        $this->store->append($convId, ['role' => 'user', 'content' => $storedText]);

        $history = $this->store->messages($convId);
        $system = $this->systemPrompt();
        $images = array_values(array_filter($attachments, fn(array $a): bool => ($a['image_data_uri'] ?? '') !== ''));

        $messages = $this->buildMessages($system, $history, $images, $userText);

        $reply = null;
        for ($call = 1; $call <= self::MAX_AI_CALLS; $call++) {
            $result = $this->core->chat($messages, [
                'module'      => 'admin_assistant',
                'model'       => $model,
                'max_tokens'  => self::MAX_TOKENS,
                'temperature' => 0.6,
            ]);

            if (!$result->isOk()) {
                $error = $this->friendlyError($result, $model);
                // Câu trả lời trước đó (nếu đã có tool round) vẫn worth lưu?
                // Không — chỉ lưu khi có nội dung hoàn chỉnh.
                return ['ok' => false, 'error' => $error];
            }

            $content = trim((string) $result->content);
            if ($content === '') {
                return ['ok' => false, 'error' => 'Model không trả nội dung (có thể do giới hạn token của reasoning model). Hãy thử gửi lại.'];
            }

            $toolCall = $this->parseToolCall($content);
            $isLast = $call === self::MAX_AI_CALLS;

            if ($toolCall === null || $isLast) {
                if ($toolCall !== null) {
                    // Vẫn chạy nốt tool cuối rồi trả kết quả trực tiếp cho admin.
                    $resultJson = AdminStatsTools::dispatch((string) $toolCall['tool'], (array) $toolCall['args']);
                    $content = "Kết quả truy vấn:\n```json\n" . json_encode($resultJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n```";
                }
                $this->store->append($convId, ['role' => 'assistant', 'content' => $content]);
                return ['ok' => true, 'reply' => $content];
            }

            // Vòng tool: thêm câu gọi của model + kết quả công cụ (chỉ trong RAM).
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $toolResult = AdminStatsTools::dispatch((string) $toolCall['tool'], (array) $toolCall['args']);
            $messages[] = [
                'role'    => 'user',
                'content' => '[KẾT QUẢ CÔNG CỤ ' . (string) $toolCall['tool'] . "]\n"
                    . json_encode($toolResult, JSON_UNESCAPED_UNICODE)
                    . "\n[/KẾT QUẢ]\nDựa trên kết quả trên, hãy trả lời admin (tiếng Việt, không hiện JSON).",
            ];
        }

        return ['ok' => false, 'error' => 'Không lấy được phản hồi từ AI.'];
    }

    /**
     * Sinh một bản kế hoạch Markdown (lưu vào storage/assistant/plans —
     * KHÔNG đụng lịch sử chat).
     *
     * @return array{ok: bool, content?: string, error?: string}
     */
    public function generatePlan(string $title, string $kind, string $details, string $model): array
    {
        $kindLabel = match ($kind) {
            'sales'    => 'kế hoạch bán hàng / kinh doanh',
            'content'  => 'kế hoạch nội dung (bài đăng, fanpage, quảng cáo)',
            default    => 'kế hoạch vận hành tổng quát',
        };

        $system = 'Bạn là chuyên gia lập kế hoạch của hệ thống VPN. '
            . 'Khi được yêu cầu, hãy viết MỘT bản kế hoạch hoàn chỉnh bằng Markdown, tiếng Việt, gồm: '
            . 'mục tiêu, các giai đoạn/công việc cụ thể (bullet có hành động rõ ràng), '
            . 'mốc thời gian gợi ý, và tiêu chí đo lường. '
            . 'Không mở đầu lan man, không hỏi lại — bám sát thông tin admin cung cấp.';

        $user = "Kế hoạch: {$title}\nLoại: {$kindLabel}\n"
            . ($details !== '' ? "Yêu cầu bổ sung: {$details}" : 'Yêu cầu bổ sung: không có.');

        $result = $this->core->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], [
            'module'      => 'admin_assistant',
            'model'       => $model,
            'max_tokens'  => 3000,
            'temperature' => 0.7,
        ]);

        if (!$result->isOk()) {
            return ['ok' => false, 'error' => $this->friendlyError($result, $model)];
        }

        $content = trim((string) $result->content);
        if ($content === '') {
            return ['ok' => false, 'error' => 'Model không trả nội dung kế hoạch. Hãy thử lại.'];
        }

        return ['ok' => true, 'content' => $content];
    }

    // -----------------------------------------------------------------
    // Prompt & messages
    // -----------------------------------------------------------------

    private function systemPrompt(): string
    {
        $siteName = 'VVC VPN';
        try {
            $siteName = (string) ((new Setting())->get('site_name') ?: $siteName);
        } catch (\Throwable $e) {
            // Giữ tên mặc định.
        }

        try {
            $prompt = $this->prompts->get('admin_assistant', 'admin_assistant', [
                'site_name' => $siteName,
                'date'      => date('d/m/Y'),
            ]);
            $system = trim((string) $prompt['system']);
        } catch (\Throwable $e) {
            $system = 'Bạn là TRỢ LÝ AI của QUẢN TRỊ VIÊN hệ thống VPN ' . $siteName . '.';
        }

        return $system . "\n" . AdminStatsTools::protocolBlock();
    }

    /**
     * Ghép system + lịch sử (role user/assistant hợp lệ) + lượt gửi hiện tại.
     *
     * @param array<int, array{role: string, content: mixed}> $history
     * @param array<int, array{name: string, image_data_uri: string}> $images
     * @return array<int, array{role: string, content: mixed}>
     */
    private function buildMessages(string $system, array $history, array $images, string $userText): array
    {
        $messages = [['role' => 'system', 'content' => $system]];

        foreach ($history as $i => $msg) {
            $role = (string) ($msg['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = (string) ($msg['content'] ?? '');
            if ($content === '') {
                continue;
            }

            // Lượt gửi cuối + có ảnh → content multimodal cho vision.
            $isLastUser = $i === count($history) - 1 && $role === 'user';
            $messages[] = [
                'role'    => $role,
                'content' => ($isLastUser && $images !== []) ? $this->multimodalContent($userText, $images) : $content,
            ];
        }

        return $messages;
    }

    /**
     * @param array<int, array{name: string, image_data_uri: string}> $images
     * @return array<int, array<string, mixed>>|string
     */
    private function multimodalContent(string $userText, array $images): array
    {
        $parts = [];
        foreach ($images as $img) {
            $parts[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => $img['image_data_uri']],
            ];
        }
        $parts[] = ['type' => 'text', 'text' => trim($userText) !== '' ? $userText : 'Hãy phân tích các hình ảnh đính kèm.'];
        return $parts;
    }

    /**
     * Nội dung lưu vào lịch sử: text file đính kèm + cờ ảnh (không lưu data-URI
     * để JSONL không phình).
     *
     * @param array<int, array{name: string, text?: string, image_data_uri?: string}> $attachments
     */
    private function buildStoredUserText(string $userText, array $attachments): string
    {
        $parts = [trim($userText)];

        foreach ($attachments as $a) {
            $name = (string) ($a['name'] ?? 'file');
            if (($a['text'] ?? null) !== null) {
                $parts[] = "[FILE \"{$name}\"]\n" . (string) $a['text'] . "\n[/FILE]";
            }
            if (($a['image_data_uri'] ?? '') !== '') {
                $path = (string) ($a['path'] ?? '');
                $parts[] = $path !== ''
                    ? "[HÌNH ẢNH ĐÍNH KÈM: {$name}]({$path})"
                    : "[HÌNH ẢNH ĐÍNH KÈM: {$name}]";
            }
        }

        return trim(implode("\n\n", $parts));
    }

    // -----------------------------------------------------------------
    // Tool protocol (JSON text)
    // -----------------------------------------------------------------

    /**
     * @return array{tool: string, args: array<string, mixed>}|null
     */
    private function parseToolCall(string $content): ?array
    {
        $json = null;

        if (preg_match('/```\w*\s*(\{.*?\})\s*```/s', $content, $m) === 1) {
            $json = $m[1];
        } elseif (preg_match('/^\s*(\{.*\})\s*$/s', $content, $m) === 1) {
            $json = $m[1];
        }

        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['tool']) || !is_string($decoded['tool'])) {
            return null;
        }

        return [
            'tool' => $decoded['tool'],
            'args' => is_array($decoded['args'] ?? null) ? $decoded['args'] : [],
        ];
    }

    // -----------------------------------------------------------------
    // Errors
    // -----------------------------------------------------------------

    private function friendlyError(AIResult $result, string $model): string
    {
        $type = (string) ($result->errorType() ?? '');
        $message = (string) ($result->errorMessage() ?? 'Lỗi không rõ.');
        $http = (int) ($result->error['http_status'] ?? 0);

        if ($type === 'RATE_LIMIT' || $http === 429 || str_contains($message, 'giới hạn')) {
            return 'Hệ thống AI đang đạt giới hạn tần suất (key hiện tại tối đa 6 yêu cầu/khung). Vui lòng chờ ít phút rồi gửi lại.';
        }

        if (str_contains($message, 'only supports text') || str_contains($message, 'vision')
            || str_contains($message, 'image_url') || (str_contains($message, 'images'))) {
            return 'Model "' . $model . '" không hỗ trợ ảnh. Hãy chọn model có hỗ trợ hình ảnh (vd: coding-mimo-v2.6-flash) hoặc bỏ ảnh đính kèm.';
        }

        if ($type === 'AUTH') {
            return 'API key AI chưa được cấu hình hoặc không có quyền (AUTH).';
        }

        return 'Lỗi AI: ' . $message;
    }
}
