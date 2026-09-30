<?php

declare(strict_types=1);

namespace App\AI\Contracts;

use Throwable;

/**
 * Exception chuẩn hóa của AI Core.
 *
 * Mọi lỗi phát sinh từ provider/core đều được map về một trong các error type
 * dưới đây để RetryPolicy và ErrorHandler xử lý tập trung.
 *
 * KHÔNG chứa API key / Authorization header / media base64 trong message.
 */
class AIException extends \RuntimeException
{
    public const AUTH = 'AUTH';
    public const NOT_FOUND = 'NOT_FOUND';
    public const RATE_LIMIT = 'RATE_LIMIT';
    public const VALIDATION = 'VALIDATION';
    public const CLIENT_ERROR = 'CLIENT_ERROR';
    public const UPSTREAM_5XX = 'UPSTREAM_5XX';
    public const TIMEOUT = 'TIMEOUT';
    public const MALFORMED = 'MALFORMED';
    public const CONFIG = 'CONFIG';
    public const UNKNOWN = 'UNKNOWN';

    private string $errorType;
    private ?int $httpStatus;
    private array $context;

    public function __construct(
        string $message,
        string $errorType = self::UNKNOWN,
        ?int $httpStatus = null,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorType = $errorType;
        $this->httpStatus = $httpStatus;
        $this->context = self::sanitizeContext($context);
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Phân loại lỗi từ HTTP status theo đúng quy tắc đã chốt.
     */
    public static function typeFromHttpStatus(int $status): string
    {
        return match (true) {
            $status === 401 || $status === 403 => self::AUTH,
            $status === 404 => self::NOT_FOUND,
            $status === 429 => self::RATE_LIMIT,
            $status >= 500 => self::UPSTREAM_5XX,
            $status >= 400 && $status < 500 => self::VALIDATION,
            default => self::UNKNOWN,
        };
    }

    public static function fromHttpStatus(int $status, string $message, array $context = []): self
    {
        return new self($message, self::typeFromHttpStatus($status), $status, $context);
    }

    /**
     * Loại bỏ các trường nhạy cảm khỏi context trước khi lưu/log.
     */
    private static function sanitizeContext(array $context): array
    {
        $sensitive = ['authorization', 'api_key', 'apikey', 'key', 'token', 'secret', 'password', 'b64_json', 'image', 'audio', 'video'];
        $clean = [];

        foreach ($context as $name => $value) {
            if (in_array(strtolower((string) $name), $sensitive, true)) {
                $clean[$name] = '[redacted]';
                continue;
            }

            if (is_string($value) && strlen($value) > 500) {
                $clean[$name] = substr($value, 0, 500) . '...[truncated]';
                continue;
            }

            $clean[$name] = is_array($value) ? self::sanitizeContext($value) : $value;
        }

        return $clean;
    }
}
