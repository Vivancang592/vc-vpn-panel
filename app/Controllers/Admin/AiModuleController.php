<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AIModel;
use App\Models\AIModule;

/**
 * AiModuleController — POST actions quản trị module AI.
 *
 * Danh mục module là HẰNG SỐ trong code (ModuleRegistry) — Admin KHÔNG tạo/xoá
 * module. Chọn Model AI TRỰC TIẾP trong từng tab (bắt buộc) — không còn
 * "model mặc định ngầm". Controller này chỉ còn gán model thủ công
 * (config.model_override) cho các module chat từ tab Trả Lời Tự Động.
 */
final class AiModuleController extends AiBaseController
{
    /**
     * Gán / xoá model THỦ CÔN (free-text model_key) ghi vào config JSON của
     * module: `config.model_override`. ModelResolver ưu tiên:
     *   options['model'] > config.model_override.
     *
     * Nhận module_key cách nhau dấu phẩy (vd: "support_chat,fanpage_comment")
     * để gán NHIỀU module CÙNG LÚT trong một thao tác — validate TOÀN BỘ
     * trước khi ghi (sai 1 key thì không ghi gì).
     *
     * Không chặn nếu key chưa có trong catalog (task sẽ fail graceful khi provider
     * không nhận) — chỉ cảnh báo, vì catalog sync theo lịch.
     */
    public function setModelOverride(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings');
        }

        $back = $this->safeBack('/admin/ai/settings', (string) ($_POST['back'] ?? ''));
        $moduleKeys = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['module_key'] ?? '')))));
        $modelKey = trim((string) ($_POST['model_override'] ?? ''));

        if ($moduleKeys === []) {
            $this->flash('Thiếu module_key.', 'danger', $back);
            return;
        }

        // Model key chỉ chứa ký tự model_key hợp lệ (không nhận input tùy ý).
        if ($modelKey !== '' && !preg_match('/^[A-Za-z0-9._:-]{1,120}$/', $modelKey)) {
            $this->flash('Model key không hợp lệ (chỉ dùng chữ, số, dấu . _ : - ).', 'danger', $back);
            return;
        }

        $moduleModel = new AIModule();

        // Validate TOÀN BỘ module TRƯỚC khi ghi — sai 1 key thì không ghi gì.
        $rows = [];
        foreach ($moduleKeys as $moduleKey) {
            if (!$this->moduleRegistry()->has($moduleKey)) {
                $this->flash('Module "' . $moduleKey . '" không tồn tại trong hệ thống.', 'danger', $back);
                return;
            }
            $row = $moduleModel->findByKey($moduleKey);
            if (!is_array($row)) {
                $this->flash('Module "' . $moduleKey . '" chưa được đăng ký trong cơ sở dữ liệu.', 'danger', $back);
                return;
            }
            $rows[$moduleKey] = $row;
        }

        $modelMissing = $modelKey !== '' && (new AIModel())->findByKey($modelKey) === null;

        // Ghi config.model_override cho từng module.
        $parts = [];
        foreach ($moduleKeys as $moduleKey) {
            $row = $rows[$moduleKey];
            $config = [];
            if (!empty($row['config'])) {
                $decoded = json_decode((string) $row['config'], true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }

            if ($modelKey === '') {
                unset($config['model_override']);
                $parts[] = $moduleKey . ' -> mặc định';
            } else {
                $config['model_override'] = $modelKey;
                $parts[] = $moduleKey . ' -> ' . $modelKey;
            }

            $moduleModel->update((int) $row['id'], ['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
        }

        $message = 'Đã cập nhật model trả lời: ' . implode('; ', $parts) . '.';
        if ($modelMissing) {
            $message .= ' LƯU Ý: key chưa có trong danh mục Kira đang sync — task sẽ báo lỗi nếu provider từ chối.';
        }

        $this->logActivity('ai_module_model_override', $message);
        $this->flash($message, 'success', $back);
    }
}