<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

use App\Models\BaseModel;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Post;
use App\Models\ReferralCommission;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VpnPlan;
use App\Models\Withdrawal;
use App\Services\NodeTaskService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReferralService;
use PDO;

/**
 * EntityCrudTools — BƯỚC 2 (2.2–2.5): AI tự thao tác CRUD ĐÚNG LUỒNG NGHIỆP VỤ.
 *
 * Vì sao không để model tự sinh SQL:
 *   `BaseModel::create()/update()` ghép tên cột thẳng vào SQL và `BaseModel` KHÔNG có `$fillable`.
 *   Nếu nhận `data` từ model rồi đưa thẳng vào model thì cột/giá trị nằm ngoài tầm kiểm soát.
 *   Ở đây MỌI cột đều phải đi qua EntityCatalog + đối chiếu information_schema, rồi mới xuống DB.
 *
 * Ba lớp an toàn (không lớp nào được bỏ qua):
 *   1. WHITELIST CỘT: EntityCatalog::allowsColumn() + cột phải thật sự tồn tại trong bảng.
 *      Cột bảo mật (password/token/hash/secret...) bị chặn bằng EntityCatalog::secretColumn()
 *      — KHÔNG dùng EntityCatalog::isSecret($entity, $col) vì hàm đó trả true khi $entity rỗng.
 *   2. GIÁ TRỊ: enum theo catalog, giới hạn độ dài theo catalog/DB, kiểu số theo catalog,
 *      ngoại khóa phải tồn tại, cột unique không được trùng, đủ cột NOT NULL.
 *   3. LUỒNG: những trường mà việc đổi nó kéo theo tác vụ node/ví/stock thì KHÔNG cho sửa
 *      trực tiếp (blocked_on_write trong catalog) — AI phải dùng action_run.
 *
 * 6 tool: entity_list, entity_get, entity_create, entity_update, entity_delete, action_run.
 *
 * Phạm vi BƯỚC 2: CHỈ đọc/ghi dữ liệu. Không đụng giao diện, không đụng luồng khách hàng.
 */
final class EntityCrudTools
{
    /** Số bản ghi tối đa mỗi lần trả về cho model (giữ prompt nhỏ). */
    private const MAX_ROWS = 50;

    /**
     * Cột bị CHẶN GHI. Sao chép từ PageEditTools::DENY_RE nhưng cố ý BỎ "token" và "private":
     *   - server.api_token  là cột Admin được phép ghi (hệ thống đã cho sửa trong ServerController).
     *   - node_inbound.private_key chỉ đọc.
     * EntityCatalog::SECRET_RE là private const nên không đọc được từ đây -> khai báo bản riêng.
     */
    private const WRITE_DENY_RE = '/(pass|pwd|hash|secret|2fa|otp|salt|credential|auth)/i';

    /** Cột bị CHE KHI ĐỌC (maskRow). Bản sao của EntityCatalog::SECRET_RE (private const). */
    private const READ_MASK_RE = '/(pass|pwd|secret|token|api[_-]?key|private|salt|hash|2fa|otp|credential|auth)/i';

    /**
     * Pseudo-key được phép trong `data` của entity_create/entity_update.
     * Đây KHÔNG phải cột DB — chỉ là tham số điều khiển luồng, handler tự tiêu thụ.
     *   user.password            -> bcrypt vào password_hash (như UserController::save)
     *   coupon.plan_ids          -> Coupon::setPlans()
     *   coupon.assigned_user_ids -> Coupon::assignUsers()/removeAssignments()
     * @var array<string,array<string,string>> entity => pseudo-key => loại
     */
    private const PSEUDO_KEYS = [
        'user'   => ['password' => 'password'],
        'coupon' => [
            'plan_ids'          => 'plan_ids',
            'assigned_user_ids' => 'assigned_user_ids',
        ],
    ];

    /** @var array<string,bool> */
    private static array $columnCache = [];

    /** @var array<string,array<int,string>> */
    private static array $mandatoryCache = [];

    /** @var array<int,string> */
    public const ACTIONS = [
        'subscription_status', 'subscription_renew', 'subscription_reset_traffic', 'subscription_reset_token',
        'order_status', 'deposit_approve', 'withdrawal_decision', 'ticket_reply',
        'coupon_assign', 'coupon_unassign', 'coupon_plans',
    ];

    // =====================================================================
    //  CẦU NỐI VỚI ToolRegistry
    // =====================================================================

    /**
     * Danh sách tool + mô tả — nguồn duy nhất cho ToolRegistry (dạng map
     * tên => mô tả, giống AdminStatsTools/AdminActionTools/PageEditTools).
     *
     * @return array<string,string>
     */
    public static function toolDescriptions(): array
    {
        return [
            'entity_list'   => 'Liệt kê / tìm kiếm bản ghi của MỘT thực thể trong danh bạ (entity: user, plan, order, subscription, server, server_group, node_inbound, payment, withdrawal, referral_commission, coupon, post, expense, ticket, ticket_message + các bảng log chỉ đọc). args: {entity: string, q?: string, filters?: object, limit?: int (max ' . self::MAX_ROWS . '), page?: int}.',
            'entity_get'    => 'Lấy CHI TIẾT một bản ghi theo id (riêng subscription tra được bằng uuid). args: {entity: string, id?: int, uuid?: string}.',
            'entity_create' => 'THÊM MỚI một bản ghi. args: {entity: string, data: object (cột => giá trị theo đúng schema thật, chỉ cột được phép ghi)}.',
            'entity_update' => 'SỬA một bản ghi theo id. args: {entity: string, id: int, data: object (CHỈ chứa cột thay đổi)}.',
            'entity_delete' => 'XÓA CỨNG một bản ghi theo id — chỉ khi Admin yêu cầu rõ ràng. args: {entity: string, id: int}.',
            'action_run'    => 'Chạy một NGHIỆP VỤ CHUẨN của hệ thống thay vì sửa trực tiếp cột trạng thái/ví/tồn kho. args: {action: ' . self::actionEnumText() . ', id: int, ...}.',
        ];
    }

    /** @return array<int,string> danh sách action cho schema/prompt */
    public static function actionNames(): array
    {
        return self::ACTIONS;
    }

    /** Chuỗi liệt kê action cho mô tả tool + prompt. */
    private static function actionEnumText(): string
    {
        return '"' . implode('" | "', self::ACTIONS) . '"';
    }

    /**
     * @param string $tool
     * @param array<string,mixed> $args
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    public static function dispatch(string $tool, array $args, array $page = []): array
    {
        try {
            return match ($tool) {
                'entity_list'   => self::entityList($args),
                'entity_get'    => self::entityGet($args),
                'entity_create' => self::entityCreate($args, $page),
                'entity_update' => self::entityUpdate($args, $page),
                'entity_delete' => self::entityDelete($args, $page),
                'action_run'    => self::actionRun($args, $page),
                default         => self::fail("Tool không rõ: {$tool}"),
            };
        } catch (\Throwable $e) {
            error_log('[EntityCrudTools] ' . $tool . ': ' . $e->getMessage());
            return self::fail('Không thực hiện được thao tác: ' . $e->getMessage());
        }
    }

    /**
     * Bản mô tả "sẽ thay đổi gì" cho thẻ xác nhận trong khung chat.
     *
     * Chỉ là dự đoán từ args + ảnh chụp hiện tại — KHÔNG ghi gì. Giá trị cột
     * nhạy cảm đã che. Trả về mảng rỗng khi không suy ra được bản ghi nào.
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    /** Danh từ tiếng Việt của entity, dùng riêng cho tiêu đề thẻ xác nhận. */
    private const NOUNS = [
        'user' => 'tài khoản',
        'plan' => 'gói VPN',
        'order' => 'đơn hàng',
        'subscription' => 'subscription',
        'server' => 'server',
        'server_group' => 'nhóm server',
        'node_inbound' => 'cấu hình inbound',
        'payment' => 'giao dịch',
        'withdrawal' => 'lệnh rút tiền',
        'referral_commission' => 'hoa hồng',
        'coupon' => 'mã giảm giá',
        'coupon_plan' => 'gán gói cho mã',
        'coupon_user' => 'gán user cho mã',
        'post' => 'bài viết',
        'expense' => 'chi phí',
        'campaign' => 'chiến dịch',
        'ticket' => 'ticket hỗ trợ',
        'ticket_message' => 'tin nhắn ticket',
        'setting' => 'cấu hình hệ thống',
        'node_task' => 'job chờ xử lý',
        'system_log' => 'log hệ thống',
        'access_log' => 'log truy cập',
        'subscription_access_log' => 'log truy cập subscription',
        'chat_session' => 'phiên chat',
        'chat_message' => 'tin nhắn chat',
        'ai_tool_call' => 'nhật ký AI',
    ];

    public static function nounOf(string $entity): string
    {
        return self::NOUNS[$entity] ?? str_replace('_', ' ', $entity);
    }

    /**
     * Mô tả trực quan những gì tool sẽ thay đổi, cho thẻ "Chờ bạn xác nhận".
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function previewFor(string $tool, array $args): array
    {
        $target = self::targetOf($tool, $args);
        if ($target === null) {
            return [];
        }
        [$entity, $id] = $target;
        $def = EntityCatalog::get($entity);
        if ($def === null) {
            return [];
        }

        $out = [
            'entity' => $entity,
            'label'  => self::nounOf($entity),
            'title'  => '',
            'id'     => $id,
            'url'    => '',
            'mode'   => $tool === 'entity_create' ? 'create' : ($tool === 'entity_delete' ? 'delete' : ($tool === 'entity_update' ? 'update' : 'action')),
            'rows'   => [],
        ];

        $data = is_array($args['data'] ?? null) ? self::normalizeKeys((array) $args['data']) : [];

        $exists = $id > 0;
        $current = $id > 0 ? self::snapshot($entity, $id) : null;
        $current = is_array($current) ? $current : [];
        if ($current === []) {
            $exists = false;
        }

        if ($out['mode'] === 'action') {
            $act = strtolower(trim((string) ($args['action'] ?? '')));
            $rows = [['column' => 'nghiệp vụ', 'before' => null, 'after' => self::actionLabel($act, $args)]];
            if (isset($args['status']) && trim((string) $args['status']) !== '') {
                $rows[] = ['column' => 'trạng thái', 'before' => (string) ($current['status'] ?? ''), 'after' => (string) $args['status']];
            }
            if (isset($args['days']) && (int) $args['days'] > 0) {
                $rows[] = ['column' => 'số ngày', 'before' => (string) ($current['end_date'] ?? ''), 'after' => '+' . (int) $args['days'] . ' ngày'];
            }
            if (isset($args['decision']) && trim((string) $args['decision']) !== '') {
                $rows[] = ['column' => 'quyết định', 'before' => (string) ($current['status'] ?? ''), 'after' => (string) $args['decision']];
            }
            if (isset($args['message']) && trim((string) $args['message']) !== '') {
                $rows[] = ['column' => 'nội dung gửi khách', 'before' => null, 'after' => mb_substr(trim((string) $args['message']), 0, 300)];
            }
            foreach (['user_ids' => 'khách được gán', 'plan_ids' => 'gói áp dụng'] as $k => $lbl) {
                $ids = self::parseIdList($args[$k] ?? []);
                if ($ids !== []) {
                    $rows[] = ['column' => $lbl, 'before' => null, 'after' => implode(', ', array_slice($ids, 0, 30))];
                }
            }
            $out['rows'] = $rows;
        } elseif ($out['mode'] === 'delete') {
            $rows = [];
            foreach (['label', 'status', 'code', 'name', 'title', 'username'] as $col) {
                if (isset($current[$col]) && (string) $current[$col] !== '') {
                    $rows[] = ['column' => $col, 'before' => self::flat($current[$col]), 'after' => null];
                }
            }
            $out['rows'] = $rows;
        } elseif ($out['mode'] === 'create') {
            $rows = [];
            foreach ($data as $col => $val) {
                $rows[] = ['column' => $col, 'before' => null, 'after' => self::flat($val)];
            }
            $out['rows'] = $rows;
        } else {
            $rows = [];
            $base = is_array($current) ? $current : [];
            foreach ($data as $col => $val) {
                if (preg_match(self::WRITE_DENY_RE, (string) $col)) {
                    continue;
                }
                $rows[] = [
                    'column' => (string) $col,
                    'before' => self::flat($base[$col] ?? null),
                    'after'  => self::flat($val),
                ];
            }
            $out['rows'] = $rows;
        }

        if ($exists) {
            try {
                $out['title'] = EntityCatalog::label($entity, $current);
            } catch (\Throwable $e) {
                $out['title'] = '';
            }
        } elseif ($out['mode'] === 'create') {
            $out['title'] = self::flat($data[EntityCatalog::pk($entity)] ?? null)
                ?? self::flat($data['name'] ?? $data['title'] ?? $data['code'] ?? $data['username'] ?? null)
                ?? '';
        }

        $out['url'] = EntityCatalog::adminUrl($entity, $out['id'] > 0 ? $out['id'] : null);
        if ($out['mode'] === 'delete' && !$exists) {
            $out['rows'] = [];
        }
        return $out;
    }

    /** @param array<string,mixed> $args */
    private static function actionLabel(string $action, array $args): string
    {
        return match ($action) {
            'subscription_status'        => 'Chuyển trạng thái subscription sang ' . (string) ($args['status'] ?? '?'),
            'subscription_renew'         => 'Gia hạn subscription thêm ' . (int) ($args['days'] ?? 0) . ' ngày',
            'subscription_reset_traffic' => 'Cấp lại toàn bộ data của subscription',
            'subscription_reset_token'   => 'Đổi token/uuid subscription (khách phải cập nhật client)',
            'order_status'               => 'Đặt trạng thái đơn hàng = ' . (string) ($args['status'] ?? '?'),
            'deposit_approve'            => 'Duyệt nạp ' . number_format((float) ($args['amount'] ?? 0)) . ' đ cho khách',
            'withdrawal_decision'        => ('approved' === strtolower((string) ($args['decision'] ?? '')) ? 'Duyệt' : 'Từ chối') . ' yêu cầu rút tiền',
            'ticket_reply'               => 'Trả lời ticket cho khách',
            'coupon_assign'              => 'Gán voucher cho khách',
            'coupon_unassign'            => 'Gỡ voucher khỏi khách',
            'coupon_plans'               => 'Cập nhật danh sách gói áp dụng voucher',
            default                      => $action,
        };
    }

    /** Giá trị hiển thị ngắn, an toàn cho thẻ xác nhận. */
    private static function flat(mixed $v): ?string
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
     * Ảnh chụp phục hồi (audit before/after) cho một bản ghi theo danh bạ.
     *
     * Dùng cho AdminAssistantService khi nó cần snapshot quanh thao tác GHI/XÓA
     * của BẤT KỲ thực thể nào, không chỉ trang đang mở. Giá trị cột nhạy cảm
     * được che trước khi rời khỏi đây — ảnh chụp chỉ để đối chiếu, không để lộ
     * mật khẩu/token ra nhật ký.
     *
     * @return array<string,mixed>|null null khi entity không có trong danh bạ hoặc bản ghi không tồn tại
     */
    public static function snapshot(string $entity, int $id): ?array
    {
        $def = EntityCatalog::get($entity);
        if ($def === null || $id <= 0) {
            return null;
        }
        $pdo = self::pdo();
        $table = (string) $def['table'];
        $pk = self::pkColumn($def, $table, $pdo);
        $row = self::fetchRow($pdo, $table, $pk, $id);
        if ($row === null) {
            return null;
        }
        $row = self::maskRow($row, $entity);
        $row['_entity'] = $entity;
        return $row;
    }

    /**
     * Xác định (entity, id) bị tác động bởi một lời gọi tool ghi/xóa, dùng để
     * snapshot quanh thao tác. Trả null khi lời gọi không trỏ tới bản ghi cụ
     * thể nào (thao tác theo uuid chưa ánh xạ, tạo mới, thêm phân quyền...).
     *
     * @param array<string,mixed> $args
     * @return array{0:string,1:int}|null
     */
    public static function targetOf(string $tool, array $args): ?array
    {
        $entity = strtolower(trim((string) ($args['entity'] ?? '')));

        if ($tool === 'entity_update' || $tool === 'entity_delete') {
            $id = (int) ($args['id'] ?? 0);
            return ($entity !== '' && $id > 0) ? [$entity, $id] : null;
        }

        if ($tool === 'entity_create') {
            // Chỉ có ảnh "sau" — id do DB cấp, lấy từ kết quả trả về.
            return $entity !== '' ? [$entity, 0] : null;
        }

        if ($tool !== 'action_run') {
            return null;
        }

        $action = strtolower(trim((string) ($args['action'] ?? '')));
        $id = (int) ($args['id'] ?? 0);

        if (str_starts_with($action, 'subscription_')) {
            if ($id > 0) {
                return ['subscription', $id];
            }
            $uuid = trim((string) ($args['uuid'] ?? ''));
            return $uuid !== '' ? ['subscription', self::subIdByUuid($uuid)] : null;
        }
        if ($action === 'order_status') {
            return $id > 0 ? ['order', $id] : null;
        }
        if ($action === 'deposit_approve') {
            return $id > 0 ? ['payment', $id] : null;
        }
        if ($action === 'withdrawal_decision') {
            return $id > 0 ? ['withdrawal', $id] : null;
        }
        if ($action === 'ticket_reply') {
            return $id > 0 ? ['ticket', $id] : null;
        }
        if ($action === 'coupon_assign' || $action === 'coupon_unassign' || $action === 'coupon_plans') {
            $cid = (int) ($args['coupon_id'] ?? $id);
            return $cid > 0 ? ['coupon', $cid] : null;
        }

        return null;
    }

    // =====================================================================
    //  entity_list / entity_get  (đọc, không xác nhận)
    // =====================================================================

    /** @param array<string,mixed> $args @return array<string,mixed> */
    private static function entityList(array $args): array
    {
        $name = self::entityNameArg($args);
        $def  = EntityCatalog::get($name);
        if ($def === null) {
            return self::fail('entity không tồn tại. Danh sách hợp lệ: ' . implode(', ', EntityCatalog::names()));
        }

        $limit  = self::clampLimit($args['limit'] ?? null);
        $pageNo = max(1, (int) ($args['page'] ?? 1));
        $offset = ($pageNo - 1) * $limit;

        [$where, $binds] = self::listFilters($args, $def, $name);

        $pdo   = self::pdo();
        $table = $def['table'];
        $pk    = self::pkColumn($def, $table, $pdo);
        if ($pk === '') {
            return self::fail("Bảng của thực thể '{$name}' không có cột khóa để đọc.");
        }

        $cols = self::readColumns($def, $name, $table, $pdo);
        if ($cols === []) {
            return self::fail("Thực thể '{$name}' không có cột an toàn nào để đọc.");
        }

        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . $where);
            $st->execute($binds);
            $total = (int) $st->fetchColumn();

            $st = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM ' . $table . $where
                . ' ORDER BY ' . $pk . ' DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
            $st->execute($binds);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return self::fail('Không đọc được bản ghi: ' . $e->getMessage());
        }

        $rows = array_map(static fn (array $r): array => self::maskRow($r, $name), $rows);

        $url = EntityCatalog::adminUrl($name, 0);
        if ($url !== '') {
            $pkOut = $pk;
            $rows = array_map(static function (array $r) use ($name, $pkOut): array {
                $r['admin_url'] = EntityCatalog::adminUrl($name, (int) ($r[$pkOut] ?? 0));
                return $r;
            }, $rows);
        }

        return [
            'ok'     => true,
            'entity' => $name,
            'total'  => $total,
            'page'   => $pageNo,
            'limit'  => $limit,
            'count'  => count($rows),
            'rows'   => $rows,
            'hint'   => $total > ($offset + count($rows)) ? 'Còn trang tiếp theo — tăng "page" để lấy tiếp.' : '',
        ];
    }

    /** @param array<string,mixed> $args @return array<string,mixed> */
    private static function entityGet(array $args): array
    {
        $name = self::entityNameArg($args);
        $def  = EntityCatalog::get($name);
        if ($def === null) {
            return self::fail('entity không tồn tại. Danh sách hợp lệ: ' . implode(', ', EntityCatalog::names()));
        }

        $table = $def['table'];
        $pdo   = self::pdo();
        $pk    = self::pkColumn($def, $table, $pdo);
        if ($pk === '') {
            return self::fail("Bảng của thực thể '{$name}' không có cột khóa để đọc.");
        }

        $cols = self::readColumns($def, $name, $table, $pdo);
        if ($cols === []) {
            return self::fail("Thực thể '{$name}' không có cột an toàn nào để đọc.");
        }

        $id = (int) ($args['id'] ?? 0);
        $uuid = trim((string) ($args['uuid'] ?? ''));

        try {
            if ($id > 0) {
                $st = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM ' . $table . ' WHERE ' . $pk . ' = :id LIMIT 1');
                $st->execute([':id' => $id]);
            } elseif ($uuid !== '' && $name === 'subscription') {
                $st = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM ' . $table . ' WHERE uuid = :uuid LIMIT 1');
                $st->execute([':uuid' => $uuid]);
            } else {
                return self::fail('Cần "id" (số nguyên > 0)' . ($name === 'subscription' ? ' hoặc "uuid".' : '.'));
            }
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return self::fail('Không đọc được bản ghi: ' . $e->getMessage());
        }

        if (!is_array($row)) {
            return self::fail("Không tìm thấy {$name} " . ($id > 0 ? "có id = {$id}." : "có uuid = '{$uuid}'."));
        }

        $row = self::maskRow($row, $name);
        $out = [
            'ok'      => true,
            'entity'  => $name,
            'id'      => (int) ($row[$pk] ?? $id),
            'label'   => self::labelOf($def, $row),
            'record'  => $row,
            'write'   => array_values(array_intersect($def['write'] ?? [], array_keys($row))),
            'blocked' => array_values(array_intersect($def['blocked_on_write'] ?? [], array_keys($row))),
        ];
        $url = EntityCatalog::adminUrl($name, (int) ($row[$pk] ?? $id));
        if ($url !== '') {
            $out['admin_url'] = $url;
        }
        $note = (string) ($def['note'] ?? '');
        if ($note !== '') {
            $out['note'] = $note;
        }
        return $out;
    }

    // =====================================================================
    //  entity_create
    // =====================================================================

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function entityCreate(array $args, array $page): array
    {
        $name = self::entityNameArg($args);
        $def  = EntityCatalog::get($name);
        if ($def === null) {
            return self::fail('entity không tồn tại. Danh sách hợp lệ: ' . implode(', ', EntityCatalog::names()));
        }
        if (!EntityCatalog::isWritable($name)) {
            return self::fail("Thực thể '{$name}' chỉ đọc — hệ thống không cho phép thêm bản ghi trực tiếp (xem note của thực thể).");
        }
        if ((string) ($def['kind'] ?? '') === EntityCatalog::KIND_PIVOT) {
            return self::fail("Thực thể '{$name}' là bảng nối (khóa phức hợp) — phải dùng tool chuyên dụng, không dùng entity_create.");
        }

        $data = is_array($args['data'] ?? null) ? (array) $args['data'] : [];
        if ($data === []) {
            return self::fail('Cần "data" là đối tượng {cột: giá trị}.');
        }

        $data = self::normalizeKeys($data);
        $err  = self::validate($name, $def, $data, true);
        if ($err !== null) {
            return self::fail($err);
        }

        $model = self::modelFor($def);
        if ($model === null) {
            return self::fail("Không chuẩn bị được model cho '{$name}'.");
        }

        // --- các thực thể có luồng riêng: đi qua model/Service thật, không INSERT thô ---
        if ($name === 'user') {
            return self::createUser($model, $data, $page, $def);
        }
        if ($name === 'post') {
            return self::createPost($model, $data, $page, $def);
        }
        if ($name === 'coupon') {
            return self::createCoupon($model, $data, $page, $def);
        }
        if ($name === 'expense') {
            return self::createExpense($model, $data, $page, $def);
        }
        if ($name === 'order') {
            return self::createOrder($model, $data, $page, $def);
        }
        if ($name === 'subscription') {
            return self::createSubscription($model, $data, $page, $def);
        }

        return self::createGeneric($model, $name, $def, $data, $page);
    }

    /**
     * INSERT qua BaseModel (ghép cột từ chính mảng dữ liệu đã được kiểm duyệt).
     * @param array<string,mixed> $def
     * @param array<string,mixed> $data
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function createGeneric(object $model, string $name, array $def, array $data, array $page): array
    {
        $table = $def['table'];
        $pdo   = self::pdo();

        $row = self::prepareRow($name, $def, $data, true, $table, $pdo);
        $row = self::applyDefaults($name, $def, $table, $pdo, $row);

        try {
            $ok = $model->create($row);
        } catch (\Throwable $e) {
            return self::fail('Thêm bản ghi thất bại: ' . $e->getMessage());
        }
        if (!$ok) {
            return self::fail('Thêm bản ghi thất bại (model từ chối).');
        }

        $id = 0;
        try {
            $id = (int) $model->lastInsertId();
        } catch (\Throwable $e) {
            $id = 0;
        }

        $after = $id > 0 ? self::fetchRow($pdo, $table, self::pkColumn($def, $table, $pdo), $id) : null;
        if (is_array($after)) {
            $after = self::maskRow($after, $name);
        }

        self::audit($page, 'AI_CREATE_' . strtoupper($name),
            "AI thêm {$name} #{$id}: " . self::brief($def, $after ?? $row));

        return [
            'ok'     => true,
            'entity' => $name,
            'id'     => $id,
            'record' => is_array($after) ? $after : $row,
            'hint'   => 'Báo Admin id và tên bản ghi vừa tạo.',
        ];
    }

    // =====================================================================
    //  entity_update
    // =====================================================================

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function entityUpdate(array $args, array $page): array
    {
        $name = self::entityNameArg($args);
        $def  = EntityCatalog::get($name);
        if ($def === null) {
            return self::fail('entity không tồn tại. Danh sách hợp lệ: ' . implode(', ', EntityCatalog::names()));
        }
        if (!EntityCatalog::isWritable($name)) {
            return self::fail("Thực thể '{$name}' chỉ đọc — phải dùng action_run hoặc luồng nghiệp vụ tương ứng.");
        }
        if ((string) ($def['kind'] ?? '') === EntityCatalog::KIND_PIVOT) {
            return self::fail("Thực thể '{$name}' là bảng nối — dùng tool chuyên dụng (coupon_plans, coupon_assign...).");
        }

        $table = $def['table'];
        $pdo   = self::pdo();
        $pk    = self::pkColumn($def, $table, $pdo);
        $id    = (int) ($args['id'] ?? 0);
        if ($pk === '' || $id <= 0) {
            return self::fail('Cần "id" là số nguyên dương.');
        }

        $data = is_array($args['data'] ?? null) ? (array) $args['data'] : [];
        if ($data === []) {
            return self::fail('Cần "data" chứa ít nhất một cột thay đổi.');
        }
        $data = self::normalizeKeys($data);

        $err = self::validate($name, $def, $data, false, $id);
        if ($err !== null) {
            return self::fail($err);
        }

        $model = self::modelFor($def);
        if ($model === null) {
            return self::fail("Không chuẩn bị được model cho '{$name}'.");
        }

        $before = self::fetchRow($pdo, $table, $pk, $id);
        if (!is_array($before)) {
            return self::fail("Không tìm thấy {$name} có id = {$id}.");
        }

        if ($name === 'user') {
            $guard = self::guardUserWrite($id, $data);
            if ($guard !== null) {
                return self::fail($guard);
            }
        }
        if ($name === 'plan') {
            $guard = self::guardPlanWrite($id, $data);
            if ($guard !== null) {
                return self::fail($guard);
            }
        }

        // coupon có bảng nối + luồng riêng
        if ($name === 'coupon') {
            return self::updateCoupon($model, $id, $data, $page, $def, $before, $pdo, $table, $pk);
        }

        $row = self::prepareRow($name, $def, $data, false, $table, $pdo);
        if ($row === []) {
            return self::fail('Không có cột hợp lệ nào để sửa.');
        }

        try {
            $ok = $model->update($id, $row);
        } catch (\Throwable $e) {
            return self::fail('Cập nhật thất bại: ' . $e->getMessage());
        }
        if (!$ok) {
            $still = self::fetchRow($pdo, $table, $pk, $id);
            if (!is_array($still)) {
                return self::fail("Không tìm thấy {$name} có id = {$id}.");
            }
            return self::fail('Không có thay đổi nào được ghi (giá trị đã đúng hiện có).');
        }

        $after = self::fetchRow($pdo, $table, $pk, $id);
        $diff  = self::diff($before, is_array($after) ? $after : [], array_keys($row));

        self::audit($page, 'AI_UPDATE_' . strtoupper($name),
            "AI sửa {$name} #{$id}: " . self::brief($def, $after ?? $before) . ' | ' . self::describeDiff($diff));

        return [
            'ok'     => true,
            'entity' => $name,
            'id'     => $id,
            'diff'   => $diff,
            'record' => is_array($after) ? self::maskRow($after, $name) : null,
            'hint'   => 'Báo Admin đúng các cột đã thay đổi (trước -> sau).',
        ];
    }

    /** @param array<string,mixed> $def @return array<string,mixed> */
    private static function updateCoupon(object $model, int $id, array $data, array $page, array $def, array $before, PDO $pdo, string $table, string $pk): array
    {
        $row = self::prepareRow('coupon', $def, $data, false, $table, $pdo);
        $diff = [];

        if ($row !== []) {
            try {
                $ok = $model->update($id, $row);
            } catch (\Throwable $e) {
                return self::fail('Cập nhật voucher thất bại: ' . $e->getMessage());
            }
            if (!$ok) {
                return self::fail("Không sửa được voucher #{$id} (có thể giá trị đã đúng hiện có).");
            }
        }

        if (array_key_exists('plan_ids', $data)) {
            $planIds = self::parseIdList($data['plan_ids']);
            $beforePlan = self::couponPlanIds($id);
            $r = self::couponPlans($planIds, $id, $page);
            if (!($r['ok'] ?? false)) {
                return $r;
            }
            $diff['plan_ids'] = [
                'before' => $beforePlan === [] ? 'TẤT CẢ gói' : implode(',', $beforePlan),
                'after'  => $planIds === [] ? 'TẤT CẢ gói' : implode(',', $planIds),
            ];
        }
        if (array_key_exists('assigned_user_ids', $data)) {
            $userIds = self::parseIdList($data['assigned_user_ids']);
            $r = $userIds === []
                ? self::couponAssign('coupon_unassign', $userIds, $id, $page)
                : self::couponAssign('coupon_assign', $userIds, $id, $page);
            if (!($r['ok'] ?? false)) {
                return $r;
            }
            $diff['assigned_user_ids'] = [
                'before' => '...',
                'after'  => ($userIds === [] ? 'gỡ hết' : 'gán ' . count($userIds)) . ' user',
            ];
        }

        if ($diff === []) {
            return self::fail('Không có thay đổi nào để ghi cho voucher.');
        }

        $after = self::fetchRow($pdo, $table, $pk, $id);
        self::audit($page, 'AI_UPDATE_COUPON', "AI sửa voucher #{$id}: " . self::describeDiff($diff));

        return [
            'ok'     => true,
            'entity' => 'coupon',
            'id'     => $id,
            'diff'   => $diff,
            'record' => is_array($after) ? self::maskRow($after, 'coupon') : null,
        ];
    }

    // =====================================================================
    //  entity_delete
    // =====================================================================

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function entityDelete(array $args, array $page): array
    {
        $name = self::entityNameArg($args);
        $def  = EntityCatalog::get($name);
        if ($def === null) {
            return self::fail('entity không tồn tại. Danh sách hợp lệ: ' . implode(', ', EntityCatalog::names()));
        }

        $table = $def['table'];
        $pdo   = self::pdo();
        $pk    = self::pkColumn($def, $table, $pdo);
        $id    = (int) ($args['id'] ?? 0);
        if ($pk === '' || $id <= 0) {
            return self::fail('Cần "id" là số nguyên dương.');
        }

        $block = self::requireEntityDeletable($name, $id, $def);
        if ($block !== null) {
            return self::fail($block);
        }

        $before = self::fetchRow($pdo, $table, $pk, $id);
        if (!is_array($before)) {
            return self::fail("Không tìm thấy {$name} có id = {$id} — kiểm tra lại id trước khi xóa.");
        }

        $model = self::modelFor($def);
        if ($model === null) {
            return self::fail("Không chuẩn bị được model cho '{$name}'.");
        }

        try {
            $ok = $model->delete($id);
        } catch (\Throwable $e) {
            return self::fail('Xóa thất bại: ' . $e->getMessage());
        }
        if (!$ok) {
            return self::fail("Không xóa được {$name} #{$id}.");
        }

        self::audit($page, 'AI_DELETE_' . strtoupper($name),
            "AI xóa {$name} #{$id}: " . self::brief($def, $before));

        return [
            'ok'      => true,
            'entity'  => $name,
            'id'      => $id,
            'deleted' => true,
            'record'  => self::maskRow($before, $name),
            'hint'    => 'Xóa cứng đã hoàn tất và không tự khôi phục. Nếu Admin đổi ý, phải tạo lại thủ công.',
        ];
    }

    // =====================================================================
    //  KIỂM DUYỆT DỮ LIỆU (2 lớp: catalog + information_schema)
    // =====================================================================

    /**
     * @param array<string,mixed> $def
     * @param array<string,mixed> $data
     */
    private static function validate(string $name, array $def, array $data, bool $isCreate, int $exceptId = 0): ?string
    {
        $writable = array_values(array_filter(
            (array) ($def['write'] ?? []),
            static fn ($c): bool => is_string($c) && $c !== ''
        ));
        if ($writable === []) {
            return "Thực thể '{$name}' không có cột nào được phép ghi.";
        }

        $blocked = array_flip(array_values(array_filter(
            (array) ($def['blocked_on_write'] ?? []),
            static fn ($c): bool => is_string($c) && $c !== ''
        )));
        $allowed = array_flip($writable);
        $pseudo  = self::PSEUDO_KEYS[$name] ?? [];
        $table   = (string) $def['table'];

        foreach ($data as $col => $val) {
            if (isset($pseudo[$col])) {
                continue;
            }
            if (!isset($allowed[$col])) {
                if (isset($blocked[$col])) {
                    return "Cột '{$col}' không được sửa trực tiếp vì phải đồng bộ node/ví/stock — hãy dùng action_run (xem note của thực thể '{$name}').";
                }
                return "Cột '{$col}' không thuộc danh sách được phép ghi của '{$name}'. Cột hợp lệ: "
                    . implode(', ', $writable) . ($pseudo !== [] ? ' (kèm: ' . implode(', ', array_keys($pseudo)) . ')' : '');
            }
            if (preg_match(self::WRITE_DENY_RE, (string) $col) || !self::hasColumn($table, (string) $col)) {
                return "Cột '{$col}' bị chặn (nhạy cảm hoặc không tồn tại trong bảng).";
            }
            if (!is_scalar($val) && $val !== null) {
                if (!(in_array((string) $col, (array) ($def['json'] ?? []), true) && is_array($val))) {
                    return "Cột '{$col}' phải là giá trị vô hướng (chuỗi/số), không phải mảng.";
                }
            }
            $limit = EntityCatalog::limitFor($name, (string) $col);
            if (is_string($val) && strlen($val) > $limit) {
                return "Cột '{$col}' vượt giới hạn {$limit} ký tự.";
            }
            if (is_string($val) && strpos($val, "\0") !== false) {
                return "Cột '{$col}' chứa ký tự NUL không hợp lệ.";
            }
            $enum = EntityCatalog::enumValues($name, (string) $col);
            if ($enum !== [] && $val !== null && !in_array((string) $val, $enum, true)) {
                return "Giá trị cột '{$col}' phải là một trong: " . implode(', ', $enum) . '.';
            }
            if (in_array((string) $col, (array) ($def['numeric'] ?? []), true) && $val !== null && !is_numeric($val)) {
                return "Cột '{$col}' phải là số.";
            }
        }

        // Toàn dữ kiện khi tạo mới: cột NOT NULL không có default phải có giá trị.
        if ($isCreate) {
            $missing = [];
            foreach (self::mandatoryColumns($table) as $col) {
                if (array_key_exists($col, $data)) {
                    continue;
                }
                if (in_array($col, ['created_by', 'approved_by', 'created_at', 'updated_at'], true)) {
                    continue;
                }
                if ($col === 'password_hash' && $name === 'user') {
                    continue;
                }
                if (in_array($col, array_keys($pseudo), true)) {
                    continue;
                }
                // Cột tự sinh (order_code, slug, uuid, ref_code...) hoặc có default trong catalog: không bắt buộc Admin đưa.
                if (array_key_exists($col, (array) ($def['generators'] ?? []))) {
                    continue;
                }
                if (array_key_exists($col, (array) ($def['defaults'] ?? []))) {
                    continue;
                }
                $missing[] = $col;
            }
            if ($missing !== []) {
                return 'Thiếu dữ kiện bắt buộc: ' . implode(', ', $missing)
                    . '. Hãy hỏi lại Admin đúng các trường này, KHÔNG tự bịa giá trị.';
            }
        }

        // Quan hệ: id tham chiếu phải tồn tại.
        $relations = EntityCatalog::relations($name);
        foreach ($relations as $col => $rel) {
            if (!array_key_exists($col, $data) || !is_array($rel)) {
                continue;
            }
            $refEntity = (string) ($rel[0] ?? '');
            $refPk     = (string) ($rel[1] ?? 'id');
            $refDef    = EntityCatalog::get($refEntity);
            if ($refDef === null) {
                continue;
            }
            $value = $data[$col];
            if (in_array((string) $col, (array) ($def['json'] ?? []), true) || $name === 'plan') {
                $ids = self::parseIdList($value);
                if ($ids === []) {
                    return "Cột '{$col}' phải chứa ít nhất một id hợp lệ.";
                }
                $miss = self::missingIds($refDef['table'], $refPk, $ids);
                if ($miss !== []) {
                    return "id không tồn tại trong '{$refEntity}': " . implode(', ', $miss) . '.';
                }
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $rid = (int) $value;
            if ($rid <= 0 || self::missingIds($refDef['table'], $refPk, [$rid]) !== []) {
                return "Không tồn tại {$refEntity} có id = {$rid} (cột {$col}).";
            }
        }

        // Cột unique: không được trùng bản ghi khác.
        foreach (self::uniqueColumns($data, $def) as $col) {
            if (!is_string($col) || $col === '') {
                continue;
            }
            if ($data[$col] === null || $data[$col] === '') {
                continue;
            }
            $exists = self::checkUnique($table, (string) $col, (string) $data[$col], $isCreate ? 0 : $exceptId);
            if ($exists !== null) {
                return $exists;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $def @return array<int,string> */
    private static function uniqueColumns(array $data, array $def): array
    {
        $unique = (array) ($def['unique'] ?? []);
        $out = [];
        foreach ($unique as $col) {
            if (is_string($col) && array_key_exists($col, $data)) {
                $out[] = $col;
            }
        }
        return $out;
    }

    private static function checkUnique(string $table, string $col, string $value, int $exceptId): ?string
    {
        if (!self::hasColumn($table, $col)) {
            return null;
        }
        try {
            $pdo = self::pdo();
            $sql = "SELECT id FROM {$table} WHERE {$col} = :v";
            $args = [':v' => $value];
            if ($exceptId > 0) {
                $sql .= ' AND id <> :id';
                $args[':id'] = $exceptId;
            }
            $sql .= ' LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute($args);
            $hit = (int) $st->fetchColumn();
        } catch (\Throwable $e) {
            return null;
        }
        return $hit > 0 ? "Giá trị '{$value}' của cột {$col} đã tồn tại ở bản ghi #{$hit} — không được trùng." : null;
    }

    /**
     * Lọc `data` xuống đúng cột DB, ép kiểu, sinh giá trị tự động.
     * @param array<string,mixed> $def
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function prepareRow(string $name, array $def, array $data, bool $isCreate, string $table, PDO $pdo): array
    {
        $writable = array_flip(array_values(array_filter(
            (array) ($def['write'] ?? []),
            static fn ($c): bool => is_string($c) && $c !== ''
        )));
        $jsonCols   = array_flip(array_filter(array_map('strval', (array) ($def['json'] ?? []))));
        $numericSet = array_flip(array_filter(array_map('strval', (array) ($def['numeric'] ?? []))));
        $enumSet    = [];
        foreach ((array) ($def['enums'] ?? []) as $col => $vals) {
            $enumSet[(string) $col] = array_map('strval', (array) $vals);
        }

        $row = [];
        foreach ($data as $col => $val) {
            if (!is_string($col) || !isset($writable[$col])) {
                continue;
            }
            if (!self::hasColumn($table, $col)) {
                continue;
            }
            if (array_key_exists($col, $jsonCols)) {
                $row[$col] = self::encodeJson($val);
                continue;
            }
            if ($val === null) {
                $row[$col] = null;
                continue;
            }
            if (array_key_exists($col, $numericSet)) {
                $row[$col] = self::castNumber($val);
                continue;
            }
            if (array_key_exists($col, $enumSet)) {
                $row[$col] = self::clampEnum($val, $enumSet[$col]);
                continue;
            }
            $row[$col] = self::castText($val, EntityCatalog::limitFor($name, $col));
        }

        if ($isCreate) {
            foreach ((array) ($def['generators'] ?? []) as $col => $kind) {
                if (array_key_exists($col, $row) && (string) $row[$col] !== '') {
                    continue;
                }
                if (!is_string($col) || !self::hasColumn($table, $col)) {
                    continue;
                }
                $gen = self::generate((string) $kind, $name, $data, $pdo);
                if ($gen !== null) {
                    $row[$col] = $gen;
                }
            }
        }

        if (self::hasColumn($table, 'updated_at') && !$isCreate) {
            $row['updated_at'] = date('Y-m-d H:i:s');
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private static function applyDefaults(string $name, array $def, string $table, PDO $pdo, array $row): array
    {
        foreach ((array) ($def['defaults'] ?? []) as $col => $val) {
            if (!is_string($col) || array_key_exists($col, $row)) {
                continue;
            }
            if (!EntityCatalog::allowsColumn($name, $col, true) || !self::hasColumn($table, $col)) {
                continue;
            }
            $row[$col] = $val;
        }
        foreach (self::mandatoryColumns($table) as $col) {
            if (array_key_exists($col, $row)) {
                continue;
            }
            if (!EntityCatalog::allowsColumn($name, $col, true) || !self::hasColumn($table, $col)) {
                continue;
            }
            $row[$col] = $col === 'created_at' || $col === 'updated_at' ? date('Y-m-d H:i:s') : null;
        }
        return $row;
    }

    // =====================================================================
    //  action_run — chạy đúng luồng nghiệp vụ chuẩn của controller
    // =====================================================================

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function actionRun(array $args, array $page): array
    {
        $action = strtolower(trim((string) ($args['action'] ?? '')));
        if ($action === '' || !in_array($action, self::ACTIONS, true)) {
            return self::fail('action không hợp lệ. Danh sách: ' . implode(', ', self::ACTIONS) . '.');
        }

        return match ($action) {
            'subscription_status'        => self::subStatus($args, $page),
            'subscription_renew'         => self::subRenew($args, $page),
            'subscription_reset_traffic' => self::subResetTraffic($args, $page),
            'subscription_reset_token'   => self::subResetToken($args, $page),
            'order_status'               => self::orderStatus($args, $page),
            'deposit_approve'            => self::approveDeposit($args, $page),
            'withdrawal_decision'        => self::withdrawalDecision($args, $page),
            'ticket_reply'               => self::ticketReply($args, $page),
            'coupon_assign',
            'coupon_unassign'            => self::couponAssign($action,
                                          self::parseIdList($args['user_ids'] ?? []),
                                          self::couponIdArg($args), $page),
            'coupon_plans'               => self::couponPlans(self::parseIdList($args['plan_ids'] ?? []),
                                          self::couponIdArg($args), $page),
            default                      => self::fail("action '{$action}' chưa được hỗ trợ."),
        };
    }

    private static function couponIdArg(array $args): int
    {
        $id = (int) ($args['coupon_id'] ?? 0);
        return $id > 0 ? $id : (int) ($args['id'] ?? 0);
    }

    /**
     * Nạp subscription theo id (ưu tiên) hoặc uuid.
     * @param array<string,mixed> $args @return array<string,mixed> có khóa 'error' khi thất bại
     */
    private static function loadSub(array $args): array
    {
        $model = new Subscription();
        $id    = (int) ($args['id'] ?? 0);
        if ($id > 0) {
            $sub = $model->find($id);
            return is_array($sub) && $sub !== [] ? $sub : ['error' => "Không tìm thấy subscription có id = {$id}."];
        }
        $uuid = trim((string) ($args['uuid'] ?? ''));
        if ($uuid !== '' && strcasecmp($uuid, 'null') !== 0) {
            $sub = $model->findByUuid($uuid);
            return is_array($sub) && $sub !== [] ? $sub
                : ['error' => "Không tìm thấy subscription có uuid = {$uuid}."];
        }
        return ['error' => 'Cần "id" hoặc "uuid" của subscription.'];
    }

    /** Tìm id subscription theo uuid; 0 khi không có (dùng cho snapshot/preview). */
    private static function subIdByUuid(string $uuid): int
    {
        if ($uuid === '' || strcasecmp($uuid, 'null') === 0) {
            return 0;
        }
        try {
            $sub = (new Subscription())->findByUuid($uuid);
        } catch (\Throwable $e) {
            return 0;
        }
        return is_array($sub) ? (int) ($sub['id'] ?? 0) : 0;
    }

    /** @param array<string,mixed> $sub @return array<int,int> */
    private static function subGroupIds(array $sub): array
    {
        if (empty($sub['plan_id'])) {
            return [];
        }
        $plan = (new VpnPlan())->find((int) $sub['plan_id']);
        return self::parseGroupIds($plan['group_id'] ?? []);
    }

    /** @return array<string,mixed> */
    private static function subOutcome(array $sub, string $action, array $extra = []): array
    {
        return $extra + [
            'ok'     => true,
            'action' => $action,
            'id'     => (int) $sub['id'],
            'hint'   => 'Báo Admin kết quả theo đúng dữ liệu trả về, không tự thêm chi tiết chưa có.',
        ];
    }

    // ---------------------------------------------------------------
    //  subscription_status  (bám sát SubscriptionController::updateStatus)
    // ---------------------------------------------------------------

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function subStatus(array $args, array $page): array
    {
        $status = strtolower(trim((string) ($args['status'] ?? '')));
        $valid  = ['active', 'suspended', 'cancelled', 'expired'];
        if (!in_array($status, $valid, true)) {
            return self::fail('status phải là một trong: ' . implode(', ', $valid) . '.');
        }

        $loaded = self::loadSub($args);
        if (isset($loaded['error'])) {
            return self::fail($loaded['error']);
        }
        $sub   = $loaded;
        $id    = (int) $sub['id'];
        $model = new Subscription();

        // Bật lại gói đã hủy: phải trừ lại 1 suất tồn kho trước.
        $reserved = false;
        if (in_array($status, ['active', 'suspended'], true)
            && ($sub['stock_state'] ?? 'none') === 'released') {
            $reserve = $model->tryReserveSlotOnReactivate($sub);
            if (!($reserve['ok'] ?? false)) {
                $errors = [
                    'out_of_stock'  => 'Tồn kho gói đã hết — không thể bật lại gói đã hủy!',
                    'plan_disabled' => 'Gói cước đang ngừng bán — không thể bật lại gói đã hủy!',
                    'plan_missing'  => 'Không tìm thấy gói cước — không thể bật lại gói đã hủy!',
                ];
                return self::fail($errors[(string) ($reserve['reason'] ?? '')]
                    ?? 'Không thể giữ suất tồn kho — vui lòng thử lại!');
            }
            $reserved = true;
        }

        $updateData = ['status' => $status];
        if ($status === 'active') {
            // Xóa mốc khóa mạng để cơ chế max_devices chạy lại từ đầu
            $updateData['network_locked_until'] = null;
        }

        if (!$model->update($id, $updateData)) {
            if ($reserved) {
                $model->releaseSlot($id);
            }
            return self::fail('Không thể cập nhật trạng thái gói đăng ký #' . $id . '.');
        }

        if ($status === 'cancelled') {
            $model->releaseSlot($id);
        }

        $groupIds = self::subGroupIds($sub);
        if ($groupIds !== []) {
            if ($status === 'active') {
                self::dispatchAdd($id, (string) $sub['uuid'], (int) $sub['transfer_enable'],
                    (string) $sub['end_date'], $groupIds, (int) ($sub['max_devices'] ?? 1), 'active');
            } elseif ($status === 'suspended') {
                self::dispatchDisable($id, $groupIds, 'admin');
            } else {
                // cancelled/expired: giữ user trên VPS, chỉ cắt mạng (contract §2.2)
                self::dispatchDisable($id, $groupIds, $status === 'expired' ? 'expired' : 'cancelled');
            }
        }

        self::audit($page, 'AI_SUBSCRIPTION_STATUS',
            'AI đổi subscription #' . $id . ' từ ' . ($sub['status'] ?? 'N/A') . ' sang ' . $status);

        return self::subOutcome($sub, 'subscription_status', [
            'status'      => $status,
            'status_from' => (string) ($sub['status'] ?? ''),
            'stock'       => $reserved ? 'đã giữ lại 1 suất tồn kho'
                : ($status === 'cancelled' ? 'đã hoàn 1 suất tồn kho' : 'không đổi tồn kho'),
            'node_tasks'  => $groupIds,
        ]);
    }

    // ---------------------------------------------------------------
    //  subscription_renew  (bám sát SubscriptionController::renew — KHÔNG tạo Payment)
    // ---------------------------------------------------------------

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function subRenew(array $args, array $page): array
    {
        $loaded = self::loadSub($args);
        if (isset($loaded['error'])) {
            return self::fail($loaded['error']);
        }
        $sub   = $loaded;
        $id    = (int) $sub['id'];
        $model = new Subscription();

        $plan = (new VpnPlan())->find((int) $sub['plan_id']);
        if (!is_array($plan) || $plan === []) {
            return self::fail('Không tìm thấy thông tin gói cước tương ứng — không gia hạn được.');
        }

        $reserved = false;
        if (($sub['stock_state'] ?? 'none') === 'released') {
            $reserve = $model->tryReserveSlotOnReactivate($sub);
            if (!($reserve['ok'] ?? false)) {
                $errors = [
                    'out_of_stock'  => 'Tồn kho gói đã hết — không thể gia hạn gói đã hủy!',
                    'plan_disabled' => 'Gói cước đang ngừng bán — không thể gia hạn gói đã hủy!',
                    'plan_missing'  => 'Không tìm thấy gói cước — không thể gia hạn gói đã hủy!',
                ];
                return self::fail($errors[(string) ($reserve['reason'] ?? '')]
                    ?? 'Không thể giữ suất tồn kho — vui lòng thử lại!');
            }
            $reserved = true;
        }

        // `days` là tùy chọn: nếu Admin nêu rõ số ngày thì dùng, ngược lại lấy duration_days của gói.
        $daysArg = $args['days'] ?? null;
        $durationDays = ($daysArg === null || $daysArg === '')
            ? (int) ($plan['duration_days'] ?? 30)
            : (int) $daysArg;
        if ($durationDays <= 0) {
            if ($reserved) {
                $model->releaseSlot($id);
            }
            return self::fail('Số ngày gia hạn phải lớn hơn 0.');
        }

        $orderModel = new Order();
        $orderCode  = 'AD' . date('YmdHis') . rand(100, 999);
        $orderCreated = $orderModel->create([
            'order_code'     => $orderCode,
            'user_id'        => (int) $sub['user_id'],
            'plan_id'        => (int) $sub['plan_id'],
            'total_amount'   => (float) ($plan['price'] ?? 0),
            'payment_status' => 'completed',
            'purchase_ip'    => self::clientIp(),
            'created_by'     => self::adminId(),
            'approved_by'    => self::adminId(),
        ]);
        if (!$orderCreated) {
            if ($reserved) {
                $model->releaseSlot($id);
            }
            return self::fail('Không thể khởi tạo đơn hàng mới cho lần gia hạn này!');
        }

        $newOrderId = (int) $orderModel->lastInsertId();
        $baseTime   = max(strtotime((string) $sub['end_date']), time());
        $newEndDate = date('Y-m-d H:i:s', strtotime("+{$durationDays} days", $baseTime));

        $updateData = ['end_date' => $newEndDate, 'status' => 'active'];
        if ($newOrderId > 0) {
            $updateData['order_id'] = $newOrderId;
        }

        if (!$model->update($id, $updateData)) {
            if ($reserved) {
                $model->releaseSlot($id);
            }
            return self::fail('Tạo đơn hàng ' . $orderCode . ' thành công nhưng không thể cập nhật gia hạn cho subscription #' . $id . '!');
        }

        $groupIds = self::parseGroupIds($plan['group_id'] ?? []);
        if ($groupIds !== []) {
            self::dispatchAdd($id, (string) $sub['uuid'], (int) $sub['transfer_enable'],
                $newEndDate, $groupIds, (int) ($sub['max_devices'] ?? 1), 'active');
        }

        self::audit($page, 'AI_RENEW_SUBSCRIPTION',
            'AI gia hạn subscription #' . $id . ' đến ' . $newEndDate . ' bằng đơn hàng ' . $orderCode);

        return self::subOutcome($sub, 'subscription_renew', [
            'end_date'    => $newEndDate,
            'end_date_from' => (string) ($sub['end_date'] ?? ''),
            'days'        => $durationDays,
            'order_code'  => $orderCode,
            'order_id'    => $newOrderId,
            'amount'      => (float) ($plan['price'] ?? 0),
            'node_tasks'  => $groupIds,
        ]);
    }

    // ---------------------------------------------------------------
    //  subscription_reset_traffic / subscription_reset_token
    // ---------------------------------------------------------------

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function subResetTraffic(array $args, array $page): array
    {
        $loaded = self::loadSub($args);
        if (isset($loaded['error'])) {
            return self::fail($loaded['error']);
        }
        $sub = $loaded;
        $id  = (int) $sub['id'];

        if (!(new Subscription())->update($id, ['upload' => 0, 'download' => 0])) {
            return self::fail('Không thể reset lưu lượng gói đăng ký #' . $id . '!');
        }

        self::audit($page, 'AI_RESET_SUBSCRIPTION_TRAFFIC', 'AI đặt lại lưu lượng gói đăng ký #' . $id);

        return self::subOutcome($sub, 'subscription_reset_traffic', [
            'upload_before'   => (int) ($sub['upload'] ?? 0),
            'download_before' => (int) ($sub['download'] ?? 0),
        ]);
    }

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function subResetToken(array $args, array $page): array
    {
        $loaded = self::loadSub($args);
        if (isset($loaded['error'])) {
            return self::fail($loaded['error']);
        }
        $sub = $loaded;
        $id  = (int) $sub['id'];

        $newUuid = self::uuidV4();
        if (!(new Subscription())->update($id, ['uuid' => $newUuid])) {
            return self::fail('Không thể đổi mã Token của gói đăng ký #' . $id . '!');
        }

        $groupIds = self::subGroupIds($sub);
        if ($groupIds !== []) {
            // Không âm thầm bật lại sub đang khóa: gửi status theo trạng thái thật của gói
            $nodeStatus = ((string) ($sub['status'] ?? '')) === 'active' ? 'active' : 'disabled';
            self::dispatchAdd($id, $newUuid, (int) $sub['transfer_enable'],
                (string) $sub['end_date'], $groupIds, (int) ($sub['max_devices'] ?? 1), $nodeStatus);
        }

        self::audit($page, 'AI_RESET_SUBSCRIPTION_TOKEN', 'AI đặt lại UUID gói đăng ký #' . $id);

        return self::subOutcome($sub, 'subscription_reset_token', [
            'uuid_old'  => self::maskSecret((string) ($sub['uuid'] ?? '')),
            'uuid_new'  => $newUuid,
            'node_tasks' => $groupIds,
        ]);
    }

    // ---------------------------------------------------------------
    //  order_status  (bám sát OrderController::updateStatus)
    // ---------------------------------------------------------------

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function orderStatus(array $args, array $page): array
    {
        $id     = (int) ($args['id'] ?? 0);
        $status = strtolower(trim((string) ($args['status'] ?? '')));
        $valid  = ['completed', 'pending', 'failed', 'cancelled'];
        if (!in_array($status, $valid, true)) {
            return self::fail('status phải là một trong: ' . implode(', ', $valid) . '.');
        }
        if ($id <= 0) {
            return self::fail('Cần "id" là số nguyên dương của đơn hàng.');
        }

        $orderModel = new Order();
        $order      = $orderModel->find($id);
        if (!is_array($order) || $order === []) {
            return self::fail("Không tìm thấy đơn hàng có id = {$id}.");
        }

        $current = (string) ($order['payment_status'] ?? '');
        if (in_array($status, ['completed', 'failed', 'cancelled'], true) && $current !== 'pending') {
            return self::fail('Chỉ có thể cập nhật đơn hàng đang chờ thanh toán (đơn #' . $id . ' đang ở trạng thái "' . $current . '").');
        }
        if ($status === 'pending' && $current !== 'pending') {
            return self::fail('Không thể chuyển lại đơn hàng đã xử lý về trạng thái chờ.');
        }

        $orderModel = new Order();
        if (in_array($status, ['failed', 'cancelled'], true)
            && !$orderModel->cancelPendingAndReleaseStock($id, null, $status)) {
            return self::fail('Không thể đóng đơn hàng #' . $id . '.');
        }

        // 1. Đơn gia hạn: có Payment pending gắn subscription → ủy quyền cho PaymentService
        if ($status === 'completed') {
            $renewal = (new Payment())->findPendingRenewalByOrderId($id);
            if (is_array($renewal) && $renewal !== []) {
                $done = (new PaymentService())->completePayment((int) $renewal['id']);
                if (!$done) {
                    return self::fail('Không thể duyệt đơn gia hạn này.');
                }
                self::audit($page, 'AI_APPROVE_RENEWAL', 'AI duyệt đơn gia hạn của đơn hàng #' . $id);
                return [
                    'ok'      => true,
                    'action'  => 'order_status',
                    'id'      => $id,
                    'status'  => 'completed',
                    'renewal' => true,
                    'hint'    => 'Đơn gia hạn đã được duyệt qua luồng PaymentService — thời hạn gói đã cập nhật.',
                ];
            }
        }

        $amount    = (float) ($order['total_amount'] ?? 0);
        $isDeposit = empty($order['plan_id']);
        $activated = false;

        if ($status === 'completed' && $current !== 'completed') {
            // Kích hoạt TRƯỚC khi ghi completed (activateOrder idempotent theo payment_status)
            $activated = (new OrderService())->activateOrder($id);
            if (!$activated) {
                return self::fail('Không thể cộng số dư / kích hoạt gói cho đơn hàng #' . $id . '!');
            }
        }

        $orderUpdate = ['payment_status' => $status, 'total_amount' => $amount];
        if ($status === 'completed' && $current !== 'completed') {
            $orderUpdate['approved_by'] = self::adminId();
        }

        if (!$orderModel->update($id, $orderUpdate)) {
            return self::fail('Không thể cập nhật trạng thái đơn hàng #' . $id . '.');
        }

        $paymentSynced = false;
        if ($status === 'completed' && $current !== 'completed') {
            $paymentModel = new Payment();
            if ($paymentModel->findSuccessfulByOrderId($id) === null) {
                $existing = $paymentModel->findPendingByOrderId($id);
                if (is_array($existing) && $existing !== []) {
                    $paymentModel->update((int) $existing['id'], [
                        'status'         => 'success',
                        'transaction_id' => $existing['transaction_id'] ?? ('MN-' . (string) ($order['order_code'] ?? $id)),
                    ]);
                } else {
                    $paymentModel->create([
                        'user_id'          => (int) $order['user_id'],
                        'order_id'         => $id,
                        'type'             => $isDeposit ? 'deposit' : 'payment',
                        'payment_method'   => (string) ($order['payment_method'] ?? 'vietqr'),
                        'transaction_id'   => 'MN-' . (string) ($order['order_code'] ?? $id),
                        'transfer_content' => $order['transfer_content'] ?? null,
                        'amount'           => $amount,
                        'status'           => 'success',
                        'created_at'       => date('Y-m-d H:i:s'),
                    ]);
                }
                $paymentSynced = true;
            }
        }

        $subCancelled = 0;
        if (in_array($status, ['failed', 'cancelled'], true) && $current === 'pending') {
            (new Payment())->failPendingByOrderId($id);
            $subModel = new Subscription();
            $sub      = $subModel->findByOrderId($id);
            if (is_array($sub) && $sub !== []) {
                $subId = (int) $sub['id'];
                $subModel->update($subId, ['status' => 'cancelled']);
                $subCancelled = 1;
                $groupIds = self::parseGroupIds(
                    (new VpnPlan())->find((int) $order['plan_id'])['group_id'] ?? []
                );
                if ($groupIds !== []) {
                    // Đơn failed/cancelled → cắt mạng, giữ user trên VPS (contract §2.2)
                    self::dispatchDisable($subId, $groupIds, 'cancelled');
                }
            }
        }

        self::audit($page, 'AI_UPDATE_ORDER_STATUS',
            'AI đổi đơn hàng #' . $id . ' (' . ($order['order_code'] ?? 'N/A') . ') từ ' . $current . ' sang ' . $status);

        return [
            'ok'             => true,
            'action'         => 'order_status',
            'id'             => $id,
            'order_code'     => (string) ($order['order_code'] ?? ''),
            'status'         => $status,
            'status_from'    => $current,
            'kind'           => $isDeposit ? 'deposit' : 'purchase',
            'amount'         => $amount,
            'activated'      => $activated,
            'payment_synced' => $paymentSynced,
            'sub_cancelled'  => $subCancelled,
            'hint'           => $status === 'completed' && $isDeposit
                ? 'Đã cộng số dư ví cho khách hàng.'
                : 'Báo Admin đúng trạng thái mới và số tiền của đơn.',
        ];
    }

    // ---------------------------------------------------------------
    //  deposit_approve / withdrawal_decision / ticket_reply
    // ---------------------------------------------------------------

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function approveDeposit(array $args, array $page): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return self::fail('Cần "id" là số nguyên dương của giao dịch nạp tiền.');
        }
        $model = new Payment();
        $row   = $model->find($id);
        if (!is_array($row) || $row === []) {
            return self::fail("Không tìm thấy payment có id = {$id}.");
        }
        if ((string) ($row['type'] ?? '') !== 'deposit') {
            return self::fail('Payment #' . $id . ' không phải giao dịch nạp tiền — chỉ deposit mới duyệt được bằng action này.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            return self::fail('Giao dịch nạp này không ở trạng thái chờ (hiện là "' . (string) ($row['status'] ?? '') . '").');
        }

        if (!(new PaymentService())->completePayment($id)) {
            return self::fail('Không thể duyệt giao dịch nạp tiền #' . $id . '.');
        }

        self::audit($page, 'AI_APPROVE_DEPOSIT',
            'AI duyệt nạp tiền #' . $id . ' số tiền ' . (string) ($row['amount'] ?? '0')
            . ' cho user #' . (int) ($row['user_id'] ?? 0));

        return [
            'ok'      => true,
            'action'  => 'deposit_approve',
            'id'      => $id,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'amount'  => (float) ($row['amount'] ?? 0),
            'hint'    => 'Số dư đã được cộng qua luồng PaymentService chuẩn.',
        ];
    }

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function withdrawalDecision(array $args, array $page): array
    {
        $id       = (int) ($args['id'] ?? 0);
        $decision = strtolower(trim((string) ($args['decision'] ?? '')));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return self::fail('decision phải là "approved" hoặc "rejected".');
        }
        if ($id <= 0) {
            return self::fail('Cần "id" là số nguyên dương của yêu cầu rút tiền.');
        }

        $model = new Withdrawal();
        $row   = $model->find($id);
        if (!is_array($row) || $row === []) {
            return self::fail("Không tìm thấy withdrawal có id = {$id}.");
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            return self::fail('Yêu cầu rút tiền này không còn ở trạng thái chờ (hiện là "' . (string) ($row['status'] ?? '') . '").');
        }

        if (!$model->updateStatus($id, $decision)) {
            return self::fail('Không thể ' . ($decision === 'approved' ? 'duyệt' : 'từ chối')
                . ' yêu cầu rút tiền #' . $id . ' (có thể số dư hoa hồng của user không đủ).');
        }

        self::audit($page, 'AI_WITHDRAWAL_' . strtoupper($decision),
            'AI ' . ($decision === 'approved' ? 'duyệt' : 'từ chối') . ' yêu cầu rút tiền #' . $id
            . ' số tiền ' . (string) ($row['amount'] ?? '0') . ' của user #' . (int) ($row['user_id'] ?? 0));

        return [
            'ok'        => true,
            'action'    => 'withdrawal_decision',
            'id'        => $id,
            'decision'  => $decision,
            'user_id'   => (int) ($row['user_id'] ?? 0),
            'amount'    => (float) ($row['amount'] ?? 0),
            'hint'      => $decision === 'approved'
                ? 'Đã trừ hoa hồng của user theo đúng luồng Withdrawal::updateStatus().'
                : 'Yêu cầu đã bị từ chối, hoa hồng chưa bị trừ.',
        ];
    }

    /** @param array<string,mixed> $args @param array<string,mixed> $page @return array<string,mixed> */
    private static function ticketReply(array $args, array $page): array
    {
        $id      = (int) ($args['id'] ?? 0);
        $message = trim((string) ($args['message'] ?? ''));
        if ($id <= 0) {
            return self::fail('Cần "id" là số nguyên dương của ticket.');
        }
        if ($message === '') {
            return self::fail('Cần "message" là nội dung trả lời (không được rỗng).');
        }
        $message = mb_substr($message, 0, 60000);

        $model  = new SupportTicket();
        $ticket = $model->find($id);
        if (!is_array($ticket) || $ticket === []) {
            return self::fail("Không tìm thấy ticket có id = {$id}.");
        }

        $adminId = self::adminId();
        (new TicketMessage())->create([
            'ticket_id'  => $id,
            'sender_id'  => $adminId,
            'message'    => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $status = strtolower(trim((string) ($args['status'] ?? '')));
        $valid  = ['open', 'in_progress', 'resolved', 'closed'];
        $updateData = [];
        if ($status !== '' && in_array($status, $valid, true)) {
            $updateData['status'] = $status;
        }
        if (empty($ticket['assigned_staff_id']) && $adminId > 0) {
            $updateData['assigned_staff_id'] = $adminId;
        }
        if ($updateData !== []) {
            $model->updateTicket($id, $updateData);
        }

        self::audit($page, 'AI_TICKET_REPLY',
            'AI trả lời ticket #' . $id . ($status !== '' ? ', chuyển trạng thái sang ' . $status : ''));

        return [
            'ok'          => true,
            'action'      => 'ticket_reply',
            'id'          => $id,
            'status'      => $status !== '' ? $status : (string) ($ticket['status'] ?? ''),
            'status_from' => (string) ($ticket['status'] ?? ''),
            'assigned'    => !empty($updateData['assigned_staff_id']),
            'hint'        => 'Nội dung trả lời đã gửi cho khách — sao lại đúng nội dung cho Admin xem.',
        ];
    }

    // ---------------------------------------------------------------
    //  coupon_assign / coupon_unassign / coupon_plans
    // ---------------------------------------------------------------

    /**
     * @param array<int,int> $userIds
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function couponAssign(string $action, array $userIds, int $couponId, array $page): array
    {
        if ($couponId <= 0) {
            return self::fail('Cần "coupon_id" là số nguyên dương.');
        }
        $model = new Coupon();
        $coupon = $model->find($couponId);
        if (!is_array($coupon) || $coupon === []) {
            return self::fail("Không tìm thấy voucher có id = {$couponId}.");
        }

        if ($action === 'coupon_unassign') {
            if ($userIds === []) {
                return self::fail('coupon_unassign cần "user_ids" (danh sách id user cần gỡ).');
            }
            $miss = self::missingIds('vc_users', 'id', $userIds);
            if ($miss !== []) {
                return self::fail('id user không tồn tại: ' . implode(', ', $miss) . '.');
            }
            $before = $model->getAssignedUserIds($couponId);
            $okAll  = true;
            foreach ($userIds as $uid) {
                if (!$model->removeAssignments($couponId, $uid)) {
                    $okAll = false;
                }
            }
            self::audit($page, 'AI_COUPON_UNASSIGN',
                'AI gỡ user ' . implode(',', $userIds) . ' khỏi voucher #' . $couponId . ' (' . ($coupon['code'] ?? '') . ')');
            return [
                'ok'        => $okAll,
                'action'    => 'coupon_unassign',
                'coupon_id' => $couponId,
                'code'      => (string) ($coupon['code'] ?? ''),
                'removed'   => $userIds,
                'assigned'  => array_values(array_diff($before, $userIds)),
            ] + ($okAll ? [] : ['error' => 'Một số user không gỡ được.']);
        }

        if ($userIds === []) {
            return self::fail('coupon_assign cần "user_ids" là danh sách id user (không rỗng).');
        }
        $miss = self::missingIds('vc_users', 'id', $userIds);
        if ($miss !== []) {
            return self::fail('id user không tồn tại: ' . implode(', ', $miss) . '.');
        }
        if (!$model->assignUsers($couponId, $userIds)) {
            return self::fail('Không gán được voucher #' . $couponId . ' cho danh sách user đã cho.');
        }
        self::audit($page, 'AI_COUPON_ASSIGN',
            'AI gán voucher #' . $couponId . ' (' . ($coupon['code'] ?? '') . ') cho ' . count($userIds) . ' user: '
            . implode(',', $userIds));

        return [
            'ok'        => true,
            'action'    => 'coupon_assign',
            'coupon_id' => $couponId,
            'code'      => (string) ($coupon['code'] ?? ''),
            'assigned'  => $model->getAssignedUserIds($couponId),
            'hint'      => 'Báo Admin danh sách user đã được gán voucher.',
        ];
    }

    /**
     * @param array<int,int> $planIds  rỗng = áp dụng cho TẤT CẢ gói (theo Coupon::setPlans)
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function couponPlans(array $planIds, int $couponId, array $page): array
    {
        if ($couponId <= 0) {
            return self::fail('Cần "coupon_id" là số nguyên dương.');
        }
        $model  = new Coupon();
        $coupon = $model->find($couponId);
        if (!is_array($coupon) || $coupon === []) {
            return self::fail("Không tìm thấy voucher có id = {$couponId}.");
        }

        $miss = self::missingIds('vc_vpn_plans', 'id', $planIds);
        if ($miss !== []) {
            return self::fail('id gói cước không tồn tại: ' . implode(', ', $miss) . '.');
        }

        $before = self::couponPlanIds($couponId);
        if (!$model->setPlans($couponId, $planIds)) {
            return self::fail('Không cập nhật được danh sách gói áp dụng cho voucher #' . $couponId . '.');
        }

        self::audit($page, 'AI_COUPON_PLANS',
            'AI đặt gói áp dụng voucher #' . $couponId . ' (' . ($coupon['code'] ?? '') . ') = '
            . ($planIds === [] ? 'TẤT CẢ gói' : implode(',', $planIds)));

        return [
            'ok'         => true,
            'action'     => 'coupon_plans',
            'coupon_id'  => $couponId,
            'code'       => (string) ($coupon['code'] ?? ''),
            'before'     => $before === [] ? 'TẤT CẢ gói' : implode(',', $before),
            'after'      => $planIds === [] ? 'TẤT CẢ gói' : implode(',', $planIds),
            'plan_ids'   => self::couponPlanIds($couponId),
            'hint'       => 'Danh sách gói rỗng nghĩa là voucher áp dụng cho TOÀN BỘ gói.',
        ];
    }

    /** @return array<int,int> */
    private static function couponPlanIds(int $couponId): array
    {
        try {
            $st = self::pdo()->prepare('SELECT plan_id FROM vc_coupon_plans WHERE coupon_id = :cid ORDER BY plan_id');
            $st->execute([':cid' => $couponId]);
            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    //  CREATOR CHUYÊN BIỆT — đi qua model/Service thật, không INSERT thô
    // =====================================================================

    /**
     * Thêm user — bám sát UserController::create (bắt buộc mật khẩu, chặn trùng, bcrypt).
     * @param array<string,mixed> $data @param array<string,mixed> $page @param array<string,mixed> $def
     * @return array<string,mixed>
     */
    private static function createUser(object $model, array $data, array $page, array $def): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        if ($username === '' || $email === '') {
            return self::fail('Cần "username" và "email" để tạo tài khoản.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return self::fail("Email '{$email}' không hợp lệ — hãy hỏi lại Admin, không tự chế email.");
        }

        // UserController::create yêu cầu mật khẩu khác rỗng; ở đây bắt buộc tối thiểu 6 ký tự.
        $password = (string) ($data['password'] ?? '');
        if (mb_strlen($password) < 6) {
            return self::fail('Cần "password" (tối thiểu 6 ký tự) trong data để tạo tài khoản. '
                . 'Không được tự bịa mật khẩu — hãy hỏi Admin.');
        }

        $u = (new User())->findByUsernameOrEmailStrict($username);
        if (is_array($u) && $u !== []) {
            return self::fail("Username '{$username}' đã tồn tại (user #{$u['id']}).");
        }
        $e = (new User())->findByUsernameOrEmailStrict($email);
        if (is_array($e) && $e !== []) {
            return self::fail("Email '{$email}' đã được dùng bởi user #{$e['id']}.");
        }

        if (!empty($data['referred_by'])) {
            $refId = (int) $data['referred_by'];
            if ($refId <= 0 || (new User())->find($refId) === null) {
                return self::fail("Không tồn tại user giới thiệu có id = {$refId}.");
            }
        }

        $row = self::prepareRow('user', $def, $data, true, 'vc_users', self::pdo());
        $row['password_hash']      = password_hash($password, PASSWORD_BCRYPT);
        $row['role']               = self::clampEnum($row['role'] ?? ($data['role'] ?? 'user'), ['admin', 'user']);
        $row['status']             = self::clampEnum($row['status'] ?? ($data['status'] ?? 'active'), ['active', 'inactive', 'banned']);
        $row['balance']            = self::castNumber($row['balance'] ?? ($data['balance'] ?? 0));
        $row['commission_balance'] = '0.00';
        $row['created_by']         = self::adminId();
        $row['register_ip']        = self::clientIp();
        $row['created_at']         = date('Y-m-d H:i:s');
        if (empty($row['ref_code'])) {
            $row['ref_code'] = self::generate('ref_code', 'user', $data, self::pdo());
        }

        try {
            $ok = $model->create($row);
        } catch (\Throwable $ex) {
            return self::fail('Tạo tài khoản thất bại: ' . $ex->getMessage());
        }
        if (!$ok) {
            return self::fail('Tạo tài khoản thất bại (model từ chối).');
        }

        $id    = (int) $model->lastInsertId();
        $after = self::fetchRow(self::pdo(), 'vc_users', 'id', $id);
        self::audit($page, 'AI_CREATE_USER',
            'AI tạo user #' . $id . ': ' . $username . ' (' . $email . ')');

        return [
            'ok'      => true,
            'entity'  => 'user',
            'id'      => $id,
            'record'  => is_array($after) ? self::maskRow($after, 'user') : null,
            'ref_code' => (string) ($row['ref_code'] ?? ''),
            'hint'    => 'Báo Admin id, username và mã giới thiệu vừa tạo. KHÔNG gửi mật khẩu cho khách qua chat.',
        ];
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $page @param array<string,mixed> $def @return array<string,mixed> */
    private static function createPost(object $model, array $data, array $page, array $def): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return self::fail('Cần "title" để tạo bài viết.');
        }
        $row = self::prepareRow('post', $def, $data, true, 'vc_posts', self::pdo());
        $row['author_id']  = self::adminId();
        $row['created_at'] = date('Y-m-d H:i:s');
        if (empty($row['slug'])) {
            $row['slug'] = self::uniqueSlugV(self::slugifyViet($title));
        } else {
            $row['slug'] = self::uniqueSlugV(self::slugifyViet((string) $row['slug']));
        }

        try {
            $ok = $model->create($row);
        } catch (\Throwable $ex) {
            return self::fail('Tạo bài viết thất bại: ' . $ex->getMessage());
        }
        if (!$ok) {
            return self::fail('Tạo bài viết thất bại (model từ chối).');
        }

        $id    = (int) $model->lastInsertId();
        $after = self::fetchRow(self::pdo(), 'vc_posts', 'id', $id);
        self::audit($page, 'AI_CREATE_POST',
            'AI tạo bài viết #' . $id . ': ' . $title . ' (slug ' . $row['slug'] . ')');

        return [
            'ok'     => true,
            'entity' => 'post',
            'id'     => $id,
            'slug'   => (string) $row['slug'],
            'record' => is_array($after) ? self::maskRow($after, 'post') : null,
            'hint'   => 'Báo Admin id + slug bài viết vừa tạo.',
        ];
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $page @param array<string,mixed> $def @return array<string,mixed> */
    private static function createCoupon(object $model, array $data, array $page, array $def): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            return self::fail('Cần "code" để tạo voucher.');
        }
        $dup = (new Coupon())->findByCode($code);
        if (is_array($dup) && $dup !== []) {
            return self::fail("Mã voucher '{$code}' đã tồn tại (voucher #{$dup['id']}).");
        }

        $row = self::prepareRow('coupon', $def, $data, true, 'vc_coupons', self::pdo());
        $row['code']       = $code;
        $row['created_at'] = date('Y-m-d H:i:s');
        $row['used_count'] = 0;

        try {
            $ok = $model->create($row);
        } catch (\Throwable $ex) {
            return self::fail('Tạo voucher thất bại: ' . $ex->getMessage());
        }
        if (!$ok) {
            return self::fail('Tạo voucher thất bại (model từ chối).');
        }

        $id = (int) $model->lastInsertId();

        // Bảng nối sau khi có id (pseudo-keys)
        $planIds = self::parseIdList($data['plan_ids'] ?? []);
        if (array_key_exists('plan_ids', $data)) {
            $r = self::couponPlans($planIds, $id, $page);
            if (!($r['ok'] ?? false)) {
                return $r;
            }
        }
        $userIds = self::parseIdList($data['assigned_user_ids'] ?? []);
        if ($userIds !== []) {
            $r = self::couponAssign('coupon_assign', $userIds, $id, $page);
            if (!($r['ok'] ?? false)) {
                return $r;
            }
        }

        $after = self::fetchRow(self::pdo(), 'vc_coupons', 'id', $id);
        self::audit($page, 'AI_CREATE_COUPON',
            'AI tạo voucher #' . $id . ': ' . $code . ' ('
            . ($row['discount_type'] ?? '') . ' ' . ($row['discount_value'] ?? '') . ')');

        return [
            'ok'     => true,
            'entity' => 'coupon',
            'id'     => $id,
            'code'   => $code,
            'record' => is_array($after) ? self::maskRow($after, 'coupon') : null,
            'hint'   => 'Báo Admin mã voucher, mức giảm và hạn dùng vừa tạo.',
        ];
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $page @param array<string,mixed> $def @return array<string,mixed> */
    private static function createExpense(object $model, array $data, array $page, array $def): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return self::fail('Cần "title" để ghi khoản chi.');
        }
        $date = trim((string) ($data['expense_date'] ?? ''));
        if ($date === '') {
            $date = date('Y-m-d');
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return self::fail("expense_date '{$date}' không đọc được — dùng định dạng YYYY-MM-DD.");
        }

        $row = self::prepareRow('expense', $def, $data, true, 'vc_expenses', self::pdo());
        $row['expense_date'] = date('Y-m-d', $ts);
        $row['amount']       = self::castNumber($row['amount'] ?? ($data['amount'] ?? 0));
        $row['created_at']   = date('Y-m-d H:i:s');

        try {
            $ok = $model->create($row);
        } catch (\Throwable $ex) {
            return self::fail('Ghi khoản chi thất bại: ' . $ex->getMessage());
        }
        if (!$ok) {
            return self::fail('Ghi khoản chi thất bại (model từ chối).');
        }

        $id    = (int) $model->lastInsertId();
        $after = self::fetchRow(self::pdo(), 'vc_expenses', 'id', $id);
        self::audit($page, 'AI_CREATE_EXPENSE',
            'AI ghi khoản chi #' . $id . ': ' . $title . ' = ' . $row['amount']);

        return [
            'ok'     => true,
            'entity' => 'expense',
            'id'     => $id,
            'record' => is_array($after) ? self::maskRow($after, 'expense') : null,
            'hint'   => 'Báo Admin khoản chi vừa ghi kèm số tiền và ngày.',
        ];
    }

    /**
     * Tạo đơn hàng thủ công — bám sát OrderController::create nhưng chỉ ở chế độ CHỜ thanh toán:
     * giữ slot tồn kho trong transaction, tự sinh order_code + transfer_content.
     * @param array<string,mixed> $data @param array<string,mixed> $page @param array<string,mixed> $def @return array<string,mixed>
     */
    private static function createOrder(object $model, array $data, array $page, array $def): array
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $planId = (int) ($data['plan_id'] ?? 0);
        if ($userId <= 0 || $planId <= 0) {
            return self::fail('Cần "user_id" và "plan_id" hợp lệ để tạo đơn hàng.');
        }
        if ((new User())->find($userId) === null) {
            return self::fail("Không tồn tại user có id = {$userId}.");
        }
        $plan = (new VpnPlan())->find($planId);
        if (!is_array($plan) || $plan === []) {
            return self::fail("Không tồn tại gói cước có id = {$planId}.");
        }

        $row = self::prepareRow('order', $def, $data, true, 'vc_orders', self::pdo());
        $row['user_id']        = $userId;
        $row['plan_id']        = $planId;
        $row['total_amount']   = self::castNumber($row['total_amount'] ?? ($data['total_amount'] ?? $plan['price'] ?? 0));
        $row['payment_status'] = 'pending';
        $row['payment_method'] = self::clampEnum($row['payment_method'] ?? ($data['payment_method'] ?? 'vietqr'), ['vietqr', 'balance']);
        $row['created_by']     = self::adminId();
        $row['purchase_ip']    = self::clientIp();
        $row['created_at']     = date('Y-m-d H:i:s');
        $row['updated_at']     = date('Y-m-d H:i:s');

        $pdo = self::pdo();
        $reserved = false;
        try {
            $pdo->beginTransaction();
            $stock = (new VpnPlan())->reserveForPurchase($planId);
            if (!($stock['available'] ?? false)) {
                $pdo->rollBack();
                return self::fail("Gói '{$plan['name']}' không còn khả dụng để tạo đơn (hết tồn kho hoặc đã ngừng bán).");
            }
            $reserved = (bool) ($stock['reserved'] ?? false);
            $row['stock_reserved'] = $reserved ? 1 : 0;

            $ok = $model->create($row);
            if (!$ok) {
                $pdo->rollBack();
                return self::fail('Tạo đơn hàng thất bại (model từ chối).');
            }
            $orderId = (int) $model->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return self::fail('Tạo đơn hàng thất bại: ' . $ex->getMessage());
        }
        if ($orderId <= 0) {
            return self::fail('Tạo đơn hàng thất bại (không lấy được id).');
        }

        // transfer_content mô phỏng cú pháp checkout thật
        $syntax = (new \App\Models\Setting())->get('order_transfer_syntax', 'THANHTOAN');
        $content = trim((string) $syntax) . str_pad((string) $orderId, 2, '0', STR_PAD_LEFT);
        $model->update($orderId, ['transfer_content' => $content]);

        $after = self::fetchRow($pdo, 'vc_orders', 'id', $orderId);
        self::audit($page, 'AI_CREATE_ORDER',
            'AI tạo đơn hàng #' . $orderId . ' (' . $row['order_code'] . ') cho user #' . $userId
            . ' gói #' . $planId . ' số tiền ' . $row['total_amount']);

        return [
            'ok'              => true,
            'entity'          => 'order',
            'id'              => $orderId,
            'order_code'      => (string) $row['order_code'],
            'transfer_content' => $content,
            'amount'          => (float) $row['total_amount'],
            'plan'            => (string) $plan['name'],
            'stock_reserved'  => $reserved,
            'payment_status'  => 'pending',
            'record'          => is_array($after) ? self::maskRow($after, 'order') : null,
            'hint'            => 'Đơn mới ở trạng thái CHỜ. Hướng dẫn khách chuyển khoản đúng nội dung '
                . $content . '. Khi Admin xác nhận đã thu tiền, duyệt bằng action_run order_status = completed.',
        ];
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $page @param array<string,mixed> $def @return array<string,mixed> */
    private static function createSubscription(object $model, array $data, array $page, array $def): array
    {
        return self::fail('Không tạo subscription trực tiếp. Subscription phải sinh ra từ đơn hàng đã thanh toán: '
            . 'tạo đơn bằng entity_create order rồi duyệt bằng action_run order_status = completed '
            . '(luồng này mới đúng nghiệp vụ: giữ stock, tạo uuid, cấp quyền node).');
    }

    // =====================================================================
    //  TIỆN ÍCH CHUNG
    // =====================================================================

    /** @return array<string,mixed> */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }

    /** @param array<string,mixed> $args */
    private static function entityNameArg(array $args): string
    {
        return strtolower(trim((string) ($args['entity'] ?? '')));
    }

    private static function clampLimit(mixed $raw): int
    {
        $limit = (int) ($raw ?? 20);
        if ($limit <= 0) {
            $limit = 20;
        }
        return min($limit, self::MAX_ROWS);
    }

    /** @param array<string,mixed> $def @return array{0:string,1:array<string,mixed>} */
    private static function listFilters(array $args, array $def, string $name): array
    {
        $where = [];
        $binds = [];

        $q = trim((string) ($args['q'] ?? ''));
        if ($q !== '') {
            $likeCols = [];
            foreach ((array) ($def['label'] ?? []) as $col) {
                if (is_string($col) && in_array($col, (array) ($def['read'] ?? []), true)
                    && self::hasColumn((string) $def['table'], $col)) {
                    $likeCols[] = $col;
                }
            }
            if ($likeCols === []) {
                $likeCols = ['id'];
            }
            $parts = [];
            foreach ($likeCols as $i => $col) {
                $parts[] = $col . ' LIKE :q' . $i;
                $binds[':q' . $i] = '%' . $q . '%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $filters = is_array($args['filters'] ?? null) ? (array) $args['filters'] : [];
        $readable = array_flip(array_filter(array_map('strval', (array) ($def['read'] ?? []))));
        $n = 0;
        foreach ($filters as $col => $val) {
            $col = (string) $col;
            if (!isset($readable[$col]) || !self::hasColumn((string) $def['table'], $col)) {
                continue;
            }
            if (preg_match(self::READ_MASK_RE, $col)) {
                continue;
            }
            if ($val === null || $val === '') {
                continue;
            }
            if (is_array($val)) {
                $ids = self::parseIdList($val);
                if ($ids !== []) {
                    $ph = [];
                    foreach ($ids as $i => $iv) {
                        $ph[] = ':f' . $n . '_' . $i;
                        $binds[':f' . $n . '_' . $i] = $iv;
                    }
                    $where[] = $col . ' IN (' . implode(', ', $ph) . ')';
                    $n++;
                }
                continue;
            }
            $enum = EntityCatalog::enumValues($name, $col);
            if ($enum !== [] && !in_array((string) $val, $enum, true)) {
                continue;
            }
            if (in_array($col, (array) ($def['numeric'] ?? []), true) && !is_numeric($val)) {
                continue;
            }
            if (strpos((string) $val, "\0") !== false) {
                continue;
            }
            $binds[':f' . $n] = $val;
            $where[] = $col . ' = :f' . $n;
            $n++;
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $binds];
    }

    /** @param array<string,mixed> $def @return array<int,string> */
    private static function readColumns(array $def, string $name, string $table, PDO $pdo): array
    {
        $cols = [];
        foreach ((array) ($def['read'] ?? []) as $col) {
            if (!is_string($col) || $col === '' || preg_match(self::READ_MASK_RE, $col)) {
                continue;
            }
            if (!self::hasColumn($table, $col)) {
                continue;
            }
            $cols[] = $col;
        }
        $pk = (string) ($def['pk'] ?? 'id');
        if ($pk !== '' && !in_array($pk, $cols, true) && self::hasColumn($table, $pk)) {
            array_unshift($cols, $pk);
        }
        return array_values(array_unique($cols));
    }

    /** @param array<string,mixed> $def */
    private static function pkColumn(array $def, string $table, PDO $pdo): string
    {
        $pk = (string) ($def['pk'] ?? 'id');
        if ($pk !== '' && self::hasColumn($table, $pk)) {
            return $pk;
        }
        return self::hasColumn($table, 'id') ? 'id' : '';
    }

    /** @return array<string,mixed>|null */
    private static function fetchRow(PDO $pdo, string $table, string $pk, int $id): ?array
    {
        if ($pk === '') {
            return null;
        }
        try {
            $st = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE ' . $pk . ' = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Che giá trị của cột nhạy cảm khi trả kết quả về cho AI.
     * @param array<string,mixed> $row @return array<string,mixed>
     */
    private static function maskRow(array $row, string $name): array
    {
        foreach ($row as $col => $val) {
            if (is_string($col) && preg_match(self::READ_MASK_RE, $col)) {
                $row[$col] = self::maskSecret((string) $val);
            }
        }
        return $row;
    }

    /** @param array<string,mixed> $def */
    private static function labelOf(array $def, array $row): string
    {
        foreach ((array) ($def['label'] ?? []) as $col) {
            if (is_string($col) && isset($row[$col]) && is_scalar($row[$col]) && (string) $row[$col] !== '') {
                return (string) $row[$col];
            }
        }
        foreach ((array) ($def['read'] ?? []) as $col) {
            if (is_string($col) && preg_match(self::READ_MASK_RE, $col) === 0
                && isset($row[$col]) && is_scalar($row[$col]) && (string) $row[$col] !== '') {
                return (string) $row[$col];
            }
        }
        return '#' . (string) ($row[$def['pk'] ?? 'id'] ?? '?');
    }

    /** @param array<string,mixed> $def @return array<string,mixed> */
    private static function brief(array $def, array $row): string
    {
        $out = self::labelOf($def, $row);
        $id  = $row[$def['pk'] ?? 'id'] ?? null;
        return ($id !== null ? '#' . $id . ' ' : '') . $out;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private static function normalizeKeys(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $clean = strtolower(trim($k));
            if ($clean === '') {
                continue;
            }
            $out[$clean] = $v;
        }
        return $out;
    }

    /** @param array<string,mixed> $def */
    private static function modelFor(array $def): ?object
    {
        $class = (string) ($def['model'] ?? '');
        if ($class === '' || !class_exists($class)) {
            return null;
        }
        try {
            $m = new $class();
            return ($m instanceof BaseModel) ? $m : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<string,array<string,mixed>> */
    private static function diff(array $before, array $after, array $cols): array
    {
        $out = [];
        foreach ($cols as $col) {
            if (!is_string($col)) {
                continue;
            }
            $b = $before[$col] ?? null;
            $a = $after[$col] ?? null;
            if (is_array($b) || is_array($a)) {
                $b = json_encode($b, JSON_UNESCAPED_UNICODE);
                $a = json_encode($a, JSON_UNESCAPED_UNICODE);
            }
            if (is_bool($b)) { $b = $b ? 1 : 0; }
            if (is_bool($a)) { $a = $a ? 1 : 0; }
            $bs = $b === null ? "\0null" : trim((string) $b);
            $as = $a === null ? "\0null" : trim((string) $a);
            if ($bs === $as) {
                continue;
            }
            // Số như nhau nhưng khác cách viết ("1000" vs "1000.00") -> coi như không đổi
            if ($b !== null && $a !== null && is_numeric($b) && is_numeric($a) && (float) $b === (float) $a) {
                continue;
            }
            $out[$col] = ['before' => $b, 'after' => $a];
        }
        return $out;
    }

    /** @param array<string,array<string,mixed>> $diff */
    private static function describeDiff(array $diff): string
    {
        if ($diff === []) {
            return 'không thay đổi cột nào';
        }
        $parts = [];
        foreach ($diff as $col => $d) {
            $b = $d['before'] ?? null;
            $a = $d['after'] ?? null;
            if (is_string($col) && preg_match(self::READ_MASK_RE, $col)) {
                $parts[] = $col . ': [đã thay đổi]';
                continue;
            }
            $parts[] = $col . ': ' . ($b === null ? 'null' : (string) $b)
                . ' -> ' . ($a === null ? 'null' : (string) $a);
        }
        return implode('; ', $parts);
    }

    /** @param array<string,mixed> $page */
    private static function audit(array $page, string $action, string $description): void
    {
        try {
            (new SystemLog())->create([
                'user_id'     => self::adminId(),
                'action'      => mb_substr($action, 0, 100),
                'description' => $description,
                'ip_address'  => self::clientIp(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[EntityCrudTools] audit: ' . $e->getMessage());
        }
    }

    private static function pdo(): PDO
    {
        // BaseModel::$db là protected static → phải lấy PDO qua getPdo() của một model cụ thể.
        return Setting::getPdo();
    }

    private static function adminId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    }

    private static function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 6) {
            return str_repeat('*', strlen($value));
        }
        return substr($value, 0, 3) . str_repeat('*', min(8, strlen($value) - 6)) . substr($value, -3);
    }

    // =====================================================================
    //  VỆ SĨ GHI / XÓA
    // =====================================================================

    /** @param array<string,mixed> $data */
    private static function guardUserWrite(int $id, array $data): ?string
    {
        $adminId = self::adminId();
        if ($id !== $adminId) {
            return null;
        }
        if (isset($data['role']) && self::clampEnum($data['role'], ['admin', 'user']) !== 'admin') {
            return 'Không thể tự hạ quyền tài khoản của chính mình.';
        }
        if (isset($data['status']) && self::clampEnum($data['status'], ['active', 'inactive', 'banned']) !== 'active') {
            return 'Không thể tự khóa tài khoản của chính mình.';
        }
        return null;
    }

    /**
     * Sửa gói cước: không cho đổi code/group_id khi gói đã có subscription (đồng bộ node).
     * @param array<string,mixed> $data
     */
    private static function guardPlanWrite(int $id, array $data): ?string
    {
        if (array_key_exists('group_id', $data)) {
            $subs = (new Subscription())->countByPlanId($id);
            if ($subs > 0) {
                return 'Không thể đổi nhóm node (group_id) của gói #' . $id
                    . ' vì đang có ' . $subs . ' subscription tham chiếu — thay đổi này làm lệch đồng bộ VPS. '
                    . 'Hãy tạo gói mới thay thế.';
            }
        }
        if (array_key_exists('duration_days', $data) && (int) $data['duration_days'] <= 0) {
            return 'duration_days phải lớn hơn 0.';
        }
        if (array_key_exists('price', $data) && (float) $data['price'] < 0) {
            return 'price không được âm.';
        }
        if (array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null
            && $data['stock_quantity'] !== '' && (int) $data['stock_quantity'] < 0) {
            return 'stock_quantity không được âm (để trống = không giới hạn tồn kho).';
        }
        return null;
    }

    /** @param array<string,mixed> $def */
    private static function requireEntityDeletable(string $name, int $id, array $def): ?string
    {
        $guard = (string) ($def['guard'] ?? '');

        if ($name === 'order') {
            return 'Không xóa đơn hàng qua entity_delete — dùng action_run order_status (cancelled/failed) '
                . 'để hoàn trả tồn kho và cắt quyền đúng nghiệp vụ.';
        }
        if ($name === 'subscription') {
            return 'Không xóa subscription qua entity_delete — dùng action_run subscription_status (cancelled) '
                . 'để hoàn tồn kho và thu hồi quyền truy cập node.';
        }
        if ($name === 'user') {
            return self::requireUserDeletable($id);
        }
        if ($guard === 'readonly' || $guard === 'pivot') {
            $note = (string) ($def['note'] ?? '');
            return $note !== '' ? $note : "Thực thể '{$name}' chỉ đọc qua AI — không được xóa.";
        }

        switch ($name) {
            case 'plan':
                $n = (new Subscription())->countByPlanId($id);
                if ($n > 0) {
                    return "Không thể xóa gói #{$id} vì còn {$n} subscription đang dùng. Hãy chuyển sang status=inactive.";
                }
                break;
            case 'server_group':
                $n = self::countWhere('vc_servers', 'group_id', $id);
                if ($n > 0) {
                    return "Không thể xóa nhóm server #{$id} vì còn {$n} server thuộc nhóm "
                        . '(xóa nhóm sẽ kéo theo xóa server và toàn bộ cổng node). Hãy xóa server trước.';
                }
                break;
            case 'coupon':
                $used = (int) (self::fetchRow(self::pdo(), 'vc_coupons', 'id', $id)['used_count'] ?? 0);
                if ($used > 0) {
                    return "Không thể xóa voucher #{$id} vì đã sử dụng {$used} lần (lịch sử đơn hàng đang tham chiếu). "
                        . 'Hãy chuyển status=inactive.';
                }
                break;
            case 'ticket':
                $st = (string) (self::fetchRow(self::pdo(), 'vc_support_tickets', 'id', $id)['status'] ?? '');
                if ($st !== 'closed') {
                    return 'Chỉ được xóa ticket đã đóng. Hãy chuyển status=closed trước khi xóa.';
                }
                break;
        }
        return null;
    }

    private static function requireUserDeletable(int $id): ?string
    {
        if ($id === self::adminId()) {
            return 'Không thể xóa chính tài khoản của bạn.';
        }
        $u = (new User())->find($id);
        if (!is_array($u) || $u === []) {
            return null;
        }
        if ((string) ($u['role'] ?? '') === 'admin') {
            return 'Không thể xóa tài khoản quản trị.';
        }
        $subs = self::countWhere('vc_subscriptions', 'user_id', $id);
        if ($subs > 0) {
            return "Không thể xóa user #{$id} vì còn {$subs} subscription tham chiếu. "
                . 'Hãy hủy các subscription trước (action_run subscription_status = cancelled) rồi mới xóa user.';
        }
        $orders = self::countWhere('vc_orders', 'user_id', $id);
        $pend   = self::countWhere('vc_orders', 'user_id', $id, "payment_status = 'pending'");
        if ($orders > 0) {
            return "User #{$id} còn {$orders} đơn hàng" . ($pend > 0 ? " ({$pend} đơn đang chờ)" : '')
                . '. Xóa user sẽ xóa CASCADE toàn bộ đơn hàng, thanh toán, bài viết và hoa hồng giới thiệu của họ. '
                . 'Hãy hủy các đơn đang chờ trước, hoặc dùng status=banned nếu chỉ muốn khóa khách.';
        }
        return null;
    }

    private static function countWhere(string $table, string $col, int $val, string $extra = ''): int
    {
        try {
            $sql = 'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE ' . $col . ' = :v'
                . ($extra !== '' ? ' AND ' . $extra : '');
            $st = self::pdo()->prepare($sql);
            $st->execute([':v' => $val]);
            return (int) ($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =====================================================================
    //  KIỂM TRA SCHEMA THẬT (information_schema, có cache)
    // =====================================================================

    private static function hasColumn(string $table, string $col): bool
    {
        $key = $table . '.' . $col;
        if (isset(self::$columnCache[$key])) {
            return self::$columnCache[$key];
        }
        try {
            $st = self::pdo()->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $st->execute([':t' => $table, ':c' => $col]);
            self::$columnCache[$key] = (int) $st->fetchColumn() > 0;
        } catch (\Throwable $e) {
            self::$columnCache[$key] = false;
        }
        return self::$columnCache[$key];
    }

    /** @return array<int,string> Cột NOT NULL không có default, không auto_increment, không generated. */
    private static function mandatoryColumns(string $table): array
    {
        if (isset(self::$mandatoryCache[$table])) {
            return self::$mandatoryCache[$table];
        }
        $out = [];
        try {
            $st = self::pdo()->prepare(
                'SELECT COLUMN_NAME, EXTRA, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t ORDER BY ORDINAL_POSITION'
            );
            $st->execute([':t' => $table]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
                $extra = (string) ($c['EXTRA'] ?? '');
                if (stripos($extra, 'auto_increment') !== false || stripos($extra, 'GENERATED') !== false) {
                    continue;
                }
                if ((string) ($c['IS_NULLABLE'] ?? '') === 'YES') {
                    continue;
                }
                if ($c['COLUMN_DEFAULT'] !== null) {
                    continue;
                }
                if ((string) ($c['DATA_TYPE'] ?? '') === 'timestamp') {
                    continue;
                }
                $out[] = (string) $c['COLUMN_NAME'];
            }
        } catch (\Throwable $e) {
            $out = [];
        }
        self::$mandatoryCache[$table] = $out;
        return $out;
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,int> những id KHÔNG tồn tại
     */
    private static function missingIds(string $table, string $pk, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($v): bool => (int) $v > 0)));
        if ($ids === []) {
            return [];
        }
        if ($pk === '' || !self::hasColumn($table, $pk)) {
            return [];
        }
        $ph = [];
        $binds = [];
        foreach ($ids as $i => $id) {
            $ph[] = ':i' . $i;
            $binds[':i' . $i] = (int) $id;
        }
        try {
            $st = self::pdo()->prepare('SELECT ' . $pk . ' FROM ' . $table . ' WHERE ' . $pk . ' IN (' . implode(', ', $ph) . ')');
            $st->execute($binds);
            $found = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
            return array_values(array_diff($ids, $found));
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    //  ÉP KIỂU / MÃ HÓA GIÁ TRỊ
    // =====================================================================

    /** @return array<int,int> */
    private static function parseIdList(mixed $raw): array
    {
        if ($raw === null || $raw === '' || (is_string($raw) && strcasecmp($raw, 'null') === 0)) {
            return [];
        }
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = preg_split('/[,\s;]+/', (string) $raw) ?: [];
        }
        $out = [];
        foreach ($items as $v) {
            if (is_array($v)) {
                continue;
            }
            $s = trim((string) $v);
            if ($s === '' || !is_numeric($s)) {
                continue;
            }
            $i = (int) $s;
            if ($i > 0) {
                $out[] = $i;
            }
        }
        return array_values(array_unique($out));
    }

    private static function encodeJson(mixed $val): ?string
    {
        if ($val === null || $val === [] || $val === '') {
            return null;
        }
        $items = is_array($val) ? $val : (preg_split('/[,\s;]+/', (string) $val) ?: []);
        $ids = [];
        foreach ($items as $v) {
            if (is_array($v)) {
                continue;
            }
            $s = trim((string) $v);
            if ($s === '' || !is_numeric($s)) {
                continue;
            }
            $ids[] = (int) $s;
        }
        $ids = array_values(array_unique($ids));
        return $ids === [] ? null : json_encode($ids, JSON_UNESCAPED_UNICODE);
    }

    private static function castNumber(mixed $val): mixed
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_bool($val)) {
            return $val ? 1 : 0;
        }
        $s = trim((string) $val);
        $s = str_replace([',', ' '], ['', ''], $s);
        if (!is_numeric($s)) {
            return 0;
        }
        if (strpos($s, '.') !== false) {
            return round((float) $s, 2);
        }
        return (int) $s;
    }

    /** @param array<int,string> $allowed */
    private static function clampEnum(mixed $val, array $allowed): string
    {
        $s = strtolower(trim((string) $val));
        return in_array($s, $allowed, true) ? $s : (string) ($allowed[0] ?? '');
    }

    private static function castText(mixed $val, int $limit): string
    {
        if ($val === null || (is_string($val) && strcasecmp($val, 'null') === 0)) {
            return '';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        if (is_array($val)) {
            $val = implode(', ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $val));
        }
        $s = trim((string) $val);
        if ($limit > 0 && strlen($s) > $limit) {
            $s = substr($s, 0, $limit);
        }
        return $s;
    }

    // =====================================================================
    //  TỰ SINH GIÁ TRỊ (chỉ 4 loại được phép — không bao giờ bịa dữ liệu nghiệp vụ)
    // =====================================================================

    /** @param array<string,mixed> $data */
    private static function generate(string $kind, string $name, array $data, PDO $pdo): mixed
    {
        switch ($kind) {
            case 'order_code':
                do {
                    $code = 'AD' . date('YmdHis') . rand(100, 999);
                } while (self::checkUnique('vc_orders', 'order_code', $code, 0) !== null);
                return $code;

            case 'slug':
                $title = (string) ($data['title'] ?? '');
                return self::uniqueSlugV(self::slugifyViet($title));

            case 'ref_code':
                do {
                    $code = strtoupper(substr(md5(uniqid((string) ($data['username'] ?? '') . mt_rand(), true)), 0, 8));
                } while (self::checkUnique('vc_users', 'ref_code', $code, 0) !== null);
                return $code;

            case 'uuid':
                return self::uuidV4();

            case 'password':
                // Không bao giờ tự chế mật khẩu: chỉ trả bcrypt khi Admin đưa rõ password trong data.
                $pw = (string) ($data['password'] ?? '');
                return strlen($pw) >= 6 ? password_hash($pw, PASSWORD_BCRYPT) : null;

            default:
                return null;
        }
    }

    private static function uuidV4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private static function slugifyViet(string $text): string
    {
        $map = [
            'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'á' => 'a', 'ạ' => 'a', 'ă' => 'a', 'ằ' => 'a', 'ẳ' => 'a',
            'ắ' => 'a', 'ặ' => 'a', 'â' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ấ' => 'a', 'ậ' => 'a',
            'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ê' => 'e', 'ề' => 'e', 'ể' => 'e',
            'ế' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'í' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ổ' => 'o',
            'ố' => 'o', 'ộ' => 'o', 'ơ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ớ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ư' => 'u', 'ừ' => 'u', 'ử' => 'u',
            'ứ' => 'u', 'ự' => 'u',
            'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ý' => 'y', 'ỵ' => 'y',
            'đ' => 'd',
        ];
        $t = mb_strtolower(trim($text), 'UTF-8');
        $out = '';
        $len = mb_strlen($t, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($t, $i, 1, 'UTF-8');
            if (isset($map[$ch])) {
                $ch = $map[$ch];
            }
            $out .= preg_match('/[a-z0-9]/', $ch) ? $ch : '-';
        }
        $out = trim(preg_replace('/-+/', '-', $out) ?? '', '-');
        return $out !== '' ? substr($out, 0, 200) : 'bai-viet';
    }

    private static function uniqueSlugV(string $slug): string
    {
        $base = $slug;
        for ($i = 0; $i < 100; $i++) {
            $try = $i === 0 ? $base : $base . '-' . substr((string) microtime(true), -4) . '-' . $i;
            if (self::checkUnique('vc_posts', 'slug', $try, 0) === null) {
                return $try;
            }
        }
        return $base . '-' . time();
    }

    // =====================================================================
    //  NHỊP NODE — một bộ helper duy nhất, gọi đúng NodeTaskService
    // =====================================================================

    /** @return array<int,int> */
    private static function parseGroupIds(mixed $raw): array
    {
        if (is_array($raw)) {
            $ids = $raw;
        } else {
            $s = trim((string) $raw);
            if ($s === '') {
                return [];
            }
            $dec = json_decode($s, true);
            $ids = is_array($dec) ? $dec : [$s];
        }
        return array_values(array_filter(
            array_map(static fn ($v): int => (int) $v, $ids),
            static fn (int $v): bool => $v > 0
        ));
    }

    /** @param array<int,int> $groupIds */
    private static function dispatchAdd(int $subId, string $uuid, int $bytes, string $endDate, array $groupIds, int $maxDevices, string $status): void
    {
        if ($groupIds === []) {
            return;
        }
        try {
            (new NodeTaskService())->addUser($groupIds, 'sub_' . $subId, $uuid, $bytes, $endDate, $status, $maxDevices);
        } catch (\Throwable $e) {
            error_log('[EntityCrudTools] dispatchAdd sub#' . $subId . ': ' . $e->getMessage());
        }
    }

    /** @param array<int,int> $groupIds */
    private static function dispatchDisable(int $subId, array $groupIds, string $reason): void
    {
        if ($groupIds === []) {
            return;
        }
        try {
            (new NodeTaskService())->disableUser($groupIds, 'sub_' . $subId, $reason);
        } catch (\Throwable $e) {
            error_log('[EntityCrudTools] dispatchDisable sub#' . $subId . ': ' . $e->getMessage());
        }
    }

    /** @param array<int,int> $groupIds */
    private static function dispatchDelete(int $subId, array $groupIds, string $reason = 'admin'): void
    {
        if ($groupIds === []) {
            return;
        }
        try {
            (new NodeTaskService())->deleteUser($groupIds, 'sub_' . $subId, $reason);
        } catch (\Throwable $e) {
            error_log('[EntityCrudTools] dispatchDelete sub#' . $subId . ': ' . $e->getMessage());
        }
    }

    /**
     * Mô tả JSON-schema cho 6 tool (nạp vào payload `tools` khi gọi Kira).
     * @return array<string,array<string,mixed>>
     */
    public static function schemas(): array
    {
        $entityEnum = EntityCatalog::names();
        sort($entityEnum);

        $entityProp = ['type' => 'string', 'enum' => $entityEnum,
            'description' => 'Tên thực thể nghiệp vụ lấy từ EntityCatalog.'];

        return [
            'entity_list' => [
                'type' => 'object',
                'properties' => [
                    'entity'  => $entityProp,
                    'q'       => ['type' => 'string', 'description' => 'Từ khóa tìm theo cột nhãn (tên/mã/email...).'],
                    'filters' => ['type' => 'object', 'description' => 'Bộ lọc đẳng trị theo cột đã whitelist, ví dụ {"status":"active"}.',
                                  'additionalProperties' => true],
                    'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_ROWS, 'description' => 'Số bản ghi tối đa (mặc định 20).'],
                    'page'    => ['type' => 'integer', 'minimum' => 1, 'description' => 'Trang, bắt đầu từ 1.'],
                ],
                'required' => ['entity'],
                'additionalProperties' => false,
            ],
            'entity_get' => [
                'type' => 'object',
                'properties' => [
                    'entity' => $entityProp,
                    'id'     => ['type' => 'integer', 'minimum' => 1, 'description' => 'id bản ghi.'],
                    'uuid'   => ['type' => 'string', 'description' => 'Riêng subscription: tra bằng uuid.'],
                ],
                'required' => ['entity'],
                'additionalProperties' => false,
            ],
            'entity_create' => [
                'type' => 'object',
                'properties' => [
                    'entity' => ['type' => 'string', 'enum' => EntityCatalog::writableNames(),
                                 'description' => 'Thực thể được phép thêm.'],
                    'data'   => ['type' => 'object', 'description' => 'Cặp cột => giá trị. Chỉ cột trong danh sách ghi của thực thể; phải đủ cột bắt buộc của bảng.',
                                 'additionalProperties' => true],
                ],
                'required' => ['entity', 'data'],
                'additionalProperties' => false,
            ],
            'entity_update' => [
                'type' => 'object',
                'properties' => [
                    'entity' => ['type' => 'string', 'enum' => EntityCatalog::writableNames(),
                                 'description' => 'Thực thể được phép sửa.'],
                    'id'     => ['type' => 'integer', 'minimum' => 1, 'description' => 'id bản ghi cần sửa.'],
                    'data'   => ['type' => 'object', 'description' => 'Chỉ chứa cột thay đổi, trong danh sách ghi của thực thể.',
                                 'additionalProperties' => true],
                ],
                'required' => ['entity', 'id', 'data'],
                'additionalProperties' => false,
            ],
            'entity_delete' => [
                'type' => 'object',
                'properties' => [
                    'entity' => $entityProp,
                    'id'     => ['type' => 'integer', 'minimum' => 1, 'description' => 'id bản ghi cần xóa.'],
                ],
                'required' => ['entity', 'id'],
                'additionalProperties' => false,
            ],
            'action_run' => [
                'type' => 'object',
                'properties' => [
                    'action'    => ['type' => 'string', 'enum' => self::ACTIONS, 'description' => 'Tên nghiệp vụ cần chạy.'],
                    'id'        => ['type' => 'integer', 'minimum' => 1, 'description' => 'id bản ghi nghiệp vụ (subscription/order/payment/withdrawal/ticket).'],
                    'uuid'      => ['type' => 'string', 'description' => 'uuid subscription (thay cho id khi Admin đưa uuid).'],
                    'status'    => ['type' => 'string', 'description' => 'Trạng thái đích: active|suspended|cancelled|expired (subscription) hoặc completed|failed|cancelled (order).'],
                    'days'      => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3650, 'description' => 'Số ngày gia hạn (subscription_renew).'],
                    'message'   => ['type' => 'string', 'maxLength' => 60000, 'description' => 'Nội dung trả lời khách (ticket_reply).'],
                    'decision'  => ['type' => 'string', 'enum' => ['approved', 'rejected'], 'description' => 'Quyết định rút tiền (withdrawal_decision).'],
                    'coupon_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'id voucher (coupon_*).'],
                    'user_ids'  => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 500, 'description' => 'Danh sách id khách (coupon_assign/unassign).'],
                    'plan_ids'  => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 100, 'description' => 'Danh sách id gói (coupon_plans).'],
                ],
                'required' => ['action'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * Bản chuyển đổi sang dạng ToolRegistry::SCHEMA (chỉ required + properties),
     * để nativeSchema()/sanitizeArgs() dùng được mà registry không phải tự
     * khai báo schema của 6 tool CRUD.
     *
     * @return array<string, array{required: string[], properties: array<string, array<string, mixed>>}>
     */
    public static function registrySchemas(): array
    {
        $out = [];
        foreach (self::schemas() as $name => $sch) {
            $out[$name] = [
                'required'   => (array) ($sch['required'] ?? []),
                'properties' => (array) ($sch['properties'] ?? []),
            ];
        }
        return $out;
    }
}
