SET @schema_name = DATABASE();

SET @statement = IF(
    (SELECT IS_NULLABLE
     FROM information_schema.columns
     WHERE table_schema = @schema_name
       AND table_name = 'vc_orders'
       AND column_name = 'plan_id') = 'NO',
    'ALTER TABLE vc_orders MODIFY COLUMN plan_id INT UNSIGNED NULL',
    'SELECT 1'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
