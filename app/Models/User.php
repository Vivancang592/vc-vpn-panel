<?php

namespace App\Models;

class User extends BaseModel
{
    protected string $table = 'vc_users';

    public function findById(int $id): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Tìm kiếm người dùng theo Google ID
     */
    public function findByGoogleId(string $googleId): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `google_id` = :google_id LIMIT 1");
        $stmt->execute(['google_id' => $googleId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Tìm kiếm người dùng phân biệt nghiêm ngặt chữ hoa/chữ thường (BINARY)
     */
    public function findByUsernameOrEmailStrict(string $identifier): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE BINARY `username` = :username OR BINARY `email` = :email LIMIT 1");
        $stmt->execute([
            'username' => $identifier,
            'email'    => $identifier
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByUsernameOrEmail(string $identifier): ?array
    {
        return $this->findByUsernameOrEmailStrict($identifier);
    }

    public function findByRefCode(string $refCode): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `ref_code` = :ref_code LIMIT 1");
        $stmt->execute(['ref_code' => $refCode]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function countAll(): int
    {
        $stmt = self::$db->query("SELECT COUNT(*) as total FROM `{$this->table}`");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    public function countToday(): int
    {
        $stmt = self::$db->query("SELECT COUNT(*) as total FROM `{$this->table}` WHERE DATE(`created_at`) = CURDATE()");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    public function getAll(string $search = '', string $role = '', string $status = ''): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (`username` LIKE :search OR `email` LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if (!empty($role)) {
            $sql .= " AND `role` = :role";
            $params['role'] = $role;
        }

        if (!empty($status)) {
            $sql .= " AND `status` = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY `id` DESC";

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        $stmt = self::$db->prepare("
            INSERT INTO `{$this->table}` (`username`, `email`, `google_id`, `password_hash`, `role`, `status`, `balance`, `commission_balance`, `ref_code`, `referred_by`, `created_by`, `register_ip`, `created_at`)
            VALUES (:username, :email, :google_id, :password_hash, :role, :status, :balance, :commission_balance, :ref_code, :referred_by, :created_by, :register_ip, NOW())
        ");
        return $stmt->execute([
            'username'           => $data['username'],
            'email'              => $data['email'],
            'google_id'          => $data['google_id'] ?? null,
            'password_hash'      => $data['password_hash'] ?? null,
            'role'               => $data['role'] ?? 'user',
            'status'             => $data['status'] ?? 'active',
            'balance'            => $data['balance'] ?? 0.00,
            'commission_balance' => $data['commission_balance'] ?? 0.00,
            'ref_code'           => $data['ref_code'] ?? null,
            'referred_by'        => $data['referred_by'] ?? null,
            'created_by'         => $data['created_by'] ?? null,
            'register_ip'        => $data['register_ip'] ?? null,
        ]);
    }

    /**
     * Cập nhật thông tin User với Whitelist tên cột chống SQL Injection
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'username', 'email', 'google_id', 'password_hash', 'role', 'status', 
            'balance', 'commission_balance', 'ref_code', 'referred_by', 
            'created_by', 'register_ip', 'last_login_ip', 'last_login_time'
        ];

        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields, true)) {
                $fields[] = "`{$key}` = :{$key}";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $fields) . ", `updated_at` = NOW() WHERE `id` = :id";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }
}