<?php

declare(strict_types=1);

namespace App\AI\Assistant;

use App\AI\Contracts\AIResult;
use App\AI\Core\AICore;
use App\AI\Core\PromptRegistry;
use App\AI\Knowledge\AdminActionTools;
use App\AI\Knowledge\AdminStatsTools;
use App\AI\Knowledge\AssistantStore;
use App\AI\Knowledge\PageEditTools;
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
    /** Tổng số lượt gọi AI cho một lần gửi (đủ cho: đọc kế hoạch → sửa → trả lời). */
    private const MAX_AI_CALLS = 5;

    /** Headroom cho JSON tool dài (page_edit HTML) — đã probe: Kira chấp nhận 4096. */
    private const MAX_TOKENS = 4096;

    /** Mô hình dự phòng không-reasoning (đã probe: JSON tool parse OK) khi mô hình chính trả rỗng/bị cắt. */
    private const FALLBACK_MODEL = 'coding-mimo-v2.6-flash';

    private AssistantStore $store;
    private PromptRegistry $prompts;
    /** @var array{url?: string, title?: string, text?: string} bối cảnh trang bong bóng đang mở. */
    private array $pageCtx = [];

    public function __construct(private AICore $core, ?AssistantStore $store = null, ?PromptRegistry $prompts = null)
    {
        $this->store = $store ?? new AssistantStore();
        $this->prompts = $prompts ?? new PromptRegistry();
    }

    /**
     * Gửi một lượt chat.
     *
     * @param array<int, array{name: string, text?: string, image_data_uri?: string}> $attachments
     * @param array{url?: string, title?: string, text?: string} $page bối cảnh trang admin đang mở (từ bong bóng, đã sanitize)
     * @return array{ok: bool, reply?: string, error?: string}
     */
    public function send(string $convId, string $model, string $userText, array $attachments = [], array $page = []): array
    {
        if (trim($userText) === '' && $attachments === []) {
            return ['ok' => false, 'error' => 'Nội dung tin nhắn trống.'];
        }

        // Bối cảnh trang admin bong bóng đang mở — dùng cho page_get/page_edit
        // và để AI biết admin đang đứng ở trang nào ([] khi bong bóng chưa mở).
        $this->pageCtx = $page;

        $storedText = $this->buildStoredUserText($userText, $attachments);
        $this->store->append($convId, ['role' => 'user', 'content' => $storedText]);

        $history = $this->store->messages($convId);
        $system = $this->systemPrompt($convId);
        $images = array_values(array_filter($attachments, fn(array $a): bool => ($a['image_data_uri'] ?? '') !== ''));

        $messages = $this->buildMessages($system, $history, $images, $userText);
        $toolsRun = 0;
        $fallbackUsed = false;

        for ($call = 1; $call <= self::MAX_AI_CALLS; $call++) {
            $result = $this->core->chat($messages, [
                'module'      => 'admin_assistant',
                'model'       => $model,
                'max_tokens'  => self::MAX_TOKENS,
                'temperature' => 0.6,
            ]);

            if (!$result->isOk()) {
                // Chỉ lưu câu trả lời hoàn chỉnh — lỗi trả về UI (đã có nút gửi lại).
                return ['ok' => false, 'error' => $this->friendlyError($result, $model)];
            }

            $content = trim((string) $result->content);
            $finish = (string) ($result->raw['choices'][0]['finish_reason'] ?? '');
            $toolCall = $content === '' ? null : $this->parseToolCall($content);

            // Chặn phản hồi hỏng: hết ngân sách token (finish_reason=length) hoặc
            // JSON tool bị cắt mà parse thất bại → KHÔNG lưu, KHÔNG hiện JSON thô.
            // (JSON parse được = nội dung đầy đủ dù báo length → vẫn chạy tool.)
            // Thử mô hình dự phòng 1 lần (chưa chạy tool nào nên không lặp tác dụng phụ).
            if ($content === '' || ($toolCall === null
                && ($finish === 'length' || $this->looksLikeRawToolJson($content)))) {
                if (!$fallbackUsed && $toolsRun === 0 && $model !== self::FALLBACK_MODEL) {
                    $fallbackUsed = true;
                    $model = self::FALLBACK_MODEL;
                    $messages = $this->buildMessages($system, $history, $images, $userText);
                    $call = 0; // quay về lượt 1 với mô hình dự phòng
                    continue;
                }

                return ['ok' => false, 'error' => $this->truncatedReplyError($model, $content, $finish)];
            }

            $isLast = $call === self::MAX_AI_CALLS;

            if ($toolCall === null || $isLast) {
                if ($toolCall !== null) {
                    // Vẫn chạy nốt tool cuối rồi trả kết quả trực tiếp cho admin.
                    $resultJson = $this->dispatchTool($convId, (string) $toolCall['tool'], (array) $toolCall['args']);
                    $content = "Kết quả truy vấn:\n```json\n" . json_encode($resultJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n```";
                }
                $this->store->append($convId, ['role' => 'assistant', 'content' => $content]);
                return ['ok' => true, 'reply' => $content];
            }

            // Vòng tool: thêm câu gọi của model + kết quả công cụ (chỉ trong RAM).
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $toolResult = $this->dispatchTool($convId, (string) $toolCall['tool'], (array) $toolCall['args']);
            $toolsRun++;
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

    private function systemPrompt(string $convId = ''): string
    {
        $siteName = $this->siteName();
        $siteUrl = $this->siteUrl();

        try {
            $prompt = $this->prompts->get('admin_assistant', 'admin_assistant', [
                'site_name' => $siteName,
                'site_url'  => $siteUrl,
                'date'      => date('d/m/Y'),
            ]);
            $system = trim((string) $prompt['system']);
        } catch (\Throwable $e) {
            $system = 'Bạn là TRỢ LÝ AI của QUẢN TRỊ VIÊN hệ thống VPN ' . $siteName . ' (' . $siteUrl . ').';
        }
        // Lớp đề phòng file prompt cũ chưa có {{site_url}}.
        if ($siteUrl !== '' && !str_contains($system, $siteUrl)) {
            $system .= "\nTên miền hệ thống: " . $siteUrl;
        }

        return $system
            . "\n" . AdminStatsTools::protocolBlock()
            . "\n" . AdminActionTools::protocolBlock()
            . "\n" . PageEditTools::protocolBlock()
            . "\n" . $this->planProtocolBlock()
            . "\n" . $this->planContextBlock($convId)
            . "\n" . $this->pageContextBlock();
    }

    /**
     * Khối "TRANG ADMIN HIỆN TẠI" mà bong bóng đang mở — AI biết admin đang ở
     * trang nào; trang rỗng → cấm dùng page_get/page_edit.
     */
    private function pageContextBlock(): string
    {
        $url = trim((string) ($this->pageCtx['url'] ?? ''));
        if ($url === '') {
            return "\n### TRANG ADMIN HIỆN TẠI\n"
                . 'Bong bóng chưa cung cấp bối cảnh trang. KHÔNG dùng page_get/page_edit — '
                . 'nếu cần thao tác nội dung trang hãy hỏi admin đang mở trang nào.';
        }

        $title = trim((string) ($this->pageCtx['title'] ?? ''));
        $text = trim((string) ($this->pageCtx['text'] ?? ''));
        $block = "\n### TRANG ADMIN HIỆN TẠI\n"
            . 'Đường dẫn: ' . $url . "\n"
            . 'Tiêu đề: ' . $title;
        if ($text !== '') {
            $block .= "\nNội dung đang hiển thị trên trang (thông tin bảo mật đã bị ẩn):\n\"\"\"\n"
                . $text . "\n\"\"\"";
        }

        return $block;
    }

    /** Tên website thật từ settings (site_name → site_title → fallback). */
    private function siteName(): string
    {
        try {
            $s = new Setting();
            $name = trim((string) ($s->get('site_name') ?: ''));
            if ($name !== '') {
                return $name;
            }
            $title = trim((string) ($s->get('site_title') ?: ''));
            if ($title !== '') {
                return $title;
            }
        } catch (\Throwable $e) {
            // Giữ tên mặc định.
        }

        return 'VC VPN';
    }

    /** Tên miền hệ thống: origin ĐANG DÙNG (HTTP_HOST + scheme thật) → site_url → APP_URL → fallback. */
    private function siteUrl(): string
    {
        // Ưu tiên origin người dùng đang mở (khớp thực tế: scheme, host, port đều đúng).
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

            return ($https ? 'https' : 'http') . '://' . $host;
        }
        try {
            $u = rtrim(trim((string) ((new Setting())->get('site_url') ?? '')), '/');
            if ($u !== '') {
                return $u;
            }
        } catch (\Throwable $e) {
            // Sang bước kế tiếp.
        }
        $u = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');

        return $u !== '' ? $u : 'https://vcvpn.com';
    }

    /** Khối mô tả công cụ kế hoạch (gắn vào system prompt). */
    private function planProtocolBlock(): string
    {
        return implode("\n", [
            "\n### CÔNG CỤ KẾ HOẠCH (đọc & sửa file kế hoạch đã lưu)",
            'Khi admin yêu cầu xem/sửa/cập nhật một kế hoạch đã tạo, hãy gọi tool kế hoạch — CHỈ trả về MỘT JSON trong code block ```json:',
            '{"tool": "<ten_tool>", "args": {...}}',
            '- plan_list: Liệt kê mọi kế hoạch hiện có. args: {}.',
            '- plan_read: Đọc TRỌN VẸN một kế hoạch. args: {plan_id: string}. BẮT BUỘC đọc trước khi sửa.',
            '- plan_update: Ghi/cập nhật kế hoạch. args: {plan_id: string, find?: string, replace?: string (thay đúng đoạn find — ƯU TIÊN khi sửa một phần), content?: string (ghi đè toàn bộ Markdown)}. Thiếu plan_id → dùng plan_list tìm theo tiêu đề.',
            'QUY TẮC: (1) plan_read trước mọi plan_update. (2) Chỉ sửa một đoạn → find/replace; viết lại toàn bộ → content. (3) Sau khi cập nhật OK, trả lời kèm link TƯƠNG ĐỐI [Xem kế hoạch](/admin/assistant/plan/view?id=<plan_id>) — KHÔNG ghép domain/https vào link này. (4) KHÔNG bịa nội dung kế hoạch — mọi thứ phải đọc từ file thật qua tool.',
        ]);
    }

    /** Khối thực tế: danh sách kế hoạch đã lưu + kế hoạch đang làm của hội thoại này. */
    private function planContextBlock(string $convId): string
    {
        try {
            $plans = $this->store->listPlans();
        } catch (\Throwable $e) {
            $plans = [];
        }
        $lines = ["\n### KẾ HOẠCH THỰC TẾ ĐANG LƯU (danh sách này luôn được cập nhật)"];
        if ($plans === []) {
            $lines[] = '- Chưa có kế hoạch nào được lưu.';
        } else {
            foreach ($plans as $p) {
                $id = (string) ($p['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $when = (string) ($p['updated_at'] ?? $p['created_at'] ?? '');
                $lines[] = '- ' . $id . ' | ' . (string) ($p['title'] ?? '(không tên)')
                    . ' | loại: ' . (string) ($p['kind'] ?? 'other')
                    . ($when !== '' ? ' | cập nhật: ' . $when : '');
            }
        }
        $activeId = '';
        if ($convId !== '') {
            try {
                foreach ($this->store->listConversations() as $c) {
                    if ((string) ($c['id'] ?? '') === $convId) {
                        $activeId = (string) ($c['active_plan'] ?? '');
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Không có meta hội thoại — bỏ qua.
            }
        }
        if ($activeId !== '') {
            foreach ($plans as $p) {
                if ((string) ($p['id'] ?? '') === $activeId) {
                    $lines[] = 'Kế hoạch đang làm việc trong hội thoại này: ' . $activeId
                        . ' — ' . (string) ($p['title'] ?? '');
                    break;
                }
            }
        }

        return implode("\n", $lines);
    }

    /** Phân công tool: nhánh kế hoạch xử lý trong service, còn lại sang AdminStatsTools. */
    private function dispatchTool(string $convId, string $tool, array $args): array
    {
        if (in_array($tool, ['plan_list', 'plan_read', 'plan_update'], true)) {
            return $this->planDispatch($convId, $tool, $args);
        }
        if (PageEditTools::handles($tool)) {
            // Đọc/sửa trang admin bong bóng đang mở — kèm pageCtx để chặn thao tác sai trang
            return PageEditTools::dispatch($tool, $args, $this->pageCtx);
        }
        if (AdminActionTools::handles($tool)) {
            return AdminActionTools::dispatch($tool, $args);
        }

        return AdminStatsTools::dispatch($tool, $args);
    }

    /** Xử lý công cụ kế hoạch đọc/ghi file thật. */
    private function planDispatch(string $convId, string $tool, array $args): array
    {
        try {
            switch ($tool) {
                case 'plan_list':
                    return ['ok' => true, 'plans' => array_values($this->store->listPlans())];

                case 'plan_read':
                    $id = trim((string) ($args['plan_id'] ?? ''));
                    $plan = $this->store->readPlan($id);
                    if ($plan === null) {
                        return ['ok' => false, 'error' => 'Không thấy kế hoạch id=' . $id . '. Hãy gọi plan_list để lấy id.'];
                    }
                    $this->setActivePlan($convId, (string) $plan['meta']['id']);

                    return ['ok' => true, 'plan' => $plan['meta'], 'content' => $plan['content']];

                case 'plan_update':
                    $id = trim((string) ($args['plan_id'] ?? ''));
                    $plan = $this->store->readPlan($id);
                    if ($plan === null) {
                        return ['ok' => false, 'error' => 'Không thấy kế hoạch id=' . $id . '. Hãy gọi plan_list để lấy id.'];
                    }
                    $find = (string) ($args['find'] ?? '');
                    $replace = (string) ($args['replace'] ?? '');
                    $full = (string) ($args['content'] ?? '');
                    $old = (string) $plan['content'];
                    if ($find !== '') {
                        $pos = strpos($old, $find);
                        if ($pos === false) {
                            return ['ok' => false, 'error' => 'Không tìm thấy đoạn "find" trong kế hoạch. Hãy plan_read lại và chọn đúng chuỗi cần thay.'];
                        }
                        $content = substr_replace($old, $replace, $pos, strlen($find));
                    } elseif ($full !== '') {
                        $content = $full;
                    } else {
                        return ['ok' => false, 'error' => 'Thiếu tham số: cần find/replace (sửa một đoạn) hoặc content (ghi đè toàn bộ).'];
                    }
                    if (trim($content) === '') {
                        return ['ok' => false, 'error' => 'Nội dung rỗng — từ chối ghi.'];
                    }
                    if (!$this->store->updatePlan($id, $content)) {
                        return ['ok' => false, 'error' => 'Ghi file kế hoạch thất bại (id=' . $id . ').'];
                    }
                    $this->setActivePlan($convId, $id);

                    return [
                        'ok'     => true,
                        'plan_id' => $id,
                        'title'  => (string) $plan['meta']['title'],
                        'bytes'  => strlen($content),
                        'link'   => '/admin/assistant/plan/view?id=' . $id,
                    ];
            }

            return ['ok' => false, 'error' => 'Unknown plan tool: ' . $tool];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Gắn kế hoạch đang làm việc vào meta hội thoại (để các lượt sau bám sát). */
    private function setActivePlan(string $convId, string $planId): void
    {
        if ($convId === '' || $planId === '') {
            return;
        }
        try {
            $this->store->updateConversation($convId, ['active_plan' => $planId]);
        } catch (\Throwable $e) {
            // Không chặn luồng chat.
        }
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

    /**
     * Phát hiện JSON tool thô mà parseToolCall không đọc được (thường do model
     * bị cắt giữa chừng). Nội dung này TUYỆT ĐỐI không được lưu/hiện cho admin.
     */
    private function looksLikeRawToolJson(string $content): bool
    {
        return preg_match('/\{\s*"tool"\s*:/u', $content) === 1;
    }

    /** Lỗi rõ ràng cho phản hồi rỗng/bị cắt — không bao giờ kèm JSON thô. */
    private function truncatedReplyError(string $model, string $content, string $finish): string
    {
        $cause = $content === ''
            ? 'không trả nội dung (hết ngân sách token)'
            : ($finish === 'length'
                ? 'trả về nội dung bị cắt do vượt giới hạn token'
                : 'trả về JSON công cụ bị cắt/không hợp lệ');

        return 'Mô hình "' . $model . '" ' . $cause
            . ' nên phản hồi không hoàn chỉnh, không được hiển thị/lưu. '
            . 'Hãy gửi lại, hoặc chọn mô hình khác (vd: coding-mimo-v2.6-flash).';
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
