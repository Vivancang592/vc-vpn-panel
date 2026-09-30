<?php

declare(strict_types=1);

namespace App\Services;

use App\AI\Contracts\AIResult;
use App\AI\Core\AICore;
use App\Models\Setting;

/**
 * AIProviderService — ADAPTER tương thích ngược cho LUỒNG AI MỚI (AI Core).
 *
 * Lịch sử: lớp này từng gọi thẳng OpenRouter/Gemini bằng HTTP.
 * Nay đã được CHUYỂN HƯỚNG: mọi lời gọi đều đi qua App\AI\Core\AICore
 * (Input → ModuleRegistry → PromptRegistry(file/DB/default) → ModelResolver
 *  → Provider(Kira) → ResponseParser → OutputHandler).
 *
 * Vai trò hiện tại: giữ nguyên chữ ký hàm cũ để các luồng nghiệp vụ
 * (ChatbotService, FanpageService, CronController, AiFanpageController)
 * KHÔNG phải viết lại, nhưng phần "ruột" đã chạy trên AI Core.
 *
 * Quy tắc:
 *  - KHÔNG còn API key OpenAI/Gemini ở đây.
 *  - KHÔNG hard-code tên model: ModelResolver quyết định.
 *  - Prompt do Admin cấu hình ở Trung Tâm AI (storage/prompts/{key}.txt) → AI đọc từ đó.
 *
 * @deprecated Dùng trực tiếp App\AI\Core\AICore cho code mới.
 *             Giữ lớp này làm cầu nối để tránh phá vỡ luồng nghiệp vụ hiện có.
 */
class AIProviderService
{
    /** @var array<string, mixed> */
    private array $settings;

    /** @var array<string, mixed> */
    private array $aiConfig;

    public function __construct()
    {
        try {
            $this->settings = (new Setting())->getAllAsKeyValue();
        } catch (\Throwable) {
            $this->settings = [];
        }

        $this->aiConfig = $this->loadAiConfig();
    }

    /**
     * Hội thoại đa lượt (chat). Chữ ký gốc được giữ nguyên.
     *
     * `$provider`/`$model` KHÔNG còn ý nghĩa (chỉ còn 1 provider Kira, model do
     * ModelResolver chọn). Vẫn nhận để không phá vỡ lời gọi cũ.
     *
     * @param array<int, array{role: string, content: mixed}> $messages
     * @param array<string, mixed> $options
     * @return array{ok: bool, content: string, error: ?string, provider?: string, model?: string}
     */
    public function ask(array $messages, ?string $provider = null, ?string $model = null, array $options = []): array
    {
        $module = (string) ($options['module'] ?? 'support_chat');

        // Lọc tham số truyền xuống provider (Kira hiểu các khoá này).
        $chatOptions = ['module' => $module];
        foreach (['temperature', 'top_p', 'max_tokens', 'stop', 'frequency_penalty', 'presence_penalty'] as $key) {
            if (array_key_exists($key, $options)) {
                $chatOptions[$key] = $options[$key];
            }
        }

        $result = $this->core()->chat($messages, $chatOptions);

        return $this->toLegacyText($result);
    }

    /**
     * Sinh nội dung bài đăng Fanpage (1 bài).
     *
     * Đi qua module `content_article` của AI Core → prompt đọc từ
     * storage/prompts/content_article.txt (Admin sửa được).
     *
     * P12: admin chỉ gửi DANH SÁCH CHỦ ĐỀ — AI tự phân tích và viết theo
     * chủ đề (không còn ô "Yêu cầu thêm"; hướng dẫn văn phong nằm trong
     * prompt hệ thống do admin sửa ở tab prompt).
     *
     * @return array{ok: bool, content: string, error: ?string, provider?: string, model?: string}
     */
    public function generateContent(string $topic): array
    {
        $result = $this->core()->run('content_article', ['topic' => $topic]);

        return $this->toLegacyText($result);
    }

    /**
     * Sinh ảnh minh hoạ qua AI Core (module `image_generation`), lưu cục bộ.
     *
     * @return array{ok: bool, url: string, error: ?string}
     */
    public function generateImage(string $prompt, string $size = '1024x1024'): array
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            return ['ok' => false, 'url' => '', 'error' => 'Prompt sinh ảnh không được để trống.'];
        }

        $size = trim($size) !== '' ? trim($size) : '1024x1024';

        $result = $this->core()->run('image_generation', ['prompt' => $prompt], ['size' => $size]);

        if (!$result->isOk()) {
            return [
                'ok'    => false,
                'url'   => '',
                'error' => $result->errorMessage() ?? 'AI Core không thể sinh ảnh.',
            ];
        }

        $file = is_array($result->files) ? ($result->files[0] ?? null) : null;
        $url = $this->persistImage(is_array($file) ? $file : []);

        if ($url === null) {
            return ['ok' => false, 'url' => '', 'error' => 'Không lưu được ảnh do AI trả về.'];
        }

        return ['ok' => true, 'url' => $url, 'error' => null];
    }

    // -----------------------------------------------------------------
    // Nội bộ
    // -----------------------------------------------------------------

    private function core(): AICore
    {
        return new AICore($this->aiConfig);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAiConfig(): array
    {
        $file = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/config/ai.php';

        if (!is_file($file)) {
            return [];
        }

        $config = require $file;

        return is_array($config) ? $config : [];
    }

    /**
     * Chuyển AIResult → mảng kết quả legacy (giữ hợp đồng cũ).
     *
     * @return array{ok: bool, content: string, error: ?string, provider?: string, model?: string}
     */
    private function toLegacyText(AIResult $result): array
    {
        if (!$result->isOk()) {
            return [
                'ok'      => false,
                'content' => '',
                'error'   => (string) ($result->errorMessage() ?? 'AI Core xử lý thất bại.'),
            ];
        }

        $content = trim((string) ($result->content ?? ''));

        if ($content === '') {
            return [
                'ok'      => false,
                'content' => '',
                'error'   => 'AI Core trả về nội dung rỗng.',
            ];
        }

        return [
            'ok'       => true,
            'content'  => $content,
            'error'    => null,
            'provider' => (string) ($result->provider ?? 'kira'),
            'model'    => (string) ($result->model ?? ''),
        ];
    }

    /**
     * Lưu ảnh AI trả về (URL hoặc base64) vào public/uploads/posts.
     *
     * @param array<string, mixed> $file
     */
    private function persistImage(array $file): ?string
    {
        $uploadDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/public/uploads/posts';

        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $binary = null;

        if (!empty($file['b64_json']) && is_string($file['b64_json'])) {
            $clean = (string) preg_replace('#^data:[^;]+;base64,#', '', $file['b64_json']);
            $decoded = base64_decode($clean, true);
            if ($decoded !== false && $decoded !== '') {
                $binary = $decoded;
            }
        } elseif (!empty($file['url']) && is_string($file['url'])) {
            $binary = $this->download((string) $file['url']);
        }

        if ($binary === null || $binary === '') {
            return null;
        }

        $fileName = 'ai_post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $path = $uploadDir . '/' . $fileName;

        if (@file_put_contents($path, $binary) === false || !is_file($path) || filesize($path) === 0) {
            return null;
        }

        return '/uploads/posts/' . $fileName;
    }

    private function download(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            $raw = @file_get_contents($url);

            return is_string($raw) && $raw !== '' ? $raw : null;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

        $proxy = trim((string) ($this->settings['ai_proxy'] ?? getenv('AI_PROXY') ?: ''));
        if ($proxy !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }

        $data = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $status < 200 || $status >= 400 || !is_string($data) || $data === '') {
            return null;
        }

        return $data;
    }
}
