<?php

namespace App\Services;

use App\Models\BaseModel;
use App\Models\Payment;
use App\Models\User;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\VpnPlan;
use App\Models\Order;
use App\Models\NodeTask;

class PaymentService
{
    /**
     * Tạo đơn nạp tiền chờ thanh toán.
     */
    public function createDepositTransaction(
        int $userId,
        float $amount,
        string $paymentMethod = 'vietqr',
        string $purchaseIp = ''
    ): array {
        $settingModel = new Setting();
        $settings = $settingModel->getAllAsKeyValue();

        $minDeposit = (float) (
            $settings['min_deposit_amount']
            ?? $settings['min_deposit']
            ?? 10000
        );

        $symbol = $settings['currency_symbol'] ?? 'đ';

        if ($amount < $minDeposit) {
            return [
                'status' => false,
                'message' =>
                    'Số tiền nạp tối thiểu là '
                    . number_format($minDeposit, 0, '.', ',')
                    . ' '
                    . $symbol
                    . '.'
            ];
        }

        $amount = round($amount, 0);
        $orderCode = 'DEP' . date('YmdHis') . random_int(100, 99999);
        $orderModel = new Order();

        if (!$orderModel->create([
            'order_code'     => $orderCode,
            'user_id'        => $userId,
            'plan_id'        => null,
            'total_amount'   => $amount,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'purchase_ip'    => $purchaseIp !== '' ? $purchaseIp : null,
            'created_by'     => $userId,
            'created_at'     => date('Y-m-d H:i:s')
        ])) {
            return ['status' => false, 'message' => 'Không thể tạo đơn hàng nạp tiền.'];
        }

        $orderId = (int) $orderModel->lastInsertId();

        try {
            $depositSyntax = trim((string) ($settings['bank_transfer_syntax'] ?? 'NAPTIEN'));
            $transferContent = $depositSyntax . str_pad((string) $orderId, 2, '0', STR_PAD_LEFT);
            $orderModel->update($orderId, ['transfer_content' => $transferContent]);

        } catch (\Throwable $exception) {
            $orderModel->delete($orderId);

            return [
                'status' => false,
                'message' => 'Không thể tạo giao dịch nạp tiền. Vui lòng liên hệ quản trị viên để kiểm tra cơ sở dữ liệu.'
            ];
        }

        return [
            'status'           => true,
            'message'          => 'Tạo đơn nạp tiền thành công.',
            'transfer_content' => $transferContent,
            'amount'           => $amount,
            'order_id'         => $orderId,
            'order_code'       => $orderCode
        ];
    }

    /**
     * Tạo đơn hàng và giao dịch thanh toán cho gia hạn subscription.
     */
    public function createRenewalTransaction(
        int $userId,
        int $subscriptionId,
        string $paymentMethod = 'vietqr',
        string $purchaseIp = ''
    ): array {
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->find($subscriptionId);

        if (!$subscription) {
            return [
                'status' => false,
                'message' => 'Không tìm thấy gói dịch vụ cần gia hạn.'
            ];
        }

        if ((int) $subscription['user_id'] !== $userId) {
            return [
                'status' => false,
                'message' => 'Bạn không có quyền gia hạn gói dịch vụ này.'
            ];
        }

        $planModel = new VpnPlan();
        $plan = $planModel->find((int) $subscription['plan_id']);

        if (!$plan) {
            return [
                'status' => false,
                'message' => 'Không tìm thấy gói cước của gói dịch vụ.'
            ];
        }

        $amount = (float) ($plan['price'] ?? 0);

        if ($amount <= 0) {
            return [
                'status' => false,
                'message' => 'Giá gia hạn của gói dịch vụ không hợp lệ.'
            ];
        }

        $amount = round($amount, 0);

        // Hỗ trợ gia hạn trực tiếp bằng số dư ví (Balance)
        if ($paymentMethod === 'balance') {
            $userModel = new User();
            if (!$userModel->debitBalance($userId, $amount)) {
                return ['status' => false, 'message' => 'Số dư trong ví không đủ để gia hạn gói dịch vụ này.'];
            }

            $orderModel = new Order();
            $orderCode = 'RE' . date('YmdHis') . rand(100, 999);
            $orderModel->create([
                'order_code'     => $orderCode,
                'user_id'        => $userId,
                'plan_id'        => (int) $subscription['plan_id'],
                'total_amount'   => $amount,
                'payment_method' => 'balance',
                'payment_status' => 'completed',
                'purchase_ip'    => $purchaseIp !== '' ? $purchaseIp : null,
                'created_by'     => $userId,
                'created_at'     => date('Y-m-d H:i:s')
            ]);
            $orderId = (int)$orderModel->lastInsertId();

            $transCode = 'BAL-REN' . date('YmdHis') . rand(100, 999);
            $paymentModel = new Payment();
            $paymentModel->create([
                'transaction_id'  => $transCode,
                'user_id'         => $userId,
                'order_id'        => $orderId,
                'subscription_id' => $subscriptionId,
                'type'            => 'payment',
                'amount'          => $amount,
                'payment_method'  => 'balance',
                'status'          => 'pending',
                'created_at'      => date('Y-m-d H:i:s')
            ]);
            $paymentId = (int)$paymentModel->lastInsertId();

            $payment = $paymentModel->find($paymentId);
            if ($payment && $this->completeRenewalPayment($payment)) {
                return [
                    'status'           => true,
                    'message'          => 'Gia hạn gói dịch vụ bằng số dư thành công!',
                    'payment_id'       => $paymentId,
                    'order_id'         => $orderId,
                    'subscription_id'  => $subscriptionId,
                    'amount'           => $amount,
                    'is_completed'     => true
                ];
            } else {
                $userModel->creditBalance($userId, $amount);
                return ['status' => false, 'message' => 'Gia hạn thất bại. Số dư đã được hoàn lại ví.'];
            }
        }

        $paymentModel = new Payment();
        $pendingPayment = $paymentModel->findPendingRenewalBySubscriptionId($subscriptionId);
        if ($pendingPayment !== null) {
            return [
                'status'           => true,
                'message'          => 'Đã có đơn gia hạn đang chờ thanh toán.',
                'transaction_code' => (string) ($pendingPayment['transaction_id'] ?? ''),
                'payment_id'       => (int) $pendingPayment['id'],
                'order_id'         => (int) ($pendingPayment['order_id'] ?? 0),
                'subscription_id'  => $subscriptionId,
                'amount'           => (float) $pendingPayment['amount']
            ];
        }

        $orderModel = new Order();
        $orderCode = 'RE' . date('YmdHis') . rand(100, 999);
        if (!$orderModel->create([
            'order_code'     => $orderCode,
            'user_id'        => $userId,
            'plan_id'        => (int) $subscription['plan_id'],
            'total_amount'   => $amount,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'purchase_ip'    => $purchaseIp !== '' ? $purchaseIp : null,
            'created_by'     => $userId,
            'created_at'     => date('Y-m-d H:i:s')
        ])) {
            return ['status' => false, 'message' => 'Không thể tạo đơn hàng gia hạn.'];
        }

        $orderId = (int)$orderModel->lastInsertId();
        $transCode = 'REN'
            . date('YmdHis')
            . rand(100, 999);

        $paymentData = [
            'transaction_id'  => $transCode,
            'user_id'         => $userId,
            'order_id'        => $orderId,
            'subscription_id' => $subscriptionId,
            'type'            => 'payment',
            'amount'          => $amount,
            'payment_method'  => $paymentMethod,
            'status'          => 'pending',
            'created_at'      => date('Y-m-d H:i:s')
        ];

        $created = $paymentModel->create($paymentData);

        if (!$created) {
            $orderModel->delete($orderId);
            return [
                'status' => false,
                'message' => 'Không thể tạo giao dịch gia hạn.'
            ];
        }

        $paymentId = (int) $paymentModel->lastInsertId();
        $settingModel = new Setting();
        $settings = $settingModel->getAllAsKeyValue();
        $renewalSyntax = trim((string) ($settings['renewal_transfer_syntax'] ?? 'GAHAN'));
        $transferContent = $renewalSyntax . str_pad((string) $orderId, 2, '0', STR_PAD_LEFT);
        $paymentModel->update($paymentId, ['transfer_content' => $transferContent]);
        $orderModel->update($orderId, ['transfer_content' => $transferContent]);

        return [
            'status'           => true,
            'message'          => 'Tạo giao dịch gia hạn thành công.',
            'transaction_code' => $transCode,
            'transfer_content' => $transferContent,
            'payment_id'       => $paymentId,
            'order_id'         => $orderId,
            'subscription_id'  => $subscriptionId,
            'amount'           => $amount
        ];
    }

    /**
     * Hoàn tất một payment.
     *
     * - deposit:
     *   cộng tiền vào balance nguyên tử (Atomic credit).
     *
     * - payment + subscription_id:
     *   gia hạn subscription hiện tại và đồng bộ VPS NodeTask.
     */
    public function completePayment(int $paymentId): bool
    {
        $paymentModel = new Payment();
        $payment = $paymentModel->find($paymentId);

        if (!$payment) {
            return false;
        }

        /*
         * Idempotent:
         * Webhook có thể gửi lại cùng một giao dịch.
         * Nếu đã success thì không cộng tiền / gia hạn lần nữa.
         */
        if (($payment['status'] ?? '') === 'success') {
            return true;
        }

        if (($payment['status'] ?? '') !== 'pending') {
            return false;
        }

        /*
         * PAYMENT GIA HẠN SUBSCRIPTION
         */
        if (
            ($payment['type'] ?? '') === 'payment'
            && !empty($payment['subscription_id'])
        ) {
            return $this->completeRenewalPayment($payment);
        }

        /*
         * PAYMENT NẠP TIỀN VÀO VÍ
         */
        if (($payment['type'] ?? '') === 'deposit') {
            BaseModel::beginTransaction();
            try {
                $paymentUpdated = $paymentModel->update($paymentId, [
                    'status' => 'success'
                ]);

                if (!$paymentUpdated) {
                    BaseModel::rollBack();
                    return false;
                }

                $userModel = new User();
                $credited = $userModel->creditBalance((int) $payment['user_id'], (float) $payment['amount']);

                if (!$credited) {
                    BaseModel::rollBack();
                    return false;
                }

                if (!empty($payment['order_id'])) {
                    (new Order())->update((int) $payment['order_id'], [
                        'payment_status' => 'completed',
                        'updated_at'     => date('Y-m-d H:i:s')
                    ]);
                }

                BaseModel::commit();
                return true;
            } catch (\Throwable $e) {
                BaseModel::rollBack();
                error_log('Error in completePayment deposit: ' . $e->getMessage());
                return false;
            }
        }

        /*
         * Payment của order mua gói mới:
         * Nếu có order_id, gọi OrderService để kích hoạt order
         */
        if (
            ($payment['type'] ?? '') === 'payment'
            && !empty($payment['order_id'])
        ) {
            $orderService = new OrderService();
            $activated = $orderService->activateOrder((int) $payment['order_id']);
            if ($activated) {
                return $paymentModel->update($paymentId, ['status' => 'success']);
            }
        }

        return false;
    }

    /**
     * Hoàn tất payment gia hạn an toàn trong Transaction.
     * Đồng bộ Task cập nhật thời hạn xuống VPS.
     */
    private function completeRenewalPayment(array $payment): bool
    {
        $paymentModel = new Payment();
        $subscriptionId = (int) ($payment['subscription_id'] ?? 0);

        if ($subscriptionId <= 0) {
            return false;
        }

        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->find($subscriptionId);

        if (!$subscription) {
            return false;
        }

        if ((int) $subscription['user_id'] !== (int) $payment['user_id']) {
            return false;
        }

        $planModel = new VpnPlan();
        $plan = $planModel->find((int) $subscription['plan_id']);

        if (!$plan) {
            return false;
        }

        $durationDays = max(1, (int) ($plan['duration_days'] ?? 30));
        $now = new \DateTimeImmutable();
        $currentEndDate = null;

        if (!empty($subscription['end_date'])) {
            try {
                $currentEndDate = new \DateTimeImmutable((string) $subscription['end_date']);
            } catch (\Throwable $e) {
                $currentEndDate = null;
            }
        }

        /*
         * Còn hạn: cộng thêm vào end_date hiện tại.
         * Hết hạn: tính từ thời điểm hiện tại.
         */
        if ($currentEndDate !== null && $currentEndDate > $now) {
            $newEndDate = $currentEndDate->modify('+' . $durationDays . ' days');
        } else {
            $newEndDate = $now->modify('+' . $durationDays . ' days');
        }

        $newStatus = ($subscription['status'] ?? '') === 'expired'
            ? 'active'
            : ($subscription['status'] ?? 'active');

        BaseModel::beginTransaction();

        try {
            $updatedSubscription = $subscriptionModel->update(
                $subscriptionId,
                [
                    'end_date'   => $newEndDate->format('Y-m-d H:i:s'),
                    'status'     => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );

            if (!$updatedSubscription) {
                BaseModel::rollBack();
                return false;
            }

            // Đồng bộ tác vụ xuống máy chủ VPS để cập nhật hạn dùng
            if (class_exists('App\Models\NodeTask') && !empty($plan['group_id'])) {
                $groupIds = json_decode($plan['group_id'] ?? '[]', true);
                if (!is_array($groupIds)) {
                    $groupIds = !empty($plan['group_id']) ? [(int)$plan['group_id']] : [];
                }

                if (!empty($groupIds)) {
                    $nodeTaskModel = new NodeTask();
                    foreach ($groupIds as $gId) {
                        $gId = (int)$gId;
                        if ($gId > 0) {
                            $nodeTaskModel->createTasksForGroup($gId, 'add_user', [
                                'uuid'            => $subscription['uuid'],
                                'end_date'        => $newEndDate->format('Y-m-d H:i:s'),
                                'username'        => 'sub_' . $subscriptionId,
                                'transfer_enable' => (int)($subscription['transfer_enable'] ?? 0)
                            ]);
                        }
                    }
                }
            }

            $paymentCompleted = (bool) $paymentModel->update(
                (int) $payment['id'],
                [
                    'status' => 'success'
                ]
            );

            if ($paymentCompleted && !empty($payment['order_id'])) {
                (new Order())->update((int) $payment['order_id'], [
                    'payment_status' => 'completed',
                    'updated_at'     => date('Y-m-d H:i:s')
                ]);
            }

            BaseModel::commit();
            return $paymentCompleted;
        } catch (\Throwable $e) {
            BaseModel::rollBack();
            error_log('Error in completeRenewalPayment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hoàn tất payment theo mã giao dịch hoặc cú pháp chuyển khoản từ Webhook.
     */
    public function completePaymentByCode(
        string $transCode,
        float $amount
    ): bool {
        $transCode = trim($transCode);

        if ($transCode === '' || $amount <= 0) {
            return false;
        }

        $paymentModel = new Payment();
        $payment = $paymentModel->findByTransactionId($transCode);

        if (!$payment && method_exists($paymentModel, 'findByTransferContent')) {
            $payment = $paymentModel->findByTransferContent($transCode);
        }

        if (!$payment) {
            return false;
        }

        $requiredAmount = (float) ($payment['amount'] ?? 0);
        if ($requiredAmount <= 0 || abs($amount - $requiredAmount) > 1.0) {
            return false;
        }

        if (($payment['status'] ?? '') === 'success') {
            return true;
        }

        return $this->completePayment((int) $payment['id']);
    }
}