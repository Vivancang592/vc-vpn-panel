<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AIModel;

/**
 * AiModelController — danh mục model của provider (/admin/ai/models).
 *
 * NGUỒN DỮ LIỆU:
 *  Đồng bộ từ chính provider (`GET /models` của Kira) — nguồn DUY NHẤT.
 *  Chỉ chạy được khi đã cấu hình Kira API key. Kết quả ghi vào vc_ai_models.
 *
 *  Nhập mô hình thủ công ĐÃ XOÁ (route + method `store()` đã gỡ theo yêu cầu)
 *  nên code KHÔNG còn đường nào ghi `limits.source = 'manual'`.
 *
 * TUYỆT ĐỐI KHÔNG seed sẵn model_key nào trong code (không bịa model Kira).
 * Nếu chưa có dữ liệu → UI hiển thị rõ "Chưa có model nào được cấu hình".
 */
final class AiModelController extends AiBaseController
{
    /**
     * Trang riêng /admin/ai/models ĐÃ GỠ theo yêu cầu: danh sách model hiển
     * ngay tại Tổng Quan (/admin/ai#ai-models-catalog). Route giữ lại chỉ để
     * redirect — các POST sync/store/toggle vẫn hoạt động từ Tổng Quan.
     */
    public function index(): void
    {
        $this->redirect('/admin/ai#ai-models-catalog');
    }

    /**
     * Đồng bộ catalog model từ provider thật (Kira `GET /models`).
     *
     * KHÔNG dùng dữ liệu giả. Nếu chưa có API key hoặc provider lỗi → báo lỗi rõ.
     */
    public function sync(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai#ai-models-catalog');
        }

        $back = $this->safeBack('/admin/ai#ai-models-catalog', (string) ($_POST['back'] ?? ''));

        // Logic dùng chung tại AiBaseController::syncModelCatalog() — cũng chạy
        // từ nút "Kiểm Tra Kết Nối" ở trang Cấu Hình (kèm cờ key mở khóa).
        $sync = $this->syncModelCatalog();

        if (!($sync['ok'] ?? false)) {
            $this->flash('Đồng bộ model thất bại: ' . (string) ($sync['message'] ?? 'lỗi không rõ'), 'danger', $back);
            return;
        }

        $this->flash((string) $sync['message'], 'success', $back);
    }

    public function toggle(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai#ai-models-catalog');
        }

        $modelId = (int) ($_POST['id'] ?? 0);
        $modelModel = new AIModel();
        $row = $modelModel->find($modelId);

        if (!is_array($row)) {
            $this->flash('Model không tồn tại.', 'danger', '/admin/ai#ai-models-catalog');
            return;
        }

        $enable = ((int) ($row['is_active'] ?? 0)) !== 1;
        $modelModel->update($modelId, ['is_active' => $enable ? 1 : 0]);

        $this->logActivity('ai_model_toggle', sprintf('%s model %s.', $enable ? 'Bật' : 'Tắt', (string) ($row['model_key'] ?? '')));

        $this->flash(
            sprintf('Đã %s model "%s".', $enable ? 'bật' : 'tắt', (string) ($row['model_key'] ?? '')),
            'success',
            '/admin/ai#ai-models-catalog'
        );
    }

}
