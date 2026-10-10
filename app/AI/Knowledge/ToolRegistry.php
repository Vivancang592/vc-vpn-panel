<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

/**
 * ToolRegistry — danh bạ CÔNG CỤ thống nhất của Trợ Lý Admin (BƯỚC 1).
 *
 * Trước đây 3 lớp tool (thống kê / hành động / trang) tự khai báo và tự
 * dispatch rời rạc. Registry gom lại MỘT nguồn sự thật gồm:
 *   - tên tool + mô tả (sinh khối system prompt),
 *   - mức rủi ro: read | write | destructive,
 *   - needs_confirm: có phải qua cổng xác nhận của admin trước khi chạy,
 *   - handler delegate thẳng sang lớp cũ → giữ nguyên 100% guardrail
 *     (allowlist cột, DENY_RE, SETTING_ALLOW, khớp trang đang mở...).
 *
 * Registry KHÔNG chứa logic nghiệp vụ và KHÔNG tự chế tool: tool nào chưa
 * khai báo ở đây coi như không tồn tại (chặn AI bịa tên tool).
 *
 * Công cụ kế hoạch (plan_list/plan_read/plan_create/plan_update) do
 * AdminAssistantService xử lý nội bộ vì chúng cần store + convId; registry
 * chỉ khai báo metadata để prompt và audit biết tới.
 */
final class ToolRegistry
{
    public const RISK_READ        = 'read';
    public const RISK_WRITE       = 'write';
    public const RISK_DESTRUCTIVE = 'destructive';

    /**
     * Mô tả bổ sung cho các tool kế hoạch (handler nằm trong service).
     *
     * @var array<string, string>
     */
    private const PLAN_TOOLS = [
        'plan_list'   => 'Liệt kê bản kế hoạch đã lưu. args: {}.',
        'plan_read'   => 'Đọc nội dung một bản kế hoạch. args: {plan_id: string}.',
        'plan_create' => 'Tạo bản kế hoạch Markdown mới. args: {title: string, content: string (Markdown), kind?: "sales"|"support"|"tech"|"marketing"|"other"}.',
        'plan_update' => 'Sửa kế hoạch. args: {plan_id: string, find: string, replace: string} HOẶC {plan_id: string, content: string (ghi đè)}.',
    ];

    /**
     * Đặc tả tool hành động/đọc cần phân lớp rủi ro + cổng xác nhận.
     *
     * desc/table/id được suy ra từ chính mô tả tool hoặc đối tượng thao tác —
     * không nhập tay để tránh lệch với nguồn của AdminActionTools/PageEditTools.
     *
     * @var array<string, array{risk: string, needs_confirm: bool, entity?: string, note?: string}>
     */
    private const POLICY = [
        // ---- Đọc số liệu: luôn chạy ngay, không cần xác nhận ----
        'get_sales_stats'        => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'get_top_plans'          => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'get_customer_stats'     => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'get_subscription_stats' => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'get_expense_summary'    => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'user_lookup'            => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'campaign_list'          => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'page_get'               => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'plan_list'              => ['risk' => self::RISK_READ, 'needs_confirm' => false],
        'plan_read'              => ['risk' => self::RISK_READ, 'needs_confirm' => false],

        // ---- Chỉ sinh bản xem trước / file tải về: không ghi DB ----
        'post_draft'             => ['risk' => self::RISK_READ, 'needs_confirm' => false,
            'note' => 'Không ghi database — admin bấm "Lưu Bài" trên card mới lưu.'],
        'revenue_report'         => ['risk' => self::RISK_READ, 'needs_confirm' => false,
            'note' => 'Chỉ xuất file báo cáo, không sửa dữ liệu.'],

        // ---- Ghi dữ liệu: phải qua cổng xác nhận ----
        'campaign_create'        => ['risk' => self::RISK_WRITE, 'needs_confirm' => true,
            'entity' => 'campaign', 'note' => 'Lập lịch chiến dịch gửi tin cho khách (once hoặc monthly hằng tháng, tập khách gồm cả khách hàng cũ).'],
        'campaign_status'        => ['risk' => self::RISK_WRITE, 'needs_confirm' => true,
            'entity' => 'campaign', 'note' => 'Đổi trạng thái chiến dịch (pause/resume/cancel).'],
        'page_edit'              => ['risk' => self::RISK_WRITE, 'needs_confirm' => true,
            'note' => 'Sửa bản ghi đang mở trên trang admin.'],

        // ---- CRUD theo danh bạ thực thể (BƯỚC 2 — EntityCatalog/EntityCrudTools) ----
        'entity_list'            => ['risk' => self::RISK_READ, 'needs_confirm' => false,
            'note' => 'Chỉ đọc cột an toàn; mật khẩu/token/secret tự động che.'],
        'entity_get'             => ['risk' => self::RISK_READ, 'needs_confirm' => false,
            'note' => 'Chỉ đọc cột an toàn; mật khẩu/token/secret tự động che.'],
        'entity_create'          => ['risk' => self::RISK_WRITE, 'needs_confirm' => true,
            'note' => 'Ghi bảng thật — admin phải duyệt diff trên thẻ xác nhận.'],
        'entity_update'          => ['risk' => self::RISK_WRITE, 'needs_confirm' => true,
            'note' => 'Hệ thống chụp lại giá trị TRƯỚC/SAU để phục hồi nếu ghi nhầm.'],
        'entity_delete'          => ['risk' => self::RISK_DESTRUCTIVE, 'needs_confirm' => true,
            'note' => 'Xóa cứng (không hoàn tác); đơn hàng/subscription/payment bị chặn, phải dùng action_run.'],
        'action_run'             => ['risk' => self::RISK_WRITE, 'needs_confirm' => true,
            'note' => 'Nghiệp vụ chuẩn: đổi trạng thái/gia hạn/duyệt đơn/duyệt nạp/rút tiền/ticket/voucher.'],
        'plan_create'            => ['risk' => self::RISK_WRITE, 'needs_confirm' => false,
            'note' => 'Chỉ ghi file kế hoạch trong storage/assistant.'],
        'plan_update'            => ['risk' => self::RISK_WRITE, 'needs_confirm' => false,
            'note' => 'Chỉ ghi file kế hoạch trong storage/assistant.'],
    ];

    /**
     * Lược đồ tham số JSON-Schema cho native function-calling (đã probe: Kira hỗ
     * trợ `tools` + vòng tool-trip đầy đủ — HTTP 200, finish_reason=tool_calls).
     *
     * Mỗi tool KHAI BÁO BẮT BUỘC ở đây (riêng 6 tool CRUD của BƯỚC 2 khai báo
     * trong EntityCrudTools::schemas() và được ghép qua schemaMap()); tool thiếu
     * lược đồ bị loại khỏi mảng `tools` (THAY VÌ gửi schema rỗng) để model không
     * gọi tool với tham số bịa. Mô tả dài vẫn nằm trong `desc` (nguồn cho system
     * prompt dạng text).
     *
     * @var array<string, array{required: string[], properties: array<string, array<string, mixed>>}>
     */
    private const SCHEMA = [
        'get_sales_stats' => ['required' => [], 'properties' => [
            'year'  => ['type' => 'integer', 'description' => 'Năm, mặc định năm hiện tại.'],
            'month' => ['type' => 'integer', 'description' => 'Tháng 1-12, mặc định tháng hiện tại.'],
        ]],
        'get_top_plans' => ['required' => [], 'properties' => [
            'year'  => ['type' => 'integer', 'description' => 'Năm, mặc định năm hiện tại.'],
            'month' => ['type' => 'integer', 'description' => 'Tháng 1-12, mặc định tháng hiện tại.'],
            'limit' => ['type' => 'integer', 'description' => 'Số gói lấy về, 1-10, mặc định 5.'],
        ]],
        'get_customer_stats' => ['required' => [], 'properties' => []],
        'get_subscription_stats' => ['required' => [], 'properties' => []],
        'get_expense_summary' => ['required' => [], 'properties' => [
            'year'  => ['type' => 'integer', 'description' => 'Năm, mặc định năm hiện tại.'],
            'month' => ['type' => 'integer', 'description' => 'Tháng 1-12, mặc định tháng hiện tại.'],
        ]],
        'user_lookup' => ['required' => [], 'properties' => [
            'q' => ['type' => 'string', 'description' => 'Tên hoặc email cần tìm; để trống lấy tối đa 50 tài khoản.'],
        ]],
        'campaign_list' => ['required' => [], 'properties' => []],
        'campaign_create' => ['required' => ['kind', 'title', 'body', 'target_mode'], 'properties' => [
            'kind'         => ['type' => 'string', 'enum' => ['email', 'fb'], 'description' => 'Kênh gửi tin.'],
            'title'        => ['type' => 'string', 'description' => 'Tên chiến dịch.'],
            'subject'      => ['type' => 'string', 'description' => 'Tiêu đề email (bắt buộc khi kind=email).'],
            'body'         => ['type' => 'string', 'description' => 'Nội dung tin nhắn/email đã soạn.'],
            'target_mode'  => ['type' => 'string', 'enum' => ['all_users', 'all_fans', 'specific', 'old_customers'], 'description' => 'Tập khách nhận tin; old_customers = khách cũ đã hết hạn/không hoạt động (remarketing).'],
            'user_ids'     => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'ID user khi target_mode=specific (email).'],
            'emails'       => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Email nhận trực tiếp khi target_mode=specific.'],
            'psids'        => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'PSID fan nhận khi target_mode=specific (fb).'],
            'daily_limit'  => ['type' => 'integer', 'description' => 'Số tin gửi mỗi ngày, 1-500, mặc định 50.'],
            'recurrence'   => ['type' => 'string', 'enum' => ['once', 'monthly'], 'description' => 'Nhịp gửi: once = gửi 1 lần; monthly = lặp hằng tháng (gửi kèm ưu đãi/khuyến mãi hiện có).'],
            'day_of_month' => ['type' => 'integer', 'description' => 'Ngày chạy khi recurrence=monthly, 1-31 (mặc định hôm nay), hệ thống tự gọn về cuối tháng.'],
            'personalize'  => ['type' => 'boolean', 'description' => 'true = AI soạn lại nội dung mỗi chu kỳ + điền {ten}/{goi_cu}/{uu_dai} theo dữ liệu thật.'],
        ]],
        'campaign_status' => ['required' => ['campaign_id', 'action'], 'properties' => [
            'campaign_id' => ['type' => 'integer', 'description' => 'ID chiến dịch.'],
            'action'      => ['type' => 'string', 'enum' => ['pause', 'resume', 'cancel'], 'description' => 'Hành động đổi trạng thái.'],
        ]],
        'revenue_report' => ['required' => [], 'properties' => [
            'year'  => ['type' => 'integer', 'description' => 'Năm, mặc định năm hiện tại.'],
            'month' => ['type' => 'integer', 'description' => 'Tháng 1-12, mặc định tháng hiện tại.'],
        ]],
        'post_draft' => ['required' => ['title', 'content', 'type'], 'properties' => [
            'title'   => ['type' => 'string', 'description' => 'Tiêu đề bài viết.'],
            'content' => ['type' => 'string', 'description' => 'Nội dung Markdown/HTML.'],
            'type'    => ['type' => 'string', 'enum' => ['news', 'tutorial', 'faq', 'popup'], 'description' => 'Nhóm bài.'],
            'slug'    => ['type' => 'string', 'description' => 'Đường dẫn tĩnh; bỏ trống hệ thống tự sinh.'],
        ]],
        'page_get' => ['required' => ['url'], 'properties' => [
            'url' => ['type' => 'string', 'description' => 'URL trang admin đang mở, ví dụ /admin/plans/edit?id=12.'],
        ]],
        'page_edit' => ['required' => ['url', 'fields'], 'properties' => [
            'url'    => ['type' => 'string', 'description' => 'URL trang admin đang mở (phải khớp trang bong bóng đang hiển thị).'],
            'fields' => ['type' => 'object', 'description' => 'Cột => giá trị mới; tối đa 20 cột vô hướng, chỉ cột được phép.', 'additionalProperties' => true],
        ]],
        'plan_list' => ['required' => [], 'properties' => []],
        'plan_read' => ['required' => ['plan_id'], 'properties' => [
            'plan_id' => ['type' => 'string', 'description' => 'ID bản kế hoạch (xem plan_list).'],
        ]],
        'plan_create' => ['required' => ['title', 'content'], 'properties' => [
            'title'   => ['type' => 'string', 'description' => 'Tên bản kế hoạch.'],
            'content' => ['type' => 'string', 'description' => 'Nội dung Markdown.'],
            'kind'    => ['type' => 'string', 'enum' => ['sales', 'support', 'tech', 'marketing', 'other'], 'description' => 'Nhóm kế hoạch.'],
        ]],
        'plan_update' => ['required' => ['plan_id'], 'properties' => [
            'plan_id' => ['type' => 'string', 'description' => 'ID bản kế hoạch cần sửa.'],
            'find'    => ['type' => 'string', 'description' => 'Đoạn văn bản cũ cần thay thế (dùng cùng replace).'],
            'replace' => ['type' => 'string', 'description' => 'Đoạn văn bản mới.'],
            'content' => ['type' => 'string', 'description' => 'Nội dung ghi đè toàn bộ kế hoạch.'],
        ]],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cached = null;

    /** @var array<string, array{required: string[], properties: array<string, array<string, mixed>>}>|null */
    private static ?array $schemaMap = null;

    /**
     * Lược đồ tham số của MỌI tool: SCHEMA khai báo tại chỗ (stats/action/page/plan)
     * + lược đồ do EntityCrudTools tự mô tả theo EntityCatalog (BƯỚC 2).
     *
     * Gom một chỗ để nativeSchema()/sanitizeArgs() không bỏ sót tool CRUD và để
     * test đối chiếu "registry không còn tool nào thiếu lược đồ" còn đúng.
     *
     * @return array<string, array{required: string[], properties: array<string, array<string, mixed>>}>
     */
    public static function schemaMap(): array
    {
        if (self::$schemaMap !== null) {
            return self::$schemaMap;
        }
        /** @var array<string, array{required: string[], properties: array<string, array<string, mixed>>}> $crud */
        $crud = EntityCrudTools::registrySchemas();
        return self::$schemaMap = array_merge(self::SCHEMA, $crud);
    }

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $nativeCached = null;

    /**
     * Mảng `tools` chuẩn OpenAI cho native function-calling.
     *
     * Tool không có trong SCHEMA bị loại (model không được mời gọi bịa tham số).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function nativeSchema(): array
    {
        if (self::$nativeCached !== null) {
            return self::$nativeCached;
        }

        $tools = [];
        $schemas = self::schemaMap();
        foreach (self::all() as $def) {
            $name = (string) $def['name'];
            if (!isset($schemas[$name])) {
                continue;
            }
            $schema = $schemas[$name];
            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $name,
                    'description' => $def['desc'] . ($def['note'] !== '' ? ' — ' . $def['note'] : '')
                        . ($def['needs_confirm'] ? ' [CẦN ADMIN XÁC NHẬN TRƯỚC KHI CHẠY]' : ''),
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => (object) $schema['properties'],
                        'required'   => $schema['required'],
                    ],
                ],
            ];
        }

        return self::$nativeCached = $tools;
    }

    /** Tên tool có lược đồ native — dùng cho test đối chiếu. @return string[] */
    public static function nativeNames(): array
    {
        $names = array_column(array_column(self::nativeSchema(), 'function'), 'name');
        sort($names);
        return $names;
    }

    /**
     * Ép kiểu + lọc tham số model đưa về (native arguments là JSON tự do).
     *
     * Chỉ giữ khoá có khai báo trong SCHEMA; giá trị để nguyên để handler cũ
     * tự validate (allowlist cột, DENY_RE, khoảng giới hạn...).
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function sanitizeArgs(string $tool, array $args): array
    {
        $schemas = self::schemaMap();
        if (!isset($schemas[$tool])) {
            return [];
        }
        $allowed = array_keys($schemas[$tool]['properties']);
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $args)) {
                $out[$key] = $args[$key];
            }
        }
        return $out;
    }

    /**
     * Toàn bộ đặc tả tool: name => {name, desc, risk, needs_confirm, entity, note, source}.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $defs = [];
        $sources = [
            'stats'   => AdminStatsTools::toolDescriptions(),
            'action'  => AdminActionTools::toolDescriptions(),
            'page'    => PageEditTools::toolDescriptions(),
            'crud'    => EntityCrudTools::toolDescriptions(),
            'plan'    => self::PLAN_TOOLS,
        ];

        foreach ($sources as $source => $list) {
            foreach ($list as $name => $desc) {
                $policy = self::POLICY[$name] ?? null;
                $defs[$name] = [
                    'name'          => (string) $name,
                    'desc'          => (string) $desc,
                    'source'        => $source,
                    'risk'          => $policy['risk'] ?? self::RISK_READ,
                    'needs_confirm' => (bool) ($policy['needs_confirm'] ?? false),
                    'entity'        => (string) ($policy['entity'] ?? self::entityFrom((string) $name)),
                    'note'          => (string) ($policy['note'] ?? ''),
                ];
            }
        }

        ksort($defs);
        return self::$cached = $defs;
    }

    /**
     * Đặc tả một tool; null khi AI bịa tên tool không tồn tại.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $tool): ?array
    {
        return self::all()[$tool] ?? null;
    }

    public static function handles(string $tool): bool
    {
        return isset(self::all()[$tool]);
    }

    public static function isWrite(string $tool): bool
    {
        $def = self::get($tool);
        return $def !== null && $def['risk'] !== self::RISK_READ;
    }

    /** Tool này bắt buộc admin bấm xác nhận trước khi chạy? */
    public static function needsConfirm(string $tool): bool
    {
        $def = self::get($tool);
        return $def !== null && $def['needs_confirm'] === true;
    }

    /** @return string[] tên mọi tool hợp lệ. */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * Danh sách tool mà service cần tự xử lý (không có handler trong registry).
     *
     * @return string[]
     */
    public static function serviceHandled(): array
    {
        return ['plan_list', 'plan_read', 'plan_create', 'plan_update'];
    }

    /**
     * Khối "### DANH BẠ CÔNG CỤ" nhúng system prompt — thay cho 3 khối rời.
     * Trình bày theo nhóm rủi ro để model hiểu hành vi nào cần admin xác nhận.
     */
    public static function protocolBlock(): string
    {
        $lines = [
            "\n### DANH BẠ CÔNG CỤ (TOOL REGISTRY — nguồn duy nhất, ngoài danh sách này là BỊA)",
            'Nếu nền tảng cung cấp function-calling trực tiếp thì GỌI hàm đó; nếu không, trả lời CHỈ bằng MỘT JSON duy nhất (không kèm text khác), đặt trong code block ```json:',
            '{"tool": "<ten_tool>", "args": {...}}',
            'Tool hợp lệ:',
        ];

        $groups = [
            self::RISK_READ        => 'ĐỌC (chạy ngay, không đổi dữ liệu)',
            self::RISK_WRITE       => 'GHI (hệ thống sẽ xin admin XÁC NHẬN trước khi chạy)',
            self::RISK_DESTRUCTIVE => 'XÓA/HỦY (bắt buộc admin XÁC NHẬN)',
        ];

        $all = self::all();
        foreach ($groups as $risk => $label) {
            $items = array_values(array_filter($all, static fn(array $d): bool => $d['risk'] === $risk));
            if ($items === []) {
                continue;
            }
            $lines[] = '';
            $lines[] = "— {$label} —";
            foreach ($items as $d) {
                $suffix = '';
                if ($d['needs_confirm']) {
                    $suffix = ' [CẦN XÁC NHẬN]';
                }
                if ($d['note'] !== '') {
                    $suffix .= ' — ' . $d['note'];
                }
                $lines[] = '- ' . $d['name'] . ': ' . $d['desc'] . $suffix;
            }
        }

        $lines[] = '';
        $lines[] = '- Chỉ gọi tool khi admin RA LỆNH rõ ràng trong tin nhắn này. Hội thoại bình thường → trả lời thường, KHÔNG gọi tool. TUYỆT ĐỐI KHÔNG tự ý thao tác.';
        $lines[] = '- Tool GHI có gắn [CẦN XÁC NHẬN]: hệ thống tự hiển thị thẻ xác nhận cho admin; em KHÔNG được nói "đã thực hiện" trước khi admin bấm xác nhận.';
        $lines[] = '- Sau khi nhận kết quả do hệ thống gửi lại, trả lời admin bằng tiếng Việt như bình thường (không hiện JSON).';
        $lines[] = '- TUYỆT ĐỐI KHÔNG đọc/đề xuất/sửa key, mật khẩu, token, secret hay bất kỳ thông tin bảo mật nào — admin yêu cầu cũng từ chối lịch sự.';

        return implode("\n", $lines);
    }

    /**
     * Chạy tool đã đăng ký (delegate sang lớp cũ — giữ nguyên guardrail).
     *
     * Tool kế hoạch do service xử lý; gọi hàm này với tool loại đó sẽ bị từ chối
     * rõ ràng để không âm thầm chạy nhầm.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $page bối cảnh trang admin đang mở
     * @return array<string, mixed>
     */
    public static function dispatch(string $tool, array $args, array $page = []): array
    {
        $def = self::get($tool);
        if ($def === null) {
            return ['ok' => false, 'error' => 'Unknown tool: ' . $tool];
        }
        if (in_array($tool, self::serviceHandled(), true)) {
            return ['ok' => false, 'error' => 'Tool kế hoạch phải chạy qua AdminAssistantService.'];
        }

        return match ($def['source']) {
            'page'   => PageEditTools::dispatch($tool, $args, $page),
            'action' => AdminActionTools::dispatch($tool, $args),
            'crud'   => EntityCrudTools::dispatch($tool, $args, $page),
            default  => AdminStatsTools::dispatch($tool, $args),
        };
    }

    /**
     * Tóm tắt hành động hiển thị trên thẻ xác nhận + audit log.
     * Chỉ dùng chuỗi do chính ta sinh từ metadata đã kiểm soát — không bao giờ
     * in nguyên văn dữ liệu AI đưa vào.
     *
     * @param array<string, mixed> $args
     */
    public static function describe(string $tool, array $args): string
    {
        $def = self::get($tool);
        $label = $def !== null ? (string) $def['risk'] : self::RISK_READ;
        $parts = ['tool=' . $tool, 'risk=' . $label];

        $entity = (string) ($args['entity'] ?? (is_array($def) ? ($def['entity'] ?? '') : ''));
        if ($entity !== '') {
            $parts[] = 'entity=' . $entity;
        }
        foreach (['id', 'plan_id', 'campaign_id'] as $k) {
            if (isset($args[$k]) && is_scalar($args[$k])) {
                $parts[] = $k . '=' . mb_substr((string) $args[$k], 0, 64);
            }
        }
        foreach (['title', 'subject', 'action', 'kind', 'q'] as $k) {
            if (isset($args[$k]) && is_scalar($args[$k])) {
                $v = trim((string) $args[$k]);
                if ($v !== '') {
                    $parts[] = $k . '=' . mb_substr($v, 0, 80);
                }
            }
        }
        if (isset($args['fields']) && is_array($args['fields'])) {
            $cols = array_map(static fn($c): string => mb_substr((string) $c, 0, 32), array_keys($args['fields']));
            $parts[] = 'fields=' . implode(',', $cols);
        }
        // entity_create/entity_update/entity_delete: liệt kê TÊN cột, không in giá trị
        // (giá trị có thể chứa dữ liệu khách hàng — thẻ xác nhận/audit chỉ cần biết sửa gì).
        if (isset($args['data']) && is_array($args['data'])) {
            $cols = array_map(static fn($c): string => mb_substr((string) $c, 0, 32), array_keys($args['data']));
            $parts[] = 'data=' . implode(',', $cols);
        }
        if (isset($args['filters']) && is_array($args['filters'])) {
            $cols = array_map(static fn($c): string => mb_substr((string) $c, 0, 32), array_keys($args['filters']));
            $parts[] = 'filters=' . implode(',', $cols);
        }
        if (isset($args['entity']) && is_string($args['entity']) && $args['entity'] !== '') {
            $cdef = EntityCatalog::get($args['entity']);
            if ($cdef !== null) {
                $parts[] = 'table=' . (string) $cdef['table'];
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * Rút gọn JSON lưu vào audit: cắt dài + che giá trị nhạy cảm.
     *
     * @param array<string, mixed>|string $payload
     */
    public static function redactJson($payload, int $maxBytes = 4000): string
    {
        $json = is_string($payload)
            ? $payload
            : (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $json = (string) preg_replace(
            '/("(?:[a-z0-9_]*(?:pass|pwd|secret|token|api[_-]?key|private|salt|hash|otp|credential|auth)[a-z0-9_]*)"\s*:\s*)"[^"]*"/i',
            '$1"[BẢO MẬT ẨN]"',
            $json
        );
        if (strlen($json) > $maxBytes) {
            $json = substr($json, 0, $maxBytes) . '…(đã cắt)';
        }
        return $json;
    }

    /** Đoán đối tượng thao tác từ tên tool (campaign_status → campaign). */
    private static function entityFrom(string $tool): string
    {
        if (str_starts_with($tool, 'campaign_')) {
            return 'campaign';
        }
        if (str_starts_with($tool, 'plan_')) {
            return 'plan_file';
        }
        if (str_starts_with($tool, 'page_')) {
            return 'page';
        }
        return '';
    }
}
