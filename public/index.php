<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('error_log', '/tmp/php_error.log');

// Configure the session cookie before starting the session.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Lưu nguồn truy cập đầu tiên của phiên để gắn vào Access Log khi người dùng đăng nhập.
// Chỉ giữ tên miền referrer và tham số UTM, không lưu URL đầy đủ có thể chứa dữ liệu nhạy cảm.
if (!isset($_SESSION['traffic_attribution'])) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $landingPath = parse_url($requestUri, PHP_URL_PATH);
    $landingPath = is_string($landingPath) && str_starts_with($landingPath, '/') ? $landingPath : '/';

    $queryParams = [];
    $queryString = parse_url($requestUri, PHP_URL_QUERY);
    if (is_string($queryString)) {
        parse_str($queryString, $queryParams);
    }

    $normalizeValue = static function (mixed $value, int $maxLength): ?string {
        $value = trim((string)$value);
        return $value === '' ? null : substr($value, 0, $maxLength);
    };

    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $referrerHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?: ''));
    $referrerHost = preg_replace('/[^a-z0-9.-]/', '', $referrerHost) ?? '';

    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = preg_replace('/:\\d+$/', '', $requestHost) ?? '';
    $internalHosts = array_filter([
        $requestHost,
        preg_replace('/^www\\./', '', $requestHost),
        $requestHost !== '' ? 'www.' . preg_replace('/^www\\./', '', $requestHost) : ''
    ]);
    if (in_array($referrerHost, $internalHosts, true)) {
        $referrerHost = '';
    }

    $utmSource = $normalizeValue($queryParams['utm_source'] ?? null, 100);
    $sourceCandidate = strtolower($utmSource ?: $referrerHost);
    $sourceCandidate = preg_replace('/[^a-z0-9._-]/', '', $sourceCandidate) ?? '';
    if (in_array($sourceCandidate, ['facebook', 'fb', 'facebook_ads'], true)
        || preg_match('/(^|\\.)(facebook\\.com|fb\\.me)$/', $sourceCandidate)) {
        $source = 'facebook';
    } elseif (in_array($sourceCandidate, ['google', 'gads', 'google_ads'], true)
        || preg_match('/(^|\\.)google\\.[a-z.]+$/', $sourceCandidate)) {
        $source = 'google';
    } elseif ($sourceCandidate !== '') {
        $source = substr($sourceCandidate, 0, 100);
    } else {
        $source = 'direct';
    }

    $_SESSION['traffic_attribution'] = [
        'source'        => $source,
        'referrer_host' => $normalizeValue($referrerHost, 255),
        'landing_path'  => substr($landingPath, 0, 255),
        'utm_source'    => $utmSource,
        'utm_medium'    => $normalizeValue($queryParams['utm_medium'] ?? null, 100),
        'utm_campaign'  => $normalizeValue($queryParams['utm_campaign'] ?? null, 150)
    ];
}

define('BASE_PATH', dirname(__DIR__));

// 1. Tự động nạp thư viện Composer (nếu có)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// 2. Nạp file cấu hình môi trường .env
if (file_exists(BASE_PATH . '/.env')) {
    $envLines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1], " \t\n\r\0\x0B\"'");
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// 3. Tải cấu hình chung và thiết lập môi trường
$appConfig = file_exists(BASE_PATH . '/config/app.php') ? require BASE_PATH . '/config/app.php' : [];
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Ho_Chi_Minh');

// Only expose errors when debug mode is explicitly enabled.
$debugMode = (bool) ($appConfig['debug'] ?? false);
ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('display_startup_errors', $debugMode ? '1' : '0');
error_reporting(E_ALL);

// 4. Autoload đơn giản cho các class thuộc App\ namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// 5. Nạp bảng định tuyến Routes
$webRoutes = file_exists(BASE_PATH . '/routes/web.php') ? require BASE_PATH . '/routes/web.php' : [];
$apiRoutes = file_exists(BASE_PATH . '/routes/api.php') ? require BASE_PATH . '/routes/api.php' : [];
$routes = array_merge($webRoutes, $apiRoutes);

// 6. Xử lý Request & Router
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$routeKey = $requestMethod . ' ' . $requestUri;

if (array_key_exists($routeKey, $routes)) {
    $handler = $routes[$routeKey];
    $controllerName = "App\\Controllers\\" . $handler[0];
    $methodName = $handler[1];

    $isUserRoute = $handler[0] === 'UserController';
    $isAdminRoute = str_starts_with($handler[0], 'Admin\\');
    if (($isUserRoute || $isAdminRoute) && empty($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Vui lòng đăng nhập để tiếp tục.';
        header('Location: /login', true, 302);
        exit;
    }

    if ($isAdminRoute && ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo '403 | Bạn không có quyền truy cập trang này.';
        exit;
    }

    if (class_exists($controllerName)) {
        $controller = new $controllerName();
        if (method_exists($controller, $methodName)) {
            $controller->$methodName();
            exit;
        }
    }
}

// 7. Xử lý lỗi 404 Not Found
http_response_code(404);
if (strpos($requestUri, '/api/') === 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'API endpoint không tồn tại.'], JSON_UNESCAPED_UNICODE);
} else {
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>404 Not Found</title></head><body style="display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;background:#f2f2f7;color:#1c1c1e;"><h1>404 | Trang không tồn tại</h1></body></html>';
}
