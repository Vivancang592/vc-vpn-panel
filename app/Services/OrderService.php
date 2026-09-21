<?php

namespace App\Services;

use App\Models\Order;
use App\Models\VpnPlan;
use App\Models\Coupon;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\NodeTask;
use App\Models\User;
use App\Models\Setting;

class OrderService
{
    /**
     * Xử lý tạo đơn hàng mới (Hỗ trợ sinh tiền lẻ cho WeChat Pay)
     */
    public function createOrder(int $userId, int $planId, ?string $couponCode = null, string $paymentMethod = 'vietqr', string $purchaseIp = ''): array
    {
        $planModel = new VpnPlan();
        $plan = $planModel->find($planId);

        if (!$plan || ($plan['status'] ?? 'active') !== 'active') {
            return ['status' => false, 'message' => 'Gói dịch vụ không tồn tại hoặc đã bị ẩn.'];
        }

        $originalPrice = (float)($plan['price'] ?? 0);
        $discountAmount = 0.0;

        if (!empty($couponCode)) {
            $couponModel = new Coupon();
            $coupon = method_exists($couponModel, 'findByCode') 
                ? $couponModel->findByCode($couponCode) 
                : null;

            if ($coupon && ($coupon['status'] ?? '') === 'active') {
                $isExpired = !empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time();
                $isMaxUsed = !empty($coupon['max_uses']) && ((int)($coupon['used_count'] ?? 0) >= (int)($coupon['max_uses']));

                if (!$isExpired && !$isMaxUsed) {
                    $discountType = $coupon['discount_type'] ?? 'percent';
                    $discountVal  = (float)($coupon['discount_value'] ?? 0);

                    if ($discountType === 'percent') {
                        $discountAmount = ($originalPrice * $discountVal) / 100;
                    } else {
                        $discountAmount = $discountVal;
                    }

                    if ($discountAmount > $originalPrice) {
                        $discountAmount = $originalPrice;
                    }
                }
            }
        }

        $finalPrice = max(0.0, $originalPrice - $discountAmount);

        // Quy đổi tiền tệ theo cổng thanh toán: VietQR luôn nhận VND, WeChat/Alipay luôn nhận CNY
        $finalPrice = $this->convertAmountForGateway($finalPrice, $paymentMethod);

        // Đối với WeChat Pay: Tạo số tiền lẻ duy nhất (+0.01 đến +0.99 CNY) để khớp tự động
        if ($paymentMethod === 'wechat') {
            $randomCent = rand(1, 99) / 100;
            $finalPrice = round($finalPrice + $randomCent, 2);
        }

        $orderCode = 'LS' . date('YmdHis') . rand(100, 999);

        $orderData = [
            'order_code'     => $orderCode,
            'user_id'        => $userId,
            'plan_id'        => $planId,
            'total_amount'   => $finalPrice,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'purchase_ip' => $purchaseIp !== '' ? $purchaseIp : null,
            'created_by' => $userId,
            'created_at'     => date('Y-m-d H:i:s')
        ];

        $orderModel = new Order();
        $created = $orderModel->create($orderData);

        if ($created) {
            $orderId = (int)$orderModel->lastInsertId();

            $settingModel = new Setting();
            $orderSyntax = trim((string)($settingModel->get('order_transfer_syntax', 'THANHTOAN') ?? 'THANHTOAN'));
            $transferContent = $orderSyntax . str_pad((string)$orderId, 2, '0', STR_PAD_LEFT);
            $orderModel->update($orderId, ['transfer_content' => $transferContent]);

            if ($paymentMethod === 'balance') {
                $userModel = new User();

                if (!$userModel->debitBalance($userId, $finalPrice)) {
                    $orderModel->delete($orderId);
                    return ['status' => false, 'message' => 'Số dư không đủ để thanh toán gói dịch vụ này.'];
                }

                if (!$this->activateOrder($orderId)) {
                    $userModel->creditBalance($userId, $finalPrice);
                    return ['status' => false, 'message' => 'Không thể kích hoạt gói dịch vụ. Số dư đã được hoàn lại.'];
                }

                (new Payment())->create([
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'type' => 'payment',
                    'payment_method' => 'balance',
                    'transaction_id' => 'BAL' . date('YmdHis') . rand(100, 999),
                    'amount' => $finalPrice,
                    'status' => 'success',
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $this->notifyAdministratorsAboutNewOrder(
                    $userId,
                    $orderCode,
                    $plan,
                    $finalPrice,
                    $paymentMethod,
                    $orderId
                );

                return [
                    'status' => true,
                    'message' => 'Thanh toán bằng số dư thành công. Gói dịch vụ đã được kích hoạt.',
                    'order_code' => $orderCode,
                    'order_id' => $orderId,
                    'payment_method' => $paymentMethod,
                    'amount' => $finalPrice
                ];
            }

            (new Payment())->create([
                'user_id'        => $userId,
                'order_id'       => $orderId,
                'type'           => 'payment',
                'payment_method' => $paymentMethod,
                'transaction_id' => $orderCode,
                'amount'         => $finalPrice,
                'status'         => 'pending',
                'created_at'     => date('Y-m-d H:i:s')
            ]);

            $this->notifyAdministratorsAboutNewOrder(
                $userId,
                $orderCode,
                $plan,
                $finalPrice,
                $paymentMethod,
                $orderId
            );

            return [
                'status'     => true,
                'message'    => 'Tạo đơn hàng thành công.',
                'order_code' => $orderCode,
                'order_id'   => $orderId,
                'payment_method' => $paymentMethod,
                'amount'     => $finalPrice
            ];
        }

        return ['status' => false, 'message' => 'Không thể khởi tạo đơn hàng. Vui lòng thử lại.'];
    }

    private function notifyAdministratorsAboutNewOrder(
        int $userId,
        string $orderCode,
        array $plan,
        float $amount,
        string $paymentMethod,
        int $orderId
    ): void {
        $userModel = new User();
        $buyer = $userModel->findById($userId);
        $administrators = $userModel->getAll('', 'admin', 'active');

        if (!$buyer || empty($administrators)) {
            return;
        }

        $mailService = new MailService();
        $data = [
            'orderCode' => $orderCode,
            'planName' => (string) ($plan['name'] ?? 'Gói VPN'),
            'amount' => number_format($amount, 2, '.', ','),
            'paymentMethod' => $paymentMethod,
            'customerName' => (string) ($buyer['username'] ?? ''),
            'customerEmail' => (string) ($buyer['email'] ?? ''),
            'orderId' => $orderId,
            'siteUrl' => getenv('APP_URL') ?: ''
        ];

        foreach ($administrators as $administrator) {
            $email = trim((string) ($administrator['email'] ?? ''));
            if ($email !== '') {
                $mailService->send(
                    $email,
                    'Đơn hàng mới ' . $orderCode,
                    'orders.new-order-admin',
                    $data
                );
            }
        }
    }

    /**
     * Kích hoạt gói dịch vụ sau khi thanh toán thành công và đồng bộ Task xuống VPS
     */
    public function activateOrder(int $orderId): bool
    {
        $orderModel = new Order();
        $order = $orderModel->find($orderId);

        $currentStatus = $order['payment_status'] ?? $order['status'] ?? '';
        if (!$order || $currentStatus === 'completed') {
            return false;
        }

        // 1. Cập nhật trạng thái đơn hàng sang completed
        $orderModel->update($orderId, [
            'payment_status' => 'completed',
            'updated_at'     => date('Y-m-d H:i:s')
        ]);

        // 2. Lấy thông tin gói cước
        $planModel = new VpnPlan();
        $plan = $planModel->find((int)$order['plan_id']);
        if (!$plan) {
            return false;
        }

        $durationDays = (int)($plan['duration_days'] ?? 30);
        $bandwidthGb  = (int)($plan['bandwidth_limit_gb'] ?? 0);

        // 3. Tạo gói đăng ký (Subscription) cho người dùng
        $subModel = new Subscription();
        $vpnService = new VpnService();

        $uuid = method_exists($vpnService, 'generateUuid') 
            ? $vpnService->generateUuid() 
            : sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        $transferEnable = $bandwidthGb * 1024 * 1024 * 1024;
        $startDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

        $subData = [
            'user_id'         => $order['user_id'],
            'plan_id'         => $order['plan_id'],
            'order_id'        => $order['id'],
            'uuid'            => $uuid,
            'transfer_enable' => $transferEnable,
            'upload'          => 0,
            'download'        => 0,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'status'          => 'active',
            'created_at'      => $startDate,
            'updated_at'      => $startDate
        ];

        $created = (bool)$subModel->create($subData);

        // 4. Đẩy Task tự động xuống tất cả các máy chủ VPS thuộc các group_id được chọn trong gói cước
        if ($created && class_exists('App\Models\NodeTask') && !empty($plan['group_id'])) {
            $groupIds = json_decode($plan['group_id'] ?? '[]', true);
            if (!is_array($groupIds)) {
                $groupIds = !empty($plan['group_id']) ? [(int)$plan['group_id']] : [];
            }

            $sub = $subModel->findByUuid($uuid);
            $subId = $sub['id'] ?? 0;

            if ($subId > 0 && !empty($groupIds)) {
                $nodeTaskModel = new NodeTask();
                foreach ($groupIds as $gId) {
                    $gId = (int)$gId;
                    if ($gId > 0) {
                        $nodeTaskModel->createTasksForGroup($gId, 'add_user', [
                            'uuid'            => $uuid,
                            'end_date'        => $endDate,
                            'username'        => 'sub_' . $subId,
                            'transfer_enable' => $transferEnable
                        ]);
                    }
                }
            }
        }

        return $created;
    }

    /**
     * Xử lý thanh toán đơn hàng theo mã đơn hàng (VietQR)
     */
    public function processPaymentByOrderCode(string $orderCode, float $amount, string $transactionId = ''): array
    {
        $orderModel = new Order();
        $order = method_exists($orderModel, 'findByOrderCode') 
            ? $orderModel->findByOrderCode($orderCode) 
            : null;

        if (!$order && method_exists($orderModel, 'where')) {
            $orders = $orderModel->where('order_code', $orderCode);
            $order = $orders[0] ?? null;
        }

        if (!$order) {
            return ['status' => false, 'message' => 'Đơn hàng ' . $orderCode . ' không tồn tại.'];
        }

        $currentStatus = $order['payment_status'] ?? $order['status'] ?? '';
        if ($currentStatus === 'completed') {
            return ['status' => false, 'message' => 'Đơn hàng ' . $orderCode . ' đã được thanh toán trước đó.'];
        }

        $expectedAmount = (float)($order['total_amount'] ?? $order['final_amount'] ?? $order['price'] ?? 0);
        if ($amount < $expectedAmount) {
            return ['status' => false, 'message' => 'Số tiền chuyển khoản (' . $amount . ') nhỏ hơn số tiền đơn hàng (' . $expectedAmount . ').'];
        }

        $activated = $this->activateOrder((int)$order['id']);
        if ($activated) {
            $paymentModel = new Payment();
            $existingPayment = method_exists($paymentModel, 'findPendingByOrderId')
                ? $paymentModel->findPendingByOrderId((int)$order['id'])
                : null;

            if ($existingPayment) {
                $paymentModel->update((int)$existingPayment['id'], [
                    'status'         => 'success',
                    'transaction_id' => $transactionId ?: ($existingPayment['transaction_id'] ?? $orderCode),
                    'amount'         => $amount,
                    'payment_method' => $existingPayment['payment_method'] ?? ($order['payment_method'] ?? 'vietqr'),
                ]);
            } else {
                $paymentModel->create([
                    'user_id'        => $order['user_id'],
                    'order_id'       => $order['id'],
                    'type'           => 'payment',
                    'payment_method' => $order['payment_method'] ?? 'vietqr',
                    'transaction_id' => $transactionId ?: ('TXN' . time() . rand(100, 999)),
                    'amount'         => $amount,
                    'status'         => 'success',
                    'created_at'     => date('Y-m-d H:i:s')
                ]);
            }

            return ['status' => true, 'message' => 'Kích hoạt đơn hàng ' . $orderCode . ' thành công.'];
        }

        return ['status' => false, 'message' => 'Cập nhật đơn hàng thất bại.'];
    }

    /**
     * Xử lý thanh toán đơn hàng theo số tiền lẻ duy nhất (WeChat Pay)
     */
    public function processPaymentByAmount(float $amount, string $transactionId = ''): array
    {
        $orderModel = new Order();
        $order = $orderModel->findByPendingAmount($amount, 15);

        if (!$order) {
            return ['status' => false, 'message' => "Không tìm thấy đơn hàng chờ thanh toán trùng khớp số tiền {$amount} CNY trong 15 phút gần đây."];
        }

        $activated = $this->activateOrder((int)$order['id']);
        if ($activated) {
            $paymentModel = new Payment();
            $existingPayment = method_exists($paymentModel, 'findPendingByOrderId')
                ? $paymentModel->findPendingByOrderId((int)$order['id'])
                : null;

            if ($existingPayment) {
                $paymentModel->update((int)$existingPayment['id'], [
                    'status'         => 'success',
                    'transaction_id' => $transactionId ?: ($existingPayment['transaction_id'] ?? ('WX' . time() . rand(100, 999))),
                    'amount'         => $amount,
                    'payment_method' => 'wechat'
                ]);
            } else {
                $paymentModel->create([
                    'user_id'        => $order['user_id'],
                    'order_id'       => $order['id'],
                    'type'           => 'payment',
                    'payment_method' => 'wechat',
                    'transaction_id' => $transactionId ?: ('WX' . time() . rand(100, 999)),
                    'amount'         => $amount,
                    'status'         => 'success',
                    'created_at'     => date('Y-m-d H:i:s')
                ]);
            }

            return ['status' => true, 'message' => "Kích hoạt đơn hàng {$order['order_code']} theo số tiền {$amount} CNY thành công."];
        }

        return ['status' => false, 'message' => 'Cập nhật đơn hàng thất bại.'];
    }

    /**
     * Quy đổi số tiền gốc (theo đơn vị tiền tệ mặc định của admin) sang đơn vị
     * tiền tệ mà cổng thanh toán yêu cầu, dùng tỷ giá đã cài trong Cài đặt hệ thống.
     * VietQR luôn nhận VND, WeChat/Alipay luôn nhận CNY.
     */
    private function convertAmountForGateway(float $amount, string $paymentMethod): float
    {
        $settingModel = new Setting();
        $settings = $settingModel->getAllAsKeyValue();

        $baseCurrency = strtoupper(trim((string) ($settings['currency'] ?? 'CNY')));
        $exchangeRate = (float) ($settings['exchange_rate'] ?? 1);

        if ($exchangeRate <= 0) {
            return $amount;
        }

        if ($paymentMethod === 'vietqr' && $baseCurrency === 'CNY') {
            return round($amount * $exchangeRate);
        }

        if (in_array($paymentMethod, ['wechat', 'alipay'], true) && $baseCurrency === 'VND') {
            return round($amount / $exchangeRate, 2);
        }

        return $amount;
    }
}