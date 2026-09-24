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
            if (!is_array($settingsData) || empty($settingsData)) {
                $_SESSION['flash_message'] = 'Không có dữ liệu cài đặt được gửi. Vui lòng kiểm tra đúng tab và thử lại.';
                $_SESSION['flash_type'] = 'danger';
                $this->redirect('/admin/settings');
                return;
            }

            $sensitiveKeys = [
                'ai_openai_api_key',
                'ai_gemini_api_key',
                'fanpage_verify_token',
                'fanpage_app_secret',
                'fanpage_page_access_token',
            ];

            try {
                $savedCount = 0;
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
                    $savedCount++;
                }

                $_SESSION['flash_message'] = 'Cập nhật cấu hình hệ thống thành công (' . $savedCount . ' khóa).';
                $_SESSION['flash_type']    = 'success';
            } catch (\RuntimeException $exception) {
                $_SESSION['flash_message'] = $exception->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }

        $this->redirect('/admin/settings');
    }

    public function aiModels(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $provider = strtolower(trim((string) ($_POST['provider'] ?? 'openai')));
        if ($provider !== 'openai') {
            $this->json(['success' => false, 'message' => 'Provider chưa được hỗ trợ.'], 422);
            return;
        }

        $apiKey = trim((string) ($this->settingModel->getByKey('ai_openai_api_key') ?? getenv('OPENAI_API_KEY') ?: ''));
        if ($apiKey === '') {
            $this->json(['success' => false, 'message' => 'Chưa có OpenAI API key trong cài đặt hoặc .env.'], 422);
            return;
        }

        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 18);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $this->json(['success' => false, 'message' => 'Lỗi kết nối OpenAI: ' . $error], 502);
            return;
        }

        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $this->json(['success' => false, 'message' => 'Không lấy được danh sách model từ OpenAI.'], 502);
            return;
        }

        $models = [];
        foreach (($data['data'] ?? []) as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            // Ưu tiên model họ GPT/O cho chat completion.
            if (
                str_starts_with($id, 'gpt-')
                || str_starts_with($id, 'o1')
                || str_starts_with($id, 'o3')
                || str_starts_with($id, 'o4')
            ) {
                $models[] = $id;
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        $this->json([
            'success' => true,
            'models' => $models,
            'current' => (string) ($this->settingModel->getByKey('ai_openai_model') ?? 'gpt-4o-mini')
        ]);
    }
}