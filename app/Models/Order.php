<?php

namespace App\Models;

class Order extends BaseModel
{
    protected string $table = 'vc_orders';

    /**
     * Tìm đơn hàng theo mã đơn hàng (order_code)
     */
    public function findByOrderCode(string $orderCode): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `order_code` = :order_code LIMIT 1");
        $stmt->execute(['order_code' => $orderCode]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Tìm đơn hàng pending theo đúng số tiền (áp dụng cho WeChat Pay khớp tiền lẻ)
     */
    public function findByPendingAmount(float $amount, int $minutes = 15): ?array
    {
        $stmt = self::$db->prepare("
            SELECT * FROM `{$this->table}` 
            WHERE `total_amount` = :amount 
              AND `payment_status` = 'pending' 
              AND `created_at` >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            ORDER BY `id` DESC 
            LIMIT 1
        ");
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':minutes', $minutes, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Lấy toàn bộ danh sách đơn hàng kèm thông tin user, gói cước và mã giảm giá
     */
    public function allWithDetails(?int $userId = null): array
    {
        $sql = "
            SELECT o.*, 
                   u.username, u.email, 
                   p.name AS plan_name, p.code AS plan_code, 
                   c.code AS coupon_code
            FROM `{$this->table}` o
            LEFT JOIN `vc_users` u ON o.user_id = u.id
            LEFT JOIN `vc_vpn_plans` p ON o.plan_id = p.id
            LEFT JOIN `vc_coupons` c ON o.coupon_id = c.id
        ";

        $params = [];
        if ($userId !== null && $userId > 0) {
            $sql .= " WHERE o.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " ORDER BY o.id DESC";

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết 1 đơn hàng theo ID kèm thông tin liên quan
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = self::$db->prepare("
            SELECT o.*, 
                   u.username, u.email, 
                   p.name AS plan_name, p.code AS plan_code, 
                   c.code AS coupon_code
            FROM `{$this->table}` o
            LEFT JOIN `vc_users` u ON o.user_id = u.id
            LEFT JOIN `vc_vpn_plans` p ON o.plan_id = p.id
            LEFT JOIN `vc_coupons` c ON o.coupon_id = c.id
            WHERE o.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByUserId(int $userId): array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `user_id` = :user_id ORDER BY `id` DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy danh sách doanh thu theo từng ngày trong tháng/năm chỉ định
     */
    public function getDailyRevenueByMonth(int $year, int $month): array
    {
        $stmt = self::$db->prepare("
            SELECT DAY(created_at) as day_num, SUM(total_amount) as total
            FROM `{$this->table}`
            WHERE payment_status = 'completed'
              AND YEAR(created_at) = :year
              AND MONTH(created_at) = :month
            GROUP BY DAY(created_at)
        ");
        $stmt->execute(['year' => $year, 'month' => $month]);
        $results = $stmt->fetchAll() ?: [];

        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $dailyData = array_fill(1, $daysInMonth, 0.0);

        foreach ($results as $row) {
            $day = (int)$row['day_num'];
            if ($day <= $daysInMonth) {
                $dailyData[$day] = (float)$row['total'];
            }
        }

        return array_values($dailyData);
    }

    /**
     * Lấy tổng doanh thu của một tháng cụ thể
     */
    public function getMonthlyRevenue(int $year, int $month): float
    {
        $stmt = self::$db->prepare("
            SELECT SUM(total_amount) as total
            FROM `{$this->table}`
            WHERE payment_status = 'completed'
              AND YEAR(created_at) = :year
              AND MONTH(created_at) = :month
        ");
        $stmt->execute(['year' => $year, 'month' => $month]);
        $result = $stmt->fetch();

        return (float)($result['total'] ?? 0.0);
    }

    /**
     * Lấy danh sách đơn hàng gần đây
     */
    public function getRecentOrders(int $limit = 10): array
    {
        $stmt = self::$db->prepare("
            SELECT o.*, u.username
            FROM `{$this->table}` o
            LEFT JOIN `vc_users` u ON o.user_id = u.id
            ORDER BY o.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}