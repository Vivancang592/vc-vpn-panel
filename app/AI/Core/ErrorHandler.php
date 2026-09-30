<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AIException;
use App\AI\Contracts\AIResult;
use Throwable;

/**
 * ErrorHandler — chuẩn hoá mọi lỗi về AIResult::failure() với error type thống nhất.
 *
 * Đây là điểm duy nhất trong AI Core được phép "nuốt" exception và biến nó
 * thành dữ liệu (AIResult) để tầng trên xử lý.
 */
final class ErrorHandler
{
    /**
     * @param array<string, mixed> $context
     */
    public function handle(Throwable $e, array $context = []): AIResult
    {
        if ($e instanceof AIException) {
            return AIResult::failure(
                $e->getErrorType(),
                $e->getMessage(),
                array_merge($e->getContext(), ['http_status' => $e->getHttpStatus()]),
                $context
            );
        }

        return AIResult::failure(
            AIException::UNKNOWN,
            $e->getMessage(),
            ['exception' => get_class($e)],
            $context
        );
    }

    /**
     * Chạy callback và luôn trả về AIResult (không ném ra ngoài).
     *
     * @param array<string, mixed> $context
     */
    public function guard(callable $callback, array $context = []): AIResult
    {
        try {
            $result = $callback();
        } catch (Throwable $e) {
            return $this->handle($e, $context);
        }

        if ($result instanceof AIResult) {
            return $result;
        }

        if (is_array($result)) {
            return AIResult::success(array_merge($context, $result));
        }

        return AIResult::failure(AIException::MALFORMED, 'Callback không trả về AIResult hợp lệ.', [], $context);
    }

    /**
     * Lỗi này có nên được retry không (chỉ dựa trên error type).
     *
     * @param array<string, mixed> $retryPolicy
     */
    public function isRetryable(?string $errorType, array $retryPolicy): bool
    {
        if ($errorType === null) {
            return false;
        }

        $nonRetryable = (array) ($retryPolicy['non_retryable_errors'] ?? []);
        if (in_array($errorType, $nonRetryable, true)) {
            return false;
        }

        $retryable = (array) ($retryPolicy['retryable_errors'] ?? []);
        if ($retryable === []) {
            return false;
        }

        return in_array($errorType, $retryable, true);
    }

    /**
     * Lỗi này là lỗi cấu hình/hệ thống (cần admin can thiệp) hay lỗi tạm thời.
     */
    public function isFatal(?string $errorType): bool
    {
        return in_array($errorType, [
            AIException::AUTH,
            AIException::CONFIG,
            AIException::VALIDATION,
        ], true);
    }
}
