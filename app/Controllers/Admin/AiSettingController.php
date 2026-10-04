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
        $chatbotSettings = [];
        try {
            $settingModel = new Setting();
            $value = $settingModel->getByKey($settingKey);
            $settingConfigured = is_string($value) && trim($value) !== '';
            $chatbotSettings = $settingModel->getAllAsKeyValue();
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
            // Chỉ model mà API key HIỆN TẠI mở khóa (cờ ghi khi Đồng Bộ/Kiểm Tra Kết Nối).
            $models = $this->filterUnlockedModels((new \App\Models\AIModel())->allActive($provider));
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
            'chatbotSettings'    => $chatbotSettings,
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
     * Lưu giới hạn vận hành chatbot. Chỉ các khoá được whitelist mới được
     * ghi xuống CSDL; các rào bảo vệ server vẫn được giữ nguyên.
     */
    public function saveChatbotSettings(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings#chatbot-settings');
        }

        $enabled = (string) ($_POST['ai_chatbot_enabled'] ?? '1');
        $cooldown = filter_var($_POST['ai_cooldown_seconds'] ?? null, FILTER_VALIDATE_FLOAT);
        $rateLimit = filter_var($_POST['ai_rate_limit_per_minute'] ?? null, FILTER_VALIDATE_INT);
        $duplicateWindow = filter_var($_POST['ai_duplicate_window_seconds'] ?? null, FILTER_VALIDATE_INT);
        $maxOutputTokens = filter_var($_POST['ai_max_output_tokens'] ?? null, FILTER_VALIDATE_INT);

        if (!in_array($enabled, ['0', '1'], true)
            || $cooldown === false || $cooldown < 0.5 || $cooldown > 6
            || $rateLimit === false || $rateLimit < 3 || $rateLimit > 30
            || $duplicateWindow === false || $duplicateWindow < 2 || $duplicateWindow > 20
            || $maxOutputTokens === false || $maxOutputTokens < 100 || $maxOutputTokens > 2000) {
            $this->flash('Giá trị chatbot không hợp lệ. Hãy kiểm tra phạm vi được ghi dưới mỗi ô.', 'danger', '/admin/ai/settings#chatbot-settings');
        }

        $values = [
            'ai_chatbot_enabled' => $enabled,
            'ai_cooldown_seconds' => rtrim(rtrim(number_format((float) $cooldown, 2, '.', ''), '0'), '.'),
            'ai_rate_limit_per_minute' => (string) $rateLimit,
            'ai_duplicate_window_seconds' => (string) $duplicateWindow,
            'ai_max_output_tokens' => (string) $maxOutputTokens,
        ];

        try {
            $settingModel = new Setting();
            foreach ($values as $key => $value) {
                if (!$settingModel->setByKey($key, $value)) {
                    throw new \RuntimeException('Không thể lưu ' . $key . '.');
                }
            }
        } catch (\Throwable $e) {
            $this->logActivity('ai_chatbot_settings_save_failed', $e->getMessage());
            $this->flash('Không lưu được giới hạn chatbot: ' . $e->getMessage(), 'danger', '/admin/ai/settings#chatbot-settings');
            return;
        }

        $this->logActivity('ai_chatbot_settings_save', 'Cập nhật trạng thái và giới hạn vận hành chatbot.');
        $this->flash('Đã lưu cấu hình chatbot.', 'success', '/admin/ai/settings#chatbot-settings');
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

        // Kira GET /models trả {"data":[...]} — content của result có thể là null
        // nên đếm qua extractModelItems (fallback raw) thay vì json_decode(content).
        $count = count($this->extractModelItems($result));

        // Kiểm tra OK → đồng bộ NGAY catalog model + ghi cờ key mở khóa,
        // để dropdown model lọc theo key mà không cần bấm nút khác.
        $syncNote = '';
        try {
            $sync = $this->syncModelCatalog();
            if ($sync['ok'] ?? false) {
                $retired = (int) ($sync['retired'] ?? 0);
                $syncNote = sprintf(
                    ' Đã đồng bộ catalog: thêm %d, cập nhật %d — key mở khóa %d model'
                    . ($retired > 0 ? ', đánh dấu bỏ %d model provider không còn cung cấp' : '') . '.',
                    (int) ($sync['inserted'] ?? 0),
                    (int) ($sync['updated'] ?? 0),
                    (int) ($sync['unlocked'] ?? 0),
                    $retired
                );
            }
        } catch (\Throwable $e) {
            $syncNote = ' (Đồng bộ catalog lỗi: ' . $e->getMessage() . ')';
        }

        $this->logActivity('ai_setting_test', 'Kiểm tra kết nối provider ' . $provider . ' thành công (' . $count . ' model).');

        $this->flash(
            'Kiểm tra kết nối THÀNH CÔNG. Provider "' . $provider . '" phản hồi ' . $count . ' model.' . $syncNote,
            'success',
            '/admin/ai/settings'
        );
    }
}
