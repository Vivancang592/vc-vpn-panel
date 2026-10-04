-- =============================================================
-- 20261004: DEM LUOT SU DUNG MA GIAM GIA THEO TUNG DON HANG
--            (vc_orders.coupon_counted)
--
-- Design (dong thoi voi code):
--   + Luot su dung +1 NGAY KHI tao don (giu cho), khong cho den luc duyet.
--   + Hoan luot (-1) khi don bi huy / tu choi / het han / xoa.
--   + coupon_counted = 1 neu don DANG giu 1 luot (pending hoac completed)
--     de moi don chi +1/-1 DUNG 1 lan (idempotent), tranh cong/tru loi.
--
-- Backfill: don cu (pending/completed co coupon) -> danh dau da tinh luot,
--           roi resync used_count = so don dang giu luot hien tai.
--
-- An toan khi chay lai nhieu lan (kiem tra cot truoc khi ALTER).
-- =============================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_orders'
      AND COLUMN_NAME  = 'coupon_counted'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_orders`
        ADD COLUMN `coupon_counted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `coupon_id`',
    'SELECT ''coupon_counted da ton tai — bo qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: don con hieu luc (pending/completed) co ma giam gia = dang giu luot.
-- Don failed/cancelled giu mac dinh 0 (khong tinh luot).
UPDATE `vc_orders`
SET `coupon_counted` = 1
WHERE `coupon_id` IS NOT NULL
  AND `payment_status` IN ('pending', 'completed');

-- Resync used_count = so don hien con dang giu luot cho moi ma.
UPDATE `vc_coupons` c
SET `used_count` = (
    SELECT COUNT(*)
    FROM `vc_orders` o
    WHERE o.`coupon_id` = c.`id`
      AND o.`coupon_counted` = 1
      AND o.`payment_status` IN ('pending', 'completed')
);
