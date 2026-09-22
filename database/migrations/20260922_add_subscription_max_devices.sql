SET @schema_name = DATABASE();

SET @statement = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'vc_subscriptions' AND column_name = 'max_devices') = 0,
    'ALTER TABLE vc_subscriptions ADD COLUMN max_devices INT NOT NULL DEFAULT 1 AFTER uuid',
    'SELECT 1'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
