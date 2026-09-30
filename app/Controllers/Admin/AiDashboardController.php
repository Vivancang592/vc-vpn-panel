<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AIAsset;
use App\Models\AIModel;
use App\Models\AIModule;
use App\Models\AIOutput;
use App\Models\AITask;
use App\Models\AITaskActivity;

/**
 * AiDashboardController — tổng quan khu vực Admin AI (/admin/ai).
 *
 * CHỈ ĐỌC dữ liệu từ các bảng `vc_ai_*` để hiển thị trạng thái thật:
 *  - số module đã đăng ký / đang bật (theo vc_ai_modules)
 *  - số model đang active (theo vc_ai_models — rỗng nếu chưa cấu hình Kira)
 *  - hàng đợi task theo trạng thái
 *  - tổng số bài viết đã sinh (output_type=text)
 *  - asset đã lưu
 *  - cấu hình Kira đã có API key hay chưa (chỉ hiện "đã/chưa", KHÔNG lộ key)
 */
final class AiDashboardController extends AiBaseController
{
    public function index(): void
    {
        $moduleRows = $this->moduleRows();

        $modulesRegistered = 0;
        $modulesEnabled = 0;
        foreach ($moduleRows as $row) {
            if ($row['id'] !== null) {
                $modulesRegistered++;
            }
            if ($row['db_enabled'] === true) {
                $modulesEnabled++;
            }
        }

        $models = [];
        try {
            $models = (new AIModel())->getAll();
        } catch (\Throwable $e) {
            $models = [];
        }

        // Đếm module đang gán model làm mặc định (cảnh báo trước khi tắt model).
        $usageByModel = [];
        try {
            foreach ((array) (new AIModule())->getAll() as $module) {
                $modelId = (int) ($module['default_model_id'] ?? 0);
                if ($modelId > 0) {
                    $usageByModel[$modelId] = ($usageByModel[$modelId] ?? 0) + 1;
                }
            }
        } catch (\Throwable $e) {
            $usageByModel = [];
        }

        $tasks = [];
        try {
            $tasks = (new AITask())->getAll();
        } catch (\Throwable $e) {
            $tasks = [];
        }

        $taskCounts = [
            'total'      => count($tasks),
            'pending'    => 0,
            'processing' => 0,
            'retrying'   => 0,
            'completed'  => 0,
            'failed'     => 0,
            'cancelled'  => 0,
        ];

        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? '');
            if (isset($taskCounts[$status])) {
                $taskCounts[$status]++;
            }
        }

        // B1: đếm task theo module (vc_ai_tasks.module_id → vc_ai_modules).
        $moduleIdToKey = [];
        foreach ($moduleRows as $row) {
            if (($row['id'] ?? null) !== null) {
                $moduleIdToKey[(int) $row['id']] = (string) ($row['module_key'] ?? '');
            }
        }

        $tasksByModule = [];
        foreach ($tasks as $task) {
            $moduleKey = $moduleIdToKey[(int) ($task['module_id'] ?? 0)] ?? '';
            if ($moduleKey === '') {
                $moduleKey = '(chưa gán module)';
            }
            $tasksByModule[$moduleKey] = ($tasksByModule[$moduleKey] ?? 0) + 1;
        }
        arsort($tasksByModule);

        // B1: model active theo capability (catalog thật trong vc_ai_models).
        $modelsByCapability = [];
        foreach ($models as $model) {
            if ((int) ($model['is_active'] ?? 0) !== 1) {
                continue;
            }
            $capability = (string) ($model['capability'] ?? '(không rõ)');
            $modelsByCapability[$capability] = ($modelsByCapability[$capability] ?? 0) + 1;
        }
        ksort($modelsByCapability);

        $outputs = [];
        try {
            $outputs = (new AIOutput())->getAll();
        } catch (\Throwable $e) {
            $outputs = [];
        }

        $articleCount = 0;
        foreach ($outputs as $output) {
            if ((string) ($output['output_type'] ?? '') === 'text') {
                $articleCount++;
            }
        }

        $assets = [];
        try {
            $assets = (new AIAsset())->getAll();
        } catch (\Throwable $e) {
            $assets = [];
        }

        $recentTasks = array_slice($tasks, 0, 8);
        $recentActivity = [];

        if ($recentTasks !== []) {
            $activityModel = new AITaskActivity();

            foreach ($recentTasks as $task) {
                try {
                    $items = $activityModel->byTask((int) ($task['id'] ?? 0));
                } catch (\Throwable $e) {
                    $items = [];
                }

                foreach (array_slice($items, -2) as $item) {
                    $item['_task_id'] = (int) ($task['id'] ?? 0);
                    $recentActivity[] = $item;
                }
            }
        }

        usort($recentActivity, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        $kiraConfigured = $this->kiraKeyConfigured();

        $this->render('admin.ai.dashboard', [
            'activeMenu'        => 'ai-dashboard',
            'pageTitle'         => 'Trung Tâm AI - Quản Trị Hệ Thống',
            'moduleRows'        => $moduleRows,
            'modulesRegistered' => $modulesRegistered,
            'modulesEnabled'    => $modulesEnabled,
            'modelCount'        => count($models),
            'activeModelCount'  => count(array_filter($models, static fn($m): bool => (int) ($m['is_active'] ?? 0) === 1)),
            'taskCounts'        => $taskCounts,
            'tasksByModule'     => $tasksByModule,
            'modelsByCapability' => $modelsByCapability,
            'articleCount'      => $articleCount,
            'assetCount'        => count($assets),
            'recentTasks'       => $recentTasks,
            'recentActivity'    => array_slice($recentActivity, 0, 8),
            'kiraConfigured'    => $kiraConfigured,
            'providerKey'       => (string) ($this->aiConfig['default_provider'] ?? 'kira'),
            // Danh mục model hiển ở Tổng Quan (trang /admin/ai/models đã gỡ).
            'models'            => $models,
            'usageByModel'      => $usageByModel,
        ]);
    }

    /**
     * Cấu hình Kira API key CHỈ xét sự tồn tại (không trả về giá trị).
     */
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
