<?php

declare(strict_types=1);

namespace App\Models;

use App\AI\Contracts\AICapability;

/**
 * Model: vc_ai_modules — CONFIG (module AI của hệ thống).
 *
 * Chỉ khai báo bảng + vài helper tra cứu thuần dữ liệu.
 * KHÔNG chứa business logic, KHÔNG điều phối workflow.
 */
class AIModule extends BaseModel
{
    protected string $table = 'vc_ai_modules';

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $moduleKey): ?array
    {
        try {
            foreach ($this->getAll() as $row) {
                if (is_array($row) && (string) ($row['module_key'] ?? '') === $moduleKey) {
                    return $row;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allEnabled(): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter((array) $rows, static function ($row): bool {
            return is_array($row) && (int) ($row['is_enabled'] ?? 0) === 1;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byCapability(string $capability): array
    {
        $normalized = AICapability::normalize($capability);

        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter((array) $rows, static function ($row) use ($normalized): bool {
            return is_array($row) && AICapability::normalize((string) ($row['capability'] ?? '')) === $normalized;
        }));
    }
}
