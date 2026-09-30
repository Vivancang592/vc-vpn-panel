<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ChatSession;

/**
 * AiReplyController — Tab "Trả Lời Tự Động" (/admin/ai/reply).
 *
 * Trạng thái auto-reply cho 2 nguồn (quyết định D7):
 *   - CHAT website  : ChatbotService (module support_chat, Nội Quy rules_auto_reply).
 *   - BÌNH LUẬN FB  : FanpageService (module fanpage_comment + rule riêng).
 *
 * Tab này CHỈ ĐỌC + hiển thị trạng thái bật/tắt: KHÔNG form test chat, KHÔNG
 * gửi tin nhắn thử (đúng ranh giới D7). Việc xem CHI TIẾT hội thoại AI đã trả
 * lời khách nằm ở trang Hội Thoại (/admin/ai/conversations — link từ tab).
 *
 * Ranh giới F19: không gọi TaskRunner/AICore/provider; chỉ đọc Setting +
 * đếm session hội thoại.
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

        // Thống kê hội thoại AI đã trả lời (theo nguồn web / fanpage).
        $conversationStats = ['web' => 0, 'fanpage' => 0, 'total' => 0];
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
        } catch (\Throwable $e) {
            // Bảng chưa có dữ liệu / lỗi DB — giữ 0, tab vẫn hiển thị.
        }

        $this->render('admin.ai.tab-reply', [
            'activeMenu' => 'ai-reply',
            'pageTitle'  => 'Trả Lời Tự Động - Trung Tâm AI',
            'chatEnabled'    => $chatEnabled,
            'commentEnabled' => $commentEnabled,
            'conversationStats' => $conversationStats,
            'recentSessions' => $this->recentSessions(),
        ]);
    }

    /**
     * 5 hội thoại mới nhất (chỉ meta — chi tiết ở /admin/ai/conversations).
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentSessions(): array
    {
        try {
            $pdo = \App\Models\BaseModel::getPdo();
            $stmt = $pdo->query(
                'SELECT `s`.`id`, `s`.`source`, `s`.`status`, `s`.`updated_at`,'
                . ' `u`.`username`,'
                . ' (SELECT `ml`.`content` FROM `vc_chat_messages` `ml` WHERE `ml`.`session_id` = `s`.`id` ORDER BY `ml`.`id` DESC LIMIT 1) AS `last_message`'
                . ' FROM `vc_chat_sessions` `s`'
                . ' LEFT JOIN `vc_users` `u` ON `u`.`id` = `s`.`user_id`'
                . ' ORDER BY `s`.`id` DESC LIMIT 5'
            );
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
