<?php

declare(strict_types=1);

/**
 * AI Worker CLI — "consumer" chính thức của AI Core.
 *
 *   Admin UI (producer) → TaskRunner::request() → vc_ai_tasks
 *                                                    ↓
 *                              tools/ai_worker.php (consumer)
 *                                                    ↓
 *                        TaskRunner::process() → AICore → KiraProvider
 *
 * Cách dùng:
 *   php tools/ai_worker.php                       # quét 5 task đến hạn
 *   php tools/ai_worker.php --limit=20            # quét tối đa 20 task
 *   php tools/ai_worker.php --task=123            # chỉ chạy 1 task cụ thể
 *   php tools/ai_worker.php --worker=worker-01    # đặt tên worker
 *   php tools/ai_worker.php --sync-modules        # đồng bộ ModuleRegistry → vc_ai_modules rồi chạy
 *   php tools/ai_worker.php --dry-run             # chỉ in task đến hạn, KHÔNG xử lý
 *
 * Nguyên tắc:
 *  - KHÔNG phải daemon, KHÔNG cần queue server. Đây là một batch đồng bộ có
 *    giới hạn: chạy xong là thoát (dùng Windows Task Scheduler / cron gọi lại).
 *  - KHÔNG gọi provider trực tiếp: mọi lời gọi đi qua TaskRunner → AICore.
 *  - KHÔNG dùng dữ liệu fixture/self-test. Chỉ xử lý task THẬT trong DB.
 *  - KHÔNG seed model. Chỉ đồng bộ MODULE (định nghĩa trong code).
 *
 * Exit code: 0 = không có lỗi; 1 = có task thất bại / lỗi cấu hình.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Ho_Chi_Minh');
mb_internal_encoding('UTF-8');

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "ai_worker.php chỉ chạy được từ dòng lệnh (CLI).\n");
    exit(1);
}

$ROOT = dirname(__DIR__);

define('BASE_PATH', $ROOT);

// ---------------------------------------------------------------------
// Bootstrap (.env + autoload) — giống public/index.php, chạy độc lập.
// ---------------------------------------------------------------------
(static function (string $root): void {
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
        $value = trim(trim($value), "\"'");

        if ($key === '') {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
})($ROOT);

$autoload = $ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

spl_autoload_register(static function (string $class) use ($ROOT): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = $ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

// ---------------------------------------------------------------------
// Tham số dòng lệnh
// ---------------------------------------------------------------------
$options = [
    'limit'        => 5,
    'task'         => 0,
    'worker'       => 'cli-' . gethostname() . '-' . getmypid(),
    'sync-modules' => false,
    'dry-run'      => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $options['limit'] = max(1, min(200, (int) $m[1]));
        continue;
    }
    if (preg_match('/^--task=(\d+)$/', $arg, $m)) {
        $options['task'] = (int) $m[1];
        continue;
    }
    if (preg_match('/^--worker=(.+)$/', $arg, $m)) {
        $options['worker'] = preg_replace('/[^A-Za-z0-9_:\-\.]/', '', $m[1]) ?: $options['worker'];
        continue;
    }
    if ($arg === '--sync-modules') {
        $options['sync-modules'] = true;
        continue;
    }
    if ($arg === '--dry-run') {
        $options['dry-run'] = true;
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "AI Worker — consumer của AI Core (TaskRunner).\n\n";
        echo "  --limit=N         Số task tối đa mỗi lượt (mặc định 5, tối đa 200)\n";
        echo "  --task=ID         Chỉ xử lý một task theo ID\n";
        echo "  --worker=ID       Tên worker (mặc định cli-host-pid)\n";
        echo "  --sync-modules    Đồng bộ ModuleRegistry → vc_ai_modules trước khi chạy\n";
        echo "  --dry-run         Chỉ liệt kê task đến hạn, không xử lý\n";
        echo "  --help            Hiển thị trợ giúp\n";
        exit(0);
    }

    fwrite(STDERR, "Tham số không hợp lệ: {$arg}\n");
    exit(1);
}

$config = [];
$configFile = $ROOT . '/config/ai.php';
if (is_file($configFile)) {
    $loaded = require $configFile;
    if (is_array($loaded)) {
        $config = $loaded;
    }
}

use App\AI\Core\ModuleRegistry;
use App\AI\Core\ModuleSynchronizer;
use App\AI\Core\TaskRunner;
use App\Models\AIModule;
use App\Models\AITask;

$format = static function (string $s): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $s . PHP_EOL;
};

$format('AI Worker khởi động — worker=' . $options['worker'] . ', limit=' . $options['limit']);

$exitCode = 0;

try {
    // -----------------------------------------------------------------
    // (Tuỳ chọn) Đồng bộ module — additive + idempotent, chỉ THÊM module.
    // -----------------------------------------------------------------
    if ($options['sync-modules']) {
        $report = (new ModuleSynchronizer(new ModuleRegistry()))->sync();
        $format(sprintf(
            'Đồng bộ module: thêm %d, cập nhật %d, giữ nguyên %d.',
            $report['inserted'],
            $report['updated'],
            $report['unchanged']
        ));
    }

    $runner = new TaskRunner($config);

    // -----------------------------------------------------------------
    // Chế độ 1: chạy MỘT task theo ID.
    // -----------------------------------------------------------------
    if ($options['task'] > 0) {
        $taskId = $options['task'];

        $task = (new AITask())->find($taskId);
        if (!is_array($task)) {
            $format('Không tìm thấy task #' . $taskId . '.');
            exit(1);
        }

        if ($options['dry-run']) {
            $format(sprintf('DRY-RUN task #%d — status=%s', $taskId, (string) ($task['status'] ?? '')));
            exit(0);
        }

        $outcome = $runner->process($taskId, $options['worker']);
        $status = (string) ($outcome['status'] ?? '');

        $format(sprintf(
            'Task #%d → %s: %s',
            $taskId,
            strtoupper($status),
            (string) ($outcome['message'] ?? '')
        ));

        if (isset($outcome['output_id'])) {
            $format('  output_id=' . (string) $outcome['output_id']
                . ', version_id=' . (string) ($outcome['version_id'] ?? '-')
                . ', asset_id=' . (string) ($outcome['asset_id'] ?? '-'));
        }

        if ($status === TaskRunner::RESULT_FAILED) {
            $exitCode = 1;
        }

        exit($exitCode);
    }

    // -----------------------------------------------------------------
    // Chế độ 2 (mặc định): quét một lượt các task đến hạn.
    // -----------------------------------------------------------------
    if ($options['dry-run']) {
        $candidates = (new AITask())->byStatus(['pending', 'queued', 'retrying']);

        // Lọc bỏ task chưa tới hạn retry (so sánh chuỗi 'Y-m-d H:i:s' ổn định).
        $now = date('Y-m-d H:i:s');
        $due = [];
        foreach ((array) $candidates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $retryAfter = (string) ($row['retry_after'] ?? '');
            if ($retryAfter !== '' && $retryAfter > $now) {
                continue;
            }
            $due[] = $row;
        }

        usort($due, static function (array $a, array $b): int {
            $pa = (int) ($a['priority'] ?? 5);
            $pb = (int) ($b['priority'] ?? 5);
            return $pa !== $pb ? $pa <=> $pb : (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        $due = array_slice($due, 0, $options['limit']);

        if ($due === []) {
            $format('DRY-RUN: không có task nào đến hạn.');
            exit(0);
        }

        $format('DRY-RUN: ' . count($due) . ' task đến hạn:');
        foreach ($due as $row) {
            $format(sprintf(
                '  #%d module_id=%s status=%s priority=%s',
                (int) ($row['id'] ?? 0),
                (string) ($row['module_id'] ?? '-'),
                (string) ($row['status'] ?? '-'),
                (string) ($row['priority'] ?? '-')
            ));
        }

        exit(0);
    }

    $stats = $runner->drain($options['worker'], $options['limit']);

    $format(sprintf(
        'Kết quả: quét %d, xử lý %d, retry %d, thất bại %d, bỏ qua %d.',
        (int) $stats['scanned'],
        (int) $stats[TaskRunner::RESULT_PROCESSED],
        (int) $stats[TaskRunner::RESULT_RETRY],
        (int) $stats[TaskRunner::RESULT_FAILED],
        (int) $stats[TaskRunner::RESULT_SKIPPED]
    ));

    foreach ((array) ($stats['details'] ?? []) as $detail) {
        $format(sprintf(
            '  #%s → %s: %s',
            (string) ($detail['task_id'] ?? '-'),
            strtoupper((string) ($detail['status'] ?? '')),
            (string) ($detail['message'] ?? '')
        ));
    }

    if ((int) $stats[TaskRunner::RESULT_FAILED] > 0) {
        $exitCode = 1;
    }

    // Cảnh báo rõ nếu chưa có module nào được đăng ký (chưa chạy sync).
    if ((int) $stats['scanned'] === 0) {
        try {
            $moduleCount = count((array) (new AIModule())->getAll());
            if ($moduleCount === 0) {
                $format('Gợi ý: chưa có module nào trong vc_ai_modules — chạy lại với --sync-modules.');
            }
        } catch (\Throwable $e) {
            $format('Cảnh báo: không đọc được vc_ai_modules (' . $e->getMessage() . ').');
        }
    }
} catch (\Throwable $e) {
    fwrite(STDERR, '[LỖI] ' . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
}

exit($exitCode);
