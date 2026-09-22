SET @schema_name = DATABASE();

SET @statement = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'vc_orders' AND column_name = 'subscription_id') = 0,
    'ALTER TABLE vc_orders ADD COLUMN subscription_id BIGINT UNSIGNED NULL AFTER plan_id, ADD CONSTRAINT fk_vc_orders_subscription_id FOREIGN KEY (subscription_id) REFERENCES vc_subscriptions(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;