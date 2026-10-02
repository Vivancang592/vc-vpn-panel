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
     * Tìm đơn hàng theo nội dung chuyển khoản (transfer_content)
     */
    public function findByTransferContent(string $transferContent): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `transfer_content` = :transfer_content LIMIT 1");
        $stmt->execute(['transfer_content' => $transferContent]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Tìm đơn hàng pending theo đúng số tiền
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
                     creator.username AS creator_username,
                     approver.username AS approver_username,
                   p.name AS plan_name, p.code AS plan_code, 
                   c.code AS coupon_code
            FROM `{$this->table}` o
            LEFT JOIN `vc_users` u ON o.user_id = u.id
                 LEFT JOIN `vc_users` creator ON o.created_by = creator.id
                 LEFT JOIN `vc_users` approver ON o.approved_by = approver.id
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
                     creator.username AS creator_username,
                     approver.username AS approver_username,
                   p.name AS plan_name, p.code AS plan_code, p.price AS plan_price,
                   c.code AS coupon_code
            FROM `{$this->table}` o
            LEFT JOIN `vc_users` u ON o.user_id = u.id
                 LEFT JOIN `vc_users` creator ON o.created_by = creator.id
                 LEFT JOIN `vc_users` approver ON o.approved_by = approver.id
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

    public function findPendingByUserId(int $userId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `user_id` = :user_id AND `payment_status` = 'pending' ORDER BY `id` DESC LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function cancelPendingForUser(int $orderId, int $userId): bool
    {
        return $this->cancelPendingAndReleaseStock($orderId, $userId, 'cancelled');
    }

    /**
     * Đóng đơn chờ và hoàn lại đúng một suất tồn kho đã giữ chỗ.
     */
    public function cancelPendingAndReleaseStock(int $orderId, ?int $userId = null, string $status = 'cancelled'): bool
    {
        if ($orderId <= 0 || !in_array($status, ['cancelled', 'failed'], true)) {
            return false;
        }

        self::beginTransaction();

        try {
            $sql = "SELECT `plan_id`, `stock_reserved`
                    FROM `{$this->table}`
                    WHERE `id` = :id
                      AND `payment_status` = 'pending'";
            $params = ['id' => $orderId];
            if ($userId !== null) {
                $sql .= ' AND `user_id` = :user_id';
                $params['user_id'] = $userId;
            }
            $sql .= ' FOR UPDATE';

            $stmt = self::$db->prepare($sql);
            $stmt->execute($params);
            $order = $stmt->fetch();
            if (!is_array($order)) {
                self::rollBack();
                return false;
            }

            $update = self::$db->prepare(
                "UPDATE `{$this->table}`
                 SET `payment_status` = :status, `updated_at` = NOW()
                 WHERE `id` = :id AND `payment_status` = 'pending'"
            );
            $update->execute(['status' => $status, 'id' => $orderId]);
            if ($update->rowCount() !== 1) {
                self::rollBack();
                return false;
            }

            if ((int) ($order['stock_reserved'] ?? 0) === 1 && (int) ($order['plan_id'] ?? 0) > 0) {
                $restock = self::$db->prepare(
                    'UPDATE `vc_vpn_plans`
                     SET `stock_quantity` = `stock_quantity` + 1
                     WHERE `id` = :plan_id AND `stock_quantity` IS NOT NULL'
                );
                $restock->execute(['plan_id' => (int) $order['plan_id']]);

                $clearReservation = self::$db->prepare(
                    "UPDATE `{$this->table}`
                     SET `stock_reserved` = 0
                     WHERE `id` = :id AND `stock_reserved` = 1"
                );
                $clearReservation->execute(['id' => $orderId]);
            }

            self::commit();
            return true;
        } catch (\Throwable $exception) {
            self::rollBack();
            throw $exception;
        }
    }

    public function cancelExpiredPending(int $minutes): int
    {
        $minutes = max(1, min(43200, $minutes));

        self::beginTransaction();

        try {
            $stmt = self::$db->prepare("SELECT o.`id`, o.`order_code`, o.`plan_id`, o.`stock_reserved`, u.`email` FROM `{$this->table}` o INNER JOIN `vc_users` u ON u.`id` = o.`user_id` WHERE o.`payment_status` = 'pending' AND o.`created_at` <= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE) FOR UPDATE");
            $stmt->execute();
            $expiredOrders = $stmt->fetchAll();
            $orderIds = array_column($expiredOrders, 'id');

            if (empty($orderIds)) {
                self::commit();
                return 0;
            }

            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $paymentStmt = self::$db->prepare("UPDATE `vc_payments` SET `status` = 'cancelled' WHERE `order_id` IN ({$placeholders}) AND `status` = 'pending'");
            $paymentStmt->execute($orderIds);

            $restockStmt = self::$db->prepare("UPDATE `{$this->table}` o INNER JOIN `vc_vpn_plans` p ON p.`id` = o.`plan_id` SET p.`stock_quantity` = p.`stock_quantity` + 1, o.`stock_reserved` = 0 WHERE o.`id` IN ({$placeholders}) AND o.`stock_reserved` = 1 AND p.`stock_quantity` IS NOT NULL");
            $restockStmt->execute($orderIds);

            $orderStmt = self::$db->prepare("UPDATE `{$this->table}` SET `payment_status` = 'cancelled', `updated_at` = NOW() WHERE `id` IN ({$placeholders}) AND `payment_status` = 'pending'");
            $orderStmt->execute($orderIds);
            $cancelledOrders = $orderStmt->rowCount();

            self::commit();

            if ($cancelledOrders > 0 && class_exists('App\Services\MailService')) {
                $mailService = new \App\Services\MailService();
                foreach ($expiredOrders as $order) {
                    $email = trim((string) ($order['email'] ?? ''));
                    if ($email !== '') {
                        $mailService->send($email, 'Đơn hàng đã bị hủy', 'orders.cancelled', [
                            'orderCode' => (string) ($order['order_code'] ?? ''),
                            'reason' => 'Đơn hàng đã quá thời gian chờ thanh toán.'
                        ]);
                    }
                }
            }

            return $cancelledOrders;
        } catch (\Throwable $exception) {
            self::rollBack();
            throw $exception;
        }
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