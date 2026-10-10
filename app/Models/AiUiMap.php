<?php

namespace App\Models;

/**
 * BƯỚC 4.1 — BẢN ĐỒ GIAO DIỆN (UI map) của hệ thống.
 *
 * NGUỒN SỰ THẬT duy nhất về trang / nút / thao tác trên giao diện người dùng:
 *  - Seed ban đầu từ const SiteKnowledge::PAGES (tools/ai_ui_map_build.php).
 *  - Bổ sung bằng cách quét file view thật (cùng tool).
 *  - Admin sửa/xóa/ẩn trực tiếp (hoặc qua AI Trợ lý Admin với entity `ai_ui_map`).
 *
 * SiteKnowledge::pages() đọc bảng này; bảng rỗng / lỗi -> tự rơi về const PAGES
 * nên chat không bao giờ chết vì thiếu dữ liệu.
 */
class AiUiMap extends BaseModel
{
    protected string $table = 'vc_ai_ui_map';

    /** @return array<int, array<string,mixed>> mọi trang đang bật, theo thứ tự thêm vào */
    public function getActivePages(): array
    {
        $stmt = self::$db->query("SELECT * FROM `{$this->table}` WHERE `is_active` = 1 ORDER BY `id` ASC");
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function findByPath(string $pagePath): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `page_path` = :p LIMIT 1");
        $stmt->execute(['p' => $pagePath]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Thêm mới hoặc cập nhật 1 trang (khóa `page_path`).
     * Chỉ ghi các cột được phép — không đụng id / created_at.
     */
    public function upsertPage(
        string $pagePath,
        string $title,
        bool $requiresLogin,
        array $actions,
        string $source = 'manual',
        string $viewPath = ''
    ): bool {
        $existing = $this->findByPath($pagePath);
        $data = [
            'page_path'      => $pagePath,
            'requires_login' => $requiresLogin ? 1 : 0,
            'title'          => mb_substr($title, 0, 191),
            'actions_json'   => json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'view_path'      => mb_substr($viewPath, 0, 191),
            'source'         => in_array($source, ['const', 'scanner', 'manual'], true) ? $source : 'manual',
            'is_active'      => 1,
        ];

        if ($existing !== null) {
            // Không đè source của row admin đã sửa tay thành const/scanner.
            if ($existing['source'] === 'manual' && $source !== 'manual') {
                unset($data['source']);
            }
            return $this->update((int) $existing['id'], $data);
        }

        return $this->create($data);
    }
}
