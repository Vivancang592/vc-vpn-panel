<?php
$pageTitle = "Chi Tiết Yêu Cầu Rút Tiền - Quản Trị Hệ Thống";
$activeMenu = "withdrawals";

ob_start();
?>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Chi Tiết Yêu Cầu Rút Tiền: #<?= $withdrawal['id'] ?></h1>
    </div>
    <a href="/admin/withdrawals" class="glass-btn" style="text-decoration: none; white-space: nowrap; flex-shrink: 0;">⬅️ Quay Lại</a>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
    <!-- Thẻ Thông Tin Khách Hàng & Yêu Cầu -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Tài Khoản</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div><strong>Mã Yêu Cầu:</strong> #<?= $withdrawal['id'] ?></div>
            <div><strong>Khách Hàng:</strong> <?= htmlspecialchars($withdrawal['username'] ?? 'N/A') ?> (ID #<?= $withdrawal['user_id'] ?>)</div>
            <div><strong>Họ và tên:</strong> <?= htmlspecialchars($withdrawal['full_name'] ?? 'Chưa cập nhật') ?></div>
            <div><strong>Email:</strong> <?= htmlspecialchars($withdrawal['email'] ?? 'N/A') ?></div>
            <div>
                <strong>Số Dư Hoa Hồng Hiện Tại:</strong> 
                <span style="color: var(--ios-warning); font-weight: 700;">
                    <?= isset($formatMoney) ? $formatMoney($withdrawal['commission_balance'] ?? 0) : number_format($withdrawal['commission_balance'] ?? 0, 2) ?>
                </span>
            </div>
            <div><strong>Thời Gian Tạo:</strong> <?= date('d/m/Y H:i:s', strtotime($withdrawal['created_at'])) ?></div>
            <div>
                <strong>Trạng Thái Yêu Cầu:</strong> 
                <?php
                $statusBadge = [
                    'approved' => 'background: rgba(52, 199, 89, 0.15); color: var(--ios-success);',
                    'pending'  => 'background: rgba(255, 149, 0, 0.15); color: var(--ios-warning);',
                    'rejected' => 'background: rgba(255, 59, 48, 0.15); color: var(--ios-danger);'
                ];
                ?>
                <span style="padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; <?= $statusBadge[$withdrawal['status'] ?? 'pending'] ?? '' ?>">
                    <?= strtoupper($withdrawal['status'] ?? 'pending') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Thẻ Thông Tin Ngân Hàng & Thao Tác -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Thông Tin Chuyển Khoản Ngân Hàng</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
            <div>
                <strong>Số Tiền Cần Chuyển:</strong> 
                <span style="color: var(--ios-success); font-weight: 700; font-size: 1.1rem;">
                    <?= isset($formatMoney) ? $formatMoney($withdrawal['amount']) : number_format($withdrawal['amount'], 2) ?>
                </span>
            </div>
            <div><strong>Tên Ngân Hàng:</strong> <span style="font-weight: 700;"><?= htmlspecialchars($withdrawal['bank_name']) ?></span></div>
            <div>
                <strong>Số Tài Khoản:</strong> 
                <code style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-weight: 700; font-size: 1rem;">
                    <?= htmlspecialchars($withdrawal['bank_account_number']) ?>
                </code>
            </div>
            <div>
                <strong>Tên Chủ Tài Khoản:</strong> 
                <span style="font-weight: 700; text-transform: uppercase;">
                    <?= htmlspecialchars($withdrawal['bank_account_name']) ?>
                </span>
            </div>

            <?php if (($withdrawal['status'] ?? '') === 'pending'): ?>
                <div style="margin-top: 1rem; border-top: 1px solid var(--glass-border); padding-top: 1rem; display: flex; gap: 0.75rem;">
                    <form method="POST" action="/admin/withdrawals/detail?id=<?= $withdrawal['id'] ?>" style="margin: 0;">
                        <input type="hidden" name="action" value="approved">
                        <button type="submit" onclick="return confirm('Xác nhận đã chuyển khoản và DUYỆT yêu cầu này?');" class="glass-btn" style="background: var(--ios-success); color: #fff; border: none; padding: 0.6rem 1.25rem; font-weight: 600;">
                            ✓ Duyệt Yêu Cầu
                        </button>
                    </form>

                    <form method="POST" action="/admin/withdrawals/detail?id=<?= $withdrawal['id'] ?>" style="margin: 0;">
                        <input type="hidden" name="action" value="rejected">
                        <button type="submit" onclick="return confirm('Bạn có chắc chắn muốn TỪ CHỐI yêu cầu này?');" class="glass-btn" style="background: var(--ios-danger); color: #fff; border: none; padding: 0.6rem 1.25rem; font-weight: 600;">
                            ✕ Từ Chối
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>