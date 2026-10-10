-- ============================================================================
-- Migration: bản đồ giao diện (UI map) cho AI — BƯỚC 4 (mục 4.1)
-- Ngày     : 2026-10-12
-- Phạm vi  : CHỈ tạo 1 bảng mới `vc_ai_ui_map`. Không sửa/xóa bảng/cột khác.
-- Idempotent: chạy lại lần 2 KHÔNG lỗi (CREATE TABLE IF NOT EXISTS).
--
-- Mục đích:
--   - NGUỒN SỰ THẬT duy nhất về trang/nút/thao tác của giao diện người dùng,
--     dùng chung cho AI chat khách (SiteKnowledge::pages()) và AI Trợ lý Admin.
--   - Admin sửa được (qua AI assistant entity `ai_ui_map` hoặc tools/ai_ui_map_build.php)
--     mà không cần đụng code — không còn hard-code trong const PAGES.
--
-- Cột:
--   page_path     : đường dẫn trang (VD: /checkout) — khóa duy nhất.
--   requires_login: 0 = trang công khai (Facebook được phép gửi link),
--                   1 = trang thành viên (chỉ khi khách ĐÃ đăng nhập).
--   title         : tên hiển thị trang trong khối [2. BẢN ĐỒ TRANG].
--   actions_json  : JSON object { "nhãn nút" => "hành động thực tế", ... }.
--   view_path      : file view nguồn lúc quét (resources/views/...) — để re-scan.
--   source        : const (khởi tạo từ SiteKnowledge) | scanner (quét view) | manual.
--   is_active     : 0 = ẩn khỏi bản đồ (giữ lại thay vì xóa cứng).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vc_ai_ui_map` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_path` VARCHAR(191) NOT NULL COMMENT 'đường dẫn trang, VD /checkout',
    `requires_login` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = trang thành viên',
    `title` VARCHAR(191) NOT NULL COMMENT 'tên trang hiển thị cho AI',
    `actions_json` MEDIUMTEXT NOT NULL COMMENT 'JSON {nhãn nút => hành động}',
    `view_path` VARCHAR(191) NOT NULL DEFAULT '' COMMENT 'view nguồn lúc quét',
    `source` VARCHAR(16) NOT NULL DEFAULT 'const' COMMENT 'const|scanner|manual',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = đang dùng',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ai_ui_map_path` (`page_path`),
    KEY `idx_ai_ui_map_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
