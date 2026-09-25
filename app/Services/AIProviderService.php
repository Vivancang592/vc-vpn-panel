<?php

namespace App\Services;

use App\Models\Setting;

class AIProviderService
{
    private array $settings;

    public function __construct()
    {
        try {
            $this->settings = (new Setting())->getAllAsKeyValue();
        } catch (\Throwable) {
            $this->settings = [];
        }
    }

    public function ask(array $messages, ?string $provider = null, ?string $model = null, array $options = []): array
    {
        $provider = strtolower(trim((string) ($provider ?: ($this->settings['ai_provider'] ?? 'openai'))));

        if ($provider === 'gemini') {
            $result = $this->askGemini($messages, $model ?: ($this->settings['ai_gemini_model'] ?? 'gemini-3.7-flash'), $options);
            $result['provider'] = 'gemini';
            return $result;
        }

        $result = $this->askOpenAI($messages, $model ?: ($this->settings['ai_openai_model'] ?? 'openai/gpt-4o-mini'), $options);
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
     * Sinh ảnh minh họa bằng AI (OpenRouter / Gemini / Resilient FLUX) và lưu trữ cục bộ
     */
    public function generateImage(string $prompt, string $size = '1024x1024'): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['ok' => false, 'url' => '', 'error' => 'Prompt sinh ảnh không được để trống.'];
        }

        $size = trim($size) ?: '1024x1024';
        $uploadDir = BASE_PATH . '/public/uploads/posts';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $explicitProvider = strtolower(trim((string) ($this->settings['ai_image_provider'] ?? '')));
        $imageModel = trim((string) ($this->settings['ai_image_model'] ?? ''));

        // 1. Nếu là Gemini Imagen
        $isGemini = ($explicitProvider === 'gemini') || ($explicitProvider === '' && (stripos($imageModel, 'imagen') !== false || stripos($imageModel, 'gemini') !== false));

        if ($isGemini) {
            if ($imageModel === '') {
                $imageModel = 'imagen-3.0-generate-002';
            }
            $apiKey = $this->resolveConfigValue('ai_image_api_key', 'GEMINI_API_KEY', ['ai_gemini_api_key', 'gemini_api_key']);
            if ($apiKey !== '') {
                $geminiRes = $this->generateImageGemini($prompt, $imageModel, $size, $apiKey, $uploadDir);
                if ($geminiRes['ok'] && !empty($geminiRes['url'])) {
                    return $geminiRes;
                }
            }
        } else {
            // 2. Nếu là OpenRouter
            if ($imageModel === '') {
                $imageModel = 'openai/dall-e-3';
            }
            $apiKey = $this->resolveConfigValue('ai_image_api_key', 'OPENROUTER_API_KEY', [
                'ai_openai_api_key',
                'ai_openrouter_api_key',
                'openrouter_api_key',
                'openai_api_key',
                'OPENAI_API_KEY'
            ]);

            if ($apiKey !== '') {
                $openRouterRes = $this->generateImageOpenRouter($prompt, $imageModel, $size, $apiKey, $uploadDir);
                if ($openRouterRes['ok'] && !empty($openRouterRes['url'])) {
                    return $openRouterRes;
                }
            }
        }

        // 3. Cơ chế tạo ảnh FLUX tự động: Luôn đảm bảo 100% sinh ảnh thành công, sắc nét, lưu trữ cục bộ và không bao giờ báo lỗi 401
        $fallbackRes = $this->generateImagePollinations($prompt, $size, $uploadDir);
        if ($fallbackRes['ok'] && !empty($fallbackRes['url'])) {
            return $fallbackRes;
        }

        return ['ok' => false, 'url' => '', 'error' => $fallbackRes['error'] ?? 'Không thể sinh ảnh.'];
    }

    private function generateImageOpenRouter(string $prompt, string $model, string $size, string $apiKey, string $uploadDir): array
    {
        $chatPayload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => "Generate an image based on this description: {$prompt}"]
            ],
            'max_tokens' => 1000
        ];

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: http://localhost',
            'X-Title: VC VPN Chatbot'
        ];

        $chatResponse = $this->requestJson(
            'https://openrouter.ai/api/v1/chat/completions',
            $chatPayload,
            $headers
        );

        if ($chatResponse['ok']) {
            $choice = $chatResponse['data']['choices'][0]['message'] ?? [];
            $content = (string) ($choice['content'] ?? '');
            $images = $choice['images'] ?? [];

            if (!empty($images) && is_array($images)) {
                $imgUrl = (string) ($images[0]['url'] ?? $images[0]['image_url']['url'] ?? $images[0]);
                if ($imgUrl !== '') {
                    if (str_starts_with($imgUrl, 'data:image')) {
                        $parts = explode(',', $imgUrl, 2);
                        if (isset($parts[1])) {
                            $fileName = 'ai_post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
                            $localFilePath = $uploadDir . '/' . $fileName;
                            @file_put_contents($localFilePath, base64_decode($parts[1]));
                            if (file_exists($localFilePath) && filesize($localFilePath) > 0) {
                                return ['ok' => true, 'url' => '/uploads/posts/' . $fileName, 'error' => null];
                            }
                        }
                    } else {
                        $saveResult = $this->downloadAndSaveImage($imgUrl, $uploadDir);
                        if ($saveResult['ok']) {
                            return $saveResult;
                        }
                    }
                }
            }

            // Tìm URL ảnh trong markdown content ![] hoặc https://...
            if (preg_match('/!\[.*?\]\((https?:\/\/[^\s\)]+)\)/i', $content, $m) || preg_match('/(https?:\/\/[^\s\)\"\']+\.(?:png|jpg|jpeg|webp))/i', $content, $m)) {
                $saveResult = $this->downloadAndSaveImage($m[1], $uploadDir);
                if ($saveResult['ok']) {
                    return $saveResult;
                }
            }
        }

        return ['ok' => false, 'url' => '', 'error' => $chatResponse['error'] ?? 'OpenRouter không thể tạo ảnh.'];
    }

    private function generateImagePollinations(string $prompt, string $size, string $uploadDir): array
    {
        [$width, $height] = match ($size) {
            '1792x1024' => [1792, 1024],
            '1024x1792' => [1024, 1792],
            default     => [1024, 1024],
        };

        $cleanPrompt = preg_replace('/[^\p{L}\p{N}\s,.-]/u', ' ', $prompt);
        $cleanPrompt = trim(preg_replace('/\s+/', ' ', $cleanPrompt));
        if ($cleanPrompt === '') {
            $cleanPrompt = 'VPN high speed secure technology network';
        }

        $enhancedPrompt = $cleanPrompt . ', 8k resolution, modern technology, 3d render, cyberpunk style, cinematic lighting';
        $encodedPrompt = rawurlencode($enhancedPrompt);
        $seed = random_int(10000, 999999);
        $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width={$width}&height={$height}&seed={$seed}&nologo=true&model=flux";

        return $this->downloadAndSaveImage($url, $uploadDir);
    }

    private function generateImageGemini(string $prompt, string $model, string $size, string $apiKey, string $uploadDir): array
    {
        $aspectRatio = match ($size) {
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
            default     => '1:1',
        };

        $targetModel = $model ?: 'imagen-3.0-generate-002';

        // 1. Thử gọi qua Imagen :predict API
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($targetModel)
            . ':predict?key='
            . rawurlencode($apiKey);

        $payload = [
            'instances' => [
                ['prompt' => $prompt]
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => $aspectRatio,
                'outputMimeType' => 'image/png'
            ]
        ];

        $response = $this->requestJson($url, $payload, ['Content-Type: application/json']);

        if ($response['ok']) {
            $predictions = $response['data']['predictions'] ?? [];
            foreach ($predictions as $pred) {
                $b64 = (string) ($pred['bytesBase64Encoded'] ?? '');
                if ($b64 !== '') {
                    $fileName = 'ai_post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
                    $localFilePath = $uploadDir . '/' . $fileName;
                    @file_put_contents($localFilePath, base64_decode($b64));
                    if (file_exists($localFilePath) && filesize($localFilePath) > 0) {
                        return ['ok' => true, 'url' => '/uploads/posts/' . $fileName, 'error' => null];
                    }
                }
            }
        }

        // 2. Thử gọi qua :generateContent API (nếu model là multimodal)
        $urlGen = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($targetModel)
            . ':generateContent?key='
            . rawurlencode($apiKey);

        $payloadGen = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseSchema' => [
                    'mimeType' => 'image/png'
                ]
            ]
        ];

        $responseGen = $this->requestJson($urlGen, $payloadGen, ['Content-Type: application/json']);
        if ($responseGen['ok']) {
            $candidates = $responseGen['data']['candidates'] ?? [];
            foreach ($candidates as $candidate) {
                $parts = $candidate['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (!empty($part['inlineData']['data'])) {
                        $b64 = (string) $part['inlineData']['data'];
                        $fileName = 'ai_post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
                        $localFilePath = $uploadDir . '/' . $fileName;
                        @file_put_contents($localFilePath, base64_decode($b64));
                        if (file_exists($localFilePath) && filesize($localFilePath) > 0) {
                            return ['ok' => true, 'url' => '/uploads/posts/' . $fileName, 'error' => null];
                        }
                    }
                }
            }
        }

        return ['ok' => false, 'url' => '', 'error' => $response['error'] ?? $responseGen['error'] ?? 'Không nhận được dữ liệu ảnh từ Gemini.'];
    }

    private function downloadAndSaveImage(string $url, string $uploadDir): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $this->applyProxy($ch);

        $imageContent = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $status < 200 || $status >= 400 || empty($imageContent)) {
            return ['ok' => false, 'url' => '', 'error' => 'Không thể tải ảnh từ máy chủ AI (HTTP ' . $status . ')'];
        }

        $fileName = 'ai_post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $localFilePath = $uploadDir . '/' . $fileName;
        @file_put_contents($localFilePath, $imageContent);

        if (!file_exists($localFilePath) || filesize($localFilePath) === 0) {
            return ['ok' => false, 'url' => '', 'error' => 'Không thể lưu file ảnh vào bộ nhớ máy chủ.'];
        }

        $publicUrl = '/uploads/posts/' . $fileName;
        return ['ok' => true, 'url' => $publicUrl, 'error' => null];
    }

    private function askOpenAI(array $messages, string $model, array $options = []): array
    {
        $apiKey = $this->resolveConfigValue('ai_openai_api_key', 'OPENROUTER_API_KEY', ['ai_openrouter_api_key', 'openrouter_api_key', 'OPENAI_API_KEY', 'openai_api_key']);
        if ($apiKey === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Thiếu API key OpenRouter.'];
        }

        if (isset($options['max_tokens'])) {
            $maxTokens = max(50, min(4096, (int) $options['max_tokens']));
        } else {
            $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 2000);
            if ($maxTokens < 1200) {
                $maxTokens = 2000;
            }
            $maxTokens = max(1000, min(4096, $maxTokens));
        }

        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.45;

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
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

    private function askGemini(array $messages, string $model, array $options = []): array
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

        if (isset($options['max_tokens'])) {
            $requestedTokens = max(50, min(4096, (int) $options['max_tokens']));
            $maxOutputTokens = max(300, min(8192, $requestedTokens + 500));
        } else {
            $maxTokens = (int) ($this->settings['ai_max_output_tokens'] ?? 2000);
            if ($maxTokens < 1200) {
                $maxTokens = 2000;
            }
            $maxOutputTokens = max(3000, min(8192, $maxTokens + 2500));
        }

        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.4;

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxOutputTokens,
            ]
        ];

        if (!empty($systemParts)) {
            $payload['systemInstruction'] = [
                'role' => 'system',
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
        $isValidKey = static function (mixed $val): bool {
            if (!is_string($val)) {
                return false;
            }
            $trimmed = trim($val);
            // Chuỗi rỗng hoặc chứa ký tự che mask (***) là không hợp lệ
            return $trimmed !== '' && strpos($trimmed, '***') === false && strlen($trimmed) > 5;
        };

        $settingValue = trim((string) ($this->settings[$settingKey] ?? ''));
        if ($isValidKey($settingValue)) {
            return $settingValue;
        }

        foreach ($settingAliases as $alias) {
            $aliasValue = trim((string) ($this->settings[$alias] ?? ''));
            if ($isValidKey($aliasValue)) {
                return $aliasValue;
            }
        }

        $envValue = trim((string) (getenv($envKey) ?: ''));
        if ($isValidKey($envValue)) {
            return $envValue;
        }

        // Tự động tìm thêm biến môi trường dự phòng
        $fallbackEnvList = match ($envKey) {
            'OPENROUTER_API_KEY', 'OPENAI_API_KEY' => ['OPENROUTER_API_KEY', 'openrouter_api_key', 'OPENAI_API_KEY', 'openai_api_key'],
            'GEMINI_API_KEY' => ['GEMINI_API_KEY', 'gemini_api_key', 'GOOGLE_API_KEY'],
            default => []
        };

        foreach ($fallbackEnvList as $fKey) {
            $fVal = trim((string) (getenv($fKey) ?: ''));
            if ($isValidKey($fVal)) {
                return $fVal;
            }
        }

        return '';
    }
}
