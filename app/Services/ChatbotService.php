<?php

namespace App\Services;

use App\Models\ChatEvent;
use App\Models\ChatAiCache;
use App\Models\Coupon;
use App\Models\Post;
use App\Models\Setting;
use App\Models\VpnPlan;

class ChatbotService
{
    private array $settings;
    private AIProviderService $provider;

    public function __construct()
    {
        $this->settings = (new Setting())->getAllAsKeyValue();
        $this->provider = new AIProviderService();
    }

    public function reply(string $message, array $context = []): array
    {
        $enabledRaw = trim((string) ($this->settings['ai_chatbot_enabled'] ?? '1'));
        $isEnabled = ($enabledRaw === '' || $enabledRaw === '1');
        if (!$isEnabled) {
            return [
                'success' => true,
                'answer' => 'Trợ lý AI hiện đang tạm tắt. Vui lòng liên hệ fanpage để được hỗ trợ trực tiếp.',
                'handoff' => true,
                'provider' => 'disabled',
                'model' => '',
                'cta' => [
                    'label' => 'Mở fanpage hỗ trợ',
                    'url' => $this->resolveSupportUrl()
                ],
            ];
        }

        $message = trim($message);
        if ($message === '') {
            return $this->fallbackResponse('Bạn hãy nhập câu hỏi cụ thể để mình hỗ trợ nhanh nhất nhé.');
        }

        $history = $context['history'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

        $isLoggedIn = !empty($context['is_logged_in']) || (!empty($context['user_id']) && (int) $context['user_id'] > 0);
        $context['is_logged_in'] = $isLoggedIn;

        $systemPrompt = $this->buildSystemPrompt($context);
        $knowledge = $this->buildKnowledgeSnippet($context);
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'system', 'content' => $knowledge],
        ];

        foreach (array_slice($history, -8) as $item) {
            $role = (string) ($item['role'] ?? 'user');
            $content = trim((string) ($item['content'] ?? ''));
            if ($content !== '') {
                $content = mb_substr($content, 0, 500);
            }
            if ($content !== '' && in_array($role, ['user', 'assistant'], true)) {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 800)];

        $cacheTtl = (int) ($this->settings['ai_cache_ttl_minutes'] ?? 60);
        $cacheTtl = max(1, min(1440, $cacheTtl));
        $cacheKey = sha1(($isLoggedIn ? 'auth_' : 'guest_') . mb_strtolower(preg_replace('/\s+/', ' ', trim($message))));

        if (class_exists(ChatAiCache::class)) {
            $cached = (new ChatAiCache())->findFresh($cacheKey, $cacheTtl);
            if ($cached && !empty($cached['answer'])) {
                return [
                    'success' => true,
                    'answer' => (string) $cached['answer'],
                    'handoff' => $this->shouldHandoff($message, (string) $cached['answer']),
                    'provider' => 'cache',
                    'model' => (string) ($cached['model'] ?? ''),
                    'cta' => $this->buildCta($context),
                ];
            }
        }

        $preferredProvider = strtolower(trim((string) ($this->settings['ai_provider'] ?? 'gemini')));
        $result = $this->provider->ask($messages, $preferredProvider);

        if (!$result['ok']) {
            $fallbackProvider = $preferredProvider === 'openai' ? 'gemini' : 'openai';
            $result = $this->provider->ask($messages, $fallbackProvider);
        }

        if (!$result['ok']) {
            $this->logEvent('ai_failed', [
                'provider' => $preferredProvider,
                'error' => (string) ($result['error'] ?? 'unknown'),
                'source' => (string) ($context['source'] ?? 'web'),
                'session_id' => (int) ($context['session_id'] ?? 0),
                'user_id' => (int) ($context['user_id'] ?? 0),
            ]);
            return $this->fallbackResponse('Hệ thống AI đang xử lý lượng yêu cầu lớn. Bạn có thể để lại câu hỏi hoặc liên hệ hỗ trợ trực tiếp để được phục vụ ngay nhé.');
        }

        $answer = trim((string) ($result['content'] ?? ''));
        $handoff = $this->shouldHandoff($message, $answer);

        if ($answer !== '' && class_exists(ChatAiCache::class)) {
            (new ChatAiCache())->upsert(
                $cacheKey,
                $message,
                $answer,
                (string) ($result['provider'] ?? ''),
                (string) ($result['model'] ?? '')
            );
        }

        $response = [
            'success' => true,
            'answer' => $answer,
            'handoff' => $handoff,
            'provider' => (string) ($result['provider'] ?? $preferredProvider),
            'model' => (string) ($result['model'] ?? ''),
            'cta' => $this->buildCta($context),
        ];

        $this->logEvent('ai_reply', [
            'provider' => $response['provider'],
            'handoff' => $handoff ? 1 : 0,
            'source' => (string) ($context['source'] ?? 'web'),
            'session_id' => (int) ($context['session_id'] ?? 0),
            'user_id' => (int) ($context['user_id'] ?? 0),
        ]);

        return $response;
    }

    private function buildSystemPrompt(array $context): string
    {
        $siteTitle = $this->settings['site_title'] ?? 'VC VPN';
        $isLoggedIn = !empty($context['is_logged_in']);
        $userName = !empty($context['user_name']) ? (string) $context['user_name'] : '';

        $promptParts = [
            "Bạn là chuyên viên tư vấn bán hàng & hỗ trợ kỹ thuật trực tuyến xuất sắc của hệ thống {$siteTitle}.",
            "TÍNH CÁCH VÀ PHONG CÁCH GIAO TIẾP:",
            "- Nói chuyện tự nhiên, nhiệt tình, ấm áp và chu đáo như một con người thực thụ (xưng 'mình' hoặc 'em', gọi khách là 'bạn' hoặc 'anh/chị').",
            "- Trả lời ĐÚNG TRỌNG TÂM, giải thích rõ ràng, thấu hiểu nhu cầu của khách hàng trước khi đưa ra gợi ý hành động.",
            "- Tuyệt đối KHÔNG chỉ quăng một đường link cụt lủn. Luôn luôn diễn giải, tư vấn giá trị gói hoặc hướng dẫn từng bước trước, sau đó mới khéo léo đính kèm đường link thao tác.",
            "- Định dạng văn bản đẹp bằng Markdown: in đậm (**tên gói, giá tiền, mã giảm giá**), danh sách gạch đầu dòng rõ ràng, liên kết dạng [Tên liên kết](đường_dẫn).",
            "",
            "QUY TẮC ĐIỀU HƯỚNG LIÊN KẾT (BẮT BUỘC TUÂN THỦ NGHIÊM NGẶT):",
            $isLoggedIn
                ? "- Người dùng HIỆN ĐÃ ĐĂNG NHẬP" . ($userName ? " (Tài khoản: {$userName})" : "") . ": BẮT BUỘC chỉ được dẫn link vào các trang nội bộ của thành viên (/user/...):\n"
                  . "  + Mua gói dịch vụ / xem bảng giá: [Xem & Mua Gói Dịch Vụ](/user/plans)\n"
                  . "  + Gói dịch vụ đang sở hữu / lấy cấu hình kết nối: [Gói Dịch Vụ Của Tôi](/subscriptions)\n"
                  . "  + Hướng dẫn cài đặt & sử dụng: [Hướng Dẫn Cài Đặt](/user/guides)\n"
                  . "  + Tải app / phần mềm kết nối: [Tải Ứng Dụng VPN](/user/downloads)\n"
                  . "  + Nạp tiền vào ví: [Nạp Tiền Vào Ví](/payments/deposit)\n"
                  . "  + Gửi yêu cầu hỗ trợ kỹ thuật: [Gửi Ticket Hỗ Trợ](/tickets/create)\n"
                  . "  + Xem lịch sử đơn hàng: [Đơn Hàng Của Tôi](/orders)\n"
                  . "  + Tiếp thị liên kết nhận hoa hồng: [Tiếp Thị Liên Kết](/referrals)\n"
                  . "  * TUYỆT ĐỐI KHÔNG gửi link /login, /register hay /#bang-gia cho người dùng đã đăng nhập."
                : "- Người dùng HIỆN CHƯA ĐĂNG NHẬP (Khách vãng lai): BẮT BUỘC chỉ được dẫn link ra các trang công khai ngoài website:\n"
                  . "  + Xem bảng giá & tính năng các gói: [Xem Bảng Giá Gói](/#bang-gia)\n"
                  . "  + Đăng ký tài khoản mới: [Đăng Ký Tài Khoản](/register)\n"
                  . "  + Đăng nhập tài khoản: [Đăng Nhập](/login)\n"
                  . "  + Hướng dẫn & Câu hỏi thường gặp: [Câu Hỏi Thường Gặp (FAQ)](/faq)\n"
                  . "  + Tải app / phần mềm kết nối: [Tải Ứng Dụng](/download)\n"
                  . "  + Điều khoản sử dụng: [Điều Khoản Sử Dụng](/terms)\n"
                  . "  + Chính sách hoàn tiền: [Chính Sách Hoàn Tiền](/refund)\n"
                  . "  * TUYỆT ĐỐI KHÔNG gửi link /user/... hay /subscriptions cho người dùng chưa đăng nhập vì họ sẽ bị chặn.",
            "",
            "KỸ NĂNG BÁN HÀNG & CHỐT ĐƠN (SALES & UPSELL):",
            "- Khi khách hàng hỏi về giá cả, hỏi mua gói, hoặc phân vân giữa các gói:",
            "  1. Tư vấn chi tiết gói phù hợp với nhu cầu (ví dụ gói theo tháng, theo năm, thiết bị, dung lượng).",
            "  2. KIỂM TRA danh sách 'Mã giảm giá đang hoạt động' trong dữ liệu nội bộ được cung cấp:",
            "     + NẾU CÓ mã giảm giá: Hãy CHỦ ĐỘNG quảng cáo và tặng mã cho khách (ví dụ: 'Đặc biệt hôm nay hệ thống đang có mã ưu đãi **`MÃ_CODE`** giảm **X%**, bạn nhớ áp dụng ở bước thanh toán để được giá tốt nhất nhé!').",
            "     + NẾU KHÔNG CÓ mã giảm giá nào: Nói rõ hiện chưa có mã giảm giá phụ, nhưng giá niêm yết hiện tại đã rất cạnh tranh và tối ưu chi phí.",
            "",
            "NGUYÊN TẮC TRUNG THỰC & CHÍNH XÁC (GROUNDING):",
            "- CHỈ được sử dụng thông tin gói cước, giá bán, mã giảm giá, chính sách từ phần DỮ LIỆU NỘI BỘ bên dưới.",
            "- TUYỆT ĐỐI KHÔNG tự bịa đặt gói cước không tồn tại, giá tiền sai lệch, hoặc mã giảm giá giả mạo.",
            "- Hướng dẫn cài đặt theo từng hệ điều hành: iOS (Shadowrocket, V2Ray), Android (V2rayNG, Sing-box), Windows (v2rayN, Clash Verge), macOS, Android TV,... đúng theo quy trình chuẩn.",
            "- Vấn đề hoàn tiền: Trả lời đúng theo Chính Sách Hoàn Tiền (được xem xét nếu lỗi kỹ thuật máy chủ không khắc phục được hoặc thanh toán trùng; không áp dụng khi đã dùng bình thường hoặc đổi ý cá nhân).",
            "- Nếu gặp khiếu nại phức tạp, lỗi chuyển khoản ngân hàng nhiều lần, hoặc khách muốn gặp nhân viên: Hướng dẫn khách để lại thông tin hoặc nhắn qua kênh Fanpage/Ticket hỗ trợ."
        ];

        $customPrompt = trim((string) ($this->settings['ai_system_prompt'] ?? ''));
        if ($customPrompt !== '') {
            $promptParts[] = "\nHƯỚNG DẪN BỔ SUNG TỪ QUẢN TRỊ VIÊN:\n" . $customPrompt;
        }

        return implode("\n", $promptParts);
    }

    private function buildKnowledgeSnippet(array $context): string
    {
        $siteTitle = $this->settings['site_title'] ?? 'VC VPN';
        $contactEmail = $this->settings['contact_email'] ?? '';
        $fanpageUrl = $this->settings['fanpage_url'] ?? '';
        $zaloUrl = $this->settings['zalo_url'] ?? '';
        $minDeposit = number_format((float) ($this->settings['min_deposit_amount'] ?? $this->settings['min_deposit'] ?? 10000), 0, '.', ',') . ' đ';
        $minWithdrawal = number_format((float) ($this->settings['min_withdrawal'] ?? 50000), 0, '.', ',') . ' đ';
        $commissionRate = ($this->settings['commission_rate'] ?? '30') . '%';
        $referralBonus = number_format((float) ($this->settings['referral_bonus'] ?? 10000), 0, '.', ',') . ' đ';

        $trialEnabled = ($this->settings['trial_enabled'] ?? '0') === '1';
        $trialDuration = (int) ($this->settings['trial_duration_days'] ?? 3);

        $lines = ["=== DỮ LIỆU NỘI BỘ HỆ THỐNG {$siteTitle} ==="];

        // 1. Danh sách Gói Cước VPN
        $planModel = new VpnPlan();
        $plans = $planModel->getAllActiveWithGroup();
        if (empty($plans)) {
            $plans = $planModel->getAllActive();
        }

        $lines[] = "\n[1. DANH SÁCH GÓI DỊCH VỤ VPN ĐANG BÁN]:";
        if (!empty($plans)) {
            foreach ($plans as $plan) {
                $priceFormatted = number_format((float) ($plan['price'] ?? 0), 0, '.', ',') . ' VND';
                $bandwidth = empty($plan['bandwidth_limit_gb']) || (int) $plan['bandwidth_limit_gb'] === 0
                    ? 'Không giới hạn dung lượng'
                    : ((int) $plan['bandwidth_limit_gb'] . ' GB/tháng');
                $devices = (int) ($plan['max_devices'] ?? 1) . ' thiết bị dùng cùng lúc';
                $duration = (int) ($plan['duration_days'] ?? 30) . ' ngày';
                $groupName = !empty($plan['group_name']) && $plan['group_name'] !== 'Chưa chọn nhóm' ? " | Server: {$plan['group_name']}" : '';
                $desc = !empty($plan['description']) ? (" | Chi tiết: " . trim((string) $plan['description'])) : '';

                $lines[] = "• **{$plan['name']}** (Mã: {$plan['code']}): Giá {$priceFormatted} | Hạn dùng: {$duration} | Băng thông: {$bandwidth} | Tối đa: {$devices}{$groupName}{$desc}";
            }
        } else {
            $lines[] = "- Hiện tại hệ thống đang cập nhật bảng giá gói mới. Khách hàng vui lòng theo dõi trên website hoặc liên hệ hỗ trợ.";
        }

        if ($trialEnabled) {
            $lines[] = "• **Chính sách Dùng Thử**: Có gói dùng thử miễn phí {$trialDuration} ngày dành cho tài khoản mới đăng ký lần đầu.";
        }

        // 2. Danh sách Mã Giảm Giá Đang Hoạt Động
        $couponModel = new Coupon();
        $activeCoupons = $couponModel->getActiveCoupons();

        $lines[] = "\n[2. MÃ GIẢM GIÁ ĐANG HOẠT ĐỘNG (DÙNG ĐỂ QUẢNG CÁO & CHỐT ĐƠN)]:";
        if (!empty($activeCoupons)) {
            foreach ($activeCoupons as $c) {
                $discountStr = ($c['discount_type'] === 'percent')
                    ? ((float) $c['discount_value'] . '%')
                    : (number_format((float) $c['discount_value'], 0, '.', ',') . ' VND');
                $expireStr = !empty($c['expires_at']) ? (" | HSD: " . date('d/m/Y', strtotime($c['expires_at']))) : ' | Không giới hạn thời gian';
                $usesLeft = ((int) $c['max_uses'] > 0) ? (" | Còn lại: " . ((int) $c['max_uses'] - (int) $c['used_count']) . " lượt") : '';

                $lines[] = "• Mã: **`{$c['code']}`** - Giảm: **{$discountStr}**{$expireStr}{$usesLeft}";
            }
            $lines[] = "* LƯU Ý: Khi khách hỏi về giá hoặc có ý định mua, hãy chủ động nhắc khách áp dụng mã giảm giá này ở bước thanh toán để kích thích chốt đơn!";
        } else {
            $lines[] = "- Hiện tại không có mã giảm giá phụ nào đang kích hoạt. Hãy thông báo cho khách rằng giá niêm yết trên website đã là giá ưu đãi trực tiếp tốt nhất.";
        }

        // 3. Danh sách Bài Viết Hướng Dẫn & FAQ
        $postModel = new Post();
        $posts = $postModel->getAllPublished();
        $lines[] = "\n[3. BÀI VIẾT HƯỚNG DẪN & TÀI LIỆU KỸ THUẬT]:";
        if (!empty($posts)) {
            foreach (array_slice($posts, 0, 8) as $p) {
                $type = ($p['type'] ?? '') === 'tutorial' ? '[Hướng dẫn]' : '[Tin tức]';
                $lines[] = "• {$type} **{$p['title']}** (Đường dẫn slug: {$p['slug']})";
            }
        } else {
            $lines[] = "• Hướng dẫn kết nối VPN: Hỗ trợ đầy đủ cho iOS (Shadowrocket), Android (v2rayNG, Sing-box), Windows (v2rayN, Clash Verge), macOS, Android TV.";
        }

        // 4. Chính Sách & Điều Khoản Vận Hành
        $lines[] = "\n[4. CHÍNH SÁCH VẬN HÀNH & HỖ TRỢ]:";
        $lines[] = "- **Chính sách Hoàn Tiền**: Được xem xét xử lý hoàn tiền nếu do lỗi kỹ thuật máy chủ kéo dài không khắc phục được hoặc do lỗi thanh toán trùng lặp. Không áp dụng hoàn tiền khi dịch vụ đã sử dụng bình thường, tài khoản vi phạm quy chế hoặc thay đổi ý định cá nhân.";
        $lines[] = "- **Nạp / Rút Tiền**: Nạp tối thiểu vào ví {$minDeposit}, rút hoa hồng tối thiểu {$minWithdrawal}.";
        $lines[] = "- **Tiếp Thị Liên Kết (Affiliate)**: Nhận hoa hồng lên đến {$commissionRate} cho mỗi đơn hàng giới thiệu thành công. Đăng ký qua mã giới thiệu được tặng {$referralBonus} vào ví.";
        $lines[] = "- **Kênh Hỗ Trợ Chính Thức**:"
            . ($contactEmail ? " Email: {$contactEmail} |" : "")
            . ($fanpageUrl ? " Fanpage: {$fanpageUrl} |" : "")
            . ($zaloUrl ? " Zalo: {$zaloUrl}" : "");

        return implode("\n", $lines);
    }

    private function shouldHandoff(string $question, string $answer): bool
    {
        $text = mb_strtolower($question . ' ' . $answer);
        $keywords = [
            'hoan tien',
            'khieu nai',
            'lua dao',
            'that bai',
            'khong thanh toan duoc',
            'doi nhan vien',
            'nguoi that',
            'support truc tiep'
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function buildCta(array $context): array
    {
        $isLoggedIn = !empty($context['is_logged_in']);
        $page = strtolower(trim((string) ($context['page'] ?? '')));

        if ($isLoggedIn) {
            if ($page === 'plans' || $page === 'checkout') {
                return [
                    'label' => '💳 Xem & Mua gói cước',
                    'url' => '/user/plans'
                ];
            }
            if ($page === 'guides' || $page === 'faq') {
                return [
                    'label' => '📖 Hướng dẫn sử dụng',
                    'url' => '/user/guides'
                ];
            }
            if ($page === 'subscriptions') {
                return [
                    'label' => '⚡ Gói dịch vụ của tôi',
                    'url' => '/subscriptions'
                ];
            }
            return [
                'label' => '🚀 Xem gói dịch vụ VPN',
                'url' => '/user/plans'
            ];
        }

        if ($page === 'faq' || $page === 'download') {
            return [
                'label' => '📥 Tải ứng dụng VPN',
                'url' => '/download'
            ];
        }

        return [
            'label' => '🎁 Xem bảng giá & Ưu đãi',
            'url' => '/#bang-gia'
        ];
    }

    private function fallbackResponse(string $message): array
    {
        return [
            'success' => true,
            'answer' => $message,
            'handoff' => true,
            'provider' => 'fallback',
            'model' => '',
            'cta' => [
                'label' => 'Liên hệ hỗ trợ trực tiếp',
                'url' => $this->resolveSupportUrl()
            ],
        ];
    }

    private function resolveSupportUrl(): string
    {
        $url = trim((string) ($this->settings['fanpage_url'] ?? ''));
        if ($url !== '' && preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        return '/faq';
    }

    private function logEvent(string $event, array $data): void
    {
        if (class_exists(ChatEvent::class)) {
            $sessionId = isset($data['session_id']) && (int) $data['session_id'] > 0 ? (int) $data['session_id'] : null;
            $userId = isset($data['user_id']) && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null;
            $source = (string) ($data['source'] ?? 'web');

            (new ChatEvent())->track(
                $event,
                $source === 'fanpage' ? 'fanpage' : 'web',
                $sessionId,
                $userId,
                null,
                $data
            );
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $basePath . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $line = date('Y-m-d H:i:s') . ' [' . $event . '] ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($dir . '/chatbot.log', $line, FILE_APPEND);
    }
}

