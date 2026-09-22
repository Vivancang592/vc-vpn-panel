<?php

namespace App\Models;

class Subscription extends BaseModel
{
    protected string $table = 'vc_subscriptions';

    /**
     * Lấy danh sách toàn bộ đăng ký kèm thông tin user và gói cước (có hỗ trợ lọc theo user_id)
     */
    public function allWithDetails(?int $userId = null): array
    {
        $sql = "
            SELECT s.*, 
                   u.username, u.email, 
                   p.name AS plan_name, p.code AS plan_code, COALESCE(s.max_devices, p.max_devices, 1) AS device_limit
            FROM `{$this->table}` s
            LEFT JOIN `vc_users` u ON s.user_id = u.id
            LEFT JOIN `vc_vpn_plans` p ON s.plan_id = p.id
        ";

        $params = [];
        if ($userId !== null && $userId > 0) {
            $sql .= " WHERE s.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " ORDER BY s.id DESC";

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết gói đăng ký theo ID kèm thông tin liên quan và giới hạn thiết bị
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = self::$db->prepare("
            SELECT s.*, 
                   u.username, u.email, 
                   p.name AS plan_name, p.code AS plan_code, COALESCE(s.max_devices, p.max_devices, 1) AS device_limit, p.group_id,
                   o.order_code
            FROM `{$this->table}` s
            LEFT JOIN `vc_users` u ON s.user_id = u.id
            LEFT JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            LEFT JOIN `vc_orders` o ON s.order_id = o.id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByUserId(int $userId): array
    {
        $stmt = self::$db->prepare("
            SELECT s.*, p.name AS plan_name, p.code AS plan_code, COALESCE(s.max_devices, p.max_devices, 1) AS device_limit
            FROM `{$this->table}` s
            LEFT JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            WHERE s.user_id = :user_id
            ORDER BY s.id DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Tìm kiếm gói đăng ký bằng UUID duy nhất
     */
    public function findByUuid(string $uuid): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `uuid` = :uuid LIMIT 1");
        $stmt->execute(['uuid' => $uuid]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Lấy danh sách tài khoản đang kích hoạt để gửi về cho Node VPS đồng bộ
     */
    public function getAllActiveUsers(): array
    {
        $stmt = self::$db->prepare("
            SELECT id, uuid, upload, download, transfer_enable 
            FROM `{$this->table}` 
            WHERE `status` = 'active' AND `end_date` > NOW()
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Cộng dồn dung lượng Upload/Download, cập nhật IP sử dụng và tự động kiểm tra ngắt kết nối tức thì nếu vượt hạn mức
     */
    public function addTrafficById(int $id, int $u, int $d, ?string $lastIp = null, ?int $ipCount = null): bool
    {
        $extraSql = "";
        $params = [
            'u'  => $u,
            'd'  => $d,
            'id' => $id
        ];

        if ($lastIp !== null) {
            $extraSql .= ", `last_used_ip` = :last_ip";
            $params['last_ip'] = $lastIp;
        }

        if ($ipCount !== null) {
            $extraSql .= ", `online_devices` = :ip_count";
            $params['ip_count'] = $ipCount;
        }

        $sql = "
            UPDATE `{$this->table}` 
            SET `upload` = `upload` + :u, 
                `download` = `download` + :d 
                {$extraSql},
                `updated_at` = NOW() 
            WHERE `id` = :id
        ";

        $stmt = self::$db->prepare($sql);
        $executed = $stmt->execute($params);

        if ($executed) {
            $this->checkAndSuspendRealtimeById($id);
        }

        return $executed;
    }

    /**
     * Cộng dồn dung lượng Upload/Download theo UUID và tự động kiểm tra ngắt kết nối tức thì nếu vượt hạn mức
     */
    public function addTrafficByUuid(string $uuid, int $u, int $d, ?string $lastIp = null, ?int $ipCount = null): bool
    {
        $extraSql = "";
        $params = [
            'u'    => $u,
            'd'    => $d,
            'uuid' => $uuid
        ];

        if ($lastIp !== null) {
            $extraSql .= ", `last_used_ip` = :last_ip";
            $params['last_ip'] = $lastIp;
        }

        if ($ipCount !== null) {
            $extraSql .= ", `online_devices` = :ip_count";
            $params['ip_count'] = $ipCount;
        }

        $sql = "
            UPDATE `{$this->table}` 
            SET `upload` = `upload` + :u, 
                `download` = `download` + :d 
                {$extraSql},
                `updated_at` = NOW() 
            WHERE `uuid` = :uuid
        ";

        $stmt = self::$db->prepare($sql);
        $executed = $stmt->execute($params);

        if ($executed) {
            $sub = $this->findByUuid($uuid);
            if ($sub && isset($sub['id'])) {
                $this->checkAndSuspendRealtimeById((int)$sub['id']);
            }
        }

        return $executed;
    }

    /**
     * Kiểm tra và ngắt kết nối Real-time ngay khi phát hiện tài khoản vượt hạn mức dung lượng
     */
    private function checkAndSuspendRealtimeById(int $id): void
    {
        $sql = "
            SELECT s.id, s.upload, s.download, s.transfer_enable, s.status, s.user_id, p.group_id, u.email, u.username AS user_name, p.name AS plan_name
            FROM `{$this->table}` s
            INNER JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            INNER JOIN `vc_users` u ON s.user_id = u.id
            WHERE s.id = :id AND s.status = 'active'
            LIMIT 1
        ";
        $stmt = self::$db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $sub = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($sub && (int)$sub['transfer_enable'] > 0) {
            $totalUsed = (int)$sub['upload'] + (int)$sub['download'];
            if ($totalUsed >= (int)$sub['transfer_enable']) {
                // 1. Cập nhật trạng thái thành suspended trong DB
                $this->update($sub['id'], [
                    'status'     => 'suspended',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // 2. Gửi Task toggle_user khóa kết nối tức thì xuống VPS thuộc group_id
                if (!empty($sub['group_id']) && class_exists('App\Models\NodeTask')) {
                    $groupIds = json_decode($sub['group_id'] ?? '[]', true);
                    if (!is_array($groupIds)) {
                        $groupIds = !empty($sub['group_id']) ? [(int)$sub['group_id']] : [];
                    }
                    $nodeTaskModel = new \App\Models\NodeTask();
                    $nodeTaskModel->createTasksForGroup($groupIds, 'toggle_user', [
                        'username' => 'sub_' . $sub['id'],
                        'status'   => 'disabled'
                    ]);
                }

                // 3. Gửi Email thông báo hết dung lượng nếu MailService có sẵn
                if (!empty($sub['email']) && class_exists('App\Services\MailService')) {
                    $mailService = new \App\Services\MailService();
                    $mailService->send($sub['email'], 'Tài khoản VPN của bạn đã hết dung lượng', 'subscriptions.data-exceeded', [
                        'username'  => $sub['user_name'],
                        'plan_name' => $sub['plan_name']
                    ]);
                }
            }
        }
    }

    /**
     * Giữ hàm cũ để đảm bảo tính tương thích
     */
    public function addTraffic(string $uuid, int $u, int $d): bool
    {
        return $this->addTrafficByUuid($uuid, $u, $d);
    }

    /**
     * Tìm kiếm gói đăng ký theo ID đơn hàng (order_id)
     */
    public function findByOrderId(int $orderId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `order_id` = :order_id LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Lấy danh sách gói đăng ký đang active thuộc Nhóm máy chủ (group_id)
     */
    public function getActiveByGroupId(int $groupId): array
    {
        $sql = "
            SELECT s.id, s.uuid, s.transfer_enable, s.end_date 
            FROM `{$this->table}` s
            INNER JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            WHERE (JSON_CONTAINS(p.group_id, CAST(:group_id AS JSON)) OR p.group_id = :group_id_str)
              AND s.status = 'active' 
              AND s.end_date > NOW()
        ";
        $stmt = self::$db->prepare($sql);
        $stmt->execute([
            'group_id'     => $groupId,
            'group_id_str' => (string)$groupId
        ]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Đếm số lượng gói đăng ký đang liên kết với một plan_id
     */
    public function countByPlanId(int $planId): int
    {
        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `plan_id` = :plan_id");
        $stmt->execute(['plan_id' => $planId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Đếm tổng số gói đăng ký đang hoạt động (Status Active & Chưa hết hạn)
     */
    public function countActive(): int
    {
        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = 'active' AND `end_date` > NOW()");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}