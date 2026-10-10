<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Knowledge\SiteKnowledge;

/**
 * BƯỚC 4.3 — Tuyến phòng thủ CHỐNG BỊA cho câu trả lời AI trả khách.
 *
 * Nguyên tắc "không bịa 100%":
 *   1. LINK: mọi link trong câu trả lời phải trỏ tới TRANG CÓ THẬT trong
 *      SiteKnowledge::allowedPaths() (bản đồ giao diện) hoặc host ngoài đã
 *      whitelist (site, Google Play, App Store, Facebook, Zalo, YouTube + host
 *      xuất hiện trong snippet dữ liệu thật). Link sai → AUTO: bỏ link, GIỮ
 *      nguyên phần chữ (không bao giờ gửi khách link chết/lừa đảo).
 *   2. GIÁ TIỀN: mọi số tiền có đơn vị (đ/VNĐ/VND/₫) trong câu trả lời phải
 *      xuất hiện trong snippet dữ liệu thật (giá gói, mã giảm giá, nạp/rút…).
 *   Khoản tiền bịa → BLOCK: trả fallback + bàn giao người thật, KHÔNG ghi cache
 *   (để câu bịa không bị tái sử dụng).
 *
 * Cố ý KHÔNG chặn: số ngày, %, dung lượng, số lượt — vì chúng thường đi kèm
 * ngữ cảnh và đã được mô tả trong prompt; chặn quá rộng sẽ làm AI "im lặng".
 */
final class AnswerGuard
{
    public const OK = 'ok';      // không có vấn đề gì
    public const AUTO = 'auto';  // đã tự sửa (gỡ link bịa), vẫn trả được
    public const BLOCK = 'block'; // phát hiện giá bịa → từ chối trả lời

    /** Host ngoài hệ thống được phép nhắc tới trong câu trả lời. */
    private const EXTERNAL_OK = [
        'play.google.com', 'apps.apple.com',
        'facebook.com', 'm.facebook.com', 'm.me', 'fb.me',
        'zalo.me', 'chat.zalo.me', 'l.zalo.me',
        'youtube.com', 'youtu.be',
    ];

    /**
     * @param string $answer    câu trả lời THÔ của model (đã resolve macro)
     * @param string $knowledge snippet sự thật đã gửi cho model (block [1]-[6] + fact tools)
     * @param bool   $isFacebook nguồn fanpage (chỉ để ghi log/issue)
     * @param string $siteUrl   origin site hợp lệ, ví dụ https://example.com
     *
     * @return array{status: string, answer: string, issues: string[]}
     */
    public static function inspect(string $answer, string $knowledge, bool $isFacebook, string $siteUrl): array
    {
        $issues = [];

        if (trim($answer) === '') {
            return ['status' => self::OK, 'answer' => $answer, 'issues' => []];
        }

        $siteHost = strtolower((string) (parse_url($siteUrl, PHP_URL_HOST) ?: ''));
        $allowedHosts = self::allowedHosts($knowledge, $siteHost);
        $paths = SiteKnowledge::allowedPaths();

        // ---------- 1) LINK ----------
        $checkLink = function (string $url) use (&$issues, $siteHost, $allowedHosts, $paths): ?string {
            if (self::linkAllowed($url, $siteHost, $allowedHosts, $paths)) {
                return null; // giữ nguyên
            }
            $issues[] = 'link:' . mb_substr($url, 0, 120);
            return '';
        };

        // Markdown: [nhãn](đường_dẫn) → gỡ link, GIỮ nhãn.
        $out = preg_replace_callback(
            '/\[((?:[^\[\]\\\\]|\\\\.)*)\]\(\s*([^)\s]+)(?:\s+"[^"]*")?\s*\)/u',
            static function (array $m) use ($checkLink): string {
                $fixed = $checkLink((string) $m[2]);
                return $fixed === null ? $m[0] : (string) $m[1];
            },
            $answer
        );
        $out = is_string($out) ? $out : $answer;

        // URL trần còn sót → gỡ hẳn nếu không hợp lệ.
        $out = preg_replace_callback(
            '~https?://[^\s<>()\[\]"\\\']+~iu',
            static function (array $m) use ($checkLink): string {
                $url = rtrim((string) $m[0], '.,;:!?');
                $fixed = $checkLink($url);
                return $fixed === null ? $m[0] : '';
            },
            $out
        );
        $out = is_string($out) ? $out : $answer;

        // ---------- 2) GIÁ TIỀN ----------
        $known = self::knownAmounts($knowledge);
        $priceIssues = [];
        if (preg_match_all('/\d[\d.,]*\s*(?:VNĐ|VND|₫|đ(?=\s|[.,;:!?)\]]|$))/iu', $out, $pm)) {
            foreach ($pm[0] as $token) {
                $digits = preg_replace('/\D/', '', (string) $token);
                if ($digits === '' || strlen($digits) > 12) {
                    continue;
                }
                $key = ltrim($digits, '0') ?: '0';
                if (!isset($known[$key])) {
                    $priceIssues[] = 'price:' . trim((string) $token);
                }
            }
        }

        if ($priceIssues !== []) {
            // Giá bịa = không được phép đoán → chặn toàn bộ câu trả lời.
            return [
                'status' => self::BLOCK,
                'answer' => '',
                'issues' => array_merge($issues, $priceIssues),
            ];
        }

        return [
            'status' => $issues !== [] ? self::AUTO : self::OK,
            'answer' => $out,
            'issues' => $issues,
        ];
    }

    /** Host hợp lệ = host site + host ngoài whitelisted + host xuất hiện trong snippet. */
    private static function allowedHosts(string $knowledge, string $siteHost): array
    {
        $hosts = self::EXTERNAL_OK;
        if ($siteHost !== '') {
            $hosts[] = $siteHost;
            $hosts[] = 'www.' . $siteHost;
        }
        if (preg_match_all('~https?://([^/\s\)\"\'>]+)~iu', $knowledge, $m)) {
            foreach ($m[1] as $h) {
                $h = strtolower(rtrim(trim($h), '.,;'));
                if ($h !== '') {
                    $hosts[] = $h;
                }
            }
        }

        return array_values(array_unique($hosts));
    }

    /** @param string[] $paths */
    private static function linkAllowed(string $url, string $siteHost, array $allowedHosts, array $paths): bool
    {
        $u = trim($url);
        if ($u === '') {
            return false;
        }
        if (str_starts_with($u, '#')) {
            return true; // neo trong trang
        }
        if (preg_match('/^(mailto|tel):/i', $u) === 1) {
            return true;
        }
        if (preg_match('/^javascript:/i', $u) === 1) {
            return false;
        }

        $host = strtolower((string) (parse_url($u, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            // Đường dẫn tương đối phải bắt đầu '/' và nằm trong bản đồ trang.
            if (!str_starts_with($u, '/')) {
                return false;
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: $u);
        } else {
            $hostCmp = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
            $siteCmp = str_starts_with($siteHost, 'www.') ? substr($siteHost, 4) : $siteHost;
            $known = [];
            foreach ($allowedHosts as $h) {
                $known[] = str_starts_with($h, 'www.') ? substr($h, 4) : $h;
            }
            if (!in_array($hostCmp, $known, true)) {
                return false;
            }
            if ($siteCmp === '' || $hostCmp !== $siteCmp) {
                return true; // host ngoài đã whitelist (Play/App Store/FB/Zalo/YT/host từ dữ liệu)
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: '/');
        }

        // Link về SITE → đường dẫn phải có thật trong bản đồ giao diện.
        $path = rtrim($path, '/') ?: '/';
        foreach ($paths as $p) {
            $p = rtrim((string) $p, '/');
            if ($p === '') {
                $p = '/';
            }
            if ($path === $p) {
                return true;
            }
            // Cho phép query/anchor ngay sau trang hợp lệ: /checkout?id=3
            foreach (['?', '#'] as $sep) {
                if ($p !== '/' && str_starts_with($path, $p . $sep)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Tập số ĐÃ CHUẨN HÓA (chỉ chữ số) xuất hiện trong snippet sự thật —
     * mọi số tiền hợp lệ của câu trả lời phải nằm trong tập này.
     *
     * @return array<string, true>
     */
    private static function knownAmounts(string $knowledge): array
    {
        $known = [];
        if (preg_match_all('/\d[\d.,]*/', $knowledge, $m)) {
            foreach ($m[0] as $n) {
                $digits = preg_replace('/\D/', '', (string) $n);
                if ($digits !== '' && strlen($digits) <= 12) {
                    $known[ltrim($digits, '0') ?: '0'] = true;
                }
            }
        }

        return $known;
    }
}
