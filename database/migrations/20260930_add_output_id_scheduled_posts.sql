-- =============================================================
-- 20260930: LIÊN KẾT Bài Viết AI (vc_ai_outputs) ↔ Hàng đợi đăng Fanpage
--            (vc_scheduled_posts).
--
-- Flow mới (trang /admin/ai/outputs):
--   Bài AI (vc_ai_outputs) --[Lên lịch]--> dòng vc_scheduled_posts.output_id
--   --> CronController::autoPostFanpage tự đăng lên Fanpage.
--
-- An toàn khi chạy lại nhiều lần (kiểm tra cột trước khi ALTER).
-- =============================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_scheduled_posts'
      AND COLUMN_NAME  = 'output_id'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `vc_scheduled_posts`
        ADD COLUMN `output_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`,
        ADD KEY `idx_scheduled_posts_output` (`output_id`)',
    'SELECT ''output_id đã tồn tại — bỏ qua.'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
