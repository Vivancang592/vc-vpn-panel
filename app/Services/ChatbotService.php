<?php

namespace App\Services;

use App\AI\Assistant\ContextBudget;
use App\AI\Knowledge\CustomerTools;
use App\AI\Knowledge\SiteKnowledge;
use App\Models\ChatEvent;
use App\Models\ChatAiCache;
use App\Models\ChatSession;
use App\Models\Coupon;
use App\Models\Post;
use App\Models\Setting;
use App\Models\VpnPlan;

class ChatbotService
{
    private array $settings;
    private AIProviderService $provider;
    /** Phiên bản ưu đãi memoize trong 1 request (xem promotionVersion()). */
    private string $promoVersion = '';

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
            $supportUrl = $this->resolveSupportUrl();
            return [
                'success' => true,
                'answer' => 'Trợ lý AI hiện đang tạm tắt. Vui lòng [Liên Hệ Fanpage](' . $supportUrl . ') để được hỗ trợ trực tiếp.',
                'handoff' => true,
                'provider' => 'disabled',
                'model' => '',
                'cta' => null,
            ];
        }

        $message = trim($message);
        if ($message === '') {
            return $this->fallbackResponse('Bạn hãy nhập câu hỏi cụ thể để mình hỗ trợ nhanh nhất nhé.');
        }

        $history = $context['history'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }
        // BƯỚC 3.1: không còn cắt 8 tin / 500 ký tự — đưa TOÀN BỘ lịch sử vào
        // rồi để ContextBudget cân cửa sổ (phần trôi ra ngoài được tóm tắt luân chuyển).
        $history = $this->normalizeHistory($history);

        $isLoggedIn = !empty($context['is_logged_in']) || (!empty($context['user_id']) && (int) $context['user_id'] > 0);
        $context['is_logged_in'] = $isLoggedIn;
        $chatUserId = (int) ($context['user_id'] ?? 0);
        $sessionId = (int) ($context['session_id'] ?? 0);

        // Nguồn hội thoại (website vs Fanpage/Facebook) + tên người dùng nếu đã đăng nhập.
        $source = strtolower((string) ($context['source'] ?? 'web'));
        $isFacebook = in_array($source, ['fanpage', 'facebook', 'messenger'], true);
        $userName = !empty($context['user_name']) ? trim((string) $context['user_name']) : '';

        $systemPrompt = $this->resolveSystemPrompt($context, $isFacebook, $isLoggedIn, $userName);
        $knowledge = $this->buildKnowledgeSnippet($context, $message);

        // ---- BƯỚC 3.4: truy vấn SỰ THẬT từ CSDL trước khi hỏi model ----
        $factTools = CustomerTools::prefetch($message, $context);
        $factResults = $factTools !== [] ? CustomerTools::dispatchAll($factTools, $context) : [];
        $factsUsed = $factResults !== [];
        $knowledge .= CustomerTools::renderFactBlock($factResults);
        $knowledge .= CustomerTools::protocolBlock($context);

        // ---- BƯỚC 3.1: cửa sổ ngữ cảnh 2 lượt + bản tóm tắt luân chuyển ----
        // history_offset = số tin CŨ đã trôi khỏi cửa sổ đọc (phiên > 100 tin) để
        // covered_through tính theo TOÀN PHIÊN chứ không theo mảng đang cầm.
        $historyOffset = max(0, (int) ($context['history_offset'] ?? 0));
        $loadedState = $this->loadContextState($sessionId, $historyOffset + count($history));
        $ctxState = $this->buildContextWindow($systemPrompt, $knowledge, $history, $loadedState, $historyOffset);
        if ($sessionId > 0
            && ((string) $ctxState['summary'] !== $loadedState['summary']
                || (int) $ctxState['covered_through'] !== $loadedState['covered_through'])
        ) {
            try {
                (new ChatSession())->saveContextState($sessionId, (string) $ctxState['summary'], (int) $ctxState['covered_through']);
            } catch (\Throwable $e) {
                // Chưa có cột summary → chat vẫn chạy bình thường ở lượt sau.
            }
        }
        $digestBlock = ContextBudget::digestBlock((string) $ctxState['summary'], (int) $ctxState['covered_through']);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'system', 'content' => $knowledge . $digestBlock],
        ];
        foreach ($ctxState['window'] as $item) {
            $messages[] = ['role' => (string) $item['role'], 'content' => (string) $item['content']];
        }
        $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 4000)];

        // ---- BƯỚC 3.3: khóa cache theo người dùng / phiên / phiên bản ưu đãi ----
        $cacheTtl = (int) ($this->settings['ai_cache_ttl_minutes'] ?? 60);
        $cacheTtl = max(1, min(1440, $cacheTtl));
        $cacheScope = $isFacebook ? 'fp_' : 'web_';
        $cacheScope .= $isLoggedIn ? 'auth_' : 'guest_';
        // Web: tách cache theo trang đang xem để câu trả lời đúng ngữ cảnh trang.
        if (!$isFacebook) {
            $cachePage = trim((string) ($context['page'] ?? ''));
            if ($cachePage !== '') {
                $cacheScope .= 'page_' . sha1(mb_strtolower($cachePage)) . '_';
            }
        }

        // Câu hỏi phụ thuộc lịch sử / đã nhét dữ liệu thật → KHÔNG đọc & KHÔNG
        // ghi cache (trả lời trước đó của người khác/session khác sẽ bị rò rỉ).
        $historyDependent = $this->historyDependent($message, $history);
        $useCache = !$historyDependent && !$factsUsed;
        $cacheKey = $this->buildCacheKey($cacheScope, $message, $chatUserId, $sessionId, count($history));

        if ($useCache && class_exists(ChatAiCache::class)) {
            $cached = (new ChatAiCache())->findFresh($cacheKey, $cacheTtl);
            if ($cached && !empty($cached['answer']) && !CustomerTools::looksLikeToolJson((string) $cached['answer'])) {
                return [
                    'success' => true,
                    'answer' => $this->resolveAnswerMacros((string) $cached['answer'], $isFacebook),
                    'handoff' => $this->shouldHandoff($message, (string) $cached['answer']),
                    'provider' => 'cache',
                    'model' => (string) ($cached['model'] ?? ''),
                    'cta' => null,
                ];
            }
        }

        // LUỒNG AI MỚI: mọi lời gọi đi qua AI Core.
        // - Website  → module `support_chat`
        // - Facebook → module `fanpage_comment`
        // Prompt được đọc từ storage/prompts/{module_key}.txt (Admin sửa được).
        $module = $isFacebook ? 'fanpage_comment' : 'support_chat';
        $askOptions = [
            'module' => $module,
            'temperature' => 0.4,
            'max_tokens' => (int) ($this->settings['ai_max_output_tokens'] ?? 700),
        ];
        $result = $this->provider->ask($messages, null, null, $askOptions);

        // ---- BƯỚC 3.4: model gọi công cụ dạng JSON → chạy thật → hỏi lại 1 lần ----
        $toolFactText = '';
        if ($result['ok']) {
            $toolCall = CustomerTools::parseToolCall(trim((string) ($result['content'] ?? '')));
            if ($toolCall !== null) {
                if (CustomerTools::handles((string) $toolCall['tool'])) {
                    $toolResult = CustomerTools::dispatch((string) $toolCall['tool'], (array) $toolCall['args'], $context);
                    $toolFactText = (string) $toolResult['text'];
                } else {
                    $toolFactText = 'Công cụ "' . (string) $toolCall['tool'] . '" không tồn tại trong hệ thống.';
                }

                if ($toolFactText !== '') {
                    $messages[] = ['role' => 'assistant', 'content' => (string) $result['content']];
                    $messages[] = ['role' => 'user', 'content' => '[KẾT QUẢ CÔNG CỤ ' . (string) $toolCall['tool'] . "]\n"
                        . $toolFactText
                        . "\nTrả lời khách bằng tiếng Việt, DỰA ĐÚNG số liệu vừa nhận. "
                        . 'Không có số liệu thì nói rõ là chưa có, không được bịa. '
                        . 'TUYỆT ĐỐI không trả về JSON hay tên công cụ cho khách.'];
                    $retry = $this->provider->ask($messages, null, null, $askOptions);
                    if ($retry['ok']) {
                        $result = $retry;
                    }
                }
            }
        }

        if (!$result['ok']) {
            $this->logEvent('ai_failed', [
                'provider' => 'kira',
                'module' => $module,
                'error' => (string) ($result['error'] ?? 'unknown'),
                'source' => $source,
                'session_id' => $sessionId,
                'user_id' => $chatUserId,
            ]);
            return $this->fallbackResponse('Hệ thống AI đang xử lý lượng yêu cầu lớn. Bạn có thể để lại câu hỏi hoặc liên hệ hỗ trợ trực tiếp để được phục vụ ngay nhé.');
        }

        $answer = $this->resolveAnswerMacros(trim((string) ($result['content'] ?? '')), $isFacebook);
        // Chốt chặn cuối: không bao giờ để JSON công cụ lọt ra mắt khách.
        if (CustomerTools::looksLikeToolJson($answer)) {
            $answer = $toolFactText !== ''
                ? $this->resolveAnswerMacros($toolFactText, $isFacebook)
                : 'Mình chưa lấy được thông tin này. Bạn vui lòng liên hệ nhân viên để được hỗ trợ chính xác nhé.';
        }
        $handoff = $this->shouldHandoff($message, $answer);

        // ---- BƯỚC 4.3: AnswerGuard — chặn link bịa & giá bịa trước khi trả khách ----
        if ($answer !== '' && class_exists(\App\AI\Core\AnswerGuard::class)) {
            $guard = \App\AI\Core\AnswerGuard::inspect($answer, $knowledge, $isFacebook, $this->resolveSiteUrl());
            $gStatus = (string) ($guard['status'] ?? \App\AI\Core\AnswerGuard::OK);
            if ($gStatus !== \App\AI\Core\AnswerGuard::OK) {
                $this->logEvent('answer_guard', [
                    'status' => $gStatus,
                    'issues' => implode(' | ', (array) ($guard['issues'] ?? [])),
                    'module' => $module,
                    'source' => $source,
                    'session_id' => $sessionId,
                    'user_id' => $chatUserId,
                ]);
            }
            if ($gStatus === \App\AI\Core\AnswerGuard::BLOCK) {
                // Giá bịa = không đủ bằng chứng → fallback + báo người thật,
                // KHÔNG ghi cache để câu bịa không bị trả lại lần sau.
                return $this->fallbackResponse(
                    'Câu trả lời này cần được nhân viên xác minh lại số liệu trước khi gửi cho bạn. '
                    . 'Bạn vui lòng chờ chút hoặc liên hệ hỗ trợ trực tiếp để được trả lời chính xác ngay nhé!'
                );
            }
            $answer = (string) ($guard['answer'] ?? $answer);
        }

        if ($answer !== '' && $useCache && !CustomerTools::looksLikeToolJson($answer) && class_exists(ChatAiCache::class)) {
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
            'provider' => (string) ($result['provider'] ?? 'kira'),
            'model' => (string) ($result['model'] ?? ''),
            'cta' => null,
        ];

        $this->logEvent('ai_reply', [
            'provider' => $response['provider'],
            'module' => $module,
            'handoff' => $handoff ? 1 : 0,
            'source' => $source,
            'session_id' => $sessionId,
            'user_id' => $chatUserId,
        ]);

        return $response;
    }

    // =================================================================
    // BƯỚC 3 — ngữ cảnh hội thoại / cache đúng / công cụ khách hàng
    // =================================================================

    /** Giữ tin hợp lệ của khách & trợ lý, bỏ role khác, mỗi tin tối đa 4000 ký tự. */
    private function normalizeHistory(array $history): array
    {
        $out = [];
        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = (string) ($item['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = trim((string) ($item['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $out[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
        }

        return array_values($out);
    }

    /**
     * Đọc trạng thái tóm tắt của phiên (vc_chat_sessions.summary /
     * covered_through — tính theo TOÀN PHIÊN). Phiếu cũ/hết hạn → tự về rỗng.
     *
     * @return array{summary: string, covered_through: int}
     */
    private function loadContextState(int $sessionId, int $totalKnown): array
    {
        $state = ['summary' => '', 'covered_through' => 0];
        if ($sessionId > 0) {
            try {
                $state = (new ChatSession())->contextState($sessionId);
            } catch (\Throwable $e) {
                $state = ['summary' => '', 'covered_through' => 0];
            }
        }

        $covered = (int) ($state['covered_through'] ?? 0);
        if ($covered > $totalKnown) {
            // Lịch sử bị cắt ngắn/reset → bản tóm tắt không còn khớp → dựng lại.
            return ['summary' => '', 'covered_through' => 0];
        }

        return ['summary' => (string) ($state['summary'] ?? ''), 'covered_through' => $covered];
    }

    /**
     * Cửa sổ 2 lượt (BƯỚC 3.1):
     *  1) Tính cửa sổ dựa trên system + knowledge + bản tóm tắt CŨ.
     *  2) Các tin trôi ra ngoài kể từ covered_through → nén vào tóm tắt MỚI.
     *  3) Tính lại cửa sổ với bản tóm tắt mới (đảm bảo vẫn vừa ngân sách).
     *
     * @param array<int, array{role: string, content: string}> $history
     * @param array{summary: string, covered_through: int} $state covered_through tính theo TOÀN PHIÊN
     * @param int $offset số tin toàn phiên đứng TRƯỚC $history[0]
     * @return array{summary: string, covered_through: int, window: array<int, array{role: string, content: string}>}
     */
    private function buildContextWindow(string $systemPrompt, string $knowledge, array $history, array $state, int $offset = 0): array
    {
        $budget = ContextBudget::budget();
        $count = count($history);
        $coveredAbs = max(0, (int) $state['covered_through']);
        $coveredRel = max(0, $coveredAbs - $offset);
        $summary = trim((string) $state['summary']);

        $baseOld = $systemPrompt . "\n" . $knowledge . "\n" . ContextBudget::digestBlock($summary, $coveredAbs);
        $window = ContextBudget::window($baseOld, $history, 0, $budget);
        $start = max(0, (int) $window['start']);

        if ($count > 0 && $start > $coveredRel) {
            $newlyDropped = array_slice($history, $coveredRel, $start - $coveredRel);
            if ($newlyDropped !== []) {
                $summary = $this->mergeSummary($summary, ContextBudget::digest($newlyDropped));
            }
            $coveredRel = $start;
            $coveredAbs = $offset + $start;
        }

        $baseNew = $systemPrompt . "\n" . $knowledge . "\n" . ContextBudget::digestBlock($summary, $coveredAbs);
        $window2 = ContextBudget::window($baseNew, $history, 0, $budget);
        $start2 = max(0, (int) $window2['start']);

        if ($count > 0 && $start2 > $coveredRel) {
            // Ngân sách thay đổi giữa 2 lượt → bù nốt phần còn hụt.
            $gap = array_slice($history, $coveredRel, $start2 - $coveredRel);
            if ($gap !== []) {
                $summary = $this->mergeSummary($summary, ContextBudget::digest($gap));
            }
            $coveredRel = $start2;
            $coveredAbs = $offset + $start2;
        }

        $kept = array_slice($history, max(0, min($start2, $count)));

        return ['summary' => $summary, 'covered_through' => $coveredAbs, 'window' => $kept];
    }

    /** Nối bản tóm tắt cũ + phần mới, cắt bớt ĐẦU (dữ liệu cũ nhất) khi quá dài. */
    private function mergeSummary(string $old, string $newPart): string
    {
        $old = trim($old);
        $newPart = trim($newPart);
        if ($newPart === '') {
            return $old;
        }
        if ($old === '') {
            $merged = $newPart;
        } else {
            $merged = $old . "\n" . $newPart;
        }

        $max = ContextBudget::MAX_DIGEST_CHARS;
        if (mb_strlen($merged, 'UTF-8') <= $max) {
            return $merged;
        }

        $merged = mb_substr($merged, -$max, null, 'UTF-8');
        $nl = mb_strpos($merged, "\n");
        if ($nl !== false) {
            $merged = mb_substr($merged, $nl + 1, null, 'UTF-8');
        }

        return $merged;
    }

    /**
     * Câu hỏi CÓ phụ thuộc lịch sử (câu hỏi ngắn / từ chỉ định mơ hồ) →
     * tuyệt đối không dùng lại câu trả lời cache của phiên/người khác.
     */
    private function historyDependent(string $message, array $history): bool
    {
        if ($history === []) {
            return false;
        }

        $norm = CustomerTools::norm($message);
        if ($norm === '') {
            return true;
        }
        if (mb_strlen($norm, 'UTF-8') <= 30) {
            return true;
        }

        // Thiếu chủ ngữ/đối ngữ: "cái đó", "như trên", "vậy sao", "nhắc lại"...
        $patterns = [
            'cai do', 'cai do ay', 'cai kia', 'cai ay', 'cua no', 'cua do',
            'nhu tren', 'nhu vay', 'vay sao', 'sao lai', 'vi sao', 'tai sao',
            'da noi', 'truoc do', 'vua roi', 'nhac lai', 'noi ro hon',
            'giai thich them', 'tra loi truoc', 'chuyen do', 'viec do',
            'goi do', 'ma do', 'thong tin do', 'duoi nay', 'tren kia',
        ];
        foreach ($patterns as $p) {
            if (str_contains($norm, $p)) {
                return true;
            }
        }

        // Câu có dấu hỏi nhưng không nêu đối tượng cụ thể.
        return str_ends_with(rtrim($message), '?') && mb_strlen($norm, 'UTF-8') <= 60;
    }

    /**
     * Khóa cache: scope trang + người dùng + phiên (khi có lịch sử) + phiên bản
     * ưu đãi + câu hỏi đã chuẩn hóa. Trả lời phụ thuộc bất kỳ yếu tố nào ở
     * trên đều KHÔNG dùng chung khóa.
     */
    private function buildCacheKey(string $cacheScope, string $message, int $userId, int $sessionId, int $historyCount): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/', ' ', trim($message)) ?? '');

        $parts = $cacheScope;
        $parts .= 'u' . sha1((string) $userId);
        if ($historyCount > 0) {
            $parts .= 'sess' . sha1((string) $sessionId);
        }
        $parts .= 'promo' . $this->promotionVersion();
        $parts .= $normalized;

        return sha1($parts);
    }

    /**
     * "Phiên bản" chương trình ưu đãi: đổi mã giảm giá / đổi bảng giá là đổi
     * khóa cache → câu trả lời cũ về giá/mã giảm giá không còn bị dùng lại.
     * Memoize trong 1 request (buildKnowledgeSnippet đã query bảng này rồi).
     */
    private function promotionVersion(): string
    {
        if ($this->promoVersion !== '') {
            return $this->promoVersion;
        }

        $bits = [];
        try {
            foreach ((new Coupon())->getActiveCoupons() as $c) {
                $bits[] = implode('|', [
                    (string) ($c['code'] ?? ''),
                    (string) ($c['discount_type'] ?? ''),
                    (string) ($c['discount_value'] ?? ''),
                    (string) ($c['expires_at'] ?? ''),
                ]);
            }
        } catch (\Throwable $e) {
            $bits[] = 'coupon-err';
        }
        try {
            foreach ((new VpnPlan())->getAllActive() as $p) {
                $bits[] = implode('|', [
                    (string) ($p['code'] ?? ''),
                    (string) ($p['price'] ?? ''),
                    (string) ($p['duration_days'] ?? ''),
                    (string) ($p['status'] ?? ''),
                ]);
            }
        } catch (\Throwable $e) {
            $bits[] = 'plan-err';
        }
        // BƯỚC 4.2 — popup/ưu đãi đang chạy cũng nằm trong snippet [4]:
        // đổi popup = đổi khóa cache, tránh trả lại câu cũ đã nhắc ưu đãi cũ.
        try {
            foreach ((new Post())->getPublishedPopups() as $pp) {
                $bits[] = 'popup|' . implode('|', [
                    (string) ($pp['id'] ?? ''),
                    (string) ($pp['title'] ?? ''),
                    (string) ($pp['updated_at'] ?? $pp['created_at'] ?? ''),
                ]);
            }
        } catch (\Throwable $e) {
            $bits[] = 'popup-err';
        }

        sort($bits);
        $this->promoVersion = substr(sha1(implode("\n", $bits)), 0, 16);

        return $this->promoVersion;
    }

    /**
     * Xây system prompt cho lượt chat này.
     *
     * Ưu tiên PROMPT FILE do Admin cấu hình ở Trung Tâm AI:
     *   storage/prompts/{support_chat|fanpage_comment}.txt
     * Nếu chưa có file → dùng PromptRegistry (DB/default) → cuối cùng mới dựng
     * văn bản mặc định bằng buildSystemPrompt().
     */
    private function resolveSystemPrompt(array $context, bool $isFacebook, bool $isLoggedIn, string $userName): string
    {
        $moduleKey = $isFacebook ? 'fanpage_comment' : 'support_chat';

        // Tên miền THẬT của hệ thống — file prompt Admin dùng {domain} /
        // {{domain}} (dạng https://{domain}/faq...). Không thay tại đây thì
        // model nhận placeholder thô và mỗi lần tự bịa một tên miền khác.
        $siteUrl = rtrim($this->resolveSiteUrl(), '/');
        $domain  = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: '');

        $variables = [
            'site_url' => $siteUrl,
            'domain'   => $domain,
            'source_label' => $isFacebook ? 'Facebook Fanpage' : 'Website',
            'user_context' => $isLoggedIn
                ? 'Khách đã ĐĂNG NHẬP website' . ($userName !== '' ? ', tên: ' . $userName : '') . '.'
                : 'Khách CHƯA đăng nhập (khách vãng lai).',
            'user_name' => $userName,
            'from_name' => $userName !== '' ? $userName : (string) ($context['from_name'] ?? ''),
            'message' => (string) ($context['message'] ?? ''),
            'comment' => (string) ($context['comment'] ?? $context['message'] ?? ''),
            'post_context' => (string) ($context['post_context'] ?? ''),
            'plans' => '',
        ];

        try {
            $registry = new \App\AI\Core\PromptRegistry();
            $raw = $registry->rawTemplate($moduleKey);

            if (is_array($raw) && ($raw['source'] ?? 'none') !== 'none') {
                $text = (string) ($raw['system'] ?? '');

                foreach ($variables as $name => $value) {
                    if (is_array($value) || is_object($value)) {
                        continue;
                    }
                    $text = str_replace('{{' . $name . '}}', (string) $value, $text);
                }

                $text = trim((string) preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '', $text));

                // {domain} (single-brace) là macro chuẩn của file prompt Admin —
                // thay bằng host thật để model không bịa tên miền. Khác với
                // {{domain}} đã thay ở vòng variables phía trên.
                if ($domain !== '') {
                    $text = str_replace('{domain}', $domain, $text);
                }

                // Ghép thêm ngữ cảnh nguồn/tên khách vào cuối system prompt khi
                // file prompt của Admin chưa có biến tương ứng.
                $contextLine = 'Nguồn hội thoại: ' . $variables['source_label'] . '. ' . $variables['user_context'];
                if ($text !== '' && !str_contains($text, (string) $variables['source_label'])) {
                    $text .= "\n" . $contextLine;
                }

                // Luôn bổ sung dòng trang khách đang mở (khách hỏi khác nhau
                // tùy trang) — kể cả khi file prompt đã có sẵn nguồn hội thoại.
                $pageLine = $this->pageContextLine($context, $isFacebook);
                if ($text !== '' && $pageLine !== '') {
                    $text .= "\n" . $pageLine;
                }

                if ($text !== '') {
                    return $this->withProcedure($text, $isFacebook);
                }
            }
        } catch (\Throwable) {
            // rơi xuống builder mặc định bên dưới
        }

        // Quy trình dò bài hướng dẫn + bản đồ trang + tâm lý chốt đơn:
        // ÉP BẮT BUỘC cho MỌI nguồn prompt (file admin / registry / mặc định).
        return $this->withProcedure($this->buildSystemPrompt($context), $isFacebook);
    }

    /**
     * Nối quy trình trả lời vào system prompt — bỏ qua nếu Admin đã tự viết
     * sẵn mục "QUY TRÌNH TRẢ LỜI BẮT BUỘC" trong file prompt (tránh trùng).
     */
    private function withProcedure(string $text, bool $isFacebook): string
    {
        if (str_contains($text, 'QUY TRÌNH TRẢ LỜI BẮT BUỘC')) {
            return $text;
        }

        return $text . "\n\n" . SiteKnowledge::renderProcedure($isFacebook);
    }

    /**
     * Dòng ngữ cảnh cho biết khách đang mở trang nào (chỉ luồng web —
     * fanpage/Messenger không có khái niệm trang web nên bỏ qua).
     */
    private function pageContextLine(array $context, bool $isFacebook): string
    {
        if ($isFacebook) {
            return '';
        }

        $page = trim((string) ($context['page'] ?? ''));
        if ($page === '' || strtolower($page) === 'messenger') {
            return '';
        }

        $line = $page === 'home'
            ? 'Khách đang xem trang: Trang chủ.'
            : 'Khách đang xem trang: /' . ltrim($page, '/') . '.';

        $title = trim((string) ($context['page_title'] ?? ''));
        if ($title !== '') {
            $line .= ' Tiêu đề trang: ' . $title . '.';
        }

        return $line;
    }

    private function buildSystemPrompt(array $context): string
    {
        $siteTitle = $this->settings['site_title'] ?? 'VC VPN';
        $isLoggedIn = !empty($context['is_logged_in']);
        $userName = !empty($context['user_name']) ? (string) $context['user_name'] : '';
        $source = strtolower((string) ($context['source'] ?? 'web'));
        $isFacebook = in_array($source, ['fanpage', 'facebook', 'messenger'], true);

        $siteUrl = $this->resolveSiteUrl();

        if ($isFacebook) {
            $promptParts = [
                "Bạn là chuyên viên tư vấn bán hàng & hỗ trợ kỹ thuật trực tuyến của hệ thống {$siteTitle} trên kênh Facebook Messenger.",
                "",
                "NGUYÊN TẮC BẮT BUỘC (TUÂN THỦ 100%):",
                "1. TRẢ LỜI ĐÚNG TRỌNG TÂM & BÁM SÁT NGỮ CẢNH CUỘC HỘI THOẠI:",
                "- CHỈ trả lời trực tiếp nội dung người dùng hỏi, không trả lời lan man, không tự ý liệt kê tràn lan các tính năng hay thông tin mà người dùng không yêu cầu.",
                "- Bám sát ngữ cảnh của các tin nhắn phía trước trong cuộc hội thoại để trả lời logic, liền mạch.",
                "- Nếu người dùng chỉ chào hỏi: Chào lại thân thiện, ngắn gọn và hỏi họ cần hỗ trợ gì.",
                "- Nếu người dùng hỏi một thông tin cụ thể: Trả lời trực diện câu hỏi đó một cách ngắn gọn, rõ ràng.",
                "",
                "2. QUY TẮC ĐỊNH DẠNG VĂN BẢN TRÊN FACEBOOK MESSENGER (CỰC KỲ QUAN TRỌNG):",
                "- Tuyệt đối KHÔNG sử dụng định dạng Markdown.",
                "- Tuyệt đối KHÔNG dùng ký tự sao (*) hoặc (**) để in đậm hoặc in nghiêng.",
                "- Tuyệt đối KHÔNG dùng cú pháp link ẩn dạng [Tên link](đường_dẫn).",
                "- BẮT BUỘC chỉ sử dụng URL trần trực tiếp (plain URL), và LUÔN LUÔN có ít nhất một khoảng trắng trước đường dẫn (Ví dụ: '...tại đây nhé: {$siteUrl}/#bang-gia').",
                "- Trình bày dạng văn bản thuần túy (plain text), dùng dấu gạch ngang (-) cho danh sách và xuống dòng hợp lý.",
                "",
                "3. PHONG CÁCH GIAO TIẾP & ĐIỀU HƯỚNG ĐƯỜNG DẪN:",
                "- Nói chuyện tự nhiên, lịch sự, nhiệt tình (xưng 'mình' hoặc 'em', gọi khách là 'bạn' hoặc 'anh/chị').",
                "- Luôn trả lời giải thích thông tin trước rồi mới gửi link hỗ trợ.",
                "- Các đường dẫn chuyển hướng chính:",
                "  + Xem bảng giá & mua gói: {$siteUrl}/#bang-gia",
                "  + Đăng ký tài khoản: {$siteUrl}/register",
                "  + Đăng nhập: {$siteUrl}/login",
                "  + Hướng dẫn & FAQ: {$siteUrl}/faq",
                "  + Tải app / phần mềm: {$siteUrl}/download",
                "  + Chính sách hoàn tiền: {$siteUrl}/refund",
                "  + Điều khoản sử dụng: {$siteUrl}/terms",
                "",
                "4. KỸ NĂNG BÁN HÀNG & MÃ GIẢM GIÁ:",
                "- Báo đúng giá gói theo bảng giá hệ thống.",
                "- Nếu có mã giảm giá trong dữ liệu: Chủ động giới thiệu mã code dạng chữ thuần (VD: NHAPMA), mức giảm % và hạn dùng để khách nhập khi thanh toán.",
                "- Nếu không có mã: Nói rõ giá niêm yết hiện tại đã là giá ưu đãi tốt nhất.",
                "",
                "5. NGUYÊN TẮC TRUNG THỰC & CHÍNH XÁC (GROUNDING):",
                "- CHỈ sử dụng dữ liệu gói cước, giá bán, chính sách từ phần DỮ LIỆU NỘI BỘ bên dưới. Tuyệt đối không tự bịa đặt thông tin sai lệch.",
            ];
        } else {
            $promptParts = [
                "Bạn là chuyên viên tư vấn bán hàng & hỗ trợ kỹ thuật trực tuyến của hệ thống {$siteTitle}.",
                "",
                "NGUYÊN TẮC BẮT BUỘC (TUÂN THỦ 100%):",
                "1. TRẢ LỜI ĐÚNG TRỌNG TÂM & BÁM SÁT NGỮ CẢNH CUỘC HỘI THOẠI:",
                "- CHỈ trả lời trực tiếp nội dung người dùng hỏi, không trả lời lan man, không tự ý liệt kê tràn lan các tính năng hay thông tin mà người dùng không yêu cầu.",
                "- Bám sát ngữ cảnh của các tin nhắn phía trước trong cuộc hội thoại để trả lời logic, liền mạch.",
                "- Nếu người dùng chỉ chào hỏi: Chào lại thân thiện, ngắn gọn và hỏi họ cần hỗ trợ gì.",
                "- Nếu người dùng hỏi một thông tin cụ thể (ví dụ: 'có hỗ trợ iPhone không?', 'gói 1 tháng bao nhiêu tiền?'): Trả lời trực diện câu hỏi đó một cách ngắn gọn, rõ ràng.",
                "",
                "2. PHONG CÁCH GIAO TIẾP TỰ NHIÊN NHƯ NGƯỜI THẬT:",
                "- Nói chuyện tự nhiên, lịch sự, nhiệt tình (xưng 'mình' hoặc 'em', gọi khách là 'bạn' hoặc 'anh/chị').",
                "- Tuyệt đối KHÔNG chỉ quăng link cụt lủn. Luôn trả lời giải thích trước, sau đó mới gắn link liên quan một cách tự nhiên.",
                "- Định dạng Markdown đẹp: in đậm (**tên gói, giá, mã code**), danh sách gạch đầu dòng rõ ràng, liên kết dạng [Tên liên kết](đường_dẫn).",
                "",
                "3. QUY TẮC ĐIỀU HƯỚNG LIÊN KẾT THEO TRẠNG THÁI ĐĂNG NHẬP:",
                $isLoggedIn
                    ? "- Người dùng HIỆN ĐÃ ĐĂNG NHẬP" . ($userName ? " (Tài khoản: {$userName})" : "") . ": BẮT BUỘC chỉ được dẫn link vào các trang nội bộ của thành viên (/user/...):\n"
                      . "  + Mua gói dịch vụ / xem bảng giá: [Xem & Mua Gói Dịch Vụ](/user/plans)\n"
                      . "  + Gói dịch vụ đang sở hữu / lấy cấu hình kết nối: [Gói Dịch Vụ Của Tôi](/subscriptions)\n"
                      . "  + Hướng dẫn cài đặt & sử dụng: [Hướng Dẫn Cài Đặt](/user/guides)\n"
                      . "  + Tải app / phần mềm kết nối: [Tải Ứng Dụng VPN](/user/downloads)\n"
                      . "  + Nạp tiền vào ví: [Nạp Tiền Vào Ví](/payments/deposit)\n"
                      . "  + Gửi yêu cầu hỗ trợ kỹ thuật: [Gửi Ticket Hỗ Trợ](/tickets/create)\n"
                      . "  + Xem lịch sử đơn hàng: [Đơn Hàng Của Tôi](/orders)\n"
                      . "  + Tiếp thị liên kết nhận hoa hồng: [Tiếp Thị Liên Kết](/referrals)\n"
                      . "  * TUYỆT ĐỐI KHÔNG gửi link /login, /register hay /#bang-gia cho người dùng đã đăng nhập."
                    : "- Người dùng HIỆN CHƯA ĐĂNG NHẬP (Khách vãng lai): BẮT BUỘC chỉ được dẫn link ra các trang công khai ngoài website:\n"
                      . "  + Xem bảng giá & tính năng các gói: [Xem Bảng Giá Gói](/#bang-gia)\n"
                      . "  + Đăng ký tài khoản mới: [Đăng Ký Tài Khoản](/register)\n"
                      . "  + Đăng nhập tài khoản: [Đăng Nhập](/login)\n"
                      . "  + Hướng dẫn & Câu hỏi thường gặp: [Câu Hỏi Thường Gặp (FAQ)](/faq)\n"
                      . "  + Tải app / phần mềm kết nối: [Tải Ứng Dụng](/download)\n"
                      . "  + Điều khoản sử dụng: [Điều Khoản Sử Dụng](/terms)\n"
                      . "  + Chính sách hoàn tiền: [Chính Sách Hoàn Tiền](/refund)\n"
                      . "  * TUYỆT ĐỐI KHÔNG gửi link /user/... hay /subscriptions cho người dùng chưa đăng nhập vì họ sẽ bị chặn.",
                "",
                "4. KỸ NĂNG BÁN HÀNG & QUẢNG CÁO MÃ GIẢM GIÁ (SALES & UPSELL):",
                "- Khi người dùng hỏi về giá cả, hỏi mua gói, hoặc phân vân lựa chọn gói:",
                "  + Báo đúng giá gói theo bảng giá hệ thống.",
                "  + KIỂM TRA danh sách 'MÃ GIẢM GIÁ ĐANG HOẠT ĐỘNG' trong Dữ liệu nội bộ bên dưới:",
                "    * NẾU CÓ mã giảm giá: Chủ động quảng cáo mã giảm giá (kèm mã code **`MÃ`**, mức giảm %, hạn dùng) và nhắc khách nhập tại bước thanh toán để chốt đơn ngay.",
                "    * NẾU KHÔNG CÓ mã giảm giá: Nói rõ giá niêm yết hiện tại đã là giá ưu đãi trực tiếp tốt nhất.",
                "",
                "5. NGUYÊN TẮC TRUNG THỰC & CHÍNH XÁC (GROUNDING):",
                "- CHỈ sử dụng dữ liệu gói cước, giá bán, mã giảm giá, chính sách từ phần DỮ LIỆU NỘI BỘ bên dưới. Tuyệt đối không tự bịa đặt thông tin sai lệch.",
                "- Tuân thủ chính sách hoàn tiền và các thông số cài đặt hệ thống.",
            ];
        }

        $customPrompt = trim((string) ($this->settings['ai_system_prompt'] ?? ''));
        if ($customPrompt !== '') {
            $promptParts[] = "\n6. QUY ĐỊNH CẤU HÌNH TỪ QUẢN TRỊ VIÊN (ADMIN SETTINGS - ƯU TIÊN CAO NHẤT):\n" . $customPrompt;
        }

        $pageLine = $this->pageContextLine($context, $isFacebook);
        if ($pageLine !== '') {
            $promptParts[] = $pageLine;
        }

        return implode("\n", $promptParts);
    }

    private function buildKnowledgeSnippet(array $context, string $question = ''): string
    {
        $siteTitle = $this->settings['site_title'] ?? 'VC VPN';
        $contactEmail = $this->settings['contact_email'] ?? '';
        $fanpageUrl = $this->settings['fanpage_url'] ?? '';
        $zaloUrl = $this->settings['zalo_url'] ?? '';
        $minDeposit = number_format((float) ($this->settings['min_deposit_amount'] ?? 10000), 0, '.', ',') . ' đ';
        $minWithdrawal = number_format((float) ($this->settings['min_withdrawal'] ?? 50000), 0, '.', ',') . ' đ';
        $commissionRate = ($this->settings['commission_rate'] ?? '30') . '%';
        $referralBonus = number_format((float) ($this->settings['referral_bonus'] ?? 10000), 0, '.', ',') . ' đ';

        $trialEnabled = ($this->settings['trial_enabled'] ?? '0') === '1';
        $trialDuration = (int) ($this->settings['trial_duration_days'] ?? 3);

        $lines = ["=== DỮ LIỆU NỘI BỘ HỆ THỐNG {$siteTitle} ==="];

        // [1] BÀI HƯỚNG DẪN LIÊN QUAN + [2] BẢN ĐỒ TRANG & NÚT THAO TÁC —
        // đặt ĐẦU tiên: AI phải dò bài hướng dẫn trước, thiếu mới dùng bản
        // đồ trang + dữ liệu thực tế bên dưới để trả lời đúng sự thật.
        $isFacebook = in_array(strtolower((string) ($context['source'] ?? 'web')), ['fanpage', 'facebook', 'messenger'], true);
        $isLoggedIn = !empty($context['is_logged_in']);
        $siteUrl = $this->resolveSiteUrl();

        $postModel = new Post();
        $posts = $postModel->getAllPublished();
        $matches = SiteKnowledge::matchGuides($question, $posts);

        $lines[] = "\n" . SiteKnowledge::renderGuides($matches, $isFacebook, $isLoggedIn, $siteUrl);
        $currentPage = $isFacebook ? '' : trim((string) ($context['page'] ?? ''));
        $lines[] = "\n" . SiteKnowledge::renderPageMap($isFacebook, $isLoggedIn, $currentPage, $siteUrl);

        // 1. Danh sách Gói Cước VPN
        $planModel = new VpnPlan();
        $plans = $planModel->getAllActiveWithGroup();
        if (empty($plans)) {
            $plans = $planModel->getAllActive();
        }

        $lines[] = "\n[3. DANH SÁCH GÓI DỊCH VỤ VPN ĐANG BÁN]:";
        if (!empty($plans)) {
            foreach ($plans as $plan) {
                $priceFormatted = number_format((float) ($plan['price'] ?? 0), 0, '.', ',') . ' VND';
                $bandwidth = empty($plan['bandwidth_limit_gb']) || (int) $plan['bandwidth_limit_gb'] === 0
                    ? 'Không giới hạn dung lượng'
                    : ((int) $plan['bandwidth_limit_gb'] . ' GB/tháng');
                $devices = (int) ($plan['max_devices'] ?? 1) . ' thiết bị dùng cùng lúc';
                $duration = (int) ($plan['duration_days'] ?? 30) . ' ngày';
                $groupName = !empty($plan['group_name']) && $plan['group_name'] !== 'Chưa chọn nhóm' ? " | Server: {$plan['group_name']}" : '';
                $desc = !empty($plan['description']) ? (" | Chi tiết: " . trim((string) $plan['description'])) : '';

                $lines[] = "• **{$plan['name']}** (Mã: {$plan['code']}): Giá {$priceFormatted} | Hạn dùng: {$duration} | Băng thông: {$bandwidth} | Tối đa: {$devices}{$groupName}{$desc}";
            }
        } else {
            $lines[] = "- Hiện tại hệ thống đang cập nhật bảng giá gói mới. Khách hàng vui lòng theo dõi trên website hoặc liên hệ hỗ trợ.";
        }

        if ($trialEnabled) {
            $lines[] = "• **Chính sách Dùng Thử**: Có gói dùng thử miễn phí {$trialDuration} ngày dành cho tài khoản mới đăng ký lần đầu.";
        }

        // 2. Danh sách Mã Giảm Giá Đang Hoạt Động
        $couponModel = new Coupon();
        $activeCoupons = $couponModel->getActiveCoupons();

        // Mã đã gán riêng cho user: chỉ hiện cho user được gán (khách
        // chưa đăng nhập user_id=0 chỉ thấy mã công khai).
        if (!empty($activeCoupons)) {
            $chatUserId = (int) ($context['user_id'] ?? 0);
            $assignments = $couponModel->getAssignmentsForCoupons(array_column($activeCoupons, 'id'));
            $activeCoupons = array_values(array_filter(
                $activeCoupons,
                static function ($c) use ($assignments, $chatUserId) {
                    $assigned = $assignments[(int) $c['id']] ?? [];
                    return empty($assigned) || in_array($chatUserId, array_keys($assigned), true);
                }
            ));
        }

        // [4] KHUYẾN MÃI HIỆN HÀNH — mã giảm giá + popup/ưu đãi đang chạy thật
        // trên site (BƯỚC 4.2). Giữ nguyên tiền tố "[4" vì prompt file tham chiếu.
        $lines[] = "\n[4. KHUYẾN MÃI HIỆN HÀNH — MÃ GIẢM GIÁ & ƯU ĐÃI ĐANG CHẠY TRÊN SITE]:";

        $couponShown = false;
        if (!empty($activeCoupons)) {
            $couponShown = true;
            // Gói áp dụng: không có bản ghi = mọi gói; có = chỉ gói được chọn
            $planMap = $couponModel->getPlanAssignmentsForCoupons(array_column($activeCoupons, 'id'));
            foreach ($activeCoupons as $c) {
                $discountStr = ($c['discount_type'] === 'percent')
                    ? ((float) $c['discount_value'] . '%')
                    : (number_format((float) $c['discount_value'], 0, '.', ',') . ' VND');
                $expireStr = !empty($c['expires_at']) ? (" | HSD: " . date('d/m/Y', strtotime($c['expires_at']))) : ' | Không giới hạn thời gian';
                $usesLeft = ((int) $c['max_uses'] > 0) ? (" | Còn lại: " . ((int) $c['max_uses'] - (int) $c['used_count']) . " lượt") : '';
                $assignedPlans = $planMap[(int) $c['id']] ?? [];
                $planStr = empty($assignedPlans)
                    ? ' | Áp dụng: mọi gói'
                    : ' | Áp dụng chỉ: ' . implode(', ', $assignedPlans);

                $lines[] = "• Mã: **`{$c['code']}`** - Giảm: **{$discountStr}**{$expireStr}{$usesLeft}{$planStr}";
            }
        }

        // Popup/ưu đãi khách THẤY khi vào trang (vc_posts type=popup, đã xuất bản).
        $popupShown = false;
        try {
            $popups = $postModel->getPublishedPopups();
        } catch (\Throwable $e) {
            $popups = [];
        }
        if (!empty($popups)) {
            $popupShown = true;
            foreach ($popups as $pp) {
                $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($pp['content'] ?? ''))) ?? '');
                if (mb_strlen($excerpt) > 220) {
                    $excerpt = mb_substr($excerpt, 0, 220) . '…';
                }
                $slug = trim((string) ($pp['slug'] ?? ''));
                $slugStr = $slug !== '' ? " (slug: {$slug})" : '';
                $lines[] = "• **[POPUP/ƯU ĐÃI ĐANG CHẠY]** {$pp['title']}{$slugStr}: " . ($excerpt !== '' ? $excerpt : '(xem chi tiết tại trang chủ)');
            }
            $lines[] = "* POPUP là banner ưu đãi khách thấy ngay khi vào website — nhắc lại ĐÚNG nội dung ưu đãi này ở bước chốt đơn, không tự đặt tên/khuyến mại thêm.";
        }

        if ($couponShown) {
            $lines[] = "* LƯU Ý: Khi khách hỏi về giá hoặc có ý định mua, hãy chủ động nhắc khách áp dụng mã giảm giá này ở bước thanh toán để kích thích chốt đơn!";
        } elseif (!$popupShown) {
            $lines[] = "- Hiện tại không có mã giảm giá phụ nào đang kích hoạt. Hãy thông báo cho khách rằng giá niêm yết trên website đã là giá ưu đãi trực tiếp tốt nhất.";
        }

        // 5. Danh sách Bài Viết Hướng Dẫn & FAQ (đã tải $posts ở trên)
        $lines[] = "\n[5. BÀI VIẾT HƯỚNG DẪN & TÀI LIỆU KỸ THUẬT]:";
        if (!empty($posts)) {
            foreach (array_slice($posts, 0, 8) as $p) {
                $type = ($p['type'] ?? '') === 'tutorial' ? '[Hướng dẫn]' : '[Tin tức]';
                $lines[] = "• {$type} **{$p['title']}** (Đường dẫn slug: {$p['slug']})";
            }
        } else {
            $lines[] = "• Hướng dẫn kết nối VPN: Hỗ trợ đầy đủ cho iOS (Shadowrocket), Android (v2rayNG, Sing-box), Windows (v2rayN, Clash Verge), macOS, Android TV.";
        }

        // 6. Chính Sách & Điều Khoản Vận Hành
        $lines[] = "\n[6. CHÍNH SÁCH VẬN HÀNH & HỖ TRỢ]:";
        $lines[] = "- **Chính sách Hoàn Tiền**: Được xem xét xử lý hoàn tiền nếu do lỗi kỹ thuật máy chủ kéo dài không khắc phục được hoặc do lỗi thanh toán trùng lặp. Không áp dụng hoàn tiền khi dịch vụ đã sử dụng bình thường, tài khoản vi phạm quy chế hoặc thay đổi ý định cá nhân.";
        $lines[] = "- **Nạp / Rút Tiền**: Nạp tối thiểu vào ví {$minDeposit}, rút hoa hồng tối thiểu {$minWithdrawal}.";
        $lines[] = "- **Tiếp Thị Liên Kết (Affiliate)**: Nhận hoa hồng lên đến {$commissionRate} cho mỗi đơn hàng giới thiệu thành công. Đăng ký qua mã giới thiệu được tặng {$referralBonus} vào ví.";
        $lines[] = "- **Kênh Hỗ Trợ Chính Thức**:"
            . ($contactEmail ? " Email: {$contactEmail} |" : "")
            . ($fanpageUrl ? " Fanpage: {$fanpageUrl} |" : "")
            . ($zaloUrl ? " Zalo: {$zaloUrl}" : "");

        return implode("\n", $lines);
    }

    /**
     * Tên miền gốc của site (https://...) — nguồn duy nhất cho URL
     * Facebook, bản đồ trang và giải macro câu trả lời.
     */
    private function resolveSiteUrl(): string
    {
        $siteUrl = rtrim((string) ($this->settings['site_url'] ?? (getenv('APP_URL') ?: '')), '/');
        if ($siteUrl === '') {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $siteUrl = $host !== '' ? 'https://' . $host : 'https://vcvpn.com';
        }

        return $siteUrl;
    }

    /**
     * An toàn macro trong câu trả lời: file prompt bắt buộc model dùng
     * {url_faq}, {domain}... nhưng KHÔNG có nơi nào thay thế — nếu model
     * sinh ra thì khách sẽ thấy chữ thô. Thay tại đây trước khi trả/cached.
     *
     * Web: giữ đường dẫn tương đối (widget tự link). Facebook: bung thành
     * URL đầy đủ https://... (Messenger không render link tương đối).
     */
    private function resolveAnswerMacros(string $answer, bool $isFacebook): string
    {
        if ($answer === '' || !str_contains($answer, '{')) {
            return $answer;
        }

        $siteUrl = $this->resolveSiteUrl();
        $map = [
            'url_home' => '/', 'url_faq' => '/faq', 'url_download' => '/download',
            'url_terms' => '/terms', 'url_privacy' => '/privacy', 'url_refund' => '/refund',
            'url_register' => '/register', 'url_login' => '/login',
            'url_dashboard' => '/dashboard', 'url_guides' => '/user/guides',
            'url_user_downloads' => '/user/downloads', 'url_user_plans' => '/user/plans',
            'url_subscriptions' => '/subscriptions', 'url_orders' => '/orders',
            'url_deposit' => '/payments/deposit', 'url_tickets' => '/tickets/create',
        ];
        foreach ($map as $macro => $path) {
            $answer = str_replace('{' . $macro . '}', $isFacebook ? $siteUrl . $path : $path, $answer);
        }

        // Macro lạ {url_xxx} / {macro_url} còn sót → trỏ về đường dẫn site.
        $answer = (string) preg_replace_callback(
            '/\{(?:macro_url|url_)([a-z0-9_\/-]*)\}/i',
            static function (array $m) use ($siteUrl, $isFacebook): string {
                $slug = trim($m[1], '_-');
                $path = '/' . ($slug !== '' ? trim($slug, '/') : '');
                return $isFacebook ? $siteUrl . $path : $path;
            },
            $answer
        );

        // {domain} trong URL đầy đủ https://{domain}/... → thay bằng host thật.
        return str_replace('{domain}', (string) (parse_url($siteUrl, PHP_URL_HOST) ?: 'vcvpn.com'), $answer);
    }

    private function shouldHandoff(string $question, string $answer): bool
    {
        $text = $this->normalizeHandoffText($question . ' ' . $answer);
        $keywords = [
            'hoan tien',
            'khieu nai',
            'lua dao',
            'that bai',
            'khong thanh toan duoc',
            'doi nhan vien',
            'nguoi that',
            'nhan vien',
            'support truc tiep'
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHandoffText(string $text): string
    {
        return strtr(mb_strtolower($text), [
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'đ' => 'd',
            'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ]);
    }

    private function fallbackResponse(string $message): array
    {
        return [
            'success' => true,
            'answer' => $message,
            'handoff' => true,
            'provider' => 'fallback',
            'model' => '',
            'cta' => null,
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

