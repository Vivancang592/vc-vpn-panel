<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

use App\Models\Order;
use App\Models\Post;
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
 *
 * Tool chỉ TẠO/LẬP LỊCH — việc gửi thật do cron (CampaignService) đảm nhiệm.
 */
final class AdminActionTools
{
    /** @var array<string, string> mô tả từng tool cho system prompt. */
    private const TOOLS = [
        'post_draft'      => 'Soạn bài viết CHƯA lưu: trả về bản xem trước hiển thị TRONG ĐOẠN CHAT cho admin xem trước, admin bấm "Lưu Bài" mới ghi thành bài nháp (tool KHÔNG ghi database). args: {title: string, content: string (Markdown/HTML), type: "news"|"tutorial"|"faq"|"popup", slug?: string}.',
        'user_lookup'     => 'Tìm tài khoản user (id, username, email) để chọn người nhận mail. args: {q?: string (tên hoặc email — để trống lấy tối đa 50).}',
        'campaign_create' => 'Lập lịch chiến dịch gửi theo ngày (cron gửi dần, CHỐNG GỬI TRÙNG vĩnh viễn). args: {kind: "email"|"fb", title, subject (bắt buộc khi email), body (nội dung đã soạn), target_mode: "all_users"|"all_fans"|"specific", user_ids?: int[], emails?: string[], psids?: string[], daily_limit?: int (1-500, mặc định 50)}.',
        'campaign_list'   => 'Liệt kê chiến dịch + trạng thái + số đã gửi. args: {}.',
        'campaign_status' => 'Tạm dừng/tiếp tục/hủy chiến dịch. args: {campaign_id: int, action: "pause"|"resume"|"cancel"}.',
        'revenue_report'  => 'Xuất báo cáo doanh thu tháng ra file Excel (.xlsx) tải về được. args: {year?: int, month?: int}.',
    ];

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
        $lines[] = '- campaign_create KHÔNG gửi ngay: hệ thống cron gửi dần mỗi ngày tối đa daily_limit người, mỗi người chỉ nhận MỘT LẦN mỗi chiến dịch (chống trùng). Sau khi tạo, trả lời admin: số người nhận, daily_limit, số ngày dự kiến + link [Xem chiến dịch](/admin/assistant) không cần thiết — chỉ nêu tóm tắt.';
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
