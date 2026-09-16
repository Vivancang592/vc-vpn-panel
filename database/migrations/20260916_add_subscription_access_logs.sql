CREATE TABLE `vc_subscription_access_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `app_name` VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `os_name` VARCHAR(50) NOT NULL DEFAULT 'Unknown',
    `request_type` ENUM('vpn_app', 'browser', 'unknown') NOT NULL DEFAULT 'unknown',
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_subscription_access_logs_subscription_created` (`subscription_id`, `created_at`),
    FOREIGN KEY (`subscription_id`) REFERENCES `vc_subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;