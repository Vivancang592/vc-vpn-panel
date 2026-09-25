<?php

namespace App\Services;

use App\Models\Setting;

class AIProviderService
{
    private array $settings;

    public function __construct()
    {
        $this->settings = (new Setting())->getAllAsKeyValue();
    }

    public function ask(array $messages, ?string $provider = null, ?string $model = null): array
    {
        $provider = strtolower(trim((string) ($provider ?: ($this->settings['ai_provider'] ?? 'openai'))));

        if ($provider === 'gemini') {
            $result = $this->askGemini($messages, $model ?: ($this->settings['ai_gemini_model'] ?? 'gemini-3.7-flash'));
            $result['provider'] = 'gemini';
            return $result;
        }

        $result = $this->askOpenAI($messages, $model ?: ($this->settings['ai_openai_model'] ?? 'openai/gpt-4o-mini'));
        $result['provider'] = 'openai';
        return $result;
    }

    /**
     * Lập kế hoạch chiến dịch bài đăng đa dạng góc nhìn, trả về cấu trúc mảng JSON chuẩn
     */
    public function generateCampaignPlan(string $coreTopics, int $postCount = 5, ?string $customInstruction = null): array
    {
        $provider = strtolower(trim((string) ($this->settings['ai_content_provider'] ?? $this->settings['ai_provider'] ?? 'openai')));
        $model = trim((string) ($this->settings['ai_content_model'] ?? ''));
        $siteName = $this->settings['site_name'] ?? 'VC VPN';

        $customSystemPrompt = trim((string) ($this->settings['ai_content_system_prompt'] ?? ''));
        if ($customSystemPrompt === '') {
            $customSystemPrompt = "Bạn là chuyên gia Content Creator & Digital Marketer hàng đầu cho dịch vụ VPN {$siteName}.";
        }

        $systemPrompt = $customSystemPrompt . "\n\n"
            . "NHIỆM VỤ QUAN TRỌNG: Bạn phải lên kế hoạch chiến dịch gồm đúng {$postCount} bài đăng Fanpage Facebook độc lập, sáng tạo, tiếp cận khách hàng từ nhiều góc độ khác nhau (tốc độ cao, chơi game mượt, bảo mật wifi công cộng, xem phim 4K không lag, vượt tường lửa, ưu đãi gói cước).\n"
            . "YÊU CẦU BẮT BUỘC VỀ ĐỊNH DẠNG ĐẦU RA:\n"
            . "- Trả về DUY NHẤT một chuỗi JSON ARRAY hợp lệ (Valid JSON Array).\n"
            . "- KHÔNG viết thêm bất kỳ lời chào, giải thích, markdown fence hay text thừa nào ngoài JSON.\n"
            . "- Mỗi phần tử trong mảng JSON là một object chứa đúng 3 khóa:\n"
            . "  1. \"topic\": Tiêu đề / chủ đề ngắn gọn của bài viết (dưới 100 ký tự).\n"
            . "  2. \"content\": Toàn bộ nội dung bài đăng chi tiết (150 - 300 từ) có emoji sinh động, lời kêu gọi hành động (CTA) và 4-8 hashtag #VPN liên quan.\n"
            . "  3. \"image_prompt\": Prompt mô tả hình ảnh bằng tiếng Anh chi tiết cho DALL-E 3 (chủ đề mạng máy tính, cyberpunk, 3D render, tốc độ ánh sáng, bảo mật công nghệ).\n"
            . "- Đảm bảo mảng có đúng {$postCount} phần tử.";

        $userPrompt = "Danh sách các chủ đề cốt lõi / từ khóa chiến dịch:\n" . $coreTopics . "\n";
        if (!empty($customInstruction)) {
            $userPrompt .= "Yêu cầu bổ sung:\n" . $customInstruction . "\n";
        }
        $userPrompt .= "\nHãy tạo đúng {$postCount} bài viết và xuất kết quả theo định dạng JSON ARRAY.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        if ($provider === 'gemini') {
            $targetModel = $model ?: ($this->settings['ai_gemini_model'] ?? 'gemini-2.5-flash');
            $result = $this->askGemini($messages, $targetModel);
            $result['provider'] = 'gemini';
            return $result;
        }

        $targetModel = $model ?: ($this->settings['ai_openai_model'] ?? 'openai/gpt-4o-mini');
        $result = $this->askOpenAI($messages, $targetModel);
        $result['provider'] = 'openai';
        return $result;
    }

    /**
     * Sinh nội dung bài viết quảng cáo / hướng dẫn / tương tác chuyên sâu cho Fanpage
     */
    public function generateContent(string $topic, ?string $contentPrompt = null, ?string $customSystemPrompt = null): array
    {
        $provider = strtolower(trim((string) ($this->settings['ai_content_provider'] ?? $this->settings['ai_provider'] ?? 'openai')));
        $model = trim((string) ($this->settings['ai_content_model'] ?? ''));

        if ($customSystemPrompt === null || trim($customSystemPrompt) === '') {
            $customSystemPrompt = trim((string) ($this->settings['ai_content_system_prompt'] ?? ''));
        }

        if ($customSystemPrompt === '') {
            $siteName = $this->settings['site_name'] ?? 'VC VPN';
            $customSystemPrompt = "Bạn là một chuyên gia Marketing & Copywriter hàng đầu cho dịch vụ VPN cao cấp {$siteName}.\n"
                . "Nhiệm vụ của bạn là viết các bài đăng Fanpage hấp dẫn, hiện đại, chạm đúng nỗi đau của người dùng (tốc độ mạng chậm, đứt cáp quang, lag khi chơi game, chặn truy cập quốc tế, bảo mật dữ liệu riêng tư).\n"
                . "Yêu cầu bài viết:\n"
                . "1. Tiêu đề (Headline) giật tít, thu hút, có emoji sinh động.\n"
                . "2. Thân bài nêu bật lợi ích vượt trội: Băng thông không giới hạn, máy chủ Singapore/Hongkong/Việt Nam độ trễ thấp, hỗ trợ 4G/5G thả ga.\n"
                . "3. Kêu gọi hành động (CTA) rõ ràng, hướng dẫn truy cập website hoặc nhắn tin Fanpage để nhận ưu đãi dùng thử.\n"
                . "4. Kèm 4-8 hashtag thịnh hành (VD: #VPN #InternetTocDoCao #GamingVPN #GiaiphapMang).\n"
                . "5. Sử dụng tiếng Việt tự nhiên, trẻ trung, không quá dài dòng (khoảng 150 - 350 từ).";
        }

        $userInstruction = "Chủ đề bài viết: " . $topic;
        if (!empty($contentPrompt)) {
            $userInstruction .= "\nĐịnh hướng & Yêu cầu chi tiết: " . $contentPrompt;
        }

        $messages = [
            ['role' => 'system', 'content' => $customSystemPrompt],
            ['role' => 'user', 'content' => $userInstruction]
        ];

        if ($provider === 'gemini') {
            $targetModel = $model ?: ($this->settings['ai_gemini_model'] ?? 'gemini-2.5-flash');
            $result = $this->askGemini($messages, $targetModel);
            $result['provider'] = 'gemini';
            return $result;
        }

        $targetModel = $model ?: ($this->settings['ai_openai_model'] ?? 'openai/gpt-4o-mini');
        $result = $this->askOpenAI($messages, $targetModel);
        $result['provider'] = 'openai';
        return $result;
    }

    /**
     * Sinh ảnh minh họa bằng AI (DALL-E 3 hoặc Imagen) và lưu trữ cục bộ
     */
    public function generateImage(string $prompt, string $size = '1024x1024'): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['ok' => false, 'url' => '', 'error' => 'Prompt sinh ảnh không được để trống.'];
        }

        $apiKey = $this->resolveConfigValue('ai_image_api_key', 'OPENAI_API_KEY', ['ai_openai_api_key', 'OPENROUTER_API_KEY', 'ai_openrouter_api_key']);
        if ($apiKey === '') {
            return ['ok' => false, 'url' => '', 'error' => 'Chưa cấu hình API Key để sinh ảnh.'];
        }

        // Tạo thư mục lưu trữ ảnh bài đăng nếu chưa có
        $uploadDir = BASE_PATH . '/public/uploads/posts';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $payload = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size ?: '1024x1024',
            'response_format' => 'url'
        ];

        $response = $this->requestJson(
            'https://api.openai.com/v1/images/generations',
            $payload,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );

        if (!$response['ok']) {
            return ['ok' => false, 'url' => '', 'error' => $response['error']];
        }

        $remoteImageUrl = (string) ($response['data']['data'][0]['url'] ?? '');
        if ($remoteImageUrl === '') {
            return ['ok' => false, 'url' => '', 'error' => 'Không nhận được URL ảnh từ AI provider.'];
        }

        // Tải ảnh về lưu trữ cục bộ để đảm bảo link không bị hết hạn
        $imageContent = @file_get_contents($remoteImageUrl);
        if ($imageContent === false) {
            // Nếu không thể curl về máy, sử dụng trực tiếp link tạm
            return ['ok' => true, 'url' => $remoteImageUrl, 'error' => null];
        }

        $fileName = 'ai_post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $localFilePath = $uploadDir . '/' . $fileName;
        @file_put_contents($localFilePath, $imageContent);

        $publicUrl = '/uploads/posts/' . $fileName;
        return ['ok' => true, 'url' => $publicUrl, 'error' => null];
    }

    private function askOpenAI(array $messages, string $model): array
    {
        $apiKey = $this->resolveConfigValue('ai_openai_api_key', 'OPENROUTER_API_KEY', ['ai_openrouter_api_key', 'openrouter_api_key', 'OPENAI_API_KEY', 'openai_api_key']);
        if ($apiKey === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Thiếu API key OpenRouter.'];
        }

        $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 2000);
        if ($maxTokens < 1200) {
            $maxTokens = 2000;
        }
        $maxTokens = max(1000, min(4096, $maxTokens));

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.45,
            'max_tokens' => $maxTokens,
        ];

        $response = $this->requestJson(
            'https://openrouter.ai/api/v1/chat/completions',
            $payload,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: http://localhost',
                'X-Title: VC VPN Chatbot'
            ]
        );

        if (!$response['ok']) {
            return ['ok' => false, 'content' => '', 'error' => $response['error']];
        }

        $content = (string) ($response['data']['choices'][0]['message']['content'] ?? '');
        if (trim($content) === '') {
            return ['ok' => false, 'content' => '', 'error' => 'OpenRouter trả về nội dung rỗng.'];
        }

        return ['ok' => true, 'content' => $content, 'error' => null, 'model' => $model];
    }

    private function askGemini(array $messages, string $model): array
    {
        $apiKey = $this->resolveConfigValue('ai_gemini_api_key', 'GEMINI_API_KEY', ['gemini_api_key']);
        if ($apiKey === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Thiếu API key Gemini.'];
        }

        $systemParts = [];
        $contents = [];

        foreach ($messages as $msg) {
            $role = (string) ($msg['role'] ?? 'user');
            $text = trim((string) ($msg['content'] ?? ''));
            if ($text === '') {
                continue;
            }

            if ($role === 'system') {
                $systemParts[] = ['text' => $text];
            } else {
                $geminiRole = ($role === 'assistant' || $role === 'model') ? 'model' : 'user';
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => [
                        ['text' => $text]
                    ]
                ];
            }
        }

        if (empty($contents)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => 'Xin chào']]
            ];
        }

        $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 2000);
        if ($maxTokens < 1200) {
            $maxTokens = 2000;
        }
        // Với Gemini thế hệ mới (2.5, 3.x Flash), maxOutputTokens bao gồm cả Thinking Tokens + Answer Tokens.
        // Cần cấp đủ headroom (tối thiểu 3000 - 8192 tokens) để không bị ngắt giữa chừng.
        $maxOutputTokens = max(3000, min(8192, $maxTokens + 2500));

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => $maxOutputTokens,
            ]
        ];

        if (!empty($systemParts)) {
            $payload['system_instruction'] = [
                'parts' => $systemParts
            ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key='
            . rawurlencode($apiKey);

        $response = $this->requestJson(
            $url,
            $payload,
            ['Content-Type: application/json']
        );

        if (!$response['ok']) {
            return ['ok' => false, 'content' => '', 'error' => $response['error']];
        }

        $parts = $response['data']['candidates'][0]['content']['parts'] ?? [];
        $texts = [];
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $texts[] = $part['text'];
            }
        }
        $content = trim(implode('', $texts));

        if ($content === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Gemini trả về nội dung rỗng.'];
        }

        return ['ok' => true, 'content' => $content, 'error' => null, 'model' => $model];
    }

    private function buildGeminiPrompt(array $messages): string
    {
        $lines = [];
        foreach ($messages as $msg) {
            $role = strtoupper((string) ($msg['role'] ?? 'USER'));
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content !== '') {
                $lines[] = $role . ': ' . $content;
            }
        }
        return implode("\n\n", $lines);
    }

    private function requestJson(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $this->applyProxy($ch);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Lỗi kết nối AI: ' . $error, 'status' => 0, 'data' => null];
        }

        $data = json_decode((string) $raw, true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string) $raw;
            return ['ok' => false, 'error' => 'AI HTTP ' . $status . ': ' . $message, 'status' => $status, 'data' => $data];
        }

        return ['ok' => true, 'error' => null, 'status' => $status, 'data' => is_array($data) ? $data : []];
    }

    private function applyProxy($ch): void
    {
        $proxy = trim((string) ($this->settings['ai_proxy'] ?? ''));
        if ($proxy === '') {
            $proxy = trim((string) (
                getenv('AI_PROXY') ?:
                getenv('HTTPS_PROXY') ?:
                getenv('HTTP_PROXY') ?:
                getenv('ALL_PROXY') ?: ''
            ));
        }

        if ($proxy === '') {
            $proxy = $this->detectLocalDevProxy();
        }

        if ($proxy !== '') {
            if (preg_match('/^socks5:\/\//i', $proxy)) {
                $proxy = preg_replace('/^socks5:\/\//i', 'socks5h://', $proxy);
            } elseif (!preg_match('/^[a-z0-9]+:\/\//i', $proxy) && (str_contains($proxy, '10808') || str_contains($proxy, '1080'))) {
                $proxy = 'socks5h://' . $proxy;
            }
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
    }

    private function detectLocalDevProxy(): string
    {
        $ports = [
            ['port' => 10808, 'type' => 'socks5h://'],
            ['port' => 7890,  'type' => 'http://'],
            ['port' => 10809, 'type' => 'http://'],
        ];

        foreach ($ports as $p) {
            $fp = @fsockopen('127.0.0.1', $p['port'], $errno, $errstr, 0.05);
            if ($fp) {
                fclose($fp);
                return $p['type'] . '127.0.0.1:' . $p['port'];
            }
        }

        return '';
    }

    private function resolveConfigValue(string $settingKey, string $envKey, array $settingAliases = []): string
    {
        $settingValue = trim((string) ($this->settings[$settingKey] ?? ''));
        if ($settingValue !== '') {
            return $settingValue;
        }

        foreach ($settingAliases as $alias) {
            $aliasValue = trim((string) ($this->settings[$alias] ?? ''));
            if ($aliasValue !== '') {
                return $aliasValue;
            }
        }

        $envValue = trim((string) (getenv($envKey) ?: ''));
        return $envValue;
    }
}
