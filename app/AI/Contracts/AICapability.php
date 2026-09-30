<?php

declare(strict_types=1);

namespace App\AI\Contracts;

/**
 * Danh mục năng lực (capability) của AI Core.
 *
 * Đây là hằng số phân loại, KHÔNG chứa business logic của module nào.
 * Model, provider và module đều tham chiếu các giá trị này để routing.
 */
final class AICapability
{
    public const TEXT = 'text';
    public const IMAGE = 'image';
    public const VIDEO = 'video';
    public const AUDIO = 'audio';
    public const COMMENT = 'comment';
    public const PUBLISH = 'publish';
    public const CHAT = 'chat';

    /** @var string[] */
    public const ALL = [
        self::TEXT,
        self::IMAGE,
        self::VIDEO,
        self::AUDIO,
        self::COMMENT,
        self::PUBLISH,
        self::CHAT,
    ];

    public static function isValid(?string $capability): bool
    {
        return $capability !== null && in_array($capability, self::ALL, true);
    }

    public static function normalize(?string $capability): ?string
    {
        $capability = strtolower(trim((string) $capability));

        return self::isValid($capability) ? $capability : null;
    }
}
