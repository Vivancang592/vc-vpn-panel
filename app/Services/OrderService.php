<?php

namespace App\Services;

use App\Models\BaseModel;
use App\Models\Order;
use App\Models\VpnPlan;
use App\Models\Coupon;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\NodeTask;
use App\Models\User;
use App\Models\Setting;
use App\Models\ReferralCommission;

class OrderService
{
    /**
     * Xử lý tạo đơn hàng mới (Chuẩn hóa duy nhất đơn vị VND)
     */
    public function createOrder(
        int $userId,
        int $planId,
        ?string $couponCode = null,
        string $paymentMethod = 'vietqr',
        string $purchaseIp = ''
    ): array {
        $planModel = new VpnPlan();
        $plan = $planModel->find($planId);

        if (!$plan || ($plan['status'] ?? 'active') !== 'active') {
            return ['status' => false, 'message' => 'Gói dịch vụ không tồn tại hoặc đã bị ẩn.'];
        }

        $originalPrice = (float)($plan['price'] ?? 0);
        $discountAmount = 0.0;
        $couponId = null;

        if (!empty($couponCode)) {
            $couponModel = new Coupon();
            $coupon = method_exists($couponModel, 'findByCode') 
                ? $couponModel->findByCode($couponCode) 
                : null;

            if ($coupon && ($coupon['status'] ?? '') === 'active') {
                $isExpired = !empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time();
                $isMaxUsed = !empty($coupon['max_uses']) && ((int)($coupon['used_count'] ?? 0) >= (int)($coupon['max_uses']));

                if (!$isExpired && !$isMaxUsed) {
                    $couponId = (int)$coupon['id'];
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

        $finalPrice = max(0.0, round($originalPrice - $discountAmount, 0));
        $orderCode = 'LS' . date('YmdHis') . random_int(100, 99999);

        $orderData = [
            'order_code'     => $orderCode,
            'user_id'        => $userId,
            'plan_id'        => $planId,
            'total_amount'   => $finalPrice,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'purchase_ip'    => $purchaseIp !== '' ? $purchaseIp : null,
            'created_by'     => $userId,
            'created_at'     => date('Y-m-d H:i:s')
        ];

        $orderModel = new Order();
        $created = $orderModel->create($orderData);

        if ($created) {
            $orderId = (int)$orderModel->lastInsertId();

            $transferContent = null;
            if ($paymentMethod !== 'balance') {
                $settingModel = new Setting();
                $orderSyntax = trim((string)($settingModel->get('order_transfer_syntax', 'THANHTOAN') ?? 'THANHTOAN'));
                $transferContent = $orderSyntax . str_pad((string)$orderId, 2, '0', STR_PAD_LEFT);
            }
            $orderModel->update($orderId, ['transfer_content' => $transferContent]);

            if ($paymentMethod === 'balance') {
                $userModel = new User();

                if (!$userModel->debitBalance($userId, $finalPrice)) {
                    $orderModel->delete($orderId);
                    return ['status' => false, 'message' => 'Số dư trong ví không đủ để thanh toán gói dịch vụ này.'];
                }

                if (!$this->activateOrder($orderId, $couponId)) {
                    $userModel->creditBalance($userId, $finalPrice);
                    return ['status' => false, 'message' => 'Không thể kích hoạt gói dịch vụ. Số dư đã được hoàn lại ví.'];
                }

                (new Payment())->create([
                    'user_id'          => $userId,
                    'order_id'         => $orderId,
                    'type'             => 'payment',
                    'payment_method'   => 'balance',
                    'transaction_id'   => 'BAL' . date('YmdHis') . rand(100, 999),
                    'transfer_content' => $transferContent,
                    'amount'           => $finalPrice,
                    'status'           => 'success',
                    'created_at'       => date('Y-m-d H:i:s')
                ]);

                $email = trim((string) ($userModel->findById($userId)['email'] ?? ''));
                if ($email !== '') {
                    (new MailService())->send($email, 'Thanh toán thành công', 'orders.payment-completed', [
                        'orderCode' => $orderCode,
                        'amount' => number_format($finalPrice, 0, '.', ',') . ' đ',
                        'description' => 'Đơn hàng của bạn đã được thanh toán bằng số dư và kích hoạt thành công.'
                    ]);
                }

                $this->notifyAdministratorsAboutNewOrder(
                    $userId,
                    $orderCode,
                    $plan,
                    $finalPrice,
                    $paymentMethod,
                    $orderId
                );

                return [
                    'status'         => true,
                    'message'        => 'Thanh toán bằng số dư thành công. Gói dịch vụ đã được kích hoạt.',
                    'order_code'     => $orderCode,
                    'order_id'       => $orderId,
                    'payment_method' => $paymentMethod,
                    'amount'         => $finalPrice
                ];
            }

            (new Payment())->create([
                'user_id'          => $userId,
                'order_id'         => $orderId,
                'type'             => 'payment',
                'payment_method'   => $paymentMethod,
                'transaction_id'   => null,
                'transfer_content' => $transferContent,
                'amount'           => $finalPrice,
                'status'           => 'pending',
                'created_at'       => date('Y-m-d H:i:s')
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
                'status'         => true,
                'message'        => 'Tạo đơn hàng thành công.',
                'order_code'     => $orderCode,
                'order_id'       => $orderId,
                'payment_method' => $paymentMethod,
                'amount'         => $finalPrice
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
            'orderCode'     => $orderCode,
            'planName'      => (string) ($plan['name'] ?? 'Gói VPN'),
            'amount'        => number_format($amount, 0, '.', ',') . ' đ',
            'paymentMethod' => $paymentMethod === 'vietqr' ? 'VietQR (Chuyển khoản)' : ($paymentMethod === 'balance' ? 'Số dư ví' : $paymentMethod),
            'customerName'  => (string) ($buyer['username'] ?? ''),
            'customerEmail' => (string) ($buyer['email'] ?? ''),
            'orderId'       => $orderId,
            'siteUrl'       => getenv('APP_URL') ?: ''
        ];

        foreach ($administrators as $administrator) {
            $email = trim((string) ($administrator['email'] ?? ''));
            if ($email !== '') {
                $mailService->send(
                    $email,
                    'Đơn hàng mới #' . $orderCode,
                    'orders.new-order-admin',
                    $data
                );
            }
        }
    }

    /**
     * Kích hoạt gói dịch vụ sau khi thanh toán thành công và đồng bộ Task xuống VPS
     * Thực hiện an toàn trong Database Transaction
     */
    public function activateOrder(int $orderId, ?int $couponId = null): bool
    {
        $orderModel = new Order();
        $order = $orderModel->find($orderId);

        if (!$order) {
            return false;
        }

        $currentStatus = $order['payment_status'] ?? $order['status'] ?? '';
        if ($currentStatus === 'completed') {
            return true; // Idempotent: đã kích hoạt rồi
        }

        BaseModel::beginTransaction();

        try {
            // 1. Cập nhật trạng thái đơn hàng sang completed
            $orderModel->update($orderId, [
                'payment_status' => 'completed',
                'updated_at'     => date('Y-m-d H:i:s')
            ]);

            // 1.1 Nếu là đơn nạp tiền ví (không có plan_id)
            if (empty($order['plan_id'])) {
                $userModel = new User();
                $credited = $userModel->creditBalance((int)$order['user_id'], (float)$order['total_amount']);
                if (!$credited) {
                    BaseModel::rollBack();
                    return false;
                }

                $paymentModel = new Payment();
                $pendingPayment = $paymentModel->findPendingByOrderId($orderId);
                if ($pendingPayment) {
                    $paymentModel->update((int)$pendingPayment['id'], [
                        'status' => 'success'
                    ]);
                }

                BaseModel::commit();
                return true;
            }

            // 2. Lấy thông tin gói cước (Đơn mua mới / gia hạn)
            $planModel = new VpnPlan();
            $plan = $planModel->find((int)$order['plan_id']);
            if (!$plan) {
                BaseModel::rollBack();
                return false;
            }

            $durationDays = (int)($plan['duration_days'] ?? 30);
            $bandwidthGb  = (int)($plan['bandwidth_limit_gb'] ?? 0);
            $maxDevices   = (int)($plan['max_devices'] ?? 1);

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
                'max_devices'     => $maxDevices,
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
            if (!$created) {
                BaseModel::rollBack();
                return false;
            }

            $subId = (int)$subModel->lastInsertId();

            // 4. Đẩy Task tự động xuống tất cả các máy chủ VPS thuộc các group_id được chọn trong gói cước
            if (class_exists('App\Models\NodeTask') && !empty($plan['group_id'])) {
                $groupIds = json_decode($plan['group_id'] ?? '[]', true);
                if (!is_array($groupIds)) {
                    $groupIds = !empty($plan['group_id']) ? [(int)$plan['group_id']] : [];
                }

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

            // 5. Tăng lượt sử dụng mã giảm giá (Coupon) nếu có
            if ($couponId !== null && $couponId > 0 && class_exists('App\Models\Coupon')) {
                (new Coupon())->incrementUsedCount($couponId);
            }

            // 6. Xử lý tính hoa hồng giới thiệu (Referral Commission)
            $userModel = new User();
            $buyer = $userModel->findById((int)$order['user_id']);
            $referrerId = (int)($buyer['referred_by'] ?? 0);

            if ($referrerId > 0 && $referrerId !== (int)$order['user_id']) {
                $settingModel = new Setting();
                $rate = (float)($settingModel->get('referral_commission_rate', '10') ?? 10);
                $orderTotal = (float)($order['total_amount'] ?? 0);
                $commissionAmount = round($orderTotal * ($rate / 100), 0);

                if ($commissionAmount > 0 && class_exists('App\Models\ReferralCommission')) {
                    (new ReferralCommission())->create([
                        'referrer_id'       => $referrerId,
                        'referred_user_id'  => (int)$order['user_id'],
                        'order_id'          => (int)$order['id'],
                        'commission_rate'   => $rate,
                        'commission_amount' => $commissionAmount,
                        'created_at'        => date('Y-m-d H:i:s')
                    ]);

                    $userModel->creditCommissionBalance($referrerId, $commissionAmount);
                }
            }

            BaseModel::commit();
            return true;
        } catch (\Throwable $e) {
            BaseModel::rollBack();
            error_log('Error in activateOrder: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Xử lý thanh toán đơn hàng theo mã đơn hàng hoặc cú pháp chuyển khoản (VietQR / Webhook)
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

        if (!$order && method_exists($orderModel, 'findByTransferContent')) {
            $order = $orderModel->findByTransferContent($orderCode);
        }

        if (!$order) {
            return ['status' => false, 'message' => 'Đơn hàng ' . $orderCode . ' không tồn tại.'];
        }

        $currentStatus = $order['payment_status'] ?? $order['status'] ?? '';
        if ($currentStatus === 'completed') {
            return ['status' => true, 'message' => 'Đơn hàng ' . $orderCode . ' đã được kích hoạt trước đó.'];
        }

        if ($currentStatus !== 'pending') {
            return ['status' => false, 'message' => 'Đơn hàng ' . $orderCode . ' không ở trạng thái chờ thanh toán (trạng thái: ' . $currentStatus . ').'];
        }

        $expectedAmount = (float)($order['total_amount'] ?? $order['final_amount'] ?? $order['price'] ?? 0);
        if (abs($amount - $expectedAmount) > 1.0) {
            return ['status' => false, 'message' => 'Số tiền chuyển khoản (' . number_format($amount, 0, ',', '.') . ' đ) không khớp với số tiền đơn hàng (' . number_format($expectedAmount, 0, ',', '.') . ' đ).'];
        }

        $activated = $this->activateOrder((int)$order['id']);
        if ($activated) {
            $paymentModel = new Payment();
            $existingPayment = method_exists($paymentModel, 'findPendingByOrderId')
                ? $paymentModel->findPendingByOrderId((int)$order['id'])
                : null;

            $transId = !empty($transactionId) ? $transactionId : null;

            if ($existingPayment) {
                $paymentModel->update((int)$existingPayment['id'], [
                    'status'           => 'success',
                    'transaction_id'   => $transId ?? ($existingPayment['transaction_id'] ?? null),
                    'transfer_content' => $existingPayment['transfer_content'] ?? ($order['transfer_content'] ?? null),
                    'amount'           => $amount,
                    'payment_method'   => $existingPayment['payment_method'] ?? ($order['payment_method'] ?? 'vietqr'),
                ]);
            } else {
                $paymentModel->create([
                    'user_id'          => $order['user_id'],
                    'order_id'         => $order['id'],
                    'type'             => 'payment',
                    'payment_method'   => $order['payment_method'] ?? 'vietqr',
                    'transaction_id'   => $transId,
                    'transfer_content' => $order['transfer_content'] ?? null,
                    'amount'           => $amount,
                    'status'           => 'success',
                    'created_at'       => date('Y-m-d H:i:s')
                ]);
            }

            return ['status' => true, 'message' => 'Kích hoạt đơn hàng #' . ($order['order_code'] ?? $orderCode) . ' thành công.'];
        }

        return ['status' => false, 'message' => 'Kích hoạt đơn hàng thất bại.'];
    }
}