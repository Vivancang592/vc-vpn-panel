<?php
// Probe P3: khởi động controller qua CLI để xác nhận index() chạy được (không HTTP).
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/vendor/autoload.php';
$_ENV = array_merge($_ENV, parse_ini_file(BASE_PATH . '/.env'));
session_start();
$_SESSION['user_id'] = 4;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'uitest';
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$ok = 0; $fail = 0;
foreach ([['Admin\\AiFanpageController', 'AiFanpage'], ['Admin\\AiReplyController', 'AiReply']] as [$cls, $name]) {
    try {
        $c = new $cls();
        ob_start();
        $c->index();
        $html = (string) ob_get_clean();
        $len = strlen($html);
        $hasTitle = str_contains($html, 'glass-card') && $len > 3000;
        echo $hasTitle ? "PASS $name render ($len bytes)" . PHP_EOL : "FAIL $name render ($len bytes)" . PHP_EOL;
        $hasTitle ? $ok++ : $fail++;
    } catch (Throwable $e) {
        echo 'FAIL ' . $name . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
        $fail++;
    }
}
echo "P3 PROBE: $ok PASS / $fail FAIL" . PHP_EOL;
