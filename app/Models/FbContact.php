<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Bước 5.2 — Bản đồ danh tính Messenger (bảng vc_fb_contacts).
 *
 * Mỗi khách nhắn fanpage được ghi nhận một dòng:
 *   - last_interaction_at : cửa sổ 7 ngày của Meta — chỉ khi còn hiệu lực
 *                           thì chiến dịch remarketing mới được phép gửi.
 *   - consent_at          : khách CHỦ ĐỘNG nhắn trước (opt-in ban đầu).
 *   - opted_out_at        : khách gõ STOP/UNSUBSCRIBE/... → cấm mọi chiến dịch
 *                           (UNSTOP/START mới gỡ).
 *   - user_id             : liên kết tài khoản website — tự nạp khi khách
 *                           gửi đúng email đăng ký trong khung chat, nhờ đó
 *                           mới lọc được "khách hàng cũ" (hết hạn/đã hủy).
 */
class FbContact extends BaseModel
{
    protected string $table = 'vc_fb_contacts';

    /** Cửa sổ tin nhắn cho phép của Meta (ngày). */
    public const WINDOW_DAYS = 7;

    /**
     * Ghi nhận một lượt khách nhắn (upsert theo psid+page_id).
     * - Lượt đầu: consent_at = NOW (opt-in vì khách chủ động nhắn).
     * - Mọi lượt: refresh last_interaction_at (mở lại cửa sổ 7 ngày).
     * - user_id chỉ GHI KHI CHƯA CÓ (không bao giờ ghi đè liên kết sẵn).
     */
    public function touch(string $psid, string $pageId = '', string $firstName = '', ?int $userId = null): void
    {
        $psid = trim($psid);
        if ($psid === '') {
            return;
        }
        $stmt = self::getPdo()->prepare(
            'INSERT INTO vc_fb_contacts
                (psid, page_id, user_id, first_name, consent_at, last_interaction_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                last_interaction_at = NOW(),
                consent_at          = COALESCE(consent_at, NOW()),
                first_name          = IF(VALUES(first_name) <> "", VALUES(first_name), first_name),
                user_id             = COALESCE(user_id, VALUES(user_id))'
        );
        $stmt->execute([$psid, $pageId, $userId, mb_substr(trim($firstName), 0, 100)]);
    }

    /** Khách từ chối nhận tin (STOP/UNSUBSCRIBE/...). Idempotent. */
    public function optOut(string $psid, string $pageId = ''): void
    {
        $psid = trim($psid);
        if ($psid === '') {
            return;
        }
        // Đảm bảo dòng tồn tại rồi mới đặt mốc — người lạ gõ STOP cũng được tôn trọng.
        $this->touch($psid, $pageId);
        $stmt = self::getPdo()->prepare(
            'UPDATE vc_fb_contacts SET opted_out_at = COALESCE(opted_out_at, NOW()) WHERE psid = ?'
        );
        $stmt->execute([$psid]);
    }

    /** Khách quay lại đồng ý nhận tin (UNSTOP/START/...). Idempotent. */
    public function optIn(string $psid): void
    {
        $psid = trim($psid);
        if ($psid === '') {
            return;
        }
        $stmt = self::getPdo()->prepare(
            'UPDATE vc_fb_contacts SET opted_out_at = NULL WHERE psid = ?'
        );
        $stmt->execute([$psid]);
    }

    public function isOptedOut(string $psid): bool
    {
        $stmt = self::getPdo()->prepare(
            'SELECT opted_out_at IS NOT NULL FROM vc_fb_contacts WHERE psid = ? LIMIT 1'
        );
        $stmt->execute([trim($psid)]);
        $row = $stmt->fetch();
        return $row !== false && (int) array_values($row)[0] === 1;
    }

    public function byPsid(string $psid): ?array
    {
        $stmt = self::getPdo()->prepare('SELECT * FROM vc_fb_contacts WHERE psid = ? LIMIT 1');
        $stmt->execute([trim($psid)]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Liên kết PSID với tài khoản website khi khách GÕ ĐÚNG email đăng ký
     * trong khung chat (so khớp exact, phân biệt hoa thường bằng LOWER).
     * Chỉ ghi khi liên kết chưa có — trả về user_id nếu vừa link, null nếu không.
     */
    public function tryLinkByEmail(string $psid, string $text): ?int
    {
        $psid = trim($psid);
        if ($psid === '' || !preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $text, $m)) {
            return null;
        }
        $email = strtolower(trim($m[0]));
        $pdo = self::getPdo();
        $stmt = $pdo->prepare("SELECT id FROM vc_users WHERE LOWER(email) = ? AND role = 'user' LIMIT 1");
        $stmt->execute([$email]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId <= 0) {
            return null;
        }
        $upd = $pdo->prepare(
            'UPDATE vc_fb_contacts SET user_id = ? WHERE psid = ? AND user_id IS NULL'
        );
        $upd->execute([$userId, $psid]);
        return $upd->rowCount() > 0 || (int) ($this->byPsid($psid)['user_id'] ?? 0) === $userId
            ? $userId
            : null;
    }

    /** Đánh dấu đã nhận một chiến dịch (dùng cho thống kê "gần nhất"). */
    public function markSent(string $psid): void
    {
        $stmt = self::getPdo()->prepare(
            'UPDATE vc_fb_contacts SET last_sent_at = NOW() WHERE psid = ?'
        );
        $stmt->execute([trim($psid)]);
    }

    /** @return array{psids: array<int, string>, skipped_optout: int, skipped_window: int} Cửa sổ 7 ngày + chưa opt-out. */
    public function windowEligiblePsids(): array
    {
        $pdo = self::getPdo();
        $psids = $pdo->query(
            'SELECT psid FROM vc_fb_contacts
             WHERE opted_out_at IS NULL
               AND last_interaction_at >= NOW() - INTERVAL ' . (int) self::WINDOW_DAYS . ' DAY'
        )->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $opted = (int) $pdo->query(
            'SELECT COUNT(*) FROM vc_fb_contacts WHERE opted_out_at IS NOT NULL'
        )->fetchColumn();
        $stale = (int) $pdo->query(
            'SELECT COUNT(*) FROM vc_fb_contacts
             WHERE opted_out_at IS NULL
               AND last_interaction_at < NOW() - INTERVAL ' . (int) self::WINDOW_DAYS . ' DAY'
        )->fetchColumn();
        return [
            'psids'         => array_values(array_map('strval', $psids)),
            'skipped_optout' => $opted,
            'skipped_window' => $stale,
        ];
    }
}
