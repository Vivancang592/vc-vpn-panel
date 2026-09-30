<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BaseModel;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\ChatSession;

/**
 * AiConversationController — Admin ĐỌC LẠI hội thoại AI (/admin/ai/conversations).
 *
 * Mục đích: cho Admin xem lại CHÍNH XÁC những gì AI đã trả lời cho khách,
 * gồm 3 nguồn:
 *   - website (web)      : khung chat trên site.
 *   - facebook (fanpage) : Messenger 1-1 + bình luận bài viết.
 *
 * Phân biệt rõ với Admin:
 *   - NGUỒN hội thoại (Website / Facebook) — cột `source` trong vc_chat_sessions.
 *   - Khách ĐÃ ĐĂNG NHẬP hay CHƯA — dựa vào `user_id` của session.
 *   - TÊN người dùng nếu đã đăng nhập (tra bảng vc_users).
 *
 * Controller này CHỈ ĐỌC (read-only): không tạo task, không gọi provider,
 * không đi qua TaskRunner — đúng ranh giới F19.
 */
final class AiConversationController extends AiBaseController
{
    public function index(): void
    {
        $source = strtolower(trim((string) ($_GET['source'] ?? '')));
        if (!in_array($source, ['web', 'fanpage'], true)) {
            $source = '';
        }

        $keyword = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $sessions = [];
        $total = 0;

        try {
            $pdo = BaseModel::getPdo();

            $where = [];
            $params = [];
            if ($source !== '') {
                $where[] = '`s`.`source` = :source';
                $params['source'] = $source;
            }
            if ($keyword !== '') {
                $where[] = '(`m`.`content` LIKE :kw OR `u`.`username` LIKE :kw2 OR `u`.`email` LIKE :kw3)';
                $params['kw'] = '%' . $keyword . '%';
                $params['kw2'] = '%' . $keyword . '%';
                $params['kw3'] = '%' . $keyword . '%';
            }
            $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

            $countSql = 'SELECT COUNT(DISTINCT `s`.`id`)'
                . ' FROM `vc_chat_sessions` `s`'
                . ' LEFT JOIN `vc_chat_messages` `m` ON `m`.`session_id` = `s`.`id`'
                . ' LEFT JOIN `vc_users` `u` ON `u`.`id` = `s`.`user_id`'
                . $whereSql;
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $sql = 'SELECT `s`.`id`, `s`.`source`, `s`.`user_id`, `s`.`visitor_token`, `s`.`external_id`,'
                . ' `s`.`status`, `s`.`created_at`, `s`.`updated_at`,'
                . ' `u`.`username`, `u`.`email` AS `user_email`,'
                . ' (SELECT COUNT(*) FROM `vc_chat_messages` `mc` WHERE `mc`.`session_id` = `s`.`id`) AS `message_count`,'
                . ' (SELECT `ml`.`content` FROM `vc_chat_messages` `ml` WHERE `ml`.`session_id` = `s`.`id` ORDER BY `ml`.`id` DESC LIMIT 1) AS `last_message`,'
                . ' (SELECT `ml2`.`role` FROM `vc_chat_messages` `ml2` WHERE `ml2`.`session_id` = `s`.`id` ORDER BY `ml2`.`id` DESC LIMIT 1) AS `last_role`'
                . ' FROM `vc_chat_sessions` `s`'
                . ' LEFT JOIN `vc_users` `u` ON `u`.`id` = `s`.`user_id`'
                . $whereSql
                . ' ORDER BY `s`.`id` DESC'
                . " LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $sessions = [];
        }

        $this->render('admin.ai.conversations', [
            'activeMenu'  => 'ai-conversations',
            'pageTitle'   => 'Hội Thoại AI',
            'sessions'    => $sessions,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'sourceFilter' => $source,
            'keyword'     => $keyword,
            'sourceLabels' => [
                'web'     => 'Website',
                'fanpage' => 'Facebook',
            ],
            'statusLabels' => [
                'open'    => 'Đang mở',
                'handoff' => 'Chờ nhân viên',
                'closed'  => 'Đã đóng',
            ],
        ]);
    }

    public function detail(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->flash('Không tìm thấy hội thoại cần xem.', 'danger', '/admin/ai/conversations');
        }

        $sessionModel = new ChatSession();
        $session = $sessionModel->find($id);

        if (!is_array($session)) {
            $this->flash('Hội thoại #' . $id . ' không tồn tại.', 'danger', '/admin/ai/conversations');
        }

        $messages = [];
        try {
            $messages = (new ChatMessage())->getRecentBySession($id, 100);
        } catch (\Throwable $e) {
            $messages = [];
        }

        // Sự kiện AI liên quan (mở chat, trả lời, chuyển nhân viên...).
        $events = [];
        try {
            $pdo = BaseModel::getPdo();
            $stmt = $pdo->prepare('SELECT * FROM `vc_chat_events` WHERE `session_id` = :sid ORDER BY `id` DESC LIMIT 50');
            $stmt->execute(['sid' => $id]);
            $events = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $events = [];
        }

        // Tên người dùng nếu khách đã đăng nhập.
        $userName = null;
        $userEmail = null;
        if (!empty($session['user_id'])) {
            try {
                $pdo = BaseModel::getPdo();
                $stmt = $pdo->prepare('SELECT `username`, `email` FROM `vc_users` WHERE `id` = :uid LIMIT 1');
                $stmt->execute(['uid' => (int) $session['user_id']]);
                $user = $stmt->fetch();
                if (is_array($user)) {
                    $userName = trim((string) ($user['username'] ?? ''));
                    $userEmail = trim((string) ($user['email'] ?? ''));
                }
            } catch (\Throwable $e) {
                // bỏ qua
            }
        }

        $this->render('admin.ai.conversation-detail', [
            'activeMenu'  => 'ai-conversations',
            'pageTitle'   => 'Chi Tiết Hội Thoại AI',
            'session'     => $session,
            'messages'    => $messages,
            'events'      => $events,
            'userName'    => $userName,
            'userEmail'   => $userEmail,
            'sourceLabel' => ((string) ($session['source'] ?? 'web')) === 'fanpage' ? 'Facebook' : 'Website',
            'statusLabels' => [
                'open'    => 'Đang mở',
                'handoff' => 'Chờ nhân viên',
                'closed'  => 'Đã đóng',
            ],
            'roleLabels' => [
                'user'      => 'Khách',
                'assistant' => 'AI',
                'system'    => 'Hệ thống',
            ],
        ]);
    }
}
