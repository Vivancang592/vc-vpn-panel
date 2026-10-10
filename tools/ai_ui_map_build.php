<?php

declare(strict_types=1);

/**
 * BƯỚC 4.1 — Công cụ quét & đồng bộ BẢN ĐỒ GIAO DIỆN (vc_ai_ui_map).
 *
 * Chạy:
 *   php tools/ai_ui_map_build.php            # seed + quét + báo cáo
 *   php tools/ai_ui_map_build.php --seed-only
 *   php tools/ai_ui_map_build.php --scan-only
 *   php tools/ai_ui_map_build.php --dry-run  # chỉ in thay đổi, không ghi DB
 *
 * Làm gì:
 *   1. SEED  : đổ các trang gốc từ const SiteKnowledge::PAGES (nguồn cũ) —
 *              CHỈ INSERT trang chưa có, KHÔNG đè row admin đã sửa.
 *   2. SCAN  : đọc routes/web.php (GET) -> controller -> $this->render('x.y')
 *              -> file resources/views/x/y.php thật -> trích nhãn nút/link
 *              trên giao diện. Trang chưa có -> thêm (source=scanner); trang
 *              đã có -> chỉ NỐI các nhãn nút mới tìm thấy (không xóa gì).
 *
 * Nguyên tắc: KHÔNG BAO GIỜ xóa row / xóa action — chỉ bổ sung. Muốn ẩn 1 trang
 * thì admin đặt is_active=0 (hoặc bảo AI: "ẩn trang /xyz khỏi bản đồ").
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Ho_Chi_Minh');
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);

function uimap_parse_env(string $root): void
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
        $value = trim($value, "\"'");
        if ($key === '') {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
uimap_parse_env($ROOT);

$autoload = $ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}
spl_autoload_register(static function (string $class) use ($ROOT): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = $ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\AI\Knowledge\SiteKnowledge;
use App\Models\AiUiMap;

$opts = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $opts, true);
$seedOnly = in_array('--seed-only', $opts, true);
$scanOnly = in_array('--scan-only', $opts, true);

echo "BƯỚC 4.1 — Đồng bộ bản đồ giao diện vc_ai_ui_map" . PHP_EOL;
echo 'Root: ' . $ROOT . PHP_EOL;
echo $dryRun ? "MODE: --dry-run (không ghi DB)\n" : '';

$model = new AiUiMap();

// ---------------------------------------------------------------- 1. SEED
$seeded = 0;
if (!$scanOnly) {
    foreach (SiteKnowledge::constPages() as $page) {
        $path = (string) $page['path'];
        if ($model->findByPath($path) !== null) {
            continue; // đã có (kể cả admin sửa tay) -> không đè
        }
        $seeded++;
        if ($dryRun) {
            echo "  [seed] sẽ thêm {$path} — {$page['title']}" . PHP_EOL;
            continue;
        }
        $model->upsertPage(
            $path,
            (string) $page['title'],
            (bool) $page['login'],
            (array) $page['actions'],
            'const'
        );
    }
}

// ---------------------------------------------------------------- 2. SCAN
/**
 * Trích nhãn nút/link THẬT từ một file view.
 *
 * @return array<string,string> {nhãn nút => hành động/href}
 */
function uimap_actions_from_view(string $file): array
{
    $html = @file_get_contents($file);
    if ($html === false) {
        return [];
    }

    $clean = static function (string $raw): string {
        $raw = strip_tags($raw);
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        return $raw;
    };

    $actions = [];

    // <a href="...">Nhãn</a>
    if (preg_match_all('/<a\s[^>]*href\s*=\s*([\'"])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $label = $clean((string) $row[3]);
            $href = trim((string) $row[2]);
            if ($label === '' || mb_strlen($label) < 2 || mb_strlen($label) > 60) {
                continue;
            }
            if (preg_match('/[<>{}]|<\?/', $label) || preg_match('/^\d+$/', $label)) {
                continue;
            }
            $actions[$label] ??= ($href !== '' && preg_match('~^(https?:)?/|^#|^mailto:|^tel:|^javascript:~i', $href) ? $href : 'thao tác trên trang này');
        }
    }

    // <button ...>Nhãn</button>
    if (preg_match_all('/<button([^>]*)>(.*?)<\/button>/is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $label = $clean((string) $row[2]);
            if ($label === '' || mb_strlen($label) < 2 || mb_strlen($label) > 60) {
                continue;
            }
            if (preg_match('/[<>{}]|<\?/', $label) || preg_match('/^\d+$/', $label)) {
                continue;
            }
            $href = '';
            if (preg_match('/href\s*=\s*([\'"])(.*?)\1/i', (string) $row[1], $hm)) {
                $href = trim((string) $hm[2]);
            }
            $actions[$label] ??= ($href !== '' ? $href : 'bấm trên trang này');
        }
    }

    // Cắt bớt: mỗi trang tối đa 40 nhãn (tránh phình map).
    if (count($actions) > 40) {
        $actions = array_slice($actions, 0, 40, true);
    }

    return $actions;
}

/** Lấy thân hàm controller theo tên method (đếm ngoặc để không cắt giữa chừng). */
function uimap_method_body(string $source, string $method): string
{
    $pos = preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $mm, PREG_OFFSET_CAPTURE);
    if ($pos !== 1) {
        return '';
    }
    $start = (int) $mm[0][1];
    $open = strpos($source, '{', $start);
    if ($open === false) {
        return '';
    }
    $depth = 0;
    $len = strlen($source);
    for ($i = $open; $i < $len; $i++) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }
    }
    return '';
}

$routes = [];
$rawRoutes = @file($ROOT . '/routes/web.php', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
foreach ($rawRoutes as $line) {
    if (preg_match("/^\s*'GET\s+(\S+)'\s*=>\s*\[\s*'([^']+)'\s*,\s*'([^']+)'\s*\]/", $line, $m)) {
        $routes[$m[1]] = [$m[2], $m[3]];
    }
}

$publicPaths = [];
foreach (SiteKnowledge::constPages() as $p) {
    if (!$p['login']) {
        $publicPaths[] = $p['path'];
    }
}

$scanned = 0;
$enriched = 0;
$newPages = 0;
$noView = 0;

if (!$seedOnly) {
    foreach ($routes as $path => [$controller, $method]) {
        if (str_starts_with($path, '/admin') || str_contains($path, '/auth/google')) {
            continue; // AI chat KHÔNG mô tả trang quản trị
        }

        $controllerFile = $ROOT . '/app/Controllers/' . str_replace('\\', '/', $controller) . '.php';
        if (!is_file($controllerFile)) {
            $noView++;
            continue;
        }
        $src = (string) @file_get_contents($controllerFile);
        $body = uimap_method_body($src, $method);
        if ($body === '') {
            $noView++;
            continue;
        }
        if (!preg_match("/(?:render|view)\(\s*'([^']+)'/", $body, $vm)) {
            $noView++;
            continue;
        }
        $viewName = str_replace('.', '/', $vm[1]);
        $viewFile = $ROOT . '/resources/views/' . $viewName . '.php';
        if (!is_file($viewFile)) {
            $noView++;
            continue;
        }

        $scanned++;
        $actions = uimap_actions_from_view($viewFile);
        $existing = $model->findByPath($path);

        if ($existing !== null) {
            if ((string) $existing['source'] === 'manual') {
                continue; // admin sửa tay -> tool không được đụng
            }
            $old = json_decode((string) ($existing['actions_json'] ?? '{}'), true);
            if (!is_array($old)) {
                $old = [];
            }
            $added = 0;
            foreach ($actions as $label => $action) {
                if (!isset($old[$label])) {
                    $old[$label] = $action;
                    $added++;
                } elseif (($old[$label] ?? '') === 'thao tác trên trang này' && $action !== 'thao tác trên trang này') {
                    // Sửa lỗi cũ: giá trị placeholder -> href thật (giữ nhãn).
                    $old[$label] = $action;
                    $added++;
                }
            }
            if ($added === 0) {
                continue;
            }
            $enriched++;
            if ($dryRun) {
                echo "  [scan] +{$added} nút cho {$path}" . PHP_EOL;
                continue;
            }
            $model->update((int) $existing['id'], [
                'actions_json' => json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'view_path'    => 'resources/views/' . $viewName . '.php',
                'source'       => 'scanner',
            ]);
            continue;
        }

        // Trang MỚI tìm thấy trong route/view.
        $newPages++;
        $requiresLogin = !in_array($path, $publicPaths, true)
            && !in_array($controller, ['HomeController', 'AuthController'], true);
        $title = pathinfo($path, PATHINFO_FILENAME);
        $title = str_replace(['-', '_'], ' ', $title === '' ? $path : $title);
        $htmlHead = (string) @file_get_contents($viewFile);
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $htmlHead, $tm)) {
            $t = trim(strip_tags($tm[1]));
            if ($t !== '' && mb_strlen($t) <= 191) {
                $title = $t;
            }
        }
        if ($dryRun) {
            echo "  [scan] sẽ thêm {$path} — {$title}" . PHP_EOL;
            continue;
        }
        $model->upsertPage($path, $title, $requiresLogin, $actions, 'scanner', 'resources/views/' . $viewName . '.php');
    }
}

SiteKnowledge::resetPagesCache();
$rows = (new AiUiMap())->getActivePages();

echo PHP_EOL . 'KẾT QUẢ:' . PHP_EOL;
echo "  - seed trang mới từ const   : {$seeded}" . PHP_EOL;
echo "  - route GET đọc được        : " . count($routes) . PHP_EOL;
echo "  - trang quét thấy view thật : {$scanned} (bỏ qua {$noView} route không map được view)" . PHP_EOL;
echo "  - trang mới thêm (scanner)  : {$newPages}" . PHP_EOL;
echo "  - trang cũ được nối thêm nút: {$enriched}" . PHP_EOL;
echo '  - tổng trang ACTIVE trong map: ' . count($rows) . PHP_EOL;
echo 'Xong.' . PHP_EOL;
