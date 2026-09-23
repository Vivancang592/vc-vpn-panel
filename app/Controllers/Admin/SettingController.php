<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Setting;
use App\Models\VpnPlan;

class SettingController extends BaseController
{
    private Setting $settingModel;
    private VpnPlan $planModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->settingModel = new Setting();
        $this->planModel = new VpnPlan();
    }

    public function index(): void
    {
        $settings = $this->settingModel->getAllAsKeyValue();
        $plans    = $this->planModel->getAllActive();

        // Gán giá trị mặc định đơn vị tiền tệ VND nếu chưa có trong cấu hình DB
        $settings['currency']        = $settings['currency'] ?? 'VND';
        $settings['currency_symbol'] = $settings['currency_symbol'] ?? 'đ';

        $this->render('admin.settings.index', [
            'activeMenu' => 'settings',
            'settings'   => $settings,
            'plans'      => $plans
        ]);
    }

    public function save(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settingsData = $_POST['settings'] ?? [];
            $sensitiveKeys = [
                'ai_openai_api_key',
                'ai_gemini_api_key',
                'fanpage_verify_token',
                'fanpage_app_secret',
                'fanpage_page_access_token',
            ];

            try {
                foreach ($settingsData as $key => $value) {
                    $normalized = is_string($value) ? trim($value) : $value;

                    if (in_array($key, $sensitiveKeys, true)) {
                        $isMasked = is_string($normalized) && preg_match('/^\*{6,}$/', $normalized) === 1;
                        if ($normalized === '' || $isMasked) {
                            $current = $this->settingModel->getByKey($key);
                            if ($current !== null) {
                                $normalized = $current;
                            }
                        }
                    }

                    $this->settingModel->setByKey($key, is_string($normalized) ? trim($normalized) : $normalized);
                }

                $_SESSION['flash_message'] = 'Cập nhật cấu hình hệ thống thành công!';
                $_SESSION['flash_type']    = 'success';
            } catch (\RuntimeException $exception) {
                $_SESSION['flash_message'] = $exception->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }

        $this->redirect('/admin/settings');
    }
}