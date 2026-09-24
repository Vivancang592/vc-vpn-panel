<?php

namespace App\Services;

use App\Models\ChatEvent;
use App\Models\ChatAiCache;
use App\Models\Post;
use App\Models\Setting;
use App\Models\VpnPlan;

class ChatbotService
{
    private array $settings;
    private AIProviderService $provider;

    public function __construct()
    {
        $this->settings = (new Setting())->getAllAsKeyValue();
        $this->provider = new AIProviderService();
    }

    public function reply(string $message, array $context = []): array
    {
        $enabledRaw = trim((string) ($this->settings['ai_chatbot_enabled'] ?? '1'));
        $isEnabled = ($enabledRaw === '' || $enabledRaw === '1');
        if (!$isEnabled) {
            return [
                'success' => true,
                'answer' => 'Trợ lý AI hiện đang tạm tắt. Vui lòng liên hệ fanpage để được hỗ trợ trực tiếp.',
                'handoff' => true,
                'provider' => 'disabled',
                'model' => '',
                'cta' => [
                    'label' => 'Mở fanpage hỗ trợ',
                    'url' => $this->resolveSupportUrl()
                ],
            ];
        }

        $message = trim($message);
        if ($message === '') {
            return $this->fallbackResponse('Bạn hãy nhập câu hỏi cụ thể để mình hỗ trợ nhanh hơn nhé.');
        }

        $history = $context['history'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

        $systemPrompt = $this->buildSystemPrompt($context);
        $knowledge = $this->buildKnowledgeSnippet();
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'system', 'content' => $knowledge],
        ];

        foreach (array_slice($history, -6) as $item) {
            $role = (string) ($item['role'] ?? 'user');
            $content = trim((string) ($item['content'] ?? ''));
            if ($content !== '') {
                $content = mb_substr($content, 0, 360);
            }
            if ($content !== '' && in_array($role, ['user', 'assistant'], true)) {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 700)];

        $cacheTtl = (int) ($this->settings['ai_cache_ttl_minutes'] ?? 60);
        $cacheTtl = max(1, min(1440, $cacheTtl));
        $cacheKey = sha1(mb_strtolower(preg_replace('/\s+/', ' ', trim($message))));

        if (class_exists(ChatAiCache::class)) {
            $cached = (new ChatAiCache())->findFresh($cacheKey, $cacheTtl);
            if ($cached && !empty($cached['answer'])) {
                return [
                    'success' => true,
                    'answer' => (string) $cached['answer'],
                    'handoff' => $this->shouldHandoff($message, (string) $cached['answer']),
                    'provider' => 'cache',
                    'model' => (string) ($cached['model'] ?? ''),
                    'cta' => $this->buildCta($context),
                ];
            }
        }

        $preferredProvider = strtolower(trim((string) ($this->settings['ai_provider'] ?? 'openai')));
        $result = $this->provider->ask($messages, $preferredProvider);

        if (!$result['ok']) {
            $fallbackProvider = $preferredProvider === 'openai' ? 'gemini' : 'openai';
            $result = $this->provider->ask($messages, $fallbackProvider);
        }

        if (!$result['ok']) {
            $this->logEvent('ai_failed', [
                'provider' => $preferredProvider,
                'error' => (string) ($result['error'] ?? 'unknown'),
                'source' => (string) ($context['source'] ?? 'web'),
                'session_id' => (int) ($context['session_id'] ?? 0),
                'user_id' => (int) ($context['user_id'] ?? 0),
            ]);
            return $this->fallbackResponse('Hệ thống AI đang bận. Bạn có thể để lại nhu cầu, đội ngũ hỗ trợ sẽ phản hồi sớm nhất.');
        }

        $answer = trim((string) ($result['content'] ?? ''));
        $handoff = $this->shouldHandoff($message, $answer);

        if ($answer !== '' && class_exists(ChatAiCache::class)) {
            (new ChatAiCache())->upsert(
                $cacheKey,
                $message,
                $answer,
                (string) ($result['provider'] ?? ''),
                (string) ($result['model'] ?? '')
            );
        }

        $response = [
            'success' => true,
            'answer' => $answer,
            'handoff' => $handoff,
            'provider' => (string) ($result['provider'] ?? $preferredProvider),
            'model' => (string) ($result['model'] ?? ''),
            'cta' => $this->buildCta($context),
        ];

        $this->logEvent('ai_reply', [
            'provider' => $response['provider'],
            'handoff' => $handoff ? 1 : 0,
            'source' => (string) ($context['source'] ?? 'web'),
            'session_id' => (int) ($context['session_id'] ?? 0),
            'user_id' => (int) ($context['user_id'] ?? 0),
        ]);

        return $response;
    }

    private function buildSystemPrompt(array $context): string
    {
        $defaultPrompt = implode("\n", [
            'Bạn là trợ lý tư vấn dịch vụ VPN của hệ thống VC VPN.',
            'Mục tiêu: trả lời câu hỏi rõ ràng, ngắn gọn, thân thiện; hướng dẫn dùng dịch vụ; tăng khả năng người dùng mua gói phù hợp.',
            'Chính sách bán hàng: CHI TU VAN, KHONG tu phat coupon/giam gia.',
            'Nếu người dùng yêu cầu vượt chính sách, hãy giải thích và hướng dẫn liên hệ hỗ trợ.',
            'Nếu gặp vấn đề nhạy cảm (khiếu nại phức tạp, hoàn tiền, thanh toán lỗi nhiều lần), đề xuất chuyển nhân viên thật hỗ trợ.',
            'Luôn ưu tiên câu trả lời bằng tiếng Việt, có thể thêm các bước thao tác cụ thể.',
        ]);

        $custom = trim((string) ($this->settings['ai_system_prompt'] ?? ''));
        $source = trim((string) ($context['source'] ?? 'web'));

        return ($custom !== '' ? $custom : $defaultPrompt) . "\nNguon hoi thoai hien tai: " . $source . '.';
    }

    private function buildKnowledgeSnippet(): string
    {
        $planModel = new VpnPlan();
        $postModel = new Post();

        $plans = array_slice($planModel->getAllActive(), 0, 6);
        $tutorials = array_values(array_filter(
            array_slice($postModel->getAllPublished(), 0, 10),
            static fn(array $p): bool => ($p['type'] ?? '') === 'tutorial'
        ));

        $lines = ['Thong tin noi bo de tu van:'];

        if (!empty($plans)) {
            $lines[] = 'Danh sach goi VPN:';
            foreach ($plans as $plan) {
                $lines[] = '- ' . ($plan['name'] ?? 'Goi')
                    . ' | Gia: ' . number_format((float) ($plan['price'] ?? 0), 0, '.', ',') . ' VND'
                    . ' | Thoi han: ' . (int) ($plan['duration_days'] ?? 0) . ' ngay'
                    . ' | Thiet bi: ' . (int) ($plan['max_devices'] ?? 1);
            }
        }

        if (!empty($tutorials)) {
            $lines[] = 'Huong dan noi bat:';
            foreach (array_slice($tutorials, 0, 4) as $post) {
                $lines[] = '- ' . trim((string) ($post['title'] ?? ''));
            }
        }

        return mb_substr(implode("\n", $lines), 0, 2200);
    }

    private function shouldHandoff(string $question, string $answer): bool
    {
        $text = mb_strtolower($question . ' ' . $answer);
        $keywords = [
            'hoan tien',
            'khieu nai',
            'lua dao',
            'that bai',
            'khong thanh toan duoc',
            'doi nhan vien',
            'nguoi that',
            'support truc tiep'
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function buildCta(array $context): array
    {
        $page = strtolower(trim((string) ($context['page'] ?? '')));

        if ($page === 'plans' || $page === 'checkout') {
            return [
                'label' => 'Xem va thanh toan goi phu hop',
                'url' => '/user/plans'
            ];
        }

        return [
            'label' => 'Xem bang gia goi VPN',
            'url' => '/#bang-gia'
        ];
    }

    private function fallbackResponse(string $message): array
    {
        return [
            'success' => true,
            'answer' => $message,
            'handoff' => true,
            'provider' => 'fallback',
            'model' => '',
            'cta' => [
                'label' => 'Lien he fanpage ho tro',
                'url' => $this->resolveSupportUrl()
            ],
        ];
    }

    private function resolveSupportUrl(): string
    {
        $url = trim((string) ($this->settings['fanpage_url'] ?? ''));
        if ($url !== '' && preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        return '/faq';
    }

    private function logEvent(string $event, array $data): void
    {
        if (class_exists(ChatEvent::class)) {
            $sessionId = isset($data['session_id']) && (int) $data['session_id'] > 0 ? (int) $data['session_id'] : null;
            $userId = isset($data['user_id']) && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null;
            $source = (string) ($data['source'] ?? 'web');

            (new ChatEvent())->track(
                $event,
                $source === 'fanpage' ? 'fanpage' : 'web',
                $sessionId,
                $userId,
                null,
                $data
            );
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $basePath . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $line = date('Y-m-d H:i:s') . ' [' . $event . '] ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($dir . '/chatbot.log', $line, FILE_APPEND);
    }
}
