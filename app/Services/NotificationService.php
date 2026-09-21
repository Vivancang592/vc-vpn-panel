<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Payment;
use App\Models\Withdrawal;

class NotificationService
{
    private static function getStorageKey(int $userId, string $type): string
    {
        return 'vc_notif_' . $type . '_' . $userId;
    }

    public static function getReadIds(int $userId): array
    {
        $sessionKey = self::getStorageKey($userId, 'read');
        if (isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
            return $_SESSION[$sessionKey];
        }

        $cookieVal = $_COOKIE[$sessionKey] ?? '';
        if ($cookieVal !== '') {
            $ids = json_decode($cookieVal, true);
            if (is_array($ids)) {
                $_SESSION[$sessionKey] = $ids;
                return $ids;
            }
        }

        $_SESSION[$sessionKey] = [];
        return [];
    }

    public static function getDeletedIds(int $userId): array
    {
        $sessionKey = self::getStorageKey($userId, 'del');
        if (isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
            return $_SESSION[$sessionKey];
        }

        $cookieVal = $_COOKIE[$sessionKey] ?? '';
        if ($cookieVal !== '') {
            $ids = json_decode($cookieVal, true);
            if (is_array($ids)) {
                $_SESSION[$sessionKey] = $ids;
                return $ids;
            }
        }

        $_SESSION[$sessionKey] = [];
        return [];
    }

    private static function saveIds(int $userId, string $type, array $ids): void
    {
        $ids = array_values(array_unique($ids));
        $sessionKey = self::getStorageKey($userId, $type);
        $_SESSION[$sessionKey] = $ids;

        // Lưu cookie 30 ngày
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
        setcookie($sessionKey, json_encode($ids), [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    public static function markAsRead(int $userId, string $id): void
    {
        $readIds = self::getReadIds($userId);
        if (!in_array($id, $readIds, true)) {
            $readIds[] = $id;
            self::saveIds($userId, 'read', $readIds);
        }
    }

    public static function markAllAsRead(int $userId): void
    {
        $notifications = self::getNotifications($userId);
        $readIds = self::getReadIds($userId);
        foreach ($notifications as $notif) {
            if (!in_array($notif['id'], $readIds, true)) {
                $readIds[] = $notif['id'];
            }
        }
        self::saveIds($userId, 'read', $readIds);
    }

    public static function deleteNotification(int $userId, string $id): void
    {
        $delIds = self::getDeletedIds($userId);
        if (!in_array($id, $delIds, true)) {
            $delIds[] = $id;
            self::saveIds($userId, 'del', $delIds);
        }
    }

    public static function clearAll(int $userId): void
    {
        $notifications = self::getNotifications($userId);
        $delIds = self::getDeletedIds($userId);
        foreach ($notifications as $notif) {
            if (!in_array($notif['id'], $delIds, true)) {
                $delIds[] = $notif['id'];
            }
        }
        self::saveIds($userId, 'del', $delIds);
    }

    public static function getNotifications(int $userId): array
    {
        $notifications = [];

        // 1. Đơn hàng (Orders): Đang chờ, thành công, bị hủy
        if (class_exists(Order::class)) {
            $orderModel = new Order();
            $orders = $orderModel->getByUserId($userId);
            foreach ($orders as $order) {
                $status = $order['payment_status'] ?? 'pending';
                $orderCode = $order['order_code'] ?? ('#' . $order['id']);
                $createdAt = $order['updated_at'] ?? $order['created_at'] ?? date('Y-m-d H:i:s');
                $notifId = 'order_' . $order['id'] . '_' . $status;

                if ($status === 'pending') {
                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'order',
                        'type' => 'pending',
                        'badge' => 'Chờ duyệt',
                        'badge_class' => 'warning',
                        'title' => 'Đơn hàng đang chờ thanh toán',
                        'message' => 'Đơn hàng ' . $orderCode . ' trị giá ' . number_format((float)($order['total_amount'] ?? 0)) . 'đ đang chờ xử lý thanh toán.',
                        'link' => '/orders/detail?id=' . (int)$order['id'],
                        'created_at' => $order['created_at'] ?? $createdAt
                    ];
                } elseif ($status === 'completed') {
                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'order',
                        'type' => 'success',
                        'badge' => 'Thành công',
                        'badge_class' => 'success',
                        'title' => 'Đơn hàng kích hoạt thành công',
                        'message' => 'Đơn hàng ' . $orderCode . ' đã được thanh toán và kích hoạt gói thành công.',
                        'link' => '/orders/detail?id=' . (int)$order['id'],
                        'created_at' => $createdAt
                    ];
                } elseif (in_array($status, ['cancelled', 'failed'], true)) {
                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'order',
                        'type' => 'danger',
                        'badge' => 'Bị hủy / Lỗi',
                        'badge_class' => 'danger',
                        'title' => 'Đơn hàng ' . ($status === 'cancelled' ? 'đã bị hủy' : 'thanh toán thất bại'),
                        'message' => 'Đơn hàng ' . $orderCode . ' đã kết thúc với trạng thái ' . ($status === 'cancelled' ? 'Hủy bỏ' : 'Thất bại') . '.',
                        'link' => '/orders/detail?id=' . (int)$order['id'],
                        'created_at' => $createdAt
                    ];
                }
            }
        }

        // 2. Gói dịch vụ (Subscriptions): Hết hạn, hết dung lượng (Data)
        if (class_exists(Subscription::class)) {
            $subModel = new Subscription();
            $subscriptions = $subModel->getByUserId($userId);
            $nowTime = time();

            foreach ($subscriptions as $sub) {
                $subUuid = substr((string)($sub['uuid'] ?? ''), 0, 8);
                $isExpired = ($sub['status'] ?? '') === 'expired' || (!empty($sub['end_date']) && strtotime($sub['end_date']) < $nowTime);
                $totalData = (float)($sub['transfer_enable'] ?? 0);
                $usedData = (float)($sub['upload'] ?? 0) + (float)($sub['download'] ?? 0);
                $isOutOfData = ($sub['status'] ?? '') === 'suspended' || ($totalData > 0 && $usedData >= $totalData);

                if ($isExpired) {
                    $notifications[] = [
                        'id' => 'sub_exp_' . $sub['id'],
                        'category' => 'subscription',
                        'type' => 'warning',
                        'badge' => 'Hết hạn',
                        'badge_class' => 'warning',
                        'title' => 'Gói dịch vụ đã hết hạn',
                        'message' => 'Gói VPN #' . $subUuid . ' của bạn đã hết hạn vào ngày ' . (!empty($sub['end_date']) ? date('d/m/Y H:i', strtotime($sub['end_date'])) : '') . '. Hãy gia hạn để tiếp tục sử dụng.',
                        'link' => '/subscriptions',
                        'created_at' => $sub['end_date'] ?? $sub['updated_at'] ?? date('Y-m-d H:i:s')
                    ];
                } elseif ($isOutOfData) {
                    $notifications[] = [
                        'id' => 'sub_data_' . $sub['id'],
                        'category' => 'subscription',
                        'type' => 'danger',
                        'badge' => 'Hết Data',
                        'badge_class' => 'danger',
                        'title' => 'Gói VPN đã sử dụng hết Data',
                        'message' => 'Gói VPN #' . $subUuid . ' đã hết dung lượng lưu lượng tốc độ cao.',
                        'link' => '/subscriptions',
                        'created_at' => $sub['updated_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // 3. Yêu cầu hỗ trợ (Tickets): Đang chờ hoặc Đã có phản hồi/giải quyết
        if (class_exists(SupportTicket::class)) {
            $ticketModel = new SupportTicket();
            $tickets = $ticketModel->getByUserId($userId);
            foreach ($tickets as $ticket) {
                $tStatus = $ticket['status'] ?? 'open';
                $tSubject = $ticket['subject'] ?? ('Ticket #' . $ticket['id']);
                $createdAt = $ticket['updated_at'] ?? $ticket['created_at'] ?? date('Y-m-d H:i:s');
                $notifId = 'ticket_' . $ticket['id'] . '_' . $tStatus;

                if ($tStatus === 'open') {
                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'ticket',
                        'type' => 'info',
                        'badge' => 'Đang chờ',
                        'badge_class' => 'info',
                        'title' => 'Ticket #' . $ticket['id'] . ' đang chờ phản hồi',
                        'message' => 'Yêu cầu: "' . $tSubject . '" đã được gửi và đang chờ kỹ thuật viên tiếp nhận.',
                        'link' => '/tickets/detail?id=' . $ticket['id'],
                        'created_at' => $ticket['created_at'] ?? $createdAt
                    ];
                } elseif (in_array($tStatus, ['in_progress', 'resolved', 'closed'], true)) {
                    $statusName = $tStatus === 'resolved' ? 'Đã xử lý xong' : ($tStatus === 'closed' ? 'Đã đóng' : 'Đã có phản hồi mới');
                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'ticket',
                        'type' => 'success',
                        'badge' => $statusName,
                        'badge_class' => $tStatus === 'resolved' ? 'success' : 'primary',
                        'title' => 'Cập nhật Ticket #' . $ticket['id'] . ': ' . $statusName,
                        'message' => 'Yêu cầu: "' . $tSubject . '" đã được cập nhật trạng thái bởi ban quản trị.',
                        'link' => '/tickets/detail?id=' . $ticket['id'],
                        'created_at' => $createdAt
                    ];
                }
            }
        }

        // 4. Giao dịch nạp tiền (Deposit Payments)
        if (class_exists(Payment::class)) {
            $paymentModel = new Payment();
            $payments = $paymentModel->getByUserId($userId);
            foreach ($payments as $pay) {
                if (($pay['type'] ?? '') !== 'deposit') {
                    continue;
                }
                $pStatus = $pay['status'] ?? 'pending';
                $txId = $pay['transaction_id'] ?? ('#' . $pay['id']);
                $amountFormatted = number_format((float)($pay['amount'] ?? 0)) . 'đ';
                $pNotifId = 'deposit_' . $pay['id'] . '_' . $pStatus;
                $pCreatedAt = $pay['created_at'] ?? date('Y-m-d H:i:s');

                if ($pStatus === 'pending') {
                    $notifications[] = [
                        'id' => $pNotifId,
                        'category' => 'deposit',
                        'type' => 'warning',
                        'badge' => 'Chờ nạp tiền',
                        'badge_class' => 'warning',
                        'title' => 'Giao dịch nạp tiền đang chờ',
                        'message' => 'Yêu cầu nạp ' . $amountFormatted . ' (Mã: ' . $txId . ') đang chờ thanh toán.',
                        'link' => '/payment/checkout?deposit=' . (int)$pay['id'],
                        'created_at' => $pCreatedAt
                    ];
                } elseif ($pStatus === 'success') {
                    $notifications[] = [
                        'id' => $pNotifId,
                        'category' => 'deposit',
                        'type' => 'success',
                        'badge' => 'Nạp thành công',
                        'badge_class' => 'success',
                        'title' => 'Nạp tiền vào ví thành công',
                        'message' => 'Tài khoản của bạn đã được cộng ' . $amountFormatted . ' từ giao dịch ' . $txId . '.',
                        'link' => '/payments',
                        'created_at' => $pCreatedAt
                    ];
                } elseif ($pStatus === 'failed') {
                    $notifications[] = [
                        'id' => $pNotifId,
                        'category' => 'deposit',
                        'type' => 'danger',
                        'badge' => 'Nạp thất bại',
                        'badge_class' => 'danger',
                        'title' => 'Giao dịch nạp tiền thất bại',
                        'message' => 'Giao dịch nạp ' . $amountFormatted . ' (Mã: ' . $txId . ') không thành công.',
                        'link' => '/payments',
                        'created_at' => $pCreatedAt
                    ];
                }
            }
        }

        // 5. Yêu cầu rút hoa hồng (Withdrawals)
        if (class_exists(Withdrawal::class)) {
            $withdrawalModel = new Withdrawal();
            $withdrawals = $withdrawalModel->getByUserId($userId);
            foreach ($withdrawals as $w) {
                $wStatus = $w['status'] ?? 'pending';
                $wAmountFormatted = number_format((float)($w['amount'] ?? 0)) . 'đ';
                $wNotifId = 'withdrawal_' . $w['id'] . '_' . $wStatus;
                $wCreatedAt = $w['created_at'] ?? date('Y-m-d H:i:s');
                $bankInfo = htmlspecialchars($w['bank_name'] ?? '');

                if ($wStatus === 'pending') {
                    $notifications[] = [
                        'id' => $wNotifId,
                        'category' => 'withdrawal',
                        'type' => 'warning',
                        'badge' => 'Chờ duyệt rút',
                        'badge_class' => 'warning',
                        'title' => 'Yêu cầu rút hoa hồng đang chờ duyệt',
                        'message' => 'Yêu cầu rút ' . $wAmountFormatted . ' về ' . $bankInfo . ' đang được quản trị viên xử lý.',
                        'link' => '/referrals',
                        'created_at' => $wCreatedAt
                    ];
                } elseif ($wStatus === 'approved') {
                    $notifications[] = [
                        'id' => $wNotifId,
                        'category' => 'withdrawal',
                        'type' => 'success',
                        'badge' => 'Đã duyệt rút',
                        'badge_class' => 'success',
                        'title' => 'Rút hoa hồng thành công',
                        'message' => 'Yêu cầu rút ' . $wAmountFormatted . ' về ' . $bankInfo . ' đã được duyệt và chi trả thành công.',
                        'link' => '/referrals',
                        'created_at' => $wCreatedAt
                    ];
                } elseif ($wStatus === 'rejected') {
                    $notifications[] = [
                        'id' => $wNotifId,
                        'category' => 'withdrawal',
                        'type' => 'danger',
                        'badge' => 'Từ chối rút',
                        'badge_class' => 'danger',
                        'title' => 'Yêu cầu rút hoa hồng bị từ chối',
                        'message' => 'Yêu cầu rút ' . $wAmountFormatted . ' về ' . $bankInfo . ' đã bị từ chối. Số dư đã được hoàn lại.',
                        'link' => '/referrals',
                        'created_at' => $wCreatedAt
                    ];
                }
            }
        }

        // 6. Lọc bỏ thông báo đã bị xóa
        $deletedIds = self::getDeletedIds($userId);
        if (!empty($deletedIds)) {
            $notifications = array_values(array_filter($notifications, static fn($n) => !in_array($n['id'], $deletedIds, true)));
        }

        // 7. Gắn cờ đã xem
        $readIds = self::getReadIds($userId);
        foreach ($notifications as &$notif) {
            $notif['is_read'] = in_array($notif['id'], $readIds, true);
        }
        unset($notif);

        // 8. Sắp xếp giảm dần theo ngày
        usort($notifications, static fn(array $a, array $b): int => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $notifications;
    }

    public static function getUnreadCount(int $userId): int
    {
        $notifications = self::getNotifications($userId);
        $count = 0;
        foreach ($notifications as $n) {
            if (empty($n['is_read'])) {
                $count++;
            }
        }
        return $count;
    }

    public static function markAdminAllAsRead(int $adminId): void
    {
        $notifications = self::getAdminNotifications($adminId);
        $readIds = self::getReadIds($adminId);
        foreach ($notifications as $notif) {
            if (!in_array($notif['id'], $readIds, true)) {
                $readIds[] = $notif['id'];
            }
        }
        self::saveIds($adminId, 'read', $readIds);
    }

    public static function deleteAdminNotification(int $adminId, string $id): void
    {
        $delIds = self::getDeletedIds($adminId);
        if (!in_array($id, $delIds, true)) {
            $delIds[] = $id;
            self::saveIds($adminId, 'del', $delIds);
        }
    }

    public static function clearAdminAll(int $adminId): void
    {
        $notifications = self::getAdminNotifications($adminId);
        $delIds = self::getDeletedIds($adminId);
        foreach ($notifications as $notif) {
            if (!in_array($notif['id'], $delIds, true)) {
                $delIds[] = $notif['id'];
            }
        }
        self::saveIds($adminId, 'del', $delIds);
    }

    public static function getAdminNotifications(int $adminId): array
    {
        $notifications = [];

        // 1. Đơn hàng đang chờ duyệt
        if (class_exists(Order::class)) {
            $orderModel = new Order();
            $orders = $orderModel->allWithDetails();
            foreach ($orders as $order) {
                if (($order['payment_status'] ?? '') === 'pending') {
                    $orderCode = $order['order_code'] ?? ('#' . $order['id']);
                    $buyer = $order['username'] ?? ('User #' . ($order['user_id'] ?? ''));
                    $amount = number_format((float)($order['total_amount'] ?? 0)) . 'đ';
                    $createdAt = $order['created_at'] ?? date('Y-m-d H:i:s');
                    $notifId = 'admin_order_' . $order['id'];

                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'order',
                        'type' => 'warning',
                        'badge' => 'Đơn hàng chờ',
                        'badge_class' => 'warning',
                        'title' => 'Đơn hàng ' . $orderCode . ' đang chờ',
                        'message' => 'Đơn hàng ' . $orderCode . ' (' . $amount . ') từ ' . $buyer . ' đang chờ thanh toán / duyệt.',
                        'link' => '/admin/orders/detail?id=' . (int)$order['id'],
                        'created_at' => $createdAt
                    ];
                }
            }
        }

        // 2. Yêu cầu hỗ trợ (Tickets) đang chờ
        if (class_exists(SupportTicket::class)) {
            $ticketModel = new SupportTicket();
            $tickets = $ticketModel->allWithDetails();
            foreach ($tickets as $ticket) {
                $tStatus = $ticket['status'] ?? 'open';
                if ($tStatus === 'open') {
                    $tSubject = $ticket['subject'] ?? ('Ticket #' . $ticket['id']);
                    $userName = $ticket['user_name'] ?? ('User #' . ($ticket['user_id'] ?? ''));
                    $createdAt = $ticket['updated_at'] ?? $ticket['created_at'] ?? date('Y-m-d H:i:s');
                    $notifId = 'admin_ticket_' . $ticket['id'];

                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'ticket',
                        'type' => 'info',
                        'badge' => 'Ticket mới',
                        'badge_class' => 'info',
                        'title' => 'Ticket #' . $ticket['id'] . ' cần hỗ trợ',
                        'message' => 'Thành viên ' . $userName . ': "' . $tSubject . '" đang chờ phản hồi.',
                        'link' => '/admin/tickets/detail?id=' . (int)$ticket['id'],
                        'created_at' => $ticket['created_at'] ?? $createdAt
                    ];
                }
            }
        }

        // 3. Yêu cầu rút hoa hồng đang chờ duyệt
        if (class_exists(Withdrawal::class)) {
            $withdrawalModel = new Withdrawal();
            $withdrawals = $withdrawalModel->allWithDetails();
            foreach ($withdrawals as $w) {
                if (($w['status'] ?? '') === 'pending') {
                    $wAmountFormatted = number_format((float)($w['amount'] ?? 0)) . 'đ';
                    $wUser = $w['username'] ?? ('User #' . ($w['user_id'] ?? ''));
                    $wBank = $w['bank_name'] ?? '';
                    $notifId = 'admin_withdraw_' . $w['id'];

                    $notifications[] = [
                        'id' => $notifId,
                        'category' => 'withdrawal',
                        'type' => 'warning',
                        'badge' => 'Chờ rút tiền',
                        'badge_class' => 'warning',
                        'title' => 'Yêu cầu rút ' . $wAmountFormatted,
                        'message' => 'Thành viên ' . $wUser . ' yêu cầu rút ' . $wAmountFormatted . ' về ' . $wBank . '.',
                        'link' => '/admin/withdrawals/detail?id=' . (int)$w['id'],
                        'created_at' => $w['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // 4. Lọc bỏ thông báo đã bị xóa
        $deletedIds = self::getDeletedIds($adminId);
        if (!empty($deletedIds)) {
            $notifications = array_values(array_filter($notifications, static fn($n) => !in_array($n['id'], $deletedIds, true)));
        }

        // 5. Gắn cờ đã xem
        $readIds = self::getReadIds($adminId);
        foreach ($notifications as &$notif) {
            $notif['is_read'] = in_array($notif['id'], $readIds, true);
        }
        unset($notif);

        // 6. Sắp xếp giảm dần theo ngày
        usort($notifications, static fn(array $a, array $b): int => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $notifications;
    }

    public static function getAdminPendingCount(int $adminId = 0): int
    {
        if ($adminId > 0) {
            $notifications = self::getAdminNotifications($adminId);
            $count = 0;
            foreach ($notifications as $n) {
                if (empty($n['is_read'])) {
                    $count++;
                }
            }
            return $count;
        }

        $count = 0;
        try {
            if (class_exists(SupportTicket::class)) {
                $tickets = (new SupportTicket())->allWithDetails();
                foreach ($tickets as $t) {
                    if (($t['status'] ?? '') === 'open') {
                        $count++;
                    }
                }
            }
            if (class_exists(Order::class)) {
                $orders = (new Order())->allWithDetails();
                foreach ($orders as $o) {
                    if (($o['payment_status'] ?? '') === 'pending') {
                        $count++;
                    }
                }
            }
            if (class_exists(Withdrawal::class)) {
                $withdrawals = (new Withdrawal())->allWithDetails();
                foreach ($withdrawals as $w) {
                    if (($w['status'] ?? '') === 'pending') {
                        $count++;
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        return $count;
    }
}
