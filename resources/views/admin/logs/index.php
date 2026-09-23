<?php
$activeLogTab = $activeLogTab ?? 'system';
$logTabs = [
    'system' => [
        'label'       => 'Hoạt động hệ thống',
        'icon'        => '📝',
        'url'         => '/admin/logs/system',
        'title'       => 'Nhật Ký Hoạt Động Hệ Thống',
        'description' => 'Theo dõi các hành động quản trị và thao tác thay đổi cấu hình trong hệ thống.'
    ],
    'access' => [
        'label'       => 'Truy cập',
        'icon'        => '🔐',
        'url'         => '/admin/logs/access',
        'title'       => 'Nhật Ký Truy Cập & Đăng Nhập',
        'description' => 'Lịch sử đăng ký, đăng nhập, thiết bị và nguồn truy cập đầu tiên của các thành viên.'
    ],
    'email' => [
        'label'       => 'Email',
        'icon'        => '📧',
        'url'         => '/admin/logs/email',
        'title'       => 'Nhật Ký Gửi Email Hệ Thống',
        'description' => 'Theo dõi trạng thái gửi email thông báo, khôi phục mật khẩu và hóa đơn.'
    ],
    'chatbot' => [
        'label'       => 'Chatbot AI',
        'icon'        => '🤖',
        'url'         => '/admin/logs/chatbot',
        'title'       => 'Nhật Ký Chatbot Và Chuyển Đổi',
        'description' => 'Theo dõi sự kiện chat web/fanpage, hành vi chuyển đổi và tình trạng chuyển người thật.'
    ],
    'macrodroid' => [
        'label'       => 'Webhook',
        'icon'        => '📲',
        'url'         => '/admin/logs/macrodroid',
        'title'       => 'Nhật Ký Webhook Thanh Toán',
        'description' => 'Kiểm tra dữ liệu phản hồi từ thiết bị hoặc webhook ngân hàng tự động.'
    ]
];

if (!isset($logTabs[$activeLogTab])) {
    $activeLogTab = 'system';
}

$currentTab = $logTabs[$activeLogTab];
$pageTitle = $currentTab['title'] . ' - Quản Trị Hệ Thống';
$activeMenu = 'logs';
ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid <?= ($_SESSION['flash_type'] ?? '') === 'success' ? 'var(--ios-success)' : 'var(--ios-danger)' ?>; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<div class="logs-page-header">
    <div>
        <h1>Nhật Ký Hệ Thống</h1>
        <p><?= htmlspecialchars($currentTab['description']) ?></p>
    </div>

    <?php if ($activeLogTab === 'macrodroid'): ?>
        <div class="logs-page-actions">
            <form method="POST" action="/admin/logs/macrodroid/clear" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ nội dung nhật ký này?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="logs-clear-btn">🗑️ Xóa log</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<nav class="logs-tabs" aria-label="Các loại nhật ký">
    <?php foreach ($logTabs as $tabKey => $tab): ?>
        <a href="<?= $tab['url'] ?>" class="logs-tab <?= $activeLogTab === $tabKey ? 'active' : '' ?>" <?= $activeLogTab === $tabKey ? 'aria-current="page"' : '' ?>>
            <span><?= $tab['icon'] ?></span> <?= htmlspecialchars($tab['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="glass-card logs-content-card">
    <?php if ($activeLogTab === 'system'): ?>
        <form method="POST" action="/admin/logs/delete" class="logs-bulk-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="log_type" value="system">
            <div class="logs-bulk-actions">
                <label class="logs-select-all-label"><input type="checkbox" class="logs-select-all"> Chọn tất cả</label>
                <div class="logs-delete-actions">
                    <button type="submit" class="logs-delete-selected" onclick="return confirm('Bạn có chắc chắn muốn xóa các bản ghi đã chọn?');">Xóa mục đã chọn</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="glass-table">
                <thead>
                    <tr>
                        <th class="logs-select-column"><span class="sr-only">Chọn</span></th>
                        <th>ID</th>
                        <th>Tài Khoản</th>
                        <th>Hành Động</th>
                        <th>Mô Tả Chi Tiết</th>
                        <th>Địa Chỉ IP</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="logs-select-column"><input type="checkbox" name="log_ids[]" value="<?= (int)$log['id'] ?>" class="logs-row-select" aria-label="Chọn log #<?= (int)$log['id'] ?>"></td>
                                <td class="logs-id">#<?= $log['id'] ?></td>
                                <td class="logs-user"><?= htmlspecialchars($log['username'] ?? 'Hệ thống') ?></td>
                                <td><span class="logs-badge logs-badge-blue"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td class="logs-truncate"><?= htmlspecialchars($log['description'] ?: '-') ?></td>
                                <td><code class="logs-code"><?= htmlspecialchars($log['ip_address'] ?: 'N/A') ?></code></td>
                                <td class="logs-time"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="logs-empty">Chưa có nhật ký hoạt động nào.</td></tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </form>
    <?php elseif ($activeLogTab === 'access'): ?>
        <form method="POST" action="/admin/logs/delete" class="logs-bulk-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="log_type" value="access">
            <div class="logs-bulk-actions">
                <label class="logs-select-all-label"><input type="checkbox" class="logs-select-all"> Chọn tất cả</label>
                <div class="logs-delete-actions">
                    <button type="submit" class="logs-delete-selected" onclick="return confirm('Bạn có chắc chắn muốn xóa các bản ghi đã chọn?');">Xóa mục đã chọn</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="glass-table">
                <thead>
                    <tr>
                        <th class="logs-select-column"><span class="sr-only">Chọn</span></th>
                        <th>ID</th>
                        <th>Thành Viên</th>
                        <th>Hành Động</th>
                        <th>Nguồn / UTM</th>
                        <th>Trang Đích</th>
                        <th>Địa Chỉ IP</th>
                        <th>Thiết Bị / Trình Duyệt</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="logs-select-column"><input type="checkbox" name="log_ids[]" value="<?= (int)$log['id'] ?>" class="logs-row-select" aria-label="Chọn log #<?= (int)$log['id'] ?>"></td>
                                <td class="logs-id">#<?= $log['id'] ?></td>
                                <td>
                                    <div class="logs-user-name"><?= htmlspecialchars($log['username'] ?? 'N/A') ?></div>
                                    <div class="logs-user-email"><?= htmlspecialchars($log['email'] ?? '') ?></div>
                                </td>
                                <td><span class="logs-badge logs-badge-green"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td>
                                    <?php $source = $log['source'] ?? ''; ?>
                                    <div class="logs-user-name"><?= htmlspecialchars($source === 'direct' ? 'Trực tiếp' : ($source ?: 'Không xác định')) ?></div>
                                    <?php if (!empty($log['referrer_host'])): ?>
                                        <div class="logs-user-email"><?= htmlspecialchars($log['referrer_host']) ?></div>
                                    <?php endif; ?>
                                    <?php
                                    $utmDetails = array_filter([
                                        !empty($log['utm_source']) ? 'Nguồn: ' . $log['utm_source'] : null,
                                        !empty($log['utm_medium']) ? 'Kênh: ' . $log['utm_medium'] : null,
                                        !empty($log['utm_campaign']) ? 'Chiến dịch: ' . $log['utm_campaign'] : null
                                    ]);
                                    ?>
                                    <?php if (!empty($utmDetails)): ?>
                                        <div class="logs-user-email"><?= htmlspecialchars(implode(' · ', $utmDetails)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><code class="logs-code"><?= htmlspecialchars($log['landing_path'] ?? '-') ?></code></td>
                                <td><code class="logs-code logs-code-blue"><?= htmlspecialchars($log['ip_address']) ?></code></td>
                                <td class="logs-truncate"><?= htmlspecialchars($log['user_agent'] ?: '-') ?></td>
                                <td class="logs-time"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="logs-empty">Chưa có dữ liệu truy cập nào.</td></tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </form>
    <?php elseif ($activeLogTab === 'email'): ?>
        <form method="POST" action="/admin/logs/delete" class="logs-bulk-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="log_type" value="email">
            <div class="logs-bulk-actions">
                <label class="logs-select-all-label"><input type="checkbox" class="logs-select-all"> Chọn tất cả</label>
                <div class="logs-delete-actions">
                    <button type="submit" class="logs-delete-selected" onclick="return confirm('Bạn có chắc chắn muốn xóa các bản ghi đã chọn?');">Xóa mục đã chọn</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="glass-table">
                <thead>
                    <tr>
                        <th class="logs-select-column"><span class="sr-only">Chọn</span></th>
                        <th>ID</th>
                        <th>Email Người Nhận</th>
                        <th>Tiêu Đề Email</th>
                        <th class="text-center">Trạng Thái</th>
                        <th>Thông Báo Lỗi</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="logs-select-column"><input type="checkbox" name="log_ids[]" value="<?= (int)$log['id'] ?>" class="logs-row-select" aria-label="Chọn log #<?= (int)$log['id'] ?>"></td>
                                <td class="logs-id">#<?= $log['id'] ?></td>
                                <td class="logs-user"><?= htmlspecialchars($log['recipient']) ?></td>
                                <td class="logs-subject"><?= htmlspecialchars($log['subject']) ?></td>
                                <td class="text-center">
                                    <?php if (($log['status'] ?? 'sent') === 'sent'): ?>
                                        <span class="logs-badge logs-badge-green">ĐÃ GỬI</span>
                                    <?php else: ?>
                                        <span class="logs-badge logs-badge-danger">THẤT BẠI</span>
                                    <?php endif; ?>
                                </td>
                                <td class="logs-truncate logs-error"><?= htmlspecialchars($log['error_message'] ?: '-') ?></td>
                                <td class="logs-time"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="logs-empty">Chưa có nhật ký gửi email nào.</td></tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </form>
    <?php elseif ($activeLogTab === 'chatbot'): ?>
        <form method="POST" action="/admin/logs/delete" class="logs-bulk-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="log_type" value="chatbot">
            <div class="logs-bulk-actions">
                <label class="logs-select-all-label"><input type="checkbox" class="logs-select-all"> Chọn tất cả</label>
                <div class="logs-delete-actions">
                    <button type="submit" class="logs-delete-selected" onclick="return confirm('Bạn có chắc chắn muốn xóa các bản ghi đã chọn?');">Xóa mục đã chọn</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="glass-table">
                <thead>
                    <tr>
                        <th class="logs-select-column"><span class="sr-only">Chọn</span></th>
                        <th>ID</th>
                        <th>Sự Kiện</th>
                        <th>Nguồn</th>
                        <th>Phiên</th>
                        <th>Thành Viên</th>
                        <th>Dữ Liệu</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $rawData = trim((string)($log['event_data'] ?? ''));
                                $decoded = $rawData !== '' ? json_decode($rawData, true) : null;
                                $displayData = is_array($decoded)
                                    ? json_encode($decoded, JSON_UNESCAPED_UNICODE)
                                    : ($rawData !== '' ? $rawData : '-');
                            ?>
                            <tr>
                                <td class="logs-select-column"><input type="checkbox" name="log_ids[]" value="<?= (int)$log['id'] ?>" class="logs-row-select" aria-label="Chọn log #<?= (int)$log['id'] ?>"></td>
                                <td class="logs-id">#<?= (int)$log['id'] ?></td>
                                <td><span class="logs-badge logs-badge-blue"><?= htmlspecialchars((string)($log['event_name'] ?? '-')) ?></span></td>
                                <td><span class="logs-badge logs-badge-green"><?= htmlspecialchars((string)($log['source'] ?? $log['session_source'] ?? 'web')) ?></span></td>
                                <td>
                                    <div class="logs-user-name">SID: <?= (int)($log['session_id'] ?? 0) ?></div>
                                    <div class="logs-user-email"><?= htmlspecialchars((string)($log['external_id'] ?? $log['visitor_token'] ?? '-')) ?></div>
                                </td>
                                <td>
                                    <div class="logs-user-name"><?= htmlspecialchars((string)($log['username'] ?? 'Khách')) ?></div>
                                    <div class="logs-user-email"><?= htmlspecialchars((string)($log['email'] ?? '')) ?></div>
                                </td>
                                <td class="logs-truncate"><?= htmlspecialchars($displayData) ?></td>
                                <td class="logs-time"><?= !empty($log['created_at']) ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="logs-empty">Chưa có nhật ký chatbot nào.</td></tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </form>
    <?php else: ?>
        <?php if (!empty(trim($logContent))): ?>
            <pre class="macrodroid-log-output"><?= htmlspecialchars($logContent) ?></pre>
        <?php else: ?>
            <div class="logs-empty-block">
                <p>File nhật ký hiện tại đang trống.</p>
                <span>Các yêu cầu Webhook mới từ MacroDroid sẽ tự động xuất hiện tại đây.</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.logs-bulk-form').forEach(function (form) {
    const selectAll = form.querySelector('.logs-select-all');
    const rowSelects = form.querySelectorAll('.logs-row-select');

    if (!selectAll || rowSelects.length === 0) {
        return;
    }

    selectAll.addEventListener('change', function () {
        rowSelects.forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
    });

    rowSelects.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            selectAll.checked = rowSelects.length === form.querySelectorAll('.logs-row-select:checked').length;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
