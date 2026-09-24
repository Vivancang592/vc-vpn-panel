<?php
$server = $server ?? [];
$pageTitle = "Chi Tiết Máy Chủ - Quản Trị Hệ Thống";
$activeMenu = "servers";

ob_start();
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Hồ Sơ: <?= htmlspecialchars($server['name'] ?? 'N/A') ?></h1>
    </div>
    <a href="/admin/servers/edit?id=<?= $server['id'] ?>" class="glass-btn" style="text-decoration: none; white-space: nowrap; flex-shrink: 0;">✏️ Chỉnh Sửa</a>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
    <!-- Thẻ Thông Tin Tổng Quan -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Chung</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>ID Máy Chủ:</strong> #<?= $server['id'] ?></div>
            <div><strong>Tên Máy Chủ:</strong> <?= htmlspecialchars($server['name'] ?? 'N/A') ?></div>
            <div><strong>Nhóm Máy Chủ:</strong> <?= htmlspecialchars($server['group_name'] ?? 'Chưa phân nhóm') ?></div>
            <div>
                <strong>Quốc Gia:</strong> 
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; background: rgba(0, 122, 255, 0.15); color: var(--ios-blue); text-transform: uppercase;">
                    <?= htmlspecialchars($server['country_code'] ?? 'N/A') ?>
                </span>
            </div>
            <div><strong>Vị Trí:</strong> <?= htmlspecialchars($server['location'] ?? 'N/A') ?></div>
            <div>
                <strong>Trạng Thái:</strong> 
                <?php
                $statusBadge = [
                    'active'      => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                    'maintenance' => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                    'offline'     => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                ];
                ?>
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$server['status'] ?? 'active'] ?? '' ?>">
                    <?= strtoupper($server['status'] ?? 'active') ?>
                </span>
            </div>
            <div><strong>Lần Check-in Cuối:</strong> <?= !empty($server['last_check_in']) ? date('d/m/Y H:i:s', strtotime($server['last_check_in'])) : 'Chưa ghi nhận' ?></div>
            <div><strong>Ngày Tạo:</strong> <?= !empty($server['created_at']) ? date('d/m/Y H:i:s', strtotime($server['created_at'])) : 'N/A' ?></div>
        </div>
    </div>

    <!-- Thẻ Cấu Hình API & Kết Nối -->
    <?php
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
    $serverApiUrl = $protocol . $host . '/api/server/checkin';
    ?>
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">API & Kết Nối Server</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
            <div>
                <strong>Node API Check-in URL:</strong>
                <div style="display: flex; gap: 0.4rem; margin-top: 0.25rem;">
                    <input type="text" id="node_api_detail_url" class="glass-input" value="<?= htmlspecialchars($serverApiUrl) ?>" readonly style="flex: 1; min-width: 0; font-family: monospace; font-size: 0.82rem; font-weight: 600; background: rgba(0,0,0,0.15);">
                    <button type="button" class="glass-btn" onclick="navigator.clipboard.writeText(document.getElementById('node_api_detail_url').value); alert('Đã sao chép Link API Máy Chủ!');" style="padding: 0 0.75rem; white-space: nowrap; cursor: pointer; font-size: 0.8rem;">
                        📋 Sao chép
                    </button>
                </div>
            </div>
            <div>
                <strong>Địa Chỉ IP:</strong> 
                <code style="background: rgba(0,122,255,0.08); color: var(--ios-text); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700;">
                    <?= htmlspecialchars($server['ip_address'] ?? 'N/A') ?>
                </code>
            </div>
            <div><strong>Cổng API (Port):</strong> <span style="font-weight: 700;"><?= htmlspecialchars($server['api_port'] ?? '80') ?></span></div>
            <div>
                <strong>API Token (Node Key):</strong>
                <div style="display: flex; gap: 0.4rem; margin-top: 0.25rem;">
                    <input type="text" id="server_token_input" class="glass-input" value="<?= htmlspecialchars($server['api_token'] ?? '') ?>" readonly style="flex: 1; min-width: 0; font-family: monospace; font-size: 0.82rem; background: rgba(0,0,0,0.15);">
                    <button type="button" class="glass-btn" onclick="navigator.clipboard.writeText(document.getElementById('server_token_input').value); alert('Đã sao chép API Token!');" style="padding: 0 0.75rem; white-space: nowrap; cursor: pointer; font-size: 0.8rem;">
                        📋 Sao chép Token
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>