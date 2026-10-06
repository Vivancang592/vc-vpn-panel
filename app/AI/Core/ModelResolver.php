<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AIException;

/**
 * ModelResolver — chọn model phù hợp cho (module, capability).
 *
 * Thứ tự ưu tiên:
 *  1. Model chỉ định tường minh (options['model']).
 *  2. Model thủ công admin gán cho module (vc_ai_modules.config JSON → model_override).
 *
 * KHÔNG còn "model mặc định ngầm": module chưa chọn model → ném
 * AIException::CONFIG (fail-fast — báo admin chọn model trong tab tương ứng),
 * không đoán bừa model đầu tiên nữa.
 * KHÔNG hard-code tên model ở bất kỳ chỗ nào khác trong AI Core.
 */
final class ModelResolver
{
    private string $provider;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $models = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $modules = null;

    public function __construct(string $provider = 'kira')
    {
        $this->provider = $provider;
    }

    /**
     * Backdoor cho test: inject catalog thay vì đọc DB.
     *
     * @param array<int, array<string, mixed>> $models
     */
    public function setCatalog(array $models): void
    {
        $this->models = $models;
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     */
    public function setModules(array $modules): void
    {
        $this->modules = $modules;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{model_key: string, model_id: ?int}
     */
    public function resolve(string $module, string $capability, array $options = []): array
    {
        if (!empty($options['model']) && is_string($options['model'])) {
            return [
                'model_key' => $options['model'],
                'model_id'  => $this->findIdByKey($options['model']),
            ];
        }

        // Ưu tiên 2: model thủ công admin gán trong tab (config.model_override).
        // Ghi đúng key admin nhập (cùng cách với options['model']); model_id có
        // thể null khi key chưa sync vào catalog — an toàn cho RequestBuilder.
        $override = $this->moduleModelOverride($module);

        if ($override !== null) {
            return [
                'model_key' => $override,
                'model_id'  => $this->findIdByKey($override),
            ];
        }

        // Không còn "model mặc định ngầm": module chưa chọn model → báo NGAY
        // cho admin biết (chọn model trong tab tương ứng) thay vì tự chọn bừa.
        throw new AIException(
            sprintf('Module "%s" chưa chọn Model AI — hãy chọn model trong tab tương ứng trước khi chạy.', $module),
            AIException::CONFIG,
            null,
            ['module' => $module, 'capability' => $capability]
        );
    }

    /**
     * Danh mục model active của provider hiện tại.
     *
     * Chỉ dùng BaseModel::getAll() rồi lọc ở PHP (không thêm query builder).
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }

        if (!class_exists(\App\Models\AIModel::class)) {
            return $this->models = [];
        }

        try {
            $rows = (new \App\Models\AIModel())->getAll();
        } catch (\Throwable $e) {
            return $this->models = [];
        }

        $filtered = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['provider'] ?? '') !== $this->provider) {
                continue;
            }
            if ((int) ($row['is_active'] ?? 0) !== 1) {
                continue;
            }
            $filtered[] = $row;
        }

        return $this->models = $filtered;
    }

    private function findIdByKey(string $modelKey): ?int
    {
        foreach ($this->catalog() as $row) {
            if ((string) ($row['model_key'] ?? '') === $modelKey) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    /**
     * Model thủ công admin gán qua tab: vc_ai_modules.config (JSON) → `model_override`.
     *
     * Trả về null nếu module không có override (config NULL/không phải JSON/không có key).
     */
    private function moduleModelOverride(string $module): ?string
    {
        foreach ($this->moduleCatalog() as $row) {
            if ((string) ($row['module_key'] ?? '') !== $module) {
                continue;
            }

            $config = $row['config'] ?? null;

            if ($config === null || $config === '') {
                return null;
            }

            // PDO có thể trả JSON đã decode sẵn (mảng) hoặc chuỗi thô tùy driver.
            if (is_string($config)) {
                $decoded = json_decode($config, true);
                $config  = is_array($decoded) ? $decoded : null;
            }

            if (!is_array($config)) {
                return null;
            }

            $override = trim((string) ($config['model_override'] ?? ''));

            return $override === '' ? null : $override;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function moduleCatalog(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        if (!class_exists(\App\Models\AIModule::class)) {
            return $this->modules = [];
        }

        try {
            $rows = (new \App\Models\AIModule())->getAll();
        } catch (\Throwable $e) {
            return $this->modules = [];
        }

        return $this->modules = array_values(array_filter((array) $rows, 'is_array'));
    }
}
