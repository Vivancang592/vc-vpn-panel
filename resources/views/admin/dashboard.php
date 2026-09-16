<?php
$pageTitle = "Dashboard - Quản Trị Hệ Thống";
$activeMenu = "dashboard";

$currencyCode = $settings['currency'] ?? 'CNY';

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-blue); display: flex; align-items: center; justify-content: space-between;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div style="display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 0.5rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Tổng Quan Hệ Thống</h1>
    <p style="color: var(--ios-text-secondary); font-size: 0.875rem;">Theo dõi chỉ số hoạt động real-time của VPN Service</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="glass-card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="title">Tổng Người Dùng</span>
            <span style="font-size: 1.25rem;">👥</span>
        </div>
        <div class="value"><?= number_format($stats['total_users'] ?? 0) ?></div>
        <div style="font-size: 0.8rem; color: var(--ios-success); font-weight: 600;">+<?= number_format($stats['new_users_today'] ?? 0) ?> hôm nay</div>
    </div>

    <div class="glass-card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="title">Gói Dịch Vụ Active</span>
            <span style="font-size: 1.25rem;">🔑</span>
        </div>
        <div class="value" style="color: var(--ios-success);"><?= number_format($stats['active_subscriptions'] ?? 0) ?></div>
        <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">Đang hoạt động</div>
    </div>

    <div class="glass-card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="title">Doanh Thu Tháng <?= htmlspecialchars($selectedMonth ?? date('n')) ?></span>
            <span style="font-size: 1.25rem;">💴</span>
        </div>
        <div class="value" style="color: var(--ios-success);"><?= isset($formatMoney) ? $formatMoney($stats['monthly_revenue'] ?? 0) : number_format($stats['monthly_revenue'] ?? 0, 2) ?></div>
        <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">Tổng đơn đã thanh toán</div>
    </div>

    <div class="glass-card stat-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="title">Cổng Inbound Active</span>
            <span style="font-size: 1.25rem;">🌐</span>
        </div>
        <div class="value" style="color: #5856D6;"><?= ($stats['active_inbounds'] ?? 0) ?> / <?= ($stats['total_inbounds'] ?? 0) ?></div>
        <div style="font-size: 0.8rem; color: var(--ios-text-secondary);">Giao thức khả dụng</div>
    </div>
</div>

<!-- Layout 2 Cột Responsive -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start; margin-bottom: 1.5rem;">
    <!-- Đơn Hàng Mới -->
    <div class="glass-card" style="padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700;">Đơn Hàng Gần Đây</h2>
            <a href="/admin/orders" style="font-size: 0.85rem; color: var(--ios-blue); text-decoration: none; font-weight: 600;">Xem tất cả &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Mã Đơn</th>
                        <th>Khách Hàng</th>
                        <th>Số Tiền</th>
                        <th>Trạng Thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentOrders)): ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= htmlspecialchars($order['order_code']) ?></td>
                                <td><?= htmlspecialchars($order['username'] ?? 'N/A') ?></td>
                                <td style="font-weight: 600; color: var(--ios-success);"><?= isset($formatMoney) ? $formatMoney($order['total_amount']) : number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusStyle = [
                                        'completed' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                        'pending' => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                        'failed' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);',
                                        'cancelled' => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);'
                                    ];
                                    ?>
                                    <span style="padding: 0.25rem 0.6rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusStyle[$order['payment_status']] ?? '' ?>">
                                        <?= strtoupper($order['payment_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--ios-text-secondary);">Chưa có đơn hàng mới</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Trạng Thái Máy Chủ -->
    <div class="glass-card" style="padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700;">Hạ Tầng VPN</h2>
            <a href="/admin/servers" style="font-size: 0.85rem; color: var(--ios-blue); text-decoration: none; font-weight: 600;">Quản lý</a>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php if (!empty($servers)): ?>
                <?php foreach ($servers as $server): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: rgba(255, 255, 255, 0.2); border: 1px solid var(--glass-border); border-radius: var(--radius-md);">
                        <div>
                            <div style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($server['name']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($server['ip_address']) ?> (<?= htmlspecialchars($server['location']) ?>)</div>
                        </div>
                        <span style="height: 10px; width: 10px; border-radius: 50%; background-color: <?= $server['status'] === 'active' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; box-shadow: 0 0 8px <?= $server['status'] === 'active' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>;"></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--ios-text-secondary); padding: 1rem;">Chưa có máy chủ nào</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Biểu Đồ So Sánh Doanh Thu Cùng Form Chọn Tháng / Năm (Ở Dưới Cùng) -->
<div class="glass-card" style="padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h2 style="font-size: 1.1rem; font-weight: 700;">So Sánh Doanh Thu (<?= htmlspecialchars($currencyCode) ?>)</h2>
            <p style="font-size: 0.8rem; color: var(--ios-text-secondary);">Đối soát tăng trưởng doanh thu theo từng ngày giữa tháng đã chọn và tháng liền trước</p>
        </div>

        <!-- Dropdown Bộ Lọc Thời Gian -->
        <form method="GET" action="/admin" style="display: flex; gap: 0.5rem; align-items: center;">
            <select name="month" onchange="this.form.submit()" style="padding: 0.45rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.4); color: var(--ios-text); font-weight: 600; font-size: 0.85rem; outline: none; cursor: pointer;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($m == ($selectedMonth ?? date('n'))) ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                <?php endfor; ?>
            </select>

            <select name="year" onchange="this.form.submit()" style="padding: 0.45rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.4); color: var(--ios-text); font-weight: 600; font-size: 0.85rem; outline: none; cursor: pointer;">
                <?php
                $currentY = (int)date('Y');
                for ($y = $currentY; $y >= $currentY - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= ($y == ($selectedYear ?? $currentY)) ? 'selected' : '' ?>>Năm <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div style="position: relative; height: 300px; width: 100%;">
        <canvas id="revenueComparisonChart" 
                data-current="<?= htmlspecialchars(json_encode($monthlyChart['current_month'] ?? array_fill(0, 31, 0))) ?>" 
                data-last="<?= htmlspecialchars(json_encode($monthlyChart['last_month'] ?? array_fill(0, 31, 0))) ?>" 
                data-month="<?= (int)($selectedMonth ?? date('n')) ?>" 
                data-year="<?= (int)($selectedYear ?? date('Y')) ?>"
                data-symbol="<?= htmlspecialchars($settings['currency_symbol'] ?? '¥') ?>"
                data-position="<?= htmlspecialchars($settings['currency_position'] ?? 'right') ?>"
                data-decimals="<?= (int)($settings['currency_decimals'] ?? 2) ?>">
        </canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/admin.js"></script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>