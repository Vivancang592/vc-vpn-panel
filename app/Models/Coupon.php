<?php

namespace App\Models;

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
}