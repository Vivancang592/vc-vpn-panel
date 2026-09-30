-- =====================================================================
-- VC VPN 2027 — DROP UNUSED AI TABLES (P6 / Phase C)
-- File      : database/migrations/20260928_drop_unused_ai_tables.sql
-- Strategy  : CLEANUP / ZERO-WASTE
--             Xoá 7 bảng vc_ai_* KHÔNG có bất kỳ caller nào trong code
--             (đã kiểm chứng bằng grep toàn workspace + selftest F19):
--               vc_ai_articles, vc_ai_images, vc_ai_videos, vc_ai_audios,
--               vc_ai_fanpage_comments, vc_ai_comment_replies, vc_ai_usage_logs.
--             Dữ liệu nghiệp vụ AI thực tế nằm hoàn toàn trong 9 bảng:
--               vc_ai_models, vc_ai_modules, vc_ai_prompts, vc_ai_prompt_versions,
--               vc_ai_tasks, vc_ai_task_activities, vc_ai_assets,
--               vc_ai_outputs, vc_ai_output_versions.
-- Thứ tự DROP: con TRƯỚC cha (chỉ trong nhóm bảng chết) để FK không chặn:
--   vc_ai_comment_replies → vc_ai_fanpage_comments
--   vc_ai_audios          → vc_ai_videos
--   các bảng còn lại chỉ có FK trỏ RA NGOÀI nhóm → xoá tự do.
-- Idempotent: DROP TABLE IF EXISTS (chạy lại an toàn).
-- Lưu ý: op id LRO của video sống trong vc_ai_tasks.params (JSON),
--        KHÔNG phụ thuộc cột kira_operation_id của bảng vc_ai_videos bị xoá.
-- =====================================================================

DROP TABLE IF EXISTS `vc_ai_comment_replies`;
DROP TABLE IF EXISTS `vc_ai_audios`;
DROP TABLE IF EXISTS `vc_ai_videos`;
DROP TABLE IF EXISTS `vc_ai_articles`;
DROP TABLE IF EXISTS `vc_ai_images`;
DROP TABLE IF EXISTS `vc_ai_usage_logs`;
DROP TABLE IF EXISTS `vc_ai_fanpage_comments`;
