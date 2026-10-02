-- Số lượng NULL = bán không giới hạn, 0 = hết hàng.
ALTER TABLE `vc_vpn_plans`
    ADD COLUMN `stock_quantity` INT UNSIGNED NULL DEFAULT NULL AFTER `max_devices`;

-- Đánh dấu đơn mua mới đã giữ một suất tồn kho để chỉ hoàn lại đúng một lần.
ALTER TABLE `vc_orders`
    ADD COLUMN `stock_reserved` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_status`;