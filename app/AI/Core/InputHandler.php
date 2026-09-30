<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AIException;

/**
 * InputHandler — chuẩn hoá đầu vào cho AI Core.
 *
 * Nhiệm vụ:
 *  - xác thực module/task_type hợp lệ
 *  - chuẩn hoá kiểu dữ liệu đầu vào (string, array, file path)
 *  - giới hạn kích thước text để tránh payload quá lớn
 *  - KHÔNG đọc media binary, KHÔNG gọi provider
 *
 * InputHandler chỉ làm việc với dữ liệu thuần (array/scalar), không phụ thuộc HTTP.
 */
final class InputHandler
{
    private const MAX_TEXT_LENGTH = 100000;

    private ModuleRegistry $modules;

    public function __construct(?ModuleRegistry $modules = null)
    {
        $this->modules = $modules ?? new ModuleRegistry();
    }

    /**
     * Chuẩn hoá payload đầu vào.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalize(string $module, array $payload): array
    {
        if (!$this->modules->has($module)) {
            throw new AIException(
                'Module không tồn tại: ' . $module,
                AIException::VALIDATION,
                null,
                ['module' => $module]
            );
        }

        if ($this->modules->isEnabled($module) !== true) {
            throw new AIException(
                'Module đang bị tắt: ' . $module,
                AIException::VALIDATION,
                null,
                ['module' => $module]
            );
        }

        $normalized = [];

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = $this->normalizeText($value);
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = $this->normalizeArray($value);
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $normalized[$key] = $value;
                continue;
            }

            // Các kiểu khác (object/resource) không được phép truyền vào AI Core.
            throw new AIException(
                'Kiểu dữ liệu đầu vào không hợp lệ cho trường "' . $key . '".',
                AIException::VALIDATION,
                null,
                ['module' => $module, 'field' => (string) $key]
            );
        }

        return $normalized;
    }

    /**
     * Xác thực input bắt buộc theo capability.
     *
     * @param array<string, mixed> $payload
     * @param array<int, string> $required
     */
    public function assertRequired(array $payload, array $required, string $module): void
    {
        foreach ($required as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === []) {
                throw new AIException(
                    'Thiếu trường bắt buộc: ' . $field,
                    AIException::VALIDATION,
                    null,
                    ['module' => $module, 'field' => $field]
                );
            }
        }
    }

    /**
     * Tạo khoá idempotency ổn định (SHA-1) từ module + payload.
     *
     * Cùng input → cùng key → chống tạo task trùng. Không chứa secret nào
     * ngoài dữ liệu nghiệp vụ.
     *
     * @param array<string, mixed> $payload
     */
    public function idempotencyKey(string $module, array $payload): string
    {
        $canonical = $this->canonicalize($payload);

        return sha1($module . '|' . json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Sắp xếp mảng theo key để đảm bảo hash ổn định.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function canonicalize(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->canonicalize($value);
            }
        }

        return $data;
    }

    private function normalizeText(string $value): string
    {
        // Đảm bảo UTF-8 hợp lệ, cắt khoảng trắng thừa ở đầu/cuối.
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        $value = trim($value);

        if (mb_strlen($value, 'UTF-8') > self::MAX_TEXT_LENGTH) {
            throw new AIException(
                'Nội dung vượt quá giới hạn cho phép (' . self::MAX_TEXT_LENGTH . ' ký tự).',
                AIException::VALIDATION
            );
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function normalizeArray(array $value): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $out[$key] = $this->normalizeText($item);
            } elseif (is_array($item)) {
                $out[$key] = $this->normalizeArray($item);
            } elseif (is_bool($item) || is_int($item) || is_float($item) || $item === null) {
                $out[$key] = $item;
            }
            // Bỏ qua object/resource lồng nhau.
        }

        return $out;
    }
}
