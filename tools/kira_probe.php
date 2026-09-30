<?php
/**
 * PHASE A — Probe contract Kira (CHỈ PHÂN TÍCH, không code production).
 * Chạy: php tools/kira_probe.php
 * In JSON tóm tắt: models (video/audio filter), thử endpoint audio/voices,
 * và probe mở rộng (upload/edit/dub) — KHÔNG chứa key trong output.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$ROOT = dirname(__DIR__);
define('BASE_PATH', $ROOT);
require $ROOT . '/vendor/autoload.php';

use App\Models\Setting;

$settingKey = 'kira_api_key';
// Đọc trực tiếp qua Setting model (BaseModel pattern).
$settingModel = new \App\Models\Setting();
$key = '';
foreach ((array) $settingModel->getAll() as $row) {
    if (($row['setting_key'] ?? '') === $settingKey) {
        $key = (string) ($row['setting_value'] ?? '');
        break;
    }
}
$key = trim($key);
if ($key === '') {
    echo json_encode(['fatal' => 'NO_API_KEY', 'setting_key' => $settingKey], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit(1);
}

$base = rtrim((string) (getenv('KIRA_BASE_URL') ?: 'https://kiraai.vn/api/v1'), '/');

function kira_call(string $method, string $url, string $key, ?array $body = null, int $timeout = 40): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $start = microtime(true);
    $resp  = curl_exec($ch);
    $ms    = (int) round((microtime(true) - $start) * 1000);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $decoded = json_decode((string) $resp, true);
    return [
        'status' => $status,
        'ms'     => $ms,
        'errno'  => $err,
        'body'   => is_array($decoded) ? $decoded : substr((string) $resp, 0, 600),
    ];
}

$out = [];

// 1) GET /models — lọc model video/audio/tts/dub
$models = kira_call('GET', $base . '/models', $key);
$out['models_status'] = $models['status'];
$list = $models['body']['data'] ?? $models['body']['models'] ?? [];
if (is_array($list)) {
    $filtered = [];
    foreach ($list as $m) {
        $id = (string) ($m['id'] ?? $m['model'] ?? '');
        if (preg_match('/video|tts|speech|audio|voice|dub|sora|veo|runway|kling|lip/i', $id)) {
            $filtered[] = [
                'id'          => $id,
                'type'        => $m['type'] ?? null,
                'is_partner'  => $m['is_partner'] ?? null,
                'input_types' => $m['input_types'] ?? null,
                'tags'        => $m['tags'] ?? null,
                'name'        => $m['name'] ?? null,
            ];
        }
    }
    $out['models_total'] = count($list);
    $out['models_video_audio'] = $filtered;
} else {
    $out['models_body'] = $models['body'];
}

// 2) GET /audio/voices
$voices = kira_call('GET', $base . '/audio/voices', $key);
$out['audio_voices'] = ['status' => $voices['status'], 'body' => is_array($voices['body']) ? array_slice($voices['body'], 0, 3) : $voices['body']];

// 3) Probe các endpoint MỚI khả dĩ — POST body rỗng: 404 = KHÔNG tồn tại, 400 = TỒN TẠI (validation)
$probePaths = [
    'POST /videos/edits'         => ['/videos/edits'],
    'POST /audio/transcriptions' => ['/audio/transcriptions'],
    'POST /audio/translations'   => ['/audio/translations'],
    'POST /videos/upload'        => ['/videos/upload'],
    'POST /files'                => ['/files'],
    'POST /uploads'              => ['/uploads'],
    'POST /videos/dub'           => ['/videos/dub'],
    'POST /audio/dub'            => ['/audio/dub'],
    'POST /speech'               => ['/speech'],
];
$probe = [];
foreach ($probePaths as $label => [$p]) {
    $r = kira_call('POST', $base . $p, $key, [], 20);
    $probe[$label] = ['status' => $r['status'], 'body_preview' => is_array($r['body']) ? substr(json_encode($r['body']), 0, 300) : substr((string) $r['body'], 0, 300)];
}
$out['probe_post'] = $probe;

// 3b) GET tĩnh còn lại
$getPaths = ['GET  /user/profile' => '/user/profile'];
foreach ($getPaths as $label => $p) {
    $r = kira_call('GET', $base . $p, $key, null, 20);
    $probe[$label] = ['status' => $r['status'], 'body_preview' => is_array($r['body']) ? substr(json_encode($r['body']), 0, 300) : substr((string) $r['body'], 0, 300)];
}
$out['probe_get'] = $probe;

// 4) POST /videos/generations rỗng — để đọc schema lỗi (VALIDATION) xác nhận input shape
$vg = kira_call('POST', $base . '/videos/generations', $key, [], 30);
$out['video_generations_empty_post'] = ['status' => $vg['status'], 'body' => is_array($vg['body']) ? substr(json_encode($vg['body']), 0, 500) : $vg['body']];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
