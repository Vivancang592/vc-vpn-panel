<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FbContact;
use App\Models\Order;

/**
 * Chiến dịch gửi theo lịch của TRỢ LÝ ADMIN (email / tin nhắn Fanpage).
 *
 * - Admin ra lệnh trong chat → AdminActionTools::campaign_create → bảng vc_campaigns.
 * - Cron gọi sendDueCampaigns():
 *   mỗi ngày mỗi chiến dịch 'active' gửi tối đa daily_limit người CHƯA từng
 *   nhận trong CHU KỲ HIỆN TẠI (khóa UNIQUE campaign_id+target_key+cycle_no
 *   trong vc_campaign_sends chống trùng vĩnh viễn — kể cả chạy song song).
 * - Bước 5.1 — recurrence:
 *   + 'once'   : giữ nguyên hành vi cũ → hết người → 'done'.
 *   + 'monthly': rollMonthlyCycles() tăng cycle_no khi tới day_of_month rồi
 *     gửi dần theo daily_limit; KHÔNG BAO GIỜ 'done' → lặp lại mỗi tháng.
 * - Bước 5.3/5.4 — target_mode 'old_customers' (fb): RemarketingService lọc
 *   khách hàng cũ từ dữ liệu THẬT + hàng rào opt-out/cửa sổ 7 ngày của Meta
 *   được kiểm tra LẠI tại thời điểm gửi (bước 5.6).
 * - Bước 5.5 — personalize: chèn {ten}/{goi_cu}/{uu_dai} từ DB; cycle_body do
 *   AI soạn cho chu kỳ (thất bại thì giữ template cũ — không bao giờ chặn gửi).
 * - Bước 5.6 — fb gửi có retry 3 lần (backoff 1s/2s, chỉ lỗi mạng/5xx/429).
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
        $validModes = $kind === 'email'
            ? ['all_users', 'specific']
            : ['all_fans', 'specific', 'old_customers']; // Bước 5.3: khách hàng cũ
        if (!in_array($targetMode, $validModes, true)) {
            return ['ok' => false, 'error' => 'target_mode phải là: ' . implode(' | ', $validModes) . '.'];
        }

        // Bước 5.1 — chu kỳ: 'once' (một lần, giữ nguyên) | 'monthly' (lặp tháng).
        $recurrence = (string) ($in['recurrence'] ?? 'once');
        if (!in_array($recurrence, ['once', 'monthly'], true)) {
            return ['ok' => false, 'error' => 'recurrence phải là "once" hoặc "monthly".'];
        }
        // Ngày chạy trong tháng (1-31); không truyền thì lấy ngày tạo.
        $dayOfMonth = isset($in['day_of_month']) && $in['day_of_month'] !== null && $in['day_of_month'] !== ''
            ? max(1, min(31, (int) $in['day_of_month']))
            : (int) date('j');
        $personalize = !empty($in['personalize']) ? 1 : 0;
        $audienceMode = $targetMode === 'old_customers' ? 'old_customers' : 'legacy';

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
        // monthly: tính ngày chạy đầu tiên (tháng này nếu chưa qua day_of_month).
        $nextRun = $recurrence === 'monthly'
            ? $this->firstRunOnOrAfter(date('Y-m-d'), $dayOfMonth)
            : null;
        $stmt = $pdo->prepare(
            'INSERT INTO vc_campaigns
                (kind, title, subject, body, target_mode, target_ref, daily_limit,
                 recurrence, day_of_month, next_run_at, cycle_no, audience_mode, personalize,
                 status, total_sent, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, "active", 0, ?, NOW())'
        );
        $stmt->execute([
            $kind,
            $title,
            $subject,
            $body,
            $targetMode,
            $targetRef === [] ? null : json_encode($targetRef, JSON_UNESCAPED_UNICODE),
            $dailyLimit,
            $recurrence,
            $dayOfMonth,
            $nextRun,
            $audienceMode,
            $personalize,
            ($uid = (int) ($_SESSION['user_id'] ?? 0)) > 0 ? $uid : null,
        ]);
        $id = (int) $pdo->lastInsertId();

        return [
            'ok'           => true,
            'campaign_id'  => $id,
            'recipients'   => $recipients,
            'daily_limit'  => $dailyLimit,
            'eta_days'     => (int) ceil($recipients / $dailyLimit),
            'recurrence'   => $recurrence,
            'day_of_month' => $recurrence === 'monthly' ? $dayOfMonth : null,
            'next_run_at'  => $nextRun,
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
                // Bước 5.1 — thông tin chu kỳ (null/once với chiến dịch một lần).
                'recurrence'    => (string) ($r['recurrence'] ?? 'once'),
                'day_of_month'  => isset($r['day_of_month']) && ($r['recurrence'] ?? 'once') === 'monthly'
                    ? (int) $r['day_of_month'] : null,
                'next_run_at'   => isset($r['next_run_at']) ? ($r['next_run_at'] !== null ? (string) $r['next_run_at'] : null) : null,
                'cycle_no'      => (int) ($r['cycle_no'] ?? 0),
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
     * Bước 5.1 — Quay vòng chu kỳ monthly: chiến dịch active+monthly tới ngày
     * chạy (next_run_at ≤ hôm nay) → cycle_no++, lùi next_run_at sang ngày
     * day_of_month của tháng kế tiếp (tự gọn 31 về cuối tháng), và nếu
     * personalize=1 → AI soạn cycle_body cho chu kỳ mới (thất bại giữ template,
     * ghi cycle_body_cycle ngay để không gọi lại AI mỗi ngày trong cùng chu kỳ).
     *
     * @param bool $dryRun true = chỉ TÍNH TOÁN (không UPDATE, không gọi AI).
     * @return array{rolled: int, due: array<int, array<string, mixed>>}
     *         due = chiến dịch tới hạn hôm nay, cycle_no đã là CHU KỲ MỚI.
     */
    public function rollMonthlyCycles(bool $dryRun = false): array
    {
        $today = date('Y-m-d');
        try {
            $rows = self::pdo()->query(
                'SELECT * FROM vc_campaigns
                 WHERE status = "active" AND recurrence = "monthly"
                 ORDER BY id'
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return ['rolled' => 0, 'due' => []];
        }

        $rolled = 0;
        $due = [];
        foreach ($rows as $c) {
            $id = (int) $c['id'];
            $dom = max(1, min(31, (int) ($c['day_of_month'] ?? 1) ?: 1));
            $next = trim((string) ($c['next_run_at'] ?? ''));

            if ($next === '') {
                // Lần đầu chưa có lịch → ghi ngày chạy đầu tiên (CHƯA tính chu kỳ).
                $next = $this->firstRunOnOrAfter($today, $dom);
                $c['next_run_at'] = $next;
                if (!$dryRun) {
                    $stmt = self::pdo()->prepare(
                        'UPDATE vc_campaigns SET next_run_at = ?, updated_at = NOW()
                         WHERE id = ? AND (next_run_at IS NULL OR next_run_at = "")'
                    );
                    $stmt->execute([$next, $id]);
                }
            }

            if ($next > $today) {
                continue; // chưa tới kỳ của tháng này
            }

            // Tới kỳ → mở chu kỳ mới.
            $c['cycle_no'] = (int) ($c['cycle_no'] ?? 0) + 1;
            $newNext = $this->firstRunOnOrAfter(date('Y-m-d', strtotime('+1 day')), $dom);
            $c['next_run_at'] = $newNext;

            if (!$dryRun) {
                $stmt = self::pdo()->prepare(
                    'UPDATE vc_campaigns SET cycle_no = ?, next_run_at = ?, updated_at = NOW()
                     WHERE id = ?'
                );
                $stmt->execute([$c['cycle_no'], $newNext, $id]);

                if ((int) ($c['personalize'] ?? 0) === 1) {
                    $composed = null;
                    try {
                        $composed = (new RemarketingService())->composeCycleBody($c);
                    } catch (\Throwable $e) {
                        $composed = null;
                    }
                    // cycle_body_cycle LUÔN ghi = cycle_no hiện tại (kể cả AI
                    // thất bại) để không thử lại AI mỗi ngày trong cùng chu kỳ.
                    $stmt = self::pdo()->prepare(
                        'UPDATE vc_campaigns
                         SET cycle_body = COALESCE(?, cycle_body), cycle_body_cycle = ?, updated_at = NOW()
                         WHERE id = ?'
                    );
                    $stmt->execute([$composed, $c['cycle_no'], $id]);
                    if ($composed !== null) {
                        $c['cycle_body'] = $composed;
                    }
                    $c['cycle_body_cycle'] = $c['cycle_no'];
                }
            }

            $due[] = $c;
            $rolled++;
        }

        return ['rolled' => $rolled, 'due' => $due];
    }

    /**
     * Gửi các chiến dịch đến hạn trong ngày (gọi từ cron).
     *
     * Bước 5.1: roll chu kỳ monthly TRƯỚC khi quét; monthly chỉ gửi khi
     * (a) vừa roll hôm nay, hoặc (b) chu kỳ đang mở còn người chưa nhận
     * (gửi bù các ngày còn lại, mỗi ngày tối đa daily_limit) — KHÔNG BAO GIỜ done.
     * Bước 5.6: $dryRun=true → chỉ tính toán (pending counts), không ghi DB,
     * không gửi tin, không gọi AI — dùng cho khảo sát/preview an toàn.
     *
     * @return array{scanned: int, sent: int, failed: int, skipped: int, rolled: int, dry_run: bool, campaigns: array<int, array<string, int|string|bool>>}
     */
    public function sendDueCampaigns(bool $dryRun = false): array
    {
        $pdo = self::pdo();
        $today = date('Y-m-d');
        $summary = [
            'scanned' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0,
            'rolled' => 0, 'dry_run' => $dryRun, 'campaigns' => [],
        ];

        $roll = $this->rollMonthlyCycles($dryRun);
        $summary['rolled'] = $roll['rolled'];
        $dueById = [];
        foreach ($roll['due'] as $row) {
            $dueById[(int) $row['id']] = $row;
        }

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
            $recurrence = (string) ($c['recurrence'] ?? 'once');

            if ($recurrence === 'monthly') {
                if (isset($dueById[$id])) {
                    $c = array_merge($c, $dueById[$id]); // lấy cycle_no MỚI từ roll
                } elseif ((int) ($c['cycle_no'] ?? 0) < 1) {
                    continue; // chưa từng tới kỳ → chờ, KHÔNG done
                }
                // chu kỳ mở (cycle_no ≥ 1) → gửi bù đến khi hết người trong chu kỳ.
            }
            $cycleNo = (int) ($c['cycle_no'] ?? 0);

            $targets = $this->resolveTargets($c);
            if ($targets === []) {
                if (!$dryRun && $recurrence !== 'monthly') {
                    $this->markDone($id);
                }
                $summary['campaigns'][] = [
                    'campaign_id' => $id, 'cycle_no' => $cycleNo, 'pending' => 0,
                    'total_targets' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0,
                    'note' => 'no_targets',
                ];
                continue;
            }

            $sentKeys = $this->sentKeys($id, $cycleNo);
            $pending = array_slice(array_values(array_diff($targets, $sentKeys)), 0, (int) $c['daily_limit']);

            if ($dryRun) {
                $summary['campaigns'][] = [
                    'campaign_id' => $id, 'cycle_no' => $cycleNo,
                    'pending' => count($pending), 'total_targets' => count($targets),
                    'sent' => 0, 'failed' => 0, 'skipped' => 0, 'dry' => true,
                ];
                continue;
            }

            $sentToday = 0;
            $failedToday = 0;
            $skippedToday = 0;
            foreach ($pending as $key) {
                if (!$this->claim($id, $key, $cycleNo)) {
                    continue; // đã có luồng khác gửi (chống trùng)
                }

                // Bước 5.4/5.6 — hàng rào tuân thủ Meta kiểm tra LẠI tại thời
                // điểm gửi: opted-out / ra cửa sổ 7 ngày → bỏ qua (status skipped).
                if ($kind === 'fb') {
                    $eligible = (new RemarketingService())->eligibleForSend($key);
                    if (!$eligible['ok']) {
                        $this->markSend($id, $key, null, 'skipped: ' . $eligible['reason'], $cycleNo);
                        $skippedToday++;
                        continue;
                    }
                }

                // Bước 5.5 — cycle_body của chu kỳ này (nếu AI đã soạn) + {ten}/{goi_cu}/{uu_dai} thật.
                $body = $this->personalizedBody($c, $key);
                [$ok, $detail] = $this->deliver($kind, $key, (string) $c['subject'], $body);
                $this->markSend($id, $key, $ok, $detail, $cycleNo);
                $ok ? $sentToday++ : $failedToday++;
                if ($ok && $kind === 'fb') {
                    try {
                        (new FbContact())->markSent($key);
                    } catch (\Throwable $e) {
                        // thống kê phụ — không ảnh hưởng luồng gửi
                    }
                }
            }

            if ($sentToday > 0 || $failedToday > 0 || $skippedToday > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE vc_campaigns
                     SET last_sent_date = ?, total_sent = total_sent + ?, updated_at = NOW()
                     WHERE id = ?'
                );
                $stmt->execute([$today, $sentToday, $id]);
            }

            // once: hết người (kể cả skipped) → done. monthly: KHÔNG BAO GIỜ done.
            if ($recurrence !== 'monthly' && array_diff($targets, $this->sentKeys($id, $cycleNo)) === []) {
                $this->markDone($id);
            }

            $summary['sent'] += $sentToday;
            $summary['failed'] += $failedToday;
            $summary['skipped'] += $skippedToday;
            $summary['campaigns'][] = [
                'campaign_id' => $id, 'cycle_no' => $cycleNo,
                'pending' => count($pending), 'total_targets' => count($targets),
                'sent' => $sentToday, 'failed' => $failedToday, 'skipped' => $skippedToday,
            ];
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
        if ($mode === 'old_customers') {
            // Bước 5.3 — khách hàng cũ từ dữ liệu THẬT (đã có gói, hết hạn/hủy
            // ≥30 ngày, không mua lại) + opt-out/cửa sổ 7 ngày (5.4).
            return (new RemarketingService())->resolveOldCustomers();
        }
        $rows = $pdo->query(
            'SELECT DISTINCT external_id FROM vc_chat_sessions
             WHERE source = "fanpage" AND external_id IS NOT NULL AND external_id <> ""'
        )->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter(array_map('strval', $rows)));
    }

    /** @return array<int, string> target đã gửi trong CHU KỲ (chống trùng). */
    private function sentKeys(int $campaignId, int $cycleNo = 0): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT target_key FROM vc_campaign_sends WHERE campaign_id = ? AND cycle_no = ?'
        );
        $stmt->execute([$campaignId, $cycleNo]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        return array_values(array_map('strval', $rows));
    }

    /** Claim một người nhận (UNIQUE campaign_id+target_key+cycle_no) — chỉ luồng đầu được gửi. */
    private function claim(int $campaignId, string $key, int $cycleNo = 0): bool
    {
        try {
            $stmt = self::pdo()->prepare(
                'INSERT INTO vc_campaign_sends (campaign_id, target_key, cycle_no, status, sent_at)
                 VALUES (?, ?, ?, "pending", NOW())'
            );
            $stmt->execute([$campaignId, $key, $cycleNo]);
            return true;
        } catch (\PDOException $e) {
            return false; // 23000 duplicate → đã claim/đã gửi
        }
    }

    /**
     * @param bool|null $ok true=sent, false=failed, null=skipped (bị hàng rào
     *                      tuân thủ Meta 5.4 chặn — không tính là lỗi).
     */
    private function markSend(int $campaignId, string $key, ?bool $ok, string $detail = '', int $cycleNo = 0): void
    {
        $status = $ok === null ? 'skipped' : ($ok ? 'sent' : 'failed');
        $stmt = self::pdo()->prepare(
            'UPDATE vc_campaign_sends SET status = ?, detail = ?, sent_at = NOW()
             WHERE campaign_id = ? AND target_key = ? AND cycle_no = ?'
        );
        $stmt->execute([
            $status,
            mb_substr($detail, 0, 500),
            $campaignId,
            $key,
            $cycleNo,
        ]);
    }

    /**
     * Bước 5.6 — Gửi MỘT người nhận. Email: 1 lần (giữ nguyên hành vi cũ).
     * Fanpage: tối đa 3 lần, backoff 1s/2s, CHỈ retry lỗi thuật toán/mạng
     * (HTTP 0/5xx/429); lỗi tokenize/trùng lặp → dừng ngay.
     *
     * @return array{0: bool, 1: string} [ok, chi tiết lỗi ('' nếu ok)]
     */
    private function deliver(string $kind, string $to, string $subject, string $body): array
    {
        try {
            if ($kind === 'email') {
                $ok = (new MailService())->sendRaw($to, $subject, $body);
                return [$ok, $ok ? '' : 'email_send_failed'];
            }

            $fs = new FanpageService();
            $last = ['ok' => false, 'status' => 0, 'error_code' => 0, 'error' => ''];
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                if ($attempt > 1) {
                    sleep($attempt - 1); // backoff 1s rồi 2s
                }
                $last = $fs->sendMessage($to, $body);
                if (!empty($last['ok'])) {
                    return [true, ''];
                }
                $err = (string) ($last['error'] ?? '');
                if ($err === 'missing_token_or_recipient') {
                    return [false, 'missing_page_token'];
                }
                $retryable = ((int) $last['status'] === 0)
                    || (int) $last['status'] >= 500
                    || (int) $last['status'] === 429;
                if (!$retryable) {
                    break;
                }
            }
            return [false, mb_substr(
                'fb_send_failed status=' . (int) $last['status']
                . ' code=' . (int) $last['error_code'] . ' ' . (string) $last['error'],
                0,
                480
            )];
        } catch (\Throwable $e) {
            return [false, 'exception: ' . mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * Bước 5.5 — Nội dung 1 người nhận: dùng cycle_body nếu AI đã soạn đúng
     * chu kỳ hiện tại, nếu personalize=1 → điền {ten}/{goi_cu}/{uu_dai} THẬT.
     * An toàn: mọi lỗi → fallback body gốc, không bao giờ làm sập luồng gửi.
     */
    private function personalizedBody(array $c, string $key): string
    {
        $body = (string) $c['body'];
        $cycleNo = (int) ($c['cycle_no'] ?? 0);
        $cycleBody = trim((string) ($c['cycle_body'] ?? ''));
        if ($cycleBody !== '' && (int) ($c['cycle_body_cycle'] ?? -1) === $cycleNo) {
            $body = $cycleBody;
        }
        if ((int) ($c['personalize'] ?? 0) !== 1) {
            return $body;
        }
        try {
            $userId = null;
            $row = (new FbContact())->byPsid($key);
            if ($row !== null && !empty($row['user_id'])) {
                $userId = (int) $row['user_id'];
            } elseif ((string) $c['kind'] === 'email') {
                $u = self::pdo()->prepare('SELECT id FROM vc_users WHERE LOWER(email) = ? LIMIT 1');
                $u->execute([mb_strtolower(trim($key))]);
                $found = $u->fetchColumn();
                if ($found !== false) {
                    $userId = (int) $found;
                }
            }
            $svc = new RemarketingService();
            return $svc->expandPlaceholders($body, $svc->buildSendContext($userId, (string) $c['kind'] === 'fb' ? $key : null));
        } catch (\Throwable $e) {
            return $body;
        }
    }

    /**
     * Bước 5.8 — Tiến độ chu kỳ các chiến dịch remarketing (giám sát admin):
     * MỖI dòng là một chiến dịch monthly đang mở, gửi bao nhiêu trong chu kỳ này.
     */
    public function cycleProgress(): array
    {
        try {
            $stmt = self::pdo()->query(
                'SELECT c.id, c.title, c.kind, c.recurrence, c.day_of_month, c.next_run_at,
                        c.cycle_no, c.daily_limit, c.status, c.last_sent_date, c.total_sent,
                        c.personalize,
                        (SELECT COUNT(*) FROM vc_campaign_sends s
                          WHERE s.campaign_id = c.id AND s.cycle_no = c.cycle_no
                            AND s.status = "sent") AS sent_this_cycle,
                        (SELECT COUNT(*) FROM vc_campaign_sends s
                          WHERE s.campaign_id = c.id AND s.cycle_no = c.cycle_no) AS claimed_this_cycle
                 FROM vc_campaigns c
                 WHERE c.kind IN ("fb", "email") AND c.recurrence = "monthly" AND c.status IN ("active", "done")
                 ORDER BY c.id'
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ngày chạy monthly ĐẦU TIÊN >= $base vào đúng $dom (29/30/31 tự gọn về
     * cuối tháng; lặp tối đa 4 lần — an toàn với mọi tháng).
     */
    private function firstRunOnOrAfter(string $base, int $dom): string
    {
        $dom = max(1, min(31, $dom));
        $d = max(1, min(31, (int) date('d', strtotime($base))));
        $m = (int) date('m', strtotime($base));
        $y = (int) date('Y', strtotime($base));
        for ($i = 0; $i < 4; $i++) {
            $last = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
            $candidate = sprintf('%04d-%02d-%02d', $y, $m, min($dom, $last));
            if ($candidate >= $base) {
                return $candidate;
            }
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
        }
        return $base;
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
        if ($mode === 'old_customers') {
            return count((new RemarketingService())->resolveOldCustomers());
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
