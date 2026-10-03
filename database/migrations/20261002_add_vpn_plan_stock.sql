-- Số lượng NULL = bán không giới hạn, 0 = hết hàng.
-- An toàn khi chạy lại nhiều lần (kiểm tra cột trước khi ALTER).

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_vpn_plans'
      AND COLUMN_NAME   = 'stock_quantity'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_vpn_plans` ADD COLUMN `stock_quantity` INT UNSIGNED NULL DEFAULT NULL AFTER `max_devices`',
    'SELECT ''stock_quantity đã tồn tại — bỏ qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Đánh dấu đơn mua mới đã giữ một suất tồn kho để chỉ hoàn lại đúng một lần.
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_orders'
      AND COLUMN_NAME   = 'stock_reserved'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_orders` ADD COLUMN `stock_reserved` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_status`',
    'SELECT ''stock_reserved đã tồn tại — bỏ qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;