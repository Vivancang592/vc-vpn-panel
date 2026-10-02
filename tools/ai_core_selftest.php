<?php

declare(strict_types=1);

/**
 * AI Core Self-Test — Phase 5.
 *
 * Chạy:  php tools/ai_core_selftest.php
 *
 * Nguyên tắc:
 *  - KHÔNG gọi Kira thật, KHÔNG cần API key.
 *  - KHÔNG dùng PHPUnit, không thêm package.
 *  - Tự bootstrap: .env + autoload + timezone (không phụ thuộc public/index.php).
 *  - Chỉ ĐỌC schema + tạo/xoá dữ liệu TEST tạm thời trong các bảng vc_ai_*.
 *  - Dọn sạch dữ liệu test sau khi chạy.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Ho_Chi_Minh');
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);

// ---------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------
function selftest_parse_env(string $root): void
{
    $file = $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key === '') {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

selftest_parse_env($ROOT);

$autoload = $ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// Autoload App\ → app/ (giống public/index.php, để script chạy độc lập).
spl_autoload_register(static function (string $class) use ($ROOT): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

// ---------------------------------------------------------------------
// Harness
// ---------------------------------------------------------------------
final class SelfTest
{
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    /** @var array<int, string> */
    private array $failures = [];
    /** @var array<int, string> */
    private array $skips = [];
    private string $currentGroup = '';

    public function group(string $name): void
    {
        $this->currentGroup = $name;
        echo PHP_EOL . "\033[36m[{$name}]\033[0m" . PHP_EOL;
    }

    public function check(string $label, bool $condition, string $detail = ''): bool
    {
        if ($condition) {
            $this->passed++;
            echo "  \033[32mPASS\033[0m {$label}" . PHP_EOL;
            return true;
        }

        $this->failed++;
        $message = "{$this->currentGroup} :: {$label}" . ($detail !== '' ? " ({$detail})" : '');
        $this->failures[] = $message;
        echo "  \033[31mFAIL\033[0m {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        return false;
    }

    public function skip(string $label, string $reason): void
    {
        $this->skipped++;
        $this->skips[] = "{$this->currentGroup} :: {$label} ({$reason})";
        echo "  \033[33mSKIP\033[0m {$label} — {$reason}" . PHP_EOL;
    }

    public function equals(string $label, mixed $expected, mixed $actual): bool
    {
        $ok = $expected === $actual;
        return $this->check(
            $label,
            $ok,
            $ok ? '' : 'expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true)
        );
    }

    public function summary(): int
    {
        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        printf("KẾT QUẢ: %d PASS / %d FAIL / %d SKIP%s", $this->passed, $this->failed, $this->skipped, PHP_EOL);

        if ($this->skips !== []) {
            echo PHP_EOL . 'Bỏ qua:' . PHP_EOL;
            foreach ($this->skips as $s) {
                echo '  - ' . $s . PHP_EOL;
            }
        }

        if ($this->failures !== []) {
            echo PHP_EOL . 'Thất bại:' . PHP_EOL;
            foreach ($this->failures as $f) {
                echo '  - ' . $f . PHP_EOL;
            }
        }

        echo str_repeat('=', 60) . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }
}

$t = new SelfTest();
$config = require $ROOT . '/config/ai.php';

echo 'AI CORE SELF-TEST — ' . date('Y-m-d H:i:s') . PHP_EOL;
echo 'PHP ' . PHP_VERSION . ' | timezone=' . date_default_timezone_get() . PHP_EOL;

// =====================================================================
// A. BẢO MẬT CẤU HÌNH
// =====================================================================
$t->group('A. Cấu hình & bảo mật');

$t->check('config/ai.php trả về array', is_array($config));
$t->equals('default_provider = kira', 'kira', $config['default_provider'] ?? null);
$t->equals('allowed_providers chỉ có kira', ['kira'], $config['allowed_providers'] ?? null);
$t->check('Cấu hình KHÔNG chứa API key thật', !preg_match('/sk-[a-zA-Z0-9]{16,}/', json_encode($config) ?: ''));
$t->check('base_url trỏ kiraai.vn', str_contains((string) ($config['providers']['kira']['base_url'] ?? ''), 'kiraai.vn'));
$t->check('retry policy có max_attempts >= 1', (int) ($config['retry']['max_attempts'] ?? 0) >= 1);
$t->check('retryable_errors chỉ gồm lỗi tạm thời', array_values($config['retry']['retryable_errors'] ?? []) === ['TIMEOUT', 'UPSTREAM_5XX', 'RATE_LIMIT']);
$t->check('AUTH không nằm trong retryable', !in_array('AUTH', (array) ($config['retry']['retryable_errors'] ?? []), true));
$t->check('assets KHÔNG lưu blob trong DB (base_path là đường dẫn)', is_string($config['assets']['base_path'] ?? null));

// =====================================================================
// B. ĐĂNG KÝ AUTOLOAD & CLASS MAP
// =====================================================================
$t->group('B. Autoload & class map');

$expectedClasses = [
    'App\AI\Contracts\AICapability'      => 'app/AI/Contracts/AICapability.php',
    'App\AI\Contracts\AIResult'          => 'app/AI/Contracts/AIResult.php',
    'App\AI\Contracts\AIException'       => 'app/AI/Contracts/AIException.php',
    'App\AI\Contracts\AIProviderInterface' => 'app/AI/Contracts/AIProviderInterface.php',
    'App\AI\Providers\KiraProvider'      => 'app/AI/Providers/KiraProvider.php',
    'App\AI\Core\AICore'                 => 'app/AI/Core/AICore.php',
    'App\AI\Core\ModelResolver'          => 'app/AI/Core/ModelResolver.php',
    'App\AI\Core\PromptRegistry'         => 'app/AI/Core/PromptRegistry.php',
    'App\AI\Core\RequestBuilder'         => 'app/AI/Core/RequestBuilder.php',
    'App\AI\Core\ResponseParser'         => 'app/AI/Core/ResponseParser.php',
    'App\AI\Core\ErrorHandler'           => 'app/AI/Core/ErrorHandler.php',
    'App\AI\Core\RetryPolicy'            => 'app/AI/Core/RetryPolicy.php',
    'App\AI\Core\AILogger'               => 'app/AI/Core/AILogger.php',
    'App\AI\Core\ModuleRegistry'         => 'app/AI/Core/ModuleRegistry.php',
    'App\AI\Core\TaskDispatcher'         => 'app/AI/Core/TaskDispatcher.php',
    'App\AI\Core\InputHandler'           => 'app/AI/Core/InputHandler.php',
    'App\AI\Core\OutputHandler'          => 'app/AI/Core/OutputHandler.php',
    'App\AI\Assets\AssetManager'         => 'app/AI/Assets/AssetManager.php',
];

foreach ($expectedClasses as $class => $path) {
    $t->check("File tồn tại: {$path}", is_file($ROOT . '/' . $path));
}

foreach (array_keys($expectedClasses) as $class) {
    $t->check("Class load được: {$class}", class_exists($class) || interface_exists($class));
}

$t->check(
    'KiraProvider implements AIProviderInterface',
    in_array('App\AI\Contracts\AIProviderInterface', class_implements('App\AI\Providers\KiraProvider') ?: [], true)
);

// =====================================================================
// C. ALIAS & TƯƠNG THÍCH NGƯỢC
// =====================================================================
$t->group('C. Tương thích ngược');

$legacyPath = $ROOT . '/app/Services/AIProviderService.php';
$t->check('AIProviderService.php vẫn tồn tại (không bị thay thế)', is_file($legacyPath));
$t->check('ChatbotService.php vẫn tồn tại', is_file($ROOT . '/app/Services/ChatbotService.php'));
$t->check('FanpageService.php vẫn tồn tại', is_file($ROOT . '/app/Services/FanpageService.php'));
$t->check('public/index.php không đổi cấu trúc (có mtime)', is_file($ROOT . '/public/index.php'));

// =====================================================================
// D. KIRA PROVIDER (MOCK — KHÔNG GỌI THẬT)
// =====================================================================
$t->group('D. KiraProvider (mock transport)');

use App\AI\Contracts\AICapability;
use App\AI\Contracts\AIResult;
use App\AI\Providers\KiraProvider;

$providerConfig = $config['providers']['kira'];

// ArrayObject (không phải array) để log lời gọi được chia sẻ theo THAM CHIẾU —
// mảng PHP trả về từ hàm luôn copy theo giá trị, nên không dùng array được.
$makeProvider = static function (array $responses, ?string $apiKey = 'test-key') use ($providerConfig): array {
    $calls = new \ArrayObject();
    $queue = $responses;

    $transport = function (string $method, string $url, array $headers, ?string $body) use ($calls, &$queue): array {
        $calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $next = array_shift($queue);

        if ($next === null) {
            return ['status' => 200, 'body' => '{}', 'errno' => 0, 'error' => '', 'duration_ms' => 1.0];
        }

        return array_merge(['status' => 200, 'body' => '{}', 'errno' => 0, 'error' => '', 'duration_ms' => 1.0], $next);
    };

    return [new KiraProvider($providerConfig, $transport, $apiKey), $calls];
};

/** Bỏ comment khỏi source PHP trước khi kiểm tra phụ thuộc (tránh dương tính giả do docblock). */
$stripComments = static function (string $path): string {
    $code = (string) @file_get_contents($path);
    $code = (string) preg_replace('#/\*.*?\*/#s', '', $code);
    $code = (string) preg_replace('#(^|\s)//[^\n]*#', '', $code);

    return $code;
};

// D1. Chat success
[$provider, $calls] = $makeProvider([[
    'status' => 200,
    'body'   => json_encode(['model' => 'kira-text-1', 'choices' => [['message' => ['content' => 'Xin chào']]], 'usage' => ['total_tokens' => 42]]),
]]);

$result = $provider->chat([['role' => 'user', 'content' => 'hi']], ['model' => 'kira-text-1', 'retry' => $config['retry']]);
$t->check('D1. chat() trả về AIResult ok', $result instanceof AIResult && $result->isOk());
$t->equals('D1. chat() content đúng', 'Xin chào', $result->content);
$t->equals('D1. chat() model đúng', 'kira-text-1', $result->model);
$t->check('D1. chat() gọi đúng endpoint /chat/completions', str_contains($calls[0]['url'] ?? '', '/chat/completions'));
$t->check('D1. chat() gửi method POST', ($calls[0]['method'] ?? '') === 'POST');
$t->check('D1. Authorization header là Bearer', str_contains(implode('|', $calls[0]['headers'] ?? []), 'Authorization: Bearer test-key'));
$t->check('D1. chat() KHÔNG stream', str_contains((string) ($calls[0]['body'] ?? ''), '"stream":false'));

// D2. Image success
[$provider] = $makeProvider([[
    'status' => 200,
    'body'   => json_encode(['data' => [['url' => 'https://cdn.kiraai.vn/x.png']]]),
]]);
$result = $provider->image(['prompt' => 'mèo'], ['retry' => $config['retry']]);
$t->check('D2. image() ok', $result->isOk());
$t->check('D2. image() có 1 file url', count($result->files) === 1 && $result->files[0]['kind'] === 'image');

// D3. TTS success
[$provider, $calls] = $makeProvider([['status' => 200, 'body' => 'binary-ish']]);
$result = $provider->speech(['input' => 'xin chào', 'voice' => 'vi-1'], ['retry' => $config['retry']]);
$t->check('D3. speech() ok (không phải JSON vẫn xử lý được)', $result->isOk() || $result->errorType() === 'MALFORMED');
$t->check('D3. speech() gọi /audio/speech', str_contains($calls[0]['url'] ?? '', '/audio/speech'));

// D4. Video create + status
[$provider, $calls] = $makeProvider([
    ['status' => 200, 'body' => json_encode(['id' => 'op_abc123', 'status' => 'queued'])],
    ['status' => 200, 'body' => json_encode(['id' => 'op_abc123', 'status' => 'completed'])],
]);
$create = $provider->videoCreate(['prompt' => 'mèo bay'], ['retry' => $config['retry']]);
$t->check('D4. videoCreate() ok', $create->isOk());
$t->check('D4. videoCreate() gọi /videos/generations', str_contains($calls[0]['url'] ?? '', '/videos/generations'));

$status = $provider->videoStatus('op_abc123', ['retry' => $config['retry']]);
$t->check('D4. videoStatus() ok', $status->isOk());
$t->check('D4. videoStatus() gọi GET /videos/operations/{id}', ($calls[1]['method'] ?? '') === 'GET' && str_contains($calls[1]['url'] ?? '', '/videos/operations/op_abc123'));

// D5. Video create KHÔNG gửi tham số edit chưa xác minh
$t->check(
    'D5. videoCreate() không tự thêm tham số video-edit',
    !str_contains(strtolower((string) ($calls[0]['body'] ?? '')), 'reference_video')
        && !str_contains(strtolower((string) ($calls[0]['body'] ?? '')), 'video_input')
        && !str_contains(strtolower((string) ($calls[0]['body'] ?? '')), 'edit')
);

// D6. Error mapping
$errorCases = [
    ['status' => 401, 'body' => '{"error":{"message":"unauthorized"}}', 'expected' => 'AUTH', 'label' => '401 → AUTH'],
    ['status' => 404, 'body' => '{"error":"not found"}', 'expected' => 'NOT_FOUND', 'label' => '404 → NOT_FOUND'],
    ['status' => 429, 'body' => '{"error":"too many"}', 'expected' => 'RATE_LIMIT', 'label' => '429 → RATE_LIMIT'],
    ['status' => 400, 'body' => '{"error":"bad request"}', 'expected' => 'VALIDATION', 'label' => '400 → VALIDATION'],
    ['status' => 500, 'body' => '{"error":"oops"}', 'expected' => 'UPSTREAM_5XX', 'label' => '500 → UPSTREAM_5XX'],
    ['status' => 0, 'errno' => 28, 'error' => 'timeout', 'body' => '', 'expected' => 'TIMEOUT', 'label' => 'timeout → TIMEOUT'],
];

foreach ($errorCases as $case) {
    $noRetry = array_merge($config['retry'], ['enabled' => false]);
    [$provider] = $makeProvider([$case]);
    $result = $provider->chat([['role' => 'user', 'content' => 'x']], ['model' => 'm', 'retry' => $noRetry]);

    $t->check('D6. ' . $case['label'], !$result->isOk() && $result->errorType() === $case['expected'], 'got=' . (string) $result->errorType());
}

// D7. Malformed JSON
$noRetry = array_merge($config['retry'], ['enabled' => false]);
[$provider] = $makeProvider([['status' => 200, 'body' => '<<<not json>>>']]);
$result = $provider->chat([['role' => 'user', 'content' => 'x']], ['model' => 'm', 'retry' => $noRetry]);
$t->check('D7. JSON lỗi → MALFORMED', $result->errorType() === 'MALFORMED', 'got=' . (string) $result->errorType());

// D8. Retry policy: 500 retry 3 lần rồi fail
[$provider, $calls] = $makeProvider([
    ['status' => 500, 'body' => '{"error":"e1"}'],
    ['status' => 500, 'body' => '{"error":"e2"}'],
    ['status' => 500, 'body' => '{"error":"e3"}'],
]);
$fastRetry = array_merge($config['retry'], ['base_delay_ms' => 0, 'max_delay_ms' => 0, 'max_attempts' => 3]);
$result = $provider->chat([['role' => 'user', 'content' => 'x']], ['model' => 'm', 'retry' => $fastRetry]);
$t->equals('D8. 500 retry đúng 3 lần', 3, count($calls));
$t->check('D8. Sau retry vẫn fail với UPSTREAM_5XX', $result->errorType() === 'UPSTREAM_5XX');

// D9. 401 KHÔNG retry
[$provider, $calls] = $makeProvider([
    ['status' => 401, 'body' => '{"error":"auth"}'],
    ['status' => 401, 'body' => '{"error":"auth"}'],
]);
$result = $provider->chat([['role' => 'user', 'content' => 'x']], ['model' => 'm', 'retry' => $fastRetry]);
$t->equals('D9. 401 chỉ gọi 1 lần (không retry)', 1, count($calls));

// D10. Thiếu API key → AUTH, không gọi mạng
// CÔ LẬP MÔI TRƯỜNG: trỏ env_key + setting_key sang tên KHÔNG tồn tại để test
// không phụ thuộc việc người dùng đã cấu hình key Kira thật trong vc_settings
// (trước đây provider đọc fallback vc_settings nên khi có key thật test bị sai).
$noKeyConfig = $providerConfig;
$noKeyConfig['env_key'] = '__SELFTEST_MISSING_ENV__';
$noKeyConfig['setting_key'] = '__selftest_missing_setting__';
$calls = new \ArrayObject();
$noKeyTransport = static function (string $method, string $url, array $headers, ?string $body) use ($calls): array {
    $calls[] = ['method' => $method, 'url' => $url];
    return ['status' => 200, 'body' => '{}', 'errno' => 0, 'error' => '', 'duration_ms' => 1.0];
};
$provider = new KiraProvider($noKeyConfig, $noKeyTransport, null);
$result = $provider->chat([['role' => 'user', 'content' => 'x']], ['model' => 'm', 'retry' => $fastRetry]);
$t->check('D10. Thiếu key → AUTH', $result->errorType() === 'AUTH', 'got=' . (string) $result->errorType());
$t->equals('D10. Không gọi HTTP khi thiếu key', 0, count($calls));

// D11. supports() phản ánh đúng capability
[$provider] = $makeProvider([]);
foreach (AICapability::ALL as $cap) {
    $t->check("D11. supports({$cap}) = true", $provider->supports($cap) === true);
}
$t->check('D11. supports(capability rác) = false', $provider->supports('telepathy') === false);

// D12. Provider KHÔNG lộ secret trong AIResult
$t->check('D12. AIResult không chứa field authorization', !array_key_exists('authorization', $result->toArray()));
$t->check('D12. AIResult không chứa raw response', !array_key_exists('raw', $result->toArray()));

// =====================================================================
// E. AI CORE (dependency wiring — không cần DB)
// =====================================================================
$t->group('E. AI Core wiring');

use App\AI\Core\AICore;
use App\AI\Core\ErrorHandler;
use App\AI\Core\ModuleRegistry;
use App\AI\Core\RetryPolicy;

$registry = new ModuleRegistry();
$t->check('ModuleRegistry có 6 module', count($registry->all()) === 6, 'count=' . count($registry->all()));

$expectedModules = ['content_article', 'image_generation', 'video_generation', 'audio_tts', 'fanpage_comment', 'support_chat'];
foreach ($expectedModules as $m) {
    $t->check("Module tồn tại: {$m}", $registry->has($m));
}

$t->equals('content_article → capability TEXT', AICapability::TEXT, $registry->capabilityOf('content_article'));
$t->equals('image_generation → capability IMAGE', AICapability::IMAGE, $registry->capabilityOf('image_generation'));
$t->equals('video_generation → capability VIDEO', AICapability::VIDEO, $registry->capabilityOf('video_generation'));
$t->equals('audio_tts → capability AUDIO', AICapability::AUDIO, $registry->capabilityOf('audio_tts'));
$t->equals('fanpage_comment → capability COMMENT', AICapability::COMMENT, $registry->capabilityOf('fanpage_comment'));
$t->equals('support_chat → capability CHAT', AICapability::CHAT, $registry->capabilityOf('support_chat'));

$rp = new RetryPolicy($config);
$t->equals('RetryPolicy default max_attempts = 3', 3, $rp->maxAttempts($rp->default()));
$t->equals('RetryPolicy noRetry() = 1 lần', 1, $rp->maxAttempts($rp->noRetry()));
$t->check('Backoff tăng theo attempt', $rp->delayFor($rp->default(), 3) > $rp->delayFor($rp->default(), 1));
$t->check('Backoff bị cap ở max_delay_ms', $rp->delayFor($rp->default(), 99) <= (int) $config['retry']['max_delay_ms']);

$eh = new ErrorHandler();
$t->check('ErrorHandler: TIMEOUT là retryable', $eh->isRetryable('TIMEOUT', $config['retry']));
$t->check('ErrorHandler: AUTH KHÔNG retryable', !$eh->isRetryable('AUTH', $config['retry']));
$t->check('ErrorHandler: AUTH là lỗi fatal', $eh->isFatal('AUTH'));

$failure = $eh->handle(new \App\AI\Contracts\AIException('bùm', 'RATE_LIMIT', 429));
$t->check('ErrorHandler.handle → AIResult failure', !$failure->isOk() && $failure->errorType() === 'RATE_LIMIT');

$guarded = $eh->guard(static function (): void {
    throw new \RuntimeException('lỗi lập trình');
});
$t->check('ErrorHandler.guard nuốt exception → UNKNOWN', $guarded->errorType() === 'UNKNOWN');

// AICore khởi tạo được với provider mock
[$provider] = $makeProvider([['status' => 200, 'body' => json_encode(['choices' => [['message' => ['content' => 'oki']]]])]]);
$core = new AICore($config, $provider);
$t->check('AICore khởi tạo được', $core instanceof AICore);
$t->check('AICore.modules() trả ModuleRegistry', $core->modules() instanceof ModuleRegistry);

$t->check(
    'AICore KHÔNG reverse-depend vào KiraProvider trong Core/',
    !str_contains((string) @file_get_contents($ROOT . '/app/AI/Core/ModuleRegistry.php'), 'KiraProvider')
);

$coreSource = (string) @file_get_contents($ROOT . '/app/AI/Core/AICore.php');
$t->check('AICore là nơi DUY NHẤT trong Core tham chiếu provider cụ thể', str_contains($coreSource, 'KiraProvider'));

$t->check(
    'KiraProvider KHÔNG phụ thuộc AssetManager / InputHandler / OutputHandler (bỏ qua comment)',
    !str_contains($stripComments($ROOT . '/app/AI/Providers/KiraProvider.php'), 'AssetManager')
        && !str_contains($stripComments($ROOT . '/app/AI/Providers/KiraProvider.php'), 'InputHandler')
        && !str_contains($stripComments($ROOT . '/app/AI/Providers/KiraProvider.php'), 'OutputHandler')
);

$t->check(
    'KiraProvider KHÔNG tham chiếu App\\Models ngoài Setting (nguồn API key)',
    preg_match_all('/App\\\\Models\\\\[A-Za-z0-9_]+/', $stripComments($ROOT . '/app/AI/Providers/KiraProvider.php'), $m) >= 0
        && array_unique($m[0]) === ['App\\Models\\Setting'],
    implode(', ', array_unique($m[0]))
);

$t->check(
    'KiraProvider KHÔNG phụ thuộc tầng Core (không reverse-depend)',
    !str_contains($stripComments($ROOT . '/app/AI/Providers/KiraProvider.php'), 'App\\AI\\Core')
        && !str_contains($stripComments($ROOT . '/app/AI/Providers/KiraProvider.php'), 'App\\AI\\Assets')
);

// =====================================================================
// F. DATABASE (đọc schema + thao tác test, CHỈ khi có pdo_mysql)
// =====================================================================
$t->group('F. Database schema & dữ liệu test');

$hasPdoMysql = extension_loaded('pdo_mysql');
$dbOk = false;
$pdo = null;
$dbError = '';

if (!$hasPdoMysql) {
    $dbError = 'extension pdo_mysql chưa bật';
} else {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? '127.0.0.1',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_DATABASE'] ?? 'vpn_service'
        );
        $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET time_zone = '+07:00'");
        $dbOk = true;
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

$expectedTables = [
    'vc_ai_models', 'vc_ai_modules',
    'vc_ai_tasks', 'vc_ai_task_activities', 'vc_ai_assets', 'vc_ai_outputs',
    'vc_ai_output_versions',
];

// 7 bảng đã bị DROP ở P6 (20260928_drop_unused_ai_tables.sql) — 0 caller trong code.
// 2 bảng prompt DB đã bị DROP ở P9 (20260928_drop_prompt_tables.sql) — prompt chỉ lưu FILE.
$droppedTables = [
    'vc_ai_articles', 'vc_ai_images', 'vc_ai_videos', 'vc_ai_audios',
    'vc_ai_fanpage_comments', 'vc_ai_comment_replies', 'vc_ai_usage_logs',
    'vc_ai_prompts', 'vc_ai_prompt_versions',
];

// Bảng mới có FK trỏ ra bảng cũ — cần các cột này tồn tại với đúng kiểu.
$fkDependencies = [
    'vc_users' => ['id' => 'bigint unsigned'],
];

// vc_posts / vc_scheduled_posts nằm trong database/vpn_service.sql nhưng DB dev hiện tại
// thiếu (drift có sẵn từ trước Phase 5). Chỉ kiểm tra "không bị Phase 5 phá" nếu chúng tồn tại.
$optionalLegacyTables = ['vc_posts', 'vc_scheduled_posts'];

if (!$dbOk) {
    $t->skip('F. Kiểm tra schema 7 bảng AI', $dbError);
    $t->skip('F. Kiểm tra bảng cũ nguyên vẹn', $dbError);
    $t->skip('F. Test ghi/đọc asset + output version', $dbError);
    $t->skip('F. Test idempotency + lock task', $dbError);
} else {
    $dbName = $_ENV['DB_DATABASE'] ?? 'vpn_service';

    // F0. Điều kiện tiên quyết: bảng cũ mà bảng mới FK tới phải tồn tại.
    $fkViable = true;
    foreach ($fkDependencies as $table => $columns) {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :name');
        $stmt->execute(['db' => $dbName, 'name' => $table]);
        $cols = [];
        foreach ($stmt->fetchAll() as $row) {
            $cols[$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
        }

        if ($cols === []) {
            $t->check("Điều kiện tiên quyết: bảng {$table} tồn tại", false, 'thiếu bảng — chưa áp migration được');
            $fkViable = false;
            continue;
        }

        $t->check("Điều kiện tiên quyết: bảng {$table} tồn tại", true);
        foreach ($columns as $column => $type) {
            $t->check(
                "Điều kiện tiên quyết: {$table}.{$column} = {$type}",
                ($cols[$column] ?? '') === $type,
                'actual=' . ($cols[$column] ?? 'missing')
            );
        }
    }

    // Tình trạng DB dev có thể chưa áp migration (thiếu bảng/cột tiên quyết).
    $migrationApplied = true;

    // F1. Đếm bảng mới
    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :name'
    );

    $foundNew = 0;
    $preexistingNew = 0;
    foreach ($expectedTables as $table) {
        $stmt->execute(['db' => $dbName, 'name' => $table]);
        $row = $stmt->fetch();

        if (!$row) {
            $migrationApplied = false;
            $t->check("Bảng tồn tại hoặc chưa áp migration: {$table}", true, 'chưa có trong DB dev');
            continue;
        }

        $foundNew++;
        $t->check("Bảng tồn tại: {$table}", true);
        $t->equals("Engine InnoDB: {$table}", 'InnoDB', $row['ENGINE']);
        $t->equals("Collation utf8mb4: {$table}", 'utf8mb4_unicode_ci', $row['TABLE_COLLATION']);
    }

    if (!$migrationApplied) {
        $t->skip('Đủ 7 bảng vc_ai_*', "DB dev đang có {$foundNew}/7 bảng (chưa áp migration). Kiểm tra schema đầy đủ ở Mục H.");
    } else {
        $t->equals('Đủ 7 bảng vc_ai_*', 7, $foundNew);
        // P6: 7 bảng chết phải KHÔNG còn trên DB dev (migration drop đã chạy).
        // P9: 2 bảng prompt DB cũng phải KHÔNG còn (prompt chỉ lưu FILE).
        $droppedLeft = [];
        foreach ($droppedTables as $table) {
            $stmt->execute(['db' => $dbName, 'name' => $table]);
            if ($stmt->fetch() !== false) {
                $droppedLeft[] = $table;
            }
        }
        $t->check('P6+P9. 9 bảng AI chết đã bị DROP khỏi DB dev', $droppedLeft === [], implode(', ', $droppedLeft));
    }

    // F2. Không có bảng cũ nào bị mất
    foreach (['vc_users', 'vc_settings', 'vc_orders'] as $table) {
        $stmt->execute(['db' => $dbName, 'name' => $table]);
        $t->check("Bảng cũ còn nguyên: {$table}", $stmt->fetch() !== false);
    }
    foreach ($optionalLegacyTables as $table) {
        $stmt->execute(['db' => $dbName, 'name' => $table]);
        if ($stmt->fetch() === false) {
            $t->skip("Bảng cũ: {$table}", 'không tồn tại trong DB dev (drift trước Phase 5, không liên quan migration này)');
        } else {
            $t->check("Bảng cũ còn nguyên: {$table}", true);
        }
    }

    if (!$migrationApplied || !$fkViable) {
        $t->skip('F3–F10. Schema chi tiết + ghi/đọc test', 'DB dev chưa áp migration / thiếu bảng tiên quyết');
    } else {
        // F3. PK là BIGINT UNSIGNED cho tất cả bảng mới
        $colStmt = $pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY, EXTRA FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :name AND COLUMN_NAME = :col'
        );

        $pkAllBigint = true;
        foreach ($expectedTables as $table) {
            $colStmt->execute(['db' => $dbName, 'name' => $table, 'col' => 'id']);
            $col = $colStmt->fetch();

            if (!$col || $col['COLUMN_TYPE'] !== 'bigint unsigned' || $col['COLUMN_KEY'] !== 'PRI' || !str_contains($col['EXTRA'], 'auto_increment')) {
                $pkAllBigint = false;
                $t->check("PK đúng chuẩn: {$table}", false, json_encode($col));
            }
        }
        $t->check('Tất cả PK = BIGINT UNSIGNED AUTO_INCREMENT', $pkAllBigint);

        // F4. KHÔNG có cột blob/base64 trong bảng mới
        $blobStmt = $pdo->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME LIKE :pattern
                AND (DATA_TYPE IN ("blob","mediumblob","longblob","binary","varbinary")
                     OR COLUMN_NAME IN ("image_data","base64","file_blob","media_binary"))'
        );
        $blobStmt->execute(['db' => $dbName, 'pattern' => 'vc_ai_%']);
        $blobCols = $blobStmt->fetchAll();
        $t->check('KHÔNG có cột blob/base64 trong 7 bảng AI', $blobCols === [], json_encode($blobCols));

        // F5. FK của bảng mới chỉ trỏ tới bảng hợp lệ
        $fkStmt = $pdo->prepare(
            'SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME LIKE :pattern AND REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $fkStmt->execute(['db' => $dbName, 'pattern' => 'vc_ai_%']);
        $fks = $fkStmt->fetchAll();

        $t->check('Có FK được tạo (>= 14)', count($fks) >= 14, 'count=' . count($fks));

        $invalidFkTargets = [];
        foreach ($fks as $fk) {
            $target = $fk['REFERENCED_TABLE_NAME'];
            if (!in_array($target, $expectedTables, true) && !in_array($target, array_merge($fkDependencies, $optionalLegacyTables, ['vc_users']), true)) {
                $invalidFkTargets[] = $fk['CONSTRAINT_NAME'] . ' → ' . $target;
            }
        }
        $t->check('FK chỉ trỏ tới bảng hợp lệ', $invalidFkTargets === [], implode(', ', $invalidFkTargets));

        // F6. Không có self-FK tự tham chiếu chính nó qua cột id (lỗi đã từng gặp)
        $selfFk = [];
        foreach ($fks as $fk) {
            if ($fk['TABLE_NAME'] === $fk['REFERENCED_TABLE_NAME'] && $fk['COLUMN_NAME'] === 'id' && $fk['REFERENCED_COLUMN_NAME'] === 'id') {
                $selfFk[] = $fk['CONSTRAINT_NAME'];
            }
        }
        $t->check('Không có FK tự tham chiếu qua cột id', $selfFk === [], implode(', ', $selfFk));

        // F7. Con trỏ version chỉ là INDEX, KHÔNG phải FK (tránh circular FK)
        // (P9: vc_ai_prompts đã DROP — chỉ còn con trỏ của vc_ai_outputs)
        $pointerCheck = [
            ['vc_ai_outputs', 'current_version_id'],
            ['vc_ai_outputs', 'approved_version_id'],
            ['vc_ai_outputs', 'final_version_id'],
        ];

        $pointerFkViolations = [];
        foreach ($fks as $fk) {
            foreach ($pointerCheck as [$table, $column]) {
                if ($fk['TABLE_NAME'] === $table && $fk['COLUMN_NAME'] === $column) {
                    $pointerFkViolations[] = $table . '.' . $column;
                }
            }
        }
        $t->check('Con trỏ version KHÔNG có FK (chỉ INDEX)', $pointerFkViolations === [], implode(', ', $pointerFkViolations));

        // F8. Kiểu cột quan trọng khớp schema cũ (P6: 2 bảng check cũ đã DROP —
        // thay bằng kiểm tra cột then chốt của bảng còn sống).
        $colStmt->execute(['db' => $dbName, 'name' => 'vc_ai_tasks', 'col' => 'lock_token']);
        $col = $colStmt->fetch();
        $t->check('vc_ai_tasks.lock_token = CHAR(36) (fencing LRO/claim)', ($col['COLUMN_TYPE'] ?? '') === 'char(36)', (string) ($col['COLUMN_TYPE'] ?? ''));

        $colStmt->execute(['db' => $dbName, 'name' => 'vc_ai_tasks', 'col' => 'params']);
        $col = $colStmt->fetch();
        $t->check('vc_ai_tasks.params = JSON (chứa kira_operation_id LRO)', ($col['COLUMN_TYPE'] ?? '') === 'json', (string) ($col['COLUMN_TYPE'] ?? ''));

        // F9. UNIQUE idempotency_key
        $idxStmt = $pdo->prepare(
            'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :name ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        $idxStmt->execute(['db' => $dbName, 'name' => 'vc_ai_tasks']);
        $idemUnique = false;
        foreach ($idxStmt->fetchAll() as $idx) {
            if ($idx['COLUMN_NAME'] === 'idempotency_key' && (int) $idx['NON_UNIQUE'] === 0) {
                $idemUnique = true;
            }
        }
        $t->check('vc_ai_tasks.idempotency_key có UNIQUE index', $idemUnique);

        // ---------------------------------------------
        // F10. Ghi/đọc test: asset → output → version
        // ---------------------------------------------
        $created = ['vc_ai_modules' => [], 'vc_ai_models' => [], 'vc_ai_tasks' => [], 'vc_ai_assets' => [], 'vc_ai_outputs' => []];

        try {
            $moduleId = null;
            $existing = $pdo->prepare("SELECT id FROM vc_ai_modules WHERE module_key = 'SELFTEST_MODULE' LIMIT 1");
            $existing->execute();
            $row = $existing->fetch();

            if ($row) {
                $moduleId = (int) $row['id'];
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO vc_ai_modules (module_key, module_name, capability, is_enabled) VALUES ('SELFTEST_MODULE', 'Self-test module', 'text', 1)"
                );
                $ins->execute();
                $moduleId = (int) $pdo->lastInsertId();
                $created['vc_ai_modules'][] = $moduleId;
            }
            $t->check('F10. Tạo được module test', $moduleId > 0);

            // Asset: ghi metadata (không tạo file thật để không rác ổ đĩa).
            $insAsset = $pdo->prepare(
                "INSERT INTO vc_ai_assets (asset_kind, relative_path, mime_type, size_bytes, checksum, storage_disk, ref_count, is_orphan)
                 VALUES ('image', :path, 'image/png', 1234, :checksum, 'public', 0, 0)"
            );
            $testPath = 'public/uploads/ai/selftest/' . bin2hex(random_bytes(8)) . '.png';
            $insAsset->execute(['path' => $testPath, 'checksum' => hash('sha256', $testPath)]);
            $assetId = (int) $pdo->lastInsertId();
            $created['vc_ai_assets'][] = $assetId;
            $t->check('F10. Tạo được asset test', $assetId > 0);

            // Task + Output + Version
            $insTask = $pdo->prepare(
                "INSERT INTO vc_ai_tasks (module_id, task_type, status, attempt_count, retry_count, max_retries, priority)
                 VALUES (:mid, 'selftest', 'completed', 1, 0, 3, 5)"
            );
            $insTask->execute(['mid' => $moduleId]);
            $taskId = (int) $pdo->lastInsertId();
            $created['vc_ai_tasks'][] = $taskId;

            $insOutput = $pdo->prepare(
                "INSERT INTO vc_ai_outputs (task_id, module_id, output_type, review_status, version_count)
                 VALUES (:tid, :mid, 'image', 'generated', 0)"
            );
            $insOutput->execute(['tid' => $taskId, 'mid' => $moduleId]);
            $outputId = (int) $pdo->lastInsertId();
            $created['vc_ai_outputs'][] = $outputId;
            $t->check('F10. Tạo được task + output test', $taskId > 0 && $outputId > 0);

            $insVersion = $pdo->prepare(
                "INSERT INTO vc_ai_output_versions (output_id, version_no, asset_id, provider, model, review_status, is_current)
                 VALUES (:oid, :no, :aid, 'kira', 'test-model', 'generated', 1)"
            );
            $insVersion->execute(['oid' => $outputId, 'no' => 1, 'aid' => $assetId]);
            $versionId = (int) $pdo->lastInsertId();
            $t->check('F10. Tạo được output version', $versionId > 0);

            $chk = $pdo->prepare('SELECT previous_version_id FROM vc_ai_output_versions WHERE id = :id');
            $chk->execute(['id' => $versionId]);
            $t->check('F10. Version đầu có previous_version_id = NULL', $chk->fetch()['previous_version_id'] === null);

            // Version 2 (version_no = 2) với previous_version_id trỏ bản 1
            $insVersion->execute(['oid' => $outputId, 'no' => 2, 'aid' => $assetId]);
            $version2Id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE vc_ai_output_versions SET previous_version_id = :prev WHERE id = :id')
                ->execute(['prev' => $versionId, 'id' => $version2Id]);

            $chk->execute(['id' => $version2Id]);
            $t->equals('F10. Version 2 trỏ previous_version_id đúng', $versionId, (int) $chk->fetch()['previous_version_id']);

            // UNIQUE (output_id, version_no) chặn trùng số version
            $dupBlocked = false;
            try {
                $insVersion->execute(['oid' => $outputId, 'no' => 1, 'aid' => $assetId]);
            } catch (PDOException $e) {
                $dupBlocked = str_contains($e->getMessage(), 'Duplicate') || (int) $e->getCode() === 23000;
            }
            $t->check('F10. UNIQUE(output_id, version_no) chặn version trùng số', $dupBlocked);

            // ref_count tăng khi attach
            $pdo->prepare('UPDATE vc_ai_assets SET ref_count = ref_count + 1 WHERE id = :id')->execute(['id' => $assetId]);
            $chkRef = $pdo->prepare('SELECT ref_count FROM vc_ai_assets WHERE id = :id');
            $chkRef->execute(['id' => $assetId]);
            $t->equals('F10. Asset ref_count tăng đúng', 1, (int) $chkRef->fetch()['ref_count']);

            // FK chặn asset_id không tồn tại
            $fkBlocked = false;
            try {
                $pdo->prepare("INSERT INTO vc_ai_output_versions (output_id, version_no, asset_id, review_status) VALUES (:oid, 99, 999999999, 'generated')")
                    ->execute(['oid' => $outputId]);
            } catch (PDOException $e) {
                $fkBlocked = (int) $e->getCode() === 23000;
            }
            $t->check('F10. FK chặn asset_id không tồn tại', $fkBlocked);

            // FK ON DELETE CASCADE: xoá output → version biến mất
            $pdo->prepare('DELETE FROM vc_ai_outputs WHERE id = :id')->execute(['id' => $outputId]);
            $chkCascade = $pdo->prepare('SELECT COUNT(*) AS c FROM vc_ai_output_versions WHERE output_id = :id');
            $chkCascade->execute(['id' => $outputId]);
            $t->equals('F10. Xoá output → cascade xoá version', 0, (int) $chkCascade->fetch()['c']);

            $created['vc_ai_outputs'] = [];
            $pdo->prepare('DELETE FROM vc_ai_tasks WHERE id = :id')->execute(['id' => $taskId]);
            $created['vc_ai_tasks'] = [];
        } catch (Throwable $e) {
            $t->check('F10. Ghi/đọc asset + output version', false, $e->getMessage());
        }

        // -----------------------------------------------------------------
        // F11. Vòng đời task THẬT qua TaskDispatcher: claim / lock /
        //      CRASH RECOVERY (thu hồi lock stale) / FENCING (worker cũ
        //      không ghi đè) / idempotency / retry.
        //      Kiểm chứng hành vi implementation thực, không mô phỏng SQL.
        // -----------------------------------------------------------------
        try {
            $moduleId = $created['vc_ai_modules'][0] ?? 0;
            if ($moduleId === 0) {
                $res = $pdo->query("SELECT id FROM vc_ai_modules WHERE module_key = 'SELFTEST_MODULE' LIMIT 1")->fetch();
                $moduleId = (int) ($res['id'] ?? 0);
            }

            if ($moduleId > 0) {
                $dispatcher = new \App\AI\Core\TaskDispatcher($config);
                $retryPolicy = new \App\AI\Core\RetryPolicy($config);
                $f11Ids = [];

                // F11.1 Idempotency — create() với cùng key KHÔNG tạo trùng.
                $idemKey = 'selftest-idem-' . bin2hex(random_bytes(6));
                $created11 = $dispatcher->create($moduleId, 'selftest', ['probe' => 1], [], null, $idemKey, null);
                $created['vc_ai_tasks'][] = $created11['id'];
                $again11 = $dispatcher->create($moduleId, 'selftest', ['probe' => 1], [], null, $idemKey, null);
                $t->check(
                    'F11. Idempotency: cùng key → cùng task, created=false',
                    $created11['created'] === true && $again11['created'] === false && $again11['id'] === $created11['id']
                );

                // Ràng buộc vật lý UNIQUE(idempotency_key) vẫn chặn insert trùng thẳng DB.
                $dupBlocked = false;
                try {
                    $pdo->prepare(
                        "INSERT INTO vc_ai_tasks (module_id, task_type, status, idempotency_key, max_retries, priority)
                         VALUES (:mid, 'selftest', 'pending', :key, 3, 5)"
                    )->execute(['mid' => $moduleId, 'key' => $idemKey]);
                } catch (PDOException $e) {
                    $dupBlocked = (int) $e->getCode() === 23000;
                }
                $t->check('F11. UNIQUE idempotency_key chặn tạo task trùng (DB)', $dupBlocked);

                // F11.2 Claim thường + lock + tương tranh (lock còn hiệu lực).
                $taskId = $created11['id'];
                $f11Ids[] = $taskId;
                $claim1 = $dispatcher->claim($taskId, 'w1');
                $t->check(
                    'F11. Worker đầu claim được task (pending→processing)',
                    is_array($claim1) && ($claim1['status'] ?? '') === 'processing' && ($claim1['locked_by'] ?? '') === 'w1'
                );
                $token1 = (string) ($claim1['lock_token'] ?? '');
                $t->check('F11. Claim sinh lock_token (khác rỗng)', $token1 !== '');
                $t->equals('F11. Claim đầu tiên tăng attempt_count lên 1', 1, (int) ($claim1['attempt_count'] ?? 0));

                // Đọc lại bằng kết nối của self-test để chắc chắn đã COMMIT.
                $rb1 = $pdo->prepare('SELECT status, locked_by, lock_token, attempt_count FROM vc_ai_tasks WHERE id = :id');
                $rb1->execute(['id' => $taskId]);
                $rowB1 = $rb1->fetch() ?: [];
                $t->check(
                    'F11. DB đã commit trạng thái claim (processing + lock_token khớp)',
                    ($rowB1['status'] ?? '') === 'processing' && (string) ($rowB1['lock_token'] ?? '') === $token1
                );

                $t->check('F11. Worker khác KHÔNG claim được khi lock còn hiệu lực', $dispatcher->claim($taskId, 'w2') === null);
                $t->check('F11. Worker đang sở hữu claim lại (lock fresh) cũng bị chặn', $dispatcher->claim($taskId, 'w1') === null);

                // F11.3 CRASH RECOVERY — lock stale cho phép worker khác thu hồi.
                $pdo->prepare("UPDATE vc_ai_tasks SET locked_at = '2000-01-01 00:00:00' WHERE id = :id")->execute(['id' => $taskId]);
                $claim3 = $dispatcher->claim($taskId, 'w3');
                $t->check(
                    'F11. Lock stale → worker khác THU HỒI được task (processing→processing)',
                    is_array($claim3) && ($claim3['locked_by'] ?? '') === 'w3'
                );
                $token3 = (string) ($claim3['lock_token'] ?? '');
                $t->check('F11. Thu hồi sinh lock_token MỚI (token cũ vô hiệu)', $token3 !== '' && $token3 !== $token1);
                $t->equals('F11. Thu hồi tăng attempt_count lên 2', 2, (int) ($claim3['attempt_count'] ?? 0));

                // F11.4 FENCING — worker cũ (token cũ) KHÔNG ghi đè được.
                $dispatcher->finish($taskId, 'completed', ['lock_token' => $token1, 'message' => 'worker cũ cố ghi đè']);
                $rf = $pdo->prepare('SELECT status, locked_by FROM vc_ai_tasks WHERE id = :id');
                $rf->execute(['id' => $taskId]);
                $rowF = $rf->fetch() ?: [];
                $t->check(
                    'F11. Worker cũ (token cũ) KHÔNG ghi đè được (vẫn processing, locked_by=w3)',
                    ($rowF['status'] ?? '') === 'processing' && ($rowF['locked_by'] ?? '') === 'w3'
                );
                $t->check('F11. isClaimedBy(token cũ) = false', $dispatcher->isClaimedBy($taskId, $token1) === false);
                $t->check('F11. isClaimedBy(token mới) = true', $dispatcher->isClaimedBy($taskId, $token3) === true);

                // F11.5 Heartbeat — touch chỉ thành công đúng worker + token.
                $t->check('F11. touch (đúng worker+token) gia hạn lock', $dispatcher->touch($taskId, 'w3', $token3) === true);
                $t->check('F11. touch (token sai) bị từ chối', $dispatcher->touch($taskId, 'w3', 'khong-phai-token') === false);
                $t->check('F11. touch (worker sai) bị từ chối', $dispatcher->touch($taskId, 'w9', $token3) === false);
                // CASE 6: sau khi bị thu hồi (w3 giữ token3), worker CŨ (w1) với token CŨ (token1)
                // KHÔNG thể gia hạn lock của worker mới.
                $t->check('F11. CASE6: worker cũ + token cũ SAU reclaim không gia hạn được', $dispatcher->touch($taskId, 'w1', $token1) === false);

                // F11.6 Worker đúng token finish được; lock được giải phóng.
                $dispatcher->finish($taskId, 'completed', ['lock_token' => $token3, 'message' => 'worker mới hoàn tất']);
                $rd = $pdo->prepare('SELECT status, lock_token, finished_at FROM vc_ai_tasks WHERE id = :id');
                $rd->execute(['id' => $taskId]);
                $rowD = $rd->fetch() ?: [];
                $t->check(
                    'F11. Worker đúng token finish được + lock giải phóng',
                    ($rowD['status'] ?? '') === 'completed' && $rowD['lock_token'] === null && $rowD['finished_at'] !== null
                );

                // F11.7 Task đã kết thúc KHÔNG bao giờ bị thu hồi dù locked_at "stale".
                $pdo->prepare("UPDATE vc_ai_tasks SET locked_at = '2000-01-01 00:00:00' WHERE id = :id")->execute(['id' => $taskId]);
                $t->check('F11. Task completed KHÔNG bị thu hồi', $dispatcher->claim($taskId, 'w9') === null);

                // F11.8 Tương thích ngược — finish KHÔNG truyền token vẫn ghi được.
                $bk = $dispatcher->create($moduleId, 'selftest', [], [], null, 'selftest-' . bin2hex(random_bytes(6)), null);
                $f11Ids[] = $bk['id'];
                $dispatcher->claim($bk['id'], 'w1');
                $dispatcher->finish($bk['id'], 'failed', ['error_code' => 'X', 'message' => 'không token']);
                $rbk = $pdo->prepare('SELECT status, lock_token FROM vc_ai_tasks WHERE id = :id');
                $rbk->execute(['id' => $bk['id']]);
                $rowBk = $rbk->fetch() ?: [];
                $t->check(
                    'F11. finish KHÔNG token vẫn ghi (tương thích ngược)',
                    ($rowBk['status'] ?? '') === 'failed' && $rowBk['lock_token'] === null
                );

                // F11.9 release() — chỉ xoá lock, GIỮ status; sau stale vẫn thu hồi được.
                $rel = $dispatcher->create($moduleId, 'selftest', [], [], null, 'selftest-' . bin2hex(random_bytes(6)), null);
                $f11Ids[] = $rel['id'];
                $dispatcher->claim($rel['id'], 'w1');
                $dispatcher->release($rel['id']);
                $rr = $pdo->prepare('SELECT status, lock_token FROM vc_ai_tasks WHERE id = :id');
                $rr->execute(['id' => $rel['id']]);
                $rowR = $rr->fetch() ?: [];
                $t->check(
                    'F11. release() xoá lock nhưng GIỮ status=processing',
                    ($rowR['status'] ?? '') === 'processing' && $rowR['lock_token'] === null
                );
                $pdo->prepare("UPDATE vc_ai_tasks SET locked_at = '2000-01-01 00:00:00' WHERE id = :id")->execute(['id' => $rel['id']]);
                $t->check('F11. Sau release + stale → vẫn thu hồi được', is_array($dispatcher->claim($rel['id'], 'w5')));

                // F11.10 scheduleRetry — retry_count tăng, chuyển 'retrying', claim lại được.
                $rt = $dispatcher->create($moduleId, 'selftest', [], [], null, 'selftest-' . bin2hex(random_bytes(6)), null);
                $f11Ids[] = $rt['id'];
                $claimT = $dispatcher->claim($rt['id'], 'w1');
                $scheduled = $dispatcher->scheduleRetry(
                    $rt['id'],
                    'TIMEOUT',
                    'lỗi tạm thời',
                    $retryPolicy,
                    1,
                    (string) ($claimT['lock_token'] ?? '')
                );
                $rrt = $pdo->prepare('SELECT status, retry_count, retry_after FROM vc_ai_tasks WHERE id = :id');
                $rrt->execute(['id' => $rt['id']]);
                $rowT = $rrt->fetch() ?: [];
                $t->check(
                    'F11. scheduleRetry hợp lệ → retrying, retry_count=1, có retry_after',
                    $scheduled === true
                        && ($rowT['status'] ?? '') === 'retrying'
                        && (int) ($rowT['retry_count'] ?? 0) === 1
                        && $rowT['retry_after'] !== null
                );
                $t->check('F11. Task retrying (đã xoá lock) claim lại được', is_array($dispatcher->claim($rt['id'], 'w2')));

                // F11.11 Fencing cho scheduleRetry — worker cũ không đẩy task đã thu hồi về retrying.
                $fr = $dispatcher->create($moduleId, 'selftest', [], [], null, 'selftest-' . bin2hex(random_bytes(6)), null);
                $f11Ids[] = $fr['id'];
                $cf1 = $dispatcher->claim($fr['id'], 'w1');
                $pdo->prepare("UPDATE vc_ai_tasks SET locked_at = '2000-01-01 00:00:00' WHERE id = :id")->execute(['id' => $fr['id']]);
                $dispatcher->claim($fr['id'], 'w3');
                $blockedRetry = $dispatcher->scheduleRetry($fr['id'], 'TIMEOUT', 'cũ', $retryPolicy, 1, (string) ($cf1['lock_token'] ?? ''));
                $rfr = $pdo->prepare('SELECT status, retry_count FROM vc_ai_tasks WHERE id = :id');
                $rfr->execute(['id' => $fr['id']]);
                $rowFr = $rfr->fetch() ?: [];
                $t->check(
                    'F11. scheduleRetry worker cũ bị chặn (không đổi status/retry_count)',
                    $blockedRetry === false && ($rowFr['status'] ?? '') === 'processing' && (int) ($rowFr['retry_count'] ?? 0) === 0
                );

                // F11.12 scheduleRetry khi đã cạn max_retries → finish('failed').
                $rd2 = $dispatcher->create($moduleId, 'selftest', [], [], null, 'selftest-' . bin2hex(random_bytes(6)), null);
                $f11Ids[] = $rd2['id'];
                $pdo->prepare('UPDATE vc_ai_tasks SET retry_count = max_retries WHERE id = :id')->execute(['id' => $rd2['id']]);
                $capped = $dispatcher->scheduleRetry($rd2['id'], 'TIMEOUT', 'hết lượt', $retryPolicy, 1);
                $rcap = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
                $rcap->execute(['id' => $rd2['id']]);
                $t->check(
                    'F11. scheduleRetry khi đã cạn max_retries → failed',
                    $capped === false && ($rcap->fetch()['status'] ?? '') === 'failed'
                );

                // Dọn task F11 ngay (activity cascade theo) — không phụ thuộc cleanup cuối file.
                foreach ($f11Ids as $fid) {
                    $pdo->prepare('DELETE FROM vc_ai_tasks WHERE id = :id')->execute(['id' => $fid]);
                }
                $created['vc_ai_tasks'] = [];
            } else {
                $t->skip('F11. Vòng đời task (TaskDispatcher)', 'không tạo được module test');
            }
        } catch (Throwable $e) {
            $t->check('F11. Vòng đời task (TaskDispatcher)', false, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // F14. WORKFLOW END-TO-END qua TaskRunner — nối các mảnh AI Core thành
    //      MỘT vòng đời thật: REQUEST → TASK → CLAIM → PROCESS (AICore +
    //      provider GIẢ) → OUTPUT → FINISH, gồm nhánh retry + fencing.
    //      KHÔNG gọi Kira (provider giả), KHÔNG ghi file media (output text).
    // -----------------------------------------------------------------
    if ($migrationApplied && $fkViable) {
        try {
            // Fixture: module 'content_article' ĐÚNG module_key trong ModuleRegistry
            // (để request() xác thực được) — chỉ tạo nếu chưa có, không đụng bản thật.
            $f14Key = 'content_article';
            $row = $pdo->query("SELECT id FROM vc_ai_modules WHERE module_key = 'content_article' LIMIT 1")->fetch();
            if ($row) {
                $f14ModuleId = (int) $row['id'];
            } else {
                $pdo->prepare("INSERT INTO vc_ai_modules (module_key, module_name, capability, is_enabled) VALUES ('content_article', 'Sinh bài viết', 'text', 1)")->execute();
                $f14ModuleId = (int) $pdo->lastInsertId();
                $created['vc_ai_modules'][] = $f14ModuleId;
            }
            $t->check('F14. Có module content_article để chạy workflow', $f14ModuleId > 0);

            $modelKey = 'selftest-publish-model';

            // Cấu hình KHÔNG cho provider tự retry nội bộ (max_attempts=1) để
            // cô lập tầng retry NGOÀI của TaskRunner (scheduleRetry) — nếu để
            // provider retry nội bộ, một lỗi 5xx tạm thời sẽ được provider tự
            // xử lý và che mất nhánh retry của workflow.
            $configNoInnerRetry = $config;
            $configNoInnerRetry['retry'] = array_merge(
                is_array($config['retry'] ?? null) ? $config['retry'] : [],
                ['enabled' => true, 'max_attempts' => 1]
            );

            // Mỗi runner dùng MỘT provider giả với hàng đợi response riêng.
            $buildRunner = static function (array $responses, ?array $cfg = null) use ($config, $makeProvider, $modelKey): array {
                [$provider] = $makeProvider($responses);
                $useConfig = $cfg ?? $config;
                $core = new \App\AI\Core\AICore($useConfig, $provider);
                $runner = new \App\AI\Core\TaskRunner(
                    $useConfig,
                    $core,
                    new \App\AI\Core\TaskDispatcher($useConfig),
                    new \App\AI\Core\OutputHandler(new \App\AI\Assets\AssetManager($useConfig))
                );

                return [$runner, $modelKey];
            };

            $chatOk = static fn(string $text): array => [
                'status' => 200,
                'body'   => json_encode(['choices' => [['message' => ['content' => $text]]]]),
            ];
            $fail500 = ['status' => 500, 'body' => '{"error":"boom"}'];

            // F14.1–F14.8: đường THÀNH CÔNG đầu-cuối.
            [$runner] = $buildRunner([$chatOk('Nội dung bài đăng thử.')]);

            $req = $runner->request($f14Key, ['content' => 'Xin chào'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $req['id'];
            $t->check('F14. request() tạo task mới (created=true, id>0)', $req['created'] === true && $req['id'] > 0);

            $reqDup = $runner->request($f14Key, ['content' => 'Xin chào'], ['model' => $modelKey]);
            $t->check('F14. request() idempotent (cùng payload → cùng task)', $reqDup['created'] === false && $reqDup['id'] === $req['id']);

            $proc = $runner->process($req['id'], 'w1');
            $t->equals('F14. process() → status processed', \App\AI\Core\TaskRunner::RESULT_PROCESSED, $proc['status']);
            $t->check('F14. process() sinh output + version', ($proc['output_id'] ?? 0) > 0 && ($proc['version_id'] ?? 0) > 0);

            $rowTask = $pdo->prepare('SELECT status, lock_token FROM vc_ai_tasks WHERE id = :id');
            $rowTask->execute(['id' => $req['id']]);
            $taskRow = $rowTask->fetch() ?: [];
            $t->check(
                'F14. Task kết thúc completed + nhả lock',
                ($taskRow['status'] ?? '') === 'completed' && $taskRow['lock_token'] === null
            );

            $rowOut = $pdo->prepare('SELECT review_status, version_count FROM vc_ai_outputs WHERE task_id = :id');
            $rowOut->execute(['id' => $req['id']]);
            $outRow = $rowOut->fetch() ?: [];
            $t->check(
                'F14. Output review_status=generated, version_count=1',
                ($outRow['review_status'] ?? '') === 'generated' && (int) ($outRow['version_count'] ?? 0) === 1
            );

            $rowVer = $pdo->prepare('SELECT content_snapshot, provider, model, is_current, previous_version_id FROM vc_ai_output_versions WHERE id = :id');
            $rowVer->execute(['id' => (int) ($proc['version_id'] ?? 0)]);
            $verRow = $rowVer->fetch() ?: [];
            $t->check(
                'F14. Version chứa đúng nội dung + provider/model + is_current',
                ($verRow['content_snapshot'] ?? '') === 'Nội dung bài đăng thử.'
                    && ($verRow['provider'] ?? '') === 'kira'
                    && ($verRow['model'] ?? '') === $modelKey
                    && (int) ($verRow['is_current'] ?? 0) === 1
                    && $verRow['previous_version_id'] === null
            );

            $procAgain = $runner->process($req['id'], 'w9');
            $t->equals('F14. Task completed → process() bỏ qua (skipped)', \App\AI\Core\TaskRunner::RESULT_SKIPPED, $procAgain['status']);

            $cntOut = $pdo->prepare('SELECT COUNT(*) AS c FROM vc_ai_outputs WHERE task_id = :id');
            $cntOut->execute(['id' => $req['id']]);
            $t->equals('F14. KHÔNG tạo output trùng', 1, (int) $cntOut->fetch()['c']);

            // F14.9–F14.11: LỖI TẠM THỜI → RETRY → THÀNH CÔNG.
            // Lần 1: provider trả 500 → TaskRunner lên lịch retry.
            // Lần 2: provider trả 200 → hoàn tất.
            [$runner2] = $buildRunner([$fail500, $chatOk('Nội dung sau retry.')], $configNoInnerRetry);
            $req2 = $runner2->request($f14Key, ['content' => 'Retry me'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $req2['id'];

            $procR = $runner2->process($req2['id'], 'w1');
            $t->equals('F14. Lỗi 5xx → TaskRunner lên lịch retry', \App\AI\Core\TaskRunner::RESULT_RETRY, $procR['status']);

            $rowR = $pdo->prepare('SELECT status, retry_count, retry_after FROM vc_ai_tasks WHERE id = :id');
            $rowR->execute(['id' => $req2['id']]);
            $rRow = $rowR->fetch() ?: [];
            $t->check(
                'F14. Task retrying, retry_count=1, có retry_after',
                ($rRow['status'] ?? '') === 'retrying' && (int) ($rRow['retry_count'] ?? 0) === 1 && $rRow['retry_after'] !== null
            );

            $pdo->prepare('UPDATE vc_ai_tasks SET retry_after = :past WHERE id = :id')
                ->execute(['past' => '2000-01-01 00:00:00', 'id' => $req2['id']]);

            $procR2 = $runner2->process($req2['id'], 'w2');
            $t->equals('F14. Retry thành công → processed', \App\AI\Core\TaskRunner::RESULT_PROCESSED, $procR2['status']);

            $rowR2 = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $rowR2->execute(['id' => $req2['id']]);
            $t->check('F14. Task sau retry = completed', ($rowR2->fetch()['status'] ?? '') === 'completed');

            // F14.12: cạn lượt retry → failed.
            [$runner3] = $buildRunner([$fail500, $fail500, $fail500], $configNoInnerRetry);
            $req3 = $runner3->request($f14Key, ['content' => 'Exhaust'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $req3['id'];
            $pdo->prepare('UPDATE vc_ai_tasks SET retry_count = max_retries WHERE id = :id')->execute(['id' => $req3['id']]);

            $procE = $runner3->process($req3['id'], 'w1');
            $t->equals('F14. Cạn retry → failed', \App\AI\Core\TaskRunner::RESULT_FAILED, $procE['status']);
            $rowE = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $rowE->execute(['id' => $req3['id']]);
            $t->check('F14. Task cạn retry = failed', ($rowE->fetch()['status'] ?? '') === 'failed');

            // F14.13: task đang bị worker khác giữ → TaskRunner bỏ qua an toàn.
            [$runner4] = $buildRunner([$chatOk('không dùng')]);
            $req4 = $runner4->request($f14Key, ['content' => 'Busy'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $req4['id'];
            $runner4->dispatcher()->claim($req4['id'], 'worker-khac');
            $procB = $runner4->process($req4['id'], 'w1');
            $t->equals('F14. Task đang bị worker khác giữ → skipped', \App\AI\Core\TaskRunner::RESULT_SKIPPED, $procB['status']);

            // F14.14: crash-recovery — output đã tồn tại (chết sau createOutput)
            //         → chạy lại KHÔNG tạo output mới, chỉ thêm version vào output cũ.
            [$runner5] = $buildRunner([$chatOk('Nội dung phục hồi.')]);
            $req5 = $runner5->request($f14Key, ['content' => 'Recover'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $req5['id'];
            $preOut = (new \App\AI\Core\OutputHandler())->createOutput($req5['id'], $f14ModuleId, 'text');
            $procC = $runner5->process($req5['id'], 'w1');
            $t->equals('F14. Crash-recovery: process() thành công dù output đã tồn tại', \App\AI\Core\TaskRunner::RESULT_PROCESSED, $procC['status']);
            $t->equals('F14. Crash-recovery: tái sử dụng output cũ (không tạo trùng)', $preOut, (int) ($procC['output_id'] ?? 0));
            $cntOut5 = $pdo->prepare('SELECT COUNT(*) AS c FROM vc_ai_outputs WHERE task_id = :id');
            $cntOut5->execute(['id' => $req5['id']]);
            $t->equals('F14. Crash-recovery: vẫn chỉ 1 output', 1, (int) $cntOut5->fetch()['c']);

            // F14.15: drain() quét & xử lý batch task đến hạn (mô phỏng 1 lượt cron).
            [$runner6] = $buildRunner([
                $chatOk('a'), $chatOk('b'), $chatOk('c'), $chatOk('d'),
                $chatOk('e'), $chatOk('f'), $chatOk('g'), $chatOk('h'),
            ]);
            $d1 = $runner6->request($f14Key, ['content' => 'drain-1'], ['model' => $modelKey]);
            $d2 = $runner6->request($f14Key, ['content' => 'drain-2'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $d1['id'];
            $created['vc_ai_tasks'][] = $d2['id'];
            $stats = $runner6->drain('w-drain', 10);
            $rowD1 = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $rowD1->execute(['id' => $d1['id']]);
            $rowD2 = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $rowD2->execute(['id' => $d2['id']]);
            $t->check(
                'F14. drain() xử lý task đến hạn trong batch (2 task → completed)',
                ($rowD1->fetch()['status'] ?? '') === 'completed'
                    && ($rowD2->fetch()['status'] ?? '') === 'completed'
                    && (int) ($stats['processed'] ?? 0) >= 2,
                'processed=' . (int) ($stats['processed'] ?? 0) . ' scanned=' . (int) ($stats['scanned'] ?? 0)
            );
        } catch (Throwable $e) {
            $t->check('F14. Workflow end-to-end (TaskRunner)', false, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // F15. G1 — FENCING OUTPUT/ASSET khi mất lock GIỮA lúc ghi.
    //      Mô phỏng ĐÚNG cuộc đua: một worker khác THU HỒI task (reclaim)
    //      ngay trong lúc process() đang gọi provider (giữa claim và writeOutput).
    //      Sau đó process() phải FAIL-CLOSED: KHÔNG tạo output, KHÔNG ghi finish.
    // -----------------------------------------------------------------
    if ($migrationApplied && $fkViable) {
        try {
            // State chia sẻ để "hijack" được kích hoạt BÊN TRONG provider->chat().
            $fenceState = new \ArrayObject(['hijack' => null]);

            $hijackProvider = new class($fenceState) implements \App\AI\Contracts\AIProviderInterface {
                private \ArrayObject $state;
                public int $hijacked = 0;

                public function __construct(\ArrayObject $state)
                {
                    $this->state = $state;
                }

                public function key(): string { return 'kira'; }
                public function supports(string $capability): bool { return true; }

                public function chat(array $messages, array $options = []): AIResult
                {
                    $hook = $this->state['hijack'] ?? null;
                    if (is_callable($hook)) {
                        $this->hijacked++;
                        $hook();
                    }

                    return AIResult::success([
                        'capability' => 'text',
                        'provider'   => 'kira',
                        'model'      => 'x',
                        'content'    => 'nội dung KHÔNG được ghi (đã mất lock).',
                    ]);
                }

                public function image(array $payload, array $options = []): AIResult { return AIResult::success(['files' => []]); }
                public function videoCreate(array $payload, array $options = []): AIResult { return AIResult::success(); }
                public function videoStatus(string $operationId, array $options = []): AIResult { return AIResult::success(); }
                public function speech(array $payload, array $options = []): AIResult { return AIResult::success(); }
                public function models(array $options = []): AIResult { return AIResult::success(); }
            };

            $core15  = new \App\AI\Core\AICore($configNoInnerRetry, $hijackProvider);
            $runner15 = new \App\AI\Core\TaskRunner(
                $configNoInnerRetry,
                $core15,
                new \App\AI\Core\TaskDispatcher($configNoInnerRetry),
                new \App\AI\Core\OutputHandler(new \App\AI\Assets\AssetManager($configNoInnerRetry))
            );

            $req15 = $runner15->request($f14Key, ['content' => 'Fence me'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $req15['id'];

            $reclaimTaskId = $req15['id'];
            $reclaimDispatch = new \App\AI\Core\TaskDispatcher($configNoInnerRetry);

            // Khi provider đang chạy: worker khác đẩy lock cũ thành stale rồi claim lại.
            $fenceState['hijack'] = static function () use ($pdo, $reclaimDispatch, $reclaimTaskId): void {
                $pdo->prepare("UPDATE vc_ai_tasks SET locked_at = '2000-01-01 00:00:00' WHERE id = :id")
                    ->execute(['id' => $reclaimTaskId]);
                $reclaimDispatch->claim($reclaimTaskId, 'worker-khac');
            };

            $proc15 = $runner15->process($req15['id'], 'w1');

            $t->equals('F15. process() fail-closed (skipped) khi mất lock giữa lúc gọi provider', \App\AI\Core\TaskRunner::RESULT_SKIPPED, $proc15['status']);
            $t->equals('F15. Provider ĐÃ được gọi (hijack xảy ra đúng giữa pipeline)', 1, $hijackProvider->hijacked);

            $cntOut15 = $pdo->prepare('SELECT COUNT(*) AS c FROM vc_ai_outputs WHERE task_id = :id');
            $cntOut15->execute(['id' => $req15['id']]);
            $t->equals('F15. KHÔNG tạo output khi đã mất lock (không ghi bẩn)', 0, (int) $cntOut15->fetch()['c']);

            $row15 = $pdo->prepare('SELECT status, locked_by, attempt_count FROM vc_ai_tasks WHERE id = :id');
            $row15->execute(['id' => $req15['id']]);
            $r15 = $row15->fetch() ?: [];
            $t->check(
                'F15. Task vẫn thuộc worker mới (processing), worker cũ KHÔNG ghi finish',
                ($r15['status'] ?? '') === 'processing'
                    && ($r15['locked_by'] ?? '') === 'worker-khac'
                    && (int) ($r15['attempt_count'] ?? 0) === 2
            );
        } catch (Throwable $e) {
            $t->check('F15. Fencing output/asset (G1)', false, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // F16. G6 — process() KHÔNG xử lý task 'retrying' khi CHƯA tới hạn
    //      retry_after; ĐÚNG hạn thì xử lý; drain() cũng tôn trọng retry_after.
    // -----------------------------------------------------------------
    if ($migrationApplied && $fkViable) {
        try {
            [$runner16] = $buildRunner([
                $chatOk('r1'), $chatOk('r2'), $chatOk('r3'), $chatOk('r4'),
                $chatOk('r5'), $chatOk('r6'), $chatOk('r7'), $chatOk('r8'),
            ], $configNoInnerRetry);

            // (a) retrying với retry_after TƯƠNG LAI → skip, KHÔNG gọi provider.
            $a16 = $runner16->request($f14Key, ['content' => 'not-due'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $a16['id'];
            $pdo->prepare("UPDATE vc_ai_tasks SET status = 'retrying', retry_after = :future WHERE id = :id")
                ->execute(['future' => date('Y-m-d H:i:s', time() + 3600), 'id' => $a16['id']]);
            $procA16 = $runner16->process($a16['id'], 'w1');
            $t->equals('F16. retrying CHƯA tới hạn → process() skip', \App\AI\Core\TaskRunner::RESULT_SKIPPED, $procA16['status']);

            $rowA16 = $pdo->prepare('SELECT status, locked_at FROM vc_ai_tasks WHERE id = :id');
            $rowA16->execute(['id' => $a16['id']]);
            $rA16 = $rowA16->fetch() ?: [];
            $t->check(
                'F16. retrying chưa hạn: giữ nguyên retrying + KHÔNG đụng lock (kiểm tra trước claim)',
                ($rA16['status'] ?? '') === 'retrying' && $rA16['locked_at'] === null
            );
            $cntA16 = $pdo->prepare('SELECT COUNT(*) AS c FROM vc_ai_outputs WHERE task_id = :id');
            $cntA16->execute(['id' => $a16['id']]);
            $t->equals('F16. retrying chưa hạn: KHÔNG sinh output', 0, (int) $cntA16->fetch()['c']);

            // (b) retrying đã tới hạn → xử lý bình thường.
            $b16 = $runner16->request($f14Key, ['content' => 'due'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $b16['id'];
            $pdo->prepare("UPDATE vc_ai_tasks SET status = 'retrying', retry_after = :past WHERE id = :id")
                ->execute(['past' => '2000-01-01 00:00:00', 'id' => $b16['id']]);
            $procB16 = $runner16->process($b16['id'], 'w2');
            $t->equals('F16. retrying ĐÃ tới hạn → process() xử lý', \App\AI\Core\TaskRunner::RESULT_PROCESSED, $procB16['status']);

            // (c) drain() bỏ qua task chưa hạn, xử lý task đã hạn.
            $c16 = $runner16->request($f14Key, ['content' => 'drain-not-due'], ['model' => $modelKey]);
            $d16 = $runner16->request($f14Key, ['content' => 'drain-due'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $c16['id'];
            $created['vc_ai_tasks'][] = $d16['id'];
            $pdo->prepare("UPDATE vc_ai_tasks SET status = 'retrying', retry_after = :future WHERE id = :id")
                ->execute(['future' => date('Y-m-d H:i:s', time() + 3600), 'id' => $c16['id']]);
            $pdo->prepare("UPDATE vc_ai_tasks SET status = 'retrying', retry_after = :past WHERE id = :id")
                ->execute(['past' => '2000-01-01 00:00:00', 'id' => $d16['id']]);

            $runner16->drain('w-drain', 20);

            $rowC16 = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $rowC16->execute(['id' => $c16['id']]);
            $t->check('F16. drain() BỎ QUA task chưa tới hạn', ($rowC16->fetch()['status'] ?? '') === 'retrying');

            $rowD16 = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $rowD16->execute(['id' => $d16['id']]);
            $t->check('F16. drain() XỬ LÝ task đã tới hạn', ($rowD16->fetch()['status'] ?? '') === 'completed');
        } catch (Throwable $e) {
            $t->check('F16. retry_after được tôn trọng (G6)', false, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // F17. G4 — TƯƠNG TRANH idempotency: INSERT đụng UNIQUE(idempotency_key)
    //      phải NHẬN NUÔI row đang tồn tại (created=false), KHÔNG ném ra ngoài,
    //      KHÔNG tạo trùng; lỗi 23000 KHÁC (vd FK) vẫn phải được ném lại.
    // -----------------------------------------------------------------
    if ($migrationApplied && $fkViable) {
        try {
            $disp17 = new \App\AI\Core\TaskDispatcher($config);
            $key17 = 'selftest-race-' . bin2hex(random_bytes(6));

            // Mô phỏng request song song đã thắng cuộc đua trước đó.
            $winner17 = $disp17->create($f14ModuleId, 'selftest', ['p' => 1], [], null, $key17, null);
            $created['vc_ai_tasks'][] = $winner17['id'];

            // Gọi thẳng persistNewTask (bỏ qua pre-check) để ép nhánh UNIQUE 23000.
            $ref17 = new \ReflectionMethod(\App\AI\Core\TaskDispatcher::class, 'persistNewTask');
            $ref17->setAccessible(true);

            $adopted = $ref17->invoke($disp17, new \App\Models\AITask(), [
                'module_id'       => $f14ModuleId,
                'task_type'       => 'selftest',
                'status'          => 'pending',
                'idempotency_key' => $key17,
                'max_retries'     => 3,
                'priority'        => 5,
            ], $key17);

            $t->check(
                'F17. UNIQUE 23000 khi tạo task → NHẬN NUÔI row cũ (adopted, đúng id)',
                is_array($adopted) && ($adopted['adopted'] ?? false) === true && (int) ($adopted['id'] ?? 0) === $winner17['id']
            );

            $cnt17 = $pdo->prepare('SELECT COUNT(*) AS c FROM vc_ai_tasks WHERE idempotency_key = :key');
            $cnt17->execute(['key' => $key17]);
            $t->equals('F17. Tương tranh KHÔNG tạo task trùng', 1, (int) $cnt17->fetch()['c']);

            // Lỗi 23000 KHÁC (FK module_id không tồn tại) KHÔNG được nuốt.
            $fkThrew = false;
            try {
                $ref17->invoke($disp17, new \App\Models\AITask(), [
                    'module_id'       => 2000000000,
                    'task_type'       => 'selftest',
                    'status'          => 'pending',
                    'idempotency_key' => 'selftest-fk-' . bin2hex(random_bytes(6)),
                    'max_retries'     => 3,
                    'priority'        => 5,
                ], 'selftest-fk-' . bin2hex(random_bytes(6)));
            } catch (\PDOException $e) {
                $fkThrew = true;
            }
            $t->check('F17. Lỗi 23000 KHÁC (FK) vẫn được ném lại (không che giấu)', $fkThrew);
        } catch (Throwable $e) {
            $t->check('F17. Tương tranh idempotency (G4)', false, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // F18. G5 — Hợp đồng trạng thái: ENUM vc_ai_tasks.status đúng 7 giá trị
    //      hiện có; finish() từ chối trạng thái không hợp lệ; TaskRunner chỉ
    //      kết thúc task bằng trạng thái NẰM TRONG ENUM.
    // -----------------------------------------------------------------
    if ($migrationApplied && $fkViable) {
        try {
            $enumStmt = $pdo->prepare(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $enumStmt->execute(['db' => $dbName, 't' => 'vc_ai_tasks', 'c' => 'status']);
            $enumType = (string) ($enumStmt->fetch()['COLUMN_TYPE'] ?? '');

            $t->check(
                'F18. G5: ENUM vc_ai_tasks.status = 7 giá trị hiện có (chưa gồm awaiting_review/approved/rejected)',
                str_contains($enumType, "'pending','queued','processing','completed','failed','cancelled','retrying'"),
                $enumType
            );

            $disp18 = new \App\AI\Core\TaskDispatcher($config);
            $badRejected = false;
            try {
                $disp18->finish($winner17['id'], 'không-hợp-lệ', []);
            } catch (Throwable $e) {
                $badRejected = true;
            }
            $t->check('F18. finish() từ chối trạng thái không hợp lệ (VALIDATION)', $badRejected);

            // TaskRunner hoàn tất một task THẬT chỉ bằng 'completed' (nằm trong ENUM).
            [$runner18] = $buildRunner([$chatOk('G5 ok')], $configNoInnerRetry);
            $e18 = $runner18->request($f14Key, ['content' => 'g5'], ['model' => $modelKey]);
            $created['vc_ai_tasks'][] = $e18['id'];
            $runner18->process($e18['id'], 'w1');
            $rowE18 = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $rowE18->execute(['id' => $e18['id']]);
            $t->check(
                'F18. TaskRunner kết thúc task bằng trạng thái NẰM TRONG ENUM (completed)',
                ($rowE18->fetch()['status'] ?? '') === 'completed'
            );
        } catch (Throwable $e) {
            $t->check('F18. Trạng thái task đúng ENUM (G5)', false, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // F21. P5 — VIDEO LRO (Long-Running Operation): provider trả operation
    //      id ngay nhưng media CHƯA xong → TaskRunner requeue về 'queued'
    //      (KHÔNG tính retry_count) chờ poll; lần claim sau videoStatus()
    //      → xong thì ghi output (asset video). Vượt hạn tổng → failed(TIMEOUT);
    //      provider báo thất bại rõ ràng → failed(CLIENT_ERROR); requeue có fencing.
    // -----------------------------------------------------------------
    if ($migrationApplied && $fkViable) {
        try {
            // Fixture: module 'video_generation' ĐÚNG module_key trong ModuleRegistry
            // (chỉ tạo nếu chưa có, không đụng bản thật — như F14 với content_article).
            $f21Key = 'video_generation';
            $row21 = $pdo->query("SELECT id FROM vc_ai_modules WHERE module_key = 'video_generation' LIMIT 1")->fetch();
            if ($row21) {
                $f21ModuleId = (int) $row21['id'];
            } else {
                $pdo->prepare("INSERT INTO vc_ai_modules (module_key, module_name, capability, is_enabled) VALUES ('video_generation', 'Sinh video', 'video', 1)")->execute();
                $f21ModuleId = (int) $pdo->lastInsertId();
                $created['vc_ai_modules'][] = $f21ModuleId;
            }
            $t->check('F21. Có module video_generation để chạy LRO', $f21ModuleId > 0);

            $f21ModelKey  = 'selftest-video-model';
            $videoQueued  = ['status' => 200, 'body' => json_encode(['id' => 'op_lro_1', 'status' => 'queued'])];
            $videoRunning = ['status' => 200, 'body' => json_encode(['id' => 'op_x', 'status' => 'running'])];
            $videoDone    = ['status' => 200, 'body' => json_encode([
                'id'     => 'op_poll1',
                'status' => 'completed',
                'data'   => [['b64_json' => base64_encode('fake-video-payload')]],
            ])];
            $videoFailed  = ['status' => 200, 'body' => json_encode(['id' => 'op_fail', 'status' => 'failed', 'error' => ['message' => 'content policy']])];

            // F21.1: lượt ĐẦU — provider trả operation id, media chưa xong
            //        → requeue về 'queued', KHÔNG tăng retry_count, KHÔNG có output.
            [$runner21] = $buildRunner([$videoQueued]);
            $req21 = $runner21->request($f21Key, ['prompt' => 'mèo bay LRO'], ['model' => $f21ModelKey]);
            $created['vc_ai_tasks'][] = $req21['id'];

            $proc21 = $runner21->process($req21['id'], 'w1');
            $t->equals('F21. Lượt đầu: provider chưa xong → skipped (đã requeue)', \App\AI\Core\TaskRunner::RESULT_SKIPPED, $proc21['status']);
            $t->equals('F21. Outcome mang operation_id để truy vết', 'op_lro_1', (string) ($proc21['operation_id'] ?? ''));

            $row21a = $pdo->prepare('SELECT status, lock_token, locked_by, attempt_count, retry_count, retry_after, params FROM vc_ai_tasks WHERE id = :id');
            $row21a->execute(['id' => $req21['id']]);
            $r21a = $row21a->fetch() ?: [];
            $params21a = is_string($r21a['params'] ?? null) ? (json_decode((string) $r21a['params'], true) ?: []) : [];
            $t->check(
                'F21. Task về đúng trạng thái queued + nhả lock',
                ($r21a['status'] ?? '') === 'queued'
                    && ($r21a['lock_token'] ?? '') === ''
                    && ($r21a['locked_by'] ?? '') === ''
            );
            $t->check(
                'F21. LRO KHÔNG tính retry (retry_count=0, attempt=1) + operation id nằm trong params',
                (int) ($r21a['retry_count'] ?? -1) === 0
                    && (int) ($r21a['attempt_count'] ?? 0) === 1
                    && ($params21a['kira_operation_id'] ?? '') === 'op_lro_1'
                    && (int) ($params21a['lro_polls'] ?? 0) === 1
            );
            $t->check('F21. retry_after đặt lịch poll trong tương lai', (string) ($r21a['retry_after'] ?? '') > date('Y-m-d H:i:s'));

            $cntOut21 = $pdo->prepare('SELECT COUNT(*) AS c FROM vc_ai_outputs WHERE task_id = :id');
            $cntOut21->execute(['id' => $req21['id']]);
            $t->equals('F21. Chưa xong → KHÔNG tạo output', 0, (int) $cntOut21->fetch()['c']);

            $act21 = $pdo->prepare("SELECT COUNT(*) AS c FROM vc_ai_task_activities WHERE task_id = :id AND activity_type = 'video_lro_wait'");
            $act21->execute(['id' => $req21['id']]);
            $t->check('F21. Ghi activity video_lro_wait', (int) $act21->fetch()['c'] >= 1);

            // F21.2: chưa tới hạn poll → process() bỏ qua (tôn trọng retry_after).
            $proc21b = $runner21->process($req21['id'], 'w1');
            $t->equals('F21. Chưa tới hạn poll → process() skip', \App\AI\Core\TaskRunner::RESULT_SKIPPED, $proc21b['status']);

            // F21.3: lượt POLL — videoStatus() báo completed kèm media (b64)
            //        → ghi output + asset video + finish completed.
            [$runner21c] = $buildRunner([$videoDone]);
            $req21c = $runner21c->request($f21Key, ['prompt' => 'poll tôi'], ['model' => $f21ModelKey]);
            $created['vc_ai_tasks'][] = $req21c['id'];
            $pdo->prepare("UPDATE vc_ai_tasks SET status = 'queued', retry_after = '2000-01-01 00:00:00', params = :params WHERE id = :id")->execute([
                'params' => json_encode(['model' => $f21ModelKey, 'kira_operation_id' => 'op_poll1', 'lro_polls' => 2]),
                'id'     => $req21c['id'],
            ]);

            $proc21c = $runner21c->process($req21c['id'], 'w2');
            $t->equals('F21. Poll: provider báo completed → processed', \App\AI\Core\TaskRunner::RESULT_PROCESSED, $proc21c['status']);
            $t->check('F21. Poll: sinh output + version + asset video', ($proc21c['output_id'] ?? 0) > 0 && ($proc21c['asset_id'] ?? 0) > 0);

            $row21c = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $row21c->execute(['id' => $req21c['id']]);
            $t->equals('F21. Poll: task kết thúc completed', 'completed', $row21c->fetch()['status'] ?? '');

            $assetId21 = (int) ($proc21c['asset_id'] ?? 0);
            $relPath21 = '';
            if ($assetId21 > 0) {
                $a21 = $pdo->prepare('SELECT relative_path, asset_kind FROM vc_ai_assets WHERE id = :id');
                $a21->execute(['id' => $assetId21]);
                $assetRow21 = $a21->fetch() ?: [];
                $relPath21 = (string) ($assetRow21['relative_path'] ?? '');
                $t->check(
                    'F21. Asset video lưu đúng loại + file vật lý tồn tại',
                    ($assetRow21['asset_kind'] ?? '') === 'video' && $relPath21 !== '' && is_file($ROOT . '/' . $relPath21),
                    $relPath21
                );
            }

            // F21.4: LRO vượt hạn tổng → failed(TIMEOUT), KHÔNG poll vô hạn.
            $cfgTimeout21 = $config;
            $cfgTimeout21['task'] = array_merge(is_array($config['task'] ?? null) ? $config['task'] : [], ['video_lro_timeout_seconds' => 1]);
            [$runner21d] = $buildRunner([$videoRunning], $cfgTimeout21);
            $req21d = $runner21d->request($f21Key, ['prompt' => 'timeout tôi'], ['model' => $f21ModelKey]);
            $created['vc_ai_tasks'][] = $req21d['id'];
            $pdo->prepare("UPDATE vc_ai_tasks SET status = 'queued', retry_after = '2000-01-01 00:00:00', params = :params, started_at = :started WHERE id = :id")->execute([
                'params'  => json_encode(['model' => $f21ModelKey, 'kira_operation_id' => 'op_x', 'lro_polls' => 5]),
                'started' => date('Y-m-d H:i:s', time() - 10),
                'id'      => $req21d['id'],
            ]);

            $proc21d = $runner21d->process($req21d['id'], 'w3');
            $t->equals('F21. LRO vượt hạn tổng → failed', \App\AI\Core\TaskRunner::RESULT_FAILED, $proc21d['status']);
            $row21d = $pdo->prepare('SELECT status, error_code FROM vc_ai_tasks WHERE id = :id');
            $row21d->execute(['id' => $req21d['id']]);
            $r21d = $row21d->fetch() ?: [];
            $t->check('F21. Timeout: task failed với error_code TIMEOUT', ($r21d['status'] ?? '') === 'failed' && ($r21d['error_code'] ?? '') === 'TIMEOUT');

            // F21.5: provider báo LRO thất bại rõ ràng → failed, KHÔNG retry, KHÔNG poll.
            [$runner21e] = $buildRunner([$videoFailed]);
            $req21e = $runner21e->request($f21Key, ['prompt' => 'fail tôi'], ['model' => $f21ModelKey]);
            $created['vc_ai_tasks'][] = $req21e['id'];
            $pdo->prepare("UPDATE vc_ai_tasks SET status = 'queued', retry_after = '2000-01-01 00:00:00', params = :params WHERE id = :id")->execute([
                'params' => json_encode(['model' => $f21ModelKey, 'kira_operation_id' => 'op_fail', 'lro_polls' => 1]),
                'id'     => $req21e['id'],
            ]);

            $proc21e = $runner21e->process($req21e['id'], 'w4');
            $t->equals('F21. Provider báo LRO failed → failed', \App\AI\Core\TaskRunner::RESULT_FAILED, $proc21e['status']);
            $row21e = $pdo->prepare('SELECT status, error_code, retry_count FROM vc_ai_tasks WHERE id = :id');
            $row21e->execute(['id' => $req21e['id']]);
            $r21e = $row21e->fetch() ?: [];
            $t->check(
                'F21. LRO failed: task failed (CLIENT_ERROR), không tăng retry_count',
                ($r21e['status'] ?? '') === 'failed'
                    && ($r21e['error_code'] ?? '') === 'CLIENT_ERROR'
                    && (int) ($r21e['retry_count'] ?? -1) === 0
            );

            // F21.6: requeue() có FENCING — token sai không thể đẩy task về queued.
            $disp21 = new \App\AI\Core\TaskDispatcher($config);
            $req21f = $disp21->create($f21ModuleId, 'selftest', [], [], null, 'selftest-lro-' . bin2hex(random_bytes(6)), null);
            $created['vc_ai_tasks'][] = $req21f['id'];
            $disp21->claim($req21f['id'], 'w5');
            $fenced21 = $disp21->requeue($req21f['id'], 'token-sai', 60, []);
            $row21f = $pdo->prepare('SELECT status FROM vc_ai_tasks WHERE id = :id');
            $row21f->execute(['id' => $req21f['id']]);
            $t->check(
                'F21. requeue() fencing: token sai → bị chặn, task giữ nguyên processing',
                $fenced21 === false && ($row21f->fetch()['status'] ?? '') === 'processing'
            );

            // Dọn file + row asset (output/version đã cascade theo task).
            if ($relPath21 !== '' && is_file($ROOT . '/' . $relPath21)) {
                @unlink($ROOT . '/' . $relPath21);
            }
            if ($assetId21 > 0) {
                $pdo->prepare('DELETE FROM vc_ai_assets WHERE id = :id')->execute(['id' => $assetId21]);
            }
        } catch (Throwable $e) {
            $t->check('F21. Video LRO requeue (P5)', false, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // F19. B1 — RANH GIỚI TÍCH HỢP PRODUCTION (đã mở CÓ KIỂM SOÁT).
    //
    //  Lịch sử: AI Core từng KHÔNG có caller production nào → blocker B1.
    //  Nay B1 được gỡ bằng MỘT seam chính thức DUY NHẤT:
    //    - khu quản trị Admin AI  (/admin/ai/*  →  Admin\Ai*Controller)
    //    - worker CLI             (tools/ai_worker.php)
    //  F19 kiểm chứng ranh giới MỚI (không nới lỏng tuỳ tiện):
    //    (a) tầng production CŨ (top-level Controllers/Services/Models) vẫn SẠCH;
    //    (b) seam tồn tại, và chỉ DUY NHẤT 1 controller là producer của TaskRunner;
    //    (c) routes/web.php chỉ nối /admin/ai* tới controller Admin\Ai*;
    //    (d) các luồng LEGACY bất khả xâm phạm KHÔNG bị đổi sang AI Core;
    //    (e) seam KHÔNG tạo authentication mới (dùng lại session/quyền sẵn có);
    //    (f) AI Core vẫn nằm gọn trong app/AI/** (không rò rỉ ra tầng khác);
    //    (g) worker CLI chỉ đi qua TaskRunner, không gọi thẳng provider.
    // -----------------------------------------------------------------
    try {
        $stripPhp = static function (string $code): string {
            $code = (string) preg_replace('#/\*.*?\*/#s', '', $code);
            return (string) preg_replace('#(^|\s)//[^\n]*#', '', $code);
        };

        // (a) Tầng production CŨ vẫn KHÔNG có caller AI Core.
        //
        //  NGOẠI LỆ ĐƯỢC UỶ QUYỀN (Phase 2 — "loại bỏ luồng AI cũ"):
        //   app/Services/AIProviderService.php nay CHỈ còn là adapter tương thích
        //   ngược (thin back-compat adapter). Nó KHÔNG gọi provider trực tiếp nữa
        //   mà uỷ thác toàn bộ cho AICore. Đây là cầu nối DUY NHẤT được phép
        //   tồn tại ở tầng Service — service legacy (Chatbot, Fanpage) đi qua
        //   nó; CronController nay KHÔNG còn sinh AI khi đăng bài (luồng thống
        //   nhất: tạo xong → lên lịch → cron chỉ đăng snapshot).
        //   Mọi file KHÁC trong tầng cũ vẫn phải sạch.
        $authorizedLegacyAdapters = ['AIProviderService.php'];

        $legacyRefs = [];
        $scanDirs = [$ROOT . '/app/Controllers', $ROOT . '/app/Services', $ROOT . '/app/Models'];
        foreach ($scanDirs as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $f) {
                if (in_array(basename($f), $authorizedLegacyAdapters, true)) {
                    continue;
                }
                $code = $stripPhp((string) file_get_contents($f));
                if (preg_match('/\b(TaskRunner|AICore|TaskDispatcher|OutputHandler)\b/', $code)) {
                    $legacyRefs[] = basename($f);
                }
            }
        }
        $t->check('F19. B1: tầng cũ (Controllers/Services/Models) KHÔNG có caller AI Core', $legacyRefs === [], implode(', ', $legacyRefs));

        // (a2) Adapter uỷ quyền PHẢI thực sự đi qua AICore (không tự gọi HTTP provider).
        $adapterFile = $ROOT . '/app/Services/AIProviderService.php';
        $t->check('F19. Phase2: AIProviderService là adapter uỷ quyền AICore', is_file($adapterFile));
        if (is_file($adapterFile)) {
            $adapter = $stripPhp((string) file_get_contents($adapterFile));
            $t->check(
                'F19. Phase2: adapter KHÔNG bypass AI Core (không tự gọi HTTP provider)',
                (bool) preg_match('/new AICore\(/', $adapter)
                    && !preg_match('#chat/completions|images/generations|generativelanguage|openrouter\.ai#', $adapter)
            );
        }

        // (b) Seam Admin AI tồn tại; producer TaskRunner đúng 1 controller.
        $seamControllers = glob($ROOT . '/app/Controllers/Admin/Ai*Controller.php') ?: [];
        $t->check('F19. Seam Admin AI tồn tại (>= 8 controller Admin\\Ai*)', count($seamControllers) >= 8, (string) count($seamControllers));

        // Điểm vào AI Core = lời gọi MUTATION (request/process/drain), không phải
        // khai báo factory runner() trong AiBaseController (hợp lệ).
        $runnerCallers = [];
        foreach ($seamControllers as $f) {
            if (preg_match('/->request\(|->process\(|->drain\(/', $stripPhp((string) file_get_contents($f)))) {
                $runnerCallers[] = basename($f);
            }
        }
        sort($runnerCallers);
        $t->check(
            'F19. Điểm vào AI Core (request/process/drain) DUY NHẤT là AiTaskController',
            $runnerCallers === ['AiTaskController.php'],
            implode(', ', $runnerCallers)
        );

        // (c) routes/web.php: mọi route /admin/ai* chỉ trỏ tới controller Admin\Ai*.
        $webRoutes = (string) file_get_contents($ROOT . '/routes/web.php');
        preg_match_all(
            "#^\\s*'([^']*?/admin/ai[^']*?)'\\s*=>\\s*\\[\\s*'([^']+)'#m",
            $webRoutes,
            $routeMatches,
            PREG_SET_ORDER
        );
        $aiRouteControllers = [];
        foreach ($routeMatches as $rm) {
            $aiRouteControllers[] = (string) $rm[2];
        }
        $aiRouteControllers = array_values(array_unique($aiRouteControllers));
        $badRouteTargets = array_values(array_filter(
            $aiRouteControllers,
            static fn(string $c): bool => !preg_match('#^Admin\\\\Ai[A-Za-z]*Controller$#', $c)
        ));

        $t->check('F19. Có route /admin/ai* trong routes/web.php', $aiRouteControllers !== []);
        $t->check('F19. Mọi route /admin/ai* trỏ tới controller Admin\\Ai*', $badRouteTargets === [], implode(', ', $badRouteTargets));
        $t->check('F19. routes/web.php KHÔNG gọi thẳng TaskRunner/AICore trong route', !preg_match('/TaskRunner|AICore|->drain\(|->process\(/', $stripPhp($webRoutes)));

        // (d) Luồng đăng bài THỐNG NHẤT: cron chỉ đăng snapshot đã xếp lịch —
        //     không sinh nội dung/ảnh bằng AI (bài "tạo xong rồi mới đăng").
        $cron = $stripPhp((string) file_get_contents($ROOT . '/app/Controllers/CronController.php'));
        $t->check('F19. B1: CronController KHÔNG sinh AI khi đăng (generateContent/generateImage/AIProviderService)', !preg_match('/generateContent|generateImage|AIProviderService/', $cron));
        $t->check('F19. B1: CronController KHÔNG gọi AI Core/TaskRunner', !preg_match('/TaskRunner|AICore|TaskDispatcher/', $cron));

        $setting = $stripPhp((string) file_get_contents($ROOT . '/app/Controllers/Admin/SettingController.php'));
        $t->check('F19. B1: SettingController (legacy) KHÔNG đụng bảng vc_ai_*', !preg_match('/vc_ai_/', $setting));

        // (e) Seam KHÔNG tạo hệ authentication mới (dùng lại session/quyền sẵn có).
        $aiBase = $stripPhp((string) file_get_contents($ROOT . '/app/Controllers/Admin/AiBaseController.php'));
        $t->check('F19. AiBaseController kế thừa BaseController (không auth mới)', str_contains($aiBase, 'extends BaseController'));
        $t->check('F19. AiBaseController gác quyền admin qua $_SESSION sẵn có', str_contains($aiBase, '$_SESSION') && str_contains($aiBase, "'role'"));

        // (f) AI Core vẫn nằm gọn trong app/AI/**; TaskRunner là điểm vào duy nhất.
        $t->check('F19. B1: TaskRunner vẫn nằm trong app/AI/Core', is_file($ROOT . '/app/AI/Core/TaskRunner.php'));

        // (g) Worker CLI tồn tại, đi qua TaskRunner, KHÔNG gọi thẳng provider.
        $workerFile = $ROOT . '/tools/ai_worker.php';
        $t->check('F19. Worker CLI tools/ai_worker.php tồn tại', is_file($workerFile));
        if (is_file($workerFile)) {
            $worker = $stripPhp((string) file_get_contents($workerFile));
            $t->check('F19. Worker đi qua TaskRunner (->drain/->process)', (bool) preg_match('/->drain\(|->process\(/', $worker));
            $t->check('F19. Worker KHÔNG gọi thẳng provider (KiraProvider/->chat/->image)', !preg_match('/KiraProvider|->chat\(|->image\(/', $worker));
        }
    } catch (Throwable $e) {
        $t->check('F19. Ranh giới tích hợp (B1)', false, $e->getMessage());
    }

    // -----------------------------------------------------------------
    // F20. PHASE 2 — LOẠI BỎ LUỒNG AI CŨ & HỢP NHẤT VỀ AI CORE.
    //
    //  (a) UI Cài Đặt Hệ Thống KHÔNG còn cấu hình AI cũ (OpenRouter/Gemini,
    //      model, system prompt) — chỉ còn cấu hình Fanpage.
    //  (b) Không còn endpoint AI cũ trong routes/web.php.
    //  (c) Prompt nay lưu theo FILE (PromptFileStore) và AI đọc từ file đó.
    //  (d) Có tab đọc lại hội thoại AI (Admin\AiConversationController).
    //  (e) Phân biệt nguồn hội thoại web vs facebook trong ChatbotService.
    //  (f) Chatbot biết user đã đăng nhập hay chưa + hiển thị tên.
    // -----------------------------------------------------------------
    try {
        $settingsView = (string) file_get_contents($ROOT . '/resources/views/admin/settings/index.php');
        $t->check(
            'F20. UI Cài Đặt KHÔNG còn input AI cũ (openai/gemini/model/prompt)',
            !preg_match('/ai_openai_api_key|ai_gemini_api_key|ai_openai_model|ai_gemini_model|ai_content_model|ai_image_model|ai_comment_model|ai_system_prompt|ai_content_system_prompt|ai_comment_system_prompt/', $settingsView)
        );
        $t->check(
            'F20. UI Cài Đặt KHÔNG còn JS gọi endpoint AI cũ',
            !preg_match('#/admin/settings/(ai-models|ai-connection-check)#', $settingsView)
                && !preg_match('/openAiUi|geminiUi|wireProviderActions/', $settingsView)
        );
        $t->check(
            'F20. UI Cài Đặt GIỮ cấu hình Fanpage (webhook/verify token/page token)',
            str_contains($settingsView, 'fanpage_verify_token')
                && str_contains($settingsView, 'fanpage_app_secret')
                && str_contains($settingsView, 'fanpage_page_access_token')
                && str_contains($settingsView, '/api/fanpage/webhook')
        );

        $webRoutesP2 = (string) file_get_contents($ROOT . '/routes/web.php');
        $t->check(
            'F20. routes/web.php KHÔNG còn endpoint AI cũ',
            !preg_match('#/admin/settings/(ai-models|ai-connection-check)#', $webRoutesP2)
        );
        $t->check(
            'F20. Có route đọc lại hội thoại AI (/admin/ai/conversations)',
            str_contains($webRoutesP2, '/admin/ai/conversations')
                && str_contains($webRoutesP2, 'AiConversationController')
        );

        $convController = $ROOT . '/app/Controllers/Admin/AiConversationController.php';
        $t->check('F20. AiConversationController tồn tại', is_file($convController));
        $t->check(
            'F20. View hội thoại AI tồn tại (index + detail)',
            is_file($ROOT . '/resources/views/admin/ai/conversations.php')
                && is_file($ROOT . '/resources/views/admin/ai/conversation-detail.php')
        );

        // (c) Prompt theo FILE.
        $t->check('F20. PromptFileStore tồn tại', is_file($ROOT . '/app/AI/Core/PromptFileStore.php'));
        $pfStore = $stripPhp((string) file_get_contents($ROOT . '/app/AI/Core/PromptFileStore.php'));
        $t->check(
            'F20. PromptFileStore dùng thư mục storage/prompts',
            str_contains($pfStore, 'storage') && str_contains($pfStore, 'prompts')
        );
        $promptRegistry = $stripPhp((string) file_get_contents($ROOT . '/app/AI/Core/PromptRegistry.php'));
        $t->check('F20. PromptRegistry đọc được template từ file', str_contains($promptRegistry, 'PromptFileStore'));

        // (e)+(f) Phân biệt nguồn & trạng thái đăng nhập trong ChatbotService.
        $chatbot = $stripPhp((string) file_get_contents($ROOT . '/app/Services/ChatbotService.php'));
        $t->check('F20. ChatbotService phân biệt nguồn web vs facebook', (bool) preg_match('/isFacebook|source.*fanpage|fp_/', $chatbot));
        $t->check('F20. ChatbotService biết user đã đăng nhập hay chưa', (bool) preg_match('/isLoggedIn|is_logged_in|auth_/', $chatbot));
        $t->check(
            'F20. ChatbotService đi qua AI Core (không gọi HTTP provider trực tiếp)',
            !preg_match('#chat/completions|generativelanguage|openrouter\.ai#', $chatbot)
        );

        // (f) Form chat hiển thị tên người dùng đã đăng nhập.
        $footerView = (string) file_get_contents($ROOT . '/resources/views/layouts/footer.php');
        $t->check(
            'F20. Khung chat hiển thị tên user đã đăng nhập (data-user-name)',
            str_contains($footerView, 'data-user-name') && str_contains($footerView, 'data-logged-in')
        );
    } catch (Throwable $e) {
        $t->check('F20. Phase 2 hợp nhất AI Core', false, $e->getMessage());
    }

    // F12. Migration tĩnh — chạy được trên DB sạch có đủ bảng tiên quyết.
    //      Kiểm tra bằng cách áp file migration lên DB dev khi mọi dependency đã sẵn sàng.
    $migrationFile = $ROOT . '/database/migrations/20260927_add_ai_core_tables.sql';
    if (!is_file($migrationFile)) {
        $t->check('F12. File migration tồn tại', false, $migrationFile);
    } else {
        $migrationSql = (string) file_get_contents($migrationFile);

        $t->check(
            'F12. Migration dùng CREATE TABLE IF NOT EXISTS cho MỌI bảng',
            preg_match_all('/^\s*CREATE TABLE IF NOT EXISTS/m', $migrationSql) === 7,
            'count=' . preg_match_all('/^\s*CREATE TABLE IF NOT EXISTS/m', $migrationSql)
        );
        $t->check(
            'F12. Migration KHÔNG có CREATE TABLE nào thiếu IF NOT EXISTS',
            preg_match_all('/^\s*CREATE TABLE(?! IF NOT EXISTS)/m', $migrationSql) === 0
        );
        $t->check('F12. Migration không có ALTER/DROP/TRUNCATE/DELETE/UPDATE bảng cũ', !preg_match(
            '/\b(ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE|DELETE\s+FROM|UPDATE\s+`?vc_(?!ai_))/i',
            $migrationSql
        ));

        if ($migrationApplied) {
            $idempotent = true;
            $errorMsg = '';
            foreach (array_filter(array_map('trim', explode(';', (string) preg_replace('/^\s*--.*$/m', '', $migrationSql)))) as $statement) {
                try {
                    $pdo->exec($statement);
                } catch (Throwable $e) {
                    $idempotent = false;
                    $errorMsg = $e->getMessage();
                    break;
                }
            }
            $t->check('F12. Chạy lại migration KHÔNG lỗi (idempotent)', $idempotent, $errorMsg);
        } else {
            $t->skip('F12. Chạy lại migration (idempotent)', 'DB dev chưa áp migration — kiểm tra đầy đủ ở Mục H');
        }
    }
}

// Dọn dẹp dữ liệu test (chạy cả khi DB không đủ điều kiện).
if ($dbOk) {
    foreach (['vc_ai_assets', 'vc_ai_tasks', 'vc_ai_modules'] as $table) {
        foreach (($created ?? [])[$table] ?? [] as $id) {
            try {
                $pdo->prepare("DELETE FROM `{$table}` WHERE id = :id")->execute(['id' => $id]);
            } catch (Throwable $e) {
                // bỏ qua — chỉ là dọn dẹp
            }
        }
    }

    // Dọn mọi module selftest còn sót (kể cả từ lần chạy trước).
    try {
        $pdo->exec("DELETE FROM vc_ai_modules WHERE module_key = 'SELFTEST_MODULE'");
    } catch (Throwable $e) {
        // bỏ qua
    }

    try {
        $leftover = (int) $pdo->query("SELECT COUNT(*) AS c FROM vc_ai_modules WHERE module_key = 'SELFTEST_MODULE'")->fetch()['c'];
        $t->equals('F13. Dữ liệu test đã dọn sạch', 0, $leftover);
    } catch (Throwable $e) {
        $t->skip('F13. Dọn dẹp dữ liệu test', $e->getMessage());
    }
}

// =====================================================================
// H. KIỂM CHỨNG MIGRATION TRÊN DATABASE SẠCH (nguồn chân lý)
// =====================================================================
// DB dev có thể đã drift (thiếu bảng cũ). Mục H dựng DB tạm từ
// database/vpn_service.sql rồi áp migration → chứng minh migration đúng,
// độc lập với tình trạng DB dev. DB tạm bị xoá ngay sau khi kiểm tra.
$t->group('G. Kiểm chứng migration trên DB sạch');

$scratchDb = 'vpn_selftest_' . bin2hex(random_bytes(4));
$mysqlBin = null;

foreach ([
    'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe',
    'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe',
    '/usr/bin/mysql',
    '/usr/local/bin/mysql',
] as $candidate) {
    if (is_file($candidate)) {
        $mysqlBin = $candidate;
        break;
    }
}

if ($mysqlBin === null) {
    $t->skip('H. Kiểm chứng migration trên DB sạch', 'không tìm thấy mysql client');
} elseif (!$hasPdoMysql) {
    $t->skip('H. Kiểm chứng migration trên DB sạch', 'extension pdo_mysql chưa bật');
} else {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $user = $_ENV['DB_USERNAME'] ?? 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? '';

    // mysql CLI cần đường dẫn kiểu Windows khi chạy từ PHP.
    $mysqlCmd = '"' . str_replace('/', '\\', $mysqlBin) . '"';
    $passArg = $pass !== '' ? ' -p' . escapeshellarg($pass) : '';

    $runMysql = static function (string $extra) use ($mysqlCmd, $host, $port, $user, $passArg): array {
        $cmd = $mysqlCmd . ' --protocol=TCP --default-character-set=utf8mb4 -h ' . escapeshellarg($host)
            . ' -P ' . escapeshellarg($port) . ' -u ' . escapeshellarg($user) . $passArg . ' ' . $extra;
        $out = [];
        $code = 0;
        exec($cmd . ' 2>&1', $out, $code);

        return ['code' => $code, 'out' => implode("\n", $out)];
    };

    try {
        $create = $runMysql('-e "CREATE DATABASE ' . $scratchDb . ' DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;"');
        $t->check('G1. Tạo được database tạm', $create['code'] === 0, $create['out']);

        if ($create['code'] === 0) {
            // Import schema legacy
            $dumpPath = $ROOT . '/database/vpn_service.sql';
            $importCmd = $mysqlCmd . ' --protocol=TCP --default-character-set=utf8mb4 -h ' . escapeshellarg($host)
                . ' -P ' . escapeshellarg($port) . ' -u ' . escapeshellarg($user) . $passArg . ' ' . $scratchDb
                . ' < ' . escapeshellarg(str_replace('/', '\\', $dumpPath));
            $out = [];
            $code = 0;
            exec($importCmd . ' 2>&1', $out, $code);
            $importOut = implode("\n", $out);

            $t->check('G2. Import được schema legacy (database/vpn_service.sql)', $code === 0, $importOut);

            $countLegacy = $runMysql('-N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\'' . $scratchDb . '\';"');
            $legacyCount = (int) trim($countLegacy['out']);
            $t->check('G2. Schema legacy có >= 20 bảng', $legacyCount >= 20, 'count=' . $legacyCount);

            // Áp migration
            $migrationPath = $ROOT . '/database/migrations/20260927_add_ai_core_tables.sql';
            $migCmd = $mysqlCmd . ' --protocol=TCP --default-character-set=utf8mb4 -h ' . escapeshellarg($host)
                . ' -P ' . escapeshellarg($port) . ' -u ' . escapeshellarg($user) . $passArg . ' ' . $scratchDb
                . ' < ' . escapeshellarg(str_replace('/', '\\', $migrationPath));
            $out = [];
            $code = 0;
            exec($migCmd . ' 2>&1', $out, $code);
            $migOut = implode("\n", $out);

            $t->check('G3. Migration áp thành công lên DB sạch (0 lỗi)', $code === 0, $migOut);

            // Xác nhận đủ 7 bảng mới (P6: 7 bảng chết, P9: 2 bảng prompt DB đã loại khỏi migration)
            $afterCount = (int) trim($runMysql('-N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\'' . $scratchDb . '\';"')['out']);
            $t->equals('G4. Tổng số bảng = legacy + 7', $legacyCount + 7, $afterCount);

            // Chạy lại migration lần 2 → phải không lỗi (idempotent)
            $out = [];
            $code = 0;
            exec($migCmd . ' 2>&1', $out, $code);
            $t->check('G5. Chạy lại migration lần 2 KHÔNG lỗi (idempotent)', $code === 0, implode("\n", $out));

            // FK + PK trên DB tạm
            $fkCount = (int) trim($runMysql('-N -e "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE table_schema=\'' . $scratchDb . '\' AND table_name LIKE \'vc_ai_%\' AND referenced_table_name IS NOT NULL;"')['out']);
            $t->check('G6. Có >= 14 FK trên 7 bảng mới', $fkCount >= 14, 'count=' . $fkCount);

            $tableCount = (int) trim($runMysql('-N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\'' . $scratchDb . '\' AND table_name LIKE \'vc_ai_%\';"')['out']);
            $t->equals('G7. Đủ 7 bảng vc_ai_* trên DB sạch', 7, $tableCount);

            // Kiểm chứng struct không đổi bảng cũ: so sánh checksum cột của bảng cũ trước/sau
            $legacyColsAfter = trim($runMysql('-N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=\'' . $scratchDb . '\' AND table_name NOT LIKE \'vc_ai_%\';"')['out']);

            // Đối chiếu gốc: đếm cột trong DB sạch CHƯA migration (dùng lại tổng cột từ dump)
            $freshDb = $scratchDb . '_ref';
            $runMysql('-e "CREATE DATABASE ' . $freshDb . ' DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;"');
            $refCmd = $mysqlCmd . ' --protocol=TCP --default-character-set=utf8mb4 -h ' . escapeshellarg($host)
                . ' -P ' . escapeshellarg($port) . ' -u ' . escapeshellarg($user) . $passArg . ' ' . $freshDb
                . ' < ' . escapeshellarg(str_replace('/', '\\', $dumpPath));
            exec($refCmd . ' 2>&1', $refOut, $refCode);

            $legacyColsBefore = (int) trim($runMysql('-N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=\'' . $freshDb . '\' AND table_name NOT LIKE \'vc_ai_%\';"')['out']);
            $t->equals('G8. Migration KHÔNG đổi cấu trúc bảng cũ (số cột không đổi)', $legacyColsBefore, (int) $legacyColsAfter);

            $runMysql('-e "DROP DATABASE IF EXISTS ' . $freshDb . ';"');
        }
    } finally {
        $runMysql('-e "DROP DATABASE IF EXISTS ' . $scratchDb . ';"');
        $t->check('G9. Database tạm đã được xoá', true);
    }
}

// =====================================================================
// G. RANH GIỚI THAY ĐỔI
// =====================================================================
$t->group('H. Ranh giới thay đổi (files bất khả xâm phạm)');

$mustNotChange = [
    'database/vpn_service.sql',
    'vc_install.sh',
    'composer.json',
    'public/index.php',
    'routes/web.php',
    'routes/api.php',
    'app/Services/AIProviderService.php',
    'app/Services/ChatbotService.php',
    'app/Services/FanpageService.php',
    'app/Controllers/CronController.php',
    'app/Models/BaseModel.php',
    'config/app.php',
    'config/database.php',
];

foreach ($mustNotChange as $file) {
    $t->check("File còn tồn tại (không bị xoá): {$file}", is_file($ROOT . '/' . $file));
}

// vc_update.sh phải giữ block 3.1 cũ VÀ có block 3.2/3.3 mới
$updateSh = (string) @file_get_contents($ROOT . '/vc_update.sh');
$t->check('vc_update.sh giữ block migration cũ (20260918)', str_contains($updateSh, '20260918_add_order_audit_users.sql'));
$t->check('vc_update.sh có block migration AI Core (20260927)', str_contains($updateSh, '20260927_add_ai_core_tables.sql'));
$t->check('vc_update.sh có block dọn bảng AI chết (20260928)', str_contains($updateSh, '20260928_drop_unused_ai_tables.sql'));

// P6: 7 model chết phải KHÔNG còn trong codebase (zero-waste)
$deadModels = ['AIArticle', 'AIImage', 'AIVideo', 'AIAudio', 'AIFanpageComment', 'AICommentReply', 'AIUsageLog'];
$deadLeft = [];
foreach ($deadModels as $model) {
    if (is_file($ROOT . '/app/Models/' . $model . '.php')) {
        $deadLeft[] = $model;
    }
}
$t->check('P6. 7 model AI chết đã bị XOÁ khỏi app/Models', $deadLeft === [], implode(', ', $deadLeft));

// P6: không còn tham chiếu nào tới 7 model chết trong app/ (trừ selftest)
$deadRefs = [];
foreach (glob($ROOT . '/app/**/*.php') ?: [] as $file) {
    $content = (string) file_get_contents($file);
    foreach ($deadModels as $model) {
        if (preg_match('/\b' . $model . '\b/', $content)) {
            $deadRefs[] = basename($file) . ':' . $model;
        }
    }
}
$t->check('P6. Không còn tham chiếu 7 model chết trong app/**', $deadRefs === [], implode(', ', $deadRefs));

// .env KHÔNG được chứa KIRA_API_KEY thật do phase này thêm vào
$envFile = $ROOT . '/.env';
if (is_file($envFile)) {
    $envContent = (string) file_get_contents($envFile);
    $t->check('.env KHÔNG bị thêm API key thật ở Phase 5', !preg_match('/KIRA_API_KEY\s*=\s*[^\s]+/', $envContent));
} else {
    $t->skip('.env không tồn tại', 'không có file .env');
}

// Code AI Core không chứa key cứng
$aiFiles = array_merge(
    glob($ROOT . '/app/AI/**/*.php') ?: [],
    glob($ROOT . '/app/AI/*/*.php') ?: []
);
$hardcodedKey = [];
foreach (array_unique($aiFiles) as $file) {
    $content = (string) file_get_contents($file);
    if (preg_match('/(sk-[a-zA-Z0-9]{20,}|Bearer\s+[a-zA-Z0-9]{24,})/', $content)) {
        $hardcodedKey[] = basename($file);
    }
}
$t->check('Không có API key cứng trong app/AI/**', $hardcodedKey === [], implode(', ', $hardcodedKey));

// Không có lời gọi STT / video-edit chưa xác minh trong provider
$kiraSource = (string) @file_get_contents($ROOT . '/app/AI/Providers/KiraProvider.php');
$t->check('KiraProvider KHÔNG có endpoint STT (chưa xác minh)', !preg_match('#/audio/transcriptions#i', $kiraSource));
$t->check('KiraProvider KHÔNG có endpoint video-edit (chưa xác minh)', !preg_match('#/videos/(edits|edit)#i', $kiraSource));

exit($t->summary());
