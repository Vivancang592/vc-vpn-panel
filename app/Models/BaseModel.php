<?php

namespace App\Models;

use PDO;
use PDOException;

abstract class BaseModel
{
    protected static ?PDO $db = null;
    protected string $table = '';

    public function __construct()
    {
        if (self::$db === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $dbname = $_ENV['DB_DATABASE'] ?? 'vpn_service';
            $username = $_ENV['DB_USERNAME'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';

            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                self::$db = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $databaseTimezone = $_ENV['DB_TIMEZONE'] ?? '+07:00';
                self::$db->exec("SET time_zone = " . self::$db->quote($databaseTimezone));
            } catch (PDOException $e) {
                die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
            }
        }
    }

    public function find(int $id): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAll(): array
    {
        $stmt = self::$db->query("SELECT * FROM `{$this->table}` ORDER BY `id` DESC");
        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): bool
    {
        $fields = array_keys($data);
        $columns = implode('`, `', $fields);
        $placeholders = ':' . implode(', :', $fields);

        $sql = "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute($data);
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}` = :{$key}";
        }
        $setClause = implode(', ', $fields);
        $data['id'] = $id;

        $sql = "UPDATE `{$this->table}` SET {$setClause} WHERE `id` = :id";
        $stmt = self::$db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Xóa nhiều bản ghi theo ID bằng một truy vấn có tham số hóa.
     */
    public function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id): bool => $id > 0
        )));

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `id` IN ({$placeholders})");
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    /**
     * Xóa toàn bộ bản ghi của model hiện tại.
     */
    public function deleteAll(): int
    {
        return (int)self::$db->exec("DELETE FROM `{$this->table}`");
    }

    /**
     * Lấy ID của bản ghi vừa được chèn vào cơ sở dữ liệu
     */
    public function lastInsertId(): int
    {
        return (int)self::$db->lastInsertId();
    }
}
