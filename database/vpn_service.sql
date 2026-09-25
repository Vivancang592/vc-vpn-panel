CREATE TABLE `vc_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `google_id` VARCHAR(255) NULL UNIQUE,
    `password_hash` VARCHAR(255) NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `status` ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active',
    `balance` DECIMAL(15, 0) NOT NULL DEFAULT 0.00,
    `commission_balance` DECIMAL(15, 0) NOT NULL DEFAULT 0.00,
    `ref_code` VARCHAR(20) NULL UNIQUE,
    `referred_by` BIGINT UNSIGNED NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `register_ip` VARCHAR(45) NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `last_login_time` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`referred_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_access_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `source` VARCHAR(100) NULL,
    `referrer_host` VARCHAR(255) NULL,
    `landing_path` VARCHAR(255) NULL,
    `utm_source` VARCHAR(100) NULL,
    `utm_medium` VARCHAR(100) NULL,
    `utm_campaign` VARCHAR(150) NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_access_logs_source` (`source`),
    FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_server_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_servers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `country_code` VARCHAR(10) NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `api_port` INT NOT NULL DEFAULT 80,
    `api_token` VARCHAR(255) NULL,
    `last_check_in` TIMESTAMP NULL,
    `status` ENUM('active', 'maintenance', 'offline') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `vc_server_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_node_inbounds` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `server_id` INT UNSIGNED NOT NULL,
    `tag` VARCHAR(100) NULL,
    `port` INT NOT NULL,
    `protocol` ENUM('vmess', 'vless', 'trojan', 'shadowsocks', 'wireguard', 'hy2', 'tuic') NOT NULL,
    `network` ENUM('tcp', 'ws', 'grpc', 'udp', 'quic') NOT NULL DEFAULT 'tcp',
    `tls` TINYINT(1) NOT NULL DEFAULT 1,
    `sni` VARCHAR(255) NULL,
    `host` VARCHAR(255) NULL,
    `path` VARCHAR(255) NULL,
    `public_key` VARCHAR(255) NULL,
    `short_id` VARCHAR(100) NULL,
    `service_name` VARCHAR(100) NULL,
    `password` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (`server_id`) REFERENCES `vc_servers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_node_tasks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `server_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `payload` JSON NOT NULL,
    `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `attempts` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`server_id`) REFERENCES `vc_servers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_vpn_plans` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` JSON NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `price` DECIMAL(15, 0) NOT NULL,
    `duration_days` INT NOT NULL,
    `bandwidth_limit_gb` INT NOT NULL DEFAULT 0,
    `max_devices` INT NOT NULL DEFAULT 1,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_coupons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `discount_type` ENUM('percent', 'fixed') NOT NULL,
    `discount_value` DECIMAL(15, 0) NOT NULL,
    `max_uses` INT NOT NULL DEFAULT 0,
    `used_count` INT NOT NULL DEFAULT 0,
    `expires_at` TIMESTAMP NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plan_id` INT UNSIGNED NULL,
    `subscription_id` BIGINT UNSIGNED NULL,
    `coupon_id` INT UNSIGNED NULL,
    `total_amount` DECIMAL(15, 0) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'vietqr',
    `transfer_content` VARCHAR(100) NULL,
    `purchase_ip` VARCHAR(45) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `approved_by` BIGINT UNSIGNED NULL,
    `payment_status` ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plan_id`) REFERENCES `vc_vpn_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coupon_id`) REFERENCES `vc_coupons`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_subscriptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plan_id` INT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `uuid` VARCHAR(36) NOT NULL UNIQUE,
    `max_devices` INT NOT NULL DEFAULT 1,
    `transfer_enable` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `upload` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `download` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `last_used_ip` VARCHAR(45) NULL,
    `online_devices` INT UNSIGNED NOT NULL DEFAULT 0,
    `start_date` TIMESTAMP NOT NULL,
    `end_date` TIMESTAMP NOT NULL,
    `status` ENUM('active', 'expired', 'suspended', 'cancelled') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plan_id`) REFERENCES `vc_vpn_plans`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`) REFERENCES `vc_orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `subscription_id` BIGINT UNSIGNED NULL,
    `type` ENUM('deposit', 'payment') NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(100) NULL UNIQUE,
    `transfer_content` VARCHAR(100) NULL,
    `amount` DECIMAL(15, 0) NOT NULL,
    `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `vc_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subscription_id`) REFERENCES `vc_subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `vc_referral_commissions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `referrer_id` BIGINT UNSIGNED NOT NULL,
    `referred_user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `commission_rate` DECIMAL(5, 2) NOT NULL,
    `commission_amount` DECIMAL(15, 0) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`referrer_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`referred_user_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `vc_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_withdrawals` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(15, 0) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `bank_account_number` VARCHAR(50) NOT NULL,
    `bank_account_name` VARCHAR(100) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_posts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `author_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `content` LONGTEXT NOT NULL,
    `type` ENUM('news', 'tutorial', 'faq') NOT NULL DEFAULT 'news',
    `status` ENUM('published', 'draft', 'hidden') NOT NULL DEFAULT 'published',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`author_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_support_tickets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `assigned_staff_id` BIGINT UNSIGNED NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_staff_id`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_ticket_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` BIGINT UNSIGNED NOT NULL,
    `sender_id` BIGINT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `vc_support_tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `vc_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_system_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_email_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recipient` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `status` ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vc_expenses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(15, 0) NOT NULL,
    `category` VARCHAR(100) NULL,
    `note` TEXT NULL,
    `expense_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Bảng Quản lý bài đăng tự động lên Fanpage
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

-- Cấu hình đơn vị tiền tệ mặc định sang VND (đ)
INSERT INTO `vc_settings` (`setting_key`, `setting_value`, `description`) 
VALUES 
    ('currency', 'VND', 'Mã đơn vị tiền tệ hệ thống'),
    ('currency_symbol', 'đ', 'Ký hiệu tiền tệ hệ thống'),
    ('min_deposit_amount', '10000', 'Số tiền nạp tối thiểu (VND)'),
    ('referral_commission_rate', '10', 'Tỷ lệ hoa hồng giới thiệu (%)'),
    ('enable_vietqr', '1', 'Bật cổng thanh toán VietQR')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);
