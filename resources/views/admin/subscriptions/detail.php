<?php
$pageTitle = "Chi Tiết Gói Đăng Ký - Quản Trị Hệ Thống";
$activeMenu = "subscriptions";

ob_start();

// Hàm hỗ trợ quy đổi dung lượng linh hoạt MB / GB
$formatTraffic = function($bytes) {
    if ($bytes <= 0) return '0 MB';
    $gb = 1024 * 1024 * 1024;
    $mb = 1024 * 1024;
    if ($bytes < $gb) {
        return round($bytes / $mb, 2) . ' MB';
    }
    return round($bytes / $gb, 2) . ' GB';
};

$uploadBytes = (float)($subscription['upload'] ?? 0);
$downloadBytes = (float)($subscription['download'] ?? 0);
$totalUsedBytes = $uploadBytes + $downloadBytes;
$limitBytes = (float)($subscription['transfer_enable'] ?? 0);

$uploadFormatted = $formatTraffic($uploadBytes);
$downloadFormatted = $formatTraffic($downloadBytes);
$totalUsedFormatted = $formatTraffic($totalUsedBytes);
$limitFormatted = $limitBytes > 0 ? $formatTraffic($limitBytes) : 'Không giới hạn';
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Gói Đăng Ký: #<?= $subscription['id'] ?></h1>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
    <!-- Thẻ Thông Tin Chủ Sở Hữu & Gói Dịch Vụ -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Tài Khoản</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>Khách Hàng:</strong> <?= htmlspecialchars($subscription['username'] ?? 'N/A') ?> (ID #<?= $subscription['user_id'] ?>)</div>
            <div><strong>Email:</strong> <?= htmlspecialchars($subscription['email'] ?? 'N/A') ?></div>
            <div><strong>Gói Cước:</strong> <span style="font-weight: 700;"><?= htmlspecialchars($subscription['plan_name'] ?? 'N/A') ?></span></div>
            <div>
                <strong>Mã Đơn Hàng:</strong> 
                <?php if (!empty($subscription['order_code'])): ?>
                    <a href="/admin/orders/detail?id=<?= $subscription['order_id'] ?>" style="color: var(--ios-blue); text-decoration: none; font-weight: 700;">
                        <?= htmlspecialchars($subscription['order_code']) ?>
                    </a>
                <?php else: ?>
                    <span style="color: var(--ios-text-secondary);">Cấp trực tiếp bởi Admin</span>
                <?php endif; ?>
            </div>
            <div><strong>Ngày Bắt Đầu:</strong> <?= date('d/m/Y H:i:s', strtotime($subscription['start_date'])) ?></div>
            <div><strong>Ngày Hết Hạn:</strong> <?= date('d/m/Y H:i:s', strtotime($subscription['end_date'])) ?></div>
            <div>
                <strong>Trạng Thái:</strong> 
                <?php
                $statusBadge = [
                    'active'    => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                    'expired'   => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                    'suspended' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);',
                    'cancelled' => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);'
                ];
                ?>
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$subscription['status'] ?? 'active'] ?? '' ?>">
                    <?= strtoupper($subscription['status'] ?? 'active') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Thẻ Cấu Hình VPN & Lưu Lượng -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Cấu Hình & Lưu Lượng VPN</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div>
                <strong>Mã Khách Hàng:</strong>
                <p style="margin: 0.25rem 0 0 0; background: rgba(0,0,0,0.2); padding: 0.4rem 0.6rem; border-radius: var(--radius-sm); font-family: monospace; word-break: break-all; color: var(--ios-blue); font-weight: 600;">
                    <?= htmlspecialchars($subscription['uuid']) ?>
                </p>
            </div>
            <div><strong>Upload:</strong> <span style="font-weight: 600;"><?= $uploadFormatted ?></span></div>
            <div><strong>Download:</strong> <span style="font-weight: 600;"><?= $downloadFormatted ?></span></div>
            <div>
                <strong>Tổng Lưu Lượng:</strong> 
                <span style="color: var(--ios-blue); font-weight: 700; font-size: 1.05rem;"><?= $totalUsedFormatted ?></span> 
                / <?= $limitFormatted ?>
            </div>
            <div>
                <strong>IP Kết Nối Cuối:</strong> 
                <code style="background: rgba(0, 0, 0, 0.1); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm);">
                    <?= htmlspecialchars($subscription['last_used_ip'] ?? 'Chưa kết nối') ?>
                </code>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>