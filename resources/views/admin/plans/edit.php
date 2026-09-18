<?php

$pageTitle = "Chỉnh Sửa Gói Cước - Quản Trị Hệ Thống";
$activeMenu = "plans";

/*
 * Lấy cấu hình tiền tệ từ Settings
 */
$currencySymbol  = $settings['currency_symbol'] ?? 'đ';
$currencyCode    = $settings['currency'] ?? 'VND';
$currencyDecimals = max(0, min(4, (int)($settings['currency_decimals'] ?? 0)));

/*
 * Tự động thiết lập bước nhập tiền theo số chữ số thập phân.
 *
 * 0 => step="1"
 * 1 => step="0.1"
 * 2 => step="0.01"
 * 3 => step="0.001"
 * 4 => step="0.0001"
 */
$priceStep = $currencyDecimals === 0
    ? '1'
    : '0.' . str_repeat('0', $currencyDecimals - 1) . '1';

/*
 * Format giá hiện tại theo đúng số chữ số thập phân
 * được cấu hình trong Settings.
 *
 * Ví dụ:
 * Settings = 0  => 10000
 * Settings = 2  => 10000.00
 */
$currentPrice = number_format(
    (float)($plan['price'] ?? 0),
    $currencyDecimals,
    '.',
    ''
);

$selectedGroupIds = $plan['group_ids'] ?? [];

if (!is_array($selectedGroupIds)) {
    $selectedGroupIds = json_decode($plan['group_id'] ?? '[]', true);

    if (!is_array($selectedGroupIds)) {
        $selectedGroupIds = !empty($plan['group_id'])
            ? [(int)$plan['group_id']]
            : [];
    }
}

ob_start();
?>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">
        Chỉnh Sửa: <?= htmlspecialchars($plan['name'] ?? '') ?>
    </h1>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
        </span>

        <button
            type="button"
            class="alert-close"
            style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;"
            title="Đóng"
        >
            &times;
        </button>

        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </span>

        <button
            type="button"
            class="alert-close"
            style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;"
            title="Đóng"
        >
            &times;
        </button>

        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="glass-card" style="padding: 1.75rem; width: 100%;">

    <form
        method="POST"
        action="/admin/plans/edit?id=<?= $plan['id'] ?>"
        style="display: flex; flex-direction: column; gap: 1.25rem;"
    >

        <input
            type="hidden"
            name="id"
            value="<?= $plan['id'] ?>"
        >

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Tên Gói Cước (*)
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="glass-input"
                    value="<?= htmlspecialchars($plan['name'] ?? '') ?>"
                    required
                    autofocus
                    style="width: 100%;"
                >
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Mã Gói (Code) (*)
                </label>

                <input
                    type="text"
                    id="code"
                    name="code"
                    class="glass-input"
                    value="<?= htmlspecialchars($plan['code'] ?? '') ?>"
                    required
                    style="width: 100%; text-transform: uppercase;"
                >
            </div>

        </div>

        <div>
            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                Nhóm Máy Chủ Áp Dụng (*)
            </label>

            <div style="display: flex; flex-wrap: wrap; gap: 1rem; background: rgba(255, 255, 255, 0.05); padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.15);">

                <?php if (!empty($groups)): ?>

                    <?php foreach ($groups as $group): ?>

                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.9rem;">

                            <input
                                type="checkbox"
                                name="group_ids[]"
                                value="<?= $group['id'] ?>"
                                <?= in_array($group['id'], $selectedGroupIds) ? 'checked' : '' ?>
                                style="cursor: pointer; width: 16px; height: 16px;"
                            >

                            <?= htmlspecialchars($group['name']) ?>

                        </label>

                    <?php endforeach; ?>

                <?php else: ?>

                    <span style="font-size: 0.85rem; color: var(--ios-text-secondary, #888);">
                        Chưa có nhóm máy chủ nào!
                    </span>

                <?php endif; ?>

            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">

            <div>

                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Giá Bán (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>) (*)
                </label>

                <input
                    type="number"
                    id="price"
                    name="price"
                    class="glass-input"
                    value="<?= htmlspecialchars($currentPrice) ?>"
                    min="0"
                    step="<?= htmlspecialchars($priceStep) ?>"
                    required
                    style="width: 100%;"
                >

            </div>

            <div>

                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Thời Hạn (Ngày) (*)
                </label>

                <input
                    type="number"
                    id="duration_days"
                    name="duration_days"
                    class="glass-input"
                    value="<?= htmlspecialchars($plan['duration_days'] ?? 30) ?>"
                    min="1"
                    step="1"
                    required
                    style="width: 100%;"
                >

            </div>

        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">

            <div>

                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Dung Lượng (GB)
                </label>

                <input
                    type="number"
                    id="bandwidth_limit_gb"
                    name="bandwidth_limit_gb"
                    class="glass-input"
                    value="<?= htmlspecialchars($plan['bandwidth_limit_gb'] ?? 0) ?>"
                    min="0"
                    step="1"
                    style="width: 100%;"
                >

            </div>

            <div>

                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                    Số Thiết Bị Tối Đa (*)
                </label>

                <input
                    type="number"
                    id="max_devices"
                    name="max_devices"
                    class="glass-input"
                    value="<?= htmlspecialchars($plan['max_devices'] ?? 1) ?>"
                    min="1"
                    step="1"
                    required
                    style="width: 100%;"
                >

            </div>

        </div>

        <div>

            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                Mô Tả / Nội Dung Gói Cước
            </label>

            <textarea
                id="description"
                name="description"
                class="glass-input"
                rows="4"
                placeholder="Nhập nội dung chi tiết, tính năng nổi bật của gói cước..."
                style="width: 100%; resize: vertical;"
            ><?= htmlspecialchars($plan['description'] ?? '') ?></textarea>

        </div>

        <div>

            <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">
                Trạng Thái
            </label>

            <select
                id="status"
                name="status"
                class="glass-input"
                style="width: 100%; cursor: pointer;"
            >
                <option value="active" <?= ($plan['status'] ?? '') === 'active' ? 'selected' : '' ?>>
                    Active (Hoạt động)
                </option>

                <option value="inactive" <?= ($plan['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                    Inactive (Khóa)
                </option>
            </select>

        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">

            <button
                type="submit"
                class="glass-btn"
                style="padding: 0.65rem 1.75rem; font-size: 0.9rem;"
            >
                💾 Cập Nhật Thông Tin
            </button>

        </div>

    </form>

</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>