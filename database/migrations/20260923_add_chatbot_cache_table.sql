CREATE TABLE IF NOT EXISTS `vc_chat_ai_cache` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cache_key` CHAR(40) NOT NULL UNIQUE,
    `question` VARCHAR(1000) NOT NULL,
    `answer` TEXT NOT NULL,
    `provider` VARCHAR(40) NULL,
    `model` VARCHAR(80) NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_chat_cache_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
