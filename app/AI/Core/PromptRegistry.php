<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AIException;

/**
 * PromptRegistry — lấy prompt template theo module.
 *
 * Thứ tự ưu tiên nguồn prompt (Admin có toàn quyền chỉnh sửa):
 *  1. FILE: storage/prompts/{key}.txt  (Admin sửa trực tiếp, AI đọc từ đây).
 *  2. Fallback: định nghĩa mặc định trong code (DEFAULT_PROMPTS).
 *  KHÔNG còn nguồn DB (P9: hai bảng prompt đã bị DROP).
 *
 * Registry này KHÔNG gọi API, KHÔNG build request, KHÔNG parse response.
 * Nó chỉ trả về cặp (system_prompt, user_template, variables) đã render biến.
 */
final class PromptRegistry
{
    private ?PromptFileStore $files = null;

    /**
     * Kho prompt dạng file (Admin sửa được).
     */
    public function fileStore(): PromptFileStore
    {
        return $this->files ??= new PromptFileStore();
    }

    /**
     * Bản đồ NỘI QUY AI: mỗi nhóm chức năng một "quy định" riêng, AI phải
     * tuân thủ khi làm việc (ghép vào ĐẦU system prompt trước phần kỹ thuật).
     *
     * Key prompt quy định KHÔNG thuộc module nào — chỉ là văn bản quy tắc do
     * Admin chỉnh sửa (file → mặc định). Không module nào trỏ tới chúng.
     */
    private const RULE_PROMPTS = [
        'rules_video' => 'Nội quy tạo video',
        'rules_video_dubbing' => 'Nội quy lời thoại/giọng đọc',
        'rules_image' => 'Nội quy tạo ảnh',
        'rules_fanpage_content' => 'Nội quy nội dung fanpage (bài viết & ảnh)',
        'rules_auto_reply' => 'Nội quy trả lời tự động (chat & bình luận)',
    ];

    /**
     * Gộp nhóm chức năng → key nội quy tương ứng.
     *
     * @return array<string, string> function-group => rules prompt key
     */
    public const FUNCTION_RULES = [
        'video' => 'rules_video',
        'dubbing' => 'rules_video_dubbing',
        'image' => 'rules_image',
        'fanpage_content' => 'rules_fanpage_content',
        'auto_reply' => 'rules_auto_reply',
    ];

    /**
     * Fallback an toàn khi chưa có file prompt nào.
     *
     * @var array<string, array{system: string, user: string}>
     */
    private const DEFAULT_PROMPTS = [
        'content_article' => [
            'system' => 'Bạn là chuyên gia nội dung tiếng Việt cho dịch vụ VPN. Viết rõ ràng, đúng sự thật, không hứa hẹn quá mức.',
            'user'   => "Viết một bài viết về chủ đề sau:\n{{topic}}\n\nTự phân tích chủ đề để chọn góc tiếp cận hấp dẫn, cấu trúc bài rõ ràng (mở bài, thân bài có các ý chính, kết bài có lời kêu gọi hành động phù hợp với dịch vụ VPN).",
        ],
        'image_generation' => [
            'system' => 'Bạn là chuyên gia tạo prompt hình ảnh.',
            'user'   => "Tạo ảnh minh hoạ cho: {{prompt}}",
        ],
        'video_generation' => [
            'system' => 'Bạn là chuyên gia tạo prompt video ngắn.',
            'user'   => "Tạo video cho: {{prompt}}",
        ],
        'audio_tts' => [
            'system' => 'Chuyển văn bản thành giọng nói tiếng Việt tự nhiên.',
            'user'   => '{{text}}',
        ],
        'fanpage_comment' => [
            'system' => "Bạn là nhân viên chăm sóc khách hàng Fanpage của dịch vụ VPN.\n"
                . "Bình luận này đến từ FACEBOOK, KHÔNG phải khung chat trên website.\n"
                . "Trả lời ngắn gọn (dưới 3 câu), lịch sự, đúng trọng tâm, xưng hô thân thiện với khách.\n"
                . "Không cam kết ngoài chính sách, không bịa giá. Nếu không chắc, hướng khách nhắn tin riêng.",
            'user'   => "Tên người bình luận: {{from_name}}\nBình luận của khách: {{comment}}\nNgữ cảnh bài viết: {{post_context}}\nThông tin gói cước đang có:\n{{plans}}",
        ],
        'support_chat' => [
            'system' => "Bạn là trợ lý hỗ trợ khách hàng của dịch vụ VPN.\n"
                . "Nguồn hội thoại: {{source_label}}.\n"
                . "{{user_context}}\n"
                . "Trả lời ngắn gọn, chính xác, thân thiện. Nếu khách đã đăng nhập, xưng hô đúng tên khách.",
            'user'   => '{{message}}',
        ],
        'publish_post' => [
            'system' => 'Bạn chuẩn bị nội dung đăng bài fanpage.',
            'user'   => '{{content}}',
        ],
        // ---- NỘI QUY AI (5 quy định chức năng, không module nào trỏ tới) ----
        'rules_image' => [
            'system' => "NỘI QUY TẠO ẢNH (bắt buộc tuân thủ tuyệt đối):\n"
                . "1. Chỉ soạn prompt ảnh đúng chủ đề admin yêu cầu, không tự ý thêm/bớt chủ đề.\n"
                . "2. Cấm nội dung 18+, bạo lực, chính trị, vi phạm pháp luật Việt Nam hoặc chính sách quảng cáo Facebook/Google.\n"
                . "3. Không chèn số liệu, giá cả, khuyến mãi vào ảnh nếu admin không yêu cầu; chữ trên ảnh tối thiểu và dễ đọc.\n"
                . "4. Mô tả rõ bố cục, tông màu, phong cách (thực tế/minh hoạ), tỷ lệ khung hình phù hợp mục đích đăng tải.\n"
                . "5. Không sử dụng hình ảnh/trade mark có bản quyền; ưu tiên phong cách sạch, chuyên nghiệp cho dịch vụ VPN.",
            'user'   => '',
        ],
        'rules_video' => [
            'system' => "NỘI QUY TẠO VIDEO (bắt buộc tuân thủ tuyệt đối):\n"
                . "1. Chỉ soạn prompt video đúng chủ đề admin yêu cầu, không tự ý thêm chủ đề.\n"
                . "2. Cấm nội dung 18+, bạo lực, vi phạm pháp luật Việt Nam hoặc chính sách quảng cáo Facebook/Google.\n"
                . "3. Không bịa số liệu, giá cả, khuyến mãi, thời hạn ngoài dữ liệu được cung cấp.\n"
                . "4. Không cam kết vượt chính sách hệ thống; không dùng tài sản có bản quyền.\n"
                . "5. Video 15-60 giây, tiếng Việt, mô tả rõ từng cảnh: bối cảnh, hành động, chữ trên màn hình.",
            'user'   => '',
        ],
        'rules_video_dubbing' => [
            'system' => "NỘI QUY LỜI THOẠI/GIỌNG ĐỌC (bắt buộc tuân thủ tuyệt đối):\n"
                . "1. Chỉ xử lý đúng văn bản admin cung cấp, không tự viết thêm hoặc thay đổi nội dung.\n"
                . "2. Cấm nội dung 18+, bạo lực, phỉ báng, vi phạm pháp luật Việt Nam.\n"
                . "3. Câu ngắn, ngắt câu tự nhiên để giọng đọc trôi chảy; giữ nguyên số liệu và thuật ngữ chuyên ngành (VPN, server, IP).",
            'user'   => '',
        ],
        'rules_fanpage_content' => [
            'system' => "NỘI QUY NỘI DUNG FANPAGE — BÀI VIẾT & ẢNH (bắt buộc tuân thủ tuyệt đối):\n"
                . "1. Bài đăng dùng văn bản thuần túy, không markdown; mở đầu thu hút, kết thúc mời khách inbox.\n"
                . "2. Chỉ dùng thông tin giá/khuyến mãi/chính sách trong dữ liệu ngữ cảnh; không bịa, không cam kết vượt chính sách.\n"
                . "3. Cấm nội dung 18+, bạo lực, chính trị, so sánh bẩn đối thủ; tuân thủ chính sách quảng cáo Facebook.\n"
                . "4. Ảnh: mô tả rõ bố cục, tông màu, ít chữ dễ đọc, không dùng hình ảnh có bản quyền.",
            'user'   => '',
        ],
        'rules_auto_reply' => [
            'system' => "NỘI QUY TRẢ LỜI TỰ ĐỘNG — CHAT & BÌNH LUẬN (bắt buộc tuân thủ, ưu tiên cao nhất):\n"
                . "1. Trả lời ngắn gọn, đúng trọng tâm, lịch sự; bình luận 1-2 câu, chat tối đa 3-4 câu.\n"
                . "2. Không dùng markdown; chỉ văn bản thuần túy vì Facebook/web không render markdown.\n"
                . "3. Không bịa giá/chính sách; thiếu thông tin thì mời khách nhắn tin Fanpage hoặc để lại thông tin hỗ trợ 1-1.\n"
                . "4. Phân biệt nguồn Facebook/website; từ chối lịch sự chủ đề ngoài dịch vụ; không yêu cầu mật khẩu/OTP.",
            'user'   => '',
        ],
    ];

    /**
     * @param array<string, mixed> $vars
     * @return array{prompt_id: null, prompt_version_id: null, system: string, user: string, source: string, rules: string}
     */
    public function get(string $module, string $promptKey, array $vars = []): array
    {
        $resolved = $this->fromFile($promptKey) ?? $this->fromDefaults($promptKey);

        if ($resolved === null) {
            throw new AIException(
                'Không tìm thấy prompt cho module "' . $module . '" (prompt_key="' . $promptKey . '").',
                AIException::CONFIG,
                null,
                ['module' => $module, 'prompt_key' => $promptKey]
            );
        }

        $rulesKey = $this->rulesKeyForPrompt($promptKey);
        $rulesText = '';

        if ($rulesKey !== null) {
            $rules = $this->rawRules($rulesKey);

            if (($rules['source'] ?? 'none') !== 'none') {
                // Nội quy đứng TRƯỚC prompt kỹ thuật; phần kỹ thuật giữ nguyên.
                $rulesText = trim((string) $rules['system']);
            }
        }

        return [
            'prompt_id'         => null,
            'prompt_version_id' => null,
            'system'            => $this->render($resolved['system'], $vars),
            'user'              => $this->render($resolved['user'], $vars),
            'source'            => $resolved['source'],
            'rules'             => $this->render($rulesText, $vars),
        ];
    }

    /**
     * Key nội quy AI tương ứng với một prompt kỹ thuật (null nếu không gắn).
     */
    public function rulesKeyForPrompt(string $promptKey): ?string
    {
        return match ($promptKey) {
            'video_generation' => self::FUNCTION_RULES['video'],
            'audio_tts' => self::FUNCTION_RULES['dubbing'],
            'content_article', 'publish_post' => self::FUNCTION_RULES['fanpage_content'],
            'image_generation' => self::FUNCTION_RULES['image'],
            'support_chat', 'fanpage_comment' => self::FUNCTION_RULES['auto_reply'],
            default => null,
        };
    }

    /**
     * Danh sách key nội quy AI (4 quy định chức năng) — dùng cho UI.
     *
     * @return array<string, string> key => nhãn tiếng Việt
     */
    public function rulePrompts(): array
    {
        return self::RULE_PROMPTS;
    }

    /**
     * Nội dung THÔ của một key nội quy (file → mặc định rules_*).
     *
     * @return array{system: string, user: string, source: string}
     */
    public function rawRules(string $rulesKey): array
    {
        $fromFile = $this->fromFile($rulesKey);

        if ($fromFile !== null) {
            return ['system' => $fromFile['system'], 'user' => $fromFile['user'], 'source' => 'file'];
        }

        $tpl = self::RULE_PROMPTS[$rulesKey] ?? null;

        if ($tpl !== null && isset(self::DEFAULT_PROMPTS[$rulesKey])) {
            return [
                'system' => self::DEFAULT_PROMPTS[$rulesKey]['system'],
                'user'   => self::DEFAULT_PROMPTS[$rulesKey]['user'],
                'source' => 'default',
            ];
        }

        return ['system' => '', 'user' => '', 'source' => 'none'];
    }

    /**
     * Đọc prompt từ file.
     *
     * @return array{prompt_id: null, prompt_version_id: null, system: string, user: string, source: string}|null
     */
    private function fromFile(string $promptKey): ?array
    {
        $tpl = $this->fileStore()->read($promptKey);

        if ($tpl === null) {
            return null;
        }

        // File hợp lệ khi có ít nhất system prompt.
        if (trim((string) $tpl['system']) === '' && trim((string) $tpl['user']) === '') {
            return null;
        }

        return [
            'prompt_id'         => null,
            'prompt_version_id' => null,
            'system'            => (string) $tpl['system'],
            'user'              => (string) $tpl['user'],
            'source'            => 'file',
        ];
    }

    /**
     * @return array{prompt_id: null, prompt_version_id: null, system: string, user: string, source: string}|null
     */
    private function fromDefaults(string $promptKey): ?array
    {
        if (!isset(self::DEFAULT_PROMPTS[$promptKey])) {
            return null;
        }

        return [
            'prompt_id'         => null,
            'prompt_version_id' => null,
            'system'            => self::DEFAULT_PROMPTS[$promptKey]['system'],
            'user'              => self::DEFAULT_PROMPTS[$promptKey]['user'],
            'source'            => 'default',
        ];
    }

    /**
     * Thay thế {{variable}}. Biến thiếu → giữ placeholder rỗng (không lộ tên biến).
     *
     * @param array<string, mixed> $vars
     */
    private function render(string $template, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $m) use ($vars): string {
                $name = $m[1];
                if (!array_key_exists($name, $vars)) {
                    return '';
                }
                $value = $vars[$name];
                if (is_array($value) || is_object($value)) {
                    return '';
                }

                return (string) $value;
            },
            $template
        );
    }

    /**
     * @return array<int, string>
     */
    public function defaultKeys(): array
    {
        return array_keys(self::DEFAULT_PROMPTS);
    }

    /**
     * Gieo file prompt mặc định (storage/prompts/{key}.txt) cho key chưa có file.
     *
     * @return array<int, string> danh sách key vừa tạo
     */
    public function seedFiles(): array
    {
        return $this->fileStore()->seed(self::DEFAULT_PROMPTS);
    }

    /**
     * Nội dung prompt THÔ hiện hành (chưa render biến) để Admin xem/sửa.
     *
     * @return array{system: string, user: string, source: string}
     */
    public function rawTemplate(string $promptKey): array
    {
        $fromFile = $this->fromFile($promptKey);

        if ($fromFile !== null) {
            return ['system' => $fromFile['system'], 'user' => $fromFile['user'], 'source' => 'file'];
        }

        $tpl = $this->defaultTemplate($promptKey);

        if ($tpl !== null) {
            return ['system' => $tpl['system'], 'user' => $tpl['user'], 'source' => 'default'];
        }

        return ['system' => '', 'user' => '', 'source' => 'none'];
    }

    /**
     * Tất cả template mặc định (dùng cho seed + UI).
     *
     * @return array<string, array{system: string, user: string}>
     */
    public function defaultPrompts(): array
    {
        return self::DEFAULT_PROMPTS;
    }

    /**
     * Template mặc định (trong code) của một prompt key.
     *
     * API chỉ-đọc (additive) để tầng Admin suy ra danh sách biến đầu vào của
     * module từ CHÍNH template thật, tránh hard-code trùng lặp ở UI.
     *
     * @return array{system: string, user: string}|null
     */
    public function defaultTemplate(string $promptKey): ?array
    {
        if (!isset(self::DEFAULT_PROMPTS[$promptKey])) {
            return null;
        }

        return [
            'system' => self::DEFAULT_PROMPTS[$promptKey]['system'],
            'user'   => self::DEFAULT_PROMPTS[$promptKey]['user'],
        ];
    }

    /**
     * Danh sách biến {{var}} của một prompt key (theo template mặc định).
     *
     * @return array<int, string>
     */
    public function defaultVariables(string $promptKey): array
    {
        $template = $this->defaultTemplate($promptKey);

        if ($template === null) {
            return [];
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $template['system'] . "\n" . $template['user'], $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
