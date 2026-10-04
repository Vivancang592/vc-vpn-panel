<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

/**
 * Kho kiến thức LUỒNG HỖ TRỢ AI (chat web / Messenger / bình luận Fanpage).
 *
 * Cung cấp 2 khối dữ liệu để AI trả lời "đúng như người thật":
 *  1. matchGuides()/renderGuides(): TRƯỚC TIÊN dò các bài hướng dẫn
 *     liên quan câu hỏi của khách (nội dung trích từ vc_posts) — nếu có thì
 *     trả lời theo bài; nếu không có mới chuyển sang bước 2.
 *  2. renderPageMap(): bản đồ trang người dùng + nút thao tác cụ thể trên
 *     từng trang, để AI biết khách đang đứng ở đâu, bấm nút nào, dẫn đi đâu.
 *
 * Dữ liệu trang lấy từ khảo sát view thực tế (routes/web.php + resources/views).
 */
final class SiteKnowledge
{
    /**
     * Bản đồ trang người dùng.
     *
     * - login=false → trang công khai (mọi kênh đều được phép gửi link).
     * - login=true  → trang thành viên (CHỈ gửi khi khách đã đăng nhập trên
     *                 chat web; KHÔNG BAO GIỜ gửi trên Facebook).
     * - actions: [nhãn nút/thao tác trên giao diện => hành động thực tế].
     */
    private const PAGES = [
        // ===== NHÓM CÔNG KHAI =====
        ['path' => '/', 'login' => false, 'title' => 'Trang chủ (bảng giá & giới thiệu)', 'actions' => [
            'Bảo vệ kết nối ngay / Xem gói VPN' => 'cuộn tới bảng giá #bang-gia',
            'ĐĂNG KÝ GÓI NÀY / Chọn gói này' => 'mở /checkout?id={id} (chưa đăng nhập sẽ bị đá về /login?redirect=...)',
            'Bắt đầu ngay / Mở bảng điều khiển' => 'đã đăng nhập -> /dashboard, chưa đăng nhập -> /register',
            'Tải ứng dụng VPN' => '/download',
            'Hướng dẫn cài đặt / Blog' => '/faq',
        ]],
        ['path' => '/download', 'login' => false, 'title' => 'Tải ứng dụng (công khai)', 'actions' => [
            'Thẻ tải theo nền tảng (iOS/Android/Windows/macOS...)' => '/client?tag={nen-tang}',
        ]],
        ['path' => '/faq', 'login' => false, 'title' => 'Hỏi đáp & bài viết (FAQ/blog)', 'actions' => [
            'Ô tìm kiếm + nút Tìm kiếm' => 'GET /faq?q=tu-khoa',
            'Thẻ bài / Xem chi tiết' => '/post-detail?slug={slug}',
        ]],
        ['path' => '/post-detail', 'login' => false, 'title' => 'Chi tiết bài viết/hướng dẫn', 'actions' => [
            'Thẻ bài liên quan' => '/post-detail?slug={slug}',
            'Xem Các Bài Viết Khác' => '/faq',
        ]],
        ['path' => '/login', 'login' => false, 'title' => 'Đăng nhập', 'actions' => [
            'ĐĂNG NHẬP' => 'POST /login (email/số điện thoại + mật khẩu)',
            'Đăng nhập bằng Google' => '/auth/google',
            'Quên mật khẩu?' => '/forgot-password',
            'Đăng ký ngay' => '/register',
        ]],
        ['path' => '/register', 'login' => false, 'title' => 'Đăng ký tài khoản', 'actions' => [
            'Gửi mã (OTP xác thực SĐT/email)' => 'POST /register/send-otp',
            'ĐĂNG KÝ' => 'POST /register (nút chỉ bật khi đã nhập đủ & hợp lệ)',
            'Điều khoản dịch vụ / Chính sách riêng tư' => 'mở /terms và /privacy',
            'Đăng nhập' => '/login',
        ]],
        ['path' => '/forgot-password', 'login' => false, 'title' => 'Quên mật khẩu', 'actions' => [
            'Gửi mã OTP' => 'POST /forgot-password/send-otp',
            'CẬP NHẬT MẬT KHẨU' => 'POST /forgot-password',
            'Quay lại Đăng nhập' => '/login',
        ]],
        ['path' => '/terms', 'login' => false, 'title' => 'Điều khoản sử dụng', 'actions' => []],
        ['path' => '/privacy', 'login' => false, 'title' => 'Chính sách bảo mật', 'actions' => []],
        ['path' => '/refund', 'login' => false, 'title' => 'Chính sách hoàn tiền', 'actions' => []],

        // ===== NHÓM SAU ĐĂNG NHẬP =====
        ['path' => '/dashboard', 'login' => true, 'title' => 'Tổng quan thành viên', 'actions' => [
            'Kết nối' => '/subscriptions/detail?id={id} (lấy cấu hình VPN)',
            'Gia hạn' => '/checkout?type=renewal&subscription={id}',
            'Nạp tiền' => '/payments/deposit',
            'Mua gói dịch vụ' => '/user/plans',
            'Tạo ticket hỗ trợ' => '/tickets/create',
            'Xem chi tiết (đơn hàng / ticket)' => '/orders và /tickets',
            'Đăng ký ngay / Chọn gói này (thẻ gói)' => '/checkout?id={id}',
        ]],
        ['path' => '/user/guides', 'login' => true, 'title' => 'Hướng dẫn cài đặt & sử dụng', 'actions' => [
            'Thẻ hướng dẫn / Xem chi tiết' => '/user/guides/detail?slug={slug}',
        ]],
        ['path' => '/user/guides/detail', 'login' => true, 'title' => 'Chi tiết hướng dẫn (thành viên)', 'actions' => [
            'Thẻ bài liên quan' => '/user/guides/detail?slug={slug}',
        ]],
        ['path' => '/user/downloads', 'login' => true, 'title' => 'Tải ứng dụng (thành viên)', 'actions' => [
            'Thẻ tải theo nền tảng' => '/client?tag={nen-tang}',
        ]],
        ['path' => '/profile', 'login' => true, 'title' => 'Hồ sơ cá nhân', 'actions' => [
            'Lưu thay đổi' => 'POST /profile/update (sửa tên, mật khẩu...)',
        ]],
        ['path' => '/user/plans', 'login' => true, 'title' => 'Cửa hàng gói dịch vụ', 'actions' => [
            'Tất cả / tab nhóm server' => 'lọc gói ngay trên trang (không tải lại trang)',
            'Chọn gói này' => '/checkout?id={id}',
        ]],
        ['path' => '/checkout', 'login' => true, 'title' => 'Thanh toán / tạo đơn mua gói', 'actions' => [
            'Ô nhập mã giảm giá + nút Áp dụng' => 'AJAX POST /checkout/coupon (giảm giá ngay tại chỗ)',
            'Nút xác nhận mua' => 'POST /checkout -> tạo đơn hàng',
            'Đơn đang chờ thanh toán' => 'mở /payment/checkout?order={id}',
            'Liên kết Điều khoản / Hoàn tiền' => '/terms và /refund',
        ]],
        ['path' => '/payment/checkout', 'login' => true, 'title' => 'Trang thanh toán đơn (VietQR / số dư)', 'actions' => [
            'Chọn phương thức + xác nhận thanh toán' => 'xử lý by JS trên trang (VietQR chuyển khoản hoặc trừ số dư tài khoản)',
            'Hủy giao dịch nạp tiền' => 'POST /payments/deposit/cancel',
        ]],
        ['path' => '/subscriptions', 'login' => true, 'title' => 'Gói dịch vụ đã mua', 'actions' => [
            'Mua gói dịch vụ / Xem gói dịch vụ' => '/user/plans',
            'Chi tiết' => '/subscriptions/detail?id={id}',
            'Gia hạn' => '/checkout?type=renewal&subscription={id}',
        ]],
        ['path' => '/subscriptions/detail', 'login' => true, 'title' => 'Chi tiết gói & cấu hình kết nối', 'actions' => [
            'Sao chép' => 'copy URL cấu hình kết nối vào clipboard',
            'Mở Karing' => 'mở app Karing qua karing://install-config?url={url}',
            'Lấy mã QR' => 'mở modal QR trên trang',
            'Sao chép (inbound)' => 'copy thông tin inbound',
        ]],
        ['path' => '/orders', 'login' => true, 'title' => 'Lịch sử đơn hàng', 'actions' => [
            'Mua gói dịch vụ' => '/user/plans',
            'Xem chi tiết' => '/orders/detail?id={id}',
            'Thanh toán ngay' => '/payment/checkout?order={id}',
            'Hủy đơn hàng' => 'POST /orders/cancel (có xác nhận)',
        ]],
        ['path' => '/orders/detail', 'login' => true, 'title' => 'Chi tiết đơn hàng', 'actions' => [
            'Thanh toán ngay' => '/payment/checkout?order={id}',
        ]],
        ['path' => '/payments', 'login' => true, 'title' => 'Lịch sử giao dịch thanh toán', 'actions' => [
            'Nạp tiền' => '/payments/deposit',
            'Thanh toán ngay (giao dịch chờ)' => '/payment/checkout?order={id}',
            'Hủy giao dịch (giao dịch nạp chờ xử lý)' => 'POST /payments/deposit/cancel',
        ]],
        ['path' => '/payments/deposit', 'login' => true, 'title' => 'Nạp tiền vào ví (VietQR)', 'actions' => [
            'Số nhanh (chọn mức nạp)' => 'điền sẵn số tiền trên trang',
            'Tiếp tục nạp tiền' => 'POST /payments/deposit -> hiện QR VietQR',
        ]],
        ['path' => '/wallet', 'login' => true, 'title' => 'Ví tiền (số dư)', 'actions' => [
            'Xem giao dịch' => '/payments',
            'Tiếp tục nạp tiền' => '/payments/deposit',
        ]],
        ['path' => '/referrals', 'login' => true, 'title' => 'Tiếp thị liên kết (giới thiệu bạn bè)', 'actions' => [
            'Sao chép link/mã giới thiệu' => 'copy link giới thiệu vào clipboard (nhận hoa hồng đơn hàng thành công)',
        ]],
        ['path' => '/withdrawals', 'login' => true, 'title' => 'Lịch sử rút tiền', 'actions' => [
            'Tạo yêu cầu' => '/withdrawals/create',
        ]],
        ['path' => '/withdrawals/create', 'login' => true, 'title' => 'Tạo yêu cầu rút tiền', 'actions' => [
            'Gửi yêu cầu' => 'POST /withdrawals/create',
        ]],
        ['path' => '/tickets', 'login' => true, 'title' => 'Danh sách yêu cầu hỗ trợ (ticket)', 'actions' => [
            'Tạo yêu cầu' => '/tickets/create',
            'Xem chi tiết' => '/tickets/detail?id={id}',
            'Hủy yêu cầu' => 'POST /tickets/close (có xác nhận)',
        ]],
        ['path' => '/tickets/create', 'login' => true, 'title' => 'Gửi yêu cầu hỗ trợ mới', 'actions' => [
            'Gửi yêu cầu' => 'POST /tickets/create (chủ đề + mô tả lỗi)',
        ]],
        ['path' => '/tickets/detail', 'login' => true, 'title' => 'Chi tiết ticket (trò chuyện hỗ trợ)', 'actions' => [
            'Ô trả lời + nút gửi' => 'POST /tickets/reply',
            'Đóng yêu cầu' => 'POST /tickets/close',
        ]],
        ['path' => '/notifications', 'login' => true, 'title' => 'Thông báo', 'actions' => [
            'Đánh dấu tất cả đã đọc' => 'POST /notifications/read-all',
            'Xóa tất cả / Xóa thông báo' => 'POST /notifications/clear và /notifications/delete',
        ]],
    ];

    /** Menu thành viên (sidebar) — thấy trên MỌI trang sau đăng nhập. */
    private const MEMBER_NAV = 'Tổng quan /dashboard | Gói dịch vụ /user/plans | Đơn hàng /orders | Thanh toán /payments | Ví tiền /wallet | Giới thiệu bạn bè /referrals | Rút tiền /withdrawals | Hỗ trợ /tickets | Hướng dẫn /user/guides | Tải ứng dụng /user/downloads | Hồ sơ /profile';

    /**
     * BƯỚC 1 — dò bài hướng dẫn liên quan câu hỏi của khách.
     *
     * @param string $question Câu hỏi/khách cần trả lời.
     * @param array<int, array<string, mixed>> $posts Dòng vc_posts (getAllPublished).
     * @param int $limit Số bài trả về nhiều nhất.
     * @return array<int, array{title: string, slug: string, type: string, excerpt: string, score: int}>
     */
    public static function matchGuides(string $question, array $posts, int $limit = 3): array
    {
        $tokens = self::tokens($question);
        if ($tokens === [] || $posts === []) {
            return [];
        }

        $scored = [];
        foreach ($posts as $p) {
            $title = (string) ($p['title'] ?? '');
            $content = strip_tags((string) ($p['content'] ?? ''));
            $normTitle = self::normalize($title);
            $normContent = self::normalize($content);

            $score = 0;
            $keywordHits = 0;
            foreach ($tokens as $t) {
                if (str_contains($normTitle, $t)) {
                    $score += 4;
                    $keywordHits++;
                }
                if ($normContent !== '' && str_contains($normContent, $t)) {
                    $score += 1;
                    $keywordHits++;
                }
            }

            // Bài hướng dẫn (tutorial) luôn được ưu tiên nhẹ.
            if (($p['type'] ?? '') === 'tutorial') {
                $score += 2;
            }

            // Bắt buộc có ít nhất 1 từ khoá thật khớp — không được match vì
            // mỗi bonus tutorial.
            if ($keywordHits === 0 || $score < 2) {
                continue;
            }

            $scored[] = [
                'title' => $title,
                'slug' => (string) ($p['slug'] ?? ''),
                'type' => (string) ($p['type'] ?? ''),
                'excerpt' => self::excerpt($content, $tokens),
                'score' => $score,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $limit));
    }

    /**
     * Khối "[1. BÀI HƯỚNG DẪN LIÊN QUAN]" — đưa lên ĐẦU system context.
     *
     * @param array<int, array{title: string, slug: string, type: string, excerpt: string, score: int}> $matches
     */
    public static function renderGuides(array $matches, bool $isFacebook, bool $isLoggedIn, string $siteUrl = ''): string
    {
        $lines = ['[1. BƯỚC 1 - BÀI HƯỚNG DẪN LIÊN QUAN (KIỂM TRA TRƯỚC KHI TRẢ LỜI)]'];

        if ($matches === []) {
            $lines[] = '- KHÔNG có bài hướng dẫn nào liên quan câu hỏi này. Chuyển sang BƯỚC 2: tự phân tích BẢN ĐỒ TRANG + DỮ LIỆU THỰC TẾ bên dưới để trả lời đúng sự thật, không được bịa.';
            return implode("\n", $lines);
        }

        $lines[] = '- Khách đang hỏi khớp với các bài sau. ƯU TIÊN tóm tắt đúng nội dung bài và dẫn khách xem chi tiết:';
        foreach ($matches as $m) {
            $typeLabel = ($m['type'] === 'tutorial') ? 'Hướng dẫn cài đặt' : (($m['type'] === 'faq') ? 'Hỏi đáp' : 'Tin tức');
            $link = self::guideLink($m['slug'], (string) $m['type'], $isFacebook, $isLoggedIn, $siteUrl);
            $lines[] = "- [{$typeLabel}] **{$m['title']}** -> {$link}";
            if ($m['excerpt'] !== '') {
                $lines[] = "  + Tóm tắt nội dung bài: {$m['excerpt']}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Khối "[2. BẢN ĐỒ TRANG & NÚT THAO TÁC]" — thực tế giao diện người dùng.
     */
    public static function renderPageMap(bool $isFacebook, bool $isLoggedIn, string $currentPage = '', string $siteUrl = ''): string
    {
        $current = self::normalizePath($currentPage);
        $lines = ['[2. BẢN ĐỒ TRANG & NÚT THAO TÁC THỰC TẾ CỦA WEBSITE]'];

        if ($isFacebook) {
            $lines[] = '- Khách Facebook CHƯA đăng nhập website: CHỈ được gửi link nhóm CÔNG KHAI bên dưới (URL đầy đủ). TUYỆT ĐỐI KHÔNG gửi đường dẫn trang thành viên (/dashboard, /user/..., /subscriptions, /orders, /payments...).';
        } else {
            $lines[] = '- Dùng để hướng dẫn khách ĐÚNG NÚT/THAO TÁC họ đang thấy trên trang. Dấu (DANG MO) là trang khách đang đứng.';
        }

        $group = $isFacebook ? 'CÔNG KHAI (Facebook được phép gửi)' : 'CÔNG KHAI';
        $lines[] = "\n-- NHÓM {$group} --";
        foreach (self::PAGES as $page) {
            if ($page['login']) {
                continue;
            }
            $lines[] = self::renderPageLine($page, $isFacebook, $isLoggedIn, $current, $siteUrl);
        }

        if (!$isFacebook) {
            if ($isLoggedIn) {
                $lines[] = "\n-- NHÓM SAU ĐĂNG NHẬP (CHỈ dùng khi khách ĐÃ đăng nhập) --";
                foreach (self::PAGES as $page) {
                    if (!$page['login']) {
                        continue;
                    }
                    $lines[] = self::renderPageLine($page, $isFacebook, $isLoggedIn, $current, $siteUrl);
                }
                $lines[] = "\n-- MENU THANH VIÊN (sidebar, thấy trên mọi trang sau đăng nhập) --";
                $lines[] = '- ' . self::MEMBER_NAV;
            } else {
                $lines[] = "\n- Khách CHƯA đăng nhập: nhóm trang thành viên (/dashboard, /user/..., /subscriptions, /orders, /payments, /wallet, /tickets...) YÊU CẦU đăng nhập — hướng khách đăng ký/đăng nhập trước, KHÔNG gửi link trang thành viên.";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Khối "[0. QUY TRÌNH TRẢ LỜI]" — thứ tự tư duy bắt buộc: hướng dẫn
     * trước, thiếu mới tự suy luận từ thực tế, bám trang khách đang mở,
     * thấu cảm để chốt đơn.
     */
    public static function renderProcedure(bool $isFacebook): string
    {
        $lines = [
            '[0. QUY TRÌNH TRẢ LỜI BẮT BUỘC (THỨ TỰ ƯU TIÊN)]',
            '1. ĐỌC KHỐI "BÀI HƯỚNG DẪN LIÊN QUAN" TRƯỚC: nếu khách hỏi đúng chủ đề có bài hướng dẫn -> trả lời theo tóm tắt nội dung bài đó (các bước cụ thể) và gửi kèm link bài.',
            '2. KHÔNG có bài liên quan -> tự trả lời dựa trên BẢN ĐỒ TRANG + DỮ LIỆU THỰC TẾ (gói cước, mã giảm giá, chính sách) bên dưới. Chỉ nói những gì hệ thống thật sự có; thiếu thông tin thì thành thật và mời hỗ trợ 1-1.',
            '3. BÁM TRANG KHÁCH ĐANG MỞ: nếu dòng "Khách đang xem trang" xuất hiện, ưu tiên hướng dẫn ngay thao tác trên TRANG ĐÓ (gọi đúng tên nút khách thấy), chỉ dẫn sang trang khác khi thật sự cần.',
            '4. THẤM HIỂU Ý ĐỊNH ĐỂ CHỐT ĐƠN: nhận diện nhu cầu rồi phản hồi hợp lý —',
            '   - Hỏi giá / so sánh gói: báo đúng giá + nêu khác biệt chính (băng thông, số thiết bị) + gợi ý gói phù hợp nhu cầu.',
            '   - Có ý định mua / phân vân: nhắc mã giảm giá đang hoạt động (nếu có) + chỉ dẫn đúng nút "Chọn gói này" -> ô "Áp dụng" mã -> xác nhận mua để chốt ngay.',
            '   - Hỏi cách cài / lỗi kỹ thuật: trả lời từng bước ngắn gọn, trấn an, dẫn link hướng dẫn; nếu là lỗi thật -> hướng mở ticket/inbox.',
            '   - Phàn nàn / khiếu nại: đồng cảm, xin lỗi, không biện minh, chuyển hỗ trợ viên kịp thời.',
            '   - Chào hỏi đơn giản: chào thân thiện và hỏi ngay nhu cầu (cần mua gói, cài đặt, hay hỗ trợ lỗi?).',
            '- Không hỏi dồn khách; mỗi lượt gợi ý TỐI ĐA một bước tiếp theo rõ ràng.',
        ];
        if ($isFacebook) {
            $lines[] = '- Kênh Facebook: văn bản thuần, URL đầy đủ dạng https://..., KHÔNG dùng Markdown/macro.';
        } else {
            $lines[] = '- Kênh Chat website: dùng Markdown; đường dẫn trang viết dạng tuyệt đối (/orders, /user/plans...) hoặc macro hệ thống nếu file prompt yêu cầu.';
        }

        return implode("\n", $lines);
    }

    /**
     * Đường dẫn bài hướng dẫn theo kênh + trạng thái đăng nhập.
     */
    private static function guideLink(string $slug, string $type, bool $isFacebook, bool $isLoggedIn, string $siteUrl): string
    {
        // Bài tutorial có trang riêng cho thành viên; FAQ/tin tức luôn qua /post-detail.
        if (!$isFacebook && $isLoggedIn && $type === 'tutorial') {
            return '/user/guides/detail?slug=' . rawurlencode($slug);
        }

        $path = '/post-detail?slug=' . rawurlencode($slug);

        if ($isFacebook) {
            $base = rtrim($siteUrl, '/');
            return $base !== '' ? $base . $path : $path;
        }

        return $path;
    }

    private static function renderPageLine(array $page, bool $isFacebook, bool $isLoggedIn, string $current, string $siteUrl): string
    {
        $path = (string) $page['path'];
        $mark = '';
        if (!$isFacebook && $current !== '' && self::normalizePath($path) === $current) {
            $mark = ' (DANG MO)';
        }

        $display = $path === '/' ? '/' : $path;
        if ($isFacebook) {
            $base = rtrim($siteUrl, '/');
            $display = ($base !== '' ? $base : '') . $path;
        }

        $line = "- **{$page['title']}** [{$display}]{$mark}";
        foreach ($page['actions'] as $label => $action) {
            $line .= "\n    + {$label} -> {$action}";
        }

        return $line;
    }

    /**
     * Trích đoạn quanh từ khóa khớp đầu tiên trong nội dung bài viết.
     */
    private static function excerpt(string $content, array $tokens): string
    {
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? '');
        if ($content === '') {
            return '';
        }

        $norm = self::normalize($content);
        $pos = 0;
        foreach ($tokens as $t) {
            $hit = mb_strpos($norm, $t);
            if ($hit !== false) {
                $pos = $hit;
                break;
            }
        }

        // mb_strpos trên chuỗi đã normalize cùng chiều với bản gốc (giữ nguyên
        // khoảng trắng) — lấy cửa sổ quanh vị trí khớp.
        $start = max(0, $pos - 150);
        $excerpt = mb_substr($content, $start, 700);
        if ($start > 0) {
            $excerpt = '...' . $excerpt;
        }
        if (mb_strlen($content) > $start + 700) {
            $excerpt .= '...';
        }

        return $excerpt;
    }

    /**
     * Từ khoá tìm kiếm: bỏ dấu tiếng Việt, chữ thường, token >= 3 ký tự.
     *
     * @return array<int, string>
     */
    private static function tokens(string $text): array
    {
        $normalized = self::normalize($text);
        $parts = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stopWords = [
            'the', 'va', 'cua', 'cho', 'khong', 'co', 'cac', 'mot', 'toi', 'ban',
            'nhu', 'nao', 'khi', 'nay', 'kia', 'gio', 'lam', 'duoc', 'phan',
            'ho', 'minh', 'anh', 'em', 'la', 've',
        ];

        $tokens = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) < 3 || in_array($p, $stopWords, true)) {
                continue;
            }
            $tokens[] = $p;
        }

        return array_values(array_unique($tokens));
    }

    /** Bỏ dấu tiếng Việt + chữ thường. */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        // Bỏ dấu tiếng Việt (TRANSLIT phụ thuộc ICU — bỏ qua lỗi).
        $text = @iconv('UTF-8', 'UTF-8//TRANSLIT//IGNORE', $text) ?: $text;
        if (preg_match('/\p{Mn}/u', $text) === 1) {
            $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
            $text = mb_strtolower((string) $text);
        }

        // Bọc tường minh các ký tự tiếng Việt hay gặp.
        $text = strtr($text, [
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'đ' => 'd',
            'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ]);

        return $text;
    }

    /** Chuẩn hóa đường dẫn trang để so khớp ('home' / 'orders' / '/orders/' -> '/orders'). */
    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || strtolower($path) === 'home') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}
