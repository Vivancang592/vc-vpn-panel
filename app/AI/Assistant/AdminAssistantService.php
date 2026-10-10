<?php

declare(strict_types=1);

namespace App\AI\Assistant;

use App\AI\Contracts\AIResult;
use App\AI\Core\AICore;
use App\AI\Core\PromptRegistry;
use App\AI\Knowledge\AssistantStore;
use App\AI\Knowledge\EntityCatalog;
use App\AI\Knowledge\EntityCrudTools;
use App\AI\Knowledge\PageEditTools;
use App\AI\Knowledge\PendingActionStore;
use App\AI\Knowledge\ToolRegistry;
use App\Models\AiConversationState;
use App\Models\AiToolCall;
use App\Models\Setting;

/**
 * Trợ Lý Admin — chat AI với protocol công cụ KHÉP KÍN.
 *
 * Đã probe (BƯỚC 1.7): Kira HỖ TRỢ native function-calling — payload `tools`
 * trả về HTTP 200 + finish_reason=tool_calls, và vòng round-trip
 * (assistant.tool_calls → role:tool → câu trả lời cuối) hoạt động đầy đủ.
 * → service gửi `tools` lấy từ ToolRegistry::nativeSchema() và ưu tiên đọc
 *   message.tool_calls.
 * → Protocol JSON text {"tool","args"} vẫn được GIỮ LẠI làm đường dự phòng
 *   cho provider/model không hỗ trợ tools (KiraProvider chỉ chuyển các key
 *   nằm trong allowlist, nên nơi khác gọi chat() sẽ không bị ảnh hưởng).
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
    /** Payload bài viết do post_draft soạn ở lượt này — gắn vào reply dạng khối post-preview. */
    private ?array $pendingPostPreview = null;
    /**
     * Hành động GHI đang chờ admin bấm xác nhận (phát hành trong lượt này) —
     * gắn vào reply cuối dạng khối ```action-confirm để UI hiển thị thẻ nút.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $pendingConfirms = [];
    private ?AiToolCall $audit = null;
    private ?AiConversationState $convState = null;
    private ?PendingActionStore $pending = null;

    public function __construct(private AICore $core, ?AssistantStore $store = null, ?PromptRegistry $prompts = null)
    {
        $this->store = $store ?? new AssistantStore();
        $this->prompts = $prompts ?? new PromptRegistry();
    }

    /** Nhật ký công cụ (bảng vc_ai_tool_calls). Lỗi DB → null, luồng chat vẫn chạy. */
    private function audit(): ?AiToolCall
    {
        try {
            return $this->audit ??= new AiToolCall();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Trạng thái ngữ cảnh (bảng vc_ai_conversation_state). */
    private function convState(): ?AiConversationState
    {
        try {
            return $this->convState ??= new AiConversationState();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Kho hành động chờ xác nhận. */
    private function pending(): PendingActionStore
    {
        return $this->pending ??= new PendingActionStore();
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
        $this->pendingPostPreview = null;
        $this->pendingConfirms = [];

        $storedText = $this->buildStoredUserText($userText, $attachments);
        $this->store->append($convId, ['role' => 'user', 'content' => $storedText]);

        $history = $this->store->messages($convId);

        // Ngân sách ngữ cảnh: tính cửa sổ lịch sử vừa token; phần tin cũ bị đẩy
        // ra ngoài → tóm tắt tích lũy (lưu DB) để AI vẫn nhớ từ ĐẦU cuộc trò chuyện.
        // applyContextWindow nối khối digest trực tiếp vào system prompt (by ref).
        $system = $this->systemPrompt($convId);
        $start = $this->applyContextWindow($convId, $history, $system);
        $images = array_values(array_filter($attachments, fn(array $a): bool => ($a['image_data_uri'] ?? '') !== ''));

        $messages = $this->buildMessages($system, $history, $images, $userText, $start);
        $toolsRun = 0;
        $fallbackUsed = false;
        // Lược đồ function-calling gửi cho model (nguồn duy nhất: ToolRegistry).
        $toolSpec = ToolRegistry::nativeSchema();

        for ($call = 1; $call <= self::MAX_AI_CALLS; $call++) {
            $chatOptions = [
                'module'      => 'admin_assistant',
                'model'       => $model,
                'max_tokens'  => self::MAX_TOKENS,
                'temperature' => 0.6,
            ];
            if ($toolSpec !== []) {
                $chatOptions['tools']       = $toolSpec;
                $chatOptions['tool_choice'] = 'auto';
            }
            $result = $this->core->chat($messages, $chatOptions);

            if (!$result->isOk()) {
                // Chỉ lưu câu trả lời hoàn chỉnh — lỗi trả về UI (đã có nút gửi lại).
                return ['ok' => false, 'error' => $this->friendlyError($result, $model)];
            }

            $content  = trim((string) $result->content);
            $finish   = (string) ($result->raw['choices'][0]['finish_reason'] ?? '');
            $native   = $this->nativeToolCall($result);
            $toolCall = $native;
            if ($toolCall === null && $content !== '') {
                $toolCall = $this->parseToolCall($content);
            }

            // Chặn phản hồi hỏng: hết ngân sách token (finish_reason=length) hoặc
            // JSON tool bị cắt mà parse thất bại → KHÔNG lưu, KHÔNG hiện JSON thô.
            // (JSON parse được = nội dung đầy đủ dù báo length → vẫn chạy tool.)
            // Thử mô hình dự phòng 1 lần (chưa chạy tool nào nên không lặp tác dụng phụ).
            if (($content === '' && $native === null)
                || ($toolCall === null && ($finish === 'length' || $this->looksLikeRawToolJson($content)))) {
                if (!$fallbackUsed && $toolsRun === 0 && $model !== self::FALLBACK_MODEL) {
                    $fallbackUsed = true;
                    $model = self::FALLBACK_MODEL;
                    $messages = $this->buildMessages($system, $history, $images, $userText, $start);
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
                $content = $this->appendPostPreview($content);
                $content = $this->appendActionConfirms($content);
                $this->store->append($convId, ['role' => 'assistant', 'content' => $content]);
                return ['ok' => true, 'reply' => $content];
            }

            // Vòng tool: thêm câu gọi của model + kết quả công cụ (chỉ trong RAM).
            if ($native !== null) {
                // Native function-calling: gửi đúng cặp assistant.tool_calls + role:tool.
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => $content === '' ? null : $content,
                    'tool_calls' => $native['raw_calls'],
                ];
                $toolResult = $this->dispatchTool($convId, (string) $native['tool'], (array) $native['args']);
                $toolsRun++;
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => (string) $native['id'],
                    'name'         => (string) $native['tool'],
                    'content'      => (string) json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
                continue;
            }

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
     * Đọc tool call dạng NATIVE (OpenAI-compatible) từ phản hồi của provider.
     *
     * Provider giữ nguyên phản hồi trong AIResult::$raw nên chỉ cần soi
     * raw['choices'][0]['message']['tool_calls']. Tên tool không có trong registry
     * hoặc arguments không phải JSON object → coi như không có (rơi về protocol
     * JSON text / câu trả lời thường).
     *
     * @return array{tool: string, args: array<string, mixed>, id: string, raw_calls: array<int, array<string, mixed>>}|null
     */
    private function nativeToolCall(AIResult $result): ?array
    {
        $calls = $result->raw['choices'][0]['message']['tool_calls'] ?? null;
        if (!is_array($calls) || $calls === []) {
            return null;
        }

        $first = null;
        foreach ($calls as $c) {
            if (!is_array($c)) {
                continue;
            }
            $name = (string) ($c['function']['name'] ?? '');
            if ($name !== '' && ToolRegistry::handles($name)) {
                $first = $c;
                break;
            }
        }
        if ($first === null) {
            return null;
        }

        $name = (string) $first['function']['name'];
        $rawArgs = (string) ($first['function']['arguments'] ?? '');
        $decoded = $rawArgs === '' ? [] : json_decode($rawArgs, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return [
            'tool'      => $name,
            'args'      => ToolRegistry::sanitizeArgs($name, $decoded),
            'id'        => (string) ($first['id'] ?? ('call_' . substr(md5($name . $rawArgs), 0, 20))),
            'raw_calls' => array_values(array_filter($calls, 'is_array')),
        ];
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
            . 'Khi được yêu cầu, hãy viết MỘT bản kế hoạch hoàn chỉnh bằng Markdown, 100% tiếng Việt, gồm: '
            . 'mục tiêu, các giai đoạn/công việc cụ thể (bullet có hành động rõ ràng), '
            . 'mốc thời gian gợi ý, và tiêu chí đo lường. '
            . 'Không mở đầu lan man, không hỏi lại — bám sát thông tin admin cung cấp. '
            . 'TUYỆT ĐỐI KHÔNG dùng bảng Markdown (dòng kiểu "| cột 1 | cột 2 |") — khó đọc; '
            . 'dùng heading và bullet (-) thay thế.';

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
    // Cổng xác nhận hành động (admin bấm nút trên thẻ action-confirm)
    // -----------------------------------------------------------------

    /**
     * Hành động đang chờ xác nhận của một đoạn chat (UI hiển thị lại khi tải
     * lại trang, hết hạn tự biến mất).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingActions(string $convId): array
    {
        try {
            $list = $this->pending()->forConversation($convId);
        } catch (\Throwable $e) {
            return [];
        }
        // Thẻ hiển thị lại sau khi tải trang lấy từ store (không còn preview
        // trong bộ nhớ) — tính lại từ args đã lưu, vẫn là thao tác đọc.
        foreach ($list as &$row) {
            if (is_array($row) && ($row['preview'] ?? null) === null) {
                $row['preview'] = $this->confirmPreview(
                    (string) ($row['tool'] ?? ''),
                    is_array($row['args'] ?? null) ? (array) $row['args'] : []
                );
            }
        }
        unset($row);
        return $list;
    }

    /**
     * Admin bấm "XÁC NHẬN THỰC HIỆN": tiêu dùng token (chạy đúng 1 lần) rồi
     * chạy tool thật, ghi audit kèm confirmed_by.
     *
     * @param array{url?: string, title?: string, text?: string} $page bối cảnh trang đang mở
     * @return array{ok: bool, result?: array<string, mixed>, error?: string}
     */
    public function confirmAction(string $convId, string $token, array $page = []): array
    {
        $entry = $this->pending()->claim($token);
        if ($entry === null) {
            return ['ok' => false, 'error' => 'Hành động này không còn hiệu lực (đã chạy, đã bỏ qua hoặc hết hạn 30 phút).'];
        }
        if ((string) ($entry['conversation'] ?? '') !== $convId) {
            return ['ok' => false, 'error' => 'Hành động không thuộc đoạn chat này.'];
        }

        $tool = (string) ($entry['tool'] ?? '');
        $args = is_array($entry['args'] ?? null) ? $entry['args'] : [];
        if (!ToolRegistry::handles($tool) || in_array($tool, ToolRegistry::serviceHandled(), true)) {
            return ['ok' => false, 'error' => 'Công cụ không còn được hỗ trợ: ' . $tool];
        }

        // Bối cảnh trang dùng cho page_edit — ưu tiên trang đang mở, nếu bong
        // bóng không gửi kèm thì dùng bản đã lưu lúc phát hành token.
        $this->pageCtx = $page !== [] ? $page : (array) ($entry['page'] ?? []);
        $this->pendingConfirms = [];

        $result = $this->runConfirmed($convId, $tool, $args);
        if (!empty($result['ok'])) {
            return ['ok' => true, 'result' => $result];
        }

        return ['ok' => false, 'error' => mb_substr((string) ($result['error'] ?? 'Không thực hiện được.'), 0, 300), 'result' => $result];
    }

    /**
     * Admin bấm "BỎ QUA": huỷ hành động, không chạy gì cả.
     *
     * @return array{ok: bool, error?: string}
     */
    public function cancelAction(string $convId, string $token): array
    {
        $entry = $this->pending()->peek($token);
        if ($entry === null) {
            return ['ok' => false, 'error' => 'Hành động không còn hiệu lực.'];
        }
        if ((string) ($entry['conversation'] ?? '') !== $convId) {
            return ['ok' => false, 'error' => 'Hành động không thuộc đoạn chat này.'];
        }
        if (!$this->pending()->cancel($token)) {
            return ['ok' => false, 'error' => 'Không bỏ qua được, hãy thử lại.'];
        }

        $log = $this->audit();
        $log?->log([
            'conversation_id' => $convId,
            'admin_id'        => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'tool'            => mb_substr((string) ($entry['tool'] ?? ''), 0, 64),
            'risk'            => (string) ($entry['risk'] ?? ToolRegistry::RISK_WRITE),
            'args_json'       => ToolRegistry::redactJson((array) ($entry['args'] ?? [])),
            'result_json'     => '{"ok":false,"status":"cancelled"}',
            'ok'              => 0,
            'error'           => 'cancelled_by_admin',
            'duration_ms'     => 0,
        ]);

        return ['ok' => true];
    }

    /**
     * Chạy tool đã được admin xác nhận + ghi audit có confirmed_by.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function runConfirmed(string $convId, string $tool, array $args): array
    {
        $target = $this->snapshotTarget($tool, $args);
        $before = null;
        if ($target !== null) {
            $before = $this->snapshot($tool, $target[0], $target[1]);
        }

        $t0 = microtime(true);
        try {
            $result = ToolRegistry::dispatch($tool, $args, $this->pageCtx);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'error' => 'Lỗi khi thực hiện: ' . $e->getMessage()];
        }
        $ms = (int) round((microtime(true) - $t0) * 1000);

        $after = $target !== null ? $this->afterSnapshot($tool, $target[0], $target[1], $result) : null;

        $log = $this->audit();
        if ($log !== null) {
            $ok = !empty($result['ok']);
            $log->log([
                'conversation_id' => $convId,
                'admin_id'        => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'tool'            => mb_substr($tool, 0, 64),
                'risk'            => (string) (ToolRegistry::get($tool)['risk'] ?? ToolRegistry::RISK_WRITE),
                'args_json'       => ToolRegistry::redactJson($args),
                'before_json'     => $before !== null ? ToolRegistry::redactJson($before) : null,
                'after_json'      => $after !== null ? ToolRegistry::redactJson($after) : null,
                'result_json'     => ToolRegistry::redactJson($result),
                'ok'              => $ok ? 1 : 0,
                'error'           => $ok ? null : mb_substr((string) ($result['error'] ?? 'unknown'), 0, 255),
                'duration_ms'     => $ms,
                'confirmed_by'    => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            ]);
        }

        return $result;
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
            . "\n" . ToolRegistry::protocolBlock()
            . "\n" . $this->planProtocolBlock()
            . "\n" . $this->planContextBlock($convId)
            . "\n" . $this->userUiBlock()
            . "\n" . $this->entitySchemaBlock()
            . "\n" . $this->pageContextBlock();
    }

    /**
     * Khối "LƯỢC ĐỒ DỮ LIỆU THẬT" — sinh trực tiếp từ EntityCatalog nên không bao giờ
     * lệch schema (khác file prompt do admin sửa tay). Model chỉ được dùng đúng cột
     * liệt kê ở đây; mọi cột ngoài danh sách sẽ bị sanitizeArgs chặn.
     */
    private function entitySchemaBlock(): string
    {
        try {
            $digest = EntityCatalog::promptDigest();
        } catch (\Throwable $e) {
            return '';
        }
        if (trim($digest) === '') {
            return '';
        }

        return <<<'MD'

### LƯỢC ĐỒ DỮ LIỆU THẬT ( EntityCatalog — nguồn duy nhất, tuyệt đối không tự chế cột )
CRUD bằng 5 tool: entity_list / entity_get (đọc), entity_create / entity_update / entity_delete (ghi).
- "entity" + "id" + "data" phải khớp từng tên cột dưới đây; cột lạ sẽ bị từ chối trước khi chạy.
- Trước khi sửa/xóa: entity_get để lấy id thật và giá trị hiện tại. KHÔNG đoán id.
- Nghiệp vụ KHÔNG được sửa trực tiếp qua data: trạng thái đơn/subscription, duyệt nạp tiền, duyệt rút tiền,
  trả lời ticket, gán voucher → dùng action_run (order_status, subscription_status, subscription_renew,
  subscription_reset_traffic, subscription_reset_token, deposit_approve, withdrawal_decision, ticket_reply,
  coupon_assign, coupon_unassign, coupon_plans).
- KHÔNG xóa đơn hàng / subscription / payment / ticket (ràng buộc khóa ngoại & tiền thật): dùng action_run
  để đóng/hủy đúng nghiệp vụ. entity_delete chỉ dành cho bản ghi nháp an toàn (post, coupon, expense,
  plan, server_group, node_inbound...) — hệ thống sẽ tự chặn và chỉ đường.
- Mật khẩu / API key / token: không bao giờ yêu cầu, không bao giờ đặt vào data (bị chặn ở tầng tool).
- Mọi tool ghi đều hiện thẻ "Chờ bạn xác nhận" kèm bảng so sánh trước → sau; chỉ nói "đã thực hiện"
  SAU khi admin bấm xác nhận và tool trả ok.
MD
            . "\n" . $digest;
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
            "\n### CÔNG CỤ KẾ HOẠCH (tạo, đọc & sửa file kế hoạch đã lưu)",
            'Khi admin yêu cầu tạo/tạo mới/xem/sửa/cập nhật một kế hoạch, hãy gọi tool kế hoạch — CHỈ trả về MỘT JSON trong code block ```json:',
            '{"tool": "<ten_tool>", "args": {...}}',
            '- plan_list: Liệt kê mọi kế hoạch hiện có. args: {}.',
            '- plan_read: Đọc TRỌN VẸN một kế hoạch. args: {plan_id: string}. BẮT BUỘC đọc trước khi sửa.',
            '- plan_create: TẠO MỚI một kế hoạch khi admin yêu cầu. args: {title: string (bắt buộc, 100% tiếng Việt), kind?: "general"|"content"|"sales"|"other", content: string (bắt buộc — Markdown đầy đủ: mục tiêu, các bước, thời gian; KHÔNG dùng bảng Markdown kiểu | cột | cột, dùng heading/bullet; triển khai đúng ý admin; 100% tiếng Việt, không trộn câu/từ tiếng Anh)}.',
            '- plan_update: Ghi/cập nhật kế hoạch. args: {plan_id: string, find?: string, replace?: string (thay đúng đoạn find — ƯU TIÊN khi sửa một phần), content?: string (ghi đè toàn bộ Markdown)}. Thiếu plan_id → dùng plan_list tìm theo tiêu đề.',
            'QUY TẮC: (1) Admin yêu cầu TẠO/KHỞI TẠO kế hoạch mới → plan_create NGAY (không cần plan_read); sau khi tạo xong CHỈ báo "đã tạo" kèm tên kế hoạch — KHÔNG trả link hay nút xem (kế hoạch tự hiện ở cột "Kế Hoạch Đã Lưu", admin bấm là mở ngay trong đoạn chat). (2) plan_read trước mọi plan_update. (3) Chỉ sửa một đoạn → find/replace; viết lại toàn bộ → content. (4) Sau khi cập nhật OK CHỈ báo "đã cập nhật" — KHÔNG trả link/nút xem kế hoạch. (5) KHÔNG bịa nội dung kế hoạch — nội dung phải do tool ghi/đọc từ file thật. (6) Title + content kế hoạch BẮT BUỘC 100% tiếng Việt — không trộn câu/từ tiếng Anh (chỉ giữ tên công nghệ riêng biệt khi thật sự cần).',
        ]);
    }

    /** Khối bản đồ GIAO DIỆN TRANG NGƯỜI DÙNG — BƯỚC 4.1: nội dung chuyển hẳn về
     *  SiteKnowledge::userUiBlock() (nguồn chung với AI chat khách, không còn
     *  hard-code ở 2 nơi). Hàm giữ nguyên tên/tham số để systemPrompt() không đổi. */
    private function userUiBlock(): string
    {
        return \App\AI\Knowledge\SiteKnowledge::userUiBlock();
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

    /**
     * Phân công tool qua ToolRegistry (nguồn duy nhất) + ghi nhật ký + cổng
     * xác nhận cho hành động GHI/XÓA.
     *
     * Trình tự: tool kế hoạch (service tự xử lý) → tool cần xác nhận (phát
     * hành thẻ, CHƯA chạy) → chạy thật qua registry, đo thời gian, snapshot
     * trước/sau với page_edit, ghi vc_ai_tool_calls.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function dispatchTool(string $convId, string $tool, array $args): array
    {
        if (in_array($tool, ToolRegistry::serviceHandled(), true)) {
            return $this->runAudited($convId, $tool, $args, fn(): array => $this->planDispatch($convId, $tool, $args));
        }

        if (!ToolRegistry::handles($tool)) {
            // Tool model tự bịa → ghi audit để biết model hay bịa, trả thông báo
            // rõ ràng để model tự sửa ở lượt kế.
            return $this->runAudited($convId, $tool, $args, fn(): array => ToolRegistry::dispatch($tool, $args, $this->pageCtx));
        }

        if (ToolRegistry::needsConfirm($tool)) {
            return $this->stageConfirm($convId, $tool, $args);
        }

        $result = $this->runAudited($convId, $tool, $args, fn(): array => ToolRegistry::dispatch($tool, $args, $this->pageCtx));

        if ($tool === 'post_draft' && !empty($result['ok']) && !empty($result['preview'])) {
            // Bài CHƯA lưu — stash payload để gắn card xem trước vào reply cuối;
            // trả model bản rút gọn (nội dung model đã tự viết trong args).
            $this->pendingPostPreview = $result;
            $result['content'] = '(Bài viết đã hiển thị card xem trước trong đoạn chat cho admin — '
                . 'bấm "Lưu Bài" để lưu. Không cần nói thêm.)';
        }

        return $result;
    }

    /**
     * Cổng xác nhận: hành động rủi ro KHÔNG chạy ngay — phát hành token và
     * báo model rằng đang chờ admin bấm nút.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function stageConfirm(string $convId, string $tool, array $args): array
    {
        $summary = ToolRegistry::describe($tool, $args);
        try {
            $issued = $this->pending()->issue(
                $convId,
                $tool,
                $args,
                (string) (ToolRegistry::get($tool)['risk'] ?? ToolRegistry::RISK_WRITE),
                $summary,
                $this->pageCtx
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Không thể chuẩn bị hành động chờ xác nhận. Hãy thao tác thủ công trong admin.'];
        }

        $this->pendingConfirms[] = $issued + ['preview' => $this->confirmPreview($tool, $args)];
        $this->auditCall($convId, $tool, $args, [
            'ok'      => true,
            'status'  => 'pending_confirmation',
            'token'   => $issued['token'],
            'summary' => $issued['summary'],
        ], 0, null, null, 'pending');

        return [
            'ok'      => true,
            'status'  => 'pending_confirmation',
            'summary' => $issued['summary'],
            'note'    => 'Hệ thống đã hiển thị thẻ "XÁC NHẬN THỰC HIỆN" cho admin. '
                . 'Hành động CHƯA chạy. Hãy nói với admin rằng em đã chuẩn bị xong và '
                . 'đang chờ admin bấm xác nhận — TUYỆT ĐỐI không nói "đã thực hiện".',
        ];
    }

    /**
     * Bảng "sẽ thay đổi gì" hiển thị trên thẻ xác nhận. Best effort: mọi lỗi
     * đều trả mảng rỗng để thẻ vẫn hiển thị được phần summary chữ.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function confirmPreview(string $tool, array $args): array
    {
        try {
            if ($tool === 'page_edit') {
                return $this->pageEditPreview($args);
            }
            if (in_array($tool, ['entity_create', 'entity_update', 'entity_delete', 'action_run'], true)) {
                return EntityCrudTools::previewFor($tool, $args);
            }
        } catch (\Throwable $e) {
            return [];
        }
        return [];
    }

    /**
     * So ảnh chụp hiện tại của trang đang mở với đúng các cột mà AI định ghi.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function pageEditPreview(array $args): array
    {
        $entity = strtolower(trim((string) ($args['entity'] ?? '')));
        $id = (int) ($args['id'] ?? 0);
        if ($entity === '') {
            return [];
        }
        $current = $id > 0 ? PageEditTools::snapshot($entity, $id) : null;
        $rows = [];
        $data = is_array($args['data'] ?? null) ? (array) $args['data'] : [];
        foreach ($data as $col => $val) {
            if (!is_string($col)) {
                continue;
            }
            $before = is_array($current) ? ($current[$col] ?? null) : null;
            $rows[] = [
                'column' => $col,
                'before' => self::flatPreview($before),
                'after'  => self::flatPreview($val),
            ];
        }
        return [
            'entity' => $entity,
            'label'  => $entity,
            'id'     => $id,
            'url'    => (string) ($this->pageCtx['url'] ?? ''),
            'mode'   => 'update',
            'rows'   => $rows,
        ];
    }

    private static function flatPreview(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $s = (string) $v;
        return mb_strlen($s) > 200 ? mb_substr($s, 0, 200) . '…' : $s;
    }

    /**
     * Chạy tool + đo thời gian + snapshot trước/sau (page_edit) + ghi audit.
     *
     * @param array<string, mixed> $args
     * @param callable(): array<string, mixed> $run
     * @return array<string, mixed>
     */
    private function runAudited(string $convId, string $tool, array $args, callable $run): array
    {
        $target = $this->snapshotTarget($tool, $args);
        $before = null;
        if ($target !== null) {
            $before = $this->snapshot($tool, $target[0], $target[1]);
        }

        $t0 = microtime(true);
        $result = $run();
        $ms = (int) round((microtime(true) - $t0) * 1000);

        $after = $target !== null ? $this->afterSnapshot($tool, $target[0], $target[1], $result) : null;

        $this->auditCall($convId, $tool, $args, $result, $ms, $before, $after, null);

        return $result;
    }

    /**
     * Ảnh chụp dữ liệu thật của một bản ghi (cho cột before/after của audit).
     *
     * Hai nguồn ảnh chụp: `page_edit` dùng PageEditTools (giữ nguyên hành vi
     * BƯỚC 1 — đọc qua chính trang đang mở), mọi tool CRUD/danh bạ khác dùng
     * EntityCrudTools (đọc theo bảng của danh bạ, đã che cột mật khẩu/token).
     *
     * @return array<string, mixed>|null
     */
    private function snapshot(string $tool, string $entity, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            if ($tool === 'page_edit') {
                return PageEditTools::snapshot($entity, $id);
            }
            return EntityCrudTools::snapshot($entity, $id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Bản ghi nào bị lời gọi tool tác động? null = không có bản ghi cụ thể.
     *
     * @param array<string, mixed> $args
     * @return array{0:string,1:int}|null
     */
    private function snapshotTarget(string $tool, array $args): ?array
    {
        if ($tool === 'page_edit') {
            $entity = strtolower(trim((string) ($args['entity'] ?? '')));
            $id = (int) ($args['id'] ?? 0);
            return ($entity !== '' && $id > 0) ? [$entity, $id] : null;
        }

        if (!in_array($tool, ['entity_create', 'entity_update', 'entity_delete', 'action_run'], true)) {
            return null;
        }

        try {
            return EntityCrudTools::targetOf($tool, $args);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ảnh chụp sau khi chạy. Với thao tác tạo mới, id chỉ có sau khi INSERT
     * thành công nên phải lấy từ kết quả của tool. Thao tác xóa thành công thì
     * không còn gì để chụp — nhật ký chỉ giữ ảnh "trước".
     *
     * @param array<string, mixed> $result
     */
    private function afterSnapshot(string $tool, string $entity, int $id, array $result): ?array
    {
        if (empty($result['ok'])) {
            return null;
        }
        $finalId = $id > 0 ? $id : (int) ($result['id'] ?? 0);
        if ($finalId <= 0) {
            return null;
        }
        if ($tool === 'entity_delete') {
            return ['deleted' => true, 'entity' => $entity, 'id' => $finalId];
        }
        return $this->snapshot($tool, $entity, $finalId);
    }

    /**
     * Ghi một dòng nhật ký công cụ — best effort, không bao giờ làm hỏng chat.
     *
     * @param array<string, mixed>    $args
     * @param array<string, mixed>    $result
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function auditCall(
        string $convId,
        string $tool,
        array $args,
        array $result,
        int $durationMs,
        ?array $before,
        ?array $after,
        ?string $status = null
    ): void {
        $log = $this->audit();
        if ($log === null) {
            return;
        }

        $ok = $status === 'pending' ? true : !empty($result['ok']);
        $error = $status === 'pending'
            ? 'pending_confirmation'
            : ($ok ? null : mb_substr((string) ($result['error'] ?? 'unknown'), 0, 255));

        try {
            $log->log([
                'conversation_id' => $convId,
                'admin_id'        => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'tool'            => mb_substr($tool, 0, 64),
                'risk'            => (string) (ToolRegistry::get($tool)['risk'] ?? ToolRegistry::RISK_READ),
                'args_json'       => ToolRegistry::redactJson($args),
                'before_json'     => $before !== null ? ToolRegistry::redactJson($before) : null,
                'after_json'      => $after !== null ? ToolRegistry::redactJson($after) : null,
                'result_json'     => ToolRegistry::redactJson($result),
                'ok'              => $ok ? 1 : 0,
                'error'           => $error,
                'duration_ms'     => $durationMs,
            ]);
        } catch (\Throwable $e) {
            // Bỏ qua — audit không được chặn nghiệp vụ.
        }
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

                case 'plan_create':
                    $title = trim((string) ($args['title'] ?? ''));
                    $content = (string) ($args['content'] ?? '');
                    $kind = (string) ($args['kind'] ?? 'other');
                    if ($title === '') {
                        return ['ok' => false, 'error' => 'Thiếu title — hãy đặt tên kế hoạch (tiếng Việt) trước khi tạo.'];
                    }
                    if (trim($content) === '') {
                        return ['ok' => false, 'error' => 'Thiếu content — kế hoạch phải có nội dung Markdown đầy đủ (mục tiêu, các bước, thời gian...).'];
                    }
                    $meta = $this->store->savePlan($title, $kind, $content);
                    $newId = (string) ($meta['id'] ?? '');
                    $this->setActivePlan($convId, $newId);

                    return [
                        'ok'      => true,
                        'plan_id' => $newId,
                        'title'   => (string) ($meta['title'] ?? $title),
                        'kind'    => (string) ($meta['kind'] ?? 'other'),
                        'bytes'   => (int) ($meta['bytes'] ?? strlen($content)),
                    ];

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
     * @param int $start chỉ số tin ĐẦU TIÊN được đưa vào cửa sổ ngữ cảnh
     * @return array<int, array{role: string, content: mixed}>
     */
    private function buildMessages(string $system, array $history, array $images, string $userText, int $start = 0): array
    {
        $messages = [['role' => 'system', 'content' => $system]];

        foreach ($history as $i => $msg) {
            if ($i < $start) {
                continue; // đã được tóm tắt trong system prompt (digest)
            }
            $role = (string) ($msg['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = (string) ($msg['content'] ?? '');
            if ($role === 'assistant') {
                // Khối post-preview (bài xem trước) + action-confirm (thẻ xác nhận)
                // là UI-render — loại khỏi message gửi model: model không "nhìn"
                // lại nội dung đã soạn cũng như token xác nhận.
                $content = (string) preg_replace('/```post-preview\n.*?\n```/s', '', $content);
                $content = (string) preg_replace('/```action-confirm\n.*?\n```/s', '', $content);
            }
            $content = trim($content);
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
     * Áp ngân sách ngữ cảnh cho lịch sử: tính cửa sổ vừa token, dựng bản tóm tắt
     * tích luỹ cho phần tin bị đẩy ra ngoài và lưu vào vc_ai_conversation_state.
     *
     * KHÔNG bao giờ sửa lịch sử JSONL — chỉ quyết định gì được gửi lên model.
     *
     * @param array<int, array{role: string, content: mixed}> $history
     * @param-out string $system
     * @return int chỉ số tin đầu tiên được giữ
     */
    private function applyContextWindow(string $convId, array $history, string &$system): int
    {
        $count = count($history);
        if ($convId === '' || $count === 0) {
            return 0;
        }

        $state = $this->convState();
        $saved = $state?->forConversation($convId);
        $covered = (int) ($saved['covered_through'] ?? 0);

        // Digest cũ đã bao phủ các tin [0, covered) — chỉ tóm tắt thêm phần mới
        // bị đẩy ra ngoài. Chốt an toàn: không bao giờ bỏ hết lịch sử.
        $win = ContextBudget::window($system, $history);
        $start = (int) $win['start'];
        if ($covered > $start) {
            $start = min($covered, max(0, $count - 1));
        }

        if ($start <= 0) {
            // Còn vừa ngân sách: giữ nguyên digest đã có (nếu có) cho mượt hội thoại.
            $system .= ContextBudget::digestBlock((string) ($saved['summary'] ?? ''), $covered);
            $state?->touchTokens($convId, (int) $win['tokens']);

            return 0;
        }

        $digest = (string) ($saved['summary'] ?? '');
        $fresh = ContextBudget::digest(array_slice($history, $covered, $start - $covered));
        if ($fresh !== '') {
            $digest = $digest === '' ? $fresh : $digest . "\n" . $fresh;
        }
        if (mb_strlen($digest, 'UTF-8') > ContextBudget::MAX_DIGEST_CHARS) {
            $digest = (string) mb_substr($digest, -$this->digestKeepChars(), null, 'UTF-8');
        }

        $state?->save($convId, $digest, $start, (int) $win['tokens']);
        $system .= ContextBudget::digestBlock($digest, $start);

        return $start;
    }

    /** Số ký tự digest giữ lại khi cắt (dành cho bản mới nhất). */
    private function digestKeepChars(): int
    {
        return (int) (ContextBudget::MAX_DIGEST_CHARS * 0.9);
    }

    /**
     * Gắn payload bài xem trước (post_draft ở lượt này) vào cuối reply dạng
     * ```post-preview\n{json}\n``` — giao diện parse/render card TRƯỚC khi lưu.
     * Thoát backtick trong JSON (thay U+2027) để bài chứa code fence không
     * phá khối marker; JSON.parse tự khôi phục ký tự trong chuỗi.
     */
    private function appendPostPreview(string $content): string
    {
        if ($this->pendingPostPreview === null) {
            return $content;
        }
        $payload = [
            'preview_id' => (string) ($this->pendingPostPreview['preview_id'] ?? ''),
            'title'      => (string) ($this->pendingPostPreview['title'] ?? ''),
            'content'    => (string) ($this->pendingPostPreview['content'] ?? ''),
            'type'       => (string) ($this->pendingPostPreview['type'] ?? 'news'),
            'slug'       => (string) ($this->pendingPostPreview['slug'] ?? ''),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $content;
        }
        $json = str_replace('`', "\u{2027}", $json);
        $this->pendingPostPreview = null;
        return $content . "\n\n```post-preview\n" . $json . "\n```";
    }

    /**
     * Gắn mọi hành động đang chờ xác nhận (phát hành ở lượt này) vào cuối reply
     * dạng ```action-confirm\n{json}\n``` — giao diện render thẻ có nút
     * "XÁC NHẬN THỰC HIỆN" / "BỎ QUA" trỏ tới /admin/assistant/action/confirm.
     * Token là chuỗi hex an toàn; cùng kỹ thuật thoát backtick như post-preview.
     */
    private function appendActionConfirms(string $content): string
    {
        if ($this->pendingConfirms === []) {
            return $content;
        }

        foreach ($this->pendingConfirms as $c) {
            $payload = [
                'token'      => (string) ($c['token'] ?? ''),
                'tool'       => (string) ($c['tool'] ?? ''),
                'summary'    => (string) ($c['summary'] ?? ''),
                'risk'       => (string) ($c['risk'] ?? ToolRegistry::RISK_WRITE),
                'expires_at' => (string) ($c['expires_at'] ?? ''),
                'preview'    => is_array($c['preview'] ?? null) ? $c['preview'] : null,
            ];
            if ($payload['token'] === '') {
                continue;
            }
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                continue;
            }
            $content .= "\n\n```action-confirm\n" . str_replace('`', "\u{2027}", $json) . "\n```";
        }

        $this->pendingConfirms = [];

        return $content;
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
