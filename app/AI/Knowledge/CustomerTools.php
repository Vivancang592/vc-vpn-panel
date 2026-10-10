<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\Subscription;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VpnPlan;
use App\Models\Withdrawal;

/**
 * CustomerTools — bộ công cụ TRUY VẤN SỰ THẬT cho AI phục vụ khách hàng
 * (BƯỚC 3.4 — chống bịa thông tin).
 *
 * Thay vì nhồi dữ liệu vào prompt (prompt phình to, model dễ "suy đoán"), AI
 * chỉ cần LẤY ĐÚNG số liệu từ CSDL rồi trả lời trên số liệu đó.
 *
 * Hai đường gọi, đều trả về dữ liệu THẬT:
 *  1) prefetch(): dò ý định khách bằng keyword TRƯỚC lượt model → nhét thẳng
 *     khối "DỮ LIỆU THẬT" vào system prompt → chỉ tốn 1 lượt gọi model.
 *  2) Model tự trả về JSON {"tool":"...","args":{...}} → ChatbotService dispatch
 *     → gọi model thêm 1 lần với kết quả (phủ case prefetch không đoán được).
 *
 * AN TOÀN DỮ LIỆU: không bao giờ đẩy password_hash / email / số tài khoản ngân
 * hàng nguyên văn cho model. UUID gói che bớt, số tài khoản giữ 4 số cuối.
 */
final class CustomerTools
{
    /** 6 công cụ đúng theo kế hoạch BƯỚC 3.4. */
    public const PLAN = 'lookup_plan';
    public const COUPON = 'lookup_coupon';
    public const ORDER = 'my_order_status';
    public const SUBSCRIPTION = 'my_subscription_status';
    public const WALLET = 'my_wallet';
    public const TICKET = 'open_ticket';

    public const NAMES = [
        self::PLAN,
        self::COUPON,
        self::ORDER,
        self::SUBSCRIPTION,
        self::WALLET,
        self::TICKET,
    ];

    /** Công cụ cần tài khoản đăng nhập (khách vãng lai → trả lời "chưa đăng nhập"). */
    public const PRIVATE_TOOLS = [self::ORDER, self::SUBSCRIPTION, self::WALLET, self::TICKET];

    /** Tối đa số vé hỗ trợ đang mở 1 khách được tạo qua chat (chống spam). */
    public const MAX_OPEN_TICKETS = 3;

    /** Khoảng thời gian trùng tiêu đề được coi là "đã tạo rồi". */
    public const TICKET_DEDUPE_SECONDS = 180;

    /** @var array<int, array<string, mixed>>|null cache danh sách gói trong 1 request */
    private static ?array $planCache = null;

    // -----------------------------------------------------------------
    // Khoản dò ý định + định tuyến
    // -----------------------------------------------------------------

    public static function handles(string $tool): bool
    {
        return in_array($tool, self::NAMES, true);
    }

    /**
     * Khai báo tool theo chuẩn OpenAI function-calling (dùng cho prompt/lượt 2).
     * @return array<int, array<string, mixed>>
     */
    public static function spec(): array
    {
        $defs = [
            self::PLAN => [
                'description' => 'Tra cứu GÓI DỊCH VỤ VPN đang bán (giá, hạn dùng, băng thông, thiết bị, tồn kho).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Tên gói, mã gói hoặc từ khóa cần tìm. Bỏ trống = lấy danh sách đang bán.'],
                    ],
                    'required' => [],
                ],
            ],
            self::COUPON => [
                'description' => 'Tra cứu MÃ GIẢM GIÁ còn hiệu lực và mức áp dụng cho khách này.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'Mã giảm giá cần kiểm tra. Bỏ trống = danh sách đang chạy.'],
                    ],
                    'required' => [],
                ],
            ],
            self::ORDER => [
                'description' => 'Xem ĐƠN HÀNG của khách (mã đơn, trạng thái thanh toán, số tiền).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_code' => ['type' => 'string', 'description' => 'Mã đơn cần xem. Bỏ trống = đơn gần nhất.'],
                    ],
                    'required' => [],
                ],
            ],
            self::SUBSCRIPTION => [
                'description' => 'Xem GÓI ĐANG DÙNG / hiệu lực / hạn dùng / thiết bị / lưu lượng của khách.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Lọc theo trạng thái: active|expired|suspended|cancelled. Bỏ trống = lấy tất cả.'],
                    ],
                    'required' => [],
                ],
            ],
            self::WALLET => [
                'description' => 'Xem SỐ DƯ VÍ, hoa hồng chờ rút và lịch nạp/rút gần đây của khách.',
                'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
            ],
            self::TICKET => [
                'description' => 'TẠO phiếu hỗ trợ mới cho khách. Chỉ gọi khi khách yêu cầu rõ ràng việc tạo phiếu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Tiêu đề phiếu (tối đa 255 ký tự).'],
                        'message' => ['type' => 'string', 'description' => 'Nội dung mô tả vấn đề của khách.'],
                    ],
                    'required' => ['subject'],
                ],
            ],
        ];

        $out = [];
        foreach (self::NAMES as $name) {
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $defs[$name]['description'],
                    'parameters' => $defs[$name]['parameters'],
                ],
            ];
        }

        return $out;
    }

    /**
     * Dò ý định khách hàng từ CÂU HỎI (không cần model).
     * Trả về danh sách tool sẽ chạy, đúng thứ tự plan → coupon → order →
     * subscription → wallet → ticket. Không đoán bừa: chỉ match pattern rõ ràng
     * hoặc trùng TÊN/MÃ gói thật trong CSDL.
     *
     * @return array<int, string>
     */
    public static function prefetch(string $message, array $context = []): array
    {
        $q = self::norm($message);
        if ($q === '') {
            return [];
        }

        $found = [];

        // lookup_plan: khớp TÊN hoặc MÃ gói thật trong CSDL (chính xác tuyệt đối)
        // + các câu hỏi giá/gói kinh điển.
        foreach (self::planCatalog() as $plan) {
            $code = self::norm((string) ($plan['code'] ?? ''));
            if ($code !== '' && str_contains($q, $code)) {
                $found[self::PLAN] = true;
                break;
            }
            $name = self::norm((string) ($plan['name'] ?? ''));
            if (mb_strlen($name, 'UTF-8') >= 5 && str_contains($q, $name)) {
                $found[self::PLAN] = true;
                break;
            }
        }
        if (preg_match('/\b(bao nhieu tien|gia goi|gia dich vu|goi nao|goi gia|mua goi|chon goi|nang cap goi|goi dich vu|goi vpn|goi cu|goi phu hop)\b/u', $q) === 1) {
            $found[self::PLAN] = true;
        }

        // lookup_coupon
        if (preg_match('/\b(ma giam gia|ma giam|giam gia|coupon|uu dai|khuyen mai|co ma nao|dung ma|nhap ma)\b/u', $q) === 1) {
            $found[self::COUPON] = true;
        }

        // my_order_status
        if (preg_match('/\b(don hang|kiem tra don|trang thai don|ma don|don cua toi|don da mua|don moi|chua duyet|don nay|thanh toan don)\b/u', $q) === 1) {
            $found[self::ORDER] = true;
        }

        // my_subscription_status
        if (preg_match('/\b(goi cua toi|goi hien tai|goi dang dung|trang thai goi|het han|con han|hieu luc|gia han|luu luong|da dung|thiet bi|uuid|subscription|ket noi that bai|goi da mua)\b/u', $q) === 1) {
            $found[self::SUBSCRIPTION] = true;
        }

        // my_wallet
        if (preg_match('/\b(so du|vi tien|nap tien|rut tien|hoa hong|du tai khoan|tai khoan con|con bao nhieu tien|lich su nap|lich su rut)\b/u', $q) === 1) {
            $found[self::WALLET] = true;
        }

        // open_ticket — CHỈ khi có động từ tạo phiếu RÕ RÀNG (không tự tạo bừa).
        // Cho phép từ đệm ngắn giữa động từ và danh từ: "tạo giúp tôi một vé".
        // Tuyệt đối không khớp kiểu "tạo hợp đồng về thanh toán".
        $filler = '(?:\s+(?:giup|cho|toi|tui|minh|em|anh|chi|mot|1|nhanh|nhe|nha|xin|dum|dum|hay|giup))';
        if (preg_match('/\b(?:tao|mo|dang ky|lap|lam|gui)(?:' . $filler . ')*\s+(?:ve|phieu|ticket)\b/u', $q) === 1
            || preg_match('/\b(?:tao|mo|gui)(?:' . $filler . ')*\s+(?:yeu cau\s+ho tro|ho tro)\b/u', $q) === 1) {
            $found[self::TICKET] = true;
        }

        $ordered = [];
        foreach (self::NAMES as $name) {
            if (isset($found[$name])) {
                $ordered[] = $name;
            }
        }

        return $ordered;
    }

    /** JSON tool call do model tự trả về: {"tool":"x","args":{...}}. */
    public static function parseToolCall(string $content): ?array
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

    /** Trả lời còn dính JSON tool → TUYỆT ĐỐI không được gửi cho khách. */
    public static function looksLikeToolJson(string $content): bool
    {
        return preg_match('/\{\s*"tool"\s*:/u', $content) === 1;
    }

    // -----------------------------------------------------------------
    // Thực thi công cụ
    // -----------------------------------------------------------------

    /**
     * Chạy 1 công cụ. Trả về:
     *   ok    — tool chạy được không
     *   data  — dữ liệu thô (dùng cho kiểm thử / vòng gọi 2)
     *   text  — dòng sự thật tiếng Việt nhét vào prompt (ràng buộc model)
     *
     * @return array{ok: bool, tool: string, data: array<string, mixed>, text: string}
     */
    public static function dispatch(string $tool, array $args = [], array $context = []): array
    {
        if (!self::handles($tool)) {
            return ['ok' => false, 'tool' => $tool, 'data' => [], 'text' => 'Không tồn tại công cụ ' . $tool . '.'];
        }

        $userId = (int) ($context['user_id'] ?? 0);

        try {
            switch ($tool) {
                case self::PLAN:
                    return self::runPlan($args);
                case self::COUPON:
                    return self::runCoupon($args, $userId);
                case self::ORDER:
                    return self::runOrders($args, $userId);
                case self::SUBSCRIPTION:
                    return self::runSubscriptions($args, $userId);
                case self::WALLET:
                    return self::runWallet($userId);
                case self::TICKET:
                    return self::runTicket($args, $userId);
            }
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'tool' => $tool,
                'data' => ['error' => $e->getMessage()],
                'text' => "Không truy vấn được {tool} lúc này. KHÔNG được đoán — hãy nói khách thử lại sau hoặc liên hệ nhân viên.",
            ];
        }

        return ['ok' => false, 'tool' => $tool, 'data' => [], 'text' => 'Công cụ ' . $tool . ' chưa được hỗ trợ.'];
    }

    /** Chạy một danh sách tool (prefetch). */
    public static function dispatchAll(array $tools, array $context = []): array
    {
        $out = [];
        foreach ($tools as $tool) {
            if (!is_string($tool) || !self::handles($tool)) {
                continue;
            }
            $out[] = self::dispatch($tool, [], $context);
        }

        return $out;
    }

    /** @param array<int, array{ok: bool, tool: string, data: array<string, mixed>, text: string}> $results */
    public static function renderFactBlock(array $results): string
    {
        if ($results === []) {
            return '';
        }

        $lines = ["\n=== DỮ LIỆU THẬT TRÚC TIẾP TỪ CSDL (chỉ dùng số liệu bên dưới) ==="];
        foreach ($results as $r) {
            if (!is_array($r)) {
                continue;
            }
            $tool = (string) ($r['tool'] ?? '');
            $text = trim((string) ($r['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $lines[] = "[{$tool}] " . $text;
        }
        $lines[] = 'QUY TẮC BẮT BUỘC: Nếu thông tin khách cần KHÔNG có ở trên → trả lời đúng là "chưa có thông tin này" và đề nghị liên hệ nhân viên. TUYỆT ĐỐI KHÔNG được suy đoán, không được bịa số liệu.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** Mô tả giao thức gọi công cụ cho model (ngắn, nhét vào system prompt). */
    public static function protocolBlock(array $context = []): string
    {
        $userId = (int) ($context['user_id'] ?? 0);
        $allowed = [];
        foreach (self::NAMES as $name) {
            if (in_array($name, self::PRIVATE_TOOLS, true) && $userId <= 0) {
                continue;
            }
            $allowed[] = $name;
        }
        if ($allowed === []) {
            return '';
        }

        return "\n### CÔNG CỤ TRUY VẤN DỮ LIỆU THẬT\n"
            . 'Muốn lấy số liệu thật hãy trả về MỘT khối JSON (không giải thích): '
            . '{"tool":"tên_công_cụ","args":{...}}\n'
            . 'Công cụ dùng được lúc này: ' . implode(', ', $allowed) . "\n"
            . "Không có số liệu trong CSDL thì nói rõ \"chưa có thông tin\", không được bịa.\n";
    }

    // -----------------------------------------------------------------
    // Từng công cụ
    // -----------------------------------------------------------------

    /** @return array{ok: bool, tool: string, data: array<string, mixed>, text: string} */
    private static function runPlan(array $args): array
    {
        $keyword = trim((string) ($args['keyword'] ?? $args['code'] ?? $args['name'] ?? ''));
        $plans = self::planCatalog();

        if ($keyword !== '') {
            $n = self::norm($keyword);
            $plans = array_values(array_filter(
                $plans,
                static function (array $p) use ($n): bool {
                    return str_contains(self::norm((string) ($p['code'] ?? '')), $n)
                        || str_contains(self::norm((string) ($p['name'] ?? '')), $n);
                }
            ));
        }

        $plans = array_slice($plans, 0, 8);
        if ($plans === []) {
            return [
                'ok' => true,
                'tool' => self::PLAN,
                'data' => ['matched' => [], 'keyword' => $keyword],
                'text' => 'Không tìm thấy gói dịch vụ nào khớp với "' . ($keyword !== '' ? $keyword : 'yêu cầu') . '". KHÔNG được bịa tên gói.',
            ];
        }

        $lines = [];
        foreach ($plans as $p) {
            $price = number_format((float) ($p['price'] ?? 0), 0, '.', ',') . ' VND';
            $duration = (int) ($p['duration_days'] ?? 0) . ' ngày';
            $bw = (int) ($p['bandwidth_limit_gb'] ?? 0) > 0
                ? ((int) $p['bandwidth_limit_gb'] . ' GB/tháng')
                : 'Không giới hạn dung lượng';
            $devices = (int) ($p['max_devices'] ?? 1) . ' thiết bị';
            $stock = ($p['stock_quantity'] ?? null) === null
                ? 'không giới hạn suất'
                : ((int) $p['stock_quantity'] . ' suất còn lại');
            $status = ($p['status'] ?? '') === 'active' ? 'đang bán' : 'TẠM NGỪNG bán';

            $lines[] = '- **' . (string) $p['name'] . '** (mã ' . (string) $p['code'] . '): ' . $price
                . ' | ' . $duration . ' | ' . $bw . ' | ' . $devices . ' | tồn kho: ' . $stock . ' | ' . $status;
        }

        return [
            'ok' => true,
            'tool' => self::PLAN,
            'data' => ['matched' => $plans],
            'text' => "Các gói dịch vụ (giá niêm yết THẬT):\n" . implode("\n", $lines),
        ];
    }

    /** @return array{ok: bool, tool: string, data: array<string, mixed>, text: string} */
    private static function runCoupon(array $args, int $userId): array
    {
        $code = strtoupper(trim((string) ($args['code'] ?? $args['keyword'] ?? '')));
        $model = new Coupon();

        if ($code !== '') {
            $coupon = $model->findByCode($code);
            if (!$coupon) {
                return [
                    'ok' => true,
                    'tool' => self::COUPON,
                    'data' => ['code' => $code, 'found' => false],
                    'text' => "Mã {$code} KHÔNG tồn tại trong hệ thống. Không được phép bịa mã khác.",
                ];
            }
            $coupons = [$coupon];
        } else {
            $coupons = $model->getActiveCoupons();
        }

        $planAssignments = $model->getPlanAssignmentsForCoupons(array_map('intval', array_column($coupons, 'id')));

        $lines = [];
        $data = [];
        foreach ($coupons as $c) {
            $couponId = (int) ($c['id'] ?? 0);

            // Hết hạn / hết lượt → nói đúng sự thật.
            $expires = (string) ($c['expires_at'] ?? '');
            $expired = $expires !== '' && strtotime($expires) < time();
            $maxUses = (int) ($c['max_uses'] ?? 0);
            $used = (int) ($c['used_count'] ?? 0);
            $soldOut = $maxUses > 0 && $used >= $maxUses;
            $inactive = ($c['status'] ?? '') !== 'active';
            $allowedUser = $model->isAllowedForUser($c, $userId);

            $value = (string) ($c['discount_type'] ?? '') === 'percent'
                ? ((float) ($c['discount_value'] ?? 0) . '%')
                : (number_format((float) ($c['discount_value'] ?? 0), 0, '.', ',') . ' VND');

            $planIds = array_keys($planAssignments[$couponId] ?? []);
            $applies = $planIds === []
                ? 'áp dụng CHO TẤT CẢ gói'
                : ('áp dụng riêng cho: ' . implode(', ', array_values($planAssignments[$couponId])));

            $note = 'còn dùng được';
            if ($inactive) {
                $note = 'ĐANG TẠT (inactive)';
            } elseif ($expired) {
                $note = 'ĐÃ HẾT HẠN ' . $expires;
            } elseif ($soldOut) {
                $note = 'ĐÃ HẾT LƯỢT DÙNG (' . $used . '/' . $maxUses . ')';
            } elseif (!$allowedUser) {
                $note = 'KHÔNG áp dụng cho tài khoản này (mã riêng)';
            }

            $lines[] = '- Mã **' . (string) $c['code'] . '**: giảm ' . $value . ' | ' . $applies
                . ' | HSD: ' . ($expires !== '' ? $expires : 'không giới hạn')
                . ' | ' . $used . '/' . ($maxUses > 0 ? $maxUses : '∞') . ' lượt | ' . $note;

            $data[] = [
                'code' => (string) ($c['code'] ?? ''),
                'discount_value' => (float) ($c['discount_value'] ?? 0),
                'expires_at' => $expires,
                'usable' => !$inactive && !$expired && !$soldOut && $allowedUser,
            ];
        }

        if ($lines === []) {
            $lines[] = 'Hiện KHÔNG có mã giảm giá nào đang hoạt động. Không được bịa mã.';
        }

        return [
            'ok' => true,
            'tool' => self::COUPON,
            'data' => ['coupons' => $data],
            'text' => "Mã giảm giá (dữ liệu THẬT):\n" . implode("\n", $lines),
        ];
    }

    /** @return array{ok: bool, tool: string, data: array<string, mixed>, text: string} */
    private static function runOrders(array $args, int $userId): array
    {
        if ($userId <= 0) {
            return [
                'ok' => true,
                'tool' => self::ORDER,
                'data' => ['logged_in' => false],
                'text' => 'KHÁCH CHƯA ĐĂNG NHẬP → không có dữ liệu đơn hàng. Hãy đề nghị khách đăng nhập rồi hỏi lại. TUYỆT ĐỐI không được đoán trạng thái đơn.',
            ];
        }

        $orders = (new Order())->getByUserId($userId);
        $code = trim((string) ($args['order_code'] ?? ''));
        if ($code !== '') {
            $codeN = self::norm($code);
            $orders = array_values(array_filter(
                $orders,
                static fn(array $o): bool => self::norm((string) ($o['order_code'] ?? '')) === $codeN
            ));
            if ($orders === []) {
                return [
                    'ok' => true,
                    'tool' => self::ORDER,
                    'data' => ['order_code' => $code, 'found' => false],
                    'text' => "Không tìm thấy đơn hàng mã {$code} trong tài khoản này. Không được bịa trạng thái đơn.",
                ];
            }
        }

        $orders = array_slice($orders, 0, 8);
        $planNames = self::planNameMap();

        $statusMap = [
            'pending' => 'CHỜ thanh toán',
            'completed' => 'ĐÃ HOÀN TẤT',
            'failed' => 'THẤT BẠI',
            'cancelled' => 'ĐÃ HỦY',
        ];

        $lines = [];
        $data = [];
        foreach ($orders as $o) {
            $planName = $planNames[(int) ($o['plan_id'] ?? 0)] ?? 'Không rõ gói';
            $status = (string) ($o['payment_status'] ?? 'pending');
            $lines[] = '- Đơn **' . (string) $o['order_code'] . '** | ' . $planName
                . ' | ' . number_format((float) ($o['total_amount'] ?? 0), 0, '.', ',') . ' VND'
                . ' | ' . ($statusMap[$status] ?? strtoupper($status))
                . ' | ' . (string) ($o['payment_method'] ?? '')
                . ' | tạo lúc ' . (string) ($o['created_at'] ?? '');

            $data[] = [
                'order_code' => (string) ($o['order_code'] ?? ''),
                'payment_status' => $status,
                'total_amount' => (float) ($o['total_amount'] ?? 0),
            ];
        }

        if ($lines === []) {
            $lines[] = 'Tài khoản này CHƯA có đơn hàng nào.';
        }

        return [
            'ok' => true,
            'tool' => self::ORDER,
            'data' => ['orders' => $data],
            'text' => "Đơn hàng của khách (dữ liệu THẬT):\n" . implode("\n", $lines),
        ];
    }

    /** @return array{ok: bool, tool: string, data: array<string, mixed>, text: string} */
    private static function runSubscriptions(array $args, int $userId): array
    {
        if ($userId <= 0) {
            return [
                'ok' => true,
                'tool' => self::SUBSCRIPTION,
                'data' => ['logged_in' => false],
                'text' => 'KHÁCH CHƯA ĐĂNG NHẬP → không có dữ liệu gói đang dùng. Hãy đề nghị khách đăng nhập. Không được đoán hạn dùng.',
            ];
        }

        $subs = (new Subscription())->getByUserId($userId);
        $filter = trim((string) ($args['status'] ?? ''));
        if ($filter !== '') {
            $subs = array_values(array_filter($subs, static fn(array $s): bool => ($s['status'] ?? '') === $filter));
        }
        if ($subs === []) {
            return [
                'ok' => true,
                'tool' => self::SUBSCRIPTION,
                'data' => ['subscriptions' => []],
                'text' => 'Tài khoản này CHƯA có gói đăng ký nào' . ($filter !== '' ? ' với trạng thái ' . $filter : '') . '. Không được bịa.',
            ];
        }

        $subs = array_slice($subs, 0, 5);
        $lines = [];
        $data = [];
        foreach ($subs as $s) {
            $transfer = (int) ($s['transfer_enable'] ?? 0);
            $used = (int) ($s['download'] ?? 0) + (int) ($s['upload'] ?? 0);
            $uuid = (string) ($s['uuid'] ?? '');
            $uuidShort = $uuid !== '' ? (mb_substr($uuid, 0, 8) . '…') : '';

            $lines[] = '- **' . (string) ($s['plan_name'] ?? 'Gói #' . (int) ($s['plan_id'] ?? 0)) . '**'
                . ' (' . (string) ($s['plan_code'] ?? '') . ') | trạng thái: ' . strtoupper((string) ($s['status'] ?? ''))
                . ' | hiệu lực: ' . (string) ($s['start_date'] ?? '') . ' → ' . (string) ($s['end_date'] ?? '')
                . ' | thiết bị: ' . (int) ($s['online_devices'] ?? 0) . '/' . (int) ($s['device_limit'] ?? $s['max_devices'] ?? 1)
                . ' đang online'
                . ' | lưu lượng: ' . ($transfer > 0 ? (number_format($used) . '/' . number_format($transfer) . ' MB') : 'không giới hạn')
                . ' | UUID: ' . $uuidShort;

            $data[] = [
                'status' => (string) ($s['status'] ?? ''),
                'end_date' => (string) ($s['end_date'] ?? ''),
                'plan_code' => (string) ($s['plan_code'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'tool' => self::SUBSCRIPTION,
            'data' => ['subscriptions' => $data],
            'text' => "Gói khách đang có (dữ liệu THẬT):\n" . implode("\n", $lines),
        ];
    }

    /** @return array{ok: bool, tool: string, data: array<string, mixed>, text: string} */
    private static function runWallet(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'ok' => true,
                'tool' => self::WALLET,
                'data' => ['logged_in' => false],
                'text' => 'KHÁCH CHƯA ĐĂNG NHẬP → không có dữ liệu số dư. Hãy đề nghị khách đăng nhập. Không được đoán số dư.',
            ];
        }

        $user = (new User())->findById($userId);
        if (!$user) {
            return [
                'ok' => false,
                'tool' => self::WALLET,
                'data' => [],
                'text' => 'Không tìm thấy tài khoản khách. Không được bịa số dư.',
            ];
        }

        $withdrawalModel = new Withdrawal();
        $pendingWithdraw = $withdrawalModel->getPendingTotalByUserId($userId);
        $recentWithdraw = array_slice($withdrawalModel->getByUserId($userId), 0, 3);
        $recentPayments = array_slice((new Payment())->getByUserId($userId), 0, 3);

        $lines = [
            '- Số dư ví: ' . number_format((float) ($user['balance'] ?? 0), 0, '.', ',') . ' VND',
            '- Hoa hồng khả dụng: ' . number_format((float) ($user['commission_balance'] ?? 0), 0, '.', ',') . ' VND',
            '- Đang chờ rút: ' . number_format($pendingWithdraw, 0, '.', ',') . ' VND',
        ];

        if ($recentWithdraw !== []) {
            $lines[] = '- Rút tiền gần đây:';
            foreach ($recentWithdraw as $w) {
                $acc = (string) ($w['bank_account_number'] ?? '');
                $masked = $acc !== '' ? ('****' . substr($acc, -4)) : '';
                $lines[] = '    + ' . number_format((float) ($w['amount'] ?? 0), 0, '.', ',') . ' VND | '
                    . (string) ($w['bank_name'] ?? '') . ' ' . $masked . ' | ' . strtoupper((string) ($w['status'] ?? ''))
                    . ' | ' . (string) ($w['created_at'] ?? '');
            }
        }
        if ($recentPayments !== []) {
            $lines[] = '- Giao dịch nạp/gần đây:';
            foreach ($recentPayments as $p) {
                $lines[] = '    + ' . number_format((float) ($p['amount'] ?? 0), 0, '.', ',') . ' VND | '
                    . strtoupper((string) ($p['type'] ?? '')) . ' | ' . strtoupper((string) ($p['status'] ?? ''))
                    . ' | ' . (string) ($p['created_at'] ?? '');
            }
        }

        return [
            'ok' => true,
            'tool' => self::WALLET,
            'data' => [
                'balance' => (float) ($user['balance'] ?? 0),
                'commission_balance' => (float) ($user['commission_balance'] ?? 0),
                'pending_withdrawal' => $pendingWithdraw,
            ],
            'text' => "Tài chính tài khoản (dữ liệu THẬT):\n" . implode("\n", $lines),
        ];
    }

    /** @return array{ok: bool, tool: string, data: array<string, mixed>, text: string} */
    private static function runTicket(array $args, int $userId): array
    {
        if ($userId <= 0) {
            return [
                'ok' => true,
                'tool' => self::TICKET,
                'data' => ['logged_in' => false],
                'text' => 'KHÁCH CHƯA ĐĂNG NHẬP → CHƯA THỂ tạo phiếu hỗ trợ. Hãy đề nghị khách đăng nhập (hoặc để lại SĐT) rồi mới tạo.',
            ];
        }

        $subject = trim(preg_replace('/\s+/u', ' ', (string) ($args['subject'] ?? '')) ?? '');
        $message = trim(preg_replace('/\s+/u', ' ', (string) ($args['message'] ?? '')) ?? '');
        if ($subject === '') {
            // PREFETCH (chưa có tiêu đề): KHÔNG tạo phiếu — chỉ cung cấp hiện trạng
            // để AI hỏi cho đúng rồi mới gọi lại công cụ này với subject.
            $pdo0 = SupportTicket::getPdo();
            $openStmt0 = $pdo0->prepare(
                "SELECT COUNT(*) FROM `vc_support_tickets` WHERE `user_id` = :uid AND `status` IN ('open','in_progress')"
            );
            $openStmt0->execute(['uid' => $userId]);
            $openCount0 = (int) $openStmt0->fetchColumn();
            $openText = $openCount0 >= self::MAX_OPEN_TICKETS
                ? 'ĐANG CÓ ' . $openCount0 . ' phiếu mở (đã đạt giới hạn ' . self::MAX_OPEN_TICKETS . ') → KHÔNG được tạo thêm.'
                : 'đang có ' . $openCount0 . '/' . self::MAX_OPEN_TICKETS . ' phiếu mở → có thể tạo thêm.';

            return [
                'ok' => false,
                'tool' => self::TICKET,
                'data' => ['error' => 'missing_subject', 'open' => $openCount0],
                'text' => 'Chưa đủ dữ liệu để TẠO phiếu (thiếu tiêu đề/mô tả). Hiện trạng THẬT: khách đã đăng nhập, '
                    . $openText
                    . ' BẮT BUỘC: hỏi khách mô tả ngắn gọn vấn đề, SAU ĐÓ mới gọi lại open_ticket với {"subject","message"}.',
            ];
        }
        if (mb_strlen($subject, 'UTF-8') > 255) {
            $subject = mb_substr($subject, 0, 255, 'UTF-8');
        }

        $pdo = SupportTicket::getPdo();

        // Chống trùng: cùng tiêu đề trong TICKET_DEDUPE_SECONDS → trả lại phiếu cũ.
        $dupStmt = $pdo->prepare(
            'SELECT `id`, `created_at` FROM `vc_support_tickets`
             WHERE `user_id` = :uid AND `subject` = :subject
               AND `created_at` >= DATE_SUB(NOW(), INTERVAL ' . self::TICKET_DEDUPE_SECONDS . ' SECOND)
             ORDER BY `id` DESC LIMIT 1'
        );
        $dupStmt->execute(['uid' => $userId, 'subject' => $subject]);
        if ($dup = $dupStmt->fetch()) {
            return [
                'ok' => true,
                'tool' => self::TICKET,
                'data' => ['ticket_id' => (int) $dup['id'], 'duplicate' => true],
                'text' => 'Phiếu hỗ trợ #' . (int) $dup['id'] . ' với tiêu đề "' . $subject . '" ĐÃ ĐƯỢC TẠO trước đó ('
                    . (string) $dup['created_at'] . '). KHÔNG tạo lại, chỉ báo cho khách là phiếu đã ghi nhận.',
            ];
        }

        $openStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `vc_support_tickets` WHERE `user_id` = :uid AND `status` IN ('open','in_progress')"
        );
        $openStmt->execute(['uid' => $userId]);
        $openCount = (int) $openStmt->fetchColumn();
        if ($openCount >= self::MAX_OPEN_TICKETS) {
            return [
                'ok' => false,
                'tool' => self::TICKET,
                'data' => ['error' => 'too_many_open', 'open' => $openCount],
                'text' => 'Khách đã có ' . $openCount . ' phiếu đang mở (tối đa ' . self::MAX_OPEN_TICKETS . '). KHÔNG tạo thêm — báo khách chờ nhân viên xử lý phiếu hiện tại.',
            ];
        }

        $ticketModel = new SupportTicket();
        $created = $ticketModel->create([
            'user_id' => $userId,
            'subject' => $subject,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$created) {
            return [
                'ok' => false,
                'tool' => self::TICKET,
                'data' => ['error' => 'insert_failed'],
                'text' => 'Tạo phiếu THẤT BẠI. Không được báo thành công — hãy nói khách thử lại hoặc liên hệ nhân viên.',
            ];
        }

        $ticketId = $ticketModel->lastInsertId();
        if ($message !== '') {
            (new TicketMessage())->create([
                'ticket_id' => $ticketId,
                'sender_id' => $userId,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'ok' => true,
            'tool' => self::TICKET,
            'data' => ['ticket_id' => $ticketId, 'subject' => $subject],
            'text' => 'ĐÃ TẠO THÀNH CÔNG phiếu hỗ trợ #' . $ticketId . ' với tiêu đề "' . $subject
                . '". Chỉ được báo đúng số phiếu này cho khách, không bịa phiếu khác.',
        ];
    }

    // -----------------------------------------------------------------
    // Nội bộ
    // -----------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private static function planCatalog(): array
    {
        if (self::$planCache !== null) {
            return self::$planCache;
        }

        try {
            $plans = (new VpnPlan())->getAll();
        } catch (\Throwable $e) {
            $plans = [];
        }

        // Sống: gói đang bán lên trước, kế đến tên A→Z.
        usort($plans, static function (array $a, array $b): int {
            $sa = ($a['status'] ?? '') === 'active' ? 0 : 1;
            $sb = ($b['status'] ?? '') === 'active' ? 0 : 1;
            if ($sa !== $sb) {
                return $sa - $sb;
            }
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return self::$planCache = $plans;
    }

    /** @return array<int, string> map plan_id → tên gói (đơn vị đã mua) */
    private static function planNameMap(): array
    {
        $map = [];
        foreach (self::planCatalog() as $p) {
            $map[(int) ($p['id'] ?? 0)] = (string) ($p['name'] ?? '');
        }

        return $map;
    }

    /** Bỏ dấu tiếng Việt + hạ chữ thường + gộp khoảng trắng (dò ý định). */
    public static function norm(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if ($text === '') {
            return '';
        }
        $text = strtr($text, [
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

        return (string) preg_replace('/\s+/u', ' ', $text);
    }
}
