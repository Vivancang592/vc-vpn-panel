-- ============================================================
-- 20261016_drop_ai_system.sql
-- XOÁ SẠCH TOÀN BỘ HỆ THỐNG AI (PHẦN 13):
--   - 18 bảng AI/chat/campaign/fanpage/remarketing/scheduled_post
--   - key cài đặt AI trong vc_settings
--   - nhật ký hành động AI trong vc_system_logs
-- Idempotent (IF EXISTS) — an toàn khi chạy lặp trên mọi install/update.
-- Vô hiệu hoá FK check tạm thời: các bảng AI có FK vòng (output_versions → assets).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Xoá 18 bảng AI ----------------------------------------------------------
DROP TABLE IF EXISTS `vc_ai_assets`;
DROP TABLE IF EXISTS `vc_ai_conversation_state`;
DROP TABLE IF EXISTS `vc_ai_models`;
DROP TABLE IF EXISTS `vc_ai_modules`;
DROP TABLE IF EXISTS `vc_ai_output_versions`;
DROP TABLE IF EXISTS `vc_ai_outputs`;
DROP TABLE IF EXISTS `vc_ai_task_activities`;
DROP TABLE IF EXISTS `vc_ai_tasks`;
DROP TABLE IF EXISTS `vc_ai_tool_calls`;
DROP TABLE IF EXISTS `vc_ai_ui_map`;
DROP TABLE IF EXISTS `vc_chat_ai_cache`;
DROP TABLE IF EXISTS `vc_chat_events`;
DROP TABLE IF EXISTS `vc_chat_messages`;
DROP TABLE IF EXISTS `vc_chat_sessions`;
DROP TABLE IF EXISTS `vc_campaign_sends`;
DROP TABLE IF EXISTS `vc_campaigns`;
DROP TABLE IF EXISTS `vc_fb_contacts`;
DROP TABLE IF EXISTS `vc_scheduled_posts`;

-- 2. Xoá key cài đặt AI trong vc_settings ------------------------------------
DELETE FROM `vc_settings`
WHERE `setting_key` IN (
    'ai_active_provider',
    'ai_api_key_openai',
    'ai_api_key_openrouter',
    'ai_cache_ttl_minutes',
    'ai_chatbot_enabled',
    'ai_cooldown_seconds',
    'ai_duplicate_window_seconds',
    'ai_max_output_tokens',
    'ai_rate_limit_per_minute',
    'ai_system_prompt',
    'kira_api_key',
    'fanpage_verify_token',
    'fanpage_app_secret',
    'fanpage_page_access_token'
);

-- 3. Xoá nhật ký hành động AI trong vc_system_logs ---------------------------
DELETE FROM `vc_system_logs`
WHERE UPPER(`action`) LIKE 'AI\_%';

SET FOREIGN_KEY_CHECKS = 1;
