-- Chạy một lần trên cơ sở dữ liệu đã tồn tại trước khi bật tính năng nguồn truy cập.
ALTER TABLE `vc_access_logs`
    ADD COLUMN `source` VARCHAR(100) NULL AFTER `action`,
    ADD COLUMN `referrer_host` VARCHAR(255) NULL AFTER `source`,
    ADD COLUMN `landing_path` VARCHAR(255) NULL AFTER `referrer_host`,
    ADD COLUMN `utm_source` VARCHAR(100) NULL AFTER `landing_path`,
    ADD COLUMN `utm_medium` VARCHAR(100) NULL AFTER `utm_source`,
    ADD COLUMN `utm_campaign` VARCHAR(150) NULL AFTER `utm_medium`,
    ADD INDEX `idx_access_logs_source` (`source`);
