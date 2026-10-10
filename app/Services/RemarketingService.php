<?php

declare(strict_types=1);

namespace App\Services;

use App\AI\Knowledge\SiteKnowledge;
use App\Models\FbContact;

/**
 * Bước 5.4/5.5 — Động cơ Remarketing Facebook.
 *
 *  - resolveOldCustomers(): lọc "khách hàng cũ" từ dữ liệu THẬT (đã từng có
 *    gói, hết hạn/hủy ≥30 ngày, không còn gói active, không mua lại trong
 *    30 ngày) — tuyệt đối không suy đoán, không bịa đối tượng.
 *  - eligibleForSend(): hàng rào tuân thủ Meta — opt-out + cửa sổ 7 ngày,
 *    kiểm tra LẠI tại thời điểm gửi (ngoài bộ lọc lúc lên danh sách).
 *  - buildSendContext()/expandPlaceholders(): cá nhân hóa {ten}/{goi_cu}/
 *    {uu_dai} bằng dữ liệu thật lấy từ DB.
 *  - composeCycleBody(): AI soạn nội dung cho chu kỳ monthly; thất bại thì
 *    giữ nguyên template (không bao giờ chặn luồng gửi).
 */
class RemarketingService
{
    /** Số ngày sau khi gói kết thúc (hết hạn/hủy) thì coi là "khách hàng cũ". */
    public const OLD_CUSTOMER_INACTIVE_DAYS = 30;

    /** Cửa sổ tin nhắn cho phép của Meta (đồng nhất FbContact::WINDOW_DAYS). */
    public const META_WINDOW_DAYS = 7;

    private function fb(): FbContact
    {
        return new FbContact();
    }

    // =========================== 5.4 — ĐỐI TƯỢNG ===========================

    /**
     * Danh sách PSID "khách hàng cũ đủ điều kiện" — tất cả đều là dữ liệu thật:
     *  1) có liên kết tài khoản website (user_id) — nhờ khách tự nhập email trong chat;
     *  2) CHƯA opt-out và còn trong cửa sổ 7 ngày của Meta;
     *  3) KHÔNG còn gói active;
     *  4) CÓ gói đã hết hạn/hủy cách đây ≥ OLD_CUSTOMER_INACTIVE_DAYS ngày;
     *  5) KHÔNG có đơn completed nào trong OLD_CUSTOMER_INACTIVE_DAYS ngày qua.
     *
     * @return array<int, string> mảng psid
     */
    public function resolveOldCustomers(): array
    {
        $d = (int) self::OLD_CUSTOMER_INACTIVE_DAYS;
        $w = (int) self::META_WINDOW_DAYS;
        $sql = "SELECT DISTINCT f.psid
                FROM vc_fb_contacts f
                JOIN vc_users u ON u.id = f.user_id
                WHERE f.opted_out_at IS NULL
                  AND f.last_interaction_at >= NOW() - INTERVAL {$w} DAY
                  AND u.role = 'user'
                  AND NOT EXISTS (
                      SELECT 1 FROM vc_subscriptions s
                      WHERE s.user_id = u.id AND s.status = 'active'
                  )
                  AND EXISTS (
                      SELECT 1 FROM vc_subscriptions s2
                      WHERE s2.user_id = u.id
                        AND s2.status IN ('expired', 'cancelled')
                        AND s2.end_date <= NOW() - INTERVAL {$d} DAY
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM vc_orders o
                      WHERE o.user_id = u.id
                        AND o.payment_status = 'completed'
                        AND o.created_at > NOW() - INTERVAL {$d} DAY
                  )
                ORDER BY f.psid";
        try {
            $rows = FbContact::getPdo()->query($sql)->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        return array_values(array_map('strval', $rows));
    }

    /**
     * Hàng rào tuân thủ tại thời điểm GỬI (kiểm tra lần 2, phòng hờ khi
     * khách opt-out hoặc ra cửa sổ 7 ngày sau khi danh sách được lập).
     *
     * @return array{ok: bool, reason: string}
     */
    public function eligibleForSend(string $psid): array
    {
        $psid = trim($psid);
        if ($psid === '') {
            return ['ok' => false, 'reason' => 'unknown_contact'];
        }
        $row = $this->fb()->byPsid($psid);
        if ($row === null) {
            // Chưa có bản đồ (khách nhắn TRƯỚC khi tồn tại vc_fb_contacts — ví dụ
            // all_fans/specific từ dữ liệu hội thoại cũ): suy ngược từ thời điểm
            // nhắn THẬT. Còn trong cửa sổ 7 ngày → mở bản đồ (opt-in thủ công);
            // hết cửa sổ → không gửi (tuyệt đối không nhắn người im lặng > 7 ngày).
            try {
                $stmt = FbContact::getPdo()->prepare(
                    'SELECT MAX(m.created_at)
                     FROM vc_chat_sessions s
                     LEFT JOIN vc_chat_messages m ON m.session_id = s.id AND m.role = "user"
                     WHERE s.source = "fanpage" AND s.external_id = ?'
                );
                $stmt->execute([$psid]);
                $lastUserMsg = (string) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                $lastUserMsg = '';
            }
            if ($lastUserMsg === '' || strtotime($lastUserMsg) < time() - self::META_WINDOW_DAYS * 86400) {
                return ['ok' => false, 'reason' => 'outside_7d_window'];
            }
            try {
                $this->fb()->touch($psid, '', '', null); // backfill bản đồ (opt-in từ hội thoại thật)
            } catch (\Throwable $e) {
                return ['ok' => false, 'reason' => 'unknown_contact'];
            }
            return ['ok' => true, 'reason' => ''];
        }
        if ($row['opted_out_at'] !== null) {
            return ['ok' => false, 'reason' => 'opted_out'];
        }
        $last = (int) strtotime((string) $row['last_interaction_at']);
        if ($last < time() - self::META_WINDOW_DAYS * 86400) {
            return ['ok' => false, 'reason' => 'outside_7d_window'];
        }
        return ['ok' => true, 'reason' => ''];
    }

    // =========================== 5.5 — CÁ NHÂN HÓA ========================

    /**
     * Dữ liệu THẬT để thay placeholder — không có dữ liệu thì dùng giá trị
     * trung tính, tuyệt đối không bịa tên/gói/mã.
     *
     * @return array{ten: string, goi_cu: string, uu_dai: string}
     */
    public function buildSendContext(?int $userId, string $psid): array
    {
        $ctx = ['ten' => 'bạn', 'goi_cu' => 'gói VPN', 'uu_dai' => 'ưu đãi đang áp dụng trên website'];

        $contact = $this->fb()->byPsid($psid);
        $firstName = trim((string) ($contact['first_name'] ?? ''));
        if ($firstName !== '') {
            $ctx['ten'] = $firstName;
        }

        if ($userId !== null && $userId > 0) {
            $pdo = FbContact::getPdo();

            // Tên hiển thị từ website (chỉ dùng nếu chưa có tên Messenger).
            $stmt = $pdo->prepare('SELECT * FROM vc_users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            if ($u !== false && $firstName === '') {
                $name = trim((string) ($u['username'] ?? '')) ?: trim((string) ($u['full_name'] ?? ''));
                if ($name !== '') {
                    $ctx['ten'] = $name;
                }
            }

            // Gói khách đã từng dùng (gần nhất) — dữ liệu thật từ vc_subscriptions.
            $stmt = $pdo->prepare(
                'SELECT p.name AS plan_name
                 FROM vc_subscriptions s
                 JOIN vc_vpn_plans p ON p.id = s.plan_id
                 WHERE s.user_id = ?
                 ORDER BY s.end_date DESC
                 LIMIT 1'
            );
            $stmt->execute([$userId]);
            $planName = (string) $stmt->fetchColumn();
            if ($planName !== '') {
                $ctx['goi_cu'] = $planName;
            }
        }

        // Mã ưu đãi đang hoạt động thật (nếu hệ thống có).
        try {
            $coupon = FbContact::getPdo()->query(
                "SELECT code, discount_type, discount_value
                 FROM vc_coupons
                 WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())
                 ORDER BY id ASC
                 LIMIT 1"
            )->fetch();
            if ($coupon !== false) {
                $code = (string) $coupon['code'];
                $ctx['uu_dai'] = $coupon['discount_type'] === 'percent'
                    ? $code . ' (giảm ' . (float) $coupon['discount_value'] . '%)'
                    : $code . ' (giảm ' . number_format((float) $coupon['discount_value']) . 'đ)';
            }
        } catch (\Throwable $e) {
            // Giữ giá trị trung tính sẵn có.
        }

        return $ctx;
    }

    /**
     * Thay placeholder trong template — {ten} {goi_cu} {uu_dai}.
     */
    public function expandPlaceholders(string $body, array $ctx): string
    {
        return strtr($body, [
            '{ten}'     => (string) ($ctx['ten'] ?? 'bạn'),
            '{goi_cu}'  => (string) ($ctx['goi_cu'] ?? 'gói VPN'),
            '{uu_dai}'  => (string) ($ctx['uu_dai'] ?? 'ưu đãi đang áp dụng'),
        ]);
    }

    /**
     * AI soạn nội dung cho chu kỳ monthly của chiến dịch (Bước 5.5).
     * - Grounding = dữ liệu THẬT: template chu kỳ trước + mã ưu đãi DB + map UI;
     * - module fanpage_comment (dùng prompt/một cấu hình AI đã cấu hình sẵn);
     * - THẤT BẠI (AI lỗi/hết quota) → trả null để CampaignService GIỮ template.
     *
     * @param array<string, mixed> $campaign dòng vc_campaigns
     */
    public function composeCycleBody(array $campaign): ?string
    {
        try {
            // Ưu đãi thật — không bịa mã.
            $couponLines = [];
            try {
                foreach (FbContact::getPdo()->query(
                    "SELECT code, discount_type, discount_value FROM vc_coupons
                     WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())
                     ORDER BY id ASC LIMIT 3"
                ) as $c) {
                    $couponLines[] = '- ' . $c['code'] . ($c['discount_type'] === 'percent'
                        ? ' (giảm ' . (float) $c['discount_value'] . '%)'
                        : ' (giảm ' . number_format((float) $c['discount_value']) . 'đ)');
                }
            } catch (\Throwable $e) {
                // Bỏ qua — prompt sẽ ghi "không có mã".
            }
            $promoBlock = $couponLines !== []
                ? implode("\n", $couponLines)
                : '(không có mã ưu đãi đang hoạt động — TUYỆT ĐỐI KHÔNG BỊA MÃ)';

            // Bản đồ trang THẬT (nút/trang đặt ở đâu) — chỉ tham chiếu, không trích dẫn dài.
            $pageMap = mb_substr((string) SiteKnowledge::renderPageMap(true, false, '', ''), 0, 2500);

            $system = 'Bạn là chuyên viên remarketing của vc-vpn-2027 (thuê VPN, bán trong nước).'
                . ' Nhiệm vụ: soạn MỘT tin nhắn Messenger chăm sóc lại khách hàng cũ.'
                . ' NGUYÊN TẮC BẮT BUỘC: (1) chỉ dùng dữ liệu được cung cấp, TUYỆT ĐỐI KHÔNG bịa giá,'
                . ' mã giảm giá, số liệu, thời hạn; (2) ≤500 ký tự, tiếng Việt, không Markdown, không emoji lòe loẹt;'
                . ' (3) giọng thân thiện, đúng tâm lý khách cũ đã từng dùng dịch vụ, có 1 lời kêu gọi hành động rõ ràng;'
                . ' (4) nếu cần nhắc trang/nút thì chỉ nhắc TRONG DANH SÁCH được cung cấp;'
                . ' (5) chỉ trả về nội dung tin nhắn, không phần mở đầu, không chú thích.';

            $user = 'TIÊU ĐỀ CHIẾN DỊCH: ' . (string) ($campaign['title'] ?? '')
                . "\nGỬI ĐẾN: khách hàng cũ (đã từng đăng ký, hết hạn/hủy ≥30 ngày, không mua lại)"
                . "\nKỲ GỬI: tháng " . ((int) ($campaign['cycle_no'] ?? 0))
                . "\n\nMẪU TEMPLATE (chu kỳ trước — sửa lại cho tự nhiên, giữ đúng thông điệp):\n"
                . mb_substr((string) ($campaign['cycle_body'] ?? $campaign['body'] ?? ''), 0, 1500)
                . "\n\nMÃ ƯU ĐÃI THẬT HIỆN CÓ:\n" . $promoBlock
                . "\n\nBẢN ĐỒ TRANG (tham chiếu):\n" . $pageMap;

            $res = (new AIProviderService())->ask(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                null,
                null,
                ['module' => 'fanpage_comment']
            );

            if (!empty($res['ok'])) {
                $text = trim((string) ($res['content'] ?? ''));
                return $text !== '' ? mb_substr($text, 0, 1900) : null;
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
