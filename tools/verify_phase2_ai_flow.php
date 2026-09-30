<?php
/**
 * Kiểm chứng luồng AI MỚI (Phase 2) — không cần network.
 *
 * 1. Prompt đọc từ FILE storage/prompts/{key}.txt (Admin sửa được).
 * 2. Phân biệt nguồn: website (support_chat) vs Facebook (fanpage_comment).
 * 3. Biết khách đã đăng nhập hay chưa + tên người dùng.
 * 4. Prompt file sửa được → AI đọc lại nội dung mới.
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
use App\Services\ChatbotService;

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $extra = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  PASS $label\n";
    } else {
        $fail++;
        echo "  FAIL $label" . ($extra !== '' ? "  → $extra" : '') . "\n";
    }
};

$svc = new ChatbotService();
$ref = new ReflectionMethod(ChatbotService::class, 'resolveSystemPrompt');
if (PHP_VERSION_ID < 80100) {
    $ref->setAccessible(true);
}

$render = static function (array $ctx, bool $fb, bool $logged, string $name) use ($svc, $ref): string {
    return (string) $ref->invoke($svc, $ctx, $fb, $logged, $name);
};

echo "[1] Prompt đọc từ FILE (storage/prompts)\n";
$store = new PromptFileStore();
$check('PromptFileStore có thư mục storage/prompts', str_contains($store->directory(), 'prompts'), $store->directory());
$keys = $store->keys();
$check('Có file support_chat.txt', in_array('support_chat', $keys, true), implode(', ', $keys));
$check('Có file fanpage_comment.txt', in_array('fanpage_comment', $keys, true), implode(', ', $keys));

$supportFile = $store->read('support_chat');
$check('Đọc được nội dung file support_chat', $supportFile !== null && $supportFile['system'] !== '');

echo "[2] Phân biệt nguồn: Website vs Facebook\n";
$webPrompt = $render(['message' => 'Giá gói 1 tháng bao nhiêu?'], false, false, '');
$fbPrompt  = $render(['message' => 'Giá gói 1 tháng bao nhiêu?'], true, false, '');
$check('Prompt website nêu rõ nguồn Website', str_contains($webPrompt, 'Website'), mb_substr($webPrompt, 0, 120));
$check('Prompt Facebook nêu rõ nguồn Facebook', str_contains($fbPrompt, 'Facebook'), mb_substr($fbPrompt, 0, 120));
$check('Hai nguồn cho ra prompt KHÁC nhau', $webPrompt !== $fbPrompt);
$check('Prompt Facebook nói rõ KHÔNG phải chat website', mb_stripos($fbPrompt, 'website') !== false);

echo "[3] Biết khách đã đăng nhập hay chưa + tên người dùng\n";
$guestPrompt = $render(['message' => 'Xin chào'], false, false, '');
$authPrompt  = $render(['message' => 'Xin chào'], false, true, 'NguyenVanA');
$check('Prompt khách vãng lai nói CHƯA đăng nhập', mb_stripos($guestPrompt, 'chưa đăng nhập') !== false);
$check('Prompt khách vãng lai KHÔNG có tên user', !str_contains($guestPrompt, 'NguyenVanA'));
$check('Prompt khách đã đăng nhập chứa TÊN người dùng', str_contains($authPrompt, 'NguyenVanA'), mb_substr($authPrompt, 0, 160));
$check('Prompt đã đăng nhập nói rõ đã ĐĂNG NHẬP', mb_stripos($authPrompt, 'đăng nhập') !== false);
$check('Hai trạng thái login cho ra prompt KHÁC nhau', $guestPrompt !== $authPrompt);

echo "[4] AI đọc lại nội dung file khi Admin sửa\n";
$marker = 'MARKER-' . bin2hex(random_bytes(4));
$backup = $store->read('support_chat');
$before = $render(['message' => 'test'], false, true, 'TesterX');
$store->write('support_chat', $marker . "\n{{user_context}} Nguồn: {{source_label}}", '{{message}}');
$after = $render(['message' => 'test'], false, true, 'TesterX');
$store->write('support_chat', (string) $backup['system'], (string) $backup['user']);
$restored = $render(['message' => 'test'], false, true, 'TesterX');
$check('Prompt sau khi sửa file chứa MARKER mới', str_contains($after, $marker));
$check('Prompt sau khi khôi phục KHÔNG còn MARKER', !str_contains($restored, $marker));
$check('Nội dung file được khôi phục nguyên vẹn', $restored === $before);

echo "[5] Không còn phụ thuộc provider cũ trong prompt\n";
$all = $webPrompt . $fbPrompt . $authPrompt;
$check('Không nhắc OpenRouter/Gemini trong prompt hệ thống', !preg_match('/openrouter|gemini/i', $all));

echo "\n============================================================\n";
echo "KẾT QUẢ: $pass PASS / $fail FAIL\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
