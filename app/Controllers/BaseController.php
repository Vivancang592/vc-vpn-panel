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
     * Định dạng số tiền động theo cấu hình CSDL
     */
    public function formatMoney($amount): string
    {
        if (empty($this->settings) && class_exists('App\Models\Setting')) {
            $settingModel = new Setting();
            $this->settings = $settingModel->getAllAsKeyValue();
        }

        $symbol   = $this->settings['currency_symbol'] ?? '¥';
        $position = $this->settings['currency_position'] ?? 'right';
        $decimals = isset($this->settings['currency_decimals']) ? (int)$this->settings['currency_decimals'] : 2;

        $formattedNumber = number_format((float)$amount, $decimals, '.', ',');

        if ($position === 'left') {
            return $symbol . $formattedNumber;
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
                    $_SESSION['role']               = $currentUser['role'] ?? 'user';

                    $data['currentUser'] = $currentUser;
                } else {
                    unset($_SESSION['user_id']);
                }
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
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
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