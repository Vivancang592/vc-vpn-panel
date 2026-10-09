<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AICapability;

/**
 * ModuleRegistry — ánh xạ module nghiệp vụ → capability + prompt + loại output.
 *
 * Registry này KHÔNG chứa business logic. Nó chỉ mô tả "module nào cần gì"
 * để AICore lắp ráp request mà không cần biết chi tiết từng module.
 *
 * Nguồn dữ liệu: định nghĩa tĩnh (an toàn, không cần DB khi boot).
 * Bảng vc_ai_modules dùng để quản trị/bật-tắt/ghi đè ở phase sau.
 */
final class ModuleRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const MODULES = [
        'content_article' => [
            'label'       => 'Tạo Bài Viết',
            'capability'  => AICapability::TEXT,
            'prompt_key'  => 'content_article',
            'output_kind' => 'text',
            'enabled'     => true,
        ],
        'image_generation' => [
            'label'       => 'Tạo Hình Ảnh AI',
            'capability'  => AICapability::IMAGE,
            'prompt_key'  => 'image_generation',
            'output_kind' => 'image',
            'enabled'     => true,
        ],
        'fanpage_comment' => [
            'label'       => 'Trả Lời Bình Luận Fanpage',
            'capability'  => AICapability::COMMENT,
            'prompt_key'  => 'fanpage_comment',
            'output_kind' => 'text',
            'enabled'     => true,
        ],
        'support_chat' => [
            'label'       => 'Hội Thoại Hỗ Trợ',
            'capability'  => AICapability::CHAT,
            'prompt_key'  => 'support_chat',
            'output_kind' => 'text',
            'enabled'     => true,
        ],
        // publish_post đã GỠ (2026-10-01): cron đăng bài thuần snapshot từ task 11,
        // không còn luồng AI "chuẩn bị đăng bài" nào — module dư thừa.
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return self::MODULES;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys(self::MODULES);
    }

    public function has(string $module): bool
    {
        return isset(self::MODULES[$module]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $module): ?array
    {
        if (!isset(self::MODULES[$module])) {
            return null;
        }

        return array_merge(['key' => $module], self::MODULES[$module]);
    }

    public function capabilityOf(string $module): ?string
    {
        return self::MODULES[$module]['capability'] ?? null;
    }

    public function promptKeyOf(string $module): ?string
    {
        return self::MODULES[$module]['prompt_key'] ?? null;
    }

    public function outputKindOf(string $module): ?string
    {
        return self::MODULES[$module]['output_kind'] ?? null;
    }

    public function isEnabled(string $module): bool
    {
        return ($this->get($module)['enabled'] ?? false) === true;
    }

    /**
     * @return array<int, string>
     */
    public function modulesForCapability(string $capability): array
    {
        $out = [];
        foreach (self::MODULES as $key => $meta) {
            if (($meta['capability'] ?? null) === $capability) {
                $out[] = $key;
            }
        }

        return $out;
    }
}
