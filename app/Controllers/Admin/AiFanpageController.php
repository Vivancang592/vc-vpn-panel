<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AIOutput;

/**
 * AiFanpageController — Tab "Nội Dung Fanpage" (/admin/ai/fanpage).
 *
 * Tab hiển thị 2 khối:
 *   - Hàng đợi VIẾT TUẦN TỰ (session) do AiTaskController::storeTopics tạo
 *     (POST /admin/ai/articles/store-topics) + nút gọi write-next.
 *   - DANH SÁCH BÀI VIẾT (CAB dưới cùng) — trước đây là trang riêng
 *     /admin/ai/outputs (view outputs.php) nay ĐÃ CHUYỂN VÀO ĐÂY.
 *
 * Các POST chiến dịch/sinh nhanh/đăng ngay cũ ĐÃ GỠ (không còn UI):
 * việc LÊN LỊCH một bài AI giờ nằm ngay trong CAB Danh Sách Bài Viết
 * (AiOutputController::schedule → vc_scheduled_posts.output_id) và
 * CronController::autoPostFanpage vẫn là luồng chạy ngầm bất khả xâm phạm
 * (F19.B1).
 *
 * Ranh giới F19:
 *  - KHÔNG gọi TaskRunner/AICore/TaskDispatcher/OutputHandler trực tiếp.
 *  - Mọi lời gọi AI đi qua AIProviderService (adapter uỷ quyền AICore duy nhất).
 */
final class AiFanpageController extends AiBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->activeMenu = 'ai-fanpage';
    }

    /**
     * Trang chính: hàng đợi viết bài (CAB 2) + Danh Sách Bài Viết (CAB 3).
     */
    public function index(): void
    {
        // ---- Danh sách bài viết (chuyển từ trang /admin/ai/outputs cũ) ----
        $filter = (string) ($_GET['tab'] ?? '');
        if (!array_key_exists($filter, self::OUTPUT_TABS)) {
            $filter = '';
        }

        $rows = [];
        try {
            $rows = (new AIOutput())->articleList($filter);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $articles = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $articles[] = $this->decorate($row);
            }
        }

        $this->render('admin.ai.tab-fanpage', [
            'activeMenu' => 'ai-fanpage',
            'pageTitle'  => 'Nội Dung Fanpage - Trung Tâm AI',
            // Hàng đợi VIẾT TUẦN TỰ (session, do AiTaskController::storeTopics
            // tạo) — tab "Tiến Trình" hiển thị + gọi write-next để viết tiếp.
            'writeQueue' => $this->liveWriteQueue(),
            // Danh sách bài viết — CAB 3 (trước đây là trang outputs.php).
            'articles'     => $articles,
            'filter'       => $filter,
            'tabs'         => self::OUTPUT_TABS,
            'postStatus'   => self::POST_STATUS,
            // Ảnh tab Tạo Ảnh → chọn đính kèm trong popup lên lịch.
            'imageGallery' => $this->galleryItems('image'),
        ]);
    }

    /**
     * Hàng đợi viết bài "còn hiệu lực" cho lần render này.
     *
     * Queue nằm trong session nên sau khi viết xong nó vẫn giữ các item
     * `done` → số "bài đã hoàn tất" ở CAB Tiến Trình hiển thị lại của lần
     * chạy TRƯỚC mỗi khi mở trang. Để khỏi phải nhớ dọn tay, chủ động bỏ
     * hàng đợi khi batch đã kết thúc:
     *   - không còn item `waiting`/`writing` (đã viết hết)  → dọn;
     *   - CHỈ toàn item `done` (không còn lỗi)              → dọn;
     *   - còn item `failed`                                 → GIỮ lại để
     *     admin reload vẫn xem được lý do lỗi + số bài lỗi.
     *
     * Khi đang viết (có `writing`) hoặc còn bài chờ thì giữ nguyên → tiến
     * trình hiển thị bình thường, JS vẫn tiếp tục pump.
     *
     * @return array<string, mixed>|null
     */
    private function liveWriteQueue(): ?array
    {
        $queue = $_SESSION['article_queue'] ?? null;
        if (!is_array($queue)) {
            return null;
        }

        $items = $queue['items'] ?? null;
        if (!is_array($items)) {
            unset($_SESSION['article_queue']);
            return null;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $status = (string) ($item['status'] ?? '');
            // Còn việc phải làm hoặc còn lỗi phải xem → giữ hàng đợi.
            if ($status === 'waiting' || $status === 'writing' || $status === 'failed') {
                return $queue;
            }
        }

        // Batch kết thúc sạch (mọi item đều done, hoặc hàng rỗng) → dọn.
        unset($_SESSION['article_queue']);
        return null;
    }
}
