<?php

namespace App\Models;

class Server extends BaseModel
{
    protected string $table = 'vc_servers';

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT s.*, g.name as group_name 
            FROM `{$this->table}` s 
            LEFT JOIN `vc_server_groups` g ON s.group_id = g.id 
            WHERE s.id = :id 
            LIMIT 1
        ";
        $stmt = self::$db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Tìm máy chủ VPS bằng API Token gửi từ Node (Chặn Token rỗng để tránh khớp nhầm)
     */
    public function findByToken(string $token): ?array
    {
        $token = trim($token);
        if (empty($token)) {
            return null;
        }

        $sql = "
            SELECT * FROM `{$this->table}` 
            WHERE `api_token` = :token 
            LIMIT 1
        ";
        $stmt = self::$db->prepare($sql);
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Lấy danh sách máy chủ đang hoạt động (active) thuộc một Nhóm máy chủ (group_id)
     */
    public function getActiveByGroupId(int $groupId): array
    {
        $sql = "
            SELECT * FROM `{$this->table}` 
            WHERE `group_id` = :group_id AND `status` = 'active'
            ORDER BY `id` ASC
        ";
        $stmt = self::$db->prepare($sql);
        $stmt->execute(['group_id' => $groupId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getAll(): array
    {
        $sql = "
            SELECT s.*, g.name as group_name 
            FROM `{$this->table}` s 
            LEFT JOIN `vc_server_groups` g ON s.group_id = g.id 
            ORDER BY s.id DESC
        ";
        $stmt = self::$db->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): bool
    {
        $stmt = self::$db->prepare("
            INSERT INTO `{$this->table}` 
            (`group_id`, `name`, `country_code`, `location`, `ip_address`, `api_port`, `api_token`, `status`, `created_at`)
            VALUES 
            (:group_id, :name, :country_code, :location, :ip_address, :api_port, :api_token, :status, NOW())
        ");
        
        return $stmt->execute([
            'group_id'     => $data['group_id'],
            'name'         => $data['name'],
            'country_code' => $data['country_code'],
            'location'     => $data['location'],
            'ip_address'   => $data['ip_address'],
            'api_port'     => $data['api_port'] ?? 80,
            'api_token'    => $data['api_token'] ?? null,
            'status'       => $data['status'] ?? 'active',
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        if (empty($fields)) return false;

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $fields) . ", `updated_at` = NOW() WHERE `id` = :id";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Cập nhật thời điểm checkin gần nhất của máy chủ VPS
     */
    public function updateLastCheckin(int $id): bool
    {
        $sql = "UPDATE `{$this->table}` SET `last_check_in` = NOW(), `updated_at` = NOW() WHERE `id` = :id";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }
}