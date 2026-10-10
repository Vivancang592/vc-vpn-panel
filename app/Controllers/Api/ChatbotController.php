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
        $pageTitle = mb_substr(trim((string) ($payload['page_title'] ?? '')), 0, 150);

        $rateCheck = $this->canProcessRequest($cooldownSeconds, $maxPerMinute);
        if (!$rateCheck['allowed']) {
            $this->json([
                'success' => false,
                'message' => $rateCheck['message']
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
        $isLoggedIn = !empty($userId);
        $userName = !empty($_SESSION['username']) ? (string) $_SESSION['username'] : (!empty($_SESSION['name']) ? (string) $_SESSION['name'] : '');
        $sessionRow = $this->resolveSession($source, null, $userId);

        $history = [];
        $historyOffset = 0;
        $messageModel = new ChatMessage();
        if (!empty($sessionRow['id'])) {
            // BƯỚC 3.1: đưa tối đa 100 tin (model tự giữ trong ngân sách token)
            // để AI nhớ toàn bộ cuộc hội thoại thay vì 8 tin × 500 ký tự như cũ.
            $historyRows = $messageModel->getRecentBySession((int) $sessionRow['id'], 100);
            foreach ($historyRows as $row) {
                $history[] = [
                    'role' => (string) ($row['role'] ?? 'user'),
                    'content' => (string) ($row['content'] ?? ''),
                    'at' => (string) ($row['created_at'] ?? '')
                ];
            }
            // Số tin cũ đã trôi khỏi cửa sổ 100 tin (đếm TRƯỚC khi thêm tin này)
            // → AI tóm tắt theo TOÀN PHIÊN chứ không theo mảng đang cầm.
            $historyOffset = max(0, $messageModel->countBySession((int) $sessionRow['id']) - count($history));
            // Lưu tin của khách vào CSDL. KHÔNG append vào $history ở đây:
            // ChatbotService::reply() tự thêm tin hiện tại → tránh lặp 2 lần.
            $messageModel->add((int) $sessionRow['id'], 'user', $message);
        }

        $service = new ChatbotService();
        $result = $service->reply($message, [
            'source' => $source,
            'page' => $page,
            'page_title' => $pageTitle,
            'history' => $history,
            'history_offset' => $historyOffset,
            'user_id' => (int) ($userId ?? 0),
            'is_logged_in' => $isLoggedIn,
            'user_name' => $userName,
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
                $sessionId = (int) $sessionRow['id'];
                if ((new ChatSession())->markHandoff($sessionId)) {
                    (new ChatEvent())->track(
                        'handoff_requested',
                        'web',
                        $sessionId,
                        $userId,
                        $this->getClientIp(),
                        ['reason' => 'ai_handoff']
                    );
                }
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
        $history = [];
        $lastRole = '';
        $idleRemainingSeconds = 0;
        $sessionRow = null;

        // Chỉ đọc phiên đang gắn với trình duyệt này. Không gọi getOrCreate()
        // vì tải lại trang không được phép mở lại hoặc tạo phiên mới.
        $visitorToken = (string) ($_SESSION['chatbot_visitor_token'] ?? '');
        if ($visitorToken !== '') {
            $sessionModel = new ChatSession();
            $sessionModel->sweepIdle(300);
            $sessionRow = $sessionModel->findByVisitor($visitorToken, 'web');
        }

        if ($sessionRow && ($sessionRow['status'] ?? '') === 'open' && !empty($sessionRow['id'])) {
            $rows = (new ChatMessage())->getRecentBySession((int) $sessionRow['id'], 20);
            foreach ($rows as $row) {
                $history[] = [
                    'role' => (string) ($row['role'] ?? 'user'),
                    'content' => (string) ($row['content'] ?? ''),
                    'at' => (string) ($row['created_at'] ?? '')
                ];
            }

            $lastMessage = end($rows);
            $lastRole = (string) ($lastMessage['role'] ?? '');
            if ($lastRole === 'assistant' && !empty($lastMessage['created_at'])) {
                $elapsed = max(0, time() - (int) strtotime((string) $lastMessage['created_at']));
                $idleRemainingSeconds = max(0, 300 - $elapsed);
            }
        }

        $this->json([
            'success' => true,
            'data' => [
                'history' => array_slice($history, -20),
                'status' => (string) ($sessionRow['status'] ?? ''),
                'last_role' => $lastRole,
                'idle_remaining_seconds' => $idleRemainingSeconds
            ]
        ]);
    }

    public function reset(): void
    {
        // Đóng phiên hiện tại (nếu còn 'open') trước khi xoay token — hội thoại
        // cũ không bị "treo" trạng thái mở và AI bắt đầu bộ nhớ sạch ở lần sau.
        // Phiên 'handoff' giữ nguyên chờ nhân viên bấm "Đã xử lý" để đóng.
        $oldToken = (string) ($_SESSION['chatbot_visitor_token'] ?? '');
        if ($oldToken !== '') {
            try {
                $sessionModel = new ChatSession();
                $oldSession = $sessionModel->findByVisitor($oldToken, 'web');
                if ($oldSession && ($oldSession['status'] ?? '') === 'open') {
                    $sessionModel->update((int) $oldSession['id'], ['status' => 'closed']);
                }
            } catch (\Throwable $e) {
                // Không chặn reset chat nếu thao tác DB gặp lỗi.
            }
        }

        unset(
            $_SESSION['chatbot_visitor_token'],
            $_SESSION['chatbot_last_message_hash'],
            $_SESSION['chatbot_last_message_at'],
            $_SESSION['chatbot_last_reply'],
            $_SESSION['chatbot_last_handoff'],
            $_SESSION['chatbot_last_provider'],
            $_SESSION['chatbot_last_cta'],
            $_SESSION['chatbot_rate_window_start'],
            $_SESSION['chatbot_rate_window_count'],
            $_SESSION['chatbot_rate_last_at']
        );
        $_SESSION['chatbot_visitor_token'] = bin2hex(random_bytes(12));

        $this->json([
            'success' => true,
            'message' => 'Đã làm mới cuộc hội thoại thành công.'
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

        // Lazy sweep: hội thoại AI trả lời mà khách không phản hồi lại quá 5
        // phút → tự động đóng. Chạy tối đa 1 lần/60s cho mỗi phiên trình duyệt
        // để không execute UPDATE thừa ở mỗi request.
        $lastSweep = (int) ($_SESSION['chatbot_sweep_at'] ?? 0);
        if ((time() - $lastSweep) >= 60) {
            $_SESSION['chatbot_sweep_at'] = time();
            try {
                (new ChatSession())->sweepIdle(300);
            } catch (\Throwable $e) {
                // Sweep lỗi không được chặn luồng chat.
            }
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

    private function canProcessRequest(float $cooldownSeconds, int $maxPerMinute): array
    {
        $now = microtime(true);
        $windowStart = (float) ($_SESSION['chatbot_rate_window_start'] ?? $now);
        $windowCount = (int) ($_SESSION['chatbot_rate_window_count'] ?? 0);
        $lastAt = (float) ($_SESSION['chatbot_rate_last_at'] ?? 0.0);

        // Cooldown giữa 2 lần gửi tin liên tiếp
        if ($lastAt > 0 && ($now - $lastAt) < $cooldownSeconds) {
            $wait = ceil($cooldownSeconds - ($now - $lastAt));
            return [
                'allowed' => false,
                'message' => 'Bạn đang gửi quá nhanh. Vui lòng chờ khoảng ' . max(1, (int)$wait) . ' giây rồi thử lại nhé.'
            ];
        }

        // Tự động làm mới chu kỳ đếm sau mỗi 60 giây
        if (($now - $windowStart) >= 60) {
            $windowStart = $now;
            $windowCount = 0;
        }

        // Giới hạn số câu hỏi trong cửa sổ 60s
        if ($windowCount >= $maxPerMinute) {
            $wait = ceil(60 - ($now - $windowStart));
            return [
                'allowed' => false,
                'message' => 'Bạn đã gửi ' . $maxPerMinute . ' tin trong 1 phút. Hệ thống sẽ tự động mở lại sau ' . max(1, (int)$wait) . ' giây nữa, bạn chờ chút nhé!'
            ];
        }

        $windowCount++;

        $_SESSION['chatbot_rate_window_start'] = $windowStart;
        $_SESSION['chatbot_rate_window_count'] = $windowCount;
        $_SESSION['chatbot_rate_last_at'] = $now;

        return ['allowed' => true, 'message' => ''];
    }
}
