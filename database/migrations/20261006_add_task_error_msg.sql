-- =============================================================
-- 20261006: CỘT error_msg CHO vc_node_tasks
--            (contract §4 — update_task_status)
--
-- VPS báo task_status=error kèm error_msg; trước đây model nhận
-- tham số nhưng không ghi (thiếu cột) → error_msg bị bỏ.
--
-- An toàn khi chạy lại nhiều lần (kiểm tra cột trước khi ALTER).
-- =============================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_node_tasks'
      AND COLUMN_NAME   = 'error_msg'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_node_tasks`
        ADD COLUMN `error_msg` TEXT NULL AFTER `attempts`',
    'SELECT ''error_msg đã tồn tại — bỏ qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
