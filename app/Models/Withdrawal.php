<?php

namespace App\Models;

class Withdrawal extends BaseModel
{
    protected string $table = 'vc_withdrawals';

    public function getByUserId(int $userId): array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `user_id` = :user_id ORDER BY `id` DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getPendingTotalByUserId(int $userId): float
    {
        $stmt = self::$db->prepare("SELECT COALESCE(SUM(`amount`), 0) FROM `{$this->table}` WHERE `user_id` = :user_id AND `status` = 'pending'");
        $stmt->execute(['user_id' => $userId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Lấy danh sách yêu cầu rút tiền kèm thông tin thành viên
     */
    public function allWithDetails(): array
    {
        $stmt = self::$db->prepare("
            SELECT w.*, 
                   u.username, u.email, u.commission_balance
            FROM `{$this->table}` w
            LEFT JOIN `vc_users` u ON w.user_id = u.id
            ORDER BY w.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết yêu cầu rút tiền theo ID kèm thông tin thành viên
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = self::$db->prepare("
            SELECT w.*, 
                   u.username, u.email, u.commission_balance, u.full_name
            FROM `{$this->table}` w
            LEFT JOIN `vc_users` u ON w.user_id = u.id
            WHERE w.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Cập nhật trạng thái yêu cầu rút tiền
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return false;
        }

        self::$db->beginTransaction();

        try {
            $stmt = self::$db->prepare("SELECT `id`, `user_id`, `amount`, `status` FROM `{$this->table}` WHERE `id` = :id LIMIT 1 FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $withdrawal = $stmt->fetch();

            if (!$withdrawal || ($withdrawal['status'] ?? '') !== 'pending') {
                self::$db->rollBack();
                return false;
            }

            if ($status === 'approved') {
                $deductStmt = self::$db->prepare(
                    "UPDATE `vc_users`
                     SET `commission_balance` = `commission_balance` - :amount
                     WHERE `id` = :user_id AND `commission_balance` >= :amount"
                );
                $deductStmt->execute([
                    'amount' => (float) ($withdrawal['amount'] ?? 0),
                    'user_id' => (int) ($withdrawal['user_id'] ?? 0)
                ]);

                if ($deductStmt->rowCount() !== 1) {
                    self::$db->rollBack();
                    return false;
                }
            }

            $updateStmt = self::$db->prepare("UPDATE `{$this->table}` SET `status` = :status WHERE `id` = :id");
            $updated = $updateStmt->execute([
                'status' => $status,
                'id'     => $id
            ]);

            if (!$updated) {
                self::$db->rollBack();
                return false;
            }

            self::$db->commit();
            return true;
        } catch (\Throwable $exception) {
            if (self::$db->inTransaction()) {
                self::$db->rollBack();
            }
            return false;
        }
    }
}