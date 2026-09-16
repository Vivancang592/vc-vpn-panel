ALTER TABLE `vc_orders`
    ADD COLUMN `payment_method` VARCHAR(50) NOT NULL DEFAULT 'vietqr' AFTER `total_amount`;