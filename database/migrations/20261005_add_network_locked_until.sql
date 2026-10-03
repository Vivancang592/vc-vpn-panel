-- =============================================================
-- 20261005: CƠ CHẾ KHÓA MẠNG 1 PHÚT KHI VƯỢT SỐ THIẾT BỊ
--            (vc_subscriptions.network_locked_until)
--
-- Flow (xem docs/task-contract.md mục 3):
--   report_traffic thấy ip_count > max_devices
--   → network_locked_until = NOW() + 60s
--   → sinh task disable_user{reason: device_limit, lock_seconds: 60}
--   → script VPS cắt mạng, tự mở lại sau 60s.
--   Cột này vừa là "cờ chống spam task" (không khóa lặp trong 60s),
--   vừa là mốc thời gian tham chiếu cho kiểm tra trạng thái.
--
-- An toàn khi chạy lại nhiều lần (kiểm tra cột trước khi ALTER).
-- =============================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_subscriptions'
      AND COLUMN_NAME   = 'network_locked_until'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_subscriptions`
        ADD COLUMN `network_locked_until` TIMESTAMP NULL DEFAULT NULL AFTER `online_devices`,
        ADD KEY `idx_subscriptions_network_locked` (`network_locked_until`)',
    'SELECT ''network_locked_until đã tồn tại — bỏ qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
