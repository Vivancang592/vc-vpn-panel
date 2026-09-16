<?php

namespace App\Models;

class NodeTask extends BaseModel
{
    protected string $table = 'vc_node_tasks';

    /**
     * Lấy danh sách các task đang chờ xử lý (pending) theo server_id
     */
    public function getPendingTasks(int $serverId): array
    {
        $sql = "
            SELECT id, action, payload 
            FROM `{$this->table}` 
            WHERE `server_id` = :server_id AND `status` = 'pending' 
            ORDER BY `id` ASC
        ";
        $stmt = self::$db->prepare($sql);
        $stmt->execute(['server_id' => $serverId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Cập nhật trạng thái của Task sau khi VPS xử lý xong
     */
    public function updateStatus(int $taskId, string $status, ?string $errorMsg = null): bool
    {
        $sql = "
            UPDATE `{$this->table}` 
            SET `status` = :status, 
                `attempts` = `attempts` + 1, 
                `updated_at` = NOW() 
            WHERE `id` = :id
        ";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute([
            'status' => $status,
            'id'     => (int)$taskId
        ]);
    }

    /**
     * Khởi tạo task mới để gửi xuống máy chủ VPS (Chuẩn hóa tham số mảng $data)
     */
    public function create(array $data): bool
    {
        $serverId = (int)($data['server_id'] ?? 0);
        $action   = trim($data['action'] ?? '');
        $payload  = $data['payload'] ?? [];

        if ($serverId <= 0 || empty($action)) {
            return false;
        }

        $sql = "
            INSERT INTO `{$this->table}` (`server_id`, `action`, `payload`, `status`, `created_at`) 
            VALUES (:server_id, :action, :payload, 'pending', NOW())
        ";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute([
            'server_id' => $serverId,
            'action'    => $action,
            'payload'   => is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload
        ]);
    }

    /**
     * Tạo Task đồng bộ cho tất cả các máy chủ VPS đang hoạt động thuộc nhóm hoặc danh sách nhóm máy chủ (group_id)
     */
    public function createTasksForGroup(int|array $groupId, string $action, array $payload): void
    {
        if (empty($groupId) || empty($action)) {
            return;
        }

        if (is_array($groupId)) {
            $groupIds = array_map('intval', array_filter($groupId));
        } else {
            $groupIds = [(int)$groupId];
        }

        $groupIds = array_filter($groupIds, fn($id) => $id > 0);
        if (empty($groupIds)) {
            return;
        }

        $inClause = implode(',', array_fill(0, count($groupIds), '?'));
        $stmt = self::$db->prepare("
            SELECT `id` FROM `vc_servers` 
            WHERE `group_id` IN ({$inClause}) AND `status` = 'active'
        ");
        $stmt->execute(array_values($groupIds));
        $servers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($servers as $server) {
            $this->create([
                'server_id' => (int)$server['id'],
                'action'    => $action,
                'payload'   => $payload
            ]);
        }
    }
}