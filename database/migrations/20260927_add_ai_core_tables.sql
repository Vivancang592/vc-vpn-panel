-- =====================================================================
-- VC VPN 2027 — AI CORE TABLES (ADDITIVE MIGRATION)
-- File      : database/migrations/20260927_add_ai_core_tables.sql
-- Phase     : Phase 5 (AI Core skeleton + Kira provider + additive DB)
-- Strategy  : ADDITIVE-FIRST / STRANGLER PATTERN
--             - Chỉ THÊM 7 bảng mới vc_ai_* (P6: 7 bảng business không caller
--               đã bị xoá — xem 20260928_drop_unused_ai_tables.sql; P9: 2 bảng
--               prompt DB đã bị xoá — xem 20260928_drop_prompt_tables.sql,
--               prompt chỉ còn lưu FILE storage/prompts/)
--             - KHÔNG ALTER / DROP bất kỳ bảng cũ nào
--             - KHÔNG UPDATE / DELETE dữ liệu cũ
--             - Idempotent: dùng CREATE TABLE IF NOT EXISTS (chạy lại an toàn)
-- Conventions: InnoDB, utf8mb4 / utf8mb4_unicode_ci,
--             PK BIGINT UNSIGNED AUTO_INCREMENT, DATETIME timestamps,
--             FK đặt tên tường minh + ON DELETE/ON UPDATE rõ ràng.
-- Lưu ý thiết kế:
--   * Cột "con trỏ vòng" (vc_ai_outputs.current_version_id /
--     approved_version_id / final_version_id) chỉ tạo INDEX, KHÔNG tạo
--     FOREIGN KEY để tránh circular FK.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. vc_ai_models — CONFIG: catalog model (Kira)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_models` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider` VARCHAR(30) NOT NULL DEFAULT 'kira',
    `model_key` VARCHAR(120) NOT NULL,
    `model_name` VARCHAR(150) NOT NULL,
    `capability` VARCHAR(30) NOT NULL,
    `input_types` JSON NULL,
    `output_types` JSON NULL,
    `limits` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ai_models_provider_key` (`provider`, `model_key`),
    KEY `idx_ai_models_provider` (`provider`),
    KEY `idx_ai_models_capability` (`capability`),
    KEY `idx_ai_models_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. vc_ai_modules — CONFIG: 6 module AI của Admin
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_modules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_key` VARCHAR(50) NOT NULL,
    `module_name` VARCHAR(150) NOT NULL,
    `capability` VARCHAR(30) NOT NULL,
    `is_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `default_model_id` BIGINT UNSIGNED NULL,
    `config` JSON NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ai_modules_key` (`module_key`),
    KEY `idx_ai_modules_capability` (`capability`),
    CONSTRAINT `fk_ai_modules_default_model` FOREIGN KEY (`default_model_id`) REFERENCES `vc_ai_models`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. vc_ai_tasks — TASK: đơn vị công việc AI (queue / lock / retry / idempotency)
-- (P9: bỏ 2 bảng prompt DB + cột prompt_version_id — prompt chỉ lưu FILE)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_tasks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `model_id` BIGINT UNSIGNED NULL,
    `task_type` VARCHAR(40) NOT NULL,
    `status` ENUM('pending','queued','processing','completed','failed','cancelled','retrying') NOT NULL DEFAULT 'pending',
    `idempotency_key` CHAR(40) NULL,
    `payload` JSON NULL,
    `params` JSON NULL,
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `retry_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `retry_after` DATETIME NULL,
    `locked_at` DATETIME NULL,
    `locked_by` VARCHAR(64) NULL,
    `lock_token` CHAR(36) NULL,
    `scheduled_at` DATETIME NULL,
    `started_at` DATETIME NULL,
    `finished_at` DATETIME NULL,
    `error_code` VARCHAR(40) NULL,
    `error_message` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ai_tasks_idem` (`idempotency_key`),
    KEY `idx_ai_tasks_queue` (`status`, `scheduled_at`),
    KEY `idx_ai_tasks_lock` (`status`, `locked_at`),
    KEY `idx_ai_tasks_module` (`module_id`, `status`),
    KEY `idx_ai_tasks_type` (`task_type`),
    CONSTRAINT `fk_ai_tasks_module` FOREIGN KEY (`module_id`) REFERENCES `vc_ai_modules`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_tasks_model` FOREIGN KEY (`model_id`) REFERENCES `vc_ai_models`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_tasks_user` FOREIGN KEY (`created_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. vc_ai_task_activities — ACTIVITY: nhật ký tiến trình + lịch sử retry (append-only)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_task_activities` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id` BIGINT UNSIGNED NOT NULL,
    `attempt_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `activity_type` VARCHAR(40) NOT NULL,
    `status` VARCHAR(30) NULL,
    `message` TEXT NULL,
    `error_code` VARCHAR(40) NULL,
    `meta` JSON NULL,
    `duration_ms` INT UNSIGNED NULL,
    `http_status` SMALLINT UNSIGNED NULL,
    `retry_in_ms` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ai_task_activities_task` (`task_id`, `created_at`),
    CONSTRAINT `fk_ai_task_activities_task` FOREIGN KEY (`task_id`) REFERENCES `vc_ai_tasks`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. vc_ai_assets — ASSET: quản lý file vật lý TẬP TRUNG (mọi file AI đi qua đây)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_assets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `asset_kind` VARCHAR(20) NOT NULL,
    `relative_path` VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(255) NULL,
    `mime_type` VARCHAR(120) NULL,
    `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `checksum` CHAR(64) NULL,
    `storage_disk` VARCHAR(30) NOT NULL DEFAULT 'public',
    `duration_seconds` DECIMAL(10,2) NULL,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `ref_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_orphan` TINYINT(1) NOT NULL DEFAULT 0,
    `orphaned_at` DATETIME NULL,
    `metadata` JSON NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ai_assets_path` (`relative_path`),
    KEY `idx_ai_assets_checksum` (`checksum`),
    KEY `idx_ai_assets_orphan` (`is_orphan`, `orphaned_at`),
    CONSTRAINT `fk_ai_assets_user` FOREIGN KEY (`created_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. vc_ai_outputs — OUTPUT: kết quả trừu tượng của task (giữ con trỏ version)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_outputs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id` BIGINT UNSIGNED NOT NULL,
    `module_id` BIGINT UNSIGNED NOT NULL,
    `output_type` VARCHAR(30) NOT NULL,
    `review_status` ENUM('draft','generated','awaiting_review','revision_requested','revised','approved','rejected','completed') NOT NULL DEFAULT 'draft',
    `current_version_id` BIGINT UNSIGNED NULL,
    `approved_version_id` BIGINT UNSIGNED NULL,
    `final_version_id` BIGINT UNSIGNED NULL,
    `version_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ai_outputs_task` (`task_id`),
    KEY `idx_ai_outputs_review_status` (`review_status`),
    KEY `idx_ai_outputs_current_version` (`current_version_id`),
    KEY `idx_ai_outputs_approved_version` (`approved_version_id`),
    KEY `idx_ai_outputs_final_version` (`final_version_id`),
    CONSTRAINT `fk_ai_outputs_task` FOREIGN KEY (`task_id`) REFERENCES `vc_ai_tasks`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_outputs_module` FOREIGN KEY (`module_id`) REFERENCES `vc_ai_modules`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_outputs_user` FOREIGN KEY (`created_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. vc_ai_output_versions — OUTPUT VERSION: từng bản (APPEND-ONLY, không bao giờ xóa)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vc_ai_output_versions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `output_id` BIGINT UNSIGNED NOT NULL,
    `version_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `previous_version_id` BIGINT UNSIGNED NULL,
    `content_snapshot` LONGTEXT NULL,
    `asset_id` BIGINT UNSIGNED NULL,
    `provider` VARCHAR(30) NULL,
    `model` VARCHAR(120) NULL,
    `review_status` ENUM('draft','generated','awaiting_review','revision_requested','revised','approved','rejected','completed') NOT NULL DEFAULT 'generated',
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
    `is_final` TINYINT(1) NOT NULL DEFAULT 0,
    `revision_reason` TEXT NULL,
    `review_note` TEXT NULL,
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `requested_by` BIGINT UNSIGNED NULL,
    `meta` JSON NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ai_output_versions_no` (`output_id`, `version_no`),
    KEY `idx_ai_output_versions_review` (`output_id`, `review_status`),
    CONSTRAINT `fk_ai_output_versions_output` FOREIGN KEY (`output_id`) REFERENCES `vc_ai_outputs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_output_versions_previous` FOREIGN KEY (`previous_version_id`) REFERENCES `vc_ai_output_versions`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_output_versions_asset` FOREIGN KEY (`asset_id`) REFERENCES `vc_ai_assets`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_output_versions_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_output_versions_requester` FOREIGN KEY (`requested_by`) REFERENCES `vc_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

