-- =====================================================================
-- VC VPN 2027 — P12: BỎ CỘT content_prompt (ZERO-WASTE)
-- File      : database/migrations/20260929_p12_drop_content_prompt.sql
-- Bối cảnh  : P12 — "Tab Viết Bài: admin chỉ gửi danh sách chủ đề,
--             AI tự phân tích và viết theo chủ đề".
--             · View tab-fanpage.php: đã bỏ ô "Yêu cầu thêm" Khối 1.
--             · AiTaskController::storeTopics(): payload chỉ còn {topic}.
--             · AIProviderService::generateContent(): bỏ tham số
--               $contentPrompt/$customSystemPrompt (topic là đủ).
--             → Cột vc_scheduled_posts.content_prompt không còn producer
--               nào đọc/ghi (đã grep toàn workspace) → bỏ để không rác.
-- An toàn    : Bảng vc_scheduled_posts hiện 0 dòng (đã SELECT COUNT).
--             BaseModel::create() không cần cột này (nullable, test insert
--             thiếu content_prompt thành công trước khi viết migration).
-- Idempotent : kiểm tra INFORMATION_SCHEMA trước khi ALTER (chạy lại an toàn).
-- =====================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'vc_scheduled_posts'
      AND COLUMN_NAME  = 'content_prompt'
);

SET @ddl := IF(
    @col_exists > 0,
    'ALTER TABLE `vc_scheduled_posts` DROP COLUMN `content_prompt`',
    'SELECT ''content_prompt không tồn tại — đã bỏ trước đó.'' AS info'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
