<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\VpnPlan;

class PaymentService
{
    /**
     * Tạo giao dịch nạp tiền vào ví
     */
    public function createDepositTransaction(
        int $userId,
        float $amount,
        string $paymentMethod
    ): array {
        $settingModel = new Setting();
        $settings = $settingModel->getAllAsKeyValue();

        $minDeposit = (float) (
            $settings['min_deposit']
            ?? $settings['min_deposit_amount']
            ?? 10
        );

        $symbol = $settings['currency_symbol'] ?? '¥';

        if ($amount < $minDeposit) {
            return [
                'status' => false,
                'message' =>
                    'Số tiền nạp tối thiểu là '
                    . number_format($minDeposit, 2, '.', ',')
                    . ' '
                    . $symbol
                    . '.'
            ];
        }

        $transCode = 'DEP'
            . date('YmdHis')
            . rand(100, 999);

        $paymentData = [
            'transaction_id' => $transCode,
            'user_id' => $userId,
            'order_id' => null,
            'subscription_id' => null,
            'type' => 'deposit',
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $paymentModel = new Payment();

        $created = $paymentModel->create($paymentData);

        if ($created) {
            return [
                'status' => true,
                'message' => 'Tạo giao dịch nạp tiền thành công.',
                'transaction_code' => $transCode,
                'amount' => $amount,
                'payment_id' => (int) $created
            ];
        }

        return [
            'status' => false,
            'message' => 'Không thể tạo giao dịch nạp tiền.'
        ];
    }

    /**
     * Tạo giao dịch thanh toán gia hạn subscription.
     *
     * Payment renewal không tạo order mới.
     * Nó liên kết trực tiếp với subscription hiện tại
     * thông qua subscription_id.
     */
    public function createRenewalTransaction(
        int $userId,
        int $subscriptionId,
        string $paymentMethod
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
                'message' => 'Không tìm thấy gói cước của subscription.'
            ];
        }

        $amount = (float) ($plan['price'] ?? 0);

        if ($amount <= 0) {
            return [
                'status' => false,
                'message' => 'Giá gia hạn của gói dịch vụ không hợp lệ.'
            ];
        }

        /*
         * Không cho tạo thêm payment renewal nếu subscription
         * đang có một giao dịch pending.
         */
        $paymentModel = new Payment();

        if (method_exists($paymentModel, 'where')) {
            $pendingPayments = $paymentModel->where(
                'subscription_id',
                $subscriptionId
            );

            foreach ($pendingPayments as $pendingPayment) {
                if (
                    ($pendingPayment['status'] ?? '') === 'pending'
                    && ($pendingPayment['type'] ?? '') === 'payment'
                ) {
                    return [
                        'status' => true,
                        'message' => 'Đã có giao dịch gia hạn đang chờ thanh toán.',
                        'transaction_code' =>
                            (string) ($pendingPayment['transaction_id'] ?? ''),
                        'payment_id' => (int) $pendingPayment['id'],
                        'subscription_id' => $subscriptionId,
                        'amount' => (float) $pendingPayment['amount']
                    ];
                }
            }
        }

        $transCode = 'REN'
            . date('YmdHis')
            . rand(100, 999);

        $paymentData = [
            'transaction_id' => $transCode,
            'user_id' => $userId,
            'order_id' => null,
            'subscription_id' => $subscriptionId,
            'type' => 'payment',
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $created = $paymentModel->create($paymentData);

        if (!$created) {
            return [
                'status' => false,
                'message' => 'Không thể tạo giao dịch gia hạn.'
            ];
        }

        return [
            'status' => true,
            'message' => 'Tạo giao dịch gia hạn thành công.',
            'transaction_code' => $transCode,
            'payment_id' => (int) $created,
            'subscription_id' => $subscriptionId,
            'amount' => $amount
        ];
    }

    /**
     * Hoàn tất một payment.
     *
     * - deposit:
     *   cộng tiền vào balance.
     *
     * - payment + order_id:
     *   để OrderService xử lý activation order.
     *
     * - payment + subscription_id:
     *   gia hạn subscription hiện tại.
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
         * PAYMENT NẠP TIỀN
         */
        if (($payment['type'] ?? '') === 'deposit') {
            $paymentUpdated = $paymentModel->update($paymentId, [
                'status' => 'success'
            ]);

            if (!$paymentUpdated) {
                return false;
            }

            $userModel = new User();
            $user = $userModel->find((int) $payment['user_id']);

            if (!$user) {
                return false;
            }

            $newBalance =
                (float) ($user['balance'] ?? 0)
                + (float) $payment['amount'];

            return $userModel->update(
                (int) $user['id'],
                [
                    'balance' => $newBalance,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
        }

        /*
         * Payment của order mua gói mới:
         * PaymentService không tự activate order ở đây.
         * OrderService / webhook order flow hiện tại tiếp tục
         * chịu trách nhiệm xử lý order.
         */
        if (
            ($payment['type'] ?? '') === 'payment'
            && !empty($payment['order_id'])
        ) {
            return $paymentModel->update($paymentId, [
                'status' => 'success'
            ]);
        }

        return false;
    }

    /**
     * Hoàn tất payment gia hạn.
     *
     * Nếu subscription còn hạn:
     *     end_date = end_date + duration
     *
     * Nếu subscription đã hết hạn:
     *     end_date = NOW() + duration
     *
     * Như vậy người dùng gia hạn trước ngày hết hạn
     * sẽ không bị mất số ngày còn lại.
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

        $durationDays = max(
            1,
            (int) ($plan['duration_days'] ?? 30)
        );

        $now = new \DateTimeImmutable();

        $currentEndDate = null;

        if (!empty($subscription['end_date'])) {
            try {
                $currentEndDate = new \DateTimeImmutable(
                    (string) $subscription['end_date']
                );
            } catch (\Throwable $e) {
                $currentEndDate = null;
            }
        }

        /*
         * Còn hạn:
         * cộng thêm vào end_date hiện tại.
         *
         * Hết hạn:
         * tính từ thời điểm hiện tại.
         */
        if (
            $currentEndDate !== null
            && $currentEndDate > $now
        ) {
            $newEndDate = $currentEndDate->modify(
                '+' . $durationDays . ' days'
            );
        } else {
            $newEndDate = $now->modify(
                '+' . $durationDays . ' days'
            );
        }

        /*
         * Nếu subscription đang suspended do hết traffic,
         * chỉ gia hạn thời gian không nên tự ý bật lại.
         *
         * Nếu đã expired thì chuyển lại active.
         */
        $newStatus = ($subscription['status'] ?? '') === 'expired'
            ? 'active'
            : ($subscription['status'] ?? 'active');

        $updatedSubscription = $subscriptionModel->update(
            $subscriptionId,
            [
                'end_date' => $newEndDate->format('Y-m-d H:i:s'),
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );

        if (!$updatedSubscription) {
            return false;
        }

        /*
         * Chỉ đánh dấu payment success sau khi subscription
         * đã gia hạn thành công.
         */
        return (bool) $paymentModel->update(
            (int) $payment['id'],
            [
                'status' => 'success'
            ]
        );
    }

    /**
     * Hoàn tất payment theo mã giao dịch từ Webhook.
     *
     * Kiểm tra số tiền webhook gửi về phải >= số tiền
     * giao dịch trước khi hoàn tất.
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
        $payment = null;

        if (method_exists($paymentModel, 'where')) {
            $payments = $paymentModel->where(
                'transaction_id',
                $transCode
            );

            $payment = $payments[0] ?? null;
        }

        if (!$payment) {
            return false;
        }

        /*
         * Không cho webhook báo số tiền thấp hơn số tiền
         * hệ thống yêu cầu.
         */
        $requiredAmount = (float) ($payment['amount'] ?? 0);

        if ($requiredAmount <= 0 || $amount < $requiredAmount) {
            return false;
        }

        /*
         * Nếu webhook gửi lại giao dịch đã success,
         * coi là thành công để webhook idempotent.
         */
        if (($payment['status'] ?? '') === 'success') {
            return true;
        }

        return $this->completePayment(
            (int) $payment['id']
        );
    }
}