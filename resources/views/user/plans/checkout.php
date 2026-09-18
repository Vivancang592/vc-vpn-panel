<?php
$pageTitle = 'Thanh Toán - ' . ($settings['site_title'] ?? 'VC VPN 2027');

$checkoutType = (string) ($checkoutType ?? 'plan');

$isDeposit = $checkoutType === 'deposit';
$isRenewal = $checkoutType === 'renewal';

$selectedPlan = isset($plan) && is_array($plan) ? $plan : [];
$subscription = isset($subscription) && is_array($subscription) ? $subscription : [];

$bandwidth = (int) ($selectedPlan['bandwidth_limit_gb'] ?? 0);
$duration = max(1, (int) ($selectedPlan['duration_days'] ?? 30));
$devices = max(1, (int) ($selectedPlan['max_devices'] ?? 1));

$paymentGateways = isset($paymentGateways) && is_array($paymentGateways)
    ? $paymentGateways
    : [];

$pendingOrder = isset($pendingOrder) && is_array($pendingOrder)
    ? $pendingOrder
    : null;

$couponPreview = isset($couponPreview) && is_array($couponPreview)
    ? $couponPreview
    : null;

$price = (float) ($selectedPlan['price'] ?? 0);

$depositAmount = (float) ($depositAmount ?? 0);
$minDeposit = max(0, (float) ($minDeposit ?? 0));

$discountAmount = (
    $couponPreview &&
    ($couponPreview['valid'] ?? false)
)
    ? (float) ($couponPreview['discount_amount'] ?? 0)
    : 0;

$finalAmount = (
    $couponPreview &&
    ($couponPreview['valid'] ?? false)
)
    ? (float) ($couponPreview['final_amount'] ?? $price)
    : $price;

$currentCoupon = (string) ($couponPreview['coupon_code'] ?? '');

$formatPrice = function ($amount) use ($settings, $formatMoney) {
    if (isset($formatMoney) && is_callable($formatMoney)) {
        return $formatMoney($amount);
    }

    $symbol = $settings['currency_symbol'] ?? 'đ';
    $position = $settings['currency_position'] ?? 'right';
    $decimals = (int) ($settings['currency_decimals'] ?? 0);

    $formatted = number_format(
        (float) $amount,
        $decimals,
        '.',
        ','
    );

    return $position === 'left'
        ? $symbol . $formatted
        : $formatted . ' ' . $symbol;
};

if ($isDeposit) {
    $pageHeading = 'NẠP TIỀN VÀO VÍ';
    $pageDescription = 'Nhập số tiền và chọn cổng thanh toán để nạp tiền.';
    $actionUrl = '/checkout';
    $submitText = 'Tiếp tục nạp tiền';
} elseif ($isRenewal) {
    $pageHeading = 'GIA HẠN GÓI DỊCH VỤ';
    $pageDescription = 'Kiểm tra thông tin và chọn cổng thanh toán để gia hạn.';
    $actionUrl = '/subscription/renew';
    $submitText = 'Tiếp tục gia hạn';
} else {
    $pageHeading = 'THANH TOÁN GÓI DỊCH VỤ';
    $pageDescription = 'Kiểm tra thông tin và chọn cổng thanh toán để hoàn tất đăng ký.';
    $actionUrl = '/checkout';
    $submitText = 'Xác nhận thanh toán';
}

ob_start();
?>

<section class="user-checkout-page">

```
<header class="user-checkout-header">

    <h2 style="color: #020af4; font-family: emoji;">
        <?= htmlspecialchars($pageHeading) ?>
    </h2>

    <p>
        <?= htmlspecialchars($pageDescription) ?>
    </p>

</header>

<?php if (!empty($_SESSION['error'])): ?>

    <div class="user-plans-alert" role="alert">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>

    <?php unset($_SESSION['error']); ?>

<?php endif; ?>


<?php if (
    !$isDeposit &&
    !$isRenewal &&
    $pendingOrder !== null
): ?>

    <div class="user-checkout-pending-notice" role="status">

        <div>

            <span class="user-checkout-pending-flag">
                Lưu ý:
            </span>

            Bạn đang có đơn
            <strong>
                <?= htmlspecialchars(
                    (string) ($pendingOrder['order_code'] ?? '')
                ) ?>
            </strong>

            (
            <?= $formatPrice(
                $pendingOrder['total_amount'] ?? 0
            ) ?>
            )
            chưa thanh toán.

            Vui lòng
            <a href="/payment/checkout?order=<?= (int) ($pendingOrder['id'] ?? 0) ?>">
                hoàn tất
            </a>
            hoặc

            <form method="post" action="/orders/cancel">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf_token ?? '') ?>"
                >

                <input
                    type="hidden"
                    name="order_id"
                    value="<?= (int) ($pendingOrder['id'] ?? 0) ?>"
                >

                <button type="submit">
                    hủy đơn
                </button>

            </form>

            đơn này để tiếp tục.

        </div>

    </div>

<?php endif; ?>


<form
    method="post"
    action="<?= htmlspecialchars($actionUrl) ?>"
    class="user-checkout-shell"
    data-checkout-form
    data-original-amount="<?= htmlspecialchars(
        number_format(
            $isDeposit ? $depositAmount : $price,
            2,
            '.',
            ''
        )
    ) ?>"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($csrf_token ?? '') ?>"
    >

    <?php if ($isDeposit): ?>

        <input
            type="hidden"
            name="checkout_type"
            value="deposit"
        >

    <?php elseif ($isRenewal): ?>

        <input
            type="hidden"
            name="checkout_type"
            value="renewal"
        >

        <input
            type="hidden"
            name="subscription_id"
            value="<?= (int) ($subscription['id'] ?? 0) ?>"
        >

        <input
            type="hidden"
            name="plan_id"
            value="<?= (int) ($selectedPlan['id'] ?? 0) ?>"
        >

    <?php else: ?>

        <input
            type="hidden"
            name="plan_id"
            value="<?= (int) ($selectedPlan['id'] ?? 0) ?>"
        >

        <input
            type="hidden"
            name="checkout_action"
            value="create_order"
            id="checkout-action-field"
        >

    <?php endif; ?>


    <div class="user-checkout-grid">

        <article class="glass-card user-checkout-tab user-checkout-info-tab">

            <div class="user-checkout-tab-head">

                <?php if ($isDeposit): ?>

                    <h4 style="color: #0702f7; text-align: center;">
                        NẠP TIỀN
                    </h4>

                <?php elseif ($isRenewal): ?>

                    <h4 style="color: #0702f7; text-align: center;">
                        XÁC NHẬN GIA HẠN
                    </h4>

                <?php else: ?>

                    <h4 style="color: #0702f7; text-align: center;">
                        XÁC NHẬN ĐĂNG KÝ
                    </h4>

                <?php endif; ?>

                <hr style="border: 1px solid #0602f72f; margin-top: 0.2em; margin-bottom: 1em;">

            </div>


            <?php if ($isDeposit): ?>

                <div class="user-checkout-coupon-wrap">

                    <label for="deposit_amount">
                        Số tiền muốn nạp
                    </label>

                    <input
                        id="deposit_amount"
                        name="amount"
                        type="number"
                        class="glass-input"
                        min="<?= htmlspecialchars((string) $minDeposit) ?>"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $depositAmount > 0
                                ? (string) $depositAmount
                                : ''
                        ) ?>"
                        placeholder="Nhập số tiền"
                        required
                    >

                    <?php if ($minDeposit > 0): ?>

                        <small>
                            Số tiền nạp tối thiểu <?= $formatPrice($minDeposit) ?>.
                        </small>

                    <?php endif; ?>

                </div>


                <div class="user-checkout-total user-checkout-final-total">

                    <span>Số tiền nạp</span>

                    <strong data-checkout-final>
                        <?= $formatPrice($depositAmount) ?>
                    </strong>

                </div>


            <?php elseif ($isRenewal): ?>

                <h5 style="text-align: center;">

                    <span style="color: #888;">
                        Gói:
                    </span>

                    <?= htmlspecialchars(
                        $selectedPlan['name'] ?? 'Gói VPN'
                    ) ?>

                    <?php if (!empty($selectedPlan['code'])): ?>

                        <span style="margin-left: 1em; color: #888;">
                            Mã:
                        </span>

                        <?= htmlspecialchars(
                            (string) $selectedPlan['code']
                        ) ?>

                    <?php endif; ?>

                </h5>


                <dl class="user-plan-features user-checkout-features">

                    <div>
                        <dt>Thời hạn gia hạn</dt>
                        <dd>
                            <?= $duration ?> ngày
                        </dd>
                    </div>

                    <div>
                        <dt>Dung lượng</dt>
                        <dd>
                            <?= $bandwidth > 0
                                ? number_format($bandwidth) . ' GB'
                                : 'Không giới hạn' ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Thiết bị</dt>
                        <dd>
                            <?= $devices ?> thiết bị
                        </dd>
                    </div>

                </dl>


                <div class="user-checkout-total user-checkout-final-total">

                    <span>Chi phí gia hạn</span>

                    <strong data-checkout-final>
                        <?= $formatPrice($finalAmount) ?>
                    </strong>

                </div>


            <?php else: ?>

                <h5 style="text-align: center;">

                    <span style="color: #888;">
                        Tên Gói:
                    </span>

                    <?= htmlspecialchars(
                        $selectedPlan['name'] ?? 'Gói VPN'
                    ) ?>

                    <span style="margin-left: 1em; color: #888;">
                        Mã Code:
                    </span>

                    <?= htmlspecialchars(
                        (string) ($selectedPlan['code'] ?? '')
                    ) ?>

                </h5>


                <dl class="user-plan-features user-checkout-features">

                    <div>
                        <dt>Thời hạn</dt>
                        <dd>
                            <?= $duration ?> ngày
                        </dd>
                    </div>

                    <div>
                        <dt>Dung lượng</dt>
                        <dd>
                            <?= $bandwidth > 0
                                ? number_format($bandwidth) . ' GB'
                                : 'Không giới hạn' ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Thiết bị</dt>
                        <dd>
                            <?= $devices ?> thiết bị
                        </dd>
                    </div>

                </dl>


                <div class="user-checkout-coupon-wrap">

                    <label for="coupon_code">
                        Mã giảm giá nếu có
                    </label>

                    <div class="user-checkout-coupon-row">

                        <input
                            id="coupon_code"
                            name="coupon_code"
                            type="text"
                            class="glass-input"
                            maxlength="50"
                            autocomplete="off"
                            placeholder="Nhập mã giảm giá"
                            value="<?= htmlspecialchars(
                                $currentCoupon
                            ) ?>"
                        >

                        <button
                            type="button"
                            class="user-checkout-coupon-button"
                            data-coupon-apply
                        >
                            Xác nhận mã
                        </button>

                    </div>

                    <div
                        class="user-checkout-coupon-feedback-row"
                        <?= $discountAmount > 0 ? '' : 'hidden' ?>
                        data-coupon-feedback-row
                    >

                        <p
                            class="user-checkout-coupon-feedback is-success"
                            data-coupon-feedback
                            role="status"
                            aria-live="polite"
                        >
                            <?= $discountAmount > 0
                                ? 'Mã giảm giá đã được áp dụng.'
                                : '' ?>
                        </p>

                        <button
                            type="button"
                            class="user-checkout-coupon-remove"
                            data-coupon-remove
                            title="Bỏ mã giảm giá"
                            aria-label="Bỏ mã giảm giá"
                        >
                            &times;
                        </button>

                    </div>

                </div>


                <div class="user-checkout-total">

                    <span>Tổng gốc</span>

                    <strong>
                        <?= $formatPrice($price) ?>
                    </strong>

                </div>


                <div class="user-checkout-total user-checkout-discount-total">

                    <span>Giảm giá</span>

                    <strong data-checkout-discount>
                        -<?= $formatPrice($discountAmount) ?>
                    </strong>

                </div>


                <div class="user-checkout-total user-checkout-final-total">

                    <span>Cần thanh toán</span>

                    <strong data-checkout-final>
                        <?= $formatPrice($finalAmount) ?>
                    </strong>

                </div>

            <?php endif; ?>

        </article>


        <article class="glass-card user-checkout-tab user-checkout-pay-tab">

            <div class="user-checkout-tab-head">

                <h4
                    style="
                        color: #0702f7;
                        text-align: center;
                        font-family: serif;
                    "
                >
                    CỔNG THANH TOÁN HIỆN HÀNH
                </h4>

                <hr style="border: 1px solid #0602f730; margin-top: 0.2em; margin-bottom: 1em;">

                <h5 style="text-align: center; color: #6f5c7a;">
                    Chọn cổng thanh toán
                </h5>

            </div>


            <div
                class="user-checkout-gateway-list"
                role="radiogroup"
                aria-label="Cổng thanh toán"
            >

                <?php if (!empty($paymentGateways)): ?>

                    <?php foreach (
                        $paymentGateways as $index => $gateway
                    ): ?>

                        <label class="user-checkout-gateway-item">

                            <input
                                type="radio"
                                name="payment_gateway"
                                value="<?= htmlspecialchars(
                                    (string) $gateway['id']
                                ) ?>"
                                <?= $index === 0 ? 'checked' : '' ?>
                            >

                            <span>

                                <strong>
                                    <?= htmlspecialchars(
                                        (string) (
                                            $gateway['name']
                                            ?? 'Cổng thanh toán'
                                        )
                                    ) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars(
                                        (string) (
                                            $gateway['hint'] ?? ''
                                        )
                                    ) ?>
                                </small>

                            </span>

                        </label>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="user-checkout-no-gateway">
                        Chưa có cổng thanh toán nào được bật trong phần
                        cài đặt.
                    </p>

                <?php endif; ?>

            </div>


            <p class="user-checkout-note">

                <?php if ($isDeposit): ?>

                    Sau khi xác nhận, giao dịch nạp tiền sẽ được tạo và
                    chuyển đến trang thanh toán của cổng bạn chọn.

                <?php elseif ($isRenewal): ?>

                    Sau khi xác nhận, giao dịch gia hạn sẽ được tạo và
                    chuyển đến trang thanh toán của cổng bạn chọn.

                <?php else: ?>

                    Sau khi xác nhận, đơn đăng ký sẽ được tạo và hệ thống
                    xử lý theo cổng thanh toán bạn chọn.

                <?php endif; ?>

            </p>


            <button
                type="submit"
                class="user-checkout-submit"
                <?= empty($paymentGateways) ||
                    (
                        !$isDeposit &&
                        !$isRenewal &&
                        $pendingOrder !== null
                    )
                        ? 'disabled'
                        : '' ?>
            >
                <?= htmlspecialchars($submitText) ?>
            </button>

        </article>

    </div>

</form>
```

</section>

<?php
$content = ob_get_clean();

$showSidebar = true;

require_once __DIR__ . '/../../layouts/app.php';
?>
