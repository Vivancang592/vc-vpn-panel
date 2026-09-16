<?php
$pageTitle = "Quản Lý Gói Đăng Ký - Quản Trị Hệ Thống";
$activeMenu = "subscriptions";

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Gói Đăng Ký VPN</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            <?= !empty($filterUser) ? 'Danh sách gói đăng ký của người dùng: <strong>' . htmlspecialchars($filterUser['username']) . '</strong> (ID #' . $filterUser['id'] . ')' : 'Danh sách tài khoản VPN đang kích hoạt và theo dõi lưu lượng kết nối' ?>
        </p>
    </div>
    <?php if (!empty($userId)): ?>
        <div>
            <a href="/admin/subscriptions" class="glass-btn" style="text-decoration: none; white-space: nowrap; background: rgba(255, 59, 48, 0.1); color: var(--ios-danger);">✕ Xóa lọc tài khoản</a>
        </div>
    <?php endif; ?>
</div>

<!-- Bảng Đăng Ký VPN -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách Hàng</th>
                    <th>Gói Cước</th>
                    <th style="text-align: center;">Online</th>
                    <th>Lưu Lượng Dùng</th>
                    <th>Ngày Bắt Đầu</th>
                    <th>Ngày Hết Hạn</th>
                    <th style="text-align: center;">Trạng Thái</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($subscriptions)): ?>
                    <?php foreach ($subscriptions as $sub): ?>
                        <?php
                        $usedBytes = ($sub['upload'] ?? 0) + ($sub['download'] ?? 0);
                        $totalBytes = (float)($sub['transfer_enable'] ?? 0);
                        
                        // Quy đổi hiển thị MB khi dưới 1 GB (1024*1024*1024 bytes)
                        if ($usedBytes < 1073741824) {
                            $formattedUsed = round($usedBytes / (1024 * 1024), 2) . ' MB';
                        } else {
                            $formattedUsed = round($usedBytes / (1024 * 1024 * 1024), 2) . ' GB';
                        }

                        if ($totalBytes <= 0) {
                            $formattedTotal = '∞';
                        } elseif ($totalBytes < 1073741824) {
                            $formattedTotal = round($totalBytes / (1024 * 1024), 2) . ' MB';
                        } else {
                            $formattedTotal = round($totalBytes / (1024 * 1024 * 1024), 2) . ' GB';
                        }

                        $onlineDevices = (int)($sub['online_devices'] ?? 0);
                        ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $sub['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($sub['username'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($sub['email'] ?? '') ?></div>
                            </td>
                            <td style="font-weight: 600; font-size: 0.85rem; color: var(--ios-text);">
                                <?= htmlspecialchars($sub['plan_name'] ?? ('Gói #' . $sub['plan_id'])) ?>
                            </td>
                            <td style="text-align: center;">
                                <span style="padding: 0.15rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 700; <?= $onlineDevices > 0 ? 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);' : 'background: rgba(142, 142, 147, 0.12); color: var(--ios-text-secondary);' ?>">
                                     <?= $onlineDevices ?> 📱
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <span style="font-weight: 700; color: var(--ios-blue);"><?= $formattedUsed ?></span>
                                <span style="color: var(--ios-text-secondary);">/ <?= $formattedTotal ?></span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y', strtotime($sub['start_date'])) ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y', strtotime($sub['end_date'])) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                $statusBadge = [
                                    'active'    => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'expired'   => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'suspended' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);',
                                    'cancelled' => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$sub['status'] ?? 'active'] ?? '' ?>">
                                    <?= strtoupper($sub['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu" style="min-width: 185px; white-space: nowrap;">
                                        <a href="/admin/subscriptions/detail?id=<?= $sub['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>👁️</span> Xem chi tiết
                                        </a>
                                        <a href="javascript:void(0)" onclick="copySubLink('<?= htmlspecialchars($sub['uuid']) ?>')" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>📋</span> Sao chép Link
                                        </a>
                                        <a href="javascript:void(0)" onclick="openQrModal('<?= htmlspecialchars($sub['uuid']) ?>')" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>📱</span> Mã QR
                                        </a>

                                        <div style="border-top: 1px solid rgba(0, 0, 0, 0.08); margin: 0.25rem 0;"></div>

                                        <!-- Reset Token (UUID) -->
                                        <button type="submit" form="reset-token-sub-form-<?= $sub['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: #FF9500; padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                            <span>🔑</span> Reset Token
                                        </button>

                                        <!-- Gia hạn gói -->
                                        <button type="submit" form="renew-sub-form-<?= $sub['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-blue); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                            <span>🔄</span> Gia hạn gói
                                        </button>

                                        <!-- Reset lưu lượng -->
                                        <button type="submit" form="reset-traffic-sub-form-<?= $sub['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: #5856D6; padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                            <span>⚡</span> Reset lưu lượng
                                        </button>

                                        <!-- Thay đổi trạng thái -->
                                        <?php if (($sub['status'] ?? '') !== 'active'): ?>
                                            <button type="submit" form="status-active-sub-form-<?= $sub['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-success); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                                <span>✅</span> Kích hoạt gói
                                            </button>
                                        <?php endif; ?>

                                        <?php if (($sub['status'] ?? '') === 'active'): ?>
                                            <button type="submit" form="status-suspended-sub-form-<?= $sub['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-warning); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                                <span>⏸️</span> Tạm dừng gói
                                            </button>
                                        <?php endif; ?>

                                        <?php if (($sub['status'] ?? '') !== 'cancelled'): ?>
                                            <button type="submit" form="status-cancelled-sub-form-<?= $sub['id'] ?>" class="action-item" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                                <span>❌</span> Hủy đăng ký
                                            </button>
                                        <?php endif; ?>

                                        <div style="border-top: 1px solid rgba(0, 0, 0, 0.08); margin: 0.25rem 0;"></div>

                                        <!-- Xóa gói đăng ký -->
                                        <button type="submit" form="delete-sub-form-<?= $sub['id'] ?>" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                            <span>🗑️</span> Xóa gói
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Chưa có tài khoản đăng ký VPN nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Các Form ẩn gửi phương thức POST cho các thao tác -->
<?php if (!empty($subscriptions)): ?>
    <?php foreach ($subscriptions as $sub): ?>
        <!-- Form Reset Token -->
        <form id="reset-token-sub-form-<?= $sub['id'] ?>" method="POST" action="/admin/subscriptions/reset-token" onsubmit="return confirm('Xác nhận đổi mã Token (UUID) mới cho gói này? Liên kết đăng ký cũ sẽ ngắt kết nối!');" style="display: none;">
            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
            <?php if (!empty($userId)): ?>
                <input type="hidden" name="user_id" value="<?= $userId ?>">
            <?php endif; ?>
        </form>

        <!-- Form Gia hạn gói -->
        <form id="renew-sub-form-<?= $sub['id'] ?>" method="POST" action="/admin/subscriptions/renew" onsubmit="return confirm('Xác nhận gia hạn thêm thời hạn sử dụng cho gói đăng ký này?');" style="display: none;">
            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
            <?php if (!empty($userId)): ?>
                <input type="hidden" name="user_id" value="<?= $userId ?>">
            <?php endif; ?>
        </form>

        <!-- Form Reset lưu lượng -->
        <form id="reset-traffic-sub-form-<?= $sub['id'] ?>" method="POST" action="/admin/subscriptions/reset-traffic" onsubmit="return confirm('Xác nhận đặt lại dung lượng đã sử dụng (Upload & Download) về 0 GB?');" style="display: none;">
            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
            <?php if (!empty($userId)): ?>
                <input type="hidden" name="user_id" value="<?= $userId ?>">
            <?php endif; ?>
        </form>

        <!-- Form Thay đổi trạng thái: Kích hoạt -->
        <?php if (($sub['status'] ?? '') !== 'active'): ?>
            <form id="status-active-sub-form-<?= $sub['id'] ?>" method="POST" action="/admin/subscriptions/update-status" onsubmit="return confirm('Kích hoạt lại gói đăng ký này?');" style="display: none;">
                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                <input type="hidden" name="status" value="active">
                <?php if (!empty($userId)): ?>
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <!-- Form Thay đổi trạng thái: Tạm dừng -->
        <?php if (($sub['status'] ?? '') === 'active'): ?>
            <form id="status-suspended-sub-form-<?= $sub['id'] ?>" method="POST" action="/admin/subscriptions/update-status" onsubmit="return confirm('Tạm dừng gói đăng ký này?');" style="display: none;">
                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                <input type="hidden" name="status" value="suspended">
                <?php if (!empty($userId)): ?>
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <!-- Form Thay đổi trạng thái: Hủy đăng ký -->
        <?php if (($sub['status'] ?? '') !== 'cancelled'): ?>
            <form id="status-cancelled-sub-form-<?= $sub['id'] ?>" method="POST" action="/admin/subscriptions/update-status" onsubmit="return confirm('Hủy gói đăng ký này?');" style="display: none;">
                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                <input type="hidden" name="status" value="cancelled">
                <?php if (!empty($userId)): ?>
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <!-- Form Xóa gói -->
        <form id="delete-sub-form-<?= $sub['id'] ?>" method="POST" action="/admin/subscriptions/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn gói đăng ký này khỏi hệ thống?');" style="display: none;">
            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
            <?php if (!empty($userId)): ?>
                <input type="hidden" name="user_id" value="<?= $userId ?>">
            <?php endif; ?>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Modal Hiển Thị Mã QR -->
<div id="qrModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center;">
    <div class="glass-card" style="padding: 1.5rem; max-width: 320px; width: 90%; text-align: center; position: relative; background: rgba(255, 255, 255, 0.95);">
        <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 700;">Quét Mã QR Đăng Ký</h3>
        <div style="margin: 1rem 0; padding: 0.75rem; background: #fff; border-radius: var(--radius-sm); display: inline-block;">
            <img id="qrCodeImg" src="" alt="QR Code" style="width: 200px; height: 200px; display: block;">
        </div>
        <div>
            <button type="button" onclick="closeQrModal()" class="glass-btn" style="width: 100%; padding: 0.5rem; font-weight: 600;">Đóng</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>