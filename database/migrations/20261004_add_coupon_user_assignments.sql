-- =====================================================================
-- VC VPN 2027 — COUPON ↔ USER ASSIGNMENTS (ADDITIVE MIGRATION)
-- File      : database/migrations/20261004_add_coupon_user_assignments.sql
-- Strategy  : ADDITIVE-FIRST
--             - Chỉ THÊM 1 bảng mới vc_coupon_users (bảng gán mã giảm giá
--               riêng cho user: coupon_id + user_id)
--             - KHÔNG ALTER / DROP / UPDATE bảng cũ
--             - Idempotent: CREATE TABLE IF NOT EXISTS (chạy lại an toàn)
-- Semantics : Không có bản ghi = mã CÔNG KHAI (mọi user dùng được).
--             Có bản ghi = chỉ các user được gán mới dùng được mã đó.
--             Xóa coupon → CASCADE xóa gán; xóa user → CASCADE xóa gán.
-- Conventions: InnoDB, utf8mb4 / utf8mb4_unicode_ci (khớp vc_coupons/vc_users)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `vc_coupon_users` (
    `coupon_id` INT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`coupon_id`, `user_id`),
    KEY `idx_coupon_users_user` (`user_id`),
    CONSTRAINT `fk_coupon_users_coupon` FOREIGN KEY (`coupon_id`)
        REFERENCES `vc_coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_coupon_users_user` FOREIGN KEY (`user_id`)
        REFERENCES `vc_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
