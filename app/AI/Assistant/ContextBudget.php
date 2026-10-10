<?php

declare(strict_types=1);

namespace App\AI\Assistant;

use App\Models\Setting;

/**
 * ContextBudget — ngân sách ngữ cảnh + bộ tóm tắt tích lũy cho Trợ Lý Admin.
 *
 * Vấn đề: buildMessages() cũ gửi TOÀN BỘ lịch sử, hội thoại dài sẽ vượt cửa sổ
 * model (bị cắt giữa chừng, AI "quên" đầu cuộc trò chuyện).
 *
 * Cách xử lý:
 *  1. Ước lượng token (heuristics 4 ký tự/token — chưa có tokenizer chính thức).
 *  2. Cửa sổ trượt: luôn giữ system prompt + N tin MỚI NHẤT trong ngân sách.
 *  3. Phần tin bị đẩy ra ngoài → dựng BẢN TÓM TẮT TÍCH LŨY (digest) đặt vào
 *     system prompt, lưu ở vc_ai_conversation_state → AI vẫn nhớ dữ kiện cũ
 *     từ đầu cuộc hội thoại mà không nổ token.
 *
 * Digest xây dựng TỪ DỮ LIỆU THẬT của tin nhắn đã xảy ra (không gọi model thêm)
 * → không thể bịa thông tin, không tốn phí.
 */
final class ContextBudget
{
    /** 4 ký tự ~ 1 token (ước lượng thận trọng cho tiếng Việt có dấu). */
    public const CHARS_PER_TOKEN = 4;

    /** Ngân sách token mặc định cho system + lịch sử (đã trừ phần trả lời). */
    public const DEFAULT_BUDGET = 24000;

    /** Chặn dưới: model kém cửa sổ hơn cũng không bị cắt quá sâu. */
    public const MIN_BUDGET = 4000;

    /** Chặn trên: tránh gửi hàng trăm nghìn token gây chậm/hết hạn. */
    public const MAX_BUDGET = 200000;

    /** Số tin mới nhất LUÔN giữ, kể cả khi vượt ngân sách (tối thiểu 2 cặp). */
    public const MIN_KEEP_MESSAGES = 6;

    /** Giới hạn độ dài bản tóm tắt tích lũy. */
    public const MAX_DIGEST_CHARS = 9000;

    /** Khóa setting cho phép admin chỉnh ngân sách ngay trong admin. */
    public const SETTING_KEY = 'ai_context_budget';

    private static ?int $cachedBudget = null;

    /** Ước lượng token của một chuỗi. */
    public static function estimateTokens(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        // mb_strlen: tính theo ký tự unicode (tiếng Việt 1 ký tự = 1, không bị
        // đếm gấp 3 như strlen trên UTF-8).
        return (int) ceil(mb_strlen($text, 'UTF-8') / self::CHARS_PER_TOKEN);
    }

    /**
     * Ước lượng token của content (chuỗi hoặc mảng multimodal vision).
     *
     * @param mixed $content
     */
    public static function estimateContent($content): int
    {
        if (is_array($content)) {
            $tokens = 0;
            foreach ($content as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $type = (string) ($part['type'] ?? '');
                if ($type === 'text') {
                    $tokens += self::estimateTokens((string) ($part['text'] ?? ''));
                } else {
                    // Ảnh: ước lượng cố định theo chiều dài data URI (an toàn).
                    $data = (string) ($part['image_url']['url'] ?? '');
                    $tokens += $data !== ''
                        ? max(600, (int) ceil(strlen($data) / 60))
                        : 600;
                }
            }
            return $tokens;
        }
        return self::estimateTokens((string) $content);
    }

    /**
     * Ngân sách hiện hành: setting ai_context_budget (đơn vị token) nếu hợp lệ,
     * ngược lại DEFAULT_BUDGET. Đọc lỗi → mặc định, không chặn luồng chat.
     */
    public static function budget(): int
    {
        if (self::$cachedBudget !== null) {
            return self::$cachedBudget;
        }
        $raw = null;
        try {
            $raw = (new Setting())->get(self::SETTING_KEY);
        } catch (\Throwable $e) {
            $raw = null;
        }
        $value = (int) trim((string) ($raw ?? ''));
        self::$cachedBudget = ($value >= self::MIN_BUDGET && $value <= self::MAX_BUDGET)
            ? $value
            : self::DEFAULT_BUDGET;

        return self::$cachedBudget;
    }

    /** Cho kiểm thử: nạp lại setting. */
    public static function resetCache(): void
    {
        self::$cachedBudget = null;
    }

    /**
     * Tính cửa sổ lịch sử vừa ngân sách.
     *
     * @param array<int, array{role: string, content: mixed}> $history
     * @return array{start: int, tokens: int, dropped: int, kept: int}
     *        start = chỉ số tin đầu tiên được giữ (0 = giữ toàn bộ)
     */
    public static function window(string $system, array $history, int $extraTokens = 0, ?int $budget = null): array
    {
        $budget = $budget ?? self::budget();
        $count = count($history);
        if ($count === 0) {
            return ['start' => 0, 'tokens' => self::estimateTokens($system) + $extraTokens, 'dropped' => 0, 'kept' => 0];
        }

        // Ngân sách tính cả system prompt + phần sinh thêm (tool result, digest).
        $base = self::estimateTokens($system) + $extraTokens;
        $tokens = [];
        foreach ($history as $i => $msg) {
            $tokens[$i] = self::estimateContent($msg['content'] ?? '') + 4; // 4 token/độ dài khung role
        }

        // Lấy từ tin MỚI NHẤT ngược về, dừng khi vượt ngân sách nhưng luôn
        // giữ tối thiểu MIN_KEEP_MESSAGES tin cuối.
        $start = 0;
        $used = $base;
        $kept = 0;
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($used + $tokens[$i] > $budget && $kept >= self::MIN_KEEP_MESSAGES) {
                $start = $i + 1;
                break;
            }
            $used += $tokens[$i];
            $kept++;
            $start = $i;
        }

        // Chốt an toàn: không bao giờ bỏ nhiều hơn (count - MIN_KEEP_MESSAGES).
        $floor = max(0, $count - self::MIN_KEEP_MESSAGES);
        if ($start > $floor) {
            $start = $floor;
        }

        $used = $base;
        for ($i = $start; $i < $count; $i++) {
            $used += $tokens[$i];
        }

        return [
            'start'   => $start,
            'tokens'  => $used,
            'dropped' => $start,
            'kept'    => $count - $start,
        ];
    }

    /**
     * Dựng digest từ các tin bị đẩy ra ngoài cửa sổ.
     *
     * Ưu tiên giữ LỜI CỦA ADMIN (mọi dòng ADMIN); khi quá ngân sách sẽ nén bớt
     * dòng TRỢ LÝ ở giữa — đảm bảo yêu cầu "nhớ toàn bộ hội thoại từ đầu".
     *
     * @param array<int, array{role: string, content: mixed}> $messages
     */
    public static function digest(array $messages, int $maxChars = self::MAX_DIGEST_CHARS): string
    {
        $lines = [];
        foreach ($messages as $msg) {
            $role = (string) ($msg['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $text = self::plainText($msg['content'] ?? '');
            if ($text === '') {
                continue;
            }
            // Tin của admin: giữ gần như nguyên văn vì lệnh của admin là dữ kiện
            // quan trọng nhất; tin trợ lý: chỉ lấy ý chính.
            $limit = $role === 'user' ? 420 : 200;
            $lines[] = [
                'role' => $role,
                'text' => ($role === 'user' ? 'ADMIN: ' : 'TRỢ LÝ: ') . self::oneLine($text, $limit),
            ];
        }

        if ($lines === []) {
            return '';
        }

        // Vượt chỗ → xoá dòng TRỢ LÝ gần giữa nhất (giữ mở đầu + kết phần tóm tắt).
        while (self::digestChars($lines) > $maxChars) {
            $victim = self::pickAssistantLine($lines);
            if ($victim < 0) {
                break; // chỉ còn dòng admin → chấp nhận dài
            }
            array_splice($lines, $victim, 1);
        }

        $out = '';
        foreach ($lines as $l) {
            $candidate = $out === '' ? $l['text'] : $out . "\n" . $l['text'];
            if (mb_strlen($candidate, 'UTF-8') > $maxChars) {
                break;
            }
            $out = $candidate;
        }

        return $out;
    }

    /** @param array<int, array{role: string, text: string}> $lines */
    private static function digestChars(array $lines): int
    {
        return mb_strlen(implode("\n", array_map(static fn(array $l): string => $l['text'], $lines)), 'UTF-8');
    }

    /**
     * Chỉ số dòng TRỢ LÝ gần giữa mảng nhất (ứng viên cắt đầu tiên), -1 nếu không còn.
     *
     * @param array<int, array{role: string, text: string}> $lines
     */
    private static function pickAssistantLine(array $lines): int
    {
        $n = count($lines);
        $mid = intdiv($n, 2);
        for ($d = 0; $d < $n; $d++) {
            foreach ([$mid - $d, $mid + $d] as $i) {
                if ($i >= 0 && $i < $n && $lines[$i]['role'] === 'assistant') {
                    return $i;
                }
            }
        }

        return -1;
    }

    /**
     * Ghép khối "NGỮ CẢNH HỘI THOẠI TRƯỚC ĐÓ" cho system prompt.
     * Trả về chuỗi rỗng khi chưa có digest.
     */
    public static function digestBlock(string $digest, int $coveredThrough): string
    {
        $digest = trim($digest);
        if ($digest === '') {
            return '';
        }

        return "\n### NGỮ CẢNH HỘI THOẠI TRƯỚC ĐÓ (đã tóm tắt từ " . $coveredThrough . " tin đầu)\n"
            . 'Đây là dữ kiện THẬT của các lượt nói chuyện trước (đã trôi ra ngoài cửa sổ token). '
            . "Hãy coi như mình nhớ rõ toàn bộ cuộc trò chuyện:\n"
            . $digest;
    }

    /** Nội dung multimodal → chuỗi thuần cho digest/ước lượng. */
    private static function plainText($content): string
    {
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && (string) ($part['type'] ?? '') === 'text') {
                    $parts[] = (string) ($part['text'] ?? '');
                }
            }
            return trim(implode(' ', $parts));
        }
        return trim((string) $content);
    }

    /** Nén khoảng trắng + cắt an toàn theo ký tự UTF-8. */
    private static function oneLine(string $text, int $limit): string
    {
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . '…';
    }
}
