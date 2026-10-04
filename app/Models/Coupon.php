<?php

namespace App\Models;

use PDO;

class Coupon extends BaseModel
{
    protected string $table = 'vc_coupons';

    public function all(): array
    {
        $stmt = self::$db->prepare("SELECT * FROM {$this->table} ORDER BY id DESC");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM {$this->table} WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): bool
    {
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = self::$db->prepare("INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})");
        return $stmt->execute(array_values($data));
    }

    public function update(int $id, array $data): bool
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $values = array_values($data);
        $values[] = $id;

        $stmt = self::$db->prepare("UPDATE {$this->table} SET {$set} WHERE id = ?");
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = self::$db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function incrementUsedCount(int $id): bool
    {
        $stmt = self::$db->prepare("UPDATE {$this->table} SET `used_count` = `used_count` + 1 WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Lấy danh sách mã giảm giá đang hoạt động và còn hạn sử dụng
     */
    public function getActiveCoupons(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'active' 
                AND (expires_at IS NULL OR expires_at >= NOW())
                AND (max_uses = 0 OR used_count < max_uses)
                ORDER BY discount_value DESC";
        $stmt = self::$db->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    // =================================================================
    // Gán mã giảm giá riêng cho user (bảng vc_coupon_users)
    // Semantics: không có bản ghi = công khai; có bản ghi = chỉ user
    // được gán mới dùng được mã đó.
    // =================================================================

    /**
     * Lấy nhiều mã theo danh sách ID (dùng cho bulk assign/unassign)
     * @param int[] $ids
     * @return array<int, array> các dòng coupon (id, code, ...)
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = self::$db->prepare("SELECT id, code FROM {$this->table} WHERE id IN ({$placeholders}) ORDER BY id DESC");
        $stmt->execute($ids);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Gán 1 coupon cho danh sách user (idempotent — bản ghi trùng bị bỏ qua)
     * @param int[] $userIds
     */
    public function assignUsers(int $couponId, array $userIds): bool
    {
        if ($couponId <= 0 || empty($userIds)) {
            return false;
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($v) => $v > 0)));
        if (empty($userIds)) {
            return false;
        }
        $stmt = self::$db->prepare(
            "INSERT IGNORE INTO `vc_coupon_users` (`coupon_id`, `user_id`, `created_at`) VALUES (?, ?, NOW())"
        );
        foreach ($userIds as $userId) {
            $stmt->execute([$couponId, $userId]);
        }
        return true;
    }

    /**
     * Gỡ gán: nếu $userId = null thì xóa TOÀN BỘ user được gán của coupon
     * (trở lại công khai), ngược lại chỉ gỡ đúng user đó.
     */
    public function removeAssignments(int $couponId, ?int $userId = null): bool
    {
        if ($couponId <= 0) {
            return false;
        }
        if ($userId !== null && $userId > 0) {
            $stmt = self::$db->prepare("DELETE FROM `vc_coupon_users` WHERE `coupon_id` = ? AND `user_id` = ?");
            return $stmt->execute([$couponId, $userId]);
        }
        $stmt = self::$db->prepare("DELETE FROM `vc_coupon_users` WHERE `coupon_id` = ?");
        return $stmt->execute([$couponId]);
    }

    /**
     * Danh sách user ID được gán 1 coupon (rỗng = công khai)
     * @return int[]
     */
    public function getAssignedUserIds(int $couponId): array
    {
        if ($couponId <= 0) {
            return [];
        }
        $stmt = self::$db->prepare("SELECT `user_id` FROM `vc_coupon_users` WHERE `coupon_id` = ? ORDER BY `user_id`");
        $stmt->execute([$couponId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Gán nhiều coupon: map coupon_id => [user_id => username] (1 query, tránh N+1)
     * @param int[] $couponIds
     * @return array<int, array<int, string>>
     */
    public function getAssignmentsForCoupons(array $couponIds): array
    {
        $couponIds = array_values(array_unique(array_filter(array_map('intval', $couponIds), static fn ($v) => $v > 0)));
        if (empty($couponIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($couponIds), '?'));
        $stmt = self::$db->prepare(
            "SELECT cu.`coupon_id`, cu.`user_id`, COALESCE(u.`username`, CONCAT('#', cu.`user_id`)) AS `username`
             FROM `vc_coupon_users` cu
             LEFT JOIN `vc_users` u ON u.`id` = cu.`user_id`
             WHERE cu.`coupon_id` IN ({$placeholders})
             ORDER BY cu.`user_id`"
        );
        $stmt->execute($couponIds);
        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(int) $row['coupon_id']][(int) $row['user_id']] = (string) $row['username'];
        }
        return $map;
    }

    /**
     * Kiểm tra user có được dùng coupon không.
     * Coupon chưa gán ai → mọi user đều dùng được.
     * Coupon đã gán → chỉ user nằm trong danh sách được gán.
     */
    public function isAllowedForUser(array $coupon, int $userId): bool
    {
        $couponId = (int) ($coupon['id'] ?? 0);
        if ($couponId <= 0) {
            return false;
        }
        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `vc_coupon_users` WHERE `coupon_id` = ?");
        $stmt->execute([$couponId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return true;
        }
        if ($userId <= 0) {
            return false;
        }
        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `vc_coupon_users` WHERE `coupon_id` = ? AND `user_id` = ?");
        $stmt->execute([$couponId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}