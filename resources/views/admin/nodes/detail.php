<?php
$pageTitle = "Chi Tiết Nút Kết Nối - Quản Trị Hệ Thống";
$activeMenu = "nodes";

ob_start();
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Nút Kết Nối: #<?= $node['id'] ?></h1>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
    <!-- Thẻ Máy Chủ VPS -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Máy Chủ VPS</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>Máy Chủ:</strong> <?= htmlspecialchars($node['server_name'] ?? ('Server #' . $node['server_id'])) ?></div>
            <div>
                <strong>Địa Chỉ IP:</strong> 
                <code style="background: rgba(0, 122, 255, 0.08); color: var(--ios-text); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700;">
                    <?= htmlspecialchars($node['server_ip'] ?? 'N/A') ?>
                </code>
            </div>
            <div><strong>Vị Trí:</strong> <?= htmlspecialchars($node['server_location'] ?? 'N/A') ?></div>
        </div>
    </div>

    <!-- Thẻ Cấu Hình Giao Thức (Đầy đủ thuộc tính) -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Cấu Hình Giao Thức (Inbound)</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div>
                <strong>Inbound Tag:</strong> 
                <span style="font-weight: 700; color: var(--ios-blue);"><?= htmlspecialchars($node['tag'] ?: '-') ?></span>
            </div>
            <div>
                <strong>Cổng (Port):</strong> 
                <code style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700;">
                    <?= htmlspecialchars($node['port']) ?>
                </code>
            </div>
            <div>
                <strong>Giao Thức:</strong> 
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); text-transform: uppercase;">
                    <?= htmlspecialchars($node['protocol']) ?>
                </span>
            </div>
            <div><strong>Mạng (Network):</strong> <span style="font-weight: 600; text-transform: uppercase;"><?= htmlspecialchars($node['network']) ?></span></div>
            <div>
                <strong>Mã Hóa TLS:</strong> 
                <?= !empty($node['tls']) ? '<span style="color: var(--ios-success); font-weight: 700;">✓ Đã bật</span>' : '<span style="color: var(--ios-text-secondary);">✕ Đã tắt</span>' ?>
            </div>
            <div><strong>SNI:</strong> <code style="background: rgba(0,0,0,0.15); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm);"><?= htmlspecialchars($node['sni'] ?: '-') ?></code></div>
            <div><strong>Host:</strong> <code style="background: rgba(0,0,0,0.15); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm);"><?= htmlspecialchars($node['host'] ?: '-') ?></code></div>
            <div><strong>Path:</strong> <code style="background: rgba(0,0,0,0.15); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm);"><?= htmlspecialchars($node['path'] ?: '-') ?></code></div>
            <div><strong>Service Name (gRPC):</strong> <code style="background: rgba(0,0,0,0.15); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm);"><?= htmlspecialchars($node['service_name'] ?: '-') ?></code></div>
            <div><strong>Public Key (Reality/WG):</strong> <code style="background: rgba(0,0,0,0.15); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm); word-break: break-all; font-size: 0.8rem;"><?= htmlspecialchars($node['public_key'] ?: '-') ?></code></div>
            <div><strong>Short ID (Reality):</strong> <code style="background: rgba(0,0,0,0.15); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm);"><?= htmlspecialchars($node['short_id'] ?: '-') ?></code></div>
            <div><strong>Password / Key:</strong> <code style="background: rgba(0,0,0,0.15); padding: 0.15rem 0.35rem; border-radius: var(--radius-sm); word-break: break-all;"><?= htmlspecialchars($node['password'] ?: '-') ?></code></div>
            <div>
                <strong>Trạng Thái:</strong> 
                <?php
                $statusBadge = [
                    'active'   => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                    'inactive' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                ];
                ?>
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$node['status'] ?? 'active'] ?? '' ?>">
                    <?= strtoupper($node['status'] ?? 'active') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>