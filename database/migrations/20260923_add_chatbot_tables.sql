CREATE TABLE IF NOT EXISTS `vc_chat_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `visitor_token` VARCHAR(100) NOT NULL,
    `source` ENUM('web','fanpage') NOT NULL DEFAULT 'web',
    `external_id` VARCHAR(100) NULL,
    `status` ENUM('open','handoff','closed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_chat_session` (`visitor_token`, `source`),
    KEY `idx_chat_session_user` (`user_id`),
    KEY `idx_chat_session_external` (`external_id`),
    CONSTRAINT `fk_chat_session_user` FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_chat_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `role` ENUM('user','assistant','system') NOT NULL,
    `content` TEXT NOT NULL,
    `provider` VARCHAR(40) NULL,
    `model` VARCHAR(80) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_chat_messages_session` (`session_id`),
    CONSTRAINT `fk_chat_messages_session` FOREIGN KEY (`session_id`) REFERENCES `vc_chat_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_chat_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` BIGINT UNSIGNED NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `source` ENUM('web','fanpage') NOT NULL DEFAULT 'web',
    `event_name` VARCHAR(60) NOT NULL,
    `event_data` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_chat_events_session` (`session_id`),
    KEY `idx_chat_events_event` (`event_name`),
    KEY `idx_chat_events_created` (`created_at`),
    CONSTRAINT `fk_chat_events_session` FOREIGN KEY (`session_id`) REFERENCES `vc_chat_sessions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_chat_events_user` FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
