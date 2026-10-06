<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\Models\AIModule;

/**
 * ModuleSynchronizer — đồng bộ ĐỊNH NGHĨA MODULE từ code (ModuleRegistry) xuống
 * bảng cấu hình `vc_ai_modules`.
 *
 * Vì sao cần:
 *  - `ModuleRegistry` là nguồn DUY NHẤT biết "module nào tồn tại, capability gì,
 *    prompt_key nào, output_kind nào". Nó là const tĩnh, không cần DB.
 *  - `TaskRunner::request()` lại yêu cầu module PHẢI có row thật trong
 *    `vc_ai_modules` (vì task cần FK module_id). Không có row → AIException::CONFIG.
 *  - Vậy cần một bước "đăng ký module" TẤT ĐỊNH, KHÔNG bịa dữ liệu:
 *    module_key / module_name / capability lấy TRỰC TIẾP từ ModuleRegistry.
 *
 * Nguyên tắc:
 *  - IDEMPOTENT: chạy bao nhiêu lần cũng được. Chỉ THÊM module còn thiếu.
 *  - KHÔNG sửa / KHÔNG bật-tắt module đã có (quyền đó thuộc Admin UI) — ngoại lệ
 *    duy nhất: nếu `capability` trong DB trống thì điền lại (dữ liệu khuyết).
 *  - KHÔNG tạo `vc_ai_models`. KHÔNG gán model cho module — model phải do admin
 *    người cấu hình từ catalog Kira thật (không hard-code model_key ở đây).
 *  - Trả về báo cáo rõ ràng: inserted / updated / skipped + chi tiết.
 */
final class ModuleSynchronizer
{
    private ModuleRegistry $registry;

    public function __construct(?ModuleRegistry $registry = null)
    {
        $this->registry = $registry ?? new ModuleRegistry();
    }

    /**
     * @return array{inserted: int, updated: int, unchanged: int, details: array<int, array<string, mixed>>}
     */
    public function sync(): array
    {
        $model  = new AIModule();
        $report = [
            'inserted'  => 0,
            'updated'   => 0,
            'unchanged' => 0,
            'details'   => [],
        ];

        // Bảo đảm bảng tồn tại/đọc được trước khi ghi.
        try {
            $existing = $model->getAll();
        } catch (\Throwable $e) {
            $existing = [];
        }

        $byKey = [];
        foreach ((array) $existing as $row) {
            if (is_array($row) && isset($row['module_key'])) {
                $byKey[(string) $row['module_key']] = $row;
            }
        }

        $sort = 0;

        foreach ($this->registry->all() as $moduleKey => $meta) {
            $sort += 10;

            $capability = (string) ($meta['capability'] ?? '');
            $label      = (string) ($meta['label'] ?? $moduleKey);
            $enabled    = (($meta['enabled'] ?? false) === true) ? 1 : 0;

            if (!isset($byKey[$moduleKey])) {
                $model->create([
                    'module_key'   => $moduleKey,
                    'module_name'  => $label,
                    'capability'   => $capability,
                    'is_enabled'   => $enabled,
                    'sort_order'   => $sort,
                ]);

                $report['inserted']++;
                $report['details'][] = [
                    'module_key' => $moduleKey,
                    'action'     => 'inserted',
                    'id'         => $model->lastInsertId(),
                ];

                continue;
            }

            $row   = $byKey[$moduleKey];
            $patch = [];

            // Chỉ điền lại dữ liệu KHUYẾT (không ghi đè lựa chọn của Admin).
            if (trim((string) ($row['capability'] ?? '')) === '' && $capability !== '') {
                $patch['capability'] = $capability;
            }

            if (trim((string) ($row['module_name'] ?? '')) === '' && $label !== '') {
                $patch['module_name'] = $label;
            }

            if ($patch !== []) {
                $model->update((int) $row['id'], $patch);
                $report['updated']++;
                $report['details'][] = [
                    'module_key' => $moduleKey,
                    'action'     => 'updated',
                    'id'         => (int) $row['id'],
                    'fields'     => array_keys($patch),
                ];

                continue;
            }

            $report['unchanged']++;
            $report['details'][] = [
                'module_key' => $moduleKey,
                'action'     => 'unchanged',
                'id'         => (int) $row['id'],
            ];
        }

        return $report;
    }

    /**
     * Đăng ký MỘT module cụ thể (dùng khi cần chắc chắn module đã có row).
     *
     * @return array{id: int, created: bool}
     */
    public function ensure(string $moduleKey): array
    {
        $meta = $this->registry->get($moduleKey);

        if ($meta === null) {
            throw new \RuntimeException('Module không tồn tại trong ModuleRegistry: ' . $moduleKey);
        }

        $model = new AIModule();
        $found = $model->findByKey($moduleKey);

        if (is_array($found)) {
            return ['id' => (int) $found['id'], 'created' => false];
        }

        $model->create([
            'module_key'  => $moduleKey,
            'module_name' => (string) ($meta['label'] ?? $moduleKey),
            'capability'  => (string) ($meta['capability'] ?? ''),
            'is_enabled'  => (($meta['enabled'] ?? false) === true) ? 1 : 0,
            'sort_order'  => 0,
        ]);

        return ['id' => $model->lastInsertId(), 'created' => true];
    }
}
