<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\AI\Contracts\AICapability;
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

        if (!$this->kiraKeyConfigured()) {
            $this->flash(
                'Chưa cấu hình Kira API key nên không thể đồng bộ model từ provider. '
                . 'Hãy cấu hình key ở trang Cấu Hình AI rồi bấm "Đồng Bộ Từ Nhà Cung Cấp".',
                'danger',
                '/admin/ai#ai-models-catalog'
            );
            return;
        }

        $result = $this->core()->listModels();

        if (!$result->isOk()) {
            $this->logActivity('ai_model_sync_failed', (string) $result->errorMessage());
            $this->flash('Đồng bộ model thất bại: ' . (string) $result->errorMessage(), 'danger', '/admin/ai#ai-models-catalog');
            return;
        }

        $items = $this->extractModelItems($result);

        if ($items === []) {
            $this->flash('Provider trả về danh sách model rỗng hoặc không đọc được.', 'danger', '/admin/ai#ai-models-catalog');
            return;
        }

        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');
        $modelModel = new AIModel();
        $existing = [];
        try {
            foreach ((array) $modelModel->getAll() as $row) {
                if (is_array($row) && (string) ($row['provider'] ?? '') === $provider) {
                    $existing[(string) ($row['model_key'] ?? '')] = $row;
                }
            }
        } catch (\Throwable $e) {
            $existing = [];
        }

        $inserted = 0;
        $updated = 0;

        foreach ($items as $item) {
            $modelKey = trim((string) ($item['id'] ?? $item['model'] ?? $item['name'] ?? ''));

            if ($modelKey === '') {
                continue;
            }

            $capability = $this->guessCapability($item, $modelKey);

            if (isset($existing[$modelKey])) {
                $row = $existing[$modelKey];
                $patch = ['model_name' => $modelKey];

                // Chỉ cập nhật capability nếu bản ghi cũ đang trống.
                if (trim((string) ($row['capability'] ?? '')) === '') {
                    $patch['capability'] = $capability;
                }

                $patch['limits'] = json_encode(
                    array_merge(
                        $this->decodeJson($row['limits'] ?? null),
                        ['source' => 'kira_sync', 'synced_at' => date('Y-m-d H:i:s')]
                    ),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                $modelModel->update((int) $row['id'], $patch);
                $updated++;
                continue;
            }

            $modelModel->create([
                'provider'   => $provider,
                'model_key' => $modelKey,
                'model_name' => $modelKey,
                'capability' => $capability,
                'is_active'  => 1,
                'limits'     => json_encode(
                    ['source' => 'kira_sync', 'synced_at' => date('Y-m-d H:i:s')],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
            $inserted++;
        }

        $this->logActivity('ai_model_sync', sprintf('Đồng bộ model Kira: thêm %d, cập nhật %d.', $inserted, $updated));

        $this->flash(
            sprintf('Đồng bộ model từ Kira thành công: thêm %d, cập nhật %d.', $inserted, $updated),
            'success',
            '/admin/ai#ai-models-catalog'
        );
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractModelItems(\App\AI\Contracts\AIResult $result): array
    {
        $content = $result->content;
        $decoded = null;

        if (is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);
        } elseif (is_array($content)) {
            $decoded = $content;
        }

        if (!is_array($decoded)) {
            $decoded = is_array($result->raw) ? $result->raw : [];
        }

        $list = $decoded['data'] ?? $decoded['models'] ?? $decoded;

        if (!is_array($list)) {
            return [];
        }

        // Bỏ khoá không phải danh sách.
        if (isset($list['data']) && is_array($list['data'])) {
            $list = $list['data'];
        }

        $items = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $items[] = ['id' => $item];
                continue;
            }

            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Suy ra capability từ dữ liệu provider trả về (KHÔNG bịa thêm).
     *
     * @param array<string, mixed> $item
     */
    private function guessCapability(array $item, string $modelKey): string
    {
        foreach (['capability', 'type', 'category', 'modality'] as $field) {
            if (isset($item[$field]) && is_string($item[$field])) {
                $normalized = AICapability::normalize($item[$field]);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $haystack = strtolower($modelKey . ' ' . json_encode($item['output_types'] ?? $item['modalities'] ?? [], JSON_UNESCAPED_UNICODE));

        $rules = [
            'image'   => ['image', 'img', 'vision', 'sd', 'diffusion'],
            'video'   => ['video'],
            'audio'   => ['audio', 'tts', 'speech', 'voice'],
            'comment' => ['comment'],
            'publish' => ['publish'],
            'chat'    => ['chat', 'dialog', 'conversation'],
        ];

        foreach ($rules as $capability => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $capability;
                }
            }
        }

        return AICapability::TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function kiraKeyConfigured(): bool
    {
        $settingKey = (string) ($this->aiConfig['providers']['kira']['setting_key'] ?? 'kira_api_key');
        $envKey = (string) ($this->aiConfig['providers']['kira']['env_key'] ?? 'KIRA_API_KEY');

        $envValue = getenv($envKey);
        if (is_string($envValue) && trim($envValue) !== '') {
            return true;
        }

        try {
            $value = (new \App\Models\Setting())->getByKey($settingKey);
        } catch (\Throwable $e) {
            return false;
        }

        return is_string($value) && trim($value) !== '';
    }
}
