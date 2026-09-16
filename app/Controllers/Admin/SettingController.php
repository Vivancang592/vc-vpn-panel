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

        // Gán giá trị mặc định đơn vị tiền tệ CNY nếu chưa có trong cấu hình DB
        $settings['currency']        = $settings['currency'] ?? 'CNY';
        $settings['currency_symbol'] = $settings['currency_symbol'] ?? '¥';

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

            foreach ($settingsData as $key => $value) {
                $this->settingModel->setByKey($key, is_string($value) ? trim($value) : $value);
            }

            $_SESSION['flash_message'] = 'Cập nhật cấu hình hệ thống thành công!';
            $_SESSION['flash_type']    = 'success';
        }

        $this->redirect('/admin/settings');
    }
}