<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AIModule;
use App\Models\AIOutput;
use App\Models\AIOutputVersion;
use App\Models\ScheduledPost;

/**
 * AiOutputController — QUẢN LÝ BÀI VIẾT AI (detail / schedule / delete).
 *
 * DANH SÁCH bài viết ĐÃ CHUYỂN SANG tab Nội Dung Fanpage
 * (view tab-fanpage.php, AiFanpageController::index) — không còn trang
 * resources/views/admin/ai/outputs.php riêng nữa (view đã xoá).
 *
 * Luồng nghiệp vụ còn lại (API cho tab Nội Dung Fanpage):
 *   1. Xem bài viết       → GET  /admin/ai/outputs/detail   (chỉ đọc)
 *   2. Copy prompt tạo ảnh → JS clipboard (prompt tách từ nội dung bài)
 *   3. Lên lịch            → POST /admin/ai/outputs/schedule
 *                            tạo dòng vc_scheduled_posts (output_id) →
 *                            CronController::autoPostFanpage tự đăng bài
 *   4. Xóa bài viết        → POST /admin/ai/outputs/delete
 *                            (xóa output + versions[cascade] + hàng đợi liên kết)
 *   + GET  /admin/ai/outputs (URL danh sách cũ, routes/web.php — Section H
 *     cấm sửa) → index() redirect về /admin/ai/fanpage.
 *
 * 3 tab lọc (Tất cả / Đang lên lịch / Đã đăng) + POST_STATUS kế thừa từ
 * AiBaseController (OUTPUT_TABS). Trạng thái SUY RA từ vc_scheduled_posts;
 * review_status giữ nguyên trong DB (enum F14) nhưng không hiển thị ở giao diện.
 */
final class AiOutputController extends AiBaseController
{
    /**
     * URL DANH SÁCH cũ (route GET /admin/ai/outputs vẫn còn trong
     * routes/web.php — Section H cấm sửa file đó). View outputs.php đã gỡ
     * → chuyển hướng sang tab Nội Dung Fanpage (danh sách hiển thị ở đây).
     * Giữ nguyên tham số ?tab= để link lọc cũ không chết.
     */
    public function index(): void
    {
        $tab    = (string) ($_GET['tab'] ?? '');
        $suffix = (array_key_exists($tab, self::OUTPUT_TABS) && $tab !== '')
            ? '?tab=' . urlencode($tab)
            : '';
        $this->redirect('/admin/ai/fanpage' . $suffix);
    }

    /**
     * Xem chi tiết một bài viết (CHỈ ĐỌC).
     */
    public function detail(): void
    {
        $outputId = (int) ($_GET['id'] ?? 0);

        $article = null;
        try {
            $article = (new AIOutput())->articleById($outputId);
        } catch (\Throwable $e) {
            $article = null;
        }

        if (!is_array($article)) {
            $this->flash('Bài viết không tồn tại.', 'danger', '/admin/ai/outputs');
            return;
        }

        $versions = [];
        try {
            $versions = (array) (new AIOutputVersion())->byOutput($outputId);
        } catch (\Throwable $e) {
            $versions = [];
        }

        $module = null;
        try {
            $module = (new AIModule())->find((int) ($article['module_id'] ?? 0));
        } catch (\Throwable $e) {
            $module = null;
        }

        $this->render('admin.ai.output-detail', [
            'activeMenu' => 'ai-outputs',
            'pageTitle'  => 'Bài Viết #' . $outputId . ' - Quản Trị Hệ Thống',
            'article'    => $this->decorate($article),
            'versions'   => $versions,
            'module'     => is_array($module) ? $module : null,
            'postStatus' => self::POST_STATUS,
        ]);
    }

    /**
     * Lên lịch đăng bài lên Fanpage (tạo/cập nhật dòng hàng đợi chờ cron).
     *
     * Nhận thêm image_url (tùy chọn) — ảnh chọn từ thư mục tab Tạo Ảnh để
     * cron đăng kèm; không truyền = bỏ ảnh cũ đã chọn trước đó.
     */
    public function schedule(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->flash('Phương thức không hợp lệ.', 'danger', '/admin/ai/outputs');
            return;
        }

        if (!$this->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/outputs');
            return;
        }

        $outputId = (int) ($_POST['output_id'] ?? 0);
        $date     = trim((string) ($_POST['schedule_date'] ?? ''));
        $time     = trim((string) ($_POST['schedule_time'] ?? ''));
        $back     = '/admin/ai/outputs/detail?id=' . $outputId;

        $article = null;
        try {
            $article = (new AIOutput())->articleById($outputId);
        } catch (\Throwable $e) {
            $article = null;
        }

        if (!is_array($article)) {
            $this->flash('Bài viết không tồn tại.', 'danger', '/admin/ai/outputs');
            return;
        }

        // Row thô từ DB → thêm title/body/image_prompt như trang danh sách.
        $article = $this->decorate($article);

        // Kiểm tra ngày/giờ đăng bài (YYYY-MM-DD + HH:MM).
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        if ($dt === false || $dt->format('Y-m-d H:i') !== ($date . ' ' . $time)) {
            $this->flash('Ngày hoặc giờ đăng bài không hợp lệ.', 'danger', $back);
            return;
        }

        if ($dt->getTimestamp() <= time()) {
            $this->flash('Thời gian đăng bài phải lớn hơn thời điểm hiện tại.', 'danger', $back);
            return;
        }

        $body = trim((string) ($article['body'] ?? ''));

        if ($body === '') {
            $this->flash('Bài viết này chưa có nội dung để đăng.', 'danger', $back);
            return;
        }

        // Ảnh đính kèm (chọn trong popup từ thư mục tab Tạo Ảnh):
        //   - URL lạ (không thuộc /uploads/ai/) → chặn (không nhận URL tự do).
        //   - Thuộc thư mục nhưng file đã bị xoá → tự bỏ, không chặn lịch.
        $imagePick = trim((string) ($_POST['image_url'] ?? ''));
        $imageUrl  = null;
        if ($imagePick !== '') {
            $validPath = preg_match('#^/uploads/ai/[A-Za-z0-9._\-/]+$#', $imagePick) === 1
                && !str_contains($imagePick, '..');
            if (!$validPath) {
                $this->flash('Ảnh đính kèm không hợp lệ. Hãy chọn lại trong thư mục tab Tạo Ảnh.', 'danger', $back);
                return;
            }
            if (is_file(BASE_PATH . '/public' . $imagePick)) {
                $imageUrl = $imagePick;
            }
        }

        $ok = false;
        try {
            $ok = (new ScheduledPost())->scheduleForOutput([
                'output_id'         => $outputId,
                'topic'             => (string) ($article['title'] ?? ''),
                'generated_content' => $body,
                // Không mang image_prompt vào hàng đợi: ảnh phải chọn sẵn ở thư
                // mục tab Tạo Ảnh (không chọn → đăng chữ). Cron không tự sinh ảnh.
                'image_prompt'      => null,
                'image_url'         => $imageUrl,
                'scheduled_at'      => $dt->format('Y-m-d H:i:s'),
                'created_by'        => (int) ($_SESSION['user_id'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            $this->logActivity('ai_output_schedule_failed', $e->getMessage());
            $ok = false;
        }

        if (!$ok) {
            $this->flash('Không lên lịch được bài viết. Vui lòng thử lại.', 'danger', $back);
            return;
        }

        $this->logActivity('ai_output_schedule', sprintf('Output #%d → %s.', $outputId, $dt->format('Y-m-d H:i:s')));
        $this->flash(
            'Đã lên lịch đăng bài lúc ' . $dt->format('H:i d/m/Y') . '. Bài đã vào hàng đợi, cron sẽ tự đăng lên Fanpage.',
            'success',
            '/admin/ai/outputs'
        );
    }

    /**
     * Xóa bài viết: hàng đợi liên kết trước, versions tự xóa theo FK cascade.
     * Quay về đúng tab + trang đang xem (giữ ngữ cảnh phân trang).
     */
    public function delete(): void
    {
        $filter = (string) ($_POST['tab'] ?? '');
        if (!array_key_exists($filter, self::OUTPUT_TABS)) {
            $filter = '';
        }
        $page = max(1, (int) ($_POST['page'] ?? 1));
        $qs = [];
        if ($filter !== '') {
            $qs['tab'] = $filter;
        }
        if ($page > 1) {
            $qs['page'] = $page;
        }
        $back = '/admin/ai/fanpage' . ($qs !== [] ? '?' . http_build_query($qs) : '');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->flash('Phương thức không hợp lệ.', 'danger', $back);
            return;
        }

        if (!$this->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
            return;
        }

        $outputId = (int) ($_POST['output_id'] ?? 0);

        $outputModel = new AIOutput();
        $article = null;
        try {
            $article = $outputModel->find($outputId);
        } catch (\Throwable $e) {
            $article = null;
        }

        if (!is_array($article)) {
            $this->flash('Bài viết không tồn tại.', 'danger', $back);
            return;
        }

        try {
            // 1. Dòng hàng đợi đăng (nếu có) — tránh để lại dữ liệu mồ côi.
            (new ScheduledPost())->deleteByOutput($outputId);
            // 2. Bài viết — versions tự xóa theo FK ON DELETE CASCADE.
            $outputModel->delete($outputId);
        } catch (\Throwable $e) {
            $this->logActivity('ai_output_delete_failed', $e->getMessage());
            $this->flash('Không xóa được bài viết: ' . $e->getMessage(), 'danger', $back);
            return;
        }

        $this->logActivity('ai_output_delete', sprintf('Output #%d deleted.', $outputId));
        $this->flash('Đã xóa bài viết #' . $outputId . '.', 'success', $back);
    }

    /**
     * Xóa nhiều bài viết (checkbox trong Danh Sách Bài Viết → "Xóa Đã Chọn").
     * Giữ nguyên bộ lọc tab + trang hiện tại sau khi xóa.
     */
    public function deleteBulk(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->flash('Phương thức không hợp lệ.', 'danger', '/admin/ai/fanpage');
            return;
        }

        if (!$this->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/fanpage');
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($_POST['output_ids'] ?? [])
        ), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            $this->flash('Chưa chọn bài viết nào để xóa.', 'danger', '/admin/ai/fanpage');
            return;
        }

        $filter = (string) ($_POST['tab'] ?? '');
        if (!array_key_exists($filter, self::OUTPUT_TABS)) {
            $filter = '';
        }
        $page = max(1, (int) ($_POST['page'] ?? 1));

        $qs = [];
        if ($filter !== '') {
            $qs['tab'] = $filter;
        }
        if ($page > 1) {
            $qs['page'] = $page;
        }
        $back = '/admin/ai/fanpage' . ($qs !== [] ? '?' . http_build_query($qs) : '');

        $outputModel = new AIOutput();
        $deleted = 0;
        $failed  = 0;
        foreach ($ids as $outputId) {
            try {
                (new ScheduledPost())->deleteByOutput($outputId);
                $outputModel->delete($outputId);
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                $this->logActivity('ai_output_delete_failed', '#' . $outputId . ': ' . $e->getMessage());
            }
        }

        $this->logActivity('ai_output_delete_bulk', sprintf('Bulk deleted %d output(s), %d failed.', $deleted, $failed));

        $msg = 'Đã xóa ' . $deleted . ' bài viết.';
        if ($failed > 0) {
            $msg .= ' Không xóa được ' . $failed . ' bài.';
        }
        $this->flash($msg, $failed > 0 ? 'danger' : 'success', $back);
    }
}
