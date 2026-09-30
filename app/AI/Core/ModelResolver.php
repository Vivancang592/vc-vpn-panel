<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AICapability;
use App\AI\Contracts\AIException;

/**
 * ModelResolver — chọn model phù hợp cho (module, capability).
 *
 * Thứ tự ưu tiên:
 *  1. Model chỉ định tường minh (options['model']).
 *  2. Model thủ công admin gán cho module (vc_ai_modules.config JSON → model_override).
 *  3. Model mặc định của module (vc_ai_modules.default_model_id → vc_ai_models).
 *  4. Model active đầu tiên khớp capability.
 *
 * KHÔNG hard-code tên model ở bất kỳ chỗ nào khác trong AI Core.
 * Nếu không tìm thấy model → ném AIException::CONFIG (fail-fast, không đoán bừa).
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

        $moduleModelId = $this->moduleDefaultModelId($module);

        if ($moduleModelId !== null) {
            $row = $this->findById($moduleModelId);
            if ($row !== null && ($row['is_active'] ?? 1)) {
                return [
                    'model_key' => (string) $row['model_key'],
                    'model_id'  => (int) $row['id'],
                ];
            }
        }

        $candidate = $this->firstActiveByCapability($capability);

        if ($candidate !== null) {
            return [
                'model_key' => (string) $candidate['model_key'],
                'model_id'  => (int) $candidate['id'],
            ];
        }

        throw new AIException(
            sprintf('Không tìm thấy model khả dụng cho module "%s" (capability "%s").', $module, $capability),
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
     * @return array<string, mixed>|null
     */
    private function findById(int $id): ?array
    {
        foreach ($this->catalog() as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstActiveByCapability(string $capability): ?array
    {
        $normalized = AICapability::normalize($capability);

        foreach ($this->catalog() as $row) {
            if (AICapability::normalize((string) ($row['capability'] ?? '')) === $normalized) {
                return $row;
            }
        }

        return null;
    }

    private function moduleDefaultModelId(string $module): ?int
    {
        foreach ($this->moduleCatalog() as $row) {
            if ((string) ($row['module_key'] ?? '') !== $module) {
                continue;
            }

            if (empty($row['default_model_id'])) {
                return null;
            }

            return (int) $row['default_model_id'];
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
