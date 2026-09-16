<?php
$pageTitle = "Chi Tiết Đơn Hàng - Quản Trị Hệ Thống";
$activeMenu = "orders";

ob_start();
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Chi Tiết Đơn Hàng: #<?= $order['id'] ?></h1>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
    <!-- Thẻ Thông Tin Đơn Hàng & Khách Hàng -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Đơn Hàng</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div>
                <strong>Mã Đơn Hàng:</strong> 
                <code style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700;">
                    <?= htmlspecialchars($order['order_code']) ?>
                </code>
            </div>
            <div><strong>Tài Khoản Mua:</strong> <?= htmlspecialchars($order['username'] ?? 'N/A') ?> (ID #<?= $order['user_id'] ?>)</div>
            <div><strong>Email:</strong> <?= htmlspecialchars($order['email'] ?? 'N/A') ?></div>
            <div>
                <strong>IP Đặt Hàng:</strong> 
                <code style="background: rgba(0, 0, 0, 0.1); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm);">
                    <?= htmlspecialchars($order['purchase_ip'] ?? 'N/A') ?>
                </code>
            </div>
            <div><strong>Thời Gian Tạo:</strong> <?= date('d/m/Y H:i:s', strtotime($order['created_at'])) ?></div>
            <div><strong>Cập Nhật Cuối:</strong> <?= !empty($order['updated_at']) ? date('d/m/Y H:i:s', strtotime($order['updated_at'])) : 'N/A' ?></div>
        </div>
    </div>

    <!-- Thẻ Chi Tiết Dịch Vụ & Thanh Toán -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Dịch Vụ & Thanh Toán</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>Gói Cước Đăng Ký:</strong> <span style="font-weight: 700;"><?= htmlspecialchars($order['plan_name'] ?? 'Chưa xác định') ?></span></div>
            <div><strong>Mã Gói Cước:</strong> <code style="background: rgba(0,122,255,0.1); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700;"><?= htmlspecialchars($order['plan_code'] ?? 'N/A') ?></code></div>
            <div>
                <strong>Mã Giảm Giá Áp Dụng:</strong> 
                <?php if (!empty($order['coupon_code'])): ?>
                    <span style="background: rgba(255, 149, 0, 0.15); color: var(--ios-warning); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700;">
                        <?= htmlspecialchars($order['coupon_code']) ?>
                    </span>
                <?php else: ?>
                    <span style="color: var(--ios-text-secondary);">Không sử dụng</span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Tổng Tiền Thanh Toán:</strong> 
                <span style="color: var(--ios-success); font-weight: 700; font-size: 1.1rem;">
                    <?= isset($formatMoney) ? $formatMoney($order['total_amount']) : number_format($order['total_amount'], 2) ?>
                </span>
            </div>
            <div>
                <strong>Trạng Thái Thanh Toán:</strong> 
                <?php
                $statusBadge = [
                    'completed' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                    'pending'   => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                    'failed'    => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);',
                    'cancelled' => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);'
                ];
                ?>
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$order['payment_status'] ?? 'pending'] ?? '' ?>">
                    <?= strtoupper($order['payment_status'] ?? 'pending') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>