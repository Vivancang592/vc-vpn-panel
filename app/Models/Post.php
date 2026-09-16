<?php

namespace App\Models;

class Post extends BaseModel
{
    protected string $table = 'vc_posts';

    /**
     * Lấy toàn bộ bài viết kèm tên tác giả
     */
    public function allWithAuthor(): array
    {
        $stmt = self::$db->prepare("
            SELECT p.*, u.username AS author_name
            FROM `{$this->table}` p
            LEFT JOIN `vc_users` u ON p.author_id = u.id
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết 1 bài viết theo ID kèm thông tin tác giả
     */
    public function findWithAuthor(int $id): ?array
    {
        $stmt = self::$db->prepare("
            SELECT p.*, u.username AS author_name
            FROM `{$this->table}` p
            LEFT JOIN `vc_users` u ON p.author_id = u.id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Lấy danh sách các bài viết đã xuất bản
     */
    public function getAllPublished(): array
    {
        $stmt = self::$db->prepare("
            SELECT p.*, u.username AS author_name
            FROM `{$this->table}` p
            LEFT JOIN `vc_users` u ON p.author_id = u.id
            WHERE p.status = 'published' 
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getPublishedTutorials(): array
    {
        $stmt = self::$db->prepare("SELECT p.*, u.username AS author_name FROM `{$this->table}` p LEFT JOIN `vc_users` u ON p.author_id = u.id WHERE p.status = 'published' AND p.type = 'tutorial' ORDER BY p.id DESC");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy chi tiết bài viết theo slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = self::$db->prepare("
            SELECT p.*, u.username AS author_name
            FROM `{$this->table}` p
            LEFT JOIN `vc_users` u ON p.author_id = u.id
            WHERE p.slug = :slug
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): bool
    {
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = self::$db->prepare("INSERT INTO `{$this->table}` ({$fields}) VALUES ({$placeholders})");
        return $stmt->execute(array_values($data));
    }

    public function update(int $id, array $data): bool
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $values = array_values($data);
        $values[] = $id;

        $stmt = self::$db->prepare("UPDATE `{$this->table}` SET {$set} WHERE id = ?");
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE id = ?");
        return $stmt->execute([$id]);
    }
}