-- ============================================================================
-- Migration: AI Remarketing Facebook tự động HÀNG THÁNG (BƯỚC 5 — mục 5.1/5.2)
-- Ngày     : 2026-10-13
-- Phạm vi  : THÊM cột cho vc_campaigns / vc_campaign_sends + TẠO 1 bảng mới
--            vc_fb_contacts. KHÔNG sửa/xóa cột hay bảng khác.
-- Idempotent: chạy lại lần 2 KHÔNG lỗi (kiểm tra information_schema trước).
--
-- [vc_campaigns] thêm:
--   recurrence       'once' (mặc định — hành vi cũ giữ nguyên) | 'monthly'
--   day_of_month     ngày chạy trong tháng (1-31, mặc định 1)
--   next_run_at      ngày bắt đầu chu kỳ tiếp theo (cycle roll tự động)
--   cycle_no         số chu kỳ đã chạy (mỗi tháng +1)
--   audience_mode    'legacy' (all_fans/specific) | 'old_customers' (khách cũ)
--   personalize      1 = AI soạn lại nội dung theo CHU KỲ + chèn dữ liệu thật
--                    {ten}/{goi_cu}/{uu_dai} lúc gửi
--   cycle_body       bản AI soạn cho chu kỳ hiện tại (NULL = dùng body gốc)
--   cycle_body_cycle chu kỳ mà cycle_body thuộc về (tránh soạn lại mỗi ngày)
--
-- [vc_campaign_sends] thêm cycle_no + đổi UNIQUE sang (campaign_id, target_key,
--   cycle_no) → mỗi người nhận MỘT LẦN MỖI CHU KỲ (gửi lại được tháng sau),
--   các chiến dịch cũ (cycle_no = 0) vẫn giữ nguyên ý nghĩa chống trùng.
--
-- [vc_fb_contacts] (mới): bản đồ danh tính Messenger —
--   psid ↔ user_id, cửa sổ tương tác 7 ngày (last_interaction_at) và opt-out
--   (opted_out_at) theo chính sách Meta.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. vc_campaigns — 8 cột cho lặp tháng + cá nhân hoá
-- ---------------------------------------------------------------------------
SET @has_recurrence := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_campaigns'
      AND COLUMN_NAME  = 'recurrence'
);
SET @sql := IF(
    @has_recurrence = 0,
    'ALTER TABLE `vc_campaigns`
        ADD COLUMN `recurrence` VARCHAR(10) NOT NULL DEFAULT ''once''
            COMMENT ''once | monthly'' AFTER `daily_limit`,
        ADD COLUMN `day_of_month` TINYINT UNSIGNED NOT NULL DEFAULT 1
            COMMENT ''ngày chạy trong tháng (1-31)'' AFTER `recurrence`,
        ADD COLUMN `next_run_at` DATE NULL
            COMMENT ''ngày bắt đầu chu kỳ tới'' AFTER `day_of_month`,
        ADD COLUMN `cycle_no` INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''số chu kỳ đã chạy'' AFTER `next_run_at`,
        ADD COLUMN `audience_mode` VARCHAR(20) NOT NULL DEFAULT ''legacy''
            COMMENT ''legacy | old_customers'' AFTER `cycle_no`,
        ADD COLUMN `personalize` TINYINT(1) NOT NULL DEFAULT 0
            COMMENT ''1 = AI soạn theo chu kỳ + chèn dữ liệu thật'' AFTER `audience_mode`,
        ADD COLUMN `cycle_body` MEDIUMTEXT NULL
            COMMENT ''nội dung AI soạn cho chu kỳ hiện tại'' AFTER `personalize`,
        ADD COLUMN `cycle_body_cycle` INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''chu kỳ mà cycle_body thuộc về'' AFTER `cycle_body`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. vc_campaign_sends — cycle_no + UNIQUE (campaign_id, target_key, cycle_no)
--    Guard theo cột cycle_no: lần đầu ⇒ thêm cột + đổi UNIQUE một lần.
-- ---------------------------------------------------------------------------
SET @has_send_cycle := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_campaign_sends'
      AND COLUMN_NAME  = 'cycle_no'
);
SET @sql := IF(
    @has_send_cycle = 0,
    'ALTER TABLE `vc_campaign_sends`
        ADD COLUMN `cycle_no` INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''chu kỳ mà dòng này thuộc về'' AFTER `target_key`,
        DROP INDEX `uq_campaign_target`,
        ADD UNIQUE KEY `uq_campaign_target_cycle` (`campaign_id`, `target_key`, `cycle_no`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. vc_fb_contacts — bản đồ danh tính Messenger (mới)
--    - last_interaction_at : cửa sổ 7 ngày của Meta (chỉ gửi khi còn hiệu lực)
--    - consent_at          : khách CHỦ ĐỘNG nhắn fanpage (opt-in ban đầu)
--    - opted_out_at        : khách đã từ chối (gõ STOP/UNSUBSCRIBE/...)
--    - user_id             : liên kết tài khoản website (khớp email trong chat)
--      → nhờ đó mới lọc được "khách hàng cũ" (hết hạn/đã hủy, không hoạt động)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_fb_contacts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `psid` VARCHAR(100) NOT NULL COMMENT 'Persistent Sender ID của Messenger',
    `page_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Fanpage sở hữu PSID',
    `user_id` BIGINT UNSIGNED NULL COMMENT 'liên kết vc_users (khớp email trong chat)',
    `first_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'tên lấy từ profile Messenger',
    `consent_at` DATETIME NULL COMMENT 'lần nhắn đầu tiên (opt-in)',
    `last_interaction_at` DATETIME NOT NULL COMMENT 'lần nhắn gần nhất — cửa sổ 7 ngày',
    `last_sent_at` DATETIME NULL COMMENT 'chiến dịch gần nhất đã gửi tới PSID này',
    `opted_out_at` DATETIME NULL COMMENT 'khách gõ STOP/... → cấm mọi chiến dịch',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fb_contact_psid` (`psid`, `page_id`),
    KEY `idx_fb_contact_user` (`user_id`),
    KEY `idx_fb_contact_window` (`opted_out_at`, `last_interaction_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
