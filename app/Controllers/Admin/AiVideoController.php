<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * AiVideoController — tab "Tạo Video" (/admin/ai/video).
 *
 * Chạy TRỰC TIẾP khi admin bấm nút (không xếp hàng đợi): prompt →
 * video_generation (LRO) → poll inline tới khi xong → lưu vào thư mục riêng
 * uploads/ai/video/<date>/. Job đang chạy lưu ở $_SESSION['ai_video_job']
 * (operation_id + prompt/options để ghi metadata khi lưu file).
 *
 * Kết quả lưu thẳng vào thư mục tab (hiển thị dạng lưới ngay trong tab).
 */
final class AiVideoController extends AiBaseController
{
    protected string $activeMenu = 'ai-video';

    public function index(): void
    {
        $job = $_SESSION['ai_video_job'] ?? null;
        if (!is_array($job)) {
            $job = null;
        }

        // Ảnh minh hoạ admin tải lên (kind=image, metadata.tab=video) cũng
        // thuộc thư mục tab này → gộp vào gallery để thấy/xfocus được ngay.
        $gallery = $this->galleryItems('video');
        try {
            foreach ($this->media()->listByTab('video', 'image') as $item) {
                $gallery[] = $item;
            }
        } catch (\Throwable $e) {
            // bỏ qua — gallery video vẫn hiển thị bình thường
        }
        usort($gallery, static fn (array $a, array $b): int =>
            strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''))
        );

        $this->render('admin.ai.tab-video', [
            'activeMenu'  => $this->activeMenu,
            'pageTitle'   => 'Tạo Video AI - Quản Trị Hệ Thống',
            'videoModels' => $this->modelsByCapability('video'),
            'gallery'     => $gallery,
            'job'         => $job,
        ]);
    }

    /**
     * Bắt đầu tạo video ngay khi bấm nút → job LRO lưu session → JS poll
     * /admin/ai/video/poll mỗi 5s tới khi done/failed.
     */
    public function generate(): void
    {
        $back = '/admin/ai/video';

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
        }

        if ($this->moduleRegistry()->get('video_generation') === null) {
            $this->flash('Module video_generation chưa được đăng ký trong hệ thống.', 'danger', $back);
            return;
        }

        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        if ($prompt === '') {
            $this->flash('Vui lòng nhập prompt yêu cầu tạo video.', 'danger', $back);
            return;
        }
        if (mb_strlen($prompt) > 5000) {
            $this->flash('Prompt tối đa 5000 ký tự (hiện ' . mb_strlen($prompt) . ').', 'danger', $back);
            return;
        }

        $aspect = trim((string) ($_POST['aspect_ratio'] ?? '16:9'));
        if (!in_array($aspect, ['16:9', '9:16', '1:1'], true)) {
            $aspect = '16:9';
        }

        $duration = (int) ($_POST['duration_seconds'] ?? 6);
        if ($duration < 1 || $duration > 60) {
            $duration = 6;
        }

        $options = [
            'aspect_ratio'     => $aspect,
            'duration_seconds' => $duration,
        ];

        $model = trim((string) ($_POST['model'] ?? ''));
        if ($model !== '') {
            $options['model'] = $model;
        }

        // Ảnh minh hoạ (tuỳ chọn) → lưu thư viện, gắn metadata tab video.
        $referenceNote = '';
        $file = $_FILES['reference_image'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                $this->flash('Tải ảnh minh hoạ thất bại (mã lỗi ' . (int) $file['error'] . ').', 'danger', $back);
                return;
            }

            $tmpPath   = (string) ($file['tmp_name'] ?? '');
            $sizeBytes = (int) ($file['size'] ?? 0);

            if ($tmpPath === '' || !is_file($tmpPath) || $sizeBytes <= 0) {
                $this->flash('File ảnh minh hoạ không hợp lệ.', 'danger', $back);
                return;
            }
            if ($sizeBytes > 5 * 1024 * 1024) {
                $this->flash('Ảnh minh hoạ tối đa 5MB.', 'danger', $back);
                return;
            }

            $mime = (string) (mime_content_type($tmpPath) ?: '');
            if (!str_starts_with($mime, 'image/')) {
                $this->flash('File tải lên không phải ảnh (phát hiện: ' . ($mime !== '' ? $mime : 'không rõ') . ').', 'danger', $back);
                return;
            }

            try {
                (new \App\AI\Assets\AssetManager($this->aiConfig))->saveContent(
                    (string) file_get_contents($tmpPath),
                    'image',
                    (string) ($file['name'] ?? null),
                    $mime !== '' ? $mime : null,
                    ['purpose' => 'video_reference', 'tab' => 'video'],
                    (int) ($_SESSION['user_id'] ?? 0)
                );
                $referenceNote = 'Ảnh minh hoạ đã lưu vào thư mục tab.';
            } catch (\Throwable $e) {
                $this->logActivity('ai_video_reference_failed', $e->getMessage());
                $this->flash('Không lưu được ảnh minh hoạ: ' . $e->getMessage(), 'danger', $back);
                return;
            }
        }

        try {
            $res = $this->media()->startVideo($prompt, $options, (int) ($_SESSION['user_id'] ?? 0));
        } catch (\Throwable $e) {
            $this->logActivity('ai_video_generate_failed', $e->getMessage());
            $this->flash('Lỗi khi bắt đầu tạo video: ' . $e->getMessage(), 'danger', $back);
            return;
        }

        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['error'] ?? 'lỗi không rõ');
            $this->logActivity('ai_video_generate_failed', $error);
            $this->flash('Không bắt đầu được video: ' . $error, 'danger', $back);
            return;
        }

        // Provider trả media ngay (đồng bộ) → lưu thẳng vào thư mục tab.
        if (!($res['pending'] ?? false)) {
            $asset = (array) ($res['asset'] ?? []);
            unset($_SESSION['ai_video_job']);
            $this->logActivity('ai_video_generate', 'Video tạo trực tiếp: ' . (string) ($asset['url'] ?? ''));
            $this->flash('Tạo video thành công — kết quả nằm ở Thư Mục Tab Tạo Video. ' . $referenceNote, 'success', '/admin/ai/video#ai-media-gallery');
            return;
        }

        // LRO: lưu job để JS poll.
        $_SESSION['ai_video_job'] = [
            'operation_id' => (string) ($res['operation_id'] ?? ''),
            'prompt'       => $prompt,
            'options'      => $options,
            'model'        => (string) ($res['model'] ?? ''),
            'started_at'   => date('Y-m-d H:i:s'),
        ];
        $this->logActivity('ai_video_generate', 'Bắt đầu video LRO (op=' . (string) ($res['operation_id'] ?? '') . ').');
        // Đang chạy (LRO) → cũng cuộn xuống cab Thư Mục (job box nằm ngay dưới).
        $this->flash('Đã bắt đầu tạo video — đang chờ provider xử lý, trang sẽ tự poll. ' . $referenceNote, 'success', '/admin/ai/video#ai-media-gallery');
    }

    /** AJAX: poll trạng thái job LRO (video) → running | done | failed. */
    public function poll(): void
    {
        $job = $_SESSION['ai_video_job'] ?? null;
        if (!is_array($job) || trim((string) ($job['operation_id'] ?? '')) === '') {
            $this->json(['state' => 'failed', 'error' => 'Không có job video đang chờ (đã kết thúc hoặc hết hạn).'], 404);
        }

        $options = is_array($job['options'] ?? null) ? $job['options'] : [];

        try {
            $res = $this->media()->pollVideo(
                (string) $job['operation_id'],
                (int) ($_SESSION['user_id'] ?? 0),
                [
                    'prompt'           => (string) ($job['prompt'] ?? ''),
                    'model'            => (string) ($job['model'] ?? ''),
                    'aspect_ratio'     => (string) ($options['aspect_ratio'] ?? ''),
                    'duration_seconds' => (int) ($options['duration_seconds'] ?? 0),
                ]
            );
        } catch (\Throwable $e) {
            $this->logActivity('ai_video_poll_failed', $e->getMessage());
            $this->json(['state' => 'failed', 'error' => 'Lỗi poll video: ' . $e->getMessage()]);
        }

        $state = (string) ($res['state'] ?? 'running');

        if ($state === 'done') {
            $asset = (array) ($res['asset'] ?? []);
            unset($_SESSION['ai_video_job']);
            $this->logActivity('ai_video_generate', 'Video hoàn tất: ' . (string) ($asset['url'] ?? ''));
            $this->json([
                'state' => 'done',
                'url'   => (string) ($asset['url'] ?? ''),
                'flash' => 'Video đã hoàn tất — kết quả nằm ở Thư Mục Tab Tạo Video.',
            ]);
        }

        if ($state === 'failed') {
            unset($_SESSION['ai_video_job']);
            $error = (string) ($res['error'] ?? 'lỗi không rõ');
            $this->logActivity('ai_video_generate_failed', $error);
            $this->json(['state' => 'failed', 'error' => $error]);
        }

        $this->json(['state' => 'running']);
    }

    /** Xoá file trong thư mục tab Tạo Video (bất cứ lúc nào). */
    public function delete(): void
    {
        $this->deleteMediaAsset('video', '/admin/ai/video');
    }
}
