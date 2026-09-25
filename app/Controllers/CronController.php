<?php

namespace App\Controllers;

use App\Models\Subscription;
use App\Models\VpnPlan;
use App\Models\User;
use App\Models\NodeTask;
use App\Models\Setting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ScheduledPost;
use App\Services\MailService;
use App\Services\AIProviderService;
use App\Services\FanpageService;

class CronController extends BaseController
{
    /**
     * Lấy kết nối PDO an toàn từ BaseModel
     */
    private function getPdo(): \PDO
    {
        $ref = new \ReflectionClass(\App\Models\BaseModel::class);
        $prop = $ref->getProperty('db');
        $prop->setAccessible(true);
        return $prop->getValue();
    }

    /**
     * Hàm phụ trợ: Giải mã mảng JSON hoặc số nguyên của group_id thành mảng danh sách ID nhóm máy chủ
     */
    private function parseGroupIds(mixed $rawGroupId): array
    {
        if (is_array($rawGroupId)) {
            $ids = $rawGroupId;
        } else {
            $ids = json_decode((string)$rawGroupId, true);
            if (!is_array($ids)) {
                $ids = !empty($rawGroupId) ? [(int)$rawGroupId] : [];
            }
        }
        return array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
    }

    /**
     * Tự động quét và xử lý các gói cước hết hạn, hết data, sắp hết hạn và reset lưu lượng đầu tháng
     */
    public function checkSubscriptions(): void
    {
        // 1. Kiểm tra Secret Key từ tham số URL (?key=VC_VPN_CRON_2027_SECRET)
        $settingModel = new Setting();
        $cronSecret = $settingModel->get('cron_secret_key', 'VC_VPN_CRON_2027_SECRET');
        $providedKey = $_GET['key'] ?? '';

        if (!hash_equals($cronSecret, $providedKey)) {
            $this->json(['status' => false, 'message' => 'Truy cập không hợp lệ.'], 403);
            return;
        }

        $db                = $this->getPdo();
        $subscriptionModel = new Subscription();
        $nodeTaskModel     = new NodeTask();
        $mailService       = new MailService();
        $orderModel        = new Order();

        $now = date('Y-m-d H:i:s');
        $stats = [
            'expired'        => 0,
            'data_exceeded'  => 0,
            'expiring_soon'  => 0,
            'cancelled_orders' => 0,
            'cancelled_subscriptions' => 0,
            'monthly_reset'  => false
        ];

        $pendingOrderTimeout = max(1, (int) $settingModel->get('pending_order_timeout_minutes', '30'));
        $stats['cancelled_orders'] = $orderModel->cancelExpiredPending($pendingOrderTimeout);
        (new Payment())->failPendingForCancelledOrders();

        $expiredSubscriptionRetentionDays = max(1, min(3650, (int) $settingModel->get('expired_subscription_cancel_after_days', '30')));
        $sqlCancelExpiredSubscriptions = "
            SELECT s.*, p.group_id
            FROM `vc_subscriptions` s
            INNER JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            WHERE s.status = 'expired'
              AND s.end_date <= DATE_SUB(:now, INTERVAL {$expiredSubscriptionRetentionDays} DAY)
        ";
        $stmt = $db->prepare($sqlCancelExpiredSubscriptions);
        $stmt->execute(['now' => $now]);
        $expiredSubscriptionsToCancel = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($expiredSubscriptionsToCancel as $subscription) {
            if (!$subscriptionModel->update((int) $subscription['id'], [
                'status' => 'cancelled',
                'updated_at' => $now
            ])) {
                continue;
            }

            $groupIds = $this->parseGroupIds($subscription['group_id'] ?? []);
            if (!empty($groupIds)) {
                $nodeTaskModel->createTasksForGroup($groupIds, 'delete_user', [
                    'username' => 'sub_' . $subscription['id']
                ]);
            }
            $stats['cancelled_subscriptions']++;
        }

        // -------------------------------------------------------------
        // XỬ LÝ 1: RESET LƯU LƯỢNG VÀO NGÀY 1 HÀNG THÁNG (CHẠY 1 LẦN DUY NHẤT)
        // -------------------------------------------------------------
        $currentMonthKey = date('Y-m-01');
        $lastResetDate   = $settingModel->get('last_monthly_reset_date', '');

        if (date('j') === '1' && $lastResetDate !== $currentMonthKey) {
            // A. Mở khóa và reset các gói bị tạm ngưng do hết data (status = suspended) nhưng chưa hết hạn
            $sqlSuspended = "
                SELECT s.*, p.group_id 
                FROM `vc_subscriptions` s
                INNER JOIN `vc_vpn_plans` p ON s.plan_id = p.id
                WHERE s.status = 'suspended' AND s.end_date > :now
            ";
            $stmt = $db->prepare($sqlSuspended);
            $stmt->execute(['now' => $now]);
            $suspendedSubs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($suspendedSubs as $sub) {
                // Cập nhật trạng thái lại thành active và reset lưu lượng
                $subscriptionModel->update($sub['id'], [
                    'status'     => 'active',
                    'upload'     => 0,
                    'download'   => 0,
                    'updated_at' => $now
                ]);

                // Gửi Task mở lại kết nối trên VPS
                $groupIds = $this->parseGroupIds($sub['group_id'] ?? []);
                if (!empty($groupIds)) {
                    $nodeTaskModel->createTasksForGroup($groupIds, 'toggle_user', [
                        'username' => 'sub_' . $sub['id'],
                        'status'   => 'active'
                    ]);
                }
            }

            // B. Reset lưu lượng upload/download về 0 cho tất cả các gói đang active
            $sqlResetActive = "
                UPDATE `vc_subscriptions` 
                SET `upload` = 0, `download` = 0, `updated_at` = :now 
                WHERE `status` = 'active'
            ";
            $stmtReset = $db->prepare($sqlResetActive);
            $stmtReset->execute(['now' => $now]);

            // C. Đánh dấu đã hoàn tất reset cho tháng này
            $settingModel->setByKey('last_monthly_reset_date', $currentMonthKey);
            $stats['monthly_reset'] = true;
        }

        // -------------------------------------------------------------
        // XỬ LÝ 2: CÁC GÓI ACTIVE ĐÃ HẾT HẠN (end_date <= NOW)
        // -------------------------------------------------------------
        $sqlExpired = "
            SELECT s.*, u.email, u.username AS user_name, p.group_id, p.name AS plan_name
            FROM `vc_subscriptions` s
            INNER JOIN `vc_users` u ON s.user_id = u.id
            INNER JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            WHERE s.status = 'active' AND s.end_date <= :now
        ";
        $stmt = $db->prepare($sqlExpired);
        $stmt->execute(['now' => $now]);
        $expiredSubs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($expiredSubs as $sub) {
            $subscriptionModel->update($sub['id'], [
                'status'     => 'expired',
                'updated_at' => $now
            ]);

            $groupIds = $this->parseGroupIds($sub['group_id'] ?? []);
            if (!empty($groupIds)) {
                $nodeTaskModel->createTasksForGroup($groupIds, 'toggle_user', [
                    'username' => 'sub_' . $sub['id'],
                    'status'   => 'disabled'
                ]);
            }

            if (!empty($sub['email']) && !$this->hasRecentEmailLog($sub['email'], 'hết hạn', 24)) {
                $mailService->send($sub['email'], 'Tài khoản VPN của bạn đã hết hạn', 'subscriptions.expired', [
                    'username'  => $sub['user_name'],
                    'plan_name' => $sub['plan_name'],
                    'end_date'  => $sub['end_date']
                ]);
            }

            $stats['expired']++;
        }

        // -------------------------------------------------------------
        // XỬ LÝ 3: CÁC GÓI ACTIVE ĐÃ HẾT DUNG LƯỢNG ((upload + download) >= transfer_enable)
        // -------------------------------------------------------------
        $sqlDataExceeded = "
            SELECT s.*, u.email, u.username AS user_name, p.group_id, p.name AS plan_name
            FROM `vc_subscriptions` s
            INNER JOIN `vc_users` u ON s.user_id = u.id
            INNER JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            WHERE s.status = 'active' 
              AND s.transfer_enable > 0 
              AND (s.upload + s.download) >= s.transfer_enable
        ";
        $stmt = $db->prepare($sqlDataExceeded);
        $stmt->execute();
        $dataExceededSubs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($dataExceededSubs as $sub) {
            $subscriptionModel->update($sub['id'], [
                'status'     => 'suspended',
                'updated_at' => $now
            ]);

            $groupIds = $this->parseGroupIds($sub['group_id'] ?? []);
            if (!empty($groupIds)) {
                $nodeTaskModel->createTasksForGroup($groupIds, 'toggle_user', [
                    'username' => 'sub_' . $sub['id'],
                    'status'   => 'disabled'
                ]);
            }

            if (!empty($sub['email']) && !$this->hasRecentEmailLog($sub['email'], 'hết dung lượng', 24)) {
                $mailService->send($sub['email'], 'Tài khoản VPN của bạn đã hết dung lượng', 'subscriptions.data-exceeded', [
                    'username'  => $sub['user_name'],
                    'plan_name' => $sub['plan_name']
                ]);
            }

            $stats['data_exceeded']++;
        }

        // -------------------------------------------------------------
        // XỬ LÝ 4: CÁC GÓI SẮP HẾT HẠN (Còn dưới 3 ngày)
        // -------------------------------------------------------------
        $threeDaysLater = date('Y-m-d H:i:s', strtotime('+3 days'));
        $sqlExpiringSoon = "
            SELECT s.*, u.email, u.username AS user_name, p.name AS plan_name
            FROM `vc_subscriptions` s
            INNER JOIN `vc_users` u ON s.user_id = u.id
            INNER JOIN `vc_vpn_plans` p ON s.plan_id = p.id
            WHERE s.status = 'active' 
              AND s.end_date > :now 
              AND s.end_date <= :three_days
        ";
        $stmt = $db->prepare($sqlExpiringSoon);
        $stmt->execute([
            'now'        => $now,
            'three_days' => $threeDaysLater
        ]);
        $expiringSoonSubs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($expiringSoonSubs as $sub) {
            if (!empty($sub['email']) && !$this->hasRecentEmailLog($sub['email'], 'sắp hết hạn', 72)) {
                $mailService->send($sub['email'], 'Cảnh báo: Tài khoản VPN sắp hết hạn', 'subscriptions.expiring-soon', [
                    'username'  => $sub['user_name'],
                    'plan_name' => $sub['plan_name'],
                    'end_date'  => $sub['end_date']
                ]);
                $stats['expiring_soon']++;
            }
        }

        $this->json([
            'status'  => true,
            'message' => 'Hoàn tất tiến trình tự động quét gói cước.',
            'data'    => $stats
        ]);
    }

    /**
     * Kiểm tra xem đã gửi email có tiêu đề chứa từ khóa cho người nhận trong khoảng thời gian (giờ) nhất định chưa
     */
    private function hasRecentEmailLog(string $recipient, string $subjectKeyword, int $hours = 72): bool
    {
        $db   = $this->getPdo();
        $sql  = "
            SELECT COUNT(*) FROM `vc_email_logs` 
            WHERE `recipient` = :recipient 
              AND `subject` LIKE :subject 
              AND `status` = 'sent' 
              AND `created_at` >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'recipient' => $recipient,
            'subject'   => '%' . $subjectKeyword . '%'
        ]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Cronjob tự động đăng bài Fanpage và sinh nội dung/hình ảnh bằng AI
     */
    public function autoPostFanpage(): void
    {
        $settingModel = new Setting();
        $cronSecret = $settingModel->get('cron_secret_key', 'VC_VPN_CRON_2027_SECRET');
        $providedKey = $_GET['key'] ?? '';

        if (!hash_equals($cronSecret, $providedKey)) {
            $this->json(['status' => false, 'message' => 'Truy cập không hợp lệ.'], 403);
            return;
        }

        $postModel = new ScheduledPost();
        $aiProvider = new AIProviderService();
        $fanpageService = new FanpageService();

        $posts = $postModel->getPendingQueue(5);
        if (empty($posts)) {
            $this->json([
                'status' => true,
                'message' => 'Không có bài viết nào trong hàng đợi cần đăng.',
                'processed' => 0
            ]);
            return;
        }

        $stats = [
            'total' => count($posts),
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($posts as $post) {
            $postId = (int) $post['id'];
            $postModel->markAsGenerating($postId);

            $content = trim((string) ($post['generated_content'] ?? ''));
            $imageUrl = trim((string) ($post['image_url'] ?? ''));
            $metaData = !empty($post['meta_data']) ? (is_array($post['meta_data']) ? $post['meta_data'] : json_decode((string) $post['meta_data'], true)) : [];

            // 1. Sinh nội dung nếu chưa có sẵn
            if ($content === '') {
                $contentResult = $aiProvider->generateContent($post['topic'], $post['content_prompt']);
                if (!$contentResult['ok'] || trim((string) $contentResult['content']) === '') {
                    $errorMsg = 'Lỗi sinh nội dung AI: ' . ($contentResult['error'] ?? 'Nội dung rỗng');
                    $postModel->markAsFailed($postId, $errorMsg);
                    $stats['failed']++;
                    $stats['details'][] = ['id' => $postId, 'status' => 'failed', 'error' => $errorMsg];
                    continue;
                }
                $content = trim((string) $contentResult['content']);
                $metaData['content_provider'] = $contentResult['provider'] ?? 'ai';
                $metaData['content_model'] = $contentResult['model'] ?? '';
            }

            // 2. Sinh hình ảnh nếu có prompt ảnh nhưng chưa có link ảnh
            if ($imageUrl === '' && !empty($post['image_prompt'])) {
                $imageResult = $aiProvider->generateImage($post['image_prompt']);
                if ($imageResult['ok'] && !empty($imageResult['url'])) {
                    $imageUrl = $imageResult['url'];
                    $metaData['image_url'] = $imageUrl;
                }
            }

            // 3. Đẩy lên Fanpage qua Meta Graph API
            if ($imageUrl !== '') {
                $publishResult = $fanpageService->publishPhoto($content, $imageUrl);
            } else {
                $publishResult = $fanpageService->publishPost($content);
            }

            if ($publishResult['ok'] && !empty($publishResult['id'])) {
                $postModel->markAsPublished($postId, $publishResult['id'], $content, $imageUrl ?: null, $metaData);
                $stats['success']++;
                $stats['details'][] = ['id' => $postId, 'status' => 'published', 'facebook_post_id' => $publishResult['id']];
            } else {
                $errorMsg = 'Lỗi đăng Fanpage: ' . ($publishResult['error'] ?? 'Không rõ nguyên nhân');
                $postModel->markAsFailed($postId, $errorMsg);
                $stats['failed']++;
                $stats['details'][] = ['id' => $postId, 'status' => 'failed', 'error' => $errorMsg];
            }
        }

        $this->json([
            'status' => true,
            'message' => 'Đã hoàn tất tiến trình quét và đăng bài Fanpage.',
            'stats' => $stats
        ]);
    }
}