<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * AiDubbingController — tab "Lời Thoại / Giọng Đọc" (/admin/ai/dubbing).
 *
 * Chạy TRỰC TIẾP khi admin bấm nút (không xếp hàng đợi): lời thoại →
 * audio_tts → lưu MP3 vào thư mục riêng uploads/ai/audio/dubbing/<date>/.
 * Kết quả lưu thẳng vào thư mục tab (hiển thị dạng lưới ngay trong tab).
 */
final class AiDubbingController extends AiBaseController
{
    protected string $activeMenu = 'ai-dubbing';

    /** Giọng đọc Kira chuẩn OpenAI (SKILL.md — đã xác minh). API /audio/voices trả đủ 6 giọng. */
    private const VOICES = [
        'alloy'   => 'Giọng Bắc - Nữ Kore',
        'echo'    => 'Giọng Bắc - Nam Fenrir',
        'fable'   => 'Giọng Nam - Nữ Puck',
        'onyx'    => 'Giọng Nam - Nam Charon',
        'nova'    => 'Giọng Bắc - Nữ Aoede',
        'shimmer' => 'Giọng Bắc - Nữ Kore',
    ];

    /**
     * Bản đồ voice key → tên file demo giọng đọc CHÍNH THỨC do Kira phát
     * hành (https://kiraai.vn/upload/demo-voices/<Tên>.wav — cùng nguồn với
     * playground voices của họ). Admin NGHE THỬ giọng trước khi tạo lời thoại
     * mà không tốn lượt gọi TTS (API key hiện chưa có quyền model audio → 403).
     */
    private const VOICE_SAMPLE_FILES = [
        'alloy'   => 'Kore',
        'echo'    => 'Fenrir',
        'fable'   => 'Puck',
        'onyx'    => 'Charon',
        'nova'    => 'Aoede',
        'shimmer' => 'Kore',
    ];

    public function index(): void
    {
        $this->render('admin.ai.tab-dubbing', [
            'activeMenu'   => $this->activeMenu,
            'pageTitle'    => 'Lồng Tiếng AI - Quản Trị Hệ Thống',
            'voices'       => self::VOICES,
            'voiceSamples' => $this->voiceSamples(),
            'ttsModels'    => $this->modelsByCapability('audio'),
            'gallery'      => $this->galleryItems('dubbing'),
        ]);
    }

    /**
     * URL file demo giọng đọc đã có sẵn trên đĩa (bỏ qua file thiếu).
     *
     * @return array<string, string> voice key => URL tĩnh /assets/...
     */
    private function voiceSamples(): array
    {
        $samples = [];

        foreach (self::VOICE_SAMPLE_FILES as $voice => $file) {
            $path = BASE_PATH . '/public/assets/audio/voice-samples/' . $file . '.wav';
            if (is_file($path)) {
                $samples[$voice] = '/assets/audio/voice-samples/' . rawurlencode($file) . '.wav';
            }
        }

        return $samples;
    }

    /** Tạo lời thoại ngay khi bấm nút (đồng bộ) → lưu thư mục tab. */
    public function generate(): void
    {
        $back = '/admin/ai/dubbing';

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
        }

        if ($this->moduleRegistry()->get('audio_tts') === null) {
            $this->flash('Module audio_tts chưa được đăng ký trong hệ thống.', 'danger', $back);
            return;
        }

        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            $this->flash('Vui lòng nhập lời thoại cần lồng tiếng.', 'danger', $back);
            return;
        }
        if (mb_strlen($text) > 10000) {
            $this->flash('Lời thoại tối đa 10000 ký tự (hiện ' . mb_strlen($text) . ').', 'danger', $back);
            return;
        }

        $voice = trim((string) ($_POST['voice'] ?? 'alloy'));
        if (!array_key_exists($voice, self::VOICES)) {
            $voice = 'alloy';
        }

        $options = ['voice' => $voice];

        $model = trim((string) ($_POST['model'] ?? ''));
        if ($model === '') {
            $this->flash('Vui lòng chọn Model AI trước khi tạo lời thoại.', 'danger', $back);
            return;
        }
        $options['model'] = $model;

        try {
            $res = $this->media()->generateDubbing($text, $options, (int) ($_SESSION['user_id'] ?? 0));
        } catch (\Throwable $e) {
            $this->logActivity('ai_dubbing_generate_failed', $e->getMessage());
            $this->flash('Lỗi khi tạo lời thoại: ' . $e->getMessage(), 'danger', $back);
            return;
        }

        if (!($res['ok'] ?? false)) {
            $error = (string) ($res['error'] ?? 'lỗi không rõ');
            $this->logActivity('ai_dubbing_generate_failed', $error);
            $this->flash('Tạo lời thoại thất bại: ' . $error, 'danger', $back);
            return;
        }

        $asset = (array) ($res['asset'] ?? []);
        $this->logActivity('ai_dubbing_generate', 'Tạo lời thoại trực tiếp: ' . (string) ($asset['url'] ?? ''));
        // Thành công → gắn anchor: trang tự cuộn xuống cab Thư Mục hiển thị kết quả.
        $this->flash('Tạo lời thoại thành công — kết quả nằm ở Thư Mục Tab Lời Thoại.', 'success', '/admin/ai/dubbing#ai-media-gallery');
    }

    /** Xoá file trong thư mục tab Lời Thoại (bất cứ lúc nào). */
    public function delete(): void
    {
        $this->deleteMediaAsset('dubbing', '/admin/ai/dubbing');
    }
}
