<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * AiImageController — tab "Tạo Ảnh" (/admin/ai/image).
 *
 * Chạy TRỰC TIẾP khi admin bấm nút (không xếp hàng đợi): prompt →
 * image_generation → lưu vào thư mục riêng uploads/ai/image/image/<date>/.
 * Kết quả lưu thẳng vào thư mục tab (hiển thị dạng lưới ngay trong
 * tab — xem lại / xoá bất cứ lúc nào).
 */
final class AiImageController extends AiBaseController
{
    protected string $activeMenu = 'ai-image';

    /** Kích thước ảnh nhà cung cấp hỗ trợ (chỉ truyền khi admin chọn). */
    private const SIZES = ['1024x1024', '1344x768', '768x1344'];

    /** Tỉ lệ tương ứng từng size — gửi kèm aspect_ratio (hợp đồng Kira). */
    private const SIZE_ASPECT = [
        '1024x1024' => '1:1',
        '1344x768'  => '16:9',
        '768x1344'  => '9:16',
    ];

    public function index(): void
    {
        $this->render('admin.ai.tab-image', [
            'activeMenu'  => $this->activeMenu,
            'pageTitle'   => 'Tạo Ảnh AI - Quản Trị Hệ Thống',
            'imageModels' => $this->modelsByCapability('image'),
            'gallery'     => $this->galleryItems('image'),
            'sizes'       => self::SIZES,
        ]);
    }

    /** Tạo ảnh ngay khi bấm nút (đồng bộ) → lưu thư mục tab. */
    public function generate(): void
    {
        $back = '/admin/ai/image';

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
        }

        if ($this->moduleRegistry()->get('image_generation') === null) {
            $this->flash('Module image_generation chưa được đăng ký trong hệ thống.', 'danger', $back);
            return;
        }

        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        if ($prompt === '') {
            $this->flash('Vui lòng nhập prompt yêu cầu tạo ảnh.', 'danger', $back);
            return;
        }
        if (mb_strlen($prompt) > 5000) {
            $this->flash('Prompt tối đa 5000 ký tự (hiện ' . mb_strlen($prompt) . ').', 'danger', $back);
            return;
        }

        $options = [];
        $size = trim((string) ($_POST['size'] ?? ''));
        if (in_array($size, self::SIZES, true)) {
            $options['size'] = $size;
            // Kira /images/generations dùng aspect_ratio (SKILL.md đã xác minh);
            // gửi kèm để provider chọn đúng tỉ lệ, size giữ cho client OpenAI-compat.
            $options['aspect_ratio'] = self::SIZE_ASPECT[$size];
        }

        $model = trim((string) ($_POST['model'] ?? ''));
        if ($model === '') {
            $this->flash('Vui lòng chọn Model AI trước khi tạo ảnh.', 'danger', $back);
            return;
        }
        $options['model'] = $model;

        try {
            $res = $this->media()->generateImage($prompt, $options, (int) ($_SESSION['user_id'] ?? 0));
        } catch (\Throwable $e) {
            $this->logActivity('ai_image_generate_failed', $e->getMessage());
            $this->flash('Lỗi khi tạo ảnh: ' . $e->getMessage(), 'danger', $back);
            return;
        }

        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['error'] ?? 'lỗi không rõ');
            $this->logActivity('ai_image_generate_failed', $error);
            $this->flash('Tạo ảnh thất bại: ' . $error, 'danger', $back);
            return;
        }

        $asset = (array) ($res['asset'] ?? []);
        $this->logActivity('ai_image_generate', 'Tạo ảnh trực tiếp: ' . (string) ($asset['url'] ?? ''));
        // Thành công → gắn anchor: trang tự cuộn xuống cab Thư Mục hiển thị kết quả.
        $this->flash('Tạo ảnh thành công — kết quả nằm ở Thư Mục Tab Tạo Ảnh.', 'success', '/admin/ai/image#ai-media-gallery');
    }

    /** Xoá file trong thư mục tab Tạo Ảnh (bất cứ lúc nào). */
    public function delete(): void
    {
        $this->deleteMediaAsset('image', '/admin/ai/image');
    }
}
