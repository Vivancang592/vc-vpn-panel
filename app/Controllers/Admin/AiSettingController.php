<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Setting;

/**
 * AiSettingController — cấu hình AI (/admin/ai/settings).
 *
 * Phạm vi (tối thiểu, đúng những gì AI Core cần):
 *  - API key của provider (mặc định 'kira') lưu trong `vc_settings` theo
 *    `setting_key` khai báo trong config/ai.php (KHÔNG hard-code tên cột).
 *  - Kiểm tra kết nối provider (GET /models hoặc /user/profile) và hiển thị
 *    kết quả THẬT. Không mô phỏng, không giả lập thành công.
 *  - Hiển thị danh mục Phân Hệ AI (gộp từ trang riêng /admin/ai/modules đã
 *    bỏ) — POST sync/toggle/set-default-model vẫn do AiModuleController xử lý.
 *
 * BẢO MẬT: giá trị API key KHÔNG BAO GIỜ được trả về view — chỉ trạng thái
 * "đã cấu hình / chưa cấu hình". Không log key. Không echo key.
 */
final class AiSettingController extends AiBaseController
{
    public function index(): void
    {
        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');
        $providerConfig = (array) ($this->aiConfig['providers'][$provider] ?? []);

        $settingKey = (string) ($providerConfig['setting_key'] ?? 'kira_api_key');
        $envKey = (string) ($providerConfig['env_key'] ?? 'KIRA_API_KEY');

        $envConfigured = false;
        $envValue = getenv($envKey);
        if (is_string($envValue) && trim($envValue) !== '') {
            $envConfigured = true;
        }

        $settingConfigured = false;
        try {
            $value = (new Setting())->getByKey($settingKey);
            $settingConfigured = is_string($value) && trim($value) !== '';
        } catch (\Throwable $e) {
            $settingConfigured = false;
        }

        // Danh mục phân hệ + model (gộp từ AiModuleController::index đã bỏ).
        $moduleRows = [];
        try {
            $moduleRows = $this->moduleRows();
        } catch (\Throwable $e) {
            $moduleRows = [];
        }

        // Chỉ model ACTIVE của provider đang dùng (tránh liệt kê model đã tắt
        // hoặc của provider khác trong dropdown Phân Hệ).
        $models = [];
        try {
            $models = (new \App\Models\AIModel())->allActive($provider);
        } catch (\Throwable $e) {
            $models = [];
        }

        // Dropdown từng dòng chỉ liệt kê model CÓ CAPABILITY HỢP LỆ với module đó.
        $modelsByModule = [];
        foreach ($moduleRows as $row) {
            $modelsByModule[(string) $row['module_key']] = $this->filterModelsForModule(
                $models,
                (string) $row['capability']
            );
        }

        $this->render('admin.ai.settings', [
            'activeMenu'         => 'ai-settings',
            'pageTitle'          => 'Cấu Hình AI - Quản Trị Hệ Thống',
            'provider'           => $provider,
            'allowedProviders'   => (array) ($this->aiConfig['allowed_providers'] ?? []),
            'baseUrl'            => (string) ($providerConfig['base_url'] ?? ''),
            'settingKey'         => $settingKey,
            'envKey'             => $envKey,
            'envConfigured'      => $envConfigured,
            'settingConfigured'  => $settingConfigured,
            'retry'              => (array) ($this->aiConfig['retry'] ?? []),
            'task'               => (array) ($this->aiConfig['task'] ?? []),
            'assets'             => (array) ($this->aiConfig['assets'] ?? []),
            'moduleRows'         => $moduleRows,
            'models'             => $models,
            'modelsByModule'     => $modelsByModule,
        ]);
    }

    /**
     * Lưu API key provider vào vc_settings.
     */
    public function save(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings');
        }

        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');
        $providerConfig = (array) ($this->aiConfig['providers'][$provider] ?? []);
        $settingKey = (string) ($providerConfig['setting_key'] ?? 'kira_api_key');

        $apiKey = trim((string) ($_POST['api_key'] ?? ''));

        if ($apiKey === '') {
            $this->flash('Vui lòng nhập API key.', 'danger', '/admin/ai/settings');
            return;
        }

        if (strlen($apiKey) < 8) {
            $this->flash('API key quá ngắn, vui lòng kiểm tra lại.', 'danger', '/admin/ai/settings');
            return;
        }

        try {
            $ok = (new Setting())->setByKey($settingKey, $apiKey);
        } catch (\Throwable $e) {
            $this->logActivity('ai_setting_save_failed', $e->getMessage());
            $this->flash('Không lưu được API key: ' . $e->getMessage(), 'danger', '/admin/ai/settings');
            return;
        }

        if (!$ok) {
            $this->flash('Không lưu được API key.', 'danger', '/admin/ai/settings');
            return;
        }

        // KHÔNG log giá trị key — chỉ log hành động.
        $this->logActivity('ai_setting_save', sprintf('Cập nhật API key provider %s (độ dài %d ký tự).', $provider, strlen($apiKey)));

        $this->flash('Đã lưu API key cho provider "' . $provider . '".', 'success', '/admin/ai/settings');
    }

    /**
     * Kiểm tra kết nối provider bằng dữ liệu THẬT (không mô phỏng).
     */
    public function test(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings');
        }

        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');

        $providerConfig = (array) ($this->aiConfig['providers'][$provider] ?? []);
        $settingKey = (string) ($providerConfig['setting_key'] ?? 'kira_api_key');

        $configured = false;
        try {
            $value = (new Setting())->getByKey($settingKey);
            $configured = is_string($value) && trim($value) !== '';
        } catch (\Throwable $e) {
            $configured = false;
        }

        $envValue = getenv((string) ($providerConfig['env_key'] ?? 'KIRA_API_KEY'));
        if (is_string($envValue) && trim($envValue) !== '') {
            $configured = true;
        }

        if (!$configured) {
            $this->flash(
                'Chưa cấu hình API key cho provider "' . $provider . '" — không thể kiểm tra kết nối.',
                'danger',
                '/admin/ai/settings'
            );
            return;
        }

        $result = $this->core()->listModels();

        if (!$result->isOk()) {
            $this->logActivity('ai_setting_test_failed', (string) $result->errorMessage());
            $this->flash(
                'Kiểm tra kết nối THẤT BẠI: [' . (string) ($result->errorType() ?? 'unknown') . '] ' . (string) $result->errorMessage(),
                'danger',
                '/admin/ai/settings'
            );
            return;
        }

        $count = 0;
        $decoded = json_decode((string) ($result->content ?? ''), true);
        if (is_array($decoded)) {
            $list = $decoded['data'] ?? $decoded['models'] ?? $decoded;
            $count = is_array($list) ? count($list) : 0;
        }

        $this->logActivity('ai_setting_test', 'Kiểm tra kết nối provider ' . $provider . ' thành công (' . $count . ' model).');

        $this->flash(
            'Kiểm tra kết nối THÀNH CÔNG. Provider "' . $provider . '" phản hồi ' . $count . ' model. Hãy vào trang Model để đồng bộ.',
            'success',
            '/admin/ai/settings'
        );
    }
}
