-- Migration: Create vc_scheduled_posts table
CREATE TABLE IF NOT EXISTS `vc_scheduled_posts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `topic` VARCHAR(255) NOT NULL,
    `content_prompt` TEXT NULL,
    `generated_content` LONGTEXT NULL,
    `image_prompt` TEXT NULL,
    `image_url` VARCHAR(1024) NULL,
    `scheduled_at` DATETIME NOT NULL,
    `published_at` DATETIME NULL,
    `facebook_post_id` VARCHAR(100) NULL,
    `status` ENUM('pending', 'generating', 'ready', 'publishing', 'published', 'failed') NOT NULL DEFAULT 'pending',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `error_message` TEXT NULL,
    `meta_data` JSON NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_scheduled_status` (`status`, `scheduled_at`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
