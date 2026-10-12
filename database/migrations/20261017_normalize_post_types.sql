-- 20261017: Chuẩn hóa phân loại bài viết
--   - `news` (không trang nào hiển thị) -> `tutorial` (khu hướng dẫn)
--   - `popup` GIỮ NGUYÊN — hiển thị popup thông báo trên trang user
-- Chạy TRƯỚC khi đổi ENUM; idempotent (chạy lại không ảnh hưởng).
UPDATE vc_posts SET `type` = 'tutorial'  WHERE `type` = 'news';
ALTER TABLE vc_posts MODIFY `type` ENUM('tutorial', 'faq', 'popup') NOT NULL DEFAULT 'tutorial';
