<?php
/**
 * Seed prompt files — chạy 1 lần để tạo storage/prompts/*.txt
 * (tương đương bấm "Gieo file prompt" trong Admin AI).
 *
 * Chạy: php tools/seed_prompts.php
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

use App\AI\Core\PromptFileStore;
use App\AI\Core\PromptRegistry;

$registry = new PromptRegistry();
$store    = new PromptFileStore();

echo "Thu muc prompt : " . $store->directory() . "\n";
echo "Truoc khi seed  : " . (($store->keys() === []) ? '(trong)' : implode(', ', $store->keys())) . "\n";

$created = $registry->seedFiles();

echo "Da tao moi     : " . (($created === []) ? '(khong co, tat ca da ton tai)' : implode(', ', $created)) . "\n";
echo "Sau khi seed   : " . implode(', ', $store->keys()) . "\n";

$errors = [];
foreach ($store->keys() as $key) {
    $parsed = $store->read($key);
    if ($parsed === null) {
        $errors[] = $key . ' (doc that bai)';
        continue;
    }
    if ($parsed['system'] === '') {
        $errors[] = $key . ' (system rong)';
    }
    echo sprintf("  - %-18s system=%d ky tu, user=%d ky tu\n", $key, mb_strlen($parsed['system']), mb_strlen($parsed['user']));
}

if ($errors !== []) {
    fwrite(STDERR, 'LOI: ' . implode(', ', $errors) . "\n");
    exit(1);
}

echo "OK: prompt files san sang.\n";
