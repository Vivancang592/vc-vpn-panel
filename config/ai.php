<?php

declare(strict_types=1);

/**
 * Cấu hình AI Core (không chứa secret).
 *
 * - KHÔNG hard-code API key tại đây.
 * - API key Kira sẽ được xử lý ở phase sau (settings/env), không nằm trong file này.
 * - Mọi giá trị đều có default an toàn để hệ thống vẫn hoạt động khi Kira chưa được cấu hình.
 *
 * Đọc env theo đúng convention hiện có của project (putenv/getenv tại public/index.php).
 */
return [
    // Provider mặc định của AI Core. Phase 5 chỉ có provider "kira".
    'default_provider' => getenv('AI_PROVIDER') ?: 'kira',

    // Provider được AI Core chấp nhận (whitelist). Phase 5 chỉ Kira.
    'allowed_providers' => ['kira'],

    'providers' => [
        'kira' => [
            // Base URL đã xác minh ở Phase 2 (không có dấu / ở cuối).
            'base_url'       => rtrim((string) (getenv('KIRA_BASE_URL') ?: 'https://kiraai.vn/api/v1'), '/'),
            'auth_header'    => 'Authorization',
            'auth_scheme'    => 'Bearer',
            // KHÔNG đọc/ghi key ở đây. Key thuộc tầng settings, xử lý ở phase sau.
            'env_key'        => 'KIRA_API_KEY',
            'setting_key'    => 'kira_api_key',
            // Timeout HTTP (giây).
            'connect_timeout' => (int) (getenv('KIRA_CONNECT_TIMEOUT') ?: 10),
            'timeout'         => (int) (getenv('KIRA_TIMEOUT') ?: 60),
            // Endpoint mapping đã xác minh ở Phase 2.
            'endpoints'      => [
                'chat'          => 'POST /chat/completions',
                'image'         => 'POST /images/generations',
                'video_create'  => 'POST /videos/generations',
                'video_status'  => 'GET /videos/operations/{id}',
                'audio_speech'  => 'POST /audio/speech',
                'models'        => 'GET /models',
            ],
        ],
    ],

    // Retry policy — chỉ retry cho các lỗi tạm thời.
    'retry' => [
        'enabled'         => true,
        'max_attempts'    => (int) (getenv('AI_RETRY_MAX_ATTEMPTS') ?: 3),
        'base_delay_ms'   => (int) (getenv('AI_RETRY_BASE_DELAY_MS') ?: 500),
        'max_delay_ms'    => (int) (getenv('AI_RETRY_MAX_DELAY_MS') ?: 8000),
        'multiplier'      => 2.0,
        // Chỉ những nhóm lỗi này mới được retry.
        'retryable_errors' => ['TIMEOUT', 'UPSTREAM_5XX', 'RATE_LIMIT'],
        // Không bao giờ retry các nhóm này.
        'non_retryable_errors' => ['AUTH', 'VALIDATION', 'NOT_FOUND', 'CLIENT_ERROR', 'MALFORMED'],
    ],

    // Task / queue (abstraction — worker thật triển khai ở phase sau).
    'task' => [
        'default_max_retries'  => (int) (getenv('AI_TASK_MAX_RETRIES') ?: 3),
        'lock_stale_seconds'   => (int) (getenv('AI_TASK_LOCK_STALE_SECONDS') ?: 900),
        'default_priority'     => 5,
        // Video LRO (Long-Running Operation): provider trả operation id → poll định kỳ.
        'video_poll_seconds'        => (int) (getenv('AI_VIDEO_POLL_SECONDS') ?: 60),
        'video_lro_timeout_seconds' => (int) (getenv('AI_VIDEO_LRO_TIMEOUT_SECONDS') ?: 1800),
    ],

    // Asset storage (AssetManager dùng). Không lưu blob/base64 vào DB.
    'assets' => [
        'disk'          => 'public',
        'base_path'     => 'public/uploads/ai',
        'base_url'      => '/uploads/ai',
        'allowed_kinds' => ['image', 'video', 'audio', 'other'],
    ],

    // Logging — metadata only, không log media/secret.
    'logging' => [
        'channel'          => 'ai_core',
        'log_file'         => 'ai_core.log',
        'max_message_len'  => 2000,
    ],
];
