-- =====================================================================
-- Chiến dịch gửi theo lịch của TRỢ LÝ ADMIN (chat admin)
--   kind = 'email' : gửi qua MailService (SMTP) — chống trùng theo vc_campaign_sends
--   kind = 'fb'    : gửi tin nhắn Fanpage tới người từng nhắn (psid)
-- Cron (api/cron/check-subscriptions) gọi CampaignService::sendDueCampaigns():
-- mỗi ngày mỗi chiến dịch active gửi tối đa daily_limit người CHƯA từng nhận,
-- hết người nhận → status = 'done'.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `vc_campaigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind` VARCHAR(10) NOT NULL COMMENT 'email | fb',
  `title` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(191) NOT NULL DEFAULT '' COMMENT 'chỉ kind=email',
  `body` MEDIUMTEXT NOT NULL COMMENT 'nội dung AI soạn (HTML cho email, text cho fb)',
  `target_mode` VARCHAR(20) NOT NULL DEFAULT 'all' COMMENT 'all_users | all_fans | specific',
  `target_ref` TEXT NULL COMMENT 'JSON: {user_ids:[..],emails:[..]} hoặc {psids:[..]} khi specific',
  `daily_limit` INT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'số người tối đa mỗi ngày',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active | paused | done | cancelled',
  `last_sent_date` DATE NULL COMMENT 'ngày gửi gần nhất (chống gửi 2 lần trong ngày)',
  `total_sent` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`, `last_sent_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vc_campaign_sends` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NOT NULL,
  `target_key` VARCHAR(191) NOT NULL COMMENT 'email hoặc psid — khóa chống trùng',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | sent | failed',
  `detail` VARCHAR(512) NOT NULL DEFAULT '',
  `sent_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campaign_target` (`campaign_id`, `target_key`),
  CONSTRAINT `fk_sends_campaign` FOREIGN KEY (`campaign_id`)
    REFERENCES `vc_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
