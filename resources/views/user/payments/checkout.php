<?php

$checkoutType = (string) ($checkoutType ?? 'order');

$isDeposit = $checkoutType === 'deposit';
$isRenewal = $checkoutType === 'renewal';
$isOrder = !$isDeposit && !$isRenewal;

$pageTitlePrefix = match ($checkoutType) {
    'deposit' => 'Nạp Tiền - ',
    'renewal' => 'Gia Hạn Gói - ',
    default => 'Thanh Toán Đơn Hàng - ',
};

$pageTitle = $pageTitlePrefix
    . ($settings['site_title'] ?? 'VC VPN 2027');

$paymentInfo = isset($paymentInstructions) && is_array($paymentInstructions)
    ? $paymentInstructions
    : [];

$qrUrl = trim((string) ($paymentInfo['qr_url'] ?? ''));

$order = isset($order) && is_array($order) ? $order : [];
$payment = isset($payment) && is_array($payment) ? $payment : [];
$subscription = isset($subscription) && is_array($subscription)
    ? $subscription
    : [];

$orderId = (int) ($order['id'] ?? 0);

$orderCode = (string) (
    $order['order_code']
    ?? $order['code']
    ?? ''
);

$paymentId = (int) ($payment['id'] ?? 0);

$transactionCode = (string) (
    $payment['transaction_id']
    ?? $payment['transaction_code']
    ?? $paymentInfo['transaction_code']
    ?? ''
);

/*
 * Renewal có thể được triển khai theo order hoặc payment.
 * Ưu tiên payment nếu backend truyền payment ID.
 */
$renewalId = (int) (
    $subscription['id']
    ?? $order['subscription_id']
    ?? $payment['subscription_id']
    ?? 0
);

$amount = (float) (
    $paymentInfo['amount']
    ?? $payment['amount']
    ?? $order['total_amount']
    ?? $order['amount']
    ?? 0
);

$amountDisplay = (string) (
    $paymentInfo['amount_display']
    ?? number_format($amount, 2, '.', ',')
);

$paymentName = (string) (
    $paymentInfo['name']
    ?? $paymentInfo['payment_name']
    ?? ''
);

$transferContent = (string) (
    $paymentInfo['transfer_content']
    ?? $paymentInfo['content']
    ?? $transactionCode
    ?? ''
);

$planName = (string) (
    $order['plan_name']
    ?? $subscription['plan_name']
    ?? $payment['plan_name']
    ?? 'Gói VPN'
);

$subscriptionCode = (string) (
    $subscription['subscription_code']
    ?? $subscription['code']
    ?? $subscription['uuid']
    ?? ''
);

ob_start();

?>

<section class="user-payment-checkout-page">

```
<header class="user-payment-checkout-header">

    <?php if ($isDeposit): ?>

        <p class="user-plans-kicker">
            NẠP TIỀN VÀO VÍ
        </p>

        <h1>
            Quét mã để nạp tiền
        </h1>

        <p>
            Giao dịch
            <strong>
                <?= htmlspecialchars($transactionCode) ?>
            </strong>
            đang chờ thanh toán.
        </p>

    <?php elseif ($isRenewal): ?>

        <p class="user-plans-kicker">
            GIA HẠN GÓI DỊCH VỤ
        </p>

        <h1>
            Quét mã để gia hạn
        </h1>

        <p>
            Vui lòng thanh toán đúng số tiền và nội dung chuyển khoản
            để hệ thống tự động xác nhận gia hạn.
        </p>

    <?php else: ?>

        <p class="user-plans-kicker">
            THANH TOÁN ĐƠN HÀNG
        </p>

        <h1>
            Quét mã để thanh toán
        </h1>

        <p>
            Đơn hàng
            <strong>
                <?= htmlspecialchars($orderCode) ?>
            </strong>
            đang chờ thanh toán.
        </p>

    <?php endif; ?>

</header>


<div
    id="payment-status-banner"
    class="user-payment-status-banner"
    hidden
>
    Đang kiểm tra thanh toán...
</div>


<div class="user-payment-checkout-grid">

    <!-- QR -->
    <article class="glass-card user-payment-qr-card">

        <h3 style="text-align: center; color: #0046f6;">

            <?php if ($isDeposit): ?>

                CỔNG NẠP TIỀN

            <?php elseif ($isRenewal): ?>

                CỔNG GIA HẠN

            <?php else: ?>

                CỔNG THANH TOÁN

            <?php endif; ?>

            <?php if ($paymentName !== ''): ?>

                <?= htmlspecialchars($paymentName) ?>

            <?php endif; ?>

        </h3>


        <p
            class="user-payment-qr-instruction"
            style="color: #00e6f6; text-align: center;"
        >
            Thanh toán đúng số tiền và nội dung để hệ thống tự động
            xác nhận giao dịch.
        </p>


        <?php if ($qrUrl !== ''): ?>

            <img
                src="<?= htmlspecialchars($qrUrl) ?>"
                alt="Mã QR thanh toán <?= htmlspecialchars($paymentName) ?>"
                class="user-payment-qr-image"
            >

        <?php else: ?>

            <div class="user-payment-qr-missing">

                Mã QR chưa được cấu hình.

            </div>

        <?php endif; ?>


        <p>
            Quét mã QR bằng ứng dụng thanh toán của bạn.
        </p>

    </article>


    <!-- PAYMENT INFO -->
    <article class="glass-card user-payment-order-card">

        <h2>
            Thông tin thanh toán
        </h2>


        <dl class="user-order-detail-list">


            <?php if ($isDeposit): ?>

                <div>

                    <dt>
                        Loại giao dịch
                    </dt>

                    <dd>
                        Nạp tiền vào ví
                    </dd>

                </div>


                <?php if ($transactionCode !== ''): ?>

                    <div>

                        <dt>
                            Mã giao dịch
                        </dt>

                        <dd>
                            <?= htmlspecialchars($transactionCode) ?>
                        </dd>

                    </div>

                <?php endif; ?>


            <?php elseif ($isRenewal): ?>

                <div>

                    <dt>
                        Loại giao dịch
                    </dt>

                    <dd>
                        Gia hạn gói dịch vụ
                    </dd>

                </div>


                <div>

                    <dt>
                        Gói dịch vụ
                    </dt>

                    <dd>
                        <?= htmlspecialchars($planName) ?>
                    </dd>

                </div>


                <?php if ($subscriptionCode !== ''): ?>

                    <div>

                        <dt>
                            Mã gói
                        </dt>

                        <dd>
                            <?= htmlspecialchars($subscriptionCode) ?>
                        </dd>

                    </div>

                <?php endif; ?>


                <?php if ($orderCode !== ''): ?>

                    <div>

                        <dt>
                            Mã đơn hàng
                        </dt>

                        <dd>
                            <?= htmlspecialchars($orderCode) ?>
                        </dd>

                    </div>

                <?php endif; ?>


                <?php if ($transactionCode !== ''): ?>

                    <div>

                        <dt>
                            Mã giao dịch
                        </dt>

                        <dd>
                            <?= htmlspecialchars($transactionCode) ?>
                        </dd>

                    </div>

                <?php endif; ?>


            <?php else: ?>

                <div>

                    <dt>
                        Gói dịch vụ
                    </dt>

                    <dd>
                        <?= htmlspecialchars($planName) ?>
                    </dd>

                </div>


                <div>

                    <dt>
                        Mã đơn hàng
                    </dt>

                    <dd>
                        <?= htmlspecialchars($orderCode) ?>
                    </dd>

                </div>

            <?php endif; ?>


            <?php if ($paymentName !== ''): ?>

                <div>

                    <dt>
                        Cổng thanh toán
                    </dt>

                    <dd>
                        <?= htmlspecialchars($paymentName) ?>
                    </dd>

                </div>

            <?php endif; ?>


            <?php if (!empty($paymentInfo['bank_name'])): ?>

                <div>

                    <dt>
                        Ngân hàng
                    </dt>

                    <dd>
                        <?= htmlspecialchars(
                            (string) $paymentInfo['bank_name']
                        ) ?>
                    </dd>

                </div>

            <?php endif; ?>


            <?php if (!empty($paymentInfo['account_number'])): ?>

                <div>

                    <dt>
                        Số tài khoản
                    </dt>

                    <dd>
                        <?= htmlspecialchars(
                            (string) $paymentInfo['account_number']
                        ) ?>
                    </dd>

                </div>

            <?php endif; ?>


            <?php if (!empty($paymentInfo['account_name'])): ?>

                <div>

                    <dt>
                        Chủ tài khoản
                    </dt>

                    <dd>
                        <?= htmlspecialchars(
                            (string) $paymentInfo['account_name']
                        ) ?>
                    </dd>

                </div>

            <?php endif; ?>


            <?php if ($transferContent !== ''): ?>

                <div class="user-payment-transfer-row">

                    <dt>
                        Nội dung chuyển khoản
                    </dt>

                    <dd class="user-payment-transfer-content">

                        <span>
                            <?= htmlspecialchars($transferContent) ?>
                        </span>


                        <button
                            type="button"
                            class="subscription-copy-button"
                            data-copy-value="<?= htmlspecialchars(
                                $transferContent
                            ) ?>"
                        >
                            Sao chép
                        </button>

                    </dd>

                </div>

            <?php endif; ?>


            <div>

                <dt>
                    Số tiền
                </dt>

                <dd class="user-order-amount">

                    <?= htmlspecialchars($amountDisplay) ?>

                </dd>

            </div>


        </dl>


        <?php if ($isDeposit): ?>

            <a
                href="/payments"
                class="user-payment-order-link"
            >
                Xem lịch sử giao dịch
            </a>


        <?php elseif ($isRenewal): ?>

            <?php if ($renewalId > 0): ?>

                <a
                    href="/subscriptions/detail?id=<?= $renewalId ?>"
                    class="user-payment-order-link"
                >
                    Xem gói dịch vụ
                </a>

            <?php elseif ($orderId > 0): ?>

                <a
                    href="/orders/detail?id=<?= $orderId ?>"
                    class="user-payment-order-link"
                >
                    Xem đơn gia hạn
                </a>

            <?php else: ?>

                <a
                    href="/subscriptions"
                    class="user-payment-order-link"
                >
                    Xem gói dịch vụ
                </a>

            <?php endif; ?>


        <?php else: ?>

            <?php if ($orderId > 0): ?>

                <a
                    href="/orders/detail?id=<?= $orderId ?>"
                    class="user-payment-order-link"
                >
                    Xem trạng thái đơn hàng
                </a>

            <?php endif; ?>

        <?php endif; ?>


    </article>

</div>
```

</section>

<?php

$content = ob_get_clean();

$showSidebar = true;

require_once __DIR__ . '/../../layouts/app.php';

?>

<script>

(function () {

    var checkoutType =
        <?= json_encode($checkoutType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;


    var orderId =
        <?= $orderId ?>;


    var paymentId =
        <?= $paymentId ?>;


    var renewalId =
        <?= $renewalId ?>;


    var banner =
        document.getElementById('payment-status-banner');


    if (!banner) {
        return;
    }


    /*
     * Hiển thị trạng thái.
     */
    function showBanner(message, success) {

        banner.textContent = message;

        banner.hidden = false;

        if (success) {

            banner.classList.add('is-success');

        } else {

            banner.classList.remove('is-success');

        }

    }


    /*
     * Mua gói:
     * kiểm tra trạng thái order.
     */
    function watchOrder() {

        if (orderId <= 0) {
            return;
        }


        var orderTimer = setInterval(function () {

            fetch(
                '/orders/status?id=' + encodeURIComponent(orderId),
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            )

            .then(function (res) {

                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }

                return res.json();

            })

            .then(function (data) {

                var status =
                    String(data.status || '').toLowerCase();


                if (
                    status === 'completed' ||
                    status === 'success' ||
                    status === 'paid'
                ) {

                    clearInterval(orderTimer);


                    showBanner(
                        'Thanh toán thành công! Đang chuyển đến đơn hàng...',
                        true
                    );


                    setTimeout(function () {

                        window.location.href =
                            '/orders/detail?id=' + encodeURIComponent(orderId);

                    }, 1200);


                } else if (
                    status === 'cancelled' ||
                    status === 'failed'
                ) {

                    clearInterval(orderTimer);


                    showBanner(
                        'Giao dịch đã thất bại hoặc bị hủy.',
                        false
                    );

                }

            })

            .catch(function () {});


        }, 5000);

    }


    /*
     * Nạp tiền / Gia hạn:
     * kiểm tra trạng thái payment.
     */
    function watchPayment() {

        if (paymentId <= 0) {
            return;
        }


        var paymentTimer = setInterval(function () {

            fetch(
                '/payments/status?id=' + encodeURIComponent(paymentId),
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            )

            .then(function (res) {

                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }

                return res.json();

            })

            .then(function (data) {

                var status =
                    String(data.status || '').toLowerCase();


                if (
                    status === 'success' ||
                    status === 'completed' ||
                    status === 'paid'
                ) {

                    clearInterval(paymentTimer);


                    if (checkoutType === 'deposit') {

                        showBanner(
                            'Nạp tiền thành công! Số dư ví đã được cập nhật.',
                            true
                        );


                        setTimeout(function () {

                            window.location.href =
                                '/wallet';

                        }, 1200);


                    } else if (checkoutType === 'renewal') {

                        showBanner(
                            'Gia hạn thành công! Đang cập nhật gói dịch vụ...',
                            true
                        );


                        setTimeout(function () {

                            if (renewalId > 0) {

                                window.location.href =
                                    '/subscriptions/detail?id=' +
                                    encodeURIComponent(renewalId);

                            } else if (orderId > 0) {

                                window.location.href =
                                    '/orders/detail?id=' +
                                    encodeURIComponent(orderId);

                            } else {

                                window.location.href =
                                    '/subscriptions';

                            }

                        }, 1200);

                    } else {

                        showBanner(
                            'Thanh toán thành công!',
                            true
                        );

                    }


                } else if (
                    status === 'failed' ||
                    status === 'cancelled'
                ) {

                    clearInterval(paymentTimer);


                    if (checkoutType === 'deposit') {

                        showBanner(
                            'Giao dịch nạp tiền đã thất bại hoặc bị hủy.',
                            false
                        );

                    } else if (checkoutType === 'renewal') {

                        showBanner(
                            'Giao dịch gia hạn đã thất bại hoặc bị hủy.',
                            false
                        );

                    } else {

                        showBanner(
                            'Giao dịch đã thất bại hoặc bị hủy.',
                            false
                        );

                    }

                }

            })

            .catch(function () {});


        }, 5000);

    }


    /*
     * Chọn cơ chế kiểm tra theo loại checkout.
     */
    if (checkoutType === 'deposit') {

        watchPayment();

        return;
    }


    if (checkoutType === 'renewal') {

        /*
         * Renewal nếu có payment ID thì theo dõi payment.
         * Nếu backend tạo renewal dưới dạng order thì theo dõi order.
         */
        if (paymentId > 0) {

            watchPayment();

        } else if (orderId > 0) {

            watchOrder();

        }

        return;
    }


    /*
     * Mặc định: mua gói.
     */
    if (checkoutType === 'order') {

        watchOrder();

    }

})();

</script>
