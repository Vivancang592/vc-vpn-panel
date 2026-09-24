<?php

namespace App\Services;

use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Setting;

class FanpageService
{
    private array $settings;
    private ChatbotService $chatbot;

    public function __construct()
    {
        $this->settings = (new Setting())->getAllAsKeyValue();
        $this->chatbot = new ChatbotService();
    }

    public function verifyToken(string $mode, string $token, string $challenge): ?string
    {
        if ($mode !== 'subscribe') {
            return null;
        }

        $expected = trim((string) ($this->settings['fanpage_verify_token'] ?? getenv('FANPAGE_VERIFY_TOKEN') ?: ''));
        if ($expected === '' || !hash_equals($expected, $token)) {
            return null;
        }

        return $challenge;
    }

    public function handleWebhook(array $payload): void
    {
        $enabledRaw = trim((string) ($this->settings['ai_chatbot_enabled'] ?? '1'));
        $isEnabled = ($enabledRaw === '' || $enabledRaw === '1');
        if (!$isEnabled) {
            return;
        }

        $entries = $payload['entry'] ?? [];
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $messagingEvents = $entry['messaging'] ?? [];
            if (!is_array($messagingEvents)) {
                continue;
            }

            foreach ($messagingEvents as $event) {
                $senderId = (string) ($event['sender']['id'] ?? '');
                $text = trim((string) ($event['message']['text'] ?? ''));
                $isEcho = (bool) ($event['message']['is_echo'] ?? false);

                if ($senderId === '' || $text === '' || $isEcho) {
                    continue;
                }

                if (mb_strlen($text) > 1200) {
                    $this->sendMessage($senderId, 'Tin nhắn của bạn hơi dài. Vui lòng rút gọn để mình hỗ trợ chính xác hơn.');
                    continue;
                }

                $session = $this->resolveFanpageSession($senderId);
                $sessionId = (int) ($session['id'] ?? 0);
                if ($sessionId > 0) {
                    $last = (new ChatMessage())->getLastBySession($sessionId);
                    if ($last) {
                        $lastText = trim((string) ($last['content'] ?? ''));
                        $lastRole = (string) ($last['role'] ?? '');
                        $lastAt = strtotime((string) ($last['created_at'] ?? '')) ?: 0;

                        if ($lastRole === 'user' && $lastText !== '' && mb_strtolower($lastText) === mb_strtolower($text) && (time() - $lastAt) < 8) {
                            continue;
                        }
                    }
                }

                $reply = $this->chatbot->reply($text, [
                    'source' => 'fanpage',
                    'page' => 'messenger',
                    'sender_id' => $senderId,
                    'history' => $this->loadHistory($senderId),
                    'session_id' => $sessionId
                ]);

                if (!empty($session['id'])) {
                    (new ChatMessage())->add($sessionId, 'user', $text);
                    (new ChatMessage())->add(
                        $sessionId,
                        'assistant',
                        (string) ($reply['answer'] ?? ''),
                        (string) ($reply['provider'] ?? ''),
                        (string) ($reply['model'] ?? '')
                    );
                    if (!empty($reply['handoff'])) {
                        (new ChatSession())->update($sessionId, ['status' => 'handoff']);
                    }
                    (new ChatEvent())->track(
                        'fanpage_inbound',
                        'fanpage',
                        $sessionId,
                        null,
                        null,
                        ['sender_id' => $senderId]
                    );
                }

                $message = trim((string) ($reply['answer'] ?? ''));

                if (!empty($reply['handoff'])) {
                    $message .= "\n\nNếu bạn cần nhân viên hỗ trợ trực tiếp, vui lòng để lại SĐT hoặc email nhé.";
                }

                $this->sendMessage($senderId, $message);
            }
        }
    }

    public function validateSignature(string $rawPayload, string $signatureHeader): bool
    {
        $appSecret = trim((string) ($this->settings['fanpage_app_secret'] ?? getenv('FANPAGE_APP_SECRET') ?: ''));
        if ($appSecret === '') {
            return true;
        }

        $signatureHeader = trim($signatureHeader);
        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);
        return hash_equals($expected, $signatureHeader);
    }

    private function resolveFanpageSession(string $senderId): ?array
    {
        $visitorToken = 'fanpage_' . $senderId;
        return (new ChatSession())->getOrCreate($visitorToken, 'fanpage', null, $senderId);
    }

    private function loadHistory(string $senderId): array
    {
        $session = $this->resolveFanpageSession($senderId);
        if (empty($session['id'])) {
            return [];
        }

        $rows = (new ChatMessage())->getRecentBySession((int) $session['id'], 12);
        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'role' => (string) ($row['role'] ?? 'user'),
                'content' => (string) ($row['content'] ?? '')
            ];
        }

        return $history;
    }

    private function sendMessage(string $recipientId, string $text): void
    {
        $token = trim((string) ($this->settings['fanpage_page_access_token'] ?? getenv('FANPAGE_PAGE_ACCESS_TOKEN') ?: ''));
        if ($token === '' || $recipientId === '' || trim($text) === '') {
            return;
        }

        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => mb_substr($text, 0, 1900)]
        ];

        $url = 'https://graph.facebook.com/v20.0/me/messages?access_token=' . rawurlencode($token);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logFanpage($status, $raw, $recipientId);
    }

    private function logFanpage(int $status, mixed $raw, string $recipientId): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $line = date('Y-m-d H:i:s')
            . ' [fanpage_reply] status=' . $status
            . ' recipient=' . $recipientId
            . ' response=' . (string) $raw
            . PHP_EOL;

        @file_put_contents($dir . '/fanpage.log', $line, FILE_APPEND);
    }
}
