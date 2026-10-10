<?php
declare(strict_types=1);

namespace App\AI\Knowledge;

/**
 * BƯỚC 2 (mục 2.1): Danh mục ngữ cảnh dữ liệu cho AI Trợ lý Admin.
 *
 * Đây là NGUỒN SỰ THẬT DUY NHẤT mô tả "được đụng vào bảng nào, cột nào, giá trị
 * nào". EntityCrudTools chỉ tin vào danh mục này; mọi thứ ngoài danh mục là từ chối.
 *
 * TỪ CHỐI CÓ CHỦ ĐÍCH (kèm lý do, để sau này không ai "tiện tay" thêm vào):
 *   - vc_ai_providers / vc_ai_models / vc_ai_assets / vc_ai_outputs / vc_ai_tasks:
 *     cấu hình lõi AI (endpoint, key, prompt gốc). Admin sửa qua trang AI riêng.
 *   - vc_chat_* : dữ liệu hội thoại khách, thuộc BƯỚC 4 (đọc thôi, không CRUD).
 *   - vc_email_logs, vc_subscription_access_logs, vc_access_logs, vc_system_logs:
 *     bằng chứng kiểm toán, xóa/sửa chúng = xóa dấu vết.
 *   - vc_node_tasks: hàng đợi cho VPS, chỉ NodeTaskService được ghi (xem docs/task-contract.md).
 *
 * Quy tắc đặt tên cột: lấy đúng từ information_schema (đã đối chiếu 40 bảng).
 * ENUM: liệt kê đúng vocabulary trong DB, không được tự chế giá trị mới.
 */
final class EntityCatalog
{
    public const KIND_RECORD = 'record';   /** bảng có id, CRUD trực tiếp được */
    public const KIND_PIVOT  = 'pivot';    /** khóa phức hợp, chỉ attach/detach qua Service */
    public const KIND_VIRTUAL = 'virtual'; /** model không kế thừa BaseModel: chỉ đọc */

    /** Cột nhạy cảm: kế thừa tinh thần DENY_RE của PageEditTools. */
    private const SECRET_RE = '/(pass|pwd|secret|token|api[_-]?key|private|salt|hash|2fa|otp|credential|auth)/i';

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return [
            /* ==================== NGƯỜI DÙNG ==================== */
            'user' => [
                'table'    => 'vc_users',
                'model'    => \App\Models\User::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['username', 'email'],
                'list_url' => '/admin/users',
                'note'     => 'Xóa user là xóa cứng (không có soft delete).',
                'read'     => ['id', 'username', 'email', 'role', 'status', 'balance', 'commission_balance',
                    'ref_code', 'referred_by', 'created_by', 'register_ip', 'last_login_time', 'created_at', 'updated_at'],
                'write'    => ['username', 'email', 'role', 'status', 'balance', 'commission_balance', 'referred_by'],
                'enums'    => [
                    'role'   => ['admin', 'user'],
                    'status' => ['active', 'inactive', 'banned'],
                ],
                'required' => ['username', 'email'],
                'unique'   => ['username', 'email', 'ref_code'],
                'relations' => [
                    'referred_by' => ['user', 'id'],
                ],
                'limits'   => ['username' => 50, 'email' => 100],
                'defaults' => ['role' => 'user', 'status' => 'active'],
                'generators' => ['ref_code' => 'ref_code', 'password_hash' => 'password'],
                'secret'   => ['password_hash', 'google_id'],
                'guard'    => 'user_delete',
            ],

            /* ==================== GÓI CƯỚC ==================== */
            'plan' => [
                'table'    => 'vc_vpn_plans',
                'model'    => \App\Models\VpnPlan::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['name', 'code'],
                'list_url' => '/admin/plans',
                'note'     => 'group_id là JSON (danh sách nhóm máy chủ). Đang có subscription thì không xóa được, phải chuyển status=inactive.',
                'read'     => ['id', 'name', 'code', 'group_id', 'price', 'duration_days', 'bandwidth_limit_gb',
                    'max_devices', 'stock_quantity', 'description', 'status', 'created_at', 'updated_at'],
                'write'    => ['name', 'code', 'group_id', 'price', 'duration_days', 'bandwidth_limit_gb',
                    'max_devices', 'stock_quantity', 'description', 'status'],
                'enums'    => ['status' => ['active', 'inactive']],
                'required' => ['name', 'code', 'price', 'duration_days'],
                'unique'   => ['code'],
                'relations' => [
                    'group_id' => ['server_group', 'id'],
                ],
                'limits'   => ['name' => 100, 'code' => 50, 'description' => 60000],
                'defaults' => ['status' => 'active', 'max_devices' => 1, 'bandwidth_limit_gb' => 0],
                'json'     => ['group_id'],
                'numeric'  => ['price', 'duration_days', 'bandwidth_limit_gb', 'max_devices', 'stock_quantity'],
                'guard'    => 'plan_delete',
            ],

            /* ==================== ĐƠN HÀNG ==================== */
            'order' => [
                'table'    => 'vc_orders',
                'model'    => \App\Models\Order::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['order_code'],
                'list_url' => '/admin/orders',
                'note'     => 'KHÔNG sửa payment_status trực tiếp: phải dùng action_run order_status (kéo theo kích hoạt subscription, cộng tiền, nhả stock).',
                'read'     => ['id', 'order_code', 'user_id', 'plan_id', 'subscription_id', 'coupon_id',
                    'coupon_counted', 'total_amount', 'payment_method', 'payment_status', 'transfer_content',
                    'purchase_ip', 'created_by', 'approved_by', 'stock_reserved', 'created_at', 'updated_at'],
                'write'    => ['user_id', 'plan_id', 'coupon_id', 'total_amount', 'payment_method', 'transfer_content'],
                'enums'    => [
                    'payment_status' => ['pending', 'completed', 'failed', 'cancelled'],
                    // payment_method là varchar(50) nhưng hệ thống chỉ dùng 2 giá trị này
                    // (xem Order::invoiceMailVars / PaymentService::$methodLabels / view admin.orders.detail).
                    'payment_method' => ['vietqr', 'balance'],
                ],
                'required' => ['user_id', 'plan_id', 'total_amount'],
                'unique'   => ['order_code'],
                'relations' => [
                    'user_id'   => ['user', 'id'],
                    'plan_id'   => ['plan', 'id'],
                    'coupon_id' => ['coupon', 'id'],
                ],
                'limits'   => ['order_code' => 50, 'payment_method' => 50, 'transfer_content' => 255],
                'defaults' => ['payment_status' => 'pending', 'payment_method' => 'vietqr'],
                'numeric'  => ['total_amount'],
                'generators' => ['order_code' => 'order_code'],
                'blocked_on_write' => ['payment_status', 'subscription_id', 'stock_reserved', 'coupon_counted', 'approved_by', 'created_by'],
                'guard'    => 'order_delete',
            ],

            /* ==================== SUBSCRIPTION ==================== */
            'subscription' => [
                'table'    => 'vc_subscriptions',
                'model'    => \App\Models\Subscription::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['uuid'],
                'list_url' => '/admin/subscriptions',
                'note'     => 'SỐT NHẤT: status/end_date/transfer_enable KHÔNG được sửa trực tiếp vì phải đồng bộ VPS. Dùng action_run (subscription_status, subscription_renew, subscription_reset_traffic, subscription_reset_token).',
                'read'     => ['id', 'user_id', 'plan_id', 'order_id', 'stock_state', 'uuid', 'max_devices',
                    'transfer_enable', 'upload', 'download', 'last_used_ip', 'online_devices',
                    'network_locked_until', 'start_date', 'end_date', 'status', 'created_at', 'updated_at'],
                'write'    => ['max_devices'],
                'enums'    => [
                    'status'      => ['active', 'expired', 'suspended', 'cancelled'],
                    'stock_state' => ['none', 'held', 'released'],
                ],
                'required' => ['user_id', 'plan_id'],
                'unique'   => ['uuid'],
                'relations' => [
                    'user_id'  => ['user', 'id'],
                    'plan_id'  => ['plan', 'id'],
                    'order_id' => ['order', 'id'],
                ],
                'limits'   => ['uuid' => 36, 'last_used_ip' => 45],
                'defaults' => ['status' => 'active', 'max_devices' => 1],
                'generators' => ['uuid' => 'uuid'],
                'blocked_on_write' => ['status', 'end_date', 'start_date', 'transfer_enable', 'upload', 'download',
                    'network_locked_until', 'stock_state', 'order_id'],
                'guard'    => 'subscription_delete',
            ],

            /* ==================== MÁY CHỦ ==================== */
            'server' => [
                'table'    => 'vc_servers',
                'model'    => \App\Models\Server::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['name', 'ip_address'],
                'list_url' => '/admin/servers',
                'note'     => 'api_token là secret: chỉ được ghi, không bao giờ được đọc/hiển thị.',
                'read'     => ['id', 'group_id', 'name', 'country_code', 'location', 'ip_address', 'api_port',
                    'status', 'last_check_in', 'created_at', 'updated_at'],
                'write'    => ['group_id', 'name', 'country_code', 'location', 'ip_address', 'api_port', 'api_token', 'status'],
                'enums'    => ['status' => ['active', 'maintenance', 'offline']],
                'required' => ['name', 'ip_address'],
                'unique'   => ['ip_address'],
                'relations' => [
                    'group_id' => ['server_group', 'id'],
                ],
                'limits'   => ['name' => 100, 'country_code' => 10, 'location' => 100, 'ip_address' => 45, 'api_token' => 255],
                'defaults' => ['status' => 'active', 'api_port' => 80],
                'numeric'  => ['api_port'],
                'secret'   => ['api_token'],
                'guard'    => 'server_delete',
            ],

            'server_group' => [
                'table'    => 'vc_server_groups',
                'model'    => \App\Models\ServerGroup::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['name'],
                'list_url' => '/admin/server-groups',
                'read'     => ['id', 'name', 'description', 'status', 'created_at'],
                'write'    => ['name', 'description', 'status'],
                'enums'    => ['status' => ['active', 'inactive']],
                'required' => ['name'],
                'unique'   => ['name'],
                'relations' => [],
                'limits'   => ['name' => 100, 'description' => 255],
                'defaults' => ['status' => 'active'],
                'guard'    => 'server_group_delete',
            ],

            'node_inbound' => [
                'table'    => 'vc_node_inbounds',
                'model'    => \App\Models\NodeInbound::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['tag', 'protocol'],
                'list_url' => '/admin/servers',
                'note'     => 'Inbound thường do đồng bộ node sinh ra; sửa tay có thể làm hỏng kết nối client.',
                'read'     => ['id', 'server_id', 'tag', 'port', 'protocol', 'network', 'tls', 'sni', 'host',
                    'path', 'public_key', 'short_id', 'service_name', 'status', 'connected_devices'],
                'write'    => ['server_id', 'tag', 'port', 'protocol', 'network', 'tls', 'sni', 'host', 'path',
                    'public_key', 'short_id', 'service_name', 'status'],
                'enums'    => [
                    'protocol' => ['vmess', 'vless', 'trojan', 'shadowsocks', 'wireguard', 'hy2', 'tuic'],
                    'network'  => ['tcp', 'ws', 'grpc', 'udp', 'quic'],
                    'status'   => ['active', 'inactive'],
                ],
                'required' => ['server_id', 'port', 'protocol'],
                'unique'   => [],
                'relations' => [
                    'server_id' => ['server', 'id'],
                ],
                'limits'   => ['tag' => 100, 'sni' => 255, 'host' => 255, 'path' => 255, 'public_key' => 255, 'short_id' => 255, 'service_name' => 255],
                'defaults' => ['network' => 'tcp', 'tls' => 1, 'status' => 'active'],
                'numeric'  => ['port', 'tls'],
                'secret'   => ['password'],
                'guard'    => 'node_inbound_delete',
            ],

            /* ==================== THANH TOÁN / RÚT TIỀN ==================== */
            'payment' => [
                'table'    => 'vc_payments',
                'model'    => \App\Models\Payment::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['transaction_id'],
                'list_url' => '/admin/payments',
                'note'     => 'Không tạo/sửa payment bằng CRUD: tiền vào ví khách phải qua action_run approve_deposit (PaymentService::completePayment).',
                'read'     => ['id', 'user_id', 'order_id', 'subscription_id', 'type', 'payment_method',
                    'transaction_id', 'transfer_content', 'amount', 'status', 'created_at'],
                'write'    => [],
                'enums'    => [
                    'type'   => ['deposit', 'payment'],
                    'status' => ['pending', 'success', 'failed'],
                ],
                'required' => ['user_id', 'type', 'amount'],
                'unique'   => ['transaction_id'],
                'relations' => [
                    'user_id'         => ['user', 'id'],
                    'order_id'        => ['order', 'id'],
                    'subscription_id' => ['subscription', 'id'],
                ],
                'limits'   => ['payment_method' => 50, 'transaction_id' => 100, 'transfer_content' => 255],
                'numeric'  => ['amount'],
                'guard'    => 'payment_delete',
            ],

            'withdrawal' => [
                'table'    => 'vc_withdrawals',
                'model'    => \App\Models\Withdrawal::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['bank_account_number'],
                'list_url' => '/admin/withdrawals',
                'note'     => 'Chỉ duyệt/từ chối qua action_run withdrawal_decision (Withdrawal::updateStatus có transaction + kiểm tra số dư). Không CRUD trực tiếp.',
                'read'     => ['id', 'user_id', 'amount', 'bank_name', 'bank_account_number', 'bank_account_name', 'status', 'created_at'],
                'write'    => [],
                'enums'    => ['status' => ['pending', 'approved', 'rejected']],
                'required' => ['user_id', 'amount'],
                'unique'   => [],
                'relations' => [
                    'user_id' => ['user', 'id'],
                ],
                'limits'   => ['bank_name' => 100, 'bank_account_number' => 50, 'bank_account_name' => 100],
                'numeric'  => ['amount'],
                'guard'    => 'readonly',
            ],

            'referral_commission' => [
                'table'    => 'vc_referral_commissions',
                'model'    => \App\Models\ReferralCommission::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['commission_amount'],
                'list_url' => '/admin/referrals',
                'note'     => 'Hoa hồng do hệ thống sinh khi đơn completed. Chỉ đọc.',
                'read'     => ['id', 'referrer_id', 'referred_user_id', 'order_id', 'commission_rate', 'commission_amount', 'created_at'],
                'write'    => [],
                'enums'    => [],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'numeric'  => ['commission_rate', 'commission_amount'],
                'guard'    => 'readonly',
            ],

            /* ==================== MARKETING / NỘI DUNG ==================== */
            'coupon' => [
                'table'    => 'vc_coupons',
                'model'    => \App\Models\Coupon::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['code'],
                'list_url' => '/admin/coupons',
                'note'     => 'Gán user / giới hạn gói: action_run coupon_assign, coupon_unassign, coupon_plans (không đụng bảng bắc cầu bằng CRUD).',
                'read'     => ['id', 'code', 'discount_type', 'discount_value', 'max_uses', 'used_count', 'expires_at', 'status', 'created_at'],
                'write'    => ['code', 'discount_type', 'discount_value', 'max_uses', 'expires_at', 'status'],
                'enums'    => [
                    'discount_type' => ['percent', 'fixed'],
                    'status'        => ['active', 'inactive'],
                ],
                'required' => ['code', 'discount_type', 'discount_value'],
                'unique'   => ['code'],
                'relations' => [],
                'limits'   => ['code' => 50],
                'defaults' => ['status' => 'active', 'discount_type' => 'percent', 'max_uses' => 0],
                'numeric'  => ['discount_value', 'max_uses'],
                'dates'    => ['expires_at'],
                'guard'    => 'coupon_delete',
            ],

            'post' => [
                'table'    => 'vc_posts',
                'model'    => \App\Models\Post::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['title'],
                'list_url' => '/admin/posts',
                'note'     => 'Bài viết/blob hướng dẫn khách: cùng cấu trúc mà BƯỚC 4 dùng để trả lời chính xác.',
                'read'     => ['id', 'author_id', 'title', 'slug', 'content', 'type', 'status', 'created_at'],
                'write'    => ['title', 'slug', 'content', 'type', 'status'],
                'enums'    => [
                    'type'   => ['news', 'tutorial', 'faq', 'popup'],
                    'status' => ['published', 'draft', 'hidden'],
                ],
                'required' => ['title'],
                'unique'   => ['slug'],
                'relations' => [
                    'author_id' => ['user', 'id'],
                ],
                'limits'   => ['title' => 255, 'slug' => 255, 'content' => 60000],
                'defaults' => ['type' => 'news', 'status' => 'published'],
                'generators' => ['slug' => 'slug'],
                'guard'    => 'post_delete',
            ],

            'expense' => [
                'table'    => 'vc_expenses',
                'model'    => \App\Models\Expense::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['title'],
                'list_url' => '/admin/expenses',
                'read'     => ['id', 'title', 'amount', 'category', 'note', 'expense_date', 'created_at'],
                'write'    => ['title', 'amount', 'category', 'note', 'expense_date'],
                'enums'    => [],
                'required' => ['title', 'amount', 'expense_date'],
                'unique'   => [],
                'relations' => [],
                'limits'   => ['title' => 200, 'category' => 100, 'note' => 60000],
                'numeric'  => ['amount'],
                'dates'    => ['expense_date'],
                'guard'    => 'record_delete',
            ],

            'campaign' => [
                'table'    => 'vc_campaigns',
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['title'],
                'list_url' => '',
                'note'     => 'CampaignService dùng PDO riêng (không có model) và chưa có trang admin riêng: tạo/sửa/gửi phải đi tool campaign_create / campaign_status, không dùng CRUD chung.',
                'read'     => ['id', 'kind', 'title', 'subject', 'body', 'target_mode', 'target_ref', 'daily_limit',
                    'status', 'last_sent_date', 'total_sent', 'created_by', 'created_at', 'updated_at'],
                'write'    => [],
                'enums'    => [],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],

            /* ==================== TICKET ==================== */
            'ticket' => [
                'table'    => 'vc_support_tickets',
                'model'    => \App\Models\SupportTicket::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['subject'],
                'list_url' => '/admin/tickets',
                'note'     => 'Trả lời + chuyển trạng thái: action_run ticket_reply. Chỉ xóa được ticket đã closed.',
                'read'     => ['id', 'user_id', 'assigned_staff_id', 'subject', 'status', 'created_at'],
                'write'    => ['assigned_staff_id', 'status'],
                'enums'    => ['status' => ['open', 'in_progress', 'resolved', 'closed']],
                'required' => ['user_id', 'subject'],
                'unique'   => [],
                'relations' => [
                    'user_id'          => ['user', 'id'],
                    'assigned_staff_id' => ['user', 'id'],
                ],
                'limits'   => ['subject' => 255],
                'defaults' => ['status' => 'open'],
                'guard'    => 'ticket_delete',
            ],

            'ticket_message' => [
                'table'    => 'vc_ticket_messages',
                'model'    => \App\Models\TicketMessage::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['message'],
                'list_url' => '/admin/tickets',
                'note'     => 'Chỉ thêm (action_run ticket_reply). Không sửa/xóa tin nhắn đã gửi.',
                'read'     => ['id', 'ticket_id', 'sender_id', 'message', 'created_at'],
                'write'    => ['ticket_id', 'sender_id', 'message'],
                'enums'    => [],
                'required' => ['ticket_id', 'message'],
                'unique'   => [],
                'relations' => [
                    'ticket_id' => ['ticket', 'id'],
                    'sender_id' => ['user', 'id'],
                ],
                'limits'   => ['message' => 60000],
                'guard'    => 'readonly',
            ],

            /* ==================== CÀI ĐẶT ==================== */
            'setting' => [
                'table'    => 'vc_settings',
                'model'    => \App\Models\Setting::class,
                'kind'     => self::KIND_RECORD,
                'pk'       => 'setting_key',
                'label'    => ['setting_key'],
                'list_url' => '/admin/settings',
                'note'     => 'Chỉ được sửa các khóa trong allow-list PageEditTools::SETTING_ALLOW. Không thêm khóa mới.',
                'read'     => ['id', 'setting_key', 'setting_value', 'description', 'updated_at'],
                'write'    => [],
                'enums'    => [],
                'required' => [],
                'unique'   => ['setting_key'],
                'relations' => [],
                'limits'   => ['setting_value' => 60000],
                'guard'    => 'readonly',
            ],

            /* ==================== BẮC CẦU (chỉ attach/detach) ==================== */
            'coupon_plan' => [
                'table'   => 'vc_coupon_plans',
                'kind'    => self::KIND_PIVOT,
                'label'   => ['coupon_id', 'plan_id'],
                'note'    => 'Bảng bắc cầu khóa phức hợp: dùng action_run coupon_plans (Coupon::setPlans).',
                'read'    => ['coupon_id', 'plan_id', 'created_at'],
                'write'   => [],
                'enums'   => [],
                'required' => [],
                'unique'  => [],
                'relations' => [],
                'limits'  => [],
                'guard'   => 'pivot',
            ],
            'coupon_user' => [
                'table'   => 'vc_coupon_users',
                'kind'    => self::KIND_PIVOT,
                'label'   => ['coupon_id', 'user_id'],
                'note'    => 'Bảng bắc cầu khóa phức hợp: dùng action_run coupon_assign / coupon_unassign (Coupon::assignUsers / removeAssignments).',
                'read'    => ['coupon_id', 'user_id', 'created_at'],
                'write'   => [],
                'enums'   => [],
                'required' => [],
                'unique'  => [],
                'relations' => [],
                'limits'  => [],
                'guard'   => 'pivot',
            ],

            /* ==================== CHỈ ĐỌC (MODEL KHÔNG KẾ THỪA BASEMODEL) ==================== */
            'node_task' => [
                'table'    => 'vc_node_tasks',
                'model'    => \App\Models\NodeTask::class,
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['action'],
                'list_url' => '/admin/nodes',
                'note'     => 'Hàng đợi gửi VPS. payload/action/status theo docs/task-contract.md; chỉ NodeTaskService được ghi.',
                'read'     => ['id', 'server_id', 'action', 'payload', 'status', 'attempts', 'error_msg', 'created_at', 'updated_at'],
                'write'    => [],
                'enums'    => [
                    'action' => ['add_user', 'delete_user', 'disable_user', 'enable_user'],
                    'status' => ['pending', 'completed', 'failed'],
                ],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],
            'system_log' => [
                'table'    => 'vc_system_logs',
                'model'    => \App\Models\SystemLog::class,
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['action'],
                'list_url' => '/admin/logs',
                'note'     => 'Nhật ký thao tác (AI cũng ghi vào đây). Chỉ đọc.',
                'read'     => ['id', 'user_id', 'action', 'description', 'ip_address', 'created_at'],
                'write'    => [],
                'enums'    => [],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],
            'access_log' => [
                'table'    => 'vc_access_logs',
                'model'    => \App\Models\AccessLog::class,
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['action'],
                'list_url' => '/admin/logs',
                'note'     => 'Truy cập site của khách. Chỉ đọc.',
                'read'     => ['id', 'user_id', 'action', 'source', 'referrer_host', 'landing_path',
                    'utm_source', 'utm_medium', 'utm_campaign', 'ip_address', 'user_agent', 'created_at'],
                'write'    => [],
                'enums'    => [],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],
            'subscription_access_log' => [
                'table'    => 'vc_subscription_access_logs',
                'model'    => \App\Models\SubscriptionAccessLog::class,
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['ip_address'],
                'list_url' => '/admin/subscriptions',
                'note'     => 'Lịch sử client lấy cấu hình VPN (xem tại trang chi tiết gói đăng ký). Chỉ đọc.',
                'read'     => ['id', 'subscription_id', 'ip_address', 'app_name', 'os_name', 'request_type', 'user_agent', 'created_at'],
                'write'    => [],
                'enums'    => [],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],
            'chat_session' => [
                'table'    => 'vc_chat_sessions',
                'model'    => \App\Models\ChatSession::class,
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['visitor_token'],
                'list_url' => '/admin/ai/reply',
                'note'     => 'Phiên chat khách (web/Facebook fanpage). Thuộc BƯỚC 4; ở đây chỉ đọc.',
                'read'     => ['id', 'user_id', 'visitor_token', 'source', 'external_id', 'status', 'created_at', 'updated_at'],
                'write'    => [],
                'enums'    => [
                    'source' => ['web', 'fanpage'],
                    'status' => ['open', 'handoff', 'closed'],
                ],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],
            'chat_message' => [
                'table'    => 'vc_chat_messages',
                'model'    => \App\Models\ChatMessage::class,
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['content'],
                'list_url' => '/admin/ai/reply',
                'note'     => 'Tin nhắn khách. Thuộc BƯỚC 4; ở đây chỉ đọc.',
                'read'     => ['id', 'session_id', 'role', 'content', 'provider', 'model', 'created_at'],
                'write'    => [],
                'enums'    => ['role' => ['user', 'assistant', 'system']],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],
            'ai_tool_call' => [
                'table'    => 'vc_ai_tool_calls',
                'model'    => \App\Models\AiToolCall::class,
                'kind'     => self::KIND_VIRTUAL,
                'label'    => ['tool'],
                'list_url' => '/admin/assistant',
                'note'     => 'Nhật ký tool AI đã gọi (kiểm toán BƯỚC 1). Chỉ đọc.',
                'read'     => ['id', 'conversation_id', 'admin_id', 'tool', 'risk', 'args_json', 'before_json',
                    'after_json', 'result_json', 'ok', 'error', 'duration_ms', 'confirmed_by', 'created_at'],
                'write'    => [],
                'enums'    => [],
                'required' => [],
                'unique'   => [],
                'relations' => [],
                'limits'   => [],
                'guard'    => 'readonly',
            ],

            /* ==================== BƯỚC 4 — BẢN ĐỒ GIAO DIỆN ==================== */
            'ai_ui_map' => [
                'table'    => 'vc_ai_ui_map',
                'model'    => \App\Models\AiUiMap::class,
                'kind'     => self::KIND_RECORD,
                'label'    => ['page_path'],
                'list_url' => '',
                'note'     => 'Bản đồ giao diện web (BƯỚC 4.1) — trang + nút thật, nạp vào prompt AI chat khách. '
                    . 'is_active=0 để ẩn trang khỏi prompt; source=manual thì tool quét (ai_ui_map_build) không được đè. '
                    . 'actions_json dạng {nhãn nút => href}.',
                'read'     => ['id', 'page_path', 'requires_login', 'title', 'actions_json', 'view_path',
                    'source', 'is_active', 'created_at', 'updated_at'],
                'write'    => ['page_path', 'requires_login', 'title', 'actions_json', 'view_path', 'source', 'is_active'],
                'enums'    => ['source' => ['const', 'scanner', 'manual']],
                'required' => ['page_path', 'title'],
                'unique'   => ['page_path'],
                'relations' => [],
                'limits'   => ['page_path' => 191, 'title' => 191, 'view_path' => 191, 'source' => 20],
                'defaults' => ['is_active' => 1, 'source' => 'manual'],
                'json'     => ['actions_json'],
                'numeric'  => ['requires_login', 'is_active'],
                'guard'    => 'ai_ui_map_delete',
            ],
        ];
    }

    /** @return array<string,array<string,mixed>>|null */
    public static function get(string $entity): ?array
    {
        return self::all()[$entity] ?? null;
    }

    public static function has(string $entity): bool
    {
        return isset(self::all()[$entity]);
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /** @return string[] tên entity ghi được (write không rỗng, là record) */
    public static function writableNames(): array
    {
        $out = [];
        foreach (self::all() as $name => $def) {
            if ($def['kind'] === self::KIND_RECORD && ($def['guard'] ?? '') !== 'readonly' && !empty($def['write'])) {
                $out[] = $name;
            }
        }
        sort($out);
        return $out;
    }

    /** @return string[] tên entity đọc được */
    public static function readableNames(): array
    {
        $out = array_keys(self::all());
        sort($out);
        return $out;
    }

    public static function pk(string $entity): string
    {
        $def = self::get($entity);
        return $def['pk'] ?? 'id';
    }

    public static function isWritable(string $entity): bool
    {
        $def = self::get($entity);
        return $def !== null
            && $def['kind'] === self::KIND_RECORD
            && ($def['guard'] ?? '') !== 'readonly'
            && !empty($def['write']);
    }

    public static function isSecret(string $entity, string $column): bool
    {
        $def = self::get($entity);
        if ($def === null) {
            return true;
        }
        foreach ((array) ($def['secret'] ?? []) as $s) {
            if (strcasecmp($s, $column) === 0) {
                return true;
            }
        }
        return self::secretColumn($column);
    }

    /** Cột bị cấm theo mẫu secret (giữ nguyên tinh thần PageEditTools::DENY_RE). */
    public static function secretColumn(string $column): bool
    {
        return (bool) preg_match(self::SECRET_RE, $column);
    }

    /**
     * Cột có được phép dùng cho thao tác này không?
     * $forWrite=true thì chặn thêm cột blocked_on_write (phải đi qua action_run).
     */
    public static function allowsColumn(string $entity, string $column, bool $forWrite = false): bool
    {
        $def = self::get($entity);
        if ($def === null) {
            return false;
        }
        $list = $forWrite ? (array) ($def['write'] ?? []) : (array) ($def['read'] ?? []);
        if (!in_array($column, $list, true)) {
            return false;
        }
        if ($forWrite && in_array($column, (array) ($def['blocked_on_write'] ?? []), true)) {
            return false;
        }
        return true;
    }

    /** @return string[] giá trị hợp pháp của 1 cột (rỗng = không phải enum) */
    public static function enumValues(string $entity, string $column): array
    {
        $def = self::get($entity);
        return (array) ($def['enums'][$column] ?? []);
    }

    public static function limitFor(string $entity, string $column): int
    {
        $def = self::get($entity);
        return (int) ($def['limits'][$column] ?? 60000);
    }

    /** @return array<string,array{0:string,1:string}> cột => [entity tham chiếu, khóa] */
    public static function relations(string $entity): array
    {
        $def = self::get($entity);
        return (array) ($def['relations'] ?? []);
    }

    public static function label(string $entity, array $row): string
    {
        $def = self::get($entity);
        if ($def === null) {
            return '#' . ($row['id'] ?? '?');
        }
        $parts = [];
        foreach ((array) $def['label'] as $col) {
            if (isset($row[$col]) && is_scalar($row[$col]) && trim((string) $row[$col]) !== '') {
                $parts[] = (string) $row[$col];
            }
        }
        $label = $parts !== [] ? implode(' · ', $parts) : ('#' . ($row[$def['pk'] ?? 'id'] ?? '?'));
        return mb_substr($label, 0, 120);
    }

    /** URL trang admin của entity (có thể kèm id để deep-link). */
    public static function adminUrl(string $entity, ?int $id = null): string
    {
        $def = self::get($entity);
        if ($def === null || empty($def['list_url'])) {
            return '';
        }
        $url = (string) $def['list_url'];
        if ($id === null || $id <= 0) {
            return $url;
        }
        // Các trang dùng ?id= cho chi tiết; plan/coupon/server-group chỉ có form edit.
        return $url . '?id=' . $id;
    }

    /**
     * Blocks tạo mới tự sinh (uuid, slug, order_code, ref_code, password_hash).
     * @return string[]
     */
    public static function generators(string $entity): array
    {
        $def = self::get($entity);
        return array_keys((array) ($def['generators'] ?? []));
    }

    /** @return string[] */
    public static function uniqueColumns(string $entity): array
    {
        $def = self::get($entity);
        return (array) ($def['unique'] ?? []);
    }

    /**
     * Bản mô tả siêu cô đặc đưa vào system prompt (mục 2.7).
     * Chỉ liệt kê entity ghi được + cột ghi được + enum, để model không tự chế.
     */
    public static function promptDigest(): string
    {
        $lines = [];
        foreach (self::writableNames() as $name) {
            $def = self::get($name);
            $cols = implode(',', (array) $def['write']);
            // Chỉ bắt buộc những cột model THỰC SỰ truyền được; cột NOT NULL do DB/tool
            // tự sinh (uuid, order_code...) hoặc do nghiệp vụ riêng — không hiện ở đây.
            $reqArr = array_values(array_intersect((array) $def['required'], (array) $def['write']));
            $req = $reqArr !== [] ? ' bắt buộc:' . implode(',', $reqArr) : '';
            $enum = '';
            foreach ((array) $def['enums'] as $col => $vals) {
                if (in_array($col, (array) $def['write'], true)) {
                    $enum .= ' ' . $col . '∈[' . implode('|', $vals) . ']';
                }
            }
            $lines[] = "- {$name} ({$def['table']}): write={$cols}{$req}" . ($enum !== '' ? ' ;' . $enum : '');
        }
        $ro = [];
        foreach (self::all() as $name => $def) {
            if (!in_array($name, self::writableNames(), true)) {
                $ro[] = $name;
            }
        }
        sort($ro);
        $lines[] = '- chỉ đọc: ' . implode(', ', $ro);
        return implode("\n", $lines);
    }
}
