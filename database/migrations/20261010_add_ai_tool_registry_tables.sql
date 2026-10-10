-- ============================================================================
-- Migration: bảng nền cho Trợ Lý Admin (BƯỚC 1 — nâng cấp luồng AI admin)
-- Ngày     : 2026-10-10
-- Phạm vi  : CHỈ thêm bảng mới. Không sửa/xóa bất kỳ bảng/cột nào đã tồn tại.
-- Idempotent: chạy lại lần 2 KHÔNG lỗi (CREATE TABLE IF NOT EXISTS).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- vc_ai_tool_calls — audit log MỌI lượt gọi công cụ của trợ lý AI admin.
-- Ghi nhận: ai gọi tool nào, tham số gì, rủi ro mức nào, kết quả ra sao,
-- admin nào (vc_users.id qua phiên đăng nhập) và mất bao lâu.
-- before_json/after_json: snapshot trước/sau khi ghi (dùng cho lớp CRUD bước 2;
-- các tool hiện hành có thể để trống).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_tool_calls` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'id đoạn chat trợ lý (storage/assistant/chats)',
  `admin_id` INT UNSIGNED NULL COMMENT 'người quản trị thực hiện phiên chat',
  `tool` VARCHAR(64) NOT NULL COMMENT 'tên công cụ đã chạy',
  `risk` VARCHAR(16) NOT NULL DEFAULT 'read' COMMENT 'read | write | destructive',
  `args_json` MEDIUMTEXT NULL COMMENT 'tham số AI đưa vào (đã rút gọn + ẩn mật)',
  `before_json` MEDIUMTEXT NULL COMMENT 'snapshot dữ liệu TRƯỚC khi ghi',
  `after_json` MEDIUMTEXT NULL COMMENT 'snapshot dữ liệu SAU khi ghi',
  `result_json` MEDIUMTEXT NULL COMMENT 'kết quả trả về cho AI (đã rút gọn)',
  `ok` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'tool chạy thành công',
  `error` VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'thông báo lỗi (nếu có)',
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'thời gian chạy tool',
  `confirmed_by` INT UNSIGNED NULL COMMENT 'admin bấm nút xác nhận hành động (nếu qua cổng xác nhận)',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tool_calls_conv` (`conversation_id`, `id`),
  KEY `idx_tool_calls_tool` (`tool`, `created_at`),
  KEY `idx_tool_calls_admin` (`admin_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- vc_ai_conversation_state — trạng thái ngữ cảnh dài của từng đoạn chat.
-- Khi hội thoại vượt ngân sách token, phần tin cũ nhất được TÓM TẮT tích lũy
-- (rolling summary) và gửi kèm system prompt → AI vẫn "nhớ" toàn bộ cuộc trò
-- chuyện từ đầu, không mất dữ kiện.
-- covered_through = số tin nhắn (theo JSONL) đã được tóm tắt xong.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_conversation_state` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` VARCHAR(64) NOT NULL COMMENT 'id đoạn chat trợ lý',
  `summary` MEDIUMTEXT NULL COMMENT 'bản tóm tắt tích lũy các tin cũ nhất',
  `covered_through` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'số tin đã nằm trong summary',
  `tokens_in` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ước lượng token của prompt lượt gần nhất',
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_state` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
