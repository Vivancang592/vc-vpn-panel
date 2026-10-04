<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\AI\Core\PromptRegistry;

/**
 * AiPromptController — sửa prompt template (trang riêng /admin/ai/prompts/create).
 *
 * Trang DANH SÁCH prompt (/admin/ai/prompts GET) đã BỎ — Nội quy được sửa từ
 * trang Cấu Hình AI (/admin/ai/settings), prompt kỹ thuật sửa từ tab tương ứng.
 *
 * Nguyên tắc (P9: prompt CHỈ lưu FILE, không còn DB):
 *  - Prompt nằm duy nhất tại storage/prompts/{key}.txt (PromptFileStore).
 *  - Khi chưa có file, hệ thống dùng DEFAULT_PROMPTS trong code (PromptRegistry).
 *  - Format file: "=== SYSTEM ===" + system prompt + "=== USER ===" + user template.
 */
final class AiPromptController extends AiBaseController
{
    private function registry(): PromptRegistry
    {
        return new PromptRegistry();
    }

    public function create(): void
    {
        $promptKey = trim((string) ($_GET['prompt_key'] ?? ''));

        if ($promptKey !== '' && !in_array($promptKey, $this->registry()->defaultKeys(), true)) {
            $this->flash('Prompt key không nằm trong danh mục hệ thống.', 'danger', '/admin/ai/settings');
        }

        // Mở trang Nội Quy Hệ Thống không kèm query string: chọn key đầu tiên
        // trước khi nạp template để dropdown và textarea luôn cùng một nội dung.
        if ($promptKey === '') {
            $promptKey = $this->registry()->defaultKeys()[0] ?? '';
        }

        // Nội dung THÔ hiện hành (file → mặc định) để Admin bắt đầu sửa.
        $rawTemplate = $promptKey !== ''
            ? $this->registry()->rawTemplate($promptKey)
            : ['system' => '', 'user' => '', 'source' => 'none'];

        $defaultTemplate = $promptKey !== '' ? $this->registry()->defaultTemplate($promptKey) : null;
        $variables = $promptKey !== '' ? $this->registry()->defaultVariables($promptKey) : [];

        $store = $this->registry()->fileStore();

        // Dropdown liệt kê toàn bộ prompt chức năng (nội quy đã gộp sẵn trong từng file).
        $defaultKeys = $this->registry()->defaultKeys();

        $this->render('admin.ai.prompt-edit', [
            'activeMenu'      => 'ai-settings',
            'pageTitle'       => 'Prompt AI - Quản Trị Hệ Thống',
            'promptKey'       => $promptKey,
            'defaultKeys'     => $defaultKeys,
            'defaultTemplate' => $defaultTemplate,
            'rawTemplate'     => $rawTemplate,
            'filePath'        => $promptKey !== '' ? $store->path($promptKey) : '',
            'fileExists'      => $promptKey !== '' && $store->exists($promptKey),
            'fileModified'    => $promptKey !== '' ? $store->modifiedAt($promptKey) : null,
            'variables'       => $variables,
            'moduleRows'      => $this->moduleRows(),
        ]);
    }

    /**
     * Lưu prompt vào FILE: storage/prompts/{key}.txt (Admin sửa trực tiếp).
     *
     * Prompt admin nhập là tiếng Việt và KHÔNG được chứa API key/secret.
     */
    public function saveFile(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', '/admin/ai/settings');
        }

        $promptKey = trim((string) ($_POST['prompt_key'] ?? ''));
        $system = (string) ($_POST['system_prompt'] ?? '');
        $user = (string) ($_POST['user_template'] ?? '');

        // Lưu xong quay lại đúng trang soạn thảo prompt đó.
        $back = '/admin/ai/prompts/create?prompt_key=' . urlencode($promptKey);

        if (!in_array($promptKey, $this->registry()->defaultKeys(), true)) {
            $this->flash('Prompt key không nằm trong danh mục hệ thống.', 'danger', '/admin/ai/settings');
            return;
        }

        if (trim($system) === '') {
            $this->flash('Vui lòng nhập "Chỉ dẫn cho AI" (system prompt).', 'danger', $back);
            return;
        }

        // Chặn admin dán key/secret vào prompt.
        $store = $this->registry()->fileStore();
        $leak = $store->detectSecret($system . "\n" . $user);
        if ($leak !== null) {
            $this->flash(
                'Prompt chứa nội dung giống API key/secret ("' . $leak . '"). Vui lòng nhập prompt bằng tiếng Việt, KHÔNG dán khoá bảo mật.',
                'danger',
                $back
            );
            return;
        }

        if (!$store->write($promptKey, $system, $user)) {
            $this->flash('Không ghi được file prompt. Kiểm tra quyền ghi thư mục storage/prompts.', 'danger', $back);
            return;
        }

        $this->logActivity('ai_prompt_file_save', sprintf('Lưu prompt %s vào file.', $promptKey));

        $this->flash('Đã lưu prompt "' . $promptKey . '" vào file ' . $store->path($promptKey) . '. AI sẽ đọc ngay từ file này.', 'success', $back);
    }
}
