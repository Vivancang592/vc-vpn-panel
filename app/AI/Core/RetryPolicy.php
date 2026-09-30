<?php

declare(strict_types=1);

namespace App\AI\Core;

/**
 * RetryPolicy — nguồn duy nhất định nghĩa luật retry (centralized).
 *
 * KiraProvider chỉ TIÊU THỤ policy (plain array) do class này tạo ra,
 * KHÔNG tự định nghĩa lại. Điều này tránh logic retry bị phân tán.
 */
final class RetryPolicy
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config Toàn bộ config/ai.php
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Policy mặc định (từ config/ai.php retry).
     *
     * @return array<string, mixed>
     */
    public function default(): array
    {
        $retry = $this->config['retry'] ?? [];

        if (!is_array($retry)) {
            $retry = [];
        }

        return array_merge([
            'enabled'              => false,
            'max_attempts'         => 1,
            'base_delay_ms'        => 500,
            'max_delay_ms'         => 8000,
            'multiplier'           => 2.0,
            'retryable_errors'     => [],
            'non_retryable_errors' => [],
            'jitter'               => 0,
        ], $retry);
    }

    /**
     * Policy ghi đè một phần (dùng cho test hoặc module có nhu cầu riêng).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function with(array $overrides): array
    {
        return array_merge($this->default(), $overrides);
    }

    /**
     * Policy cho lỗi auth: KHÔNG bao giờ retry.
     *
     * @return array<string, mixed>
     */
    public function noRetry(): array
    {
        return $this->with(['enabled' => false, 'max_attempts' => 1]);
    }

    /**
     * Tính delay (ms) với exponential backoff + cap, kèm jitter tùy chọn.
     *
     * @param array<string, mixed> $policy
     */
    public function delayFor(array $policy, int $attempt): int
    {
        $base = max(0, (int) ($policy['base_delay_ms'] ?? 500));
        $max = max(0, (int) ($policy['max_delay_ms'] ?? 8000));
        $multiplier = (float) ($policy['multiplier'] ?? 2.0);
        $jitter = (int) ($policy['jitter'] ?? 0);

        if ($base === 0) {
            return 0;
        }

        $delay = (int) ($base * ($multiplier ** max(0, $attempt - 1)));
        $delay = (int) min($delay, $max);

        if ($jitter > 0) {
            $delay += random_int(0, $jitter);
        }

        return max(0, $delay);
    }

    /**
     * Tổng số lần thử tối đa (luôn >= 1).
     *
     * @param array<string, mixed> $policy
     */
    public function maxAttempts(array $policy): int
    {
        if (($policy['enabled'] ?? false) !== true) {
            return 1;
        }

        return max(1, (int) ($policy['max_attempts'] ?? 1));
    }
}
