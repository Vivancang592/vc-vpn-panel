<?php

declare(strict_types=1);

namespace App\Models;

use App\AI\Contracts\AICapability;

/**
 * Model: vc_ai_models — CONFIG (catalog model provider).
 *
 * KHÔNG hard-code tên model ở nơi khác; mọi tra cứu đi qua đây hoặc ModelResolver.
 */
class AIModel extends BaseModel
{
    protected string $table = 'vc_ai_models';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allActive(string $provider = 'kira'): array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter((array) $rows, static function ($row) use ($provider): bool {
            return is_array($row)
                && (string) ($row['provider'] ?? '') === $provider
                && (int) ($row['is_active'] ?? 0) === 1;
        }));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $modelKey, string $provider = 'kira'): ?array
    {
        try {
            $rows = $this->getAll();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ((array) $rows as $row) {
            if (is_array($row)
                && (string) ($row['model_key'] ?? '') === $modelKey
                && (string) ($row['provider'] ?? '') === $provider
            ) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byCapability(string $capability, string $provider = 'kira'): array
    {
        $normalized = AICapability::normalize($capability);

        return array_values(array_filter($this->allActive($provider), static function (array $row) use ($normalized): bool {
            return AICapability::normalize((string) ($row['capability'] ?? '')) === $normalized;
        }));
    }
}
