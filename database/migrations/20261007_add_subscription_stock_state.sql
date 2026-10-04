-- =============================================================
-- 20261007: CO HOAN LUOT MUA KHI GOI CUOC BI HUY
--            (vc_subscriptions.stock_state)
--
-- Design (dong thoi voi code):
--   + none     = khong giu suot (trial / goi khong gioi han / don khong tru stock)
--   + held     = dang giu 1 suot (don mua da tru stock_quantity cua goi)
--   + released = da hoan suot (goi chuyen cancelled / xoa sub)
--
-- Luot hoan idempotent (chi xay ra 1 lan moi suot):
--   held -> released + stock_quantity + 1 khi sub bi huy
--   (admin updateStatus / xoa sub / cron expired->cancelled).
-- Khi bat lai (cancelled -> active/suspended): released -> held
--   (tru lai stock; het hang thi chan, khong doi trang thai).
--
-- An toan khi chay lai nhieu lan (kiem tra cot truoc khi ALTER).
-- =============================================================

-- 1. Them cot stock_state
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_subscriptions'
      AND COLUMN_NAME  = 'stock_state'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_subscriptions`
        ADD COLUMN `stock_state` ENUM(''none'', ''held'', ''released'') NOT NULL DEFAULT ''none'' AFTER `order_id`',
    'SELECT ''stock_state da ton tai — bo qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Backfill: sub con hieu luc co don da tru stock -> dang giu suot
--    (khong doi voi don renewal: stock_reserved = 0)
UPDATE `vc_subscriptions` s
INNER JOIN `vc_orders` o ON o.`id` = s.`order_id`
SET s.`stock_state` = 'held'
WHERE s.`status` <> 'cancelled'
  AND s.`stock_state` = 'none'
  AND o.`stock_reserved` = 1;

-- 3. Backfill luot mua cu: sub da huy ma don tru stock -> danh dau da hoan
--    va cong lai ton kho goi (idempotent: chay xong stock_state = 'released'
--    se khong khop WHERE nua, khong cong tru 2 lan).
--    Goi khong gioi han (stock_quantity NULL) van giu NULL.
UPDATE `vc_subscriptions` s
INNER JOIN `vc_orders` o ON o.`id` = s.`order_id`
INNER JOIN `vc_vpn_plans` p ON p.`id` = s.`plan_id`
SET s.`stock_state` = 'released',
    p.`stock_quantity` = IF(p.`stock_quantity` IS NULL, NULL, p.`stock_quantity` + 1)
WHERE s.`status` = 'cancelled'
  AND s.`stock_state` = 'none'
  AND o.`stock_reserved` = 1;
