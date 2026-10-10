<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\AI\Knowledge\AssistantStore;
use App\AI\Knowledge\AdminActionTools;
use App\AI\Assistant\AdminAssistantService;
use App\Models\AiToolCall;

/**
 * Trợ Lý Admin — chat AI riêng cho trang admin (kiểu OpenAI).
 *
 * Lưu trữ: storage/assistant/ (JSONL hội thoại, đính kèm, kế hoạch — tách
 * riêng, tự cắt khi file vượt 1GB). Mọi hành động qua POST + CSRF.
 * Model: chỉ nhận model chat mà API key hiện tại mở khóa.
 */
class AiAssistantController extends AiBaseController
{
    protected string $activeMenu = 'ai-assistant';

    private AssistantStore $store;

    /** @var array<string, string> đuôi ảnh hợp lệ → mime. */
    private const IMAGE_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    /** @var string[] đuôi file văn bản hợp lệ (đưa nguyên văn vào prompt). */
    private const TEXT_TYPES = [
        'txt', 'md', 'csv', 'json', 'log', 'xml', 'yaml', 'yml', 'ini',
        'sql', 'php', 'js', 'ts', 'py', 'html', 'css', 'env', 'conf',
    ];

    private const MAX_FILES        = 5;
    private const MAX_IMAGE_BYTES  = 5242880;  // 5MB
    private const MAX_TEXT_BYTES   = 2097152;  // 2MB

    public function __construct()
    {
        parent::__construct();
        $this->store = new AssistantStore();
    }

    // ------------------------------------------------------------------
    // Trang
    // ------------------------------------------------------------------

    public function index(): void
    {
        // Trang mỏng — bong bóng tự tải dữ liệu qua bootstrap() khi admin mở
        // (xem resources/views/layouts/_assistant_bubble.php).
        $this->render('admin.ai.assistant', [
            'activeMenu' => 'ai-assistant',
        ]);
    }

    /**
     * GET /admin/assistant/bootstrap — nạp một lần khi admin mở bong bóng:
     * lịch sử chat, kế hoạch đã lưu và model chat mà key hiện tại mở khóa.
     */
    public function bootstrap(): void
    {
        $models = $this->modelsByCapability('chat');
        $this->json([
            'ok'            => true,
            'conversations' => $this->store->listConversations(),
            'plans'         => $this->store->listPlans(),
            'models'        => $models,
            'has_model'     => $models !== [],
        ]);
    }

    // ------------------------------------------------------------------
    // Hội thoại — JSON API
    // ------------------------------------------------------------------

    public function listChats(): void
    {
        $this->json(['ok' => true, 'conversations' => $this->store->listConversations()]);
    }

    public function history(): void
    {
        $convId = trim((string) ($_GET['id'] ?? ''));
        $meta = $this->findMeta($convId);
        if ($meta === null) {
            $this->json(['ok' => false, 'error' => 'Hội thoại không tồn tại.'], 404);
        }

        $this->json([
            'ok'       => true,
            'messages' => $this->store->messages($convId),
            'meta'     => $meta,
        ]);
    }

    public function newChat(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $model = $this->allowedModel((string) ($_POST['model'] ?? ''));
        if ($model === '') {
            $this->json(['ok' => false, 'error' => 'Hiện không có model chat nào được API key mở khóa.'], 400);
        }
        $conv = $this->store->createConversation($model);

        $this->json(['ok' => true, 'id' => $conv['id'], 'title' => $conv['title'], 'model' => $model]);
    }

    public function send(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $convId = trim((string) ($_POST['conv_id'] ?? ''));
        if ($this->findMeta($convId) === null) {
            $this->json(['ok' => false, 'error' => 'Hội thoại không tồn tại (hãy bấm "+ Đoạn chat mới).'], 404);
        }

        $model = $this->allowedModel((string) ($_POST['model'] ?? ''));
        if ($model === '') {
            $this->json(['ok' => false, 'error' => 'Hiện không có model chat nào được API key mở khóa.'], 400);
        }
        $message = trim((string) ($_POST['message'] ?? ''));
        // Bối cảnh trang admin mà bong bóng đang mở (đã sanitize + redact phía server)
        $page = $this->sanitizePageContext();

        try {
            $attachments = $this->handleUploads($convId);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        if ($message === '' && $attachments === []) {
            $this->json(['ok' => false, 'error' => 'Hãy nhập nội dung hoặc đính kèm file.']);
        }

        $this->store->updateConversation($convId, ['model' => $model]);

        try {
            $result = $this->assistant()->send($convId, $model, $message, $attachments, $page);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Lỗi xử lý chat: ' . $e->getMessage()]);
        }

        // meta đổi sau append (tự đặt tiêu đề từ tin nhắn đầu, msg_count, updated_at)
        $result['meta'] = $this->findMeta($convId);
        $this->json($result);
    }

    /**
     * Bối cảnh trang admin đang mở do bong bóng gửi kèm (page_url/title/text).
     *
     * Lớp redact phía SERVER (client đã redact trước): chỉ chấp nhận đường dẫn
     * bắt đầu bằng /admin, lọc ký tự điều khiển, ẩn mọi dạng secret phổ biến
     * rồi cắt độ dài — key/mật khẩu không bao giờ lọt vào prompt.
     *
     * @return array{url: string, title: string, text: string}
     */
    private function sanitizePageContext(): array
    {
        $url = trim((string) ($_POST['page_url'] ?? ''));
        $title = trim((string) ($_POST['page_title'] ?? ''));
        $text = (string) ($_POST['page_text'] ?? '');

        if ($url === '' || !str_starts_with($url, '/admin') || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return ['url' => '', 'title' => '', 'text' => ''];
        }
        $url = mb_substr($url, 0, 300);

        $title = strip_tags($title);
        $title = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $title) ?? '';
        $title = mb_substr(trim($title), 0, 200);

        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? $text;
        $text = preg_replace([
            '/sk-[A-Za-z0-9_\-]{8,}/',
            '/ghp_[A-Za-z0-9]{10,}/',
            '/EAAG[A-Za-z0-9]+/',
            '/Bearer\s+[A-Za-z0-9._\-]+/i',
            '/\b(api[_-]?key|secret|token|password)\b\s*[:=]\s*["\']?[^\s"\',;]{4,}/i',
        ], '[BẢO MẬT ẨN]', $text) ?? $text;
        $text = mb_substr($text, 0, 6000);

        return ['url' => $url, 'title' => $title, 'text' => $text];
    }

    public function deleteChat(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $convId = trim((string) ($_POST['id'] ?? ''));
        if (!$this->store->deleteConversation($convId)) {
            $this->json(['ok' => false, 'error' => 'Không xóa được hội thoại.'], 400);
        }

        $this->logActivity('ai_assistant_delete', 'Xóa hội thoại trợ lý admin ' . $convId . '.');
        $this->json(['ok' => true]);
    }

    /** Trả file đính kèm (ảnh xem ngay trong khung chat). */
    public function attachment(): void
    {
        $convId = trim((string) ($_GET['conv'] ?? ''));
        $file = trim((string) ($_GET['file'] ?? ''));
        // View gửi tên file bên trong conv (vd: 001_anh.jpg) — nối prefix đầy đủ;
        // attachmentAbsPath tự chặn ".." và validate realpath nên vẫn an toàn.
        if ($file !== '' && $convId !== '' && !str_starts_with($file, $convId . '/')) {
            $file = $convId . '/' . $file;
        }
        $abs = $this->store->attachmentAbsPath($convId, $file);

        if ($abs === null || !is_file($abs)) {
            http_response_code(404);
            exit;
        }

        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = self::IMAGE_TYPES[$ext] ?? 'text/plain; charset=utf-8';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($abs));
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    // ------------------------------------------------------------------
    // Kế hoạch — lưu file riêng, tách khỏi chat
    // ------------------------------------------------------------------

    public function plans(): void
    {
        $this->json(['ok' => true, 'plans' => $this->store->listPlans()]);
    }

    /**
     * POST /admin/assistant/post/save — lưu bài nháp khi admin bấm "Lưu Bài"
     * trên card xem trước trong đoạn chat.
     */
    public function postSave(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $res = AdminActionTools::savePreview($_POST);
        if (empty($res['ok'])) {
            $this->json(['ok' => false, 'error' => (string) ($res['error'] ?? 'Không lưu được bài.')], 400);
        }
        $this->json([
            'ok'        => true,
            'post_id'   => (int) ($res['post_id'] ?? 0),
            'already'   => !empty($res['already']),
            'link'      => (string) ($res['link'] ?? ''),
            'list_link' => (string) ($res['list_link'] ?? '/admin/posts'),
            'message'   => (string) ($res['message'] ?? 'Đã lưu NHÁP.'),
        ]);
    }

    /**
     * GET /admin/assistant/post/status?ids=a,b,c — trạng thái lưu của các
     * card xem trước (gọi sau khi tải lại lịch sử chat).
     */
    public function postSaveStatus(): void
    {
        $ids = array_filter(array_map('trim', explode(',', (string) ($_GET['ids'] ?? ''))));
        $this->json(AdminActionTools::saveStatus($ids));
    }

    /**
     * POST /admin/assistant/action/confirm — admin bấm "XÁC NHẬN THỰC HIỆN"
     * trên thẻ action-confirm: chạy đúng MỘT lần hành động đã chờ xác nhận.
     */
    public function confirmAction(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $convId = trim((string) ($_POST['conv_id'] ?? ''));
        $token = trim((string) ($_POST['token'] ?? ''));
        if ($this->findMeta($convId) === null) {
            $this->json(['ok' => false, 'error' => 'Hội thoại không tồn tại.'], 404);
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $this->json(['ok' => false, 'error' => 'Mã xác nhận không hợp lệ.'], 400);
        }

        $page = $this->sanitizePageContext();

        try {
            $res = $this->assistant()->confirmAction($convId, $token, $page);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Lỗi thực hiện hành động: ' . $e->getMessage()]);
        }

        if (empty($res['ok'])) {
            $this->logActivity('ai_assistant_action_rejected', 'Trợ lý AI: hành động bị từ chối — ' . (string) ($res['error'] ?? ''));
            $this->json(['ok' => false, 'error' => (string) ($res['error'] ?? 'Không thực hiện được.')], 400);
        }

        $this->logActivity('ai_assistant_action_confirmed', 'Trợ lý AI: admin xác nhận hành động ' . $token);
        $this->json([
            'ok'     => true,
            'result' => $res['result'] ?? [],
            'message' => (string) (($res['result']['message'] ?? null) ?: 'Đã thực hiện.'),
        ]);
    }

    /**
     * POST /admin/assistant/action/cancel — admin bấm "BỎ QUA": huỷ hành động,
     * không chạy bất kỳ thao tác nào.
     */
    public function cancelAction(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $convId = trim((string) ($_POST['conv_id'] ?? ''));
        $token = trim((string) ($_POST['token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $this->json(['ok' => false, 'error' => 'Mã xác nhận không hợp lệ.'], 400);
        }

        try {
            $res = $this->assistant()->cancelAction($convId, $token);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Lỗi bỏ qua hành động: ' . $e->getMessage()]);
        }

        if (empty($res['ok'])) {
            $this->json(['ok' => false, 'error' => (string) ($res['error'] ?? 'Không bỏ qua được.')], 400);
        }

        $this->json(['ok' => true]);
    }

    /**
     * GET /admin/assistant/action/pending?conv_id=… — hành động còn hiệu lực
     * đang chờ xác nhận (UI gắn lại thẻ khi tải lại trang).
     */
    public function pendingActions(): void
    {
        $convId = trim((string) ($_GET['conv_id'] ?? ''));
        $items = [];
        foreach ($this->assistant()->pendingActions($convId) as $e) {
            $items[] = [
                'token'      => (string) ($e['token'] ?? ''),
                'tool'       => (string) ($e['tool'] ?? ''),
                'risk'       => (string) ($e['risk'] ?? ''),
                'summary'    => (string) ($e['summary'] ?? ''),
                'expires_at' => (string) ($e['expires_at'] ?? ''),
                'preview'    => is_array($e['preview'] ?? null) ? $e['preview'] : null,
            ];
        }
        $this->json(['ok' => true, 'items' => $items]);
    }

    /**
     * GET /admin/assistant/tool-calls?conv_id=… — nhật ký thao tác công cụ của
     * một đoạn chat (mới nhất trước) cho Admin theo dõi AI đã làm gì.
     */
    public function toolCalls(): void
    {
        $convId = trim((string) ($_GET['conv_id'] ?? ''));
        if ($this->findMeta($convId) === null) {
            $this->json(['ok' => false, 'error' => 'Hội thoại không tồn tại.'], 404);
        }
        $limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
        try {
            $rows = (new AiToolCall())->forConversation($convId, $limit);
        } catch (\Throwable $e) {
            $rows = [];
        }
        $this->json(['ok' => true, 'items' => $rows]);
    }

    /**
     * GET /admin/assistant/ai-images — danh sách ảnh do AI tạo (tab Tạo Ảnh,
     * public/uploads/ai/image/) để admin chọn đính kèm vào tin nhắn chat.
     */
    public function aiImages(): void
    {
        $items = [];
        foreach ($this->galleryItems('image') as $row) {
            $url = (string) ($row['_url'] ?? '');
            $rel = (string) ($row['relative_path'] ?? '');
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            if ($url === '' || $rel === '' || !isset(self::IMAGE_TYPES[$ext])) {
                continue;
            }
            $items[] = [
                'name'       => basename($rel),
                'url'        => $url,
                'size'       => (int) ($row['size_bytes'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        // Mới nhất trước
        usort($items, static function (array $a, array $b): int {
            return strcmp($b['created_at'], $a['created_at']);
        });
        $this->json(['ok' => true, 'images' => $items]);
    }

    /**
     * Tải báo cáo Excel do trợ lý AI xuất (storage/assistant/reports/*.xlsx).
     * Chỉ cho qua tên file đơn (không có đường dẫn) và đuôi .xlsx.
     */
    public function report(): void
    {
        $file = basename(trim((string) ($_GET['file'] ?? '')));
        if (!preg_match('/^[A-Za-z0-9_\-]{1,80}\.xlsx$/', $file)) {
            http_response_code(404);
            exit;
        }

        $reportsDir = realpath(BASE_PATH . '/storage/assistant/reports');
        $abs = $reportsDir !== false ? realpath($reportsDir . DIRECTORY_SEPARATOR . $file) : false;
        if ($abs === false || !is_file($abs) || !str_starts_with($abs, $reportsDir . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . (string) filesize($abs));
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    public function planView(): void
    {
        $plan = $this->store->readPlan(trim((string) ($_GET['id'] ?? '')));
        if ($plan === null) {
            $this->json(['ok' => false, 'error' => 'Không tìm thấy kế hoạch.'], 404);
        }

        $this->json([
            'ok'      => true,
            'plan'    => (array) ($plan['meta'] ?? []),
            'content' => (string) ($plan['content'] ?? ''),
        ]);
    }

    public function planNew(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $title = trim((string) ($_POST['title'] ?? ''));
        $kind = trim((string) ($_POST['kind'] ?? 'general'));
        $details = trim((string) ($_POST['details'] ?? ''));
        $model = $this->allowedModel((string) ($_POST['model'] ?? ''));
        $reqId = (string) preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_POST['req_id'] ?? '')));
        $convId = trim((string) ($_POST['conv_id'] ?? ''));

        if ($title === '') {
            $this->json(['ok' => false, 'error' => 'Hãy đặt tên cho kế hoạch.']);
        }
        if ($model === '') {
            $this->json(['ok' => false, 'error' => 'Hiện không có model chat nào được API key mở khóa.'], 400);
        }

        try {
            // Huỷ ngay nếu admin đã bấm "Huỷ" trước khi AI kịp chạy (không tốn lượt AI).
            $this->stopIfClientGone();
            if ($reqId !== '' && $this->store->isPlanCancelled($reqId)) {
                exit;
            }
            $result = $this->assistant()->generatePlan($title, $kind, $details, $model);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Lỗi sinh kế hoạch: ' . $e->getMessage()]);
        }

        if (!($result['ok'] ?? false)) {
            $this->json(['ok' => false, 'error' => (string) ($result['error'] ?? 'Không sinh được kế hoạch.')]);
        }

        // Admin bấm "Huỷ" trong lúc AI đang sinh → KHÔNG lưu kế hoạch.
        $this->stopIfClientGone();
        if ($reqId !== '' && $this->store->isPlanCancelled($reqId)) {
            exit; // Beacon hủy đã đến trong lúc AI sinh → bỏ, không lưu.
        }

        $plan = $this->store->savePlan($title, $kind, (string) $result['content'], $reqId);

        // Cancel đến trễ trong lúc đang lưu → gỡ luôn kế hoạch vừa lưu.
        if ($reqId !== '' && $this->store->isPlanCancelled($reqId)) {
            $this->store->deletePlan((string) ($plan['id'] ?? ''));
            exit;
        }

        // Gắn kế hoạch vừa tạo vào hội thoại để AI chat bám sát phiên hiện tại.
        if ($convId !== '' && !empty($plan['id'])) {
            $this->store->updateConversation($convId, ['active_plan' => (string) $plan['id']]);
        }

        $this->logActivity('ai_assistant_plan_new', 'Trợ lý admin tạo kế hoạch "' . $title . '".');
        $this->json(['ok' => true, 'plan' => $plan]);
    }

    /**
     * Admin bấm "Huỷ" khi AI đang sinh kế hoạch → client gửi beacon riêng (không tin được
     * connection_status trên Windows/Apache). Server đánh marker theo req_id; planNew kiểm
     * marker trước/sau lưu → không bao giờ giữ kế hoạch đã bị hủy.
     */
    public function planCancel(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        $reqId = (string) preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_POST['req_id'] ?? '')));
        if ($reqId === '') {
            $this->json(['ok' => false, 'error' => 'Thiếu mã yêu cầu.'], 400);
        }

        // Marker cho lần lưu sắp tới (nếu AI vẫn đang sinh)…
        $this->store->markPlanCancelled($reqId);
        // …và gỡ ngay nếu kế hoạch đã kịp lưu (beacon đến trễ).
        $this->store->deletePlanByReqId($reqId);

        $this->json(['ok' => true]);
    }

    /**
     * Gửi 1 byte heartbeat để nhận diện client đã ngắt kết nối (admin bấm Huỷ).
     * Nếu client đã gone → thoát ngay: không trả JSON, không lưu kế hoạch.
     * (Byte space đầu response vô hại — JSON.parse chấp nhận khoảng trắng.)
     */
    private function stopIfClientGone(): void
    {
        // Cam kết header JSON TRƯỚC khi gửi byte heartbeat (sau đó header không đổi được nữa).
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo ' ';
        $level = ob_get_level();
        for ($i = 0; $i < $level; $i++) {
            @ob_flush();
        }
        @flush();
        if (connection_status() !== CONNECTION_NORMAL) {
            exit;
        }
    }

    public function planDelete(): void
    {
        $this->requirePost();
        $this->requireCsrf();

        if (!$this->store->deletePlan(trim((string) ($_POST['id'] ?? '')))) {
            $this->json(['ok' => false, 'error' => 'Không xóa được kế hoạch.'], 400);
        }

        $this->logActivity('ai_assistant_plan_delete', 'Xóa kế hoạch trợ lý admin.');
        $this->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function assistant(): AdminAssistantService
    {
        return new AdminAssistantService($this->core());
    }

    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Chỉ chấp nhận POST.'], 405);
        }
    }

    private function requireCsrf(): void
    {
        if (!$this->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $this->json(['ok' => false, 'error' => 'CSRF token không hợp lệ.'], 403);
        }
    }

    /** Model bắt buộc phải nằm trong danh sách chat unlocked. */
    private function allowedModel(string $model): string
    {
        $allowed = array_column($this->modelsByCapability('chat'), 'model_key');
        return in_array($model, $allowed, true) ? $model : (string) ($allowed[0] ?? '');
    }

    private function findMeta(string $convId): ?array
    {
        if ($convId === '') {
            return null;
        }
        foreach ($this->store->listConversations() as $meta) {
            if (($meta['id'] ?? '') === $convId) {
                return $meta;
            }
        }
        return null;
    }

    /**
     * Validate + lưu file upload vào storage/assistant/attachments/{conv}/.
     *
     * @return array<int, array{name: string, path: string, text?: string, image_data_uri?: string}>
     * @throws \Throwable báo lỗi cho người dùng (tiếng Việt).
     */
    private function handleUploads(string $convId): array
    {
        $files = $this->flattenUploads($_FILES['files'] ?? null);
        if ($files === []) {
            return [];
        }
        if (count($files) > self::MAX_FILES) {
            throw new \RuntimeException('Tối đa ' . self::MAX_FILES . ' file mỗi lượt gửi.');
        }

        $out = [];
        foreach ($files as $f) {
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('File "' . ($f['name'] ?? '?') . '" upload lỗi.');
            }

            $name = (string) ($f['name'] ?? 'file');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (isset(self::IMAGE_TYPES[$ext])) {
                $size = (int) ($f['size'] ?? 0);
                if ($size > self::MAX_IMAGE_BYTES) {
                    throw new \RuntimeException('Ảnh "' . $name . '" vượt 5MB.');
                }
                $saved = $this->store->saveAttachment($convId, $f, self::IMAGE_TYPES[$ext]);
                $abs = $this->store->attachmentAbsPath($convId, $saved['path']);
                $bytes = $abs !== null ? @file_get_contents($abs) : false;
                if ($bytes === false) {
                    throw new \RuntimeException('Không đọc được ảnh "' . $name . '".');
                }
                $out[] = [
                    'name'          => $saved['name'],
                    'path'          => $saved['path'],
                    'image_data_uri'=> 'data:' . self::IMAGE_TYPES[$ext] . ';base64,' . base64_encode($bytes),
                ];
                continue;
            }

            if (in_array($ext, self::TEXT_TYPES, true)) {
                $size = (int) ($f['size'] ?? 0);
                if ($size > self::MAX_TEXT_BYTES) {
                    throw new \RuntimeException('File "' . $name . '" vượt 2MB.');
                }
                $saved = $this->store->saveAttachment($convId, $f, 'text/plain');
                $text = $this->store->readAttachmentText($convId, $saved['path']);
                if ($text === null) {
                    throw new \RuntimeException('Không đọc được nội dung file "' . $name . '".');
                }
                $out[] = ['name' => $saved['name'], 'path' => $saved['path'], 'text' => $text];
                continue;
            }

            throw new \RuntimeException(
                'File .' . $ext . ' không được hỗ trợ. Chỉ nhận ảnh (jpg/png/webp/gif) và file văn bản ('
                . implode(', ', self::TEXT_TYPES) . ').'
            );
        }

        return $out;
    }

    /**
     * Chuẩn hóa $_FILES (mảng song song hoặc file lẻ) thành danh sách mảng.
     *
     * @param mixed $field
     * @return array<int, array<string, mixed>>
     */
    private function flattenUploads($field): array
    {
        if (!is_array($field) || !isset($field['name'])) {
            return [];
        }

        $names = (array) $field['name'];
        $out = [];
        foreach (array_keys($names) as $i) {
            $out[] = [
                'name'      => (string) $names[$i],
                'tmp_name'  => (string) ($field['tmp_name'][$i] ?? ''),
                'error'     => (int) ($field['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size'      => (int) ($field['size'][$i] ?? 0),
            ];
        }
        return $out;
    }
}
