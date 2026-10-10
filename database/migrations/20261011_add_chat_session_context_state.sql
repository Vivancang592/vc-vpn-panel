-- ============================================================================
-- Migration: ngữ cảnh cuộn cho AI chat khách hàng (BƯỚC 3 — luồng chat)
-- Ngày     : 2026-10-11
-- Phạm vi  : CHỈ thêm 2 cột vào vc_chat_sessions. Không sửa/xóa cột khác,
--            không đụng bảng khác.
-- Idempotent: chạy lại lần 2 KHÔNG lỗi (kiểm tra information_schema trước).
--
-- - summary        : bản tóm tắt tích lũy các tin CŨ NHẤT đã trôi ra ngoài
--                    cửa sổ token → AI vẫn nhớ đầu cuộc hội thoại.
-- - covered_through: số tin đầu tiên đã nằm trong summary (mốc đánh dấu).
-- ============================================================================

SET @has_summary := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_chat_sessions'
      AND COLUMN_NAME  = 'summary'
);
SET @sql := IF(
    @has_summary = 0,
    'ALTER TABLE `vc_chat_sessions`
        ADD COLUMN `summary` MEDIUMTEXT NULL
            COMMENT ''bản tóm tắt tích lũy các tin cũ nhất đã trôi ra ngoài cửa sổ token''
            AFTER `status`,
        ADD COLUMN `covered_through` INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''số tin đầu cuộc hội thoại đã nằm trong summary''
            AFTER `summary`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
