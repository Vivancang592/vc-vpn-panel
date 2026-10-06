<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\AI\Contracts\AICapability;
use App\AI\Contracts\AIResult;
use App\AI\Core\AICore;
use App\AI\Core\DirectMediaService;
use App\AI\Core\ModuleRegistry;
use App\AI\Core\ModuleSynchronizer;
use App\AI\Core\PromptRegistry;
use App\AI\Core\TaskRunner;
use App\Controllers\BaseController;
use App\Models\AIModule;
use App\Models\AIModel;
use App\Models\Setting;

/**
 * AiBaseController — base dùng chung cho khu vực Admin AI (/admin/ai/*).
 *
 * Trách nhiệm:
 *  - Cổng quyền: CHỈ admin (đúng pattern của mọi Admin controller hiện có,
 *    dùng lại session sẵn có — KHÔNG tạo hệ authentication mới).
 *  - Nạp `config/ai.php` (config thuần, không secret).
 *  - Cung cấp TaskRunner / AICore / DirectMediaService / ModuleSynchronizer
 *    dùng chung (3 tab media — ảnh/video/lời thoại — chạy TRỰC TIẾP qua
 *    DirectMediaService khi admin bấm nút, không xếp hàng đợi).
 *
 * KHÔNG chứa business logic của module nào, KHÔNG gọi provider trực tiếp.
 * Mọi lời gọi AI đi qua TaskRunner → AICore.
 */
abstract class AiBaseController extends BaseController
{
    /** @var array<string, mixed> */
    protected array $aiConfig = [];

    /** Menu id dùng cho sidebar/tab (mặc định con phải override khi render). */
    protected string $activeMenu = 'ai-dashboard';

    /**
     * Cấu hình 7 tab AI — khớp menu group 'ai' trong layouts/admin.php.
     *
     * Bố cục chức năng:
     *   1. Tổng Quan   (ai-dashboard) — điều phối task/model/output.
     *   2. Tạo Ảnh     (ai-image)     — image_generation, chạy TRỰC TIẾP khi
     *                                   bấm nút (không xếp hàng), thư mục riêng.
     *   3. Tạo Video   (ai-video)     — video_generation, chạy trực tiếp + poll
     *                                   LRO inline, thư mục riêng.
     *   4. Lời Thoại   (ai-dubbing)   — audio_tts, chạy trực tiếp, thư mục riêng.
     *   5. Fanpage     (ai-fanpage)   — hợp nhất content_article + image_generation
     *                                   + lên lịch đăng (cron thuần snapshot, không AI).
     *   6. Trả Lời TĐ  (ai-reply)     — auto-reply chat/bình luận (support_chat +
     *                                   fanpage_comment) — chỉ trạng thái + Nội Quy
     *                                   + hội thoại (D7: KHÔNG form test chat).
     *   7. Cấu Hình    (ai-settings)  — trang kỹ thuật.
     * Trang kỹ thuật (tasks/modules/models/prompts/conversations/outputs/assets)
     * không còn là tab nhưng vẫn truy cập được từ dashboard + menu ngang.
     *
     * - module_key  : module trong vc_ai_modules mà tab vận hành (null = tổng quan/cấu hình).
     * - prompt_keys : prompt thuộc tab (đều đã có trong PromptRegistry::defaultKeys()).
     * - capability  : capability dùng để nạp danh sách model cho dropdown.
     *
     * @var array<string, array<string, mixed>>
     */
    protected const TAB_CONFIGS = [
        'ai-dashboard' => [
            'title'       => 'Trang Tổng Quan',
            'subtitle'    => 'Theo dõi task, model, usage và hoạt động AI theo thời gian thực.',
            'module_key'  => null,
            'prompt_keys' => [],
            'capability'  => null,
        ],
        'ai-image' => [
            'title'       => 'Tạo Ảnh Bằng AI',
            'subtitle'    => 'Nhập prompt mô tả ảnh muốn tạo — kết quả hiển thị theo lưới ở Thư Mục Tab public/uploads/ai/image.',
            'module_key'  => 'image_generation',
            'prompt_keys' => ['image_generation'],
            'capability'  => 'image',
        ],
        'ai-video' => [
            'title'       => 'Tạo Video Bằng AI',
            'subtitle'    => 'Nhập prompt mô tả video muốn tạo — kết quả hiển thị theo lưới ở Thư Mục Tab public/uploads/ai/video.',
            'module_key'  => 'video_generation',
            'prompt_keys' => ['video_generation'],
            'capability'  => 'video',
        ],
        'ai-dubbing' => [
            'title'       => 'Tạo Giọng Đọc Bằng AI',
            'subtitle'    => 'Nhập lời thoại cần lồng tiếng, chọn giọng + model — file MP3 tạo xong hiển thị theo lưới ở Thư Mục Tab public/uploads/ai/audio.',
            'module_key'  => 'audio_tts',
            'prompt_keys' => ['audio_tts'],
            'capability'  => 'audio',
        ],
        'ai-fanpage' => [
            'title'       => 'Nội Dung Fanpage',
            'subtitle'    => 'Giao AI viết bài theo chủ đề, theo dõi tiến trình và quản lý Danh Sách Bài Viết (xem, copy prompt, lên lịch đăng).',
            'module_key'  => null,
            'prompt_keys' => ['content_article', 'image_generation'],
            'capability'  => 'text',
        ],
        'ai-reply' => [
            'title'       => 'Trả Lời Tự Động',
            'subtitle'    => 'Trạng thái auto-reply chat & bình luận, Nội Quy AI và hội thoại khách.',
            'module_key'  => null,
            'prompt_keys' => ['support_chat'],
            'capability'  => null,
        ],
        'ai-settings' => [
            'title'       => 'Cấu Hình AI',
            'subtitle'    => 'Model, prompt, module và cấu hình hệ thống AI.',
            'module_key'  => null,
            'prompt_keys' => [],
            'capability'  => null,
        ],
    ];

    /**
     * Tab lọc của DANH SÁCH BÀI VIẾT (GET ?tab= → nhãn hiển thị).
     * Hiển thị trong tab Nội Dung Fanpage (view tab-fanpage.php) —
     * trước đây nằm trong AiOutputController khi list còn ở /admin/ai/outputs.
     *
     * @var array<string, string>
     */
    protected const OUTPUT_TABS = [
        ''          => 'Tất cả',
        'scheduled' => 'Đang lên lịch',
        'published' => 'Đã đăng',
    ];

    /**
     * Trạng thái bài viết SUY RA từ hàng đợi đăng (label + màu) —
     * dùng cho danh sách bài viết (tab fanpage) + trang chi tiết.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const POST_STATUS = [
        'unscheduled' => ['Chưa lên lịch', 'var(--ios-text-secondary)'],
        'scheduled'   => ['Đang lên lịch', 'var(--ios-blue)'],
        'generating'  => ['Đang đăng', 'var(--ios-warning, #ff9f0a)'],
        'failed'      => ['Lỗi đăng', 'var(--ios-danger)'],
        'published'   => ['Đã đăng', 'var(--ios-success)'],
    ];

    public function __construct()
    {
        parent::__construct();

        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }

        $this->aiConfig = $this->loadAiConfig();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadAiConfig(): array
    {
        $file = BASE_PATH . '/config/ai.php';

        if (!is_file($file)) {
            return [];
        }

        $config = require $file;

        return is_array($config) ? $config : [];
    }

    protected function runner(): TaskRunner
    {
        return new TaskRunner($this->aiConfig);
    }

    protected function core(): AICore
    {
        return new AICore($this->aiConfig);
    }

    /**
     * Dịch vụ chạy trực tiếp media (3 tab ảnh/video/lời thoại).
     */
    protected function media(): DirectMediaService
    {
        return new DirectMediaService($this->aiConfig);
    }

    /**
     * File trong thư mục lưu trữ CỦA TAB hiển thị sẵn ngay trong tab.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function galleryItems(string $tab): array
    {
        try {
            return $this->media()->listByTab($tab);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Xoá 1 asset thuộc tab (unlink file + bản ghi) — quyền xoá bất cứ lúc
     * nào, không phân biệt ref_count/orphan (admin chủ thư mục tab).
     */
    protected function deleteMediaAsset(string $tab, string $back): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
        }

        $assetId = (int) ($_POST['id'] ?? 0);
        if ($assetId <= 0) {
            $this->flash('Không xác định được file cần xoá.', 'danger', $back);
            return;
        }

        try {
            $res = $this->media()->forceDelete($assetId);
        } catch (\Throwable $e) {
            $this->flash('Lỗi xoá file: ' . $e->getMessage(), 'danger', $back);
            return;
        }

        if (!($res['ok'] ?? false)) {
            $this->flash('Không xoá được file: ' . (string) ($res['error'] ?? 'lỗi không rõ'), 'danger', $back);
            return;
        }

        $this->logActivity('ai_media_delete', sprintf('Xoá asset #%d khỏi thư mục tab %s.', $assetId, $tab));
        $this->flash('Đã xoá file khỏi thư mục tab.', 'success', $back);
    }

    /**
     * Danh sách model ACTIVE theo capability cho dropdown (kèm cờ model đang
     * là mặc định của module để preselect đúng gợi ý hệ thống).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function modelsByCapability(string $capability): array
    {
        try {
            // Chỉ model mà API key HIỆN TẠI mở khóa (ẩn model bị key chặn).
            $rows = $this->filterUnlockedModels((new AIModel())->byCapability($capability));
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'model_key'  => (string) ($row['model_key'] ?? ''),
                'model_name' => (string) ($row['model_name'] ?? ($row['model_key'] ?? '')),
                'id'         => (int) ($row['id'] ?? 0),
            ];
        }

        return $out;
    }

    protected function moduleRegistry(): ModuleRegistry
    {
        return new ModuleRegistry();
    }

    /**
     * Danh sách capability MODEL hợp lệ cho một module.
     *
     * Catalog `vc_ai_models` CHỈ có 4 capability: chat / image / video / audio,
     * trong khi module có thêm text / comment / publish (đều chạy qua model chat).
     *
     *  - Module sinh văn bản (text/comment/publish/chat) → nhận model CHAT.
     *  - Module ảnh / video / lời thoại → chỉ nhận ĐÚNG capability tương ứng.
     *
     * Mục đích: chặn gán nhầm chat model (vd: gpt-oss-120b) làm model mặc định
     * cho video_generation / audio_tts → gây lỗi "does not have permission".
     *
     * @return string[]
     */
    protected function modelCapabilitiesForModule(string $moduleCapability): array
    {
        $capability = AICapability::normalize($moduleCapability);

        if ($capability === null) {
            return [];
        }

        if (in_array($capability, [AICapability::TEXT, AICapability::COMMENT, AICapability::PUBLISH], true)) {
            return [AICapability::CHAT, AICapability::TEXT];
        }

        return [$capability];
    }

    /**
     * Lọc danh mục model theo capability hợp lệ của module (dùng cho dropdown).
     *
     * @param array<int, array<string, mixed>> $models
     * @return array<int, array<string, mixed>>
     */
    protected function filterModelsForModule(array $models, string $moduleCapability): array
    {
        $allowed = $this->modelCapabilitiesForModule($moduleCapability);

        if ($allowed === []) {
            return array_values($models);
        }

        $out = [];
        foreach ($models as $model) {
            if (!is_array($model)) {
                continue;
            }

            if (in_array(AICapability::normalize((string) ($model['capability'] ?? '')), $allowed, true)) {
                $out[] = $model;
            }
        }

        // Không bao giờ để dropdown rỗng (catalog chưa có model capability đó).
        return $out === [] ? array_values($models) : $out;
    }

    /**
     * Chuẩn hoá URL quay lại sau khi submit (chỉ chấp nhận path nội bộ bắt
     * đầu bằng /admin/ai — chặn open-redirect).
     */
    protected function safeBack(string $default, string $back): string
    {
        $back = trim($back);

        if ($back !== '' && str_starts_with($back, '/admin/ai/') && !str_contains($back, '//')) {
            return $back;
        }

        return $default;
    }

    /**
     * API key Kira hiện hành (env trước, sau đó vc_settings) — KHÔNG log/echo.
     */
    protected function resolveKiraApiKey(): ?string
    {
        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');
        $config = (array) ($this->aiConfig['providers'][$provider] ?? []);

        $env = getenv((string) ($config['env_key'] ?? 'KIRA_API_KEY'));
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        try {
            $value = (new Setting())->getByKey((string) ($config['setting_key'] ?? 'kira_api_key'));
        } catch (\Throwable $e) {
            return null;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Danh sách model mà key HIỆN TẠI được mở khóa.
     *
     * Probe bằng 1 request chat completion với model giả `__vc_probe__`
     * (max_tokens=1): provider chặn TRƯỚC khi sinh token nên KHÔNG tốn credit
     * và trả 403 kèm "Allowed models: a, b, c." → parse ra danh sách.
     *
     * @return array<int, string>|null  null = không probe được (giữ nguyên cờ cũ)
     */
    protected function fetchKiraAllowedModels(): ?array
    {
        $key = $this->resolveKiraApiKey();

        if ($key === null) {
            return null;
        }

        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');
        $config = (array) ($this->aiConfig['providers'][$provider] ?? []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            return null;
        }

        $ch = curl_init($baseUrl . '/chat/completions');
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode(
                [
                    'model'      => '__vc_probe__',
                    'max_tokens' => 1,
                    'messages'   => [['role' => 'user', 'content' => 'x']],
                ],
                JSON_UNESCAPED_UNICODE
            ),
        ]);

        $body = (string) curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($body, true);
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : $body;

        if (!preg_match('/Allowed models:\s*(.+)$/is', $message, $m)) {
            return null;
        }

        $list = [];
        foreach (explode(',', rtrim((string) $m[1], " .\"'\t\n\r")) as $item) {
            $item = trim($item, " \t\n\r\"'");
            if ($item !== '') {
                $list[] = $item;
            }
        }

        return $list === [] ? null : $list;
    }

    /**
     * Cờ model có được key mở khóa hay không (null = chưa ghi nhận).
     */
    protected function isModelUnlocked(array $model): ?bool
    {
        $limits = $model['limits'] ?? null;

        if (is_string($limits)) {
            $limits = $this->decodeJson($limits);
        }

        if (!is_array($limits) || !array_key_exists('key_unlocked', $limits)) {
            return null;
        }

        return (bool) $limits['key_unlocked'];
    }

    /**
     * Ẩn model mà key KHÔNG mở khóa (giữ model chưa có cờ — fail-open cho
     * lần đầu chưa từng đồng bộ).
     *
     * @param array<int, array<string, mixed>> $models
     * @return array<int, array<string, mixed>>
     */
    protected function filterUnlockedModels(array $models): array
    {
        $out = [];

        foreach ($models as $model) {
            if (!is_array($model)) {
                continue;
            }

            if ($this->isModelUnlocked($model) === false) {
                continue;
            }

            $out[] = $model;
        }

        return array_values($out);
    }

    /**
     * Đồng bộ catalog model từ Kira (`GET /models`) + ghi cờ key mở khóa
     * (`limits.key_unlocked`) cho TỪNG model — nguồn dữ liệu cho bộ lọc dropdown.
     *
     * @return array{ok: bool, message: string, inserted: int, updated: int, unlocked: int}
     */
    protected function syncModelCatalog(): array
    {
        $out = ['ok' => false, 'message' => '', 'inserted' => 0, 'updated' => 0, 'unlocked' => 0, 'retired' => 0];

        if ($this->resolveKiraApiKey() === null) {
            $out['message'] = 'Chưa cấu hình Kira API key nên không thể đồng bộ model từ provider.';
            return $out;
        }

        $result = $this->core()->listModels();

        if (!$result->isOk()) {
            $this->logActivity('ai_model_sync_failed', (string) $result->errorMessage());
            $out['message'] = (string) $result->errorMessage();
            return $out;
        }

        $items = $this->extractModelItems($result);

        if ($items === []) {
            $out['message'] = 'Provider trả về danh sách model rỗng hoặc không đọc được.';
            return $out;
        }

        // Danh sách model key HIỆN TẠI mở khóa (probe 1 request, không tốn token).
        $allowed = $this->fetchKiraAllowedModels();
        $allowedSet = $allowed === null ? null : array_flip($allowed);

        $provider = (string) ($this->aiConfig['default_provider'] ?? 'kira');
        $modelModel = new AIModel();
        $existing = [];

        try {
            foreach ((array) $modelModel->getAll() as $row) {
                if (is_array($row) && (string) ($row['provider'] ?? '') === $provider) {
                    $existing[(string) ($row['model_key'] ?? '')] = $row;
                }
            }
        } catch (\Throwable $e) {
            $existing = [];
        }

        $inserted = 0;
        $updated = 0;
        $unlocked = 0;
        $seen = [];
        $now = date('Y-m-d H:i:s');

        foreach ($items as $item) {
            $modelKey = trim((string) ($item['id'] ?? $item['model'] ?? $item['name'] ?? ''));

            if ($modelKey === '') {
                continue;
            }

            $seen[$modelKey] = true;
            $capability = $this->guessCapability($item, $modelKey);
            $isOpen = $allowedSet === null ? null : isset($allowedSet[$modelKey]);

            if ($isOpen === true) {
                $unlocked++;
            }

            if (isset($existing[$modelKey])) {
                $row = $existing[$modelKey];
                $limits = $this->decodeJson($row['limits'] ?? null);
                $limits['source'] = 'kira_sync';
                $limits['synced_at'] = $now;

                if ($isOpen !== null) {
                    $limits['key_unlocked'] = $isOpen;
                }

                $patch = ['model_name' => $modelKey];

                // Chỉ cập nhật capability nếu bản ghi cũ đang trống.
                if (trim((string) ($row['capability'] ?? '')) === '') {
                    $patch['capability'] = $capability;
                }

                $patch['limits'] = json_encode($limits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $modelModel->update((int) $row['id'], $patch);
                $updated++;
                continue;
            }

            $limits = ['source' => 'kira_sync', 'synced_at' => $now];

            if ($isOpen !== null) {
                $limits['key_unlocked'] = $isOpen;
            }

            $modelModel->create([
                'provider'   => $provider,
                'model_key'  => $modelKey,
                'model_name' => $modelKey,
                'capability' => $capability,
                'is_active'  => 1,
                'limits'     => json_encode($limits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $inserted++;
        }

        // Model CÙNG provider nhưng không còn trong /models hiện tại (provider đã bỏ):
        // đánh dấu key_unlocked=false để không lọt vào dropdown chọn model.
        $retired = 0;
        foreach ($existing as $legacyKey => $row) {
            if (isset($seen[$legacyKey])) {
                continue;
            }

            $limits = $this->decodeJson($row['limits'] ?? null);
            $limits['source'] = 'kira_sync';
            $limits['synced_at'] = $now;
            $limits['key_unlocked'] = false;
            $limits['missing_in_catalog'] = true;
            $modelModel->update((int) $row['id'], ['limits' => json_encode($limits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $retired++;
        }

        $this->logActivity(
            'ai_model_sync',
            sprintf('Đồng bộ model Kira: thêm %d, cập nhật %d, key mở khóa %d, đánh dấu bỏ %d.', $inserted, $updated, $unlocked, $retired)
        );

        $out['ok'] = true;
        $out['inserted'] = $inserted;
        $out['updated'] = $updated;
        $out['unlocked'] = $unlocked;
        $out['retired'] = $retired;
        $out['message'] = sprintf(
            'Đồng bộ model từ Kira thành công: thêm %d, cập nhật %d (key mở khóa %d model'
            . ($retired > 0 ? ', đánh dấu bỏ %d model provider không còn cung cấp' : '') . ').',
            $inserted,
            $updated,
            $unlocked,
            $retired
        );

        return $out;
    }

    /**
     * Đọc danh sách model từ kết quả `GET /models` (content có thể null nên
     * fallback sang raw).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractModelItems(AIResult $result): array
    {
        $content = $result->content;
        $decoded = null;

        if (is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);
        } elseif (is_array($content)) {
            $decoded = $content;
        }

        if (!is_array($decoded)) {
            $decoded = is_array($result->raw) ? $result->raw : [];
        }

        $list = $decoded['data'] ?? $decoded['models'] ?? $decoded;

        if (!is_array($list)) {
            return [];
        }

        // Bỏ khoá không phải danh sách.
        if (isset($list['data']) && is_array($list['data'])) {
            $list = $list['data'];
        }

        $items = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $items[] = ['id' => $item];
                continue;
            }

            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Suy ra capability từ dữ liệu provider trả về (KHÔNG bịa thêm).
     *
     * @param array<string, mixed> $item
     */
    protected function guessCapability(array $item, string $modelKey): string
    {
        foreach (['capability', 'type', 'category', 'modality'] as $field) {
            if (isset($item[$field]) && is_string($item[$field])) {
                $normalized = AICapability::normalize($item[$field]);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $haystack = strtolower($modelKey . ' ' . json_encode($item['output_types'] ?? $item['modalities'] ?? [], JSON_UNESCAPED_UNICODE));

        $rules = [
            'image'   => ['image', 'img', 'vision', 'sd', 'diffusion'],
            'video'   => ['video'],
            'audio'   => ['audio', 'tts', 'speech', 'voice'],
            'comment' => ['comment'],
            'publish' => ['publish'],
            'chat'    => ['chat', 'dialog', 'conversation'],
        ];

        foreach ($rules as $capability => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $capability;
                }
            }
        }

        return AICapability::TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function moduleSynchronizer(): ModuleSynchronizer
    {
        return new ModuleSynchronizer($this->moduleRegistry());
    }

    /**
     * ID định danh worker cho các thao tác chạy tay từ Admin UI.
     */
    protected function adminWorkerId(string $suffix = 'admin'): string
    {
        return sprintf('admin:%s:%s', $suffix, substr(sha1((string) ($_SESSION['username'] ?? 'admin')), 0, 8));
    }

    /**
     * Đặt flash message rồi quay lại trang trước.
     */
    protected function flash(string $message, string $type = 'success', ?string $redirect = null): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;

        $this->redirect($redirect ?? (string) ($_SERVER['HTTP_REFERER'] ?? '/admin/ai'));
    }

    /**
     * Trả về danh sách module (registry code) đã gắn row cấu hình DB (nếu có).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function moduleRows(): array
    {
        $rows = [];

        try {
            $persisted = (new \App\Models\AIModule())->getAll();
        } catch (\Throwable $e) {
            $persisted = [];
        }

        $byKey = [];
        foreach ((array) $persisted as $row) {
            if (is_array($row) && isset($row['module_key'])) {
                $byKey[(string) $row['module_key']] = $row;
            }
        }

        foreach ($this->moduleRegistry()->all() as $key => $meta) {
            $rows[] = [
                'module_key'      => $key,
                'label'           => (string) ($meta['label'] ?? $key),
                'capability'      => (string) ($meta['capability'] ?? ''),
                'prompt_key'      => (string) ($meta['prompt_key'] ?? ''),
                'output_kind'     => (string) ($meta['output_kind'] ?? ''),
                'code_enabled'    => (($meta['enabled'] ?? false) === true),
                'db'              => $byKey[$key] ?? null,
                'id'              => isset($byKey[$key]['id']) ? (int) $byKey[$key]['id'] : null,
                'db_enabled'      => isset($byKey[$key]['is_enabled']) ? ((int) $byKey[$key]['is_enabled'] === 1) : null,
            ];
        }

        return $rows;
    }

    /**
     * Từ điển nhãn tiếng Việt cho toàn bộ khu vực Admin AI.
     *
     * Mục đích: KHÔNG render key/giá trị kỹ thuật thô ra giao diện. Mọi nơi
     * hiển thị capability / module / prompt_key / task_type / provider / nguồn
     * đều tra qua map này, và luôn có fallback về chính giá trị gốc.
     *
     * @return array<string, array<string, string>>
     */
    protected function aiLabelMap(): array
    {
        return [
            'capability' => [
                'text'    => 'Văn bản',
                'image'   => 'Hình ảnh',
                'video'   => 'Video',
                'audio'   => 'Âm thanh',
                'comment' => 'Bình luận',
                'chat'    => 'Hội thoại',
                'publish' => 'Đăng bài',
            ],
            'task_type' => [
                'text'    => 'Tạo bài viết',
                'image'   => 'Tạo hình ảnh',
                'video'   => 'Tạo video',
                'audio'   => 'Tạo giọng nói',
                'chat'    => 'Hội thoại hỗ trợ',
                'comment' => 'Trả lời bình luận',
                'publish' => 'Chuẩn bị đăng bài',
            ],
            'model_source' => [
                'kira_sync' => 'Đồng bộ tự động',
                'manual'    => 'Nhập tay',
                'unknown'   => 'Không rõ',
            ],
            'source' => [
                'web'     => 'Website',
                'fanpage' => 'Facebook',
            ],
            'task_status' => [
                'pending'    => 'Chờ xử lý',
                'queued'     => 'Đã vào hàng đợi',
                'processing' => 'Đang xử lý',
                'retrying'   => 'Chờ thử lại',
                'completed'  => 'Hoàn tất',
                'failed'     => 'Thất bại',
                'cancelled'  => 'Đã huỷ',
            ],
            'activity_type' => [
                'created'             => 'Tạo task',
                'claimed'             => 'Nhận việc',
                'finished'            => 'Kết thúc',
                'provider_error'      => 'Lỗi provider',
                'retry_scheduled'     => 'Lên lịch thử lại',
                'stale_write_blocked' => 'Chặn ghi cũ (fence)',
                'video_lro_wait'      => 'Chờ video LRO',
            ],
            'provider' => [
                'kira' => 'Kira AI',
            ],
        ];
    }

    /**
     * Tra nhãn tiếng Việt theo nhóm + giá trị gốc.
     */
    protected function label(string $group, ?string $value, string $fallback = '—'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        $map = $this->aiLabelMap();

        return $map[$group][$value] ?? $value;
    }

    /**
     * Nhãn tiếng Việt cho module_key (nguồn: ModuleRegistry).
     */
    protected function moduleLabel(string $moduleKey): string
    {
        $meta = $this->moduleRegistry()->all()[$moduleKey] ?? null;

        return is_array($meta) ? (string) ($meta['label'] ?? $moduleKey) : $moduleKey;
    }

    /**
     * Nhãn tiếng Việt thân thiện cho prompt_key.
     */
    protected function promptLabel(string $promptKey): string
    {
        $map = [
            'content_article'  => 'Tạo Bài Viết',
            'image_generation' => 'Tạo Hình Ảnh',
            'video_generation' => 'Tạo Video',
            'audio_tts'        => 'Tạo Giọng Nói',
            'fanpage_comment'  => 'Trả Lời Bình Luận Fanpage',
            'support_chat'     => 'Hội Thoại Hỗ Trợ Khách Hàng',
            'admin_assistant'  => 'Trợ Lý Admin',
        ];

        return $map[$promptKey] ?? $this->moduleLabel($promptKey);
    }

    /**
     * Gói nhãn truyền xuống view: `$aiLabels` (tra cứu trực tiếp trong view)
     * cùng các map module/prompt đã dựng sẵn.
     *
     * @return array<string, mixed>
     */
    protected function aiLabels(): array
    {
        $moduleLabels = [];
        $promptLabels = [];

        foreach ($this->moduleRegistry()->all() as $key => $meta) {
            $key                = (string) $key;
            $moduleLabels[$key] = (string) ($meta['label'] ?? $key);
            $promptLabels[$key] = $this->promptLabel($key);
        }

        // Bổ sung nhãn prompt thuộc các tab (chưa có trong ModuleRegistry)
        // để tab hiển thị tiếng Việt.
        foreach (self::TAB_CONFIGS as $tabCfg) {
            foreach ((array) $tabCfg['prompt_keys'] as $key) {
                $key = (string) $key;
                if (!isset($promptLabels[$key])) {
                    $promptLabels[$key] = $this->promptLabel($key);
                }
            }
        }

        return [
            'map'     => $this->aiLabelMap(),
            'modules' => $moduleLabels,
            'prompts' => $promptLabels,
        ];
    }

    /**
     * Cấu hình tab theo activeMenu (null nếu không phải 1 trong 7 tab AI).
     *
     * Bổ sung runtime: `module_row` (dòng vc_ai_modules, null nếu chưa sync),
     * `prompt_available` (key => bool theo PromptRegistry::defaultKeys()),
     * `models` (dropdown model active theo capability của tab).
     *
     * @return array<string, mixed>|null
     */
    protected function tabConfig(?string $menu = null): ?array
    {
        $menu = $menu !== null && $menu !== '' ? $menu : $this->activeMenu;

        if (!isset(self::TAB_CONFIGS[$menu])) {
            return null;
        }

        $config = self::TAB_CONFIGS[$menu];

        $registry      = new PromptRegistry();
        $defaultKeys   = $registry->defaultKeys();
        $availability  = [];
        foreach ((array) $config['prompt_keys'] as $key) {
            $availability[(string) $key] = in_array((string) $key, $defaultKeys, true);
        }
        $config['prompt_available'] = $availability;

        $config['module_row'] = null;
        if (!empty($config['module_key'])) {
            try {
                $config['module_row'] = (new AIModule())->findByKey((string) $config['module_key']);
            } catch (\Throwable $e) {
                $config['module_row'] = null;
            }
        }

        // Bỏ lọc theo capability: hiển TẤT CẢ model đang active theo catalog
        // (theo key) để admin tự chọn — không suy đoán khả năng.
        $config['models'] = [];
        $config['model_keys'] = [];
        try {
            $modelModel = new AIModel();
            $config['models'] = $modelModel->allActive();
            foreach ($config['models'] as $m) {
                $key = trim((string) ($m['model_key'] ?? ''));
                if ($key !== '') {
                    $config['model_keys'][] = $key;
                }
            }
        } catch (\Throwable $e) {
            $config['models'] = [];
            $config['model_keys'] = [];
        }

        return $config;
    }

    /**
     * Chuẩn bị 1 dòng bài viết cho view: tiêu đề, thân bài, prompt ảnh, trạng thái.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function decorate(array $row): array
    {
        // Thay {domain} TRƯỚC khi tách khối: body, image_prompt và cả title
        // (lấy từ topic/firstLine) đều ra URL dùng được ngay khi hiển thị
        // hay đưa vào hàng đợi đăng bài (helper ở BaseController).
        $rawContent    = $this->replaceDomainMacro((string) ($row['content_snapshot'] ?? ''));
        [$body, $imagePrompt] = $this->splitArticle($rawContent);

        $title = $this->replaceDomainMacro(trim((string) ($row['topic'] ?? '')));
        if ($title === '') {
            $title = $this->firstLine($body);
        }
        if ($title === '') {
            $title = 'Bài viết #' . (int) ($row['id'] ?? 0);
        }

        $row['title']        = mb_substr($title, 0, 140);
        $row['body']         = $body;
        $row['image_prompt'] = $imagePrompt;
        $row['post_status']  = $this->deriveStatus($row);

        return $row;
    }

    /**
     * Trạng thái bài SUY RA từ dòng hàng đợi đăng tương ứng.
     *
     * @param array<string, mixed> $row
     */
    protected function deriveStatus(array $row): string
    {
        if ((int) ($row['schedule_id'] ?? 0) <= 0) {
            return 'unscheduled';
        }

        return match ((string) ($row['schedule_status'] ?? '')) {
            'published'  => 'published',
            'failed'     => 'failed',
            'generating',
            'publishing' => 'generating',
            default      => 'scheduled',
        };
    }

    /**
     * Tách bài viết thành (thân bài, prompt tạo ảnh).
     *
     * Bài AI viết gồm 2 khối: nội dung đăng + khối prompt ảnh ở CUỐI bài
     * (dùng sinh ảnh minh họa). Khối prompt KHÔNG được đưa lên Fanpage.
     *
     * AI không phải lúc nào cũng viết đúng marker "ẢNH MINH HOẠ:" — tùy
     * nội quy nó có thể ra "Mô tả ảnh minh họa:", "Prompt ảnh:...". Nếu bám
     * cứng 1 chuỗi thì `image_prompt` rỗng → view ẩn mất nút "Copy prompt
     * tạo ảnh" dù bài VẪN có phần mô tả ảnh. Nên nhận diện nhiều biến thể.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitArticle(string $content): array
    {
        if (trim($content) === '') {
            return ['', ''];
        }

        // Nhận dòng BẮT ĐẦU bằng marker (cho phép bullet/số thứ tự phía trước)
        // + mọi biến thể thường gặp của "ẢNH MINH HOẠ" / "Mô tả ảnh".
        $pattern = '/^[ \t]*(?:[-*•]\s*)?(?:\d+\s*\.\s*)?'
            . '(?:ẢNH\s+MINH\s+HOẠ|MÔ\s+TẢ\s+ẢNH(?:\s+MINH\s+HOẠ)?|PROMPT\s+ẢNH(?:\s+TẠO\s+ẢNH)?)'
            . '[^\r\n]*/miu';

        $count = preg_match_all($pattern, $content, $hits, PREG_OFFSET_CAPTURE);
        if (!is_int($count) || $count < 1 || $hits[0] === []) {
            return [trim($content), ''];
        }

        // Prompt luôn nằm ở CUỐI bài → chỉ nhận marker trong nửa sau của bài
        // và lấy vị trí CUỐI CÙNG, tránh cắt nhầm khi thân bài nhắc "ảnh minh họa".
        $minPos = (int) floor(strlen($content) * 0.4);
        $pos    = -1;
        foreach ($hits[0] as $hit) {
            if ($hit[1] >= $minPos && $hit[1] > $pos) {
                $pos = (int) $hit[1];
            }
        }
        if ($pos < 0) {
            return [trim($content), ''];
        }

        // PREG_OFFSET_CAPTURE trả về offset THEO BYTE → đổi sang ký tự cho mb_substr.
        $at = mb_strlen(substr($content, 0, $pos), 'UTF-8');

        $body = trim(mb_substr($content, 0, $at));
        // Bỏ dấu phân cách "---" còn sót ngay trước khối prompt.
        $body = trim((string) preg_replace('/\s*-{3,}\s*\z/u', '', $body));

        $prompt = mb_substr($content, $at);
        $newline = mb_strpos($prompt, "\n");
        if ($newline !== false) {
            // Bỏ dòng marker, giữ toàn bộ prompt phía sau nó.
            $prompt = trim(mb_substr($prompt, $newline));
        } else {
            // Khối prompt nằm TRÊN MỘT DÒNG DUY NHẤT (marker + mô tả cùng dòng,
            // không có xuống dòng) — bỏ tiền tố marker chứ không được bỏ hết prompt.
            $prompt = trim((string) preg_replace(
                '/^[ \t]*(?:[-*•]\s*)?(?:\d+\s*\.\s*)?(?:ẢNH\s+MINH\s+HOẠ|MÔ\s+TẢ\s+ẢNH(?:\s+MINH\s+HOẠ)?|PROMPT\s+ẢNH(?:\s+TẠO\s+ẢNH)?)\s*[:：\-]?\s*/iu',
                '',
                (string) $prompt,
                1
            ));
        }

        return [$body, $prompt];
    }

    /**
     * Dòng đầu tiên không rỗng (dùng làm tiêu đề khi task không có topic).
     */
    protected function firstLine(string $text): string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                return $line;
            }
        }

        return '';
    }

    /**
     * Tự động bơm `$aiLabels` vào MỌI view trong khu vực Admin AI để giao diện
     * luôn hiển thị tiếng Việt thay vì key kỹ thuật. Tab cũng bơm `$tabConfig`
     * (mảng cấu hình tab hoặc null) cho component `_tab-header.php`.
     */
    protected function render(string $view, array $data = []): void
    {
        if (!isset($data['aiLabels'])) {
            $data['aiLabels'] = $this->aiLabels();
        }

        if (!array_key_exists('tabConfig', $data)) {
            $data['tabConfig'] = $this->tabConfig($data['activeMenu'] ?? $this->activeMenu);
        }

        parent::render($view, $data);
    }

    /**
     * Nhãn + màu cho trạng thái task (chỉ hiển thị; giá trị nằm trong ENUM DB).
     */
    protected function taskStatusMeta(string $status): array
    {
        $map = [
            'pending'    => ['Chờ xử lý', 'var(--ios-text-secondary)'],
            'queued'     => ['Đã vào hàng đợi', 'var(--ios-blue)'],
            'processing' => ['Đang xử lý', 'var(--ios-warning, #ff9f0a)'],
            'retrying'   => ['Chờ thử lại', 'var(--ios-warning, #ff9f0a)'],
            'completed'  => ['Hoàn tất', 'var(--ios-success)'],
            'failed'     => ['Thất bại', 'var(--ios-danger)'],
            'cancelled'  => ['Đã huỷ', 'var(--ios-text-secondary)'],
        ];

        return $map[$status] ?? [$status, 'var(--ios-text-secondary)'];
    }
}
