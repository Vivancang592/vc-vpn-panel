<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;

/**
 * Chiến dịch gửi theo lịch của TRỢ LÝ ADMIN (email / tin nhắn Fanpage).
 *
 * - Admin ra lệnh trong chat → AdminActionTools::campaign_create → bảng vc_campaigns.
 * - Cron (CronController::checkSubscriptions) gọi sendDueCampaigns():
 *   mỗi ngày mỗi chiến dịch 'active' gửi tối đa daily_limit người CHƯA từng
 *   nhận (khóa UNIQUE campaign_id+target_key trong vc_campaign_sends chống
 *   trùng vĩnh viễn — kể cả chạy cron song song) → hết người → 'done'.
 * - Nội dung do AI soạn lúc tạo; hệ thống không sinh lại nội dung khi gửi.
 */
final class CampaignService
{
    // -----------------------------------------------------------------
    // Tạo / quản lý (gọi từ chat admin)
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $in thô từ AI (kind, title, subject, body,
     *                                  target_mode, user_ids, emails, psids, daily_limit)
     * @return array{ok: bool, campaign_id?: int, recipients?: int, daily_limit?: int, error?: string}
     */
    public function create(array $in): array
    {
        $kind = (string) ($in['kind'] ?? '');
        if (!in_array($kind, ['email', 'fb'], true)) {
            return ['ok' => false, 'error' => 'kind phải là "email" hoặc "fb".'];
        }
        $title = trim((string) ($in['title'] ?? ''));
        $body = trim((string) ($in['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['ok' => false, 'error' => 'Thiếu title hoặc body (nội dung).'];
        }
        $subject = trim((string) ($in['subject'] ?? ''));
        if ($kind === 'email' && $subject === '') {
            return ['ok' => false, 'error' => 'Chiến dịch email cần subject (tiêu đề thư).'];
        }

        $targetMode = (string) ($in['target_mode'] ?? 'all');
        $validModes = $kind === 'email' ? ['all_users', 'specific'] : ['all_fans', 'specific'];
        if (!in_array($targetMode, $validModes, true)) {
            return ['ok' => false, 'error' => 'target_mode phải là: ' . implode(' | ', $validModes) . '.'];
        }

        $targetRef = [];
        if ($targetMode === 'specific') {
            if ($kind === 'email') {
                $emails = $this->normalizeEmails((array) ($in['emails'] ?? []));
                foreach (array_map('intval', (array) ($in['user_ids'] ?? [])) as $uid) {
                    if ($uid > 0) {
                        $email = $this->emailOfUser($uid);
                        if ($email !== '') {
                            $emails[] = $email;
                        }
                    }
                }
                $emails = array_values(array_unique($emails));
                if ($emails === []) {
                    return ['ok' => false, 'error' => 'Thiếu người nhận: cần emails[] hoặc user_ids[] có email.'];
                }
                $targetRef = ['emails' => $emails];
            } else {
                $psids = array_values(array_unique(array_filter(array_map(
                    'trim',
                    array_map('strval', (array) ($in['psids'] ?? []))
                ))));
                if ($psids === []) {
                    return ['ok' => false, 'error' => 'Thiếu psids[] (người từng nhắn fanpage).'];
                }
                $targetRef = ['psids' => $psids];
            }
        }

        $dailyLimit = max(1, min(500, (int) ($in['daily_limit'] ?? 50)));
        $recipients = $this->estimateRecipients($kind, $targetMode, $targetRef);
        if ($recipients === 0) {
            return ['ok' => false, 'error' => 'Không có người nhận nào phù hợp (kiểm tra lại target).'];
        }

        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO vc_campaigns
                (kind, title, subject, body, target_mode, target_ref, daily_limit, status, total_sent, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, "active", 0, ?, NOW())'
        );
        $stmt->execute([
            $kind,
            $title,
            $subject,
            $body,
            $targetMode,
            $targetRef === [] ? null : json_encode($targetRef, JSON_UNESCAPED_UNICODE),
            $dailyLimit,
            ($uid = (int) ($_SESSION['user_id'] ?? 0)) > 0 ? $uid : null,
        ]);
        $id = (int) $pdo->lastInsertId();

        return [
            'ok'          => true,
            'campaign_id' => $id,
            'recipients'  => $recipients,
            'daily_limit' => $dailyLimit,
            'eta_days'    => (int) ceil($recipients / $dailyLimit),
        ];
    }

    /** @return array{ok: bool, campaigns?: array<int, array<string, mixed>>, error?: string} */
    public function list(): array
    {
        try {
            $rows = self::pdo()->query(
                'SELECT * FROM vc_campaigns ORDER BY id DESC LIMIT 100'
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = [
                'campaign_id' => $id,
                'kind'        => (string) $r['kind'],
                'title'       => (string) $r['title'],
                'subject'     => (string) $r['subject'],
                'target_mode' => (string) $r['target_mode'],
                'daily_limit' => (int) $r['daily_limit'],
                'status'      => (string) $r['status'],
                'total_sent'  => (int) $r['total_sent'],
                'last_sent'   => $r['last_sent_date'],
                'created_at'  => (string) $r['created_at'],
            ];
        }

        return ['ok' => true, 'campaigns' => $out];
    }

    /**
     * @return array{ok: bool, status?: string, error?: string}
     */
    public function status(int $campaignId, string $action): array
    {
        $current = $this->findStatus($campaignId);
        if ($current === null) {
            return ['ok' => false, 'error' => 'Không thấy chiến dịch #' . $campaignId . '.'];
        }

        $map = [
            'pause'  => ['from' => ['active'], 'to' => 'paused'],
            'resume' => ['from' => ['paused', 'cancelled'], 'to' => 'active'],
            'cancel' => ['from' => ['active', 'paused'], 'to' => 'cancelled'],
        ];
        if (!isset($map[$action])) {
            return ['ok' => false, 'error' => 'action phải là: pause | resume | cancel.'];
        }
        if (!in_array($current, $map[$action]['from'], true)) {
            return ['ok' => false, 'error' => "Chỉ {$action} khi đang '" . implode("' hoặc '", $map[$action]['from']) . "' (hiện: {$current})."];
        }

        $stmt = self::pdo()->prepare('UPDATE vc_campaigns SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$map[$action]['to'], $campaignId]);

        return ['ok' => true, 'status' => $map[$action]['to']];
    }

    // -----------------------------------------------------------------
    // Cron — gửi dần mỗi ngày
    // -----------------------------------------------------------------

    /**
     * Gửi các chiến dịch đến hạn trong ngày (gọi từ cron).
     *
     * @return array{scanned: int, sent: int, failed: int, campaigns: array<int, array<string, int>>}
     */
    public function sendDueCampaigns(): array
    {
        $pdo = self::pdo();
        $today = date('Y-m-d');
        $summary = ['scanned' => 0, 'sent' => 0, 'failed' => 0, 'campaigns' => []];

        $stmt = $pdo->prepare(
            'SELECT * FROM vc_campaigns
             WHERE status = "active" AND (last_sent_date IS NULL OR last_sent_date < ?)'
        );
        $stmt->execute([$today]);
        $campaigns = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($campaigns as $c) {
            $summary['scanned']++;
            $id = (int) $c['id'];
            $kind = (string) $c['kind'];
            $targets = $this->resolveTargets($c);
            if ($targets === []) {
                $this->markDone($id);
                continue;
            }

            $sentKeys = $this->sentKeys($id);
            $pending = array_slice(array_values(array_diff($targets, $sentKeys)), 0, (int) $c['daily_limit']);

            $sentToday = 0;
            $failedToday = 0;
            foreach ($pending as $key) {
                if (!$this->claim($id, $key)) {
                    continue; // đã có luồng khác gửi (chống trùng)
                }
                $ok = $this->deliver($kind, $key, (string) $c['subject'], (string) $c['body']);
                $this->markSend($id, $key, $ok);
                $ok ? $sentToday++ : $failedToday++;
            }

            if ($sentToday > 0 || $failedToday > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE vc_campaigns
                     SET last_sent_date = ?, total_sent = total_sent + ?, updated_at = NOW()
                     WHERE id = ?'
                );
                $stmt->execute([$today, $sentToday, $id]);
            }

            // Người nhận mới không phát sinh trong ngày (all_users) — done khi đã gửi hết.
            if (array_diff($targets, $this->sentKeys($id)) === []) {
                $this->markDone($id);
            }

            $summary['sent'] += $sentToday;
            $summary['failed'] += $failedToday;
            $summary['campaigns'][] = ['campaign_id' => $id, 'sent' => $sentToday, 'failed' => $failedToday];
        }

        return $summary;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** @return array<int, string> danh sách email/psid hợp lệ của chiến dịch. */
    private function resolveTargets(array $c): array
    {
        $pdo = self::pdo();
        $kind = (string) $c['kind'];
        $mode = (string) $c['target_mode'];
        $ref = json_decode((string) ($c['target_ref'] ?? ''), true);
        $ref = is_array($ref) ? $ref : [];

        if ($kind === 'email') {
            if ($mode === 'specific') {
                return $this->normalizeEmails($ref['emails'] ?? []);
            }
            $rows = $pdo->query(
                'SELECT email FROM vc_users WHERE role = "user" AND email IS NOT NULL AND email <> ""'
            )->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            return $this->normalizeEmails($rows);
        }

        if ($mode === 'specific') {
            return array_values(array_filter(array_map('strval', $ref['psids'] ?? [])));
        }
        $rows = $pdo->query(
            'SELECT DISTINCT external_id FROM vc_chat_sessions
             WHERE source = "fanpage" AND external_id IS NOT NULL AND external_id <> ""'
        )->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter(array_map('strval', $rows)));
    }

    /** @return array<int, string> target đã gửi/thực hiện (chống trùng). */
    private function sentKeys(int $campaignId): array
    {
        $stmt = self::pdo()->prepare('SELECT target_key FROM vc_campaign_sends WHERE campaign_id = ?');
        $stmt->execute([$campaignId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        return array_values(array_map('strval', $rows));
    }

    /** Claim một người nhận (UNIQUE campaign_id+target_key) — chỉ luồng đầu được gửi. */
    private function claim(int $campaignId, string $key): bool
    {
        try {
            $stmt = self::pdo()->prepare(
                'INSERT INTO vc_campaign_sends (campaign_id, target_key, status, sent_at)
                 VALUES (?, ?, "pending", NOW())'
            );
            $stmt->execute([$campaignId, $key]);
            return true;
        } catch (\PDOException $e) {
            return false; // 23000 duplicate → đã claim/đã gửi
        }
    }

    private function markSend(int $campaignId, string $key, bool $ok): void
    {
        $stmt = self::pdo()->prepare(
            'UPDATE vc_campaign_sends SET status = ?, detail = ?, sent_at = NOW()
             WHERE campaign_id = ? AND target_key = ?'
        );
        $stmt->execute([$ok ? 'sent' : 'failed', $ok ? '' : 'Gửi thất bại (xem vc_email_logs / fanpage logs)', $campaignId, $key]);
    }

    private function deliver(string $kind, string $to, string $subject, string $body): bool
    {
        try {
            if ($kind === 'email') {
                return (new MailService())->sendRaw($to, $subject, $body);
            }
            (new FanpageService())->sendMessage($to, $body);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function markDone(int $campaignId): void
    {
        $stmt = self::pdo()->prepare(
            'UPDATE vc_campaigns SET status = "done", updated_at = NOW() WHERE id = ? AND status = "active"'
        );
        $stmt->execute([$campaignId]);
    }

    private function estimateRecipients(string $kind, string $mode, array $targetRef): int
    {
        if ($mode === 'specific') {
            if ($kind === 'email') {
                return count($this->normalizeEmails($targetRef['emails'] ?? []));
            }
            return count($targetRef['psids'] ?? []);
        }

        $pdo = self::pdo();
        if ($kind === 'email') {
            return (int) $pdo->query(
                'SELECT COUNT(*) FROM vc_users WHERE role = "user" AND email IS NOT NULL AND email <> ""'
            )->fetchColumn();
        }
        return (int) $pdo->query(
            'SELECT COUNT(DISTINCT external_id) FROM vc_chat_sessions
             WHERE source = "fanpage" AND external_id IS NOT NULL AND external_id <> ""'
        )->fetchColumn();
    }

    /** @return array<int, string> emails hợp lệ, chuẩn hóa, không trùng. */
    private function normalizeEmails(mixed $raw): array
    {
        $out = [];
        foreach ((array) $raw as $e) {
            $e = strtolower(trim((string) $e));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }
        return array_values(array_unique($out));
    }

    private function emailOfUser(int $userId): string
    {
        $stmt = self::pdo()->prepare('SELECT email FROM vc_users WHERE id = ? AND role = "user"');
        $stmt->execute([$userId]);
        $email = (string) $stmt->fetchColumn();
        return strtolower(trim($email));
    }

    private function findStatus(int $campaignId): ?string
    {
        $stmt = self::pdo()->prepare('SELECT status FROM vc_campaigns WHERE id = ?');
        $stmt->execute([$campaignId]);
        $status = (string) $stmt->fetchColumn();
        return $status !== '' ? $status : null;
    }

    private static function pdo(): \PDO
    {
        return Order::getPdo();
    }
}
