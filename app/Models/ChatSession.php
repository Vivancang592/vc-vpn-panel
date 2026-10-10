<?php

namespace App\Models;

class ChatSession extends BaseModel
{
    protected string $table = 'vc_chat_sessions';

    public function findByVisitor(string $visitorToken, string $source = 'web'): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM `{$this->table}` WHERE `visitor_token` = :visitor_token AND `source` = :source LIMIT 1");
        $stmt->execute([
            'visitor_token' => $visitorToken,
            'source' => $source
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Phiên "chết" cần tự đóng: status đang 'open' nhưng không có hoạt động
     * mới (tin nhắn cuối hoặc thời điểm tạo phiên) trong $idleSeconds qua —
     * ví dụ AI đã trả lời mà khách không phản hồi lại 5 phút.
     *
     * KHÔNG đụng phiên 'handoff' (chờ nhân viên xử lý — chỉ đóng bằng nút
     * "Đã xử lý" trong admin) và 'closed' (đã đóng).
     */
    public function isIdleStale(array $session, int $idleSeconds = 300): bool
    {
        if (trim((string) ($session['status'] ?? '')) !== 'open') {
            return false;
        }

        $lastAt = trim((string) ($session['created_at'] ?? ''));
        try {
            $lastMessage = (new ChatMessage())->getLastBySession((int) ($session['id'] ?? 0));
            if (!empty($lastMessage['created_at'])) {
                $lastAt = (string) $lastMessage['created_at'];
            }
        } catch (\Throwable $e) {
            // Không đọc được tin nhắn → dùng thời điểm tạo phiên.
        }

        if ($lastAt === '') {
            return false;
        }

        $lastTs = strtotime($lastAt);
        return $lastTs > 0 && (time() - $lastTs) >= $idleSeconds;
    }

    /**
     * Đóng hàng loạt các phiên 'open' idle quá $idleSeconds giây (mặc định 5
     * phút) — chạy lazy khi admin mở danh sách hội thoại để trạng thái phản
     * ánh đúng thực tế (không còn phiên "treo" open vô hạn).
     *
     * @return int Số phiên vừa tự đóng.
     */
    public function sweepIdle(int $idleSeconds = 300): int
    {
        $idleSeconds = max(1, (int) $idleSeconds);

        try {
            // Cutoff tính bằng PHP — đồng nhất clock với ChatMessage::add()
            // (cũng ghi created_at bằng date()), tránh lệch timezone PHP/MySQL.
            $cutoff = date('Y-m-d H:i:s', time() - $idleSeconds);
            $sql = "UPDATE `{$this->table}` `s`
                LEFT JOIN (
                    SELECT `session_id`, MAX(`id`) AS `mid`
                    FROM `vc_chat_messages`
                    GROUP BY `session_id`
                ) `lm` ON `lm`.`session_id` = `s`.`id`
                LEFT JOIN `vc_chat_messages` `m` ON `m`.`id` = `lm`.`mid`
                SET `s`.`status` = 'closed'
                WHERE `s`.`status` = 'open'
                  AND COALESCE(`m`.`created_at`, `s`.`created_at`) < :cutoff";
            $stmt = self::$db->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Tìm phiên hội thoại cho admin: lọc theo nguồn (web/fanpage) + keyword
     * (nội dung tin nhắn / username / email), phân trang, kèm số tin và tin
     * nhắn cuối. Dùng cho cab "Danh sách hội thoại" trong tab Trả Lời Tự Động.
     *
     * @return array{sessions: array<int, array<string, mixed>>, total: int}
     */
    public function searchSessions(string $source, string $keyword, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        if ($source !== '') {
            $where[] = '`s`.`source` = :source';
            $params['source'] = $source;
        }
        if ($keyword !== '') {
            // Dùng EXISTS thay vì JOIN vc_chat_messages: vừa không cần alias `m`
            // trong 2 query, vừa không nhân đôi dòng khi 1 session có nhiều tin.
            $where[] = '(EXISTS (SELECT 1 FROM `vc_chat_messages` `mk` WHERE `mk`.`session_id` = `s`.`id` AND `mk`.`content` LIKE :kw)'
                . ' OR `u`.`username` LIKE :kw2 OR `u`.`email` LIKE :kw3)';
            $params['kw'] = '%' . $keyword . '%';
            $params['kw2'] = '%' . $keyword . '%';
            $params['kw3'] = '%' . $keyword . '%';
        }
        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        try {
            $pdo = self::getPdo();

            $countSql = 'SELECT COUNT(DISTINCT `s`.`id`)'
                . ' FROM `vc_chat_sessions` `s`'
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

            return [
                'sessions' => $stmt->fetchAll() ?: [],
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            return ['sessions' => [], 'total' => 0];
        }
    }

    /**
     * Xóa một phiên đã đóng. Các tin nhắn liên quan được xóa theo foreign key;
     * sự kiện được giữ lại nhưng bỏ liên kết session để phục vụ audit.
     */
    public function deleteClosed(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id AND `status` = 'closed'");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Xóa toàn bộ phiên đã đóng và nội dung tin nhắn thuộc các phiên đó.
     *
     * @return int Số phiên đã xóa.
     */
    public function deleteAllClosed(): int
    {
        $stmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE `status` = 'closed'");
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Đánh dấu một phiên đang mở cần nhân viên hỗ trợ.
     *
     * Chỉ đổi trạng thái một lần để các kênh Web/Fanpage cùng tránh ghi nhận
     * lặp lại một yêu cầu chuyển tiếp trong cùng phiên.
     */
    public function markHandoff(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = self::$db->prepare("UPDATE `{$this->table}` SET `status` = 'handoff' WHERE `id` = :id AND `status` = 'open'");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() === 1;
    }

    public function countByStatus(string $status): int
    {
        if (!in_array($status, ['open', 'handoff', 'closed'], true)) {
            return 0;
        }

        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = :status");
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Ngữ cảnh cuộn của phiên chat khách hàng (BƯỚC 3.1).
     *
     * Khi hội thoại vượt ngân sách token, phần tin CŨ NHẤT được tóm tắt lại
     * và lưu ở đây → câu hỏi mới vẫn biết rõ "đầu cuộc trò chuyện nói gì".
     * Đọc lỗi (migration chưa chạy) → coi như chưa có tóm tắt, không chặn chat.
     *
     * @return array{summary: string, covered_through: int}
     */
    public function contextState(int $id): array
    {
        $fallback = ['summary' => '', 'covered_through' => 0];
        if ($id <= 0) {
            return $fallback;
        }

        try {
            $stmt = self::$db->prepare(
                "SELECT `summary`, `covered_through` FROM `{$this->table}` WHERE `id` = :id LIMIT 1"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                return $fallback;
            }

            return [
                'summary' => (string) ($row['summary'] ?? ''),
                'covered_through' => (int) ($row['covered_through'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /** Ghi lại bản tóm tắt + mốc đã tóm tắt của phiên. */
    public function saveContextState(int $id, string $summary, int $coveredThrough): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $stmt = self::$db->prepare(
                "UPDATE `{$this->table}` SET `summary` = :summary, `covered_through` = :covered_through WHERE `id` = :id"
            );
            return $stmt->execute([
                'summary' => $summary,
                'covered_through' => max(0, $coveredThrough),
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getOrCreate(string $visitorToken, string $source = 'web', ?int $userId = null, ?string $externalId = null): ?array
    {
        $existing = $this->findByVisitor($visitorToken, $source);
        if ($existing) {
            if (trim((string) ($existing['status'] ?? '')) === 'closed') {
                // Phiên đã đóng mà khách (web hoặc Facebook) quay lại nhắn tiếp:
                // "lưu trữ" phiên cũ — đổi visitor_token để giải phóng khóa UNIQUE
                // (giữ nguyên lịch sử tin nhắn cho admin xem lại) — rồi tạo phiên
                // MỚI bên dưới → bộ nhớ AI sạch, hội thoại bắt đầu lại từ đầu.
                $this->update((int) $existing['id'], [
                    'visitor_token' => substr($visitorToken, 0, 64) . '#c' . (int) $existing['id'] . '_' . time(),
                    'status' => 'closed'
                ]);
            } else {
                $updates = [];
                if ($userId !== null && $userId > 0 && empty($existing['user_id'])) {
                    $updates['user_id'] = $userId;
                }
                if ($externalId !== null && $externalId !== '' && empty($existing['external_id'])) {
                    $updates['external_id'] = $externalId;
                }
                if (!empty($updates)) {
                    $this->update((int) $existing['id'], $updates);
                    return $this->find((int) $existing['id']);
                }
                return $existing;
            }
        }

        $ok = $this->create([
            'user_id' => $userId,
            'visitor_token' => $visitorToken,
            'source' => $source,
            'external_id' => $externalId,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if (!$ok) {
            return null;
        }

        return $this->find($this->lastInsertId());
    }
}
