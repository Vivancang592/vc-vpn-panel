-- =============================================================
-- 20261002: Task AI chỉ là nhật ký thực thi ngắn hạn.
-- Giữ output/bài viết khi task cũ bị dọn: task_id được phép NULL
-- và FK chuyển từ CASCADE sang SET NULL.
-- =============================================================

ALTER TABLE `vc_ai_outputs`
    DROP FOREIGN KEY `fk_ai_outputs_task`;

ALTER TABLE `vc_ai_outputs`
    MODIFY COLUMN `task_id` BIGINT UNSIGNED NULL;

ALTER TABLE `vc_ai_outputs`
    ADD CONSTRAINT `fk_ai_outputs_task`
        FOREIGN KEY (`task_id`) REFERENCES `vc_ai_tasks`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `vc_ai_tasks`
    ADD KEY `idx_ai_tasks_retention` (`status`, `finished_at`);