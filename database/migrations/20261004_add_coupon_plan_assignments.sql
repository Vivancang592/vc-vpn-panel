-- =====================================================================
-- Migration: Bảng gán GÓI áp dụng cho mã giảm giá
-- Quy ước: 0 bản ghi  -> coupon áp dụng cho TẤT CẢ gói dịch vụ
--          có bản ghi -> chỉ các gói được chọn mới áp dụng coupon
-- Chạy an toàn nhiều lần (idempotent).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `vc_coupon_plans` (
    `coupon_id` INT UNSIGNED NOT NULL,
    `plan_id`   INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`coupon_id`, `plan_id`),
    KEY `idx_vc_coupon_plans_plan` (`plan_id`),
    CONSTRAINT `fk_vc_coupon_plans_coupon`
        FOREIGN KEY (`coupon_id`) REFERENCES `vc_coupons` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_vc_coupon_plans_plan`
        FOREIGN KEY (`plan_id`) REFERENCES `vc_vpn_plans` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
