-- =============================================================
-- 20261006: SỐ IP ĐANG KẾT NỐI THEO INBOUND (Phase 2)
--            (vc_node_inbounds.connected_devices)
--
-- Flow (xem docs/task-contract.md mục 8–10):
--   Script VPS query Clash API 127.0.0.1:9090/connections
--   → report_inbounds mỗi phút kèm connected_devices
--     (số IP ĐANG kết nối vào inbound, khác với `status`
--      vốn là trạng thái mở cổng — giữ nguyên semantics
--      để không ảnh hưởng cấp link subscription).
--
-- An toàn khi chạy lại nhiều lần (kiểm tra cột trước khi ALTER).
-- =============================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_node_inbounds'
      AND COLUMN_NAME   = 'connected_devices'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_node_inbounds`
        ADD COLUMN `connected_devices` INT NOT NULL DEFAULT 0 AFTER `status`',
    'SELECT ''connected_devices đã tồn tại — bỏ qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
