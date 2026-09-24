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
        if (!in_array($provider, ['openai', 'gemini'], true)) {
            $this->json(['success' => false, 'message' => 'Provider chưa được hỗ trợ.'], 422);
            return;
        }

        if ($provider === 'gemini') {
            $this->loadGeminiModels();
            return;
        }

        $apiKey = $this->resolveOpenAiApiKey();
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $this->applyProxy($ch);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $totalTime = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        if ($errno !== 0) {
            $this->json([
                'success' => false,
                'message' => 'Lỗi mạng khi kết nối OpenAI: ' . $error,
                'diagnostics' => [
                    'curl_errno' => $errno,
                    'primary_ip' => $primaryIp,
                    'total_time' => $totalTime,
                ]
            ], 502);
            return;
        }

        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $providerMessage = is_array($data)
                ? trim((string)($data['error']['message'] ?? ''))
                : '';
            $message = 'Không lấy được danh sách model từ OpenAI. HTTP ' . $status;
            if ($providerMessage !== '') {
                $message .= ' - ' . $providerMessage;
            }
            $this->json(['success' => false, 'message' => $message], 502);
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
                || str_starts_with($id, 'chatgpt-')
            ) {
                $models[] = $id;
            }
        }

        if (empty($models) && !empty($data['data'])) {
            foreach ($data['data'] as $item) {
                $id = trim((string) ($item['id'] ?? ''));
                if ($id !== '' && !str_contains($id, 'whisper') && !str_contains($id, 'tts') && !str_contains($id, 'dall-e') && !str_contains($id, 'embedding')) {
                    $models[] = $id;
                }
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        $this->json([
            'success' => true,
            'message' => 'Đã tải thành công ' . count($models) . ' model từ OpenAI.',
            'models' => $models,
            'current' => (string) ($this->settingModel->getByKey('ai_openai_model') ?? 'gpt-4o-mini')
        ]);
    }

    public function aiConnectionCheck(): void
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
        if (!in_array($provider, ['openai', 'gemini'], true)) {
            $this->json(['success' => false, 'message' => 'Provider chưa được hỗ trợ.'], 422);
            return;
        }

        if ($provider === 'gemini') {
            $this->checkGeminiConnection();
            return;
        }

        $apiKey = $this->resolveOpenAiApiKey();
        if ($apiKey === '') {
            $this->json(['success' => false, 'message' => 'Chưa có OpenAI API key trong cài đặt hoặc .env.'], 422);
            return;
        }

        $url = 'https://api.openai.com/v1/models';
        $hostIp = gethostbyname('api.openai.com');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $this->applyProxy($ch);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $namelookup = (float) curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
        $connect = (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $total = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $diagnostics = [
            'dns_host_ip' => $hostIp,
            'curl_errno' => $errno,
            'http_status' => $status,
            'primary_ip' => $primaryIp,
            'timing' => [
                'namelookup' => $namelookup,
                'connect' => $connect,
                'total' => $total,
            ],
        ];

        if ($errno !== 0) {
            $this->json([
                'success' => false,
                'message' => 'Không kết nối được OpenAI: ' . $error,
                'diagnostics' => $diagnostics,
            ], 502);
            return;
        }

        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            $providerMessage = is_array($data)
                ? trim((string) ($data['error']['message'] ?? ''))
                : '';
            $message = 'OpenAI phản hồi lỗi HTTP ' . $status;
            if ($providerMessage !== '') {
                $message .= ': ' . $providerMessage;
            }
            $this->json([
                'success' => false,
                'message' => $message,
                'diagnostics' => $diagnostics,
            ], 502);
            return;
        }

        $models = [];
        foreach (($data['data'] ?? []) as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            if (
                str_starts_with($id, 'gpt-')
                || str_starts_with($id, 'o1')
                || str_starts_with($id, 'o3')
                || str_starts_with($id, 'o4')
                || str_starts_with($id, 'chatgpt-')
            ) {
                $models[] = $id;
            }
        }

        if (empty($models) && !empty($data['data'])) {
            foreach ($data['data'] as $item) {
                $id = trim((string) ($item['id'] ?? ''));
                if ($id !== '' && !str_contains($id, 'whisper') && !str_contains($id, 'tts') && !str_contains($id, 'dall-e') && !str_contains($id, 'embedding')) {
                    $models[] = $id;
                }
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        $this->json([
            'success' => true,
            'message' => 'Kết nối OpenAI thành công (' . count($models) . ' model). Đã cập nhật danh sách chọn.',
            'models' => $models,
            'current' => (string) ($this->settingModel->getByKey('ai_openai_model') ?? 'gpt-4o-mini'),
            'diagnostics' => $diagnostics,
        ]);
    }

    private function loadGeminiModels(): void
    {
        $apiKey = $this->resolveGeminiApiKey();
        if ($apiKey === '') {
            $this->json(['success' => false, 'message' => 'Chưa có Gemini API key trong cài đặt hoặc .env.'], 422);
            return;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $this->applyProxy($ch);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $totalTime = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        if ($errno !== 0) {
            $this->json([
                'success' => false,
                'message' => 'Lỗi mạng khi kết nối Gemini: ' . $error,
                'diagnostics' => [
                    'curl_errno' => $errno,
                    'primary_ip' => $primaryIp,
                    'total_time' => $totalTime,
                ]
            ], 502);
            return;
        }

        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $providerMessage = is_array($data)
                ? trim((string)($data['error']['message'] ?? ''))
                : '';
            $message = 'Không lấy được danh sách model từ Gemini. HTTP ' . $status;
            if ($providerMessage !== '') {
                $message .= ' - ' . $providerMessage;
            }
            $this->json(['success' => false, 'message' => $message], 502);
            return;
        }

        $models = [];
        foreach (($data['models'] ?? []) as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $methods = $item['supportedGenerationMethods'] ?? [];
            if (!empty($methods) && is_array($methods) && !in_array('generateContent', $methods, true)) {
                continue;
            }

            $normalized = str_starts_with($name, 'models/') ? substr($name, 7) : $name;
            if ($normalized !== '') {
                $models[] = $normalized;
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        $this->json([
            'success' => true,
            'message' => 'Đã tải thành công ' . count($models) . ' model từ Gemini.',
            'models' => $models,
            'current' => (string) ($this->settingModel->getByKey('ai_gemini_model') ?? 'gemini-1.5-flash')
        ]);
    }

    private function checkGeminiConnection(): void
    {
        $apiKey = $this->resolveGeminiApiKey();
        if ($apiKey === '') {
            $this->json(['success' => false, 'message' => 'Chưa có Gemini API key trong cài đặt hoặc .env.'], 422);
            return;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($apiKey);
        $hostIp = gethostbyname('generativelanguage.googleapis.com');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $this->applyProxy($ch);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $namelookup = (float) curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
        $connect = (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $total = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $diagnostics = [
            'dns_host_ip' => $hostIp,
            'curl_errno' => $errno,
            'http_status' => $status,
            'primary_ip' => $primaryIp,
            'timing' => [
                'namelookup' => $namelookup,
                'connect' => $connect,
                'total' => $total,
            ],
        ];

        if ($errno !== 0) {
            $this->json([
                'success' => false,
                'message' => 'Không kết nối được Gemini: ' . $error,
                'diagnostics' => $diagnostics,
            ], 502);
            return;
        }

        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            $providerMessage = is_array($data)
                ? trim((string) ($data['error']['message'] ?? ''))
                : '';
            $message = 'Gemini phản hồi lỗi HTTP ' . $status;
            if ($providerMessage !== '') {
                $message .= ': ' . $providerMessage;
            }
            $this->json([
                'success' => false,
                'message' => $message,
                'diagnostics' => $diagnostics,
            ], 502);
            return;
        }

        $models = [];
        foreach (($data['models'] ?? []) as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $methods = $item['supportedGenerationMethods'] ?? [];
            if (!empty($methods) && is_array($methods) && !in_array('generateContent', $methods, true)) {
                continue;
            }

            $normalized = str_starts_with($name, 'models/') ? substr($name, 7) : $name;
            if ($normalized !== '') {
                $models[] = $normalized;
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        $this->json([
            'success' => true,
            'message' => 'Kết nối Gemini thành công (' . count($models) . ' model). Đã cập nhật danh sách chọn.',
            'models' => $models,
            'current' => (string) ($this->settingModel->getByKey('ai_gemini_model') ?? 'gemini-1.5-flash'),
            'diagnostics' => $diagnostics,
        ]);
    }

    private function resolveOpenAiApiKey(): string
    {
        $inputKey = trim((string) ($_POST['api_key'] ?? ''));
        if ($inputKey !== '' && !str_contains($inputKey, '***')) {
            return $inputKey;
        }

        $candidateKeys = [
            'ai_openai_api_key',
            'openai_api_key',
            'OPENAI_API_KEY',
        ];

        foreach ($candidateKeys as $key) {
            $value = trim((string) ($this->settingModel->getByKey($key) ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['OPENAI_API_KEY', 'openai_api_key'] as $envKey) {
            $envValue = trim((string) (getenv($envKey) ?: ''));
            if ($envValue !== '') {
                return $envValue;
            }
        }

        return '';
    }

    private function resolveGeminiApiKey(): string
    {
        $inputKey = trim((string) ($_POST['api_key'] ?? ''));
        if ($inputKey !== '' && !str_contains($inputKey, '***')) {
            return $inputKey;
        }

        $candidateKeys = [
            'ai_gemini_api_key',
            'gemini_api_key',
            'GEMINI_API_KEY',
        ];

        foreach ($candidateKeys as $key) {
            $value = trim((string) ($this->settingModel->getByKey($key) ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['GEMINI_API_KEY', 'gemini_api_key'] as $envKey) {
            $envValue = trim((string) (getenv($envKey) ?: ''));
            if ($envValue !== '') {
                return $envValue;
            }
        }

        return '';
    }

    private function applyProxy($ch): void
    {
        $proxy = trim((string) ($this->settingModel->getByKey('ai_proxy') ?? ''));
        if ($proxy === '') {
            $proxy = trim((string) (
                getenv('AI_PROXY') ?:
                getenv('HTTPS_PROXY') ?:
                getenv('HTTP_PROXY') ?:
                getenv('ALL_PROXY') ?: ''
            ));
        }

        if ($proxy === '') {
            $proxy = $this->detectLocalDevProxy();
        }

        if ($proxy !== '') {
            if (preg_match('/^socks5:\/\//i', $proxy)) {
                $proxy = preg_replace('/^socks5:\/\//i', 'socks5h://', $proxy);
            } elseif (!preg_match('/^[a-z0-9]+:\/\//i', $proxy) && (str_contains($proxy, '10808') || str_contains($proxy, '1080'))) {
                $proxy = 'socks5h://' . $proxy;
            }
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
    }

    private function detectLocalDevProxy(): string
    {
        $ports = [
            ['port' => 10808, 'type' => 'socks5h://'],
            ['port' => 7890,  'type' => 'http://'],
            ['port' => 10809, 'type' => 'http://'],
        ];

        foreach ($ports as $p) {
            $fp = @fsockopen('127.0.0.1', $p['port'], $errno, $errstr, 0.05);
            if ($fp) {
                fclose($fp);
                return $p['type'] . '127.0.0.1:' . $p['port'];
            }
        }

        return '';
    }
}