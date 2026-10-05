<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

use App\Models\Order;

/**
 * Công cụ ĐỌC/SỬA NỘI DUNG TRANG ADMIN ĐANG MỞ (bong bóng trợ lý).
 *
 * Nguyên tắc bảo mật BẤT KHẢ XÂM PHẠM:
 *  - page_get/page_edit CHỈ hiệu lực khi "page_url" bong bóng gửi kèm khớp trang
 *    chứa đối tượng (vd /admin/posts/edit?id=3 → entity post, id 3). Trang không
 *    khớp → chặn. AI KHÔNG THỂ thao tác từ trang khác.
 *  - Cột được phép đọc/ghi nằm trong ALLOWLIST theo entity — KHÔNG nhận tên cột
 *    từ model đưa vào câu lệnh SQL (chỉ implode từ hằng số).
 *  - DENY regex chặn mọi cột/khóa liên quan mật khẩu, key, token, secret...
 *  - setting chỉ ghi được khóa trong SETTING_ALLOW (khóa bảo mật như
 *    kira_api_key, smtp_password, fanpage_app_secret... KHÔNG BAO GIỜ vào được).
 *  - Chỉ thao tác khi ADMIN RA LỆNH rõ ràng (rule nhúng vào system prompt).
 *
 * Protocol JSON text như AdminStatsTools/AdminActionTools (Kira không có tools API).
 */
final class PageEditTools
{
    /** @var array<string, string> mô tả tool cho system prompt. */
    private const TOOLS = [
        'page_get'  => 'Đọc dữ liệu đối tượng ĐANG MỞ trên trang admin hiện tại. args: {entity: "post"|"plan"|"coupon"|"expense"|"user"|"setting", id?: int}.',
        'page_edit' => 'Sửa nội dung đối tượng ĐANG MỞ theo YÊU CẦU của admin. args: {entity: "post"|"plan"|"coupon"|"expense"|"user"|"setting", id?: int, fields: {cột_được_phép: giá_trị_mới}}. CHỈ gọi khi admin chủ động yêu cầu sửa — luôn page_get trước.',
    ];

    /** Chặn tuyệt đối mọi cột/khóa có dính tới bảo mật. */
    private const DENY_RE = '/(pass|pwd|secret|token|api[_-]?key|private|salt|hash|2fa|otp|credential|auth)/i';

    /** Khóa setting duy nhất được phép đọc/ghi qua AI — mọi khóa khác bị chặn. */
    private const SETTING_ALLOW = [
        'site_name', 'site_title', 'site_description',
        'fanpage_url', 'zalo_url', 'youtube_url',
        'contact_email', 'contact_phone', 'support_zalo',
        'footer_text', 'announcement', 'maintenance_mode',
    ];

    /**
     * entity → bảng + cột đọc/ghi + giá trị enum hợp lệ.
     * read = cột AI được XEM; write = cột AI được SỬA (phải qua DENY + enum).
     *
     * @var array<string, array{table: string, read: string[], write: string[], enum: array<string, string[]>}>
     */
    private const ENTITIES = [
        'post' => [
            'table' => 'vc_posts',
            'read'  => ['id', 'title', 'slug', 'type', 'status', 'content', 'created_at'],
            'write' => ['title', 'slug', 'content', 'type', 'status'],
            'enum'  => ['type' => ['news', 'tutorial', 'faq', 'popup'], 'status' => ['published', 'draft', 'hidden']],
        ],
        'plan' => [
            'table' => 'vc_vpn_plans',
            'read'  => ['id', 'name', 'code', 'price', 'duration_days', 'bandwidth_limit_gb', 'max_devices', 'stock_quantity', 'description', 'status'],
            'write' => ['name', 'code', 'price', 'duration_days', 'bandwidth_limit_gb', 'max_devices', 'stock_quantity', 'description', 'status'],
            'enum'  => ['status' => ['active', 'inactive']],
        ],
        'coupon' => [
            'table' => 'vc_coupons',
            'read'  => ['id', 'code', 'discount_type', 'discount_value', 'max_uses', 'used_count', 'expires_at', 'status'],
            'write' => ['code', 'discount_type', 'discount_value', 'max_uses', 'expires_at', 'status'],
            'enum'  => ['discount_type' => ['percent', 'fixed'], 'status' => ['active', 'inactive']],
        ],
        'expense' => [
            'table' => 'vc_expenses',
            'read'  => ['id', 'title', 'amount', 'category', 'note', 'expense_date'],
            'write' => ['title', 'amount', 'category', 'note', 'expense_date'],
            'enum'  => [],
        ],
        'user' => [
            'table' => 'vc_users',
            'read'  => ['id', 'username', 'status', 'created_at'],
            'write' => ['username', 'status'],
            'enum'  => ['status' => ['active', 'inactive', 'banned']],
        ],
        'setting' => [
            'table' => 'vc_settings',
            'read'  => ['setting_key', 'setting_value'],
            'write' => ['setting_key', 'setting_value'],
            'enum'  => [],
        ],
    ];

    /**
     * page_url (bong bóng gửi kèm) → entity được phép thao tác trên trang đó.
     * Trang không nằm trong map → CẤM cả page_get và page_edit.
     *
     * @var array<string, string>
     */
    private const PAGES = [
        '#^/admin/posts/edit(?:\?|$)#'    => 'post',
        '#^/admin/plans/edit(?:\?|$)#'    => 'plan',
        '#^/admin/coupons/edit(?:\?|$)#'  => 'coupon',
        '#^/admin/expenses/edit(?:\?|$)#' => 'expense',
        '#^/admin/users/edit(?:\?|$)#'    => 'user',
        '#^/admin/settings(?:/|\?|$)#'    => 'setting',
    ];

    /** Khối mô tả protocol nhúng vào system prompt lúc runtime. */
    public static function protocolBlock(): string
    {
        $lines = [
            "\n### CÔNG CỤ TRANG ADMIN ĐANG MỞ (đọc/sửa nội dung trang qua bong bóng)",
            'Khi admin RA LỆNH đọc hoặc sửa nội dung trang admin đang mở → trả lời CHỈ bằng MỘT JSON trong code block ```json:',
            '{"tool": "<ten_tool>", "args": {...}}',
            'Các tool hợp lệ:',
        ];
        foreach (self::TOOLS as $name => $desc) {
            $lines[] = "- {$name}: {$desc}";
        }
        $lines[] = '- CHỈ dùng khi admin RA LỆNH rõ ràng trong tin nhắn này (vd "sửa tiêu đề bài này thành..."). Admin chỉ đang hỏi/hội thoại bình thường → trả lời thường, KHÔNG gọi tool. TUYỆT ĐỐI KHÔNG tự ý thao tác.';
        $lines[] = '- page_get/page_edit CHỈ hợp lệ khi "TRANG ADMIN HIỆN TẠI" ở cuối prompt khớp trang chứa đối tượng (vd đang mở /admin/posts/edit?id=3 → entity "post", id 3). Không khớp → từ chối, hướng admin mở bong bóng trên đúng trang.';
        $lines[] = '- LUÔN page_get đọc dữ liệu hiện tại TRƯỚC khi page_edit. Sau khi sửa OK → nêu rõ các trường đã đổi.';
        $lines[] = '- TUYỆT ĐỐI KHÔNG đọc/đề xuất/sửa key, mật khẩu, token, secret hay bất kỳ thông tin bảo mật nào — admin yêu cầu cũng từ chối lịch sự.';
        return implode("\n", $lines);
    }

    public static function handles(string $tool): bool
    {
        return array_key_exists($tool, self::TOOLS);
    }

    /**
     * Chạy tool theo tên — luôn kèm bối cảnh trang admin đang mở ($page).
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $page {url, title?, text?} từ bong bóng (đã sanitize ở controller)
     * @return array<string, mixed>
     */
    public static function dispatch(string $tool, array $args, array $page = []): array
    {
        if (!array_key_exists($tool, self::TOOLS)) {
            return ['ok' => false, 'error' => 'Unknown tool: ' . $tool];
        }

        try {
            $scope = self::resolve($page);
            if ($scope === null) {
                return [
                    'ok'    => false,
                    'error' => 'Không xác định được trang admin hiện tại chứa đối tượng — hãy bảo admin mở bong bóng trợ lý trên đúng trang cần thao tác rồi hỏi lại.',
                ];
            }

            $entity = (string) ($args['entity'] ?? '');
            if ($entity === '' || !isset(self::ENTITIES[$entity])) {
                return ['ok' => false, 'error' => 'entity không hợp lệ: ' . $entity . ' (chấp nhận: post|plan|coupon|expense|user|setting).'];
            }
            if ($entity !== $scope['entity']) {
                return [
                    'ok'    => false,
                    'error' => 'Đối tượng "' . $entity . '" KHÔNG nằm trên trang admin đang mở (trang này chứa "' . $scope['entity'] . '"). Không thao tác để tránh sửa nhầm — hãy mở đúng trang chứa đối tượng.',
                ];
            }

            $id = isset($args['id']) ? (int) $args['id'] : (int) $scope['id'];
            if ($scope['entity'] !== 'setting') {
                if ((int) $scope['id'] <= 0) {
                    return ['ok' => false, 'error' => 'Trang đang mở thiếu id đối tượng (?id=...) — không xác định được bản ghi để thao tác.'];
                }
                if ($id !== (int) $scope['id']) {
                    return [
                        'ok'    => false,
                        'error' => 'id ' . $id . ' KHÔNG khớp đối tượng đang mở trên trang (id ' . (int) $scope['id'] . '). Không thao tác để tránh sửa nhầm.',
                    ];
                }
                $id = (int) $scope['id'];
            }

            if ($tool === 'page_get') {
                return self::pageGet($entity, $id);
            }
            return self::pageEdit($entity, $id, (array) ($args['fields'] ?? []));
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Lỗi công cụ trang: ' . $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------
    // Nội bộ
    // -----------------------------------------------------------------

    /**
     * Khớp page_url với trang chứa đối tượng.
     *
     * @param array<string, mixed> $page
     * @return array{entity: string, id: int}|null
     */
    private static function resolve(array $page): ?array
    {
        $url = trim((string) ($page['url'] ?? ''));
        if ($url === '' || !str_starts_with($url, '/admin')) {
            return null;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);

        foreach (self::PAGES as $pattern => $entity) {
            if (!preg_match($pattern, $path)) {
                continue;
            }
            if ($entity === 'setting') {
                return ['entity' => 'setting', 'id' => 0];
            }
            $query = [];
            parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
            $id = (int) ($query['id'] ?? 0);
            if ($id <= 0) {
                return null; // trang edit thiếu id → không xác định được đối tượng
            }
            return ['entity' => $entity, 'id' => $id];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function pageGet(string $entity, int $id): array
    {
        $def = self::ENTITIES[$entity];
        $pdo = Order::getPdo();

        if ($entity === 'setting') {
            $in = implode(',', array_fill(0, count(self::SETTING_ALLOW), '?'));
            $stmt = $pdo->prepare(
                'SELECT setting_key, setting_value FROM vc_settings WHERE setting_key IN (' . $in . ')'
            );
            $stmt->execute(self::SETTING_ALLOW);
            $map = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $map[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }

            return [
                'ok'       => true,
                'entity'   => 'setting',
                'settings' => $map,
                'note'     => 'Chỉ khóa an toàn — khóa bảo mật (key/mật khẩu/token) không bao giờ được trả về.',
            ];
        }

        $cols = '`' . implode('`, `', $def['read']) . '`';
        $stmt = $pdo->prepare('SELECT ' . $cols . ' FROM `' . $def['table'] . '` WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['ok' => false, 'error' => 'Không tìm thấy ' . $entity . ' id ' . $id . ' (bị xóa từ trước?).'];
        }

        return ['ok' => true, 'entity' => $entity, 'id' => $id, 'row' => $row];
    }

    /** @param array<string, mixed> $fields */
    private static function pageEdit(string $entity, int $id, array $fields): array
    {
        if ($fields === []) {
            return ['ok' => false, 'error' => 'Thiếu fields: hãy nêu rõ cột cần sửa kèm giá trị mới.'];
        }
        if ($entity === 'setting') {
            return self::editSetting($fields);
        }
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'Thiếu id đối tượng đang mở trên trang.'];
        }
        if (count($fields) > 20) {
            return ['ok' => false, 'error' => 'Quá nhiều trường một lần (tối đa 20).'];
        }

        $def = self::ENTITIES[$entity];
        $set = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $col = (string) $col;
            if (!in_array($col, $def['write'], true)) {
                return [
                    'ok'    => false,
                    'error' => 'Trường "' . $col . '" KHÔNG được phép sửa trên ' . $entity
                        . '. Trường hợp lệ: ' . implode(', ', $def['write']) . '.',
                ];
            }
            if (preg_match(self::DENY_RE, $col)) {
                return ['ok' => false, 'error' => 'Trường "' . $col . '" liên quan bảo mật — bị chặn tuyệt đối.'];
            }
            if (!is_scalar($val) && $val !== null) {
                return ['ok' => false, 'error' => 'Giá trị "' . $col . '" phải là chuỗi hoặc số.'];
            }
            if (is_bool($val)) {
                $val = (int) $val;
            }
            if (is_string($val)) {
                $val = str_replace("\0", '', $val);
                if (strlen($val) > 60000) {
                    return ['ok' => false, 'error' => 'Giá trị "' . $col . '" dài quá 60000 ký tự.'];
                }
            }
            if (isset($def['enum'][$col]) && !in_array((string) $val, $def['enum'][$col], true)) {
                return [
                    'ok'    => false,
                    'error' => 'Giá trị "' . $col . '" không hợp lệ. Chấp nhận: ' . implode(' | ', $def['enum'][$col]) . '.',
                ];
            }
            $set[] = '`' . $col . '` = ?';
            $params[] = $val;
        }

        $pdo = Order::getPdo();
        $stmt = $pdo->prepare(
            'UPDATE `' . $def['table'] . '` SET ' . implode(', ', $set) . ' WHERE id = ?'
        );
        $stmt->execute(array_merge($params, [$id]));

        if ($stmt->rowCount() === 0) {
            // Có thể không đổi giá trị nào — chỉ báo lỗi khi bản ghi KHÔNG tồn tại.
            $chk = $pdo->prepare('SELECT id FROM `' . $def['table'] . '` WHERE id = ?');
            $chk->execute([$id]);
            if ($chk->fetchColumn() === false) {
                return ['ok' => false, 'error' => 'Không tìm thấy ' . $entity . ' id ' . $id . '.'];
            }
        }

        return [
            'ok'      => true,
            'entity'  => $entity,
            'id'      => $id,
            'updated' => array_keys($fields),
            'message' => 'Đã cập nhật ' . count($fields) . ' trường trên ' . $entity . ' id ' . $id . '.',
        ];
    }

    /**
     * Ghi setting — CHỈ khóa trong SETTING_ALLOW.
     * fields = {setting_key: value}.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function editSetting(array $fields): array
    {
        $pdo = Order::getPdo();
        $updated = [];
        foreach ($fields as $key => $val) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            if (preg_match(self::DENY_RE, $key)) {
                return ['ok' => false, 'error' => 'Khóa "' . $key . '" liên quan bảo mật — bị chặn tuyệt đối.'];
            }
            if (!in_array($key, self::SETTING_ALLOW, true)) {
                return [
                    'ok'    => false,
                    'error' => 'Khóa setting "' . $key . '" KHÔNG được phép sửa qua AI. Chỉ chấp nhận: '
                        . implode(', ', self::SETTING_ALLOW) . '.',
                ];
            }
            if (!is_scalar($val) && $val !== null) {
                return ['ok' => false, 'error' => 'Giá trị "' . $key . '" phải là chuỗi hoặc số.'];
            }
            $val = str_replace("\0", '', (string) $val);
            if (strlen($val) > 10000) {
                return ['ok' => false, 'error' => 'Giá trị "' . $key . '" dài quá 10000 ký tự.'];
            }

            $stmt = $pdo->prepare(
                'INSERT INTO vc_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $stmt->execute([$key, $val]);
            $updated[] = $key;
        }

        if ($updated === []) {
            return ['ok' => false, 'error' => 'Thiếu khóa setting cần sửa trong fields.'];
        }

        return [
            'ok'      => true,
            'entity'  => 'setting',
            'updated' => $updated,
            'message' => 'Đã cập nhật ' . count($updated) . ' khóa setting.',
        ];
    }
}
