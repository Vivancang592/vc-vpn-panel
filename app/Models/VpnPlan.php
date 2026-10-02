<?php

namespace App\Models;

class VpnPlan extends BaseModel
{
    protected string $table = 'vc_vpn_plans';

    /**
     * Giữ một suất của gói có tồn kho hữu hạn.
     *
     * @return array{available: bool, reserved: bool}
     */
    public function reserveForPurchase(int $planId): array
    {
        if ($planId <= 0) {
            return ['available' => false, 'reserved' => false];
        }

        $stmt = self::$db->prepare(
            "UPDATE `{$this->table}`
             SET `stock_quantity` = `stock_quantity` - 1
             WHERE `id` = :id
               AND `status` = 'active'
               AND `stock_quantity` IS NOT NULL
               AND `stock_quantity` > 0"
        );
        $stmt->execute(['id' => $planId]);

        if ($stmt->rowCount() === 1) {
            return ['available' => true, 'reserved' => true];
        }

        $lock = self::$db->inTransaction() ? ' FOR UPDATE' : '';
        $stmt = self::$db->prepare(
            "SELECT `status`, `stock_quantity`
             FROM `{$this->table}`
             WHERE `id` = :id
             LIMIT 1{$lock}"
        );
        $stmt->execute(['id' => $planId]);
        $plan = $stmt->fetch();

        return [
            'available' => is_array($plan)
                && ($plan['status'] ?? '') === 'active'
                && ($plan['stock_quantity'] ?? null) === null,
            'reserved' => false,
        ];
    }

    public function getAllActive(): array
    {
        $stmt = self::$db->query("SELECT * FROM `{$this->table}` WHERE `status` = 'active' ORDER BY `price` ASC");
        $plans = $stmt->fetchAll() ?: [];
        $groupStmt = self::$db->query("SELECT `id`, `name` FROM `vc_server_groups` WHERE `status` = 'active'");
        $groups = $groupStmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        foreach ($plans as &$plan) {
            $ids = json_decode($plan['group_id'] ?? '[]', true);
            $ids = is_array($ids) ? $ids : (!empty($plan['group_id']) ? [(int) $plan['group_id']] : []);
            $plan['group_ids'] = $ids;
            $plan['group_names'] = array_values(array_filter(array_map(fn ($id) => $groups[$id] ?? null, $ids)));
        }

        return $plans;
    }

    public function getAllWithGroup(): array
    {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY `id` DESC";
        $stmt = self::$db->query($sql);
        $plans = $stmt->fetchAll() ?: [];

        if (empty($plans)) {
            return [];
        }

        // Lấy tất cả nhóm máy chủ để ghép tên
        $groupStmt = self::$db->query("SELECT `id`, `name` FROM `vc_server_groups`");
        $groupsList = $groupStmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        foreach ($plans as &$plan) {
            $groupIds = json_decode($plan['group_id'] ?? '[]', true);
            if (!is_array($groupIds)) {
                $groupIds = !empty($plan['group_id']) ? [(int)$plan['group_id']] : [];
            }

            $names = [];
            foreach ($groupIds as $gId) {
                if (isset($groupsList[$gId])) {
                    $names[] = $groupsList[$gId];
                }
            }

            $plan['group_name'] = !empty($names) ? implode(', ', $names) : 'Chưa chọn nhóm';
            $plan['group_ids']  = $groupIds;
        }

        return $plans;
    }

    public function getAllActiveWithGroup(): array
    {
        $stmt = self::$db->query("SELECT * FROM `{$this->table}` WHERE `status` = 'active' ORDER BY `price` ASC");
        $plans = $stmt->fetchAll() ?: [];
        $groupStmt = self::$db->query("SELECT `id`, `name` FROM `vc_server_groups` WHERE `status` = 'active'");
        $groups = $groupStmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        foreach ($plans as &$plan) {
            $ids = json_decode($plan['group_id'] ?? '[]', true);
            $ids = is_array($ids) ? $ids : (!empty($plan['group_id']) ? [(int) $plan['group_id']] : []);
            $plan['group_ids'] = $ids;
            $plan['group_names'] = array_filter(array_map(fn ($id) => $groups[$id] ?? null, $ids));
        }

        return $plans;
    }
}