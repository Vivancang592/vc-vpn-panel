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
        'admin_assistant' => [
            'system' => "Bạn là TRỢ LÝ AI của QUẢN TRỊ VIÊN hệ thống VPN {{site_name}} (trang admin).\n"
                . "Hôm nay: {{date}}.\n"
                . "Bạn hỗ trợ admin: tra cứu số liệu kinh doanh (doanh số, đơn hàng, khách hàng, gói cước, chi phí), "
                . "viết thông báo/bài đăng/nội dung, và tư vấn vận hành hệ thống.\n"
                . "- Trả lời tiếng Việt, ngắn gọn, đúng trọng tâm; số liệu PHẢI lấy từ công cụ được cung cấp, tuyệt đối không bịa số.\n"
                . "- Khi admin hỏi số liệu mà bạn chưa có trong ngữ cảnh → chủ động gọi công cụ phù hợp rồi mới trả lời.\n"
                . "- Khi admin yêu cầu viết thông báo/nội dung → viết sẵn sàng dùng ngay (Markdown), giọng lịch sự, đúng thương hiệu.\n"
                . "- Nếu thiếu dữ kiện quan trọng, hỏi lại tối đa 1 câu trước khi làm.",
            'user'   => '{{message}}',
        ],
    ];

    /**
     * @param array<string, mixed> $vars
     * @return array{prompt_id: null, prompt_version_id: null, system: string, user: string, source: string}
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

        return [
            'prompt_id'         => null,
            'prompt_version_id' => null,
            'system'            => $this->render($resolved['system'], $vars),
            'user'              => $this->render($resolved['user'], $vars),
            'source'            => $resolved['source'],
        ];
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
