<?php

namespace App\Models;

class Payment extends BaseModel
{
    protected string $table = 'vc_payments';

    /**
     * Tìm giao dịch thanh toán / nạp tiền theo mã giao dịch (transaction_id)
     */
    public function findByTransactionId(string $transactionId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `transaction_id` = :transaction_id LIMIT 1");
        $stmt->execute(['transaction_id' => $transactionId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Tìm giao dịch thanh toán / nạp tiền theo nội dung chuyển khoản (transfer_content)
     */
    public function findByTransferContent(string $transferContent): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `transfer_content` = :transfer_content LIMIT 1");
        $stmt->execute(['transfer_content' => $transferContent]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Lấy toàn bộ danh sách thanh toán kèm thông tin người dùng và mã đơn hàng
     */
    public function allWithDetails(): array
    {
        $stmt = self::$db->prepare("
            SELECT p.*, 
                   u.username, u.email, 
                   o.order_code
            FROM `{$this->table}` p
            LEFT JOIN `vc_users` u ON p.user_id = u.id
            LEFT JOIN `vc_orders` o ON p.order_id = o.id
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết 1 giao dịch thanh toán theo ID kèm thông tin liên quan
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = self::$db->prepare("
            SELECT p.*, 
                   u.username, u.email, 
                   o.order_code
            FROM `{$this->table}` p
            LEFT JOIN `vc_users` u ON p.user_id = u.id
            LEFT JOIN `vc_orders` o ON p.order_id = o.id
            WHERE p.id = :id
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

    public function findSuccessfulByOrderId(int $orderId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `order_id` = :order_id AND `status` = 'success' LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findPendingByOrderId(int $orderId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `order_id` = :order_id AND `status` = 'pending' LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findPendingRenewalBySubscriptionId(int $subscriptionId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `subscription_id` = :subscription_id AND `type` = 'payment' AND `status` = 'pending' ORDER BY `id` DESC LIMIT 1");
        $stmt->execute(['subscription_id' => $subscriptionId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findPendingRenewalByOrderId(int $orderId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `order_id` = :order_id AND `subscription_id` IS NOT NULL AND `type` = 'payment' AND `status` = 'pending' LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function failPendingByOrderId(int $orderId): void
    {
        $stmt = self::$db->prepare("UPDATE `{$this->table}` SET `status` = 'failed' WHERE `order_id` = :order_id AND `status` = 'pending'");
        $stmt->execute(['order_id' => $orderId]);
    }

    public function failPendingForCancelledOrders(): int
    {
        $stmt = self::$db->prepare("UPDATE `{$this->table}` p INNER JOIN `vc_orders` o ON p.order_id = o.id SET p.status = 'failed' WHERE o.payment_status = 'cancelled' AND p.status = 'pending'");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function cancelPendingDeposit(int $paymentId, int $userId): bool
    {
        $stmt = self::$db->prepare("UPDATE `{$this->table}` SET `status` = 'failed' WHERE `id` = :id AND `user_id` = :user_id AND `type` = 'deposit' AND `status` = 'pending'");
        $stmt->execute(['id' => $paymentId, 'user_id' => $userId]);
        return $stmt->rowCount() === 1;
    }

    public function deletePendingOrFailed(int $paymentId): bool
    {
        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id AND `status` IN ('pending', 'failed')");
        $stmt->execute(['id' => $paymentId]);
        return $stmt->rowCount() === 1;
    }
}