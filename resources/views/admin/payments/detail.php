<?php
$pageTitle = "Chi Tiết Thanh Toán - Quản Trị Hệ Thống";
$activeMenu = "payments";

ob_start();
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Giao Dịch Thanh Toán: #<?= $payment['id'] ?></h1>
    </div>
    <a href="/admin/payments" class="glass-btn" style="text-decoration: none; white-space: nowrap; flex-shrink: 0;">⬅️ Quay Lại</a>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
    <!-- Thẻ Thông Tin Giao Dịch & Khách Hàng -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Khách Hàng</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>ID Giao Dịch:</strong> #<?= $payment['id'] ?></div>
            <div><strong>Khách Hàng:</strong> <?= htmlspecialchars($payment['username'] ?? 'N/A') ?> (ID #<?= $payment['user_id'] ?>)</div>
            <div><strong>Email:</strong> <?= htmlspecialchars($payment['email'] ?? 'N/A') ?></div>
            <div>
                <strong>Liên Kết Đơn Hàng:</strong> 
                <?php if (!empty($payment['order_code'])): ?>
                    <a href="/admin/orders/detail?id=<?= $payment['order_id'] ?>" style="color: var(--ios-blue); text-decoration: none; font-weight: 700;">
                        #<?= htmlspecialchars($payment['order_code']) ?>
                    </a>
                <?php else: ?>
                    <span style="color: var(--ios-text-secondary);">Không có (Giao dịch nạp tiền)</span>
                <?php endif; ?>
            </div>
            <div><strong>Thời Gian Khởi Tạo:</strong> <?= date('d/m/Y H:i:s', strtotime($payment['created_at'])) ?></div>
        </div>
    </div>

    <!-- Thẻ Chi Tiết Thanh Toán -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Chi Tiết Thanh Toán</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div>
                <strong>Loại Giao Dịch:</strong> 
                <?php if (($payment['type'] ?? 'payment') === 'deposit'): ?>
                    <span style="background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700;">
                        NẠP TIỀN TÀI KHOẢN
                    </span>
                <?php else: ?>
                    <span style="background: rgba(88, 86, 214, 0.15); color: #5856D6; padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700;">
                        THANH TOÁN ĐƠN HÀNG
                    </span>
                <?php endif; ?>
            </div>
            <div><strong>Phương Thức:</strong> <span style="font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($payment['payment_method']) ?></span></div>
            <div>
                <strong>Mã Giao Dịch Cổng (Txn ID):</strong> 
                <code style="background: rgba(0, 0, 0, 0.1); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-family: monospace;">
                    <?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?>
                </code>
            </div>
            <div>
                <strong>Số Tiền Giao Dịch:</strong> 
                <span style="color: var(--ios-success); font-weight: 700; font-size: 1.1rem;">
                    <?= isset($formatMoney) ? $formatMoney($payment['amount']) : number_format($payment['amount'], 2) ?>
                </span>
            </div>
            <div>
                <strong>Trạng Thái Giao Dịch:</strong> 
                <?php
                $statusBadge = [
                    'success' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                    'pending' => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                    'failed'  => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                ];
                ?>
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$payment['status'] ?? 'pending'] ?? '' ?>">
                    <?= strtoupper($payment['status'] ?? 'pending') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>