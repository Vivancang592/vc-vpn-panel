<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AIModule;
use App\Models\ChatSession;

/**
 * AiReplyController — Tab "Trả Lời Tự Động" (/admin/ai/reply).
 *
 * Trạng thái auto-reply cho 2 nguồn (quyết định D7):
 *   - CHAT website  : ChatbotService (module support_chat).
 *   - BÌNH LUẬN FB  : FanpageService (module fanpage_comment + rule riêng).
 *
 * Tab này CHỈ ĐỌC + hiển thị trạng thái bật/tắt: KHÔNG form test chat, KHÔNG
 * gửi tin nhắn thử (đúng ranh giới D7). Cab "Danh sách hội thoại" ngay dưới
 * cab "Hội Thoại AI Đã Trả Lời" là NƠI XEM + ĐÓNG hội thoại (đã gộp từ trang
 * /admin/ai/conversations — route cũ redirect về đây).
 *
 * Ranh giới F19: không gọi TaskRunner/AICore/provider; chỉ đọc Setting +
 * đếm/tra cứu session hội thoại.
 */
final class AiReplyController extends AiBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->activeMenu = 'ai-reply';
    }

    public function index(): void
    {
        $settings = $this->settings;

        // Trạng thái bật/tắt từng kênh auto-reply (giá trị '1' = bật, mặc định bật).
        $chatEnabled = trim((string) ($settings['ai_chatbot_enabled'] ?? '1')) === '1';
        $commentEnabled = trim((string) ($settings['ai_comment_auto_reply'] ?? '1')) === '1';

        // Model trả lời tự động: đọc config.model_override của 2 module chat
        // (tab này gán CẢ support_chat + fanpage_comment trong một thao tác chọn).
        $moduleModel = new AIModule();
        $readOverride = static function ($row): string {
            if (!is_array($row) || empty($row['config'])) {
                return '';
            }
            $decoded = json_decode((string) $row['config'], true);
            return is_array($decoded) ? trim((string) ($decoded['model_override'] ?? '')) : '';
        };
        $replyModelOverride = $readOverride($moduleModel->findByKey('support_chat'));
        $fpModelOverride = $readOverride($moduleModel->findByKey('fanpage_comment'));

        // Danh sách model hợp lệ capability chat (bắt buộc chọn — thiếu báo ngay)
        // + ẩn model bị API key chặn — đồng bộ với tab Ảnh).
        $replyTabConfig = $this->tabConfig();
        $replyModels = $this->filterUnlockedModels($this->filterModelsForModule(
            is_array($replyTabConfig['models'] ?? null) ? $replyTabConfig['models'] : [],
            'chat'
        ));

        $sessionModel = new ChatSession();

        // Lazy sweep: hội thoại AI trả lời mà khách không phản hồi > 5 phút →
        // tự đóng trước khi hiển thị danh sách (trạng thái phản ánh thực tế).
        $sessionModel->sweepIdle(300);

        // Thống kê hội thoại AI đã trả lời (theo nguồn web / fanpage).
        $conversationStats = ['web' => 0, 'fanpage' => 0, 'total' => 0];
        $statusStats = ['open' => 0, 'handoff' => 0, 'closed' => 0];
        try {
            $pdo = \App\Models\BaseModel::getPdo();
            $stmt = $pdo->query("SELECT `source`, COUNT(*) AS `total` FROM `vc_chat_sessions` GROUP BY `source`");
            foreach (($stmt->fetchAll() ?: []) as $row) {
                $src = (string) ($row['source'] ?? '');
                $cnt = (int) ($row['total'] ?? 0);
                if (isset($conversationStats[$src])) {
                    $conversationStats[$src] = $cnt;
                }
                $conversationStats['total'] += $cnt;
            }
            $stmt = $pdo->query("SELECT `status`, COUNT(*) AS `total` FROM `vc_chat_sessions` GROUP BY `status`");
            foreach (($stmt->fetchAll() ?: []) as $row) {
                $st = (string) ($row['status'] ?? '');
                if (isset($statusStats[$st])) {
                    $statusStats[$st] = (int) ($row['total'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            // Bảng chưa có dữ liệu / lỗi DB — giữ 0, tab vẫn hiển thị.
        }

        // Bộ lọc + phân trang cho cab "Danh sách hội thoại" (từ GET).
        $source = strtolower(trim((string) ($_GET['source'] ?? '')));
        if (!in_array($source, ['web', 'fanpage'], true)) {
            $source = '';
        }
        $keyword = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $list = $sessionModel->searchSessions($source, $keyword, $page, $perPage);

        $this->render('admin.ai.tab-reply', [
            'activeMenu' => 'ai-reply',
            'pageTitle'  => 'Trả Lời Tự Động - Trung Tâm AI',
            'chatEnabled'    => $chatEnabled,
            'commentEnabled' => $commentEnabled,
            // Chọn model trả lời (áp dụng CẢ 2 kênh) + override hiện tại của từng module.
            'replyModels'        => $replyModels,
            'replyModelOverride' => $replyModelOverride,
            'fpModelOverride'    => $fpModelOverride,
            'conversationStats' => $conversationStats,
            'statusStats' => $statusStats,
            // Danh sách hội thoại đầy đủ (tìm kiếm/phân trang/đóng).
            'convSessions'   => $list['sessions'],
            'convTotal'      => $list['total'],
            'convPage'       => $page,
            'convPerPage'    => $perPage,
            'convSource'     => $source,
            'convKeyword'    => $keyword,
            'sourceLabels'   => ['web' => 'Website', 'fanpage' => 'Facebook'],
            'statusLabels'   => [
                'open'    => 'Đang mở',
                'handoff' => 'Chờ nhân viên',
                'closed'  => 'Đã đóng',
            ],
        ]);
    }
}
