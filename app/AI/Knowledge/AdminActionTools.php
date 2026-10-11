<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

use App\AI\Core\DirectMediaService;
use App\Models\AIAsset;
use App\Models\AIOutput;
use App\Models\Order;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Services\CampaignService;
use App\Support\XlsxWriter;

/**
 * Công cụ HÀNH ĐỘNG của Trợ Lý Admin (protocol JSON text như AdminStatsTools):
 * - post_draft       : soạn bài + xem trước TRONG ĐOẠN CHAT, admin bấm "Lưu Bài" mới ghi nháp
 *   - user_lookup      : tìm user để chọn người nhận mail
 *   - campaign_create  : lập lịch chiến dịch email/Fanpage (cron gửi dần)
 *   - campaign_list    : xem chiến dịch + tiến độ
 *   - campaign_status  : pause/resume/cancel
 *   - revenue_report   : xuất báo cáo doanh thu Excel (.xlsx) kèm link tải
 *   - image_list       : liệt kê ảnh trong thư mục AI (public/uploads/ai)
 *   - image_delete     : xóa 1 ảnh TRONG thư mục AI (destructive, cần xác nhận)
 *   - post_list        : liệt kê bài AI + lịch đăng (lấy output_id)
 *   - post_schedule    : lên lịch bài AI đăng Fanpage (ghi vc_scheduled_posts, cần xác nhận)
 *   - topics_stage     : gắn card chủ đề vào chat → admin bấm chuyển sang tab Nội Dung
 *
 * Tool chỉ TẠO/LẬP LỊCH — việc gửi thật do cron (CampaignService) đảm nhiệm.
 */
final class AdminActionTools
{
    /** Đuôi file ảnh hợp lệ — mirror IMAGE_TYPES của AiAssistantController (const đó là private). */
    private const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Thư mục do AI/hệ thống tạo ra — ảnh xóa được PHẢI nằm trong đây. */
    private const AI_IMAGE_DIR = 'public/uploads/ai/';

    /** @var array<string, string> mô tả từng tool cho system prompt. */
    private const TOOLS = [
        'post_draft'      => 'Soạn bài viết CHƯA lưu: trả về bản xem trước hiển thị TRONG ĐOẠN CHAT cho admin xem trước, admin bấm "Lưu Bài" mới ghi thành bài nháp (tool KHÔNG ghi database). args: {title: string, content: string (Markdown/HTML), type: "news"|"tutorial"|"faq"|"popup", slug?: string}.',
        'user_lookup'     => 'Tìm tài khoản user (id, username, email) để chọn người nhận mail. args: {q?: string (tên hoặc email — để trống lấy tối đa 50).}',
        'campaign_create' => 'Lập lịch chiến dịch gửi theo ngày (cron gửi dần, CHỐNG GỬI TRÙNG trong mỗi chu kỳ). args: {kind: "email"|"fb", title, subject (bắt buộc khi email), body (nội dung đã soạn), target_mode: "all_users"|"all_fans"|"specific"|"old_customers", user_ids?: int[], emails?: string[], psids?: string[], daily_limit?: int (1-500, mặc định 50), recurrence?: "once"|"monthly" (mặc định once), day_of_month?: int (1-31, ngày chạy khi monthly), personalize?: bool (true = AI soạn lại mỗi chu kỳ + điền {ten}/{goi_cu}/{uu_dai} thật)}.',
        'campaign_list'   => 'Liệt kê chiến dịch + trạng thái + số đã gửi. args: {}.',
        'campaign_status' => 'Tạm dừng/tiếp tục/hủy chiến dịch. args: {campaign_id: int, action: "pause"|"resume"|"cancel"}.',
        'revenue_report'  => 'Xuất báo cáo doanh thu tháng ra file Excel (.xlsx) tải về được. args: {year?: int, month?: int}.',
        'image_list'      => 'Liệt kê ảnh thư mục AI. args:{limit?}',
        'image_delete'    => 'Xóa ảnh trong thư mục AI. args:{asset_id}',
        'post_list'       => 'Liệt kê bài AI + lịch. args:{filter?:scheduled|published}',
        'post_schedule'   => 'Lên lịch bài AI lên Fanpage. args:{output_id,date:YYYY-MM-DD,time:HH:MM,image_url?}',
        'topics_stage'    => 'Card chủ đề → tab Nội Dung, không ghi DB. args:{topics:[...]}',
    ];

    /** Danh sách tool + mô tả — nguồn duy nhất cho ToolRegistry. @return array<string,string> */
    public static function toolDescriptions(): array
    {
        return self::TOOLS;
    }

    /** Khối mô tả protocol nhúng vào system prompt lúc runtime. */
    public static function protocolBlock(): string
    {
        $lines = [
            "\n### CÔNG CỤ HÀNH ĐỘNG (bài viết, chiến dịch gửi, báo cáo Excel)",
            'Khi admin yêu cầu hành động (đăng bài, gửi mail/tin Fanpage theo lịch, xuất báo cáo) → trả lời CHỈ bằng MỘT JSON trong code block ```json:',
            '{"tool": "<ten_tool>", "args": {...}}',
            'Các tool hợp lệ:',
        ];
        foreach (self::TOOLS as $name => $desc) {
            $lines[] = "- {$name}: {$desc}";
        }
        $lines[] = '- campaign_create KHÔNG gửi ngay: hệ thống cron gửi dần mỗi ngày tối đa daily_limit người, mỗi người chỉ nhận MỘT LẦN MỖI CHU KỲ (chống trùng theo cycle_no). recurrence="monthly" → lặp hằng tháng vào day_of_month (hệ thống tự gọn 31 về cuối tháng), KHÔNG BAO GIỜ tự done; target_mode="old_customers" → khách đã hết hạn/không hoạt động (remarketing FB), đã lọc opt-out + cửa sổ 7 ngày của Meta. Sau khi tạo, trả lời admin: tập khách, nhịp gửi, số người nhận, daily_limit, số ngày/ngày chạy dự kiến.';
        $lines[] = '- post_draft CHỈ soạn + hiển thị bài cho admin xem TRƯỚC trong đoạn chat (CHƯA ghi database). Khi tool trả preview=true → trả lời ĐÚNG MỘT CÂU: "Bài viết đã soạn xong và hiển thị ngay bên dưới — admin bấm **Lưu Bài** để lưu nháp." TUYỆT ĐỐI KHÔNG nói "đã lưu", KHÔNG chèn link, KHÔNG lặp lại nội dung bài.';
        $lines[] = '- revenue_report trả về link TƯƠNG ĐỐI dạng /admin/assistant/report?file=... — hãy chèn vào câu trả lời Markdown dạng [Tải file Excel](/admin/assistant/report?file=...) (KHÔNG ghép domain).';
        return implode("\n", $lines);
    }

    public static function handles(string $tool): bool
    {
        return array_key_exists($tool, self::TOOLS);
    }

    /**
     * Chạy tool theo tên — trả về mảng kết quả (service json_encode cho AI).
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function dispatch(string $tool, array $args): array
    {
        if (!array_key_exists($tool, self::TOOLS)) {
            return ['ok' => false, 'error' => 'Unknown tool: ' . $tool];
        }

        try {
            switch ($tool) {
                case 'post_draft':
                    return self::postDraft($args);
                case 'user_lookup':
                    return self::userLookup((string) ($args['q'] ?? ''));
                case 'campaign_create':
                    return (new CampaignService())->create($args);
                case 'campaign_list':
                    return (new CampaignService())->list();
                case 'campaign_status':
                    return (new CampaignService())->status((int) ($args['campaign_id'] ?? 0), (string) ($args['action'] ?? ''));
                case 'revenue_report':
                    return self::revenueReport(
                        isset($args['year']) ? (int) $args['year'] : null,
                        isset($args['month']) ? (int) $args['month'] : null
                    );
                case 'image_list':
                    return self::imageList((int) ($args['limit'] ?? 30));
                case 'image_delete':
                    return self::imageDelete($args);
                case 'post_list':
                    return self::postList((string) ($args['filter'] ?? ''));
                case 'post_schedule':
                    return self::postSchedule($args);
                case 'topics_stage':
                    return self::topicsStage($args);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Lỗi công cụ: ' . $e->getMessage()];
        }

        return ['ok' => false, 'error' => 'Unknown tool: ' . $tool];
    }

    // -----------------------------------------------------------------
    // Tools
    // -----------------------------------------------------------------

    /**
     * Soạn bài MỚI — CHƯA ghi database: trả payload xem trước để hệ thống
     * HIỂN THỊ bài trong đoạn chat; admin bấm "Lưu Bài" → savePreview()
     * mới ghi nháp vào vc_posts.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private static function postDraft(array $args): array
    {
        $title = trim((string) ($args['title'] ?? ''));
        $content = trim((string) ($args['content'] ?? ''));
        if ($title === '' || $content === '') {
            return ['ok' => false, 'error' => 'Thiếu title hoặc content.'];
        }

        $type = (string) ($args['type'] ?? 'news');
        if (!in_array($type, ['news', 'tutorial', 'faq', 'popup'], true)) {
            return ['ok' => false, 'error' => 'type phải là: news | tutorial | faq | popup.'];
        }

        $slug = self::slugify($title);
        $custom = trim((string) ($args['slug'] ?? ''));
        if ($custom !== '') {
            $slug = self::slugify($custom);
        }

        return [
            'ok'         => true,
            'preview'    => true,
            'preview_id' => bin2hex(random_bytes(8)),
            'title'      => $title,
            'content'    => $content,
            'type'       => $type,
            'slug'       => $slug,
            'message'    => 'Bài đã soạn xong và hiển thị trong đoạn chat cho admin xem trước — CHƯA lưu. '
                . 'Admin bấm "Lưu Bài" mới ghi thành bài nháp.',
        ];
    }

    /**
     * Lưu bài nháp KHI admin bấm "Lưu Bài" trên card xem trước trong đoạn chat.
     * Idempotent theo preview_id: bấm lại/lưu lại cùng bài KHÔNG tạo trùng.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function savePreview(array $args): array
    {
        $previewId = trim((string) ($args['preview_id'] ?? ''));
        if (!preg_match('/^[a-f0-9]{16}$/', $previewId)) {
            return ['ok' => false, 'error' => 'Mã bài xem trước không hợp lệ.'];
        }

        $map = self::loadSaveMap();
        if (isset($map[$previewId]['post_id'])) {
            $pid = (int) $map[$previewId]['post_id'];
            return [
                'ok'      => true,
                'post_id' => $pid,
                'already' => true,
                'link'    => '/admin/posts/edit?id=' . $pid,
                'message' => 'Bài này đã được lưu trước đó (không tạo trùng).',
            ];
        }

        $title = trim((string) ($args['title'] ?? ''));
        $content = trim((string) ($args['content'] ?? ''));
        if ($title === '' || $content === '') {
            return ['ok' => false, 'error' => 'Thiếu title hoặc content.'];
        }

        $type = (string) ($args['type'] ?? 'news');
        if (!in_array($type, ['news', 'tutorial', 'faq', 'popup'], true)) {
            return ['ok' => false, 'error' => 'type phải là: news | tutorial | faq | popup.'];
        }

        $slug = self::slugify($title);
        $custom = trim((string) ($args['slug'] ?? ''));
        if ($custom !== '') {
            $slug = self::slugify($custom);
        }
        $slug = self::uniqueSlug($slug);

        $authorId = (int) ($_SESSION['user_id'] ?? 0);
        if ($authorId <= 0) {
            $authorId = 1;
        }

        $post = new Post();
        $ok = $post->create([
            'author_id'  => $authorId,
            'title'      => $title,
            'slug'       => $slug,
            'content'    => $content,
            'type'       => $type,
            'status'     => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Ghi database vc_posts thất bại.'];
        }

        $pdo = Order::getPdo();
        $id = (int) $pdo->lastInsertId();

        $map[$previewId] = ['post_id' => $id, 'saved_at' => date('Y-m-d H:i:s')];
        self::saveSaveMap($map);

        return [
            'ok'        => true,
            'post_id'   => $id,
            'already'   => false,
            'status'    => 'draft',
            'type'      => $type,
            'slug'      => $slug,
            'link'      => '/admin/posts/edit?id=' . $id,
            'list_link' => '/admin/posts',
            'message'   => 'Đã lưu NHÁP — bài đã vào Danh Sách Bài Viết.',
        ];
    }

    /**
     * Tra trạng thái lưu của các card xem trước (dùng khi tải lại lịch sử chat).
     *
     * @param string[] $ids
     * @return array<string, mixed>
     */
    public static function saveStatus(array $ids): array
    {
        $map = self::loadSaveMap();
        $saved = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
                continue;
            }
            if (isset($map[$id]['post_id'])) {
                $saved[$id] = (int) $map[$id]['post_id'];
            }
        }
        return ['ok' => true, 'saved' => $saved];
    }

    /** @return array<string, array<string, mixed>> */
    private static function loadSaveMap(): array
    {
        $path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3))
            . '/storage/assistant/post_saves.json';
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, array<string, mixed>> $map */
    private static function saveSaveMap(array $map): void
    {
        $path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3))
            . '/storage/assistant/post_saves.json';
        @file_put_contents(
            $path,
            json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /** @return array<string, mixed> */
    private static function userLookup(string $q): array
    {
        $pdo = Order::getPdo();
        $q = trim($q);
        if ($q !== '') {
            $stmt = $pdo->prepare(
                'SELECT id, username, email, status FROM vc_users
                 WHERE role = "user" AND (username LIKE ? OR email LIKE ?)
                 ORDER BY id DESC LIMIT 50'
            );
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $pdo->query(
                'SELECT id, username, email, status FROM vc_users
                 WHERE role = "user" ORDER BY id DESC LIMIT 50'
            );
        }

        $users = [];
        foreach (($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $r) {
            $users[] = [
                'id'       => (int) $r['id'],
                'username' => (string) $r['username'],
                'email'    => (string) $r['email'],
                'status'   => (string) $r['status'],
            ];
        }

        return ['ok' => true, 'users' => $users];
    }

    /**
     * Xuất báo cáo doanh thu theo tháng ra .xlsx (storage/assistant/reports/).
     *
     * @return array<string, mixed>
     */
    private static function revenueReport(?int $year, ?int $month): array
    {
        $year = ($year && $year >= 2020 && $year <= 2100) ? $year : (int) date('Y');
        $month = ($month && $month >= 1 && $month <= 12) ? $month : (int) date('n');
        $pdo = Order::getPdo();

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) AS revenue, COUNT(*) AS orders
             FROM vc_orders
             WHERE payment_status = "completed" AND YEAR(created_at) = ? AND MONTH(created_at) = ?'
        );
        $stmt->execute([$year, $month]);
        $summary = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare(
            'SELECT DAY(created_at) AS d,
                    COALESCE(SUM(total_amount), 0) AS revenue,
                    COUNT(*) AS orders
             FROM vc_orders
             WHERE payment_status = "completed" AND YEAR(created_at) = ? AND MONTH(created_at) = ?
             GROUP BY DAY(created_at)'
        );
        $stmt->execute([$year, $month]);
        $byDay = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
            $byDay[(int) $r['d']] = ['revenue' => (float) $r['revenue'], 'orders' => (int) $r['orders']];
        }

        $days = (int) date('t', mktime(12, 0, 0, $month, 1, $year));
        $writer = new XlsxWriter(sprintf('Doanh thu %04d-%02d', $year, $month));
        $writer->addRow(['BÁO CÁO DOANH THU', sprintf('%04d-%02d', $year, $month)]);
        $writer->addRow([]);
        $writer->addRow(['Ngày', 'Doanh thu (VNĐ)', 'Đơn hoàn thành']);
        for ($d = 1; $d <= $days; $d++) {
            $row = $byDay[$d] ?? ['revenue' => 0.0, 'orders' => 0];
            $writer->addRow([
                sprintf('%02d/%02d/%04d', $d, $month, $year),
                (float) $row['revenue'],
                (int) $row['orders'],
            ]);
        }
        $writer->addRow(['Tổng cộng', (float) ($summary['revenue'] ?? 0), (int) ($summary['orders'] ?? 0)]);

        $file = sprintf('doanhthu_%04d-%02d.xlsx', $year, $month);
        $path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3))
            . '/storage/assistant/reports/' . $file;
        $writer->save($path);

        return [
            'ok'                => true,
            'month'             => sprintf('%04d-%02d', $year, $month),
            'revenue_vnd'       => (float) ($summary['revenue'] ?? 0),
            'orders_completed'  => (int) ($summary['orders'] ?? 0),
            'file'              => '/admin/assistant/report?file=' . $file,
            'filename'          => $file,
        ];
    }

    // -----------------------------------------------------------------
    // Thư mục ảnh AI / Bài viết AI / Chuyển chủ đề (tool mới)
    // -----------------------------------------------------------------

    /** Cấu hình config/ai.php — DirectMediaService nhận đúng mảng này từ controller. */
    private static function aiConfig(): array
    {
        $base  = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $file  = $base . '/config/ai.php';
        $config = is_file($file) ? require $file : [];

        return is_array($config) ? $config : [];
    }

    /** Liệt kê ảnh trong thư mục AI (chỉ đọc — không cần xác nhận). */
    private static function imageList(int $limit): array
    {
        $limit = max(1, min(100, $limit > 0 ? $limit : 30));
        $rows  = (new DirectMediaService(self::aiConfig()))->listByTab('image', null, $limit * 3);

        $items = [];
        foreach ($rows as $row) {
            $rel = (string) ($row['relative_path'] ?? '');
            $url = (string) ($row['_url'] ?? '');
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            if ($rel === '' || $url === '' || !in_array($ext, self::IMAGE_EXTS, true)) {
                continue;
            }
            $items[] = [
                'asset_id'   => (int) ($row['id'] ?? 0),
                'name'       => basename($rel),
                'url'        => $url,
                'size'       => (int) ($row['size_bytes'] ?? $row['size'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        return [
            'ok'      => true,
            'count'   => count($items),
            'images'  => $items,
            'message' => count($items) . ' ảnh trong thư mục AI — dùng asset_id của image_list để image_delete.',
        ];
    }

    /**
     * Đọc + KIỂM TRA ảnh trước khi xóa: chỉ ảnh (đuôi hợp lệ) nằm TRONG thư
     * mục do AI/hệ thống tạo ra (public/uploads/ai/), không chứa "..".
     *
     * @return array{ok: bool, error: string|null, row: array<string, mixed>|null}
     */
    private static function imageAsset(int $assetId): array
    {
        if ($assetId <= 0) {
            return ['ok' => false, 'error' => 'Thiếu asset_id — hãy chạy image_list để lấy danh sách ảnh.', 'row' => null];
        }

        $row = null;
        try {
            $row = (new AIAsset())->find($assetId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Không đọc được asset: ' . $e->getMessage(), 'row' => null];
        }
        if (!is_array($row)) {
            return ['ok' => false, 'error' => 'Ảnh #' . $assetId . ' không tồn tại (đã bị xóa trước đó?).', 'row' => null];
        }

        $rel = str_replace('\\', '/', (string) ($row['relative_path'] ?? ''));
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (str_contains($rel, '..') || !str_starts_with($rel, self::AI_IMAGE_DIR) || !in_array($ext, self::IMAGE_EXTS, true)) {
            return ['ok' => false, 'error' => 'Chỉ được xóa ảnh thuộc thư mục do AI/hệ thống tạo ra (' . self::AI_IMAGE_DIR . ').', 'row' => null];
        }

        return ['ok' => true, 'error' => null, 'row' => $row];
    }

    /** Xóa 1 ảnh trong thư mục AI (gọi sau khi admin đã xác nhận trên thẻ). */
    private static function imageDelete(array $args): array
    {
        $check = self::imageAsset((int) ($args['asset_id'] ?? 0));
        if (!$check['ok']) {
            return ['ok' => false, 'error' => (string) $check['error']];
        }

        $rel = str_replace('\\', '/', (string) ($check['row']['relative_path'] ?? ''));
        $res = (new DirectMediaService(self::aiConfig()))->forceDelete((int) ($args['asset_id'] ?? 0));
        if (empty($res['ok'])) {
            return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Không xóa được ảnh.')];
        }

        return [
            'ok'      => true,
            'deleted' => (int) ($args['asset_id'] ?? 0),
            'file'    => basename($rel),
            'message' => 'Đã xóa ảnh "' . basename($rel) . '" khỏi thư mục AI.',
        ];
    }

    /**
     * Liệt kê bài viết AI + lịch đăng — để AI biết output_id trước post_schedule.
     * AIOutput::articleList đã giới hạn 100 bản ghi mới nhất.
     */
    private static function postList(string $filter): array
    {
        $filter = in_array($filter, ['scheduled', 'published'], true) ? $filter : '';
        $rows   = (new AIOutput())->articleList($filter);

        $items = [];
        foreach ($rows as $row) {
            $outputId = (int) ($row['id'] ?? 0);
            $title    = trim((string) ($row['topic'] ?? ''));
            $items[]  = [
                'output_id'    => $outputId,
                'title'        => $title !== '' ? mb_substr($title, 0, 140) : 'Bài viết #' . $outputId,
                'status'       => (string) ($row['schedule_status'] ?? '') ?: 'unscheduled',
                'scheduled_at' => (string) ($row['scheduled_at'] ?? ''),
                'published_at' => (string) ($row['published_at'] ?? ''),
            ];
        }

        return [
            'ok'      => true,
            'count'   => count($items),
            'posts'   => $items,
            'message' => count($items) . ' bài viết AI — dùng output_id của post_list cho post_schedule.',
        ];
    }

    /**
     * Lên lịch 1 bài AI đăng lên Fanpage — validate Y HỆT
     * AiOutputController::schedule() nhưng ghi qua ScheduledPost::scheduleForOutput
     * (KHÔNG new controller: constructor của AiBaseController redirect+exit khi
     * thiếu session admin, tool chạy trong request AJAX của service).
     */
    private static function postSchedule(array $args): array
    {
        $outputId = (int) ($args['output_id'] ?? 0);
        $date     = trim((string) ($args['date'] ?? ''));
        $time     = trim((string) ($args['time'] ?? ''));

        $article = null;
        try {
            $article = (new AIOutput())->articleById($outputId);
        } catch (\Throwable $e) {
            $article = null;
        }
        if (!is_array($article)) {
            return ['ok' => false, 'error' => 'Bài viết #' . $outputId . ' không tồn tại — dùng post_list để xem output_id.'];
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        if ($dt === false || $dt->format('Y-m-d H:i') !== ($date . ' ' . $time)) {
            return ['ok' => false, 'error' => 'Ngày/giờ không hợp lệ — date dạng YYYY-MM-DD, time dạng HH:MM (vd 2026-11-05 09:30).'];
        }
        if ($dt->getTimestamp() <= time()) {
            return ['ok' => false, 'error' => 'Thời gian đăng phải lớn hơn thời điểm hiện tại.'];
        }

        // Mirror AiBaseController::decorate(): thay {domain} rồi TÁCH thân bài
        // khỏi khối prompt ảnh — cron đăng nguyên văn generated_content nên
        // nếu không tách, khối "ẢNH MINH HOẠ" sẽ bị đăng lên Fanpage.
        $raw  = self::replaceDomainMacro((string) ($article['content_snapshot'] ?? ''));
        [$body, $imagePrompt] = self::splitArticle($raw);
        if (trim($body) === '') {
            return ['ok' => false, 'error' => 'Bài viết này chưa có nội dung để đăng.'];
        }

        $title = self::replaceDomainMacro(trim((string) ($article['topic'] ?? '')));
        if ($title === '') {
            $title = self::firstLine($body);
        }
        if ($title === '') {
            $title = 'Bài viết #' . $outputId;
        }
        $title = mb_substr($title, 0, 140);

        // Ảnh: chỉ nhận file còn tồn tại trong /uploads/ai/ (khớp regex của
        // AiOutputController). File đã bị xóa → bỏ ảnh, vẫn cho lên lịch (đăng chữ).
        $imagePick = trim((string) ($args['image_url'] ?? ''));
        $imageUrl  = null;
        if ($imagePick !== '') {
            if (preg_match('#^/uploads/ai/[A-Za-z0-9._\-/]+$#', $imagePick) !== 1 || str_contains($imagePick, '..')) {
                return ['ok' => false, 'error' => 'image_url không hợp lệ — chỉ nhận ảnh trong thư mục /uploads/ai/ (xem image_list).'];
            }
            $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
            if (is_file($base . '/public' . $imagePick)) {
                $imageUrl = $imagePick;
            }
        }

        $ok = false;
        try {
            // KHÔNG mang image_prompt vào hàng đợi (giống form): cron không tự
            // sinh ảnh, ảnh phải chọn sẵn — nếu không có thì đăng kèm ảnh đã
            // chọn hoặc đăng chữ.
            $ok = (new ScheduledPost())->scheduleForOutput([
                'output_id'         => $outputId,
                'topic'             => $title,
                'generated_content' => $body,
                'image_prompt'      => null,
                'image_url'         => $imageUrl,
                'scheduled_at'      => $dt->format('Y-m-d H:i:s'),
                'created_by'        => (int) ($_SESSION['user_id'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Không lên lịch được: ' . $e->getMessage()];
        }
        if (!$ok) {
            return ['ok' => false, 'error' => 'Không lên lịch được bài viết #' . $outputId . '.'];
        }

        return [
            'ok'           => true,
            'output_id'    => $outputId,
            'scheduled_at' => $dt->format('Y-m-d H:i:s'),
            'message'      => 'Đã lên lịch đăng bài lúc ' . $dt->format('H:i d/m/Y') . ' — cron sẽ tự đăng lên Fanpage.',
        ];
    }

    /**
     * Chuẩn bị danh sách chủ đề → service gắn card ```topics-list``` vào reply;
     * admin bấm "Mở tab Nội Dung" trên card → JS lưu localStorage + mở form
     * viết bài, tab đó tự dán chủ đề vào ô textarea. KHÔNG ghi database.
     */
    private static function topicsStage(array $args): array
    {
        $raw = is_array($args['topics'] ?? null) ? $args['topics'] : [];
        $topics = [];
        foreach ($raw as $topic) {
            $topic = trim((string) $topic);
            if ($topic === '') {
                continue;
            }
            $topics[] = mb_substr($topic, 0, 200);
            if (count($topics) >= 50) {
                break;
            }
        }
        if ($topics === []) {
            return ['ok' => false, 'error' => 'Danh sách chủ đề rỗng — hãy rút chủ đề từ chiến dịch/bài viết trước.'];
        }

        return [
            'ok'      => true,
            'count'   => count($topics),
            'topics'  => array_values($topics),
            'message' => 'Đã chuẩn bị ' . count($topics) . ' chủ đề — card "Mở tab Nội Dung" hiển thị trong đoạn chat cho admin bấm.',
        ];
    }

    /**
     * Bảng before/after cho thẻ xác nhận — AdminAssistantService::confirmPreview
     * delegate về đây cho image_delete và post_schedule. Mọi lỗi → mảng rỗng
     * (thẻ vẫn hiện summary do ToolRegistry::describe tạo, chỉ thiếu bảng so sánh).
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function previewFor(string $tool, array $args): array
    {
        if ($tool === 'image_delete') {
            $check = self::imageAsset((int) ($args['asset_id'] ?? 0));
            if (!$check['ok'] || !is_array($check['row'])) {
                return [];
            }
            $rel      = str_replace('\\', '/', (string) ($check['row']['relative_path'] ?? ''));
            $assetId  = (int) ($args['asset_id'] ?? 0);

            return [
                'mode'  => 'delete',
                'label' => basename($rel),
                'id'    => $assetId,
                'rows'  => [
                    ['column' => 'File ảnh', 'before' => $rel, 'after' => ''],
                ],
                'url'   => '/' . substr($rel, strlen('public/')),
            ];
        }

        if ($tool === 'post_schedule') {
            $outputId = (int) ($args['output_id'] ?? 0);
            $date     = trim((string) ($args['date'] ?? ''));
            $time     = trim((string) ($args['time'] ?? ''));

            $article = null;
            try {
                $article = (new AIOutput())->articleById($outputId);
            } catch (\Throwable $e) {
                $article = null;
            }
            if (!is_array($article)) {
                return [];
            }

            $title = trim((string) ($article['topic'] ?? ''));
            $title = $title !== '' ? mb_substr($title, 0, 140) : 'Bài viết #' . $outputId;

            $oldSchedule = trim((string) ($article['scheduled_at'] ?? ''));
            $oldStatus   = trim((string) ($article['schedule_status'] ?? ''));
            if ($oldSchedule !== '' && $oldStatus !== '') {
                $oldSchedule .= ' (' . $oldStatus . ')';
            }

            return [
                'mode'  => 'update',
                'label' => $title,
                'id'    => $outputId,
                'rows'  => [
                    ['column' => 'Lịch đăng', 'before' => $oldSchedule, 'after' => trim($date . ' ' . $time)],
                    ['column' => 'Ảnh đăng', 'before' => (string) ($article['scheduled_image_url'] ?? ''), 'after' => trim((string) ($args['image_url'] ?? ''))],
                ],
                'url'   => '/admin/ai/outputs/detail?id=' . $outputId,
            ];
        }

        return [];
    }

    // -----------------------------------------------------------------
    // Mirror xử lý bài — GIỮ NGUYÊN nếu đổi logic ở BaseController /
    // AiBaseController (method protected của controller khác, không dùng
    // chung được; AdminActionTools PHẢI tự tách body trước khi lên lịch
    // vì CronController đăng generated_content nguyên văn).
    // -----------------------------------------------------------------

    /** Mirror BaseController::replaceDomainMacro — thứ tự nguồn: DB → APP_URL → HTTP_HOST → vcvpn.com. */
    private static function replaceDomainMacro(string $text): string
    {
        if (!str_contains($text, '{domain}')) {
            return $text;
        }

        $settings = [];
        try {
            $settings = (new \App\Models\Setting())->getAllAsKeyValue();
        } catch (\Throwable $e) {
            $settings = [];
        }

        $siteUrl = rtrim((string) ($settings['site_url'] ?? $settings['app_url'] ?? (getenv('APP_URL') ?: '')), '/');
        if ($siteUrl === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $siteUrl = $host !== '' ? 'https://' . $host : '';
        }

        $domain = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: 'vcvpn.com');

        return str_replace('{domain}', $domain, $text);
    }

    /** Mirror AiBaseController::splitArticle — trả [thân bài, image_prompt]. */
    private static function splitArticle(string $content): array
    {
        if (trim($content) === '') {
            return ['', ''];
        }

        $pattern = '/^[ \t]*(?:[-*•]\s*)?(?:\d+\s*\.\s*)?'
            . '(?:ẢNH\s+MINH\s+HOẠ|MÔ\s+TẢ\s+ẢNH(?:\s+MINH\s+HOẠ)?|PROMPT\s+ẢNH(?:\s+TẠO\s+ẢNH)?)'
            . '[^\r\n]*/miu';

        $count = preg_match_all($pattern, $content, $hits, PREG_OFFSET_CAPTURE);
        if (!is_int($count) || $count < 1 || $hits[0] === []) {
            return [trim($content), ''];
        }

        // Prompt luôn nằm ở CUỐI bài → chỉ nhận marker trong nửa sau, vị trí CUỐI CÙNG.
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

        $at   = mb_strlen(substr($content, 0, $pos), 'UTF-8');
        $body = trim(mb_substr($content, 0, $at));
        $body = trim((string) preg_replace('/\s*-{3,}\s*\z/u', '', $body));

        $prompt = mb_substr($content, $at);
        $newline = mb_strpos($prompt, "\n");
        if ($newline !== false) {
            $prompt = trim(mb_substr($prompt, $newline));
        } else {
            $prompt = trim((string) preg_replace(
                '/^[ \t]*(?:[-*•]\s*)?(?:\d+\s*\.\s*)?(?:ẢNH\s+MINH\s+HOẠ|MÔ\s+TẢ\s+ẢNH(?:\s+MINH\s+HOẠ)?|PROMPT\s+ẢNH(?:\s+TẠO\s+ẢNH)?)\s*[:：\-]?\s*/iu',
                '',
                (string) $prompt,
                1
            ));
        }

        return [$body, $prompt];
    }

    /** Mirror AiBaseController::firstLine — dòng đầu không rỗng (dùng làm tiêu đề). */
    private static function firstLine(string $text): string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                return $line;
            }
        }

        return '';
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private static function slugify(string $text): string
    {
        $text = trim($text);
        if (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($translit) && $translit !== '') {
                $text = $translit;
            }
        }
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '');
        $slug = trim($slug, '-');
        if ($slug === '' || preg_match('/^\d+$/', $slug)) {
            $slug = $slug !== '' ? 'bai-' . $slug : 'bai-viet';
        }
        return substr($slug, 0, 150);
    }

    /** Tránh trùng slug (PostController cũng chặn trùng khi tạo tay). */
    private static function uniqueSlug(string $slug): string
    {
        $pdo = Order::getPdo();
        $candidate = $slug;
        for ($i = 2; $i <= 50; $i++) {
            $stmt = $pdo->prepare('SELECT id FROM vc_posts WHERE slug = ?');
            $stmt->execute([$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i;
        }
        return $slug . '-' . substr((string) time(), -6);
    }
}
