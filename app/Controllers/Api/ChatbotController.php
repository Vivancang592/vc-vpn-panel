<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Services\ChatbotService;

class ChatbotController extends BaseController
{
    public function message(): void
    {
        $settings = (new Setting())->getAllAsKeyValue();
        $chatbotEnabledRaw = trim((string) ($settings['ai_chatbot_enabled'] ?? '1'));
        $chatbotEnabled = ($chatbotEnabledRaw === '' || $chatbotEnabledRaw === '1');
        if (!$chatbotEnabled) {
            $this->json([
                'success' => false,
                'message' => 'Trợ lý AI hiện đang tạm tắt.'
            ], 503);
            return;
        }

        $cooldownSeconds = (float) ($settings['ai_cooldown_seconds'] ?? 1.5);
        $cooldownSeconds = max(0.5, min(6.0, $cooldownSeconds));
        $maxPerMinute = (int) ($settings['ai_rate_limit_per_minute'] ?? 8);
        $maxPerMinute = max(3, min(30, $maxPerMinute));
        $duplicateWindow = (int) ($settings['ai_duplicate_window_seconds'] ?? 4);
        $duplicateWindow = max(2, min(20, $duplicateWindow));

        $payload = $this->parseInput();
        $message = trim((string) ($payload['message'] ?? ''));
        $page = trim((string) ($payload['page'] ?? ''));

        if (!$this->canProcessRequest($cooldownSeconds, $maxPerMinute)) {
            $this->json([
                'success' => false,
                'message' => 'Bạn đang gửi quá nhanh. Vui lòng chờ vài giây rồi thử lại.'
            ], 429);
            return;
        }

        if ($message === '') {
            $this->json([
                'success' => false,
                'message' => 'Nội dung câu hỏi không được để trống.'
            ], 422);
            return;
        }

        if (mb_strlen($message) > 1200) {
            $this->json([
                'success' => false,
                'message' => 'Câu hỏi quá dài. Vui lòng rút gọn dưới 1200 ký tự.'
            ], 422);
            return;
        }

        // Chặn gửi lặp lại liên tục cùng một nội dung trong thời gian rất ngắn.
        $hash = sha1(mb_strtolower($message));
        $now = time();
        $lastHash = (string) ($_SESSION['chatbot_last_message_hash'] ?? '');
        $lastAt = (int) ($_SESSION['chatbot_last_message_at'] ?? 0);
        if ($hash === $lastHash && ($now - $lastAt) < $duplicateWindow) {
            $cachedReply = trim((string) ($_SESSION['chatbot_last_reply'] ?? ''));
            if ($cachedReply !== '') {
                $this->json([
                    'success' => true,
                    'data' => [
                        'answer' => $cachedReply,
                        'handoff' => (bool) ($_SESSION['chatbot_last_handoff'] ?? false),
                        'provider' => (string) ($_SESSION['chatbot_last_provider'] ?? 'cache'),
                        'cta' => $_SESSION['chatbot_last_cta'] ?? null,
                        'history' => []
                    ]
                ]);
                return;
            }
        }

        $source = 'web';
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $sessionRow = $this->resolveSession($source, null, $userId);

        $history = [];
        if (!empty($sessionRow['id'])) {
            $messageModel = new ChatMessage();
            $historyRows = $messageModel->getRecentBySession((int) $sessionRow['id'], 20);
            foreach ($historyRows as $row) {
                $history[] = [
                    'role' => (string) ($row['role'] ?? 'user'),
                    'content' => (string) ($row['content'] ?? ''),
                    'at' => (string) ($row['created_at'] ?? '')
                ];
            }
            $messageModel->add((int) $sessionRow['id'], 'user', $message);
            $history[] = [
                'role' => 'user',
                'content' => $message,
                'at' => date('Y-m-d H:i:s')
            ];
        }

        $service = new ChatbotService();
        $result = $service->reply($message, [
            'source' => $source,
            'page' => $page,
            'history' => $history,
            'user_id' => (int) ($userId ?? 0),
            'session_id' => (int) ($sessionRow['id'] ?? 0)
        ]);

        if (!empty($sessionRow['id'])) {
            $answer = (string) ($result['answer'] ?? '');
            (new ChatMessage())->add(
                (int) $sessionRow['id'],
                'assistant',
                $answer,
                (string) ($result['provider'] ?? ''),
                (string) ($result['model'] ?? '')
            );
            if (!empty($result['handoff'])) {
                (new ChatSession())->update((int) $sessionRow['id'], ['status' => 'handoff']);
            }
        }

        $historyOut = [];
        if (!empty($sessionRow['id'])) {
            $rows = (new ChatMessage())->getRecentBySession((int) $sessionRow['id'], 20);
            foreach ($rows as $row) {
                $historyOut[] = [
                    'role' => (string) ($row['role'] ?? 'user'),
                    'content' => (string) ($row['content'] ?? ''),
                    'at' => (string) ($row['created_at'] ?? '')
                ];
            }
        }

        $_SESSION['chatbot_last_message_hash'] = $hash;
        $_SESSION['chatbot_last_message_at'] = $now;
        $_SESSION['chatbot_last_reply'] = (string) ($result['answer'] ?? '');
        $_SESSION['chatbot_last_handoff'] = (bool) ($result['handoff'] ?? false);
        $_SESSION['chatbot_last_provider'] = (string) ($result['provider'] ?? '');
        $_SESSION['chatbot_last_cta'] = $result['cta'] ?? null;

        $this->json([
            'success' => true,
            'data' => [
                'answer' => (string) ($result['answer'] ?? ''),
                'handoff' => (bool) ($result['handoff'] ?? false),
                'provider' => (string) ($result['provider'] ?? ''),
                'cta' => $result['cta'] ?? null,
                'history' => $historyOut
            ]
        ]);
    }

    public function history(): void
    {
        $sessionRow = $this->resolveSession('web', null, isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
        $history = [];

        if (!empty($sessionRow['id'])) {
            $rows = (new ChatMessage())->getRecentBySession((int) $sessionRow['id'], 20);
            foreach ($rows as $row) {
                $history[] = [
                    'role' => (string) ($row['role'] ?? 'user'),
                    'content' => (string) ($row['content'] ?? ''),
                    'at' => (string) ($row['created_at'] ?? '')
                ];
            }
        }

        $this->json([
            'success' => true,
            'data' => [
                'history' => array_slice($history, -20)
            ]
        ]);
    }

    public function event(): void
    {
        $payload = $this->parseInput();
        $eventName = trim((string) ($payload['event_name'] ?? ''));
        $source = trim((string) ($payload['source'] ?? 'web'));
        $meta = $payload['meta'] ?? [];

        $allowedEvents = [
            'chat_opened',
            'cta_shown',
            'cta_clicked',
            'checkout_clicked',
            'handoff_requested',
            'fanpage_inbound',
            'ai_reply',
            'ai_failed',
        ];

        if ($eventName === '' || !in_array($eventName, $allowedEvents, true)) {
            $this->json(['success' => false, 'message' => 'event_name is required'], 422);
            return;
        }

        $sessionRow = $this->resolveSession($source === 'fanpage' ? 'fanpage' : 'web', null, isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
        $sessionId = !empty($sessionRow['id']) ? (int) $sessionRow['id'] : null;
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        (new ChatEvent())->track(
            $eventName,
            $source === 'fanpage' ? 'fanpage' : 'web',
            $sessionId,
            $userId,
            $this->getClientIp(),
            $this->sanitizeMeta(is_array($meta) ? $meta : [])
        );

        $this->json(['success' => true]);
    }

    private function parseInput(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode((string) $raw, true);
        if (is_array($json)) {
            return $json;
        }

        return $_POST;
    }

    private function resolveSession(string $source, ?string $externalId = null, ?int $userId = null): ?array
    {
        if (empty($_SESSION['chatbot_visitor_token'])) {
            $_SESSION['chatbot_visitor_token'] = bin2hex(random_bytes(12));
        }

        $visitorToken = (string) $_SESSION['chatbot_visitor_token'];
        $source = $source === 'fanpage' ? 'fanpage' : 'web';

        $sessionModel = new ChatSession();
        return $sessionModel->getOrCreate($visitorToken, $source, $userId, $externalId);
    }

    private function sanitizeMeta(array $meta): array
    {
        $clean = [];
        $count = 0;
        foreach ($meta as $key => $value) {
            if ($count >= 12) {
                break;
            }
            $k = mb_substr(trim((string) $key), 0, 40);
            if ($k === '') {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$k] = mb_substr(trim((string) $value), 0, 300);
            } else {
                $clean[$k] = mb_substr(json_encode($value, JSON_UNESCAPED_UNICODE), 0, 300);
            }
            $count++;
        }

        return $clean;
    }

    private function canProcessRequest(float $cooldownSeconds, int $maxPerMinute): bool
    {
        $now = microtime(true);
        $windowStart = (float) ($_SESSION['chatbot_rate_window_start'] ?? $now);
        $windowCount = (int) ($_SESSION['chatbot_rate_window_count'] ?? 0);
        $lastAt = (float) ($_SESSION['chatbot_rate_last_at'] ?? 0.0);

        // Cooldown giữa 2 lần gọi để tránh spam liên tiếp.
        if ($lastAt > 0 && ($now - $lastAt) < $cooldownSeconds) {
            return false;
        }

        // Tối đa N request trong 60 giây cho mỗi session.
        if (($now - $windowStart) > 60) {
            $windowStart = $now;
            $windowCount = 0;
        }

        $windowCount++;

        $_SESSION['chatbot_rate_window_start'] = $windowStart;
        $_SESSION['chatbot_rate_window_count'] = $windowCount;
        $_SESSION['chatbot_rate_last_at'] = $now;

        return $windowCount <= $maxPerMinute;
    }
}
