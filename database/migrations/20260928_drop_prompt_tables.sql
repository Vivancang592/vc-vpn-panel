-- =====================================================================
-- Migration: DROP 2 bảng prompt DB (P9 — prompt chỉ còn lưu FILE)
-- Thời gian: 2026-09-28
--
-- Bối cảnh:
--   Quyết định kiến trúc của Admin: prompt AI CHỈ lưu ở FILE
--   (storage/prompts/{key}.txt) — KHÔNG lưu DB nữa. Chuỗi fallback
--   rút gọn thành: FILE → mặc định trong code (PromptRegistry).
--
-- Bảng bị DROP (0 dòng dữ liệu trên DB dev, xác minh trước khi tạo file này):
--   - vc_ai_prompt_versions (FK từ vc_ai_tasks.prompt_version_id trỏ vào đây)
--   - vc_ai_prompts
--
-- Thứ tự DROP: bảng con trước (prompt_versions), bảng cha sau (prompts).
-- FK phụ thuộc phải gỡ thủ công TRƯỚC:
--   - fk_ai_tasks_prompt_version (vc_ai_tasks.prompt_version_id → vc_ai_prompt_versions.id)
--     Cột vc_ai_tasks.prompt_version_id được DROP luôn (P9: không còn nguồn DB,
--     TaskDispatcher::create không ghi cột này nữa) → schema sạch, không dead column.
--   - fk_ai_prompt_versions_prompt/model/user (bị DROP cùng bảng con).
--
-- P9 cũng XOÁ khỏi code:
--   - app/Models/AIPrompt.php, app/Models/AIPromptVersion.php
--   - From-database fallback trong PromptRegistry (chuyển thành FILE → default)
--   - Các action store/newVersion/activate/publish trong AiPromptController
--   - Route POST /admin/ai/prompts/store|new-version|activate|publish
--
-- idempotent: DROP TABLE IF EXISTS.
-- =====================================================================

-- idempotent: ALTER dùng thủ tục có điều kiện để chạy lại khi FK/cột đã gỡ;
-- DROP TABLE IF EXISTS là tự idempotent.

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_ai_tasks_prompt_version'
);

SET @ddl := IF(@fk_exists > 0,
    'ALTER TABLE `vc_ai_tasks` DROP FOREIGN KEY `fk_ai_tasks_prompt_version`',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vc_ai_tasks'
      AND COLUMN_NAME = 'prompt_version_id'
);

SET @ddl := IF(@col_exists > 0,
    'ALTER TABLE `vc_ai_tasks` DROP COLUMN `prompt_version_id`',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `vc_ai_prompt_versions`;
DROP TABLE IF EXISTS `vc_ai_prompts`;
