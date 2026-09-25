<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ScheduledPost;
use App\Models\Setting;
use App\Services\AIProviderService;
use App\Services\FanpageService;

class ScheduledPostController extends BaseController
{
    private ScheduledPost $postModel;
    private Setting $settingModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->postModel = new ScheduledPost();
        $this->settingModel = new Setting();
    }

    /**
     * Danh sách hàng đợi và lịch sử đăng bài
     */
    public function index(): void
    {
        $posts = $this->postModel->getAllWithAuthor(100);
        $counts = $this->postModel->getCountsByStatus();
        $settings = $this->settingModel->getAllAsKeyValue();

        $this->render('admin.auto-post.index', [
            'activeMenu' => 'auto-post',
            'posts'      => $posts,
            'counts'     => $counts,
            'settings'   => $settings
        ]);
    }

    /**
     * Form giao việc cho AI lên chiến dịch bài đăng
     */
    public function showCreate(): void
    {
        $settings = $this->settingModel->getAllAsKeyValue();
        $this->render('admin.auto-post.create', [
            'activeMenu' => 'auto-post',
            'settings'   => $settings
        ]);
    }

    /**
     * Xử lý giao việc cho AI lên toàn bộ chiến dịch bài đăng (Campaign Automation)
     */
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/auto-post');
            return;
        }

        $coreTopics        = trim($_POST['core_topics'] ?? '');
        $postCount         = max(1, min(30, (int)($_POST['post_count'] ?? 5)));
        $startTimeRaw      = trim($_POST['start_time'] ?? '');
        $frequency         = trim($_POST['frequency'] ?? '24h');
        $customInstruction = trim($_POST['custom_instruction'] ?? '');

        if ($coreTopics === '') {
            $_SESSION['flash_message'] = 'Vui lòng nhập danh sách các chủ đề cốt lõi để AI xây dựng chiến dịch!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post/create');
            return;
        }

        $startTimestamp = !empty($startTimeRaw) ? strtotime($startTimeRaw) : time();
        if ($startTimestamp === false) {
            $startTimestamp = time();
        }

        // Tần suất đăng bài tính theo giây
        $intervalHours = match($frequency) {
            '6h'    => 6,
            '12h'   => 12,
            '24h'   => 24, // Mỗi ngày 1 bài
            '48h'   => 48, // 2 ngày 1 bài
            '72h'   => 72, // 3 ngày 1 bài
            default => 24
        };
        $intervalSeconds = $intervalHours * 3600;

        // 1. Gửi prompt tổng sang AIProviderService yêu cầu JSON Array
        $aiProvider = new AIProviderService();
        $result = $aiProvider->generateCampaignPlan($coreTopics, $postCount, $customInstruction);

        if (!$result['ok'] || trim((string)($result['content'] ?? '')) === '') {
            $_SESSION['flash_message'] = 'Lỗi kết nối AI: ' . ($result['error'] ?? 'Không nhận được phản hồi từ mô hình AI.');
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post/create');
            return;
        }

        // 2. Parse chuỗi JSON chặt chẽ
        $rawContent = trim((string)$result['content']);
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/i', $rawContent, $matches)) {
            $jsonString = $matches[1];
        } elseif (preg_match('/\[[\s\S]*\]/', $rawContent, $matches)) {
            $jsonString = $matches[0];
        } else {
            $jsonString = $rawContent;
        }

        $postsArray = json_decode($jsonString, true);
        if (!is_array($postsArray) || empty($postsArray)) {
            $_SESSION['flash_message'] = 'AI không trả về đúng định dạng JSON danh sách bài viết. Vui lòng thử lại!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post/create');
            return;
        }

        // 3. Lặp qua mảng JSON, tự động tính thời gian và INSERT vào database
        $insertedCount = 0;
        foreach ($postsArray as $index => $item) {
            $topic = trim((string)($item['topic'] ?? ''));
            $content = trim((string)($item['content'] ?? ''));
            $imagePrompt = trim((string)($item['image_prompt'] ?? ''));

            if ($topic === '' && $content === '') {
                continue;
            }

            $scheduledAt = date('Y-m-d H:i:s', $startTimestamp + ($index * $intervalSeconds));

            $this->postModel->create([
                'topic'             => $topic ?: ('Bài viết #' . ($index + 1) . ' - ' . mb_substr($coreTopics, 0, 30)),
                'content_prompt'    => $coreTopics,
                'generated_content' => $content ?: null,
                'image_prompt'      => $imagePrompt ?: null,
                'image_url'         => null,
                'scheduled_at'      => $scheduledAt,
                'status'            => 'pending',
                'meta_data'         => json_encode([
                    'campaign_generated' => true,
                    'provider' => $result['provider'] ?? 'ai',
                    'model' => $result['model'] ?? ''
                ], JSON_UNESCAPED_UNICODE),
                'created_by'        => (int)($_SESSION['user_id'] ?? 1)
            ]);
            $insertedCount++;
        }

        if ($insertedCount === 0) {
            $_SESSION['flash_message'] = 'Không có bài viết hợp lệ nào được khởi tạo từ AI.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post/create');
            return;
        }

        $_SESSION['flash_message'] = "🎉 AI đã lên chiến dịch thành công với {$insertedCount} bài viết trong hàng đợi tự động!";
        $_SESSION['flash_type'] = 'success';
        $this->redirect('/admin/auto-post');
    }

    /**
     * Form chỉnh sửa bài đăng
     */
    public function showEdit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $post = $id ? $this->postModel->find($id) : null;

        if (!$post) {
            $_SESSION['flash_message'] = 'Bài đăng không tồn tại!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post');
            return;
        }

        $settings = $this->settingModel->getAllAsKeyValue();
        $this->render('admin.auto-post.edit', [
            'activeMenu' => 'auto-post',
            'post'       => $post,
            'settings'   => $settings
        ]);
    }

    /**
     * Xử lý cập nhật bài đăng
     */
    public function edit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/auto-post');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $post = $id ? $this->postModel->find($id) : null;

        if (!$post) {
            $_SESSION['flash_message'] = 'Bài đăng không tồn tại!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post');
            return;
        }

        $topic            = trim($_POST['topic'] ?? '');
        $contentPrompt    = trim($_POST['content_prompt'] ?? '');
        $generatedContent = trim($_POST['generated_content'] ?? '');
        $imagePrompt      = trim($_POST['image_prompt'] ?? '');
        $imageUrl         = trim($_POST['image_url'] ?? '');
        $scheduledAt      = trim($_POST['scheduled_at'] ?? '');
        $status           = trim($_POST['status'] ?? $post['status']);

        if (!empty($_FILES['image_file']['tmp_name'])) {
            $uploadedUrl = $this->handleImageUpload($_FILES['image_file']);
            if ($uploadedUrl) {
                $imageUrl = $uploadedUrl;
            }
        }

        if ($topic === '') {
            $_SESSION['flash_message'] = 'Chủ đề bài viết không được để trống!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post/edit?id=' . $id);
            return;
        }

        $updateData = [
            'topic'             => $topic,
            'content_prompt'    => $contentPrompt ?: null,
            'generated_content' => $generatedContent ?: null,
            'image_prompt'      => $imagePrompt ?: null,
            'image_url'         => $imageUrl ?: null,
            'scheduled_at'      => $scheduledAt ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : $post['scheduled_at'],
            'status'            => in_array($status, ['pending', 'ready', 'published', 'failed']) ? $status : $post['status']
        ];

        $this->postModel->update($id, $updateData);

        $_SESSION['flash_message'] = 'Cập nhật bài đăng thành công!';
        $_SESSION['flash_type'] = 'success';
        $this->redirect('/admin/auto-post');
    }

    /**
     * Xóa bài đăng
     */
    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            $this->postModel->delete($id);
            $_SESSION['flash_message'] = 'Đã xóa bài đăng thành công!';
            $_SESSION['flash_type'] = 'success';
        }
        $this->redirect('/admin/auto-post');
    }

    /**
     * Thử lại bài đăng bị lỗi
     */
    public function retry(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            $this->postModel->retry($id);
            $_SESSION['flash_message'] = 'Đã đặt lại trạng thái bài đăng về Chờ xử lý.';
            $_SESSION['flash_type'] = 'success';
        }
        $this->redirect('/admin/auto-post');
    }

    /**
     * Kích hoạt đăng bài lên Fanpage ngay lập tức
     */
    public function forcePublish(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/auto-post');
            return;
        }

        $this->executePublish($id);
    }

    /**
     * AJAX Endpoint: Sinh thử nội dung & hình ảnh bằng AI trực tiếp trên form
     */
    public function ajaxGenerate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $topic = trim($_POST['topic'] ?? '');
        $contentPrompt = trim($_POST['content_prompt'] ?? '');
        $imagePrompt = trim($_POST['image_prompt'] ?? '');
        $generateImage = !empty($_POST['generate_image']);

        if ($topic === '') {
            echo json_encode(['ok' => false, 'message' => 'Vui lòng nhập chủ đề bài viết.']);
            return;
        }

        $aiProvider = new AIProviderService();

        // 1. Sinh văn bản
        $contentRes = $aiProvider->generateContent($topic, $contentPrompt);
        if (!$contentRes['ok']) {
            echo json_encode(['ok' => false, 'message' => $contentRes['error'] ?? 'Lỗi sinh nội dung AI.']);
            return;
        }

        $response = [
            'ok' => true,
            'content' => $contentRes['content'],
            'image_url' => null
        ];

        // 2. Sinh ảnh nếu có yêu cầu
        if ($generateImage && !empty($imagePrompt)) {
            $imageRes = $aiProvider->generateImage($imagePrompt);
            if ($imageRes['ok']) {
                $response['image_url'] = $imageRes['url'];
            } else {
                $response['image_error'] = $imageRes['error'];
            }
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Thực thi đăng bài thực tế và cập nhật trạng thái
     */
    private function executePublish(int $id): void
    {
        $post = $this->postModel->find($id);
        if (!$post) {
            $_SESSION['flash_message'] = 'Bài đăng không tồn tại!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/auto-post');
            return;
        }

        $aiProvider = new AIProviderService();
        $fanpageService = new FanpageService();

        $this->postModel->markAsGenerating($id);

        $content = trim((string) ($post['generated_content'] ?? ''));
        $imageUrl = trim((string) ($post['image_url'] ?? ''));
        $metaData = !empty($post['meta_data']) ? (is_array($post['meta_data']) ? $post['meta_data'] : json_decode((string) $post['meta_data'], true)) : [];

        if ($content === '') {
            $contentResult = $aiProvider->generateContent($post['topic'], $post['content_prompt']);
            if (!$contentResult['ok'] || trim((string) $contentResult['content']) === '') {
                $errorMsg = 'Lỗi sinh nội dung AI: ' . ($contentResult['error'] ?? 'Nội dung rỗng');
                $this->postModel->markAsFailed($id, $errorMsg);
                $_SESSION['flash_message'] = $errorMsg;
                $_SESSION['flash_type'] = 'danger';
                $this->redirect('/admin/auto-post');
                return;
            }
            $content = trim((string) $contentResult['content']);
            $metaData['content_provider'] = $contentResult['provider'] ?? 'ai';
            $metaData['content_model'] = $contentResult['model'] ?? '';
        }

        if ($imageUrl === '' && !empty($post['image_prompt'])) {
            $imageResult = $aiProvider->generateImage($post['image_prompt']);
            if ($imageResult['ok'] && !empty($imageResult['url'])) {
                $imageUrl = $imageResult['url'];
                $metaData['image_url'] = $imageUrl;
            }
        }

        if ($imageUrl !== '') {
            $publishResult = $fanpageService->publishPhoto($content, $imageUrl);
        } else {
            $publishResult = $fanpageService->publishPost($content);
        }

        if ($publishResult['ok'] && !empty($publishResult['id'])) {
            $this->postModel->markAsPublished($id, $publishResult['id'], $content, $imageUrl ?: null, $metaData);
            $_SESSION['flash_message'] = 'Đăng bài lên Facebook Fanpage thành công!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $errorMsg = 'Lỗi đăng Fanpage: ' . ($publishResult['error'] ?? 'Không rõ nguyên nhân');
            $this->postModel->markAsFailed($id, $errorMsg);
            $_SESSION['flash_message'] = $errorMsg;
            $_SESSION['flash_type'] = 'danger';
        }

        $this->redirect('/admin/auto-post');
    }

    /**
     * Upload ảnh thủ công
     */
    private function handleImageUpload(array $file): ?string
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed, true)) {
            return null;
        }

        $uploadDir = BASE_PATH . '/public/uploads/posts';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'upload_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $uploadDir . '/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return '/uploads/posts/' . $fileName;
        }

        return null;
    }

    private function getLastInsertId(): int
    {
        $ref = new \ReflectionClass(\App\Models\BaseModel::class);
        $prop = $ref->getProperty('db');
        $prop->setAccessible(true);
        $db = $prop->getValue();
        return (int) $db->lastInsertId();
    }
}
