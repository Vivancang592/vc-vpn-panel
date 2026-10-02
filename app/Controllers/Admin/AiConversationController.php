<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BaseModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;

/**
 * AiConversationController — Admin xem lại + ĐÓNG hội thoại AI.
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
 * Danh sách hội thoại đã TÍCH HỢP vào tab Trả Lời Tự Động (/admin/ai/reply)
 * — route GET /admin/ai/conversations vẫn giữ (redirect) để link cũ không gãy.
 * Trang này chỉ còn 2 việc:
 *   - detail(): xem chi tiết một hội thoại.
 *   - close() : POST "✅ Đã xử lý" — đóng hội thoại đã chuyển nhân viên (handoff)
 *     hoặc đang treo open, đúng ranh giới F19 (KHÔNG gọi TaskRunner/provider).
 */
final class AiConversationController extends AiBaseController
{
    public function index(): void
    {
        // Danh sách hội thoại đã gộp vào tab Trả Lời Tự Động — giữ route cũ
        // (selftest F20) bằng redirect để UI gọn, không còn 2 trang trùng.
        $this->redirect('/admin/ai/reply#cab-conversations');
    }

    public function detail(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->flash('Không tìm thấy hội thoại cần xem.', 'danger', '/admin/ai/reply');
        }

        $sessionModel = new ChatSession();
        $session = $sessionModel->find($id);

        if (!is_array($session)) {
            $this->flash('Hội thoại #' . $id . ' không tồn tại.', 'danger', '/admin/ai/reply');
        }

        // Phiên idle > 5 phút chưa được sweep (admin mở trực tiếp detail) →
        // đồng bộ trạng thái cho khớp thực tế trước khi hiển thị.
        if ($sessionModel->isIdleStale($session)) {
            $sessionModel->update($id, ['status' => 'closed']);
            $session['status'] = 'closed';
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

    /**
     * POST "✅ Đã xử lý" — đóng một hội thoại (trạng thái → closed).
     *
     * Dùng cho hội thoại đã chuyển nhân viên (handoff) hoặc đang treo open:
     * admin bấm nút là đóng dứt điểm, không để treo vô hạn.
     * Phiên 'closed' thì không làm gì (idempotent).
     */
    public function close(): void
    {
        $back = (string) ($_POST['back'] ?? '');
        // Chỉ cho quay về URL nội bộ bắt đầu bằng /admin/ (chống open redirect),
        // không chứa CRLF (chống header injection).
        if ($back === '' || strpos($back, '/admin/') !== 0 || preg_match('/[\r\n]/', $back)) {
            $back = '/admin/ai/reply#cab-conversations';
        }

        if (!$this->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('Không tìm thấy hội thoại cần đóng.', 'danger', $back);
            return;
        }

        $sessionModel = new ChatSession();
        $session = $sessionModel->find($id);

        if (!is_array($session)) {
            $this->flash('Hội thoại #' . $id . ' không tồn tại.', 'danger', $back);
            return;
        }

        if ((string) ($session['status'] ?? '') === 'closed') {
            $this->flash('Hội thoại #' . $id . ' đã đóng trước đó.', 'success', $back);
            return;
        }

        $sessionModel->update($id, ['status' => 'closed']);
        $this->flash('✅ Đã xử lý — hội thoại #' . $id . ' đã được đóng.', 'success', $back);
    }

    /**
     * Xóa vĩnh viễn một hội thoại đã đóng cùng các tin nhắn của nó.
     */
    public function delete(): void
    {
        $back = $this->conversationBackUrl();

        if (!$this->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if (!(new ChatSession())->deleteClosed($id)) {
            $this->flash('Chỉ có thể xóa hội thoại đã đóng.', 'danger', $back);
            return;
        }

        $this->flash('Đã xóa vĩnh viễn hội thoại #' . $id . '.', 'success', $back);
    }

    /**
     * Xóa vĩnh viễn toàn bộ hội thoại đã đóng.
     */
    public function deleteAllClosed(): void
    {
        $back = $this->conversationBackUrl();

        if (!$this->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $this->flash('CSRF token không hợp lệ.', 'danger', $back);
            return;
        }

        $deleted = (new ChatSession())->deleteAllClosed();
        $this->flash(
            $deleted > 0 ? 'Đã xóa vĩnh viễn ' . $deleted . ' hội thoại đã đóng.' : 'Không có hội thoại đã đóng để xóa.',
            'success',
            $back
        );
    }

    private function conversationBackUrl(): string
    {
        $back = (string) ($_POST['back'] ?? '');

        if ($back === '' || strpos($back, '/admin/') !== 0 || preg_match('/[\r\n]/', $back)) {
            return '/admin/ai/reply#cab-conversations';
        }

        return $back;
    }
}
