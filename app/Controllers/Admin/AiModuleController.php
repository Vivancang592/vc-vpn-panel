<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AIModel;
use App\Models\AIModule;

/**
 * AiModuleController — POST actions quản trị module AI.
 *
 * Danh mục module là HẰNG SỐ trong code (ModuleRegistry) — Admin KHÔNG tạo/xoá
 * module. Admin chỉ:
 *  - đồng bộ định nghĩa code → `vc_ai_modules` (additive, idempotent)
 *  - bật / tắt module (is_enabled)
 *  - gán model mặc định cho module (default_model_id) từ catalog THẬT trong
 *    `vc_ai_models` (không hard-code model_key, không bịa model).
 *
 * Trang hiển thị Phân Hệ AI đã GỘP vào /admin/ai/settings — các POST action
 * này được gọi từ đó, xong flash() quay lại đúng trang.
 */
final class AiModuleController extends AiBaseController
{
    /**
     * Chuẩn hoá URL quay lại sau khi submit từ tab (chỉ chấp nhận path nội bộ
     * bắt đầu bằng /admin/ai — chặn open-redirect).
     */
    private function safeBack(string $default, string $back): string
    {
        $back = trim($back);

        if ($back !== '' && str_starts_with($back, '/admin/ai/') && !str_contains($back, '//')) {
            return $back;
        }

        return $default;
    }

    /**
     * Đồng bộ ModuleRegistry → vc_ai_modules (chỉ THÊM module còn thiếu).
     */
    public function sync(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings');
        }

        try {
            $report = $this->moduleSynchronizer()->sync();
        } catch (\Throwable $e) {
            $this->logActivity('ai_module_sync_failed', $e->getMessage());
            $this->flash('Đồng bộ module thất bại: ' . $e->getMessage(), 'danger', '/admin/ai/settings');
            return;
        }

        $this->logActivity(
            'ai_module_sync',
            sprintf(
                'Đồng bộ module AI: thêm %d, cập nhật %d, giữ nguyên %d.',
                $report['inserted'],
                $report['updated'],
                $report['unchanged']
            )
        );

        $this->flash(
            sprintf(
                'Đã đồng bộ module AI: thêm %d, cập nhật %d, giữ nguyên %d.',
                $report['inserted'],
                $report['updated'],
                $report['unchanged']
            ),
            'success',
            '/admin/ai/settings'
        );
    }

    /**
     * Bật / tắt module (không cho tạo/xoá module).
     */
    public function toggle(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings');
        }

        $moduleKey = trim((string) ($_POST['module_key'] ?? ''));

        if (!$this->moduleRegistry()->has($moduleKey)) {
            $this->flash('Module không tồn tại trong hệ thống.', 'danger', '/admin/ai/settings');
            return;
        }

        $moduleModel = new AIModule();
        $row = $moduleModel->findByKey($moduleKey);

        if (!is_array($row)) {
            $this->flash('Module chưa được đăng ký trong cơ sở dữ liệu. Hãy đồng bộ trước.', 'danger', '/admin/ai/settings');
            return;
        }

        $enable = ((int) ($row['is_enabled'] ?? 0)) !== 1;
        $moduleModel->update((int) $row['id'], ['is_enabled' => $enable ? 1 : 0]);

        $this->logActivity(
            'ai_module_toggle',
            sprintf('%s module %s.', $enable ? 'Bật' : 'Tắt', $moduleKey)
        );

        $this->flash(
            sprintf('Đã %s module "%s".', $enable ? 'bật' : 'tắt', $moduleKey),
            'success',
            '/admin/ai/settings'
        );
    }

    /**
     * Gán / bỏ model mặc định cho module.
     */
    public function setDefaultModel(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings');
        }

        $back = $this->safeBack('/admin/ai/settings', (string) ($_POST['back'] ?? ''));
        $moduleKey = trim((string) ($_POST['module_key'] ?? ''));
        $rawModelId = trim((string) ($_POST['default_model_id'] ?? ''));

        if (!$this->moduleRegistry()->has($moduleKey)) {
            $this->flash('Module không tồn tại trong hệ thống.', 'danger', $back);
            return;
        }

        $moduleModel = new AIModule();
        $row = $moduleModel->findByKey($moduleKey);

        if (!is_array($row)) {
            $this->flash('Module chưa được đăng ký trong cơ sở dữ liệu.', 'danger', $back);
            return;
        }

        // Bỏ gán model mặc định.
        if ($rawModelId === '' || (int) $rawModelId === 0) {
            $moduleModel->update((int) $row['id'], ['default_model_id' => null]);
            $this->logActivity('ai_module_default_model', 'Bỏ model mặc định của module ' . $moduleKey);
            $this->flash('Đã bỏ model mặc định của module "' . $moduleKey . '".', 'success', $back);
            return;
        }

        $modelId = (int) $rawModelId;
        $model = (new AIModel())->find($modelId);

        if (!is_array($model)) {
            $this->flash('Model không tồn tại trong danh mục.', 'danger', $back);
            return;
        }

        // Chỉ cho gán model đúng provider đang dùng.
        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');
        if ((string) ($model['provider'] ?? '') !== $provider) {
            $this->flash('Model không thuộc provider "' . $provider . '".', 'danger', $back);
            return;
        }

        // CHẶN CỨNG: capability model phải hợp lệ với module.
        // Trước đây chỉ cảnh báo nhưng VẪN LƯU → admin gán chat model (gpt-oss-120b)
        // cho video_generation/audio_tts → user tạo video/đọc nói bị 403.
        $moduleCapability = (string) ($row['capability'] ?? $this->moduleRegistry()->capabilityOf($moduleKey) ?? '');
        $modelCapability  = (string) ($model['capability'] ?? '');
        $allowedCaps      = $this->modelCapabilitiesForModule($moduleCapability);

        if ($allowedCaps !== [] && !in_array($modelCapability, $allowedCaps, true)) {
            $this->flash(
                'Không gán được: model "' . (string) ($model['model_key'] ?? '')
                . '" có capability "' . $modelCapability
                . '" không hợp lệ với module "' . $moduleKey
                . '" (cần: ' . implode(' / ', $allowedCaps) . ').',
                'danger',
                $back
            );
            return;
        }

        $moduleModel->update((int) $row['id'], ['default_model_id' => $modelId]);

        $this->logActivity(
            'ai_module_default_model',
            sprintf('Gán model mặc định %s cho module %s.', (string) ($model['model_key'] ?? ''), $moduleKey)
        );

        $this->flash(
            'Đã gán model mặc định cho module "' . $moduleKey . '".',
            'success',
            $back
        );
    }

    /**
     * Gán / xoá model THỦ CÔN (free-text model_key) ghi vào config JSON của
     * module: `config.model_override`. ModelResolver ưu tiên:
     *   options['model'] > config.model_override > default_model_id > theo capability.
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
        $moduleKey = trim((string) ($_POST['module_key'] ?? ''));
        $modelKey  = trim((string) ($_POST['model_override'] ?? ''));

        if (!$this->moduleRegistry()->has($moduleKey)) {
            $this->flash('Module không tồn tại trong hệ thống.', 'danger', $back);
            return;
        }

        $moduleModel = new AIModule();
        $row = $moduleModel->findByKey($moduleKey);

        if (!is_array($row)) {
            $this->flash('Module chưa được đăng ký trong cơ sở dữ liệu.', 'danger', $back);
            return;
        }

        // Model key chỉ chứa ký tự model_key hợp lệ (không nhận input tùy ý).
        if ($modelKey !== '' && !preg_match('/^[A-Za-z0-9._:-]{1,120}$/', $modelKey)) {
            $this->flash('Model key không hợp lệ (chỉ dùng chữ, số, dấu . _ : - ).', 'danger', $back);
            return;
        }

        $config = [];
        if (!empty($row['config'])) {
            $decoded = json_decode((string) $row['config'], true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        if ($modelKey === '') {
            unset($config['model_override']);
            $message = 'Đã bỏ model thủ công của module "' . $moduleKey . '" (về model mặc định).';
        } else {
            $config['model_override'] = $modelKey;
            $message = 'Đã gán model thủ công "' . $modelKey . '" cho module "' . $moduleKey . '".';

            if ((new AIModel())->findByKey($modelKey) === null) {
                $message .= ' LƯU Ý: key chưa có trong danh mục Kira đang sync — task sẽ báo lỗi nếu provider từ chối.';
            }
        }

        $moduleModel->update((int) $row['id'], ['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);

        $this->logActivity('ai_module_model_override', $message);
        $this->flash($message, 'success', $back);
    }
}
