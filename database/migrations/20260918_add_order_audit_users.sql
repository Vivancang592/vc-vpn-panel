SET @schema_name = DATABASE();

SET @statement = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'vc_orders' AND column_name = 'created_by') = 0,
    'ALTER TABLE vc_orders ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER purchase_ip',
    'SELECT 1'
);
PREPARE audit_statement FROM @statement;
EXECUTE audit_statement;
DEALLOCATE PREPARE audit_statement;

SET @statement = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'vc_orders' AND column_name = 'approved_by') = 0,
    'ALTER TABLE vc_orders ADD COLUMN approved_by BIGINT UNSIGNED NULL AFTER created_by',
    'SELECT 1'
);
PREPARE audit_statement FROM @statement;
EXECUTE audit_statement;
DEALLOCATE PREPARE audit_statement;