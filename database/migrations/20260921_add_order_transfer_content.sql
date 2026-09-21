SET @schema_name = DATABASE();

SET @statement = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'vc_orders' AND column_name = 'transfer_content') = 0,
    'ALTER TABLE vc_orders ADD COLUMN transfer_content VARCHAR(100) NULL AFTER payment_method',
    'SELECT 1'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
