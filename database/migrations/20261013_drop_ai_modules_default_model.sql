-- 20261013: Bỏ hoàn toàn "model mặc định ngầm" khỏi vc_ai_modules.
-- Admin CHỌN model trực tiếp trong từng tab AI (bắt buộc) — không còn cột
-- default_model_id lẫn FK liên kết vc_ai_models.

ALTER TABLE `vc_ai_modules` DROP FOREIGN KEY `fk_ai_modules_default_model`;
ALTER TABLE `vc_ai_modules` DROP COLUMN `default_model_id`;
