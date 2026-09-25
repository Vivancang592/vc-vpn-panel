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
        $entries = $payload['entry'] ?? [];
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $pageId = (string) ($entry['id'] ?? '');

            // 1. Nhánh Messenger 1-1 (Giữ nguyên luồng chat an toàn)
            $messagingEvents = $entry['messaging'] ?? [];
            if (is_array($messagingEvents) && !empty($messagingEvents)) {
                $enabledRaw = trim((string) ($this->settings['ai_chatbot_enabled'] ?? '1'));
                $isChatEnabled = ($enabledRaw === '' || $enabledRaw === '1');
                if ($isChatEnabled) {
                    foreach ($messagingEvents as $event) {
                        try {
                            $this->handleMessengerEvent($event);
                        } catch (\Throwable $e) {
                            $this->logFanpage(500, 'Messenger error: ' . $e->getMessage(), 'system');
                        }
                    }
                }
            }

            // 2. Nhánh Feed & Comment (Tự động trả lời bình luận bài viết)
            $changes = $entry['changes'] ?? [];
            if (is_array($changes) && !empty($changes)) {
                $isCommentEnabled = trim((string) ($this->settings['ai_comment_auto_reply'] ?? '1')) === '1';
                if ($isCommentEnabled) {
                    foreach ($changes as $change) {
                        try {
                            $this->handleFeedCommentEvent($change, $pageId);
                        } catch (\Throwable $e) {
                            $this->logFanpage(500, 'Comment error: ' . $e->getMessage(), 'system');
                        }
                    }
                }
            }
        }
    }

    /**
     * Xử lý tin nhắn 1-1 qua Messenger
     */
    private function handleMessengerEvent(array $event): void
    {
        $senderId = (string) ($event['sender']['id'] ?? '');
        $text = trim((string) ($event['message']['text'] ?? ''));
        $isEcho = (bool) ($event['message']['is_echo'] ?? false);

        if ($senderId === '' || $text === '' || $isEcho) {
            return;
        }

        if (mb_strlen($text) > 1200) {
            $this->sendMessage($senderId, 'Tin nhắn của bạn hơi dài. Vui lòng rút gọn để mình hỗ trợ chính xác hơn.');
            return;
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
                    return;
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

    /**
     * Xử lý bình luận bài viết Fanpage bằng AI
     */
    private function handleFeedCommentEvent(array $change, string $pageId): void
    {
        $field = $change['field'] ?? '';
        if ($field !== 'feed') {
            return;
        }

        $value = $change['value'] ?? [];
        $item = (string) ($value['item'] ?? '');
        $verb = (string) ($value['verb'] ?? '');

        // Chỉ xử lý sự kiện thêm bình luận mới
        if ($item !== 'comment' || $verb !== 'add') {
            return;
        }

        $fromId = (string) ($value['from']['id'] ?? '');
        $fromName = trim((string) ($value['from']['name'] ?? 'bạn'));
        $commentId = (string) ($value['comment_id'] ?? $value['id'] ?? '');
        $message = trim((string) ($value['message'] ?? ''));
        $postId = (string) ($value['post_id'] ?? '');

        // 1. Chống lặp: Không tự trả lời bình luận của chính Page hoặc bình luận rỗng
        if ($commentId === '' || $message === '') {
            return;
        }

        if ($fromId !== '' && $fromId === $pageId) {
            return;
        }

        // 2. Chống phản hồi trùng lặp (Deduplication / Idempotency)
        $cacheKey = 'fp_cmt_replied_' . $commentId;
        if (class_exists(\App\Models\ChatAiCache::class)) {
            $existing = (new \App\Models\ChatAiCache())->findFresh($cacheKey, 1440);
            if ($existing) {
                return;
            }
        }

        // 3. Kiểm tra Blacklist từ khóa cấm / nhạy cảm
        $blacklistRaw = trim((string) ($this->settings['ai_comment_keywords_blacklist'] ?? ''));
        if ($blacklistRaw !== '') {
            $keywords = array_filter(array_map('trim', explode(',', $blacklistRaw)));
            foreach ($keywords as $kw) {
                if ($kw !== '' && mb_stripos($message, $kw) !== false) {
                    $this->trackCommentEvent('fanpage_comment_skipped', [
                        'comment_id'   => $commentId,
                        'reason'       => 'blacklist_keyword',
                        'keyword'      => $kw,
                        'user_message' => $message,
                        'from_name'    => $fromName
                    ]);
                    return;
                }
            }
        }

        // 4. Chuẩn bị prompt sinh phản hồi bình luận - Tuân thủ nghiêm ngặt Prompt cài đặt hệ thống của Admin
        $siteName = $this->settings['site_name'] ?? $this->settings['site_title'] ?? 'VC VPN';
        $adminCustomPrompt = trim((string) ($this->settings['ai_comment_system_prompt'] ?? ''));

        if ($adminCustomPrompt !== '') {
            $systemPrompt = "Bạn là Trợ lý hỗ trợ Fanpage chính thức của {$siteName}.\n"
                . "HƯỚNG DẪN QUẢN TRỊ VIÊN (ƯU TIÊN TUÂN THỦ CAO NHẤT):\n"
                . $adminCustomPrompt . "\n\n";
        } else {
            $systemPrompt = "Bạn là Trợ lý hỗ trợ Fanpage chính thức của {$siteName}.\n"
                . "Khách hàng vừa để lại bình luận trên bài đăng Facebook.\n"
                . "Nhiệm vụ: Trả lời ngắn gọn, lịch sự, giải đáp đúng thắc mắc và gợi ý khách nhắn tin Messenger / inbox Fanpage để nhận tư vấn cụ thể.\n\n";
        }

        $systemPrompt .= "QUY TẮC BẮT BUỘC KHI TRẢ LỜI BÌNH LUẬN FACEBOOK:\n"
            . "1. ĐỊNH DẠNG: Tuyệt đối KHÔNG dùng định dạng Markdown (KHÔNG dùng dấu sao **in đậm**, KHÔNG dùng cú pháp [link](url)). Chỉ dùng văn bản thuần túy (plain text).\n"
            . "2. ĐỘ DÀI & TIẾT KIỆM TỪ: Ngắn gọn từ 1 đến 2 câu (tối đa 150 - 250 ký tự), trả lời trực diện câu hỏi của khách.\n"
            . "3. XƯNG HÔ: Lịch sự, thân thiện (xưng 'shop' hoặc 'mình', gọi khách là 'bạn' hoặc tên khách '{$fromName}').\n"
            . "4. ĐIỀU HƯỚNG: Mời khách nhắn tin trực tiếp cho Fanpage để nhận mã test hoặc được hỗ trợ 1-1.\n"
            . "5. TRUNG THỰC: Bám sát bảng giá và chính sách của hệ thống, không tự bịa đặt thông tin sai lệch.";

        $planSummary = $this->buildCommentPlanSummary();
        if ($planSummary !== '') {
            $systemPrompt .= "\n\nTHÔNG TIN GÓI DỊCH VỤ THAM KHẢO:\n" . $planSummary;
        }

        $userPrompt = "Khách hàng \"{$fromName}\" vừa bình luận: \"{$message}\". Hãy viết 1 câu trả lời công khai ngắn gọn, lịch sự.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        $commentProvider = strtolower(trim((string) ($this->settings['ai_comment_provider'] ?? $this->settings['ai_provider'] ?? 'gemini')));
        $commentModel = trim((string) ($this->settings['ai_comment_model'] ?? ''));

        $aiProvider = new AIProviderService();
        // Tiết kiệm token: Giới hạn max_tokens 180 cho bình luận
        $reply = $aiProvider->ask($messages, $commentProvider ?: null, $commentModel ?: null, [
            'max_tokens'  => 180,
            'temperature' => 0.3
        ]);

        $replyText = trim((string) ($reply['content'] ?? ''));

        if ($replyText === '') {
            $this->trackCommentEvent('fanpage_comment_failed', [
                'comment_id'   => $commentId,
                'from_name'    => $fromName,
                'user_message' => $message,
                'error'        => $reply['error'] ?? 'AI trả về nội dung rỗng'
            ]);
            return;
        }

        // Làm sạch văn bản trước khi gửi (loại bỏ markdown nếu AI vô tình sinh ra)
        $cleanReplyText = $this->cleanCommentReplyText($replyText);

        // 5. Gửi bình luận trả lời lên Facebook Graph API
        $postResult = $this->replyComment($commentId, $cleanReplyText);

        if ($postResult['ok']) {
            if (class_exists(\App\Models\ChatAiCache::class)) {
                (new \App\Models\ChatAiCache())->upsert(
                    $cacheKey,
                    $message,
                    $cleanReplyText,
                    $reply['provider'] ?? $commentProvider,
                    $reply['model'] ?? $commentModel
                );
            }

            $this->trackCommentEvent('fanpage_comment_reply', [
                'comment_id'        => $commentId,
                'post_id'           => $postId,
                'from_id'           => $fromId,
                'from_name'         => $fromName,
                'user_message'      => $message,
                'ai_reply'          => $cleanReplyText,
                'facebook_reply_id' => $postResult['id'] ?? null,
                'provider'          => $reply['provider'] ?? $commentProvider,
                'model'             => $reply['model'] ?? $commentModel,
                'status'            => 'success'
            ]);
        } else {
            $this->trackCommentEvent('fanpage_comment_failed', [
                'comment_id'   => $commentId,
                'from_name'    => $fromName,
                'user_message' => $message,
                'ai_reply'     => $cleanReplyText,
                'error'        => $postResult['error'] ?? 'Lỗi khi gửi comment tới Facebook'
            ]);
        }
    }

    private function cleanCommentReplyText(string $text): string
    {
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/\*([^\*]+)\*/', '$1', $text);
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1 ($2)', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text, " \t\n\r\0\x0B\"'");
    }

    private function buildCommentPlanSummary(): string
    {
        try {
            $plans = (new \App\Models\VpnPlan())->getAllActive();
            if (empty($plans)) {
                return '';
            }
            $items = [];
            foreach (array_slice($plans, 0, 4) as $p) {
                $price = number_format((float) ($p['price'] ?? 0), 0, '.', ',') . 'đ';
                $days = (int) ($p['duration_days'] ?? 30);
                $items[] = "- {$p['name']}: {$price}/{$days} ngày";
            }
            return implode("\n", $items);
        } catch (\Throwable) {
            return '';
        }
    }

    private function trackCommentEvent(string $eventName, array $data): void
    {
        try {
            if (class_exists(\App\Models\ChatEvent::class)) {
                (new \App\Models\ChatEvent())->track(
                    $eventName,
                    'fanpage',
                    null,
                    null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $data
                );
            }
        } catch (\Throwable $e) {
            $this->logFanpage(500, 'Track event error: ' . $e->getMessage(), 'system');
        }
    }

    /**
     * Trả lời công khai vào bình luận của khách
     */
    public function replyComment(string $commentId, string $message): array
    {
        $token = trim((string) ($this->settings['fanpage_page_access_token'] ?? getenv('FANPAGE_PAGE_ACCESS_TOKEN') ?: ''));
        if ($token === '' || $commentId === '' || trim($message) === '') {
            return ['ok' => false, 'error' => 'Thiếu thông tin hoặc token'];
        }

        $url = 'https://graph.facebook.com/v20.0/' . rawurlencode($commentId) . '/comments?access_token=' . rawurlencode($token);
        $payload = ['message' => $message];

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

        $this->logFanpage($status, $raw, 'comment_' . $commentId);

        $res = json_decode((string) $raw, true);
        if ($status >= 200 && $status < 300 && !empty($res['id'])) {
            return ['ok' => true, 'id' => $res['id']];
        }

        return ['ok' => false, 'error' => $res['error']['message'] ?? 'Lỗi khi phản hồi comment (HTTP ' . $status . ')'];
    }

    /**
     * Đăng bài viết dạng văn bản thuần lên Fanpage
     */
    public function publishPost(string $message): array
    {
        $token = trim((string) ($this->settings['fanpage_page_access_token'] ?? getenv('FANPAGE_PAGE_ACCESS_TOKEN') ?: ''));
        if ($token === '' || trim($message) === '') {
            return ['ok' => false, 'error' => 'Thiếu Page Access Token hoặc nội dung bài viết.'];
        }

        $url = 'https://graph.facebook.com/v20.0/me/feed?access_token=' . rawurlencode($token);
        $payload = ['message' => $message];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logFanpage($status, $raw, 'publish_feed');

        $res = json_decode((string) $raw, true);
        if ($status >= 200 && $status < 300 && !empty($res['id'])) {
            return ['ok' => true, 'id' => $res['id']];
        }

        return ['ok' => false, 'error' => $res['error']['message'] ?? 'Lỗi khi đăng bài lên Fanpage (HTTP ' . $status . ')'];
    }

    /**
     * Đăng bài viết kèm hình ảnh lên Fanpage
     */
    public function publishPhoto(string $message, string $imageUrl): array
    {
        $token = trim((string) ($this->settings['fanpage_page_access_token'] ?? getenv('FANPAGE_PAGE_ACCESS_TOKEN') ?: ''));
        if ($token === '' || trim($message) === '') {
            return ['ok' => false, 'error' => 'Thiếu Page Access Token hoặc nội dung bài viết.'];
        }

        $fullImageUrl = $imageUrl;
        // Nếu là relative path trên server, ghép domain công khai hoặc upload file cục bộ
        if (str_starts_with($imageUrl, '/')) {
            $siteUrl = rtrim((string) ($this->settings['app_url'] ?? $this->settings['site_url'] ?? ''), '/');
            if ($siteUrl === '') {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $siteUrl = $protocol . $host;
            }
            $fullImageUrl = $siteUrl . $imageUrl;
        }

        $localFilePath = BASE_PATH . '/public' . $imageUrl;
        $url = 'https://graph.facebook.com/v20.0/me/photos?access_token=' . rawurlencode($token);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        // Nếu file có thật trên disk và curl hỗ trợ upload CURLFile
        if (str_starts_with($imageUrl, '/') && file_exists($localFilePath) && class_exists('\CURLFile')) {
            $payload = [
                'caption' => $message,
                'source'  => new \CURLFile($localFilePath)
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } else {
            // Upload bằng remote URL
            $payload = [
                'caption' => $message,
                'url'     => $fullImageUrl
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logFanpage($status, $raw, 'publish_photo');

        $res = json_decode((string) $raw, true);
        if ($status >= 200 && $status < 300 && !empty($res['id'])) {
            $postId = $res['post_id'] ?? $res['id'];
            return ['ok' => true, 'id' => $postId];
        }

        return ['ok' => false, 'error' => $res['error']['message'] ?? 'Lỗi khi đăng ảnh lên Fanpage (HTTP ' . $status . ')'];
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
