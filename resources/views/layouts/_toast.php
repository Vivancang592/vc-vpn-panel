<?php
/**
 * Toast thông báo trung tâm cho Khu vực Admin.
 *
 * Nguồn dữ liệu: $vcToasts do BaseController::render() thu từ session
 * (flash_message/flash_type, success, error) TRƯỚC khi render view,
 * nên hiển thị đúng trang sau mỗi thao tác — kể cả trang AI trước đây thiếu flash success.
 *
 * Class "glass-alert" + nút ".alert-close" GIỮ NGUYÊN để js/admin.js
 * (vcInitGlassAlerts) tự xử lý đóng nút ✕ và tự ẩn sau 4 giây — không sửa JS.
 */
$vcToasts = $vcToasts ?? [];
$vcToastIcons = ['success' => '&#10003;', 'danger' => '&#10005;', 'warning' => '&#9888;', 'info' => '&#8505;'];

if (!$vcToasts) {
    return;
}
?>
<?php foreach ($vcToasts as $vcToast):
    $vcType = (string)($vcToast['type'] ?? 'info');
    if ($vcType === 'error') {
        $vcType = 'danger';
    }
    if (!isset($vcToastIcons[$vcType])) {
        $vcType = 'info';
    }
    $vcMsg = (string)($vcToast['message'] ?? '');
    if ($vcMsg === '') {
        continue;
    }
    ?>
    <div class="glass-alert vc-toast vc-toast--<?= $vcType ?>" role="alert" aria-live="polite">
        <span class="vc-toast__icon" aria-hidden="true"><?= $vcToastIcons[$vcType] ?></span>
        <span class="vc-toast__msg"><?= htmlspecialchars($vcMsg, ENT_QUOTES, 'UTF-8') ?></span>
        <button type="button" class="alert-close vc-toast__close" title="Đóng" aria-label="Đóng">&times;</button>
    </div>
<?php endforeach; ?>
