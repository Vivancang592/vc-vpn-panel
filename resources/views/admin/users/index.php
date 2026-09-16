<?php
$pageTitle = "Danh Sách Người Dùng - Quản Trị Hệ Thống";
$activeMenu = "users";

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Quản Lý Người Dùng</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Danh sách tất cả tài khoản thành viên trong hệ thống</p>
    </div>
    <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
        <a href="/admin/users/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">+ Thêm Người Dùng</a>
    </div>
</div>

<!-- Bộ Lọc & Tìm Kiếm -->
<div class="glass-card" style="padding: 1rem; margin-bottom: 1.25rem; width: 100%; box-sizing: border-box;">
    <form method="GET" action="/admin/users" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; width: 100%;">
        <input type="text" name="search" class="glass-input" placeholder="Tìm theo username, email..." value="<?= htmlspecialchars($search ?? '') ?>" style="flex: 1 1 180px; min-width: 0; max-width: 100%;">
        
        <select name="role" class="glass-input" style="flex: 1 1 120px; min-width: 0; cursor: pointer;">
            <option value="">-- Vai trò --</option>
            <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="staff" <?= ($role ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
            <option value="user" <?= ($role ?? '') === 'user' ? 'selected' : '' ?>>User</option>
        </select>

        <select name="status" class="glass-input" style="flex: 1 1 130px; min-width: 0; cursor: pointer;">
            <option value="">-- Trạng thái --</option>
            <option value="active" <?= ($status ?? '') === 'active' ? 'selected' : '' ?>>Hoạt động</option>
            <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Chưa kích hoạt</option>
            <option value="banned" <?= ($status ?? '') === 'banned' ? 'selected' : '' ?>>Khóa (Banned)</option>
        </select>

        <button type="submit" class="glass-btn" style="white-space: nowrap;">🔍 Tìm kiếm</button>
        <?php if (!empty($search) || !empty($role) || !empty($status)): ?>
            <a href="/admin/users" style="color: var(--ios-danger); font-size: 0.85rem; text-decoration: none; font-weight: 600; white-space: nowrap;">Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<!-- Bảng Người Dùng -->
<div class="glass-card" style="padding: 1.25rem; width: 100%; box-sizing: border-box;">
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Người Dùng</th>
                    <th>IP Đăng Nhập</th>
                    <th>Vai Trò</th>
                    <th>Số Dư</th>
                    <th>Hoa Hồng</th>
                    <th>Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= $u['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.9rem;"><?= htmlspecialchars($u['username']) ?></div>
                                <div style="font-size: 0.78rem; color: var(--ios-text-secondary);"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <code style="background: rgba(0, 122, 255, 0.08); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 600; color: var(--ios-text);">
                                    <?= htmlspecialchars($u['last_login_ip'] ?? 'Chưa ghi nhận') ?>
                                </code>
                            </td>
                            <td>
                                <?php
                                $roleBadge = [
                                    'admin' => 'background: rgba(255, 45, 85, 0.15); color: #ff2d55;',
                                    'staff' => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                                    'user'  => 'background: rgba(0, 122, 255, 0.15); color: var(--ios-blue);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $roleBadge[$u['role']] ?? '' ?>">
                                    <?= strtoupper($u['role']) ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-success);">
                                <?= isset($formatMoney) ? $formatMoney($u['balance']) : number_format($u['balance'], 2) ?>
                            </td>
                            <td style="font-weight: 700; color: var(--ios-warning);">
                                <?= isset($formatMoney) ? $formatMoney($u['commission_balance']) : number_format($u['commission_balance'], 2) ?>
                            </td>
                            <td>
                                <?php
                                $statusBadge = [
                                    'active'   => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                                    'inactive' => 'background: rgba(142, 142, 147, 0.15); color: var(--ios-text-secondary);',
                                    'banned'   => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                                ];
                                ?>
                                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$u['status']] ?? '' ?>">
                                    <?= strtoupper($u['status']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--ios-text-secondary);">
                                <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu" style="min-width: 175px; white-space: nowrap;">
                                        <a href="/admin/users/detail?id=<?= $u['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>👁️</span> Xem chi tiết
                                        </a>
                                        <a href="/admin/users/edit?id=<?= $u['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>✏️</span> Chỉnh sửa
                                        </a>
                                        <a href="/admin/orders/create?user_id=<?= $u['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>🛒</span> Tạo đơn hàng
                                        </a>
                                        <a href="/admin/orders?user_id=<?= $u['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>📦</span> Xem đơn hàng
                                        </a>
                                        <a href="/admin/subscriptions?user_id=<?= $u['id'] ?>" class="action-item" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>🔑</span> Xem gói đăng ký
                                        </a>
                                        <?php if ($u['role'] !== 'admin'): ?>
                                            <form method="POST" action="/admin/users/delete" onsubmit="return confirm('Bạn có chắc chắn muốn xóa thành viên này?');" style="margin: 0;">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="action-item delete" style="background: none; border: none; width: 100%; text-align: left; cursor: pointer; color: var(--ios-danger); padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                                    <span>🗑️</span> Xóa tài khoản
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--ios-text-secondary);">Không tìm thấy thành viên nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>