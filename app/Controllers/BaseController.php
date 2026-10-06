<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Setting;
use App\Models\SystemLog;

abstract class BaseController
{
    protected array $settings = [];

    /**
     * Hàm khởi tạo BaseController
     * Tự động nạp cấu hình hệ thống từ CSDL cho các Controller kế thừa
     */
    public function __construct()
    {
        if (empty($this->settings) && class_exists('App\Models\Setting')) {
            $settingModel = new Setting();
            $this->settings = $settingModel->getAllAsKeyValue();
        }
    }

    /**
     * Tạo và lưu CSRF token vào session
     */
    protected function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Xác minh CSRF token
     */
    protected function validateCsrfToken(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Lấy IP thực của Client an toàn chống giả mạo Header
     */
    protected function getClientIp(): string
    {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                foreach (explode(',', $_SERVER[$header]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        $fallbackIp = $ip;
                    }
                }
            }
        }
        return $fallbackIp ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Thay macro {domain} trong nội dung bằng tên miền THẬT của hệ thống.
     *
     * Prompt content_article (mục 3-4) YÊU CẦU model giữ nguyên {domain}
     * trong URL — chỉ ChatbotService từng thay, pipeline bài viết Fanpage
     * không thay ở đâu → admin thấy link thô, khách bấm không được. Dùng
     * chung cho AiBaseController (hiển thị/lên lịch) và CronController
     * (hàng đợi cũ). Thứ tự nguồn theo resolveSiteUrl() của ChatbotService:
     * site_url/app_url (DB) → APP_URL → HTTP_HOST → fallback vcvpn.com.
     */
    protected function replaceDomainMacro(string $text): string
    {
        if (!str_contains($text, '{domain}')) {
            return $text;
        }

        $siteUrl = rtrim((string) ($this->settings['site_url'] ?? $this->settings['app_url'] ?? (getenv('APP_URL') ?: '')), '/');
        if ($siteUrl === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $siteUrl = $host !== '' ? 'https://' . $host : '';
        }

        $domain = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: 'vcvpn.com');

        return str_replace('{domain}', $domain, $text);
    }

    /**
     * Định dạng số tiền động theo cấu hình CSDL (Mặc định chuẩn VND)
     */
    public function formatMoney($amount): string
    {
        if (empty($this->settings) && class_exists('App\Models\Setting')) {
            $settingModel = new Setting();
            $this->settings = $settingModel->getAllAsKeyValue();
        }

        $symbol   = $this->settings['currency_symbol'] ?? 'đ';
        $position = $this->settings['currency_position'] ?? 'right';
        $decimals = isset($this->settings['currency_decimals']) ? (int)$this->settings['currency_decimals'] : 0;

        $formattedNumber = number_format((float)$amount, $decimals, '.', ',');

        if ($position === 'left') {
            return $symbol . ' ' . $formattedNumber;
        }

        return $formattedNumber . ' ' . $symbol;
    }

    /**
     * Ghi nhật ký thao tác hệ thống vào CSDL
     */
    protected function logActivity(string $action, ?string $description = null): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $ipAddress = $this->getClientIp();

        if (class_exists('App\Models\SystemLog')) {
            $systemLog = new SystemLog();
            $systemLog->create([
                'user_id'     => (int)$_SESSION['user_id'],
                'action'      => $action,
                'description' => $description,
                'ip_address'  => $ipAddress,
                'created_at'  => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Render Giao diện View (Tự động nạp $settings hệ thống & CSRF Token)
     */
    protected function render(string $view, array $data = []): void
    {
        if (empty($this->settings) && class_exists('App\Models\Setting')) {
            $settingModel = new Setting();
            $this->settings = $settingModel->getAllAsKeyValue();
        }

        if (!isset($data['settings'])) {
            $data['settings'] = $this->settings;
        } else {
            $data['settings'] = array_merge($this->settings, $data['settings']);
        }

        if (!isset($data['formatMoney'])) {
            $data['formatMoney'] = fn($amount) => $this->formatMoney($amount);
        }

        // Tự động inject csrf_token vào tất cả các view
        $data['csrf_token'] = $this->generateCsrfToken();

        if (isset($_SESSION['user_id'])) {
            if (class_exists('App\Models\User')) {
                $userModel = new User();
                $currentUser = $userModel->findById((int)$_SESSION['user_id']);

                if ($currentUser) {
                    $_SESSION['username']           = $currentUser['username'] ?? $_SESSION['username'] ?? '';
                    $_SESSION['full_name']          = $currentUser['full_name'] ?? $_SESSION['full_name'] ?? '';
                    $_SESSION['email']              = $currentUser['email'] ?? '';
                    $_SESSION['balance']            = $currentUser['balance'] ?? 0;
                    $_SESSION['commission_balance'] = $currentUser['commission_balance'] ?? 0;
                    $_SESSION['created_at']         = $currentUser['created_at'] ?? '';
                    $_SESSION['role']               = ($currentUser['role'] ?? '') === 'admin' ? 'admin' : 'user';

                    $data['currentUser'] = $currentUser;
                } else {
                    unset($_SESSION['user_id']);
                }
            }
        }

        // Thu flash thông báo vào $vcToasts TRƯỚC khi render view admin.
        // Mọi trang admin đều đi qua render() này nên toast hiển thị đúng trang
        // sau mỗi thao tác, với màu viền theo trạng thái (success/danger/warning/info)
        // và không còn phụ thuộc đoạn code flash copy trong từng view.
        if (strpos($view, 'admin.') === 0) {
            $vcToasts = [];

            if (!empty($_SESSION['flash_message'])) {
                $vcToasts[] = [
                    'message' => (string)$_SESSION['flash_message'],
                    'type'    => (string)($_SESSION['flash_type'] ?? 'info'),
                ];
                unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            }

            if (!empty($_SESSION['success'])) {
                $vcToasts[] = ['message' => (string)$_SESSION['success'], 'type' => 'success'];
                unset($_SESSION['success']);
            }

            if (!empty($_SESSION['error'])) {
                $vcToasts[] = ['message' => (string)$_SESSION['error'], 'type' => 'danger'];
                unset($_SESSION['error']);
            }

            if ($vcToasts) {
                $data['vcToasts'] = $vcToasts;
            }
        }

        extract($data);
        $viewFile = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            die("View [{$view}] không tồn tại.");
        }
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        // Cho phép controller đã gửi heartbeat output (phát hiện client ngắt kết nối)
        // vẫn trả JSON thuần — chỉ set header khi chưa gửi.
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $url): void
    {
        // Chống Header Injection
        $url = str_replace(["\r", "\n"], '', $url);
        header("Location: {$url}");
        exit;
    }
}