<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\AI\Contracts\AICapability;
use App\AI\Core\AICore;
use App\AI\Core\DirectMediaService;
use App\AI\Core\ModuleRegistry;
use App\AI\Core\ModuleSynchronizer;
use App\AI\Core\PromptRegistry;
use App\AI\Core\TaskRunner;
use App\Controllers\BaseController;
use App\Models\AIModule;
use App\Models\AIModel;

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
     *                                   + publish_post + lịch đăng tự động.
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
            'prompt_keys' => ['image_generation', 'rules_image'],
            'capability'  => 'image',
        ],
        'ai-video' => [
            'title'       => 'Tạo Video Bằng AI',
            'subtitle'    => 'Nhập prompt mô tả video muốn tạo — kết quả hiển thị theo lưới ở Thư Mục Tab public/uploads/ai/video.',
            'module_key'  => 'video_generation',
            'prompt_keys' => ['video_generation', 'rules_video'],
            'capability'  => 'video',
        ],
        'ai-dubbing' => [
            'title'       => 'Tạo Giọng Đọc Bằng AI',
            'subtitle'    => 'Nhập lời thoại cần lồng tiếng, chọn giọng + model — file MP3 tạo xong hiển thị theo lưới ở Thư Mục Tab public/uploads/ai/audio.',
            'module_key'  => 'audio_tts',
            'prompt_keys' => ['audio_tts', 'rules_video_dubbing'],
            'capability'  => 'audio',
        ],
        'ai-fanpage' => [
            'title'       => 'Nội Dung Fanpage',
            'subtitle'    => 'Giao AI viết bài theo chủ đề, theo dõi tiến trình và quản lý Danh Sách Bài Viết (xem, copy prompt, lên lịch đăng).',
            'module_key'  => null,
            'prompt_keys' => ['content_article', 'image_generation', 'publish_post', 'rules_fanpage_content'],
            'capability'  => 'text',
        ],
        'ai-reply' => [
            'title'       => 'Trả Lời Tự Động',
            'subtitle'    => 'Trạng thái auto-reply chat & bình luận, Nội Quy AI và hội thoại khách.',
            'module_key'  => null,
            'prompt_keys' => ['support_chat', 'rules_auto_reply'],
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
            $rows = (new AIModel())->byCapability($capability);
        } catch (\Throwable $e) {
            return [];
        }

        $defaultId = null;
        $config = $this->tabConfig();
        if ($config !== null && ($config['module_row'] ?? null) !== null) {
            $defaultId = (int) ($config['module_row']['default_model_id'] ?? 0) ?: null;
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'model_key'  => (string) ($row['model_key'] ?? ''),
                'model_name' => (string) ($row['model_name'] ?? ($row['model_key'] ?? '')),
                'id'         => (int) ($row['id'] ?? 0),
                'is_default' => $defaultId !== null && (int) ($row['id'] ?? 0) === $defaultId,
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
                'default_model_id' => isset($byKey[$key]['default_model_id']) ? (int) $byKey[$key]['default_model_id'] : null,
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
                'text'    => 'Sinh văn bản',
                'image'   => 'Sinh hình ảnh',
                'video'   => 'Sinh video',
                'audio'   => 'Chuyển thành giọng nói',
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
            'content_article'  => 'Sinh bài viết',
            'image_generation' => 'Sinh hình ảnh',
            'video_generation' => 'Sinh video',
            'audio_tts'        => 'Chuyển văn bản thành giọng nói',
            'fanpage_comment'  => 'Trả lời bình luận fanpage',
            'support_chat'     => 'Hội thoại hỗ trợ khách hàng',
            'publish_post'     => 'Chuẩn bị nội dung đăng bài',
            'rules_video'           => 'Nội quy tạo video',
            'rules_video_dubbing'   => 'Nội quy lời thoại/giọng đọc',
            'rules_image'           => 'Nội quy tạo ảnh',
            'rules_fanpage_content' => 'Nội quy nội dung fanpage',
            'rules_auto_reply'      => 'Nội quy trả lời tự động',
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

        // Bổ sung nhãn prompt thuộc các tab (kể cả key nội quy rules_* chưa
        // có trong ModuleRegistry) để tab hiển thị tiếng Việt.
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
        [$body, $imagePrompt] = $this->splitArticle((string) ($row['content_snapshot'] ?? ''));

        $title = trim((string) ($row['topic'] ?? ''));
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

        $prompt  = mb_substr($content, $at);
        $newline = mb_strpos($prompt, "\n");
        $prompt  = $newline === false ? '' : trim(mb_substr($prompt, $newline));

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
