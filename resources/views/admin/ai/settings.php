<?php
$pageTitle = 'Cấu Hình AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-settings';

ob_start();

require __DIR__ . '/_flash.php';

/** Nhãn tiếng Việt cho các khoá cấu hình kỹ thuật (không hiển thị key thô). */
$cfgLabels = [
    // Cơ chế thử lại
    'enabled'              => 'Bật cơ chế thử lại',
    'max_attempts'         => 'Số lần thử tối đa',
    'base_delay_ms'        => 'Chờ lần đầu (mili giây)',
    'max_delay_ms'         => 'Chờ tối đa (mili giây)',
    'multiplier'           => 'Hệ số tăng dần',
    'retryable_errors'     => 'Lỗi được thử lại',
    'non_retryable_errors' => 'Lỗi không thử lại',
    // Hàng đợi tác vụ
    'default_max_retries'  => 'Số lần thử lại tối đa',
    'lock_stale_seconds'   => 'Thu hồi khoá treo sau (giây)',
    'default_priority'     => 'Ưu tiên mặc định',
    // Thư viện file
    'disk'                 => 'Nơi lưu trữ',
    'base_path'            => 'Đường dẫn gốc',
    'base_url'             => 'Đường dẫn công khai',
    'allowed_kinds'        => 'Loại file cho phép',
];
$cfgLabel = static function (string $key) use ($cfgLabels): string {
    return $cfgLabels[$key] ?? $key;
};

/** Nhãn tiếng Việt cho mã lỗi của nhà cung cấp AI. */
$errLabels = [
    'TIMEOUT'      => 'Hết thời gian chờ',
    'UPSTREAM_5XX' => 'Lỗi máy chủ nhà cung cấp (5xx)',
    'RATE_LIMIT'   => 'Vượt giới hạn tốc độ',
    'AUTH'         => 'Sai khoá xác thực',
    'VALIDATION'   => 'Dữ liệu không hợp lệ',
    'NOT_FOUND'    => 'Không tìm thấy',
    'CLIENT_ERROR' => 'Lỗi yêu cầu (4xx)',
    'MALFORMED'    => 'Phản hồi sai định dạng',
    'CANCELLED'    => 'Đã huỷ',
    'UNKNOWN'      => 'Không rõ',
];

/** Định dạng giá trị cấu hình sang tiếng Việt. */
$cfgValue = static function ($value, string $key = '') use ($errLabels, $aiLabels): string {
    if (is_bool($value)) {
        return $value ? 'Có' : 'Không';
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $item) {
            $out[] = $errLabels[$item]
                ?? $aiLabels['map']['capability'][$item]
                ?? ($item === 'other' ? 'Khác' : (string) $item);
        }
        return implode(', ', $out);
    }
    if ($key === 'disk') {
        return match ((string) $value) {
            'public'  => 'Thư mục công khai',
            'private' => 'Thư mục nội bộ',
            'local'   => 'Máy chủ nội bộ',
            default   => (string) $value,
        };
    }
    return (string) $value;
};
?>

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box;">
    <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Cấu Hình AI</h1>
    <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
        Cấu hình nhà cung cấp AI, phân hệ và nội quy cho toàn bộ luồng AI. Thông số kỹ thuật do hệ thống quy định, quản trị viên chỉ xem.
    </p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; width: 100%; box-sizing: border-box;">

    <!-- Nhà cung cấp -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Nhà Cung Cấp AI</h2>
        <div style="display: flex; flex-direction: column; gap: 0.45rem; font-size: 0.85rem;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--ios-text-secondary);">Nhà cung cấp mặc định</span>
                <strong><?= htmlspecialchars($aiLabels['map']['provider'][strtolower((string) $provider)] ?? (string) $provider) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--ios-text-secondary);">Nhà cung cấp được phép</span>
                <strong><?= htmlspecialchars(implode(', ', array_map(fn($p) => $aiLabels['map']['provider'][strtolower((string) $p)] ?? (string) $p, $allowedProviders))) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; gap: 0.75rem;">
                <span style="color: var(--ios-text-secondary); white-space: nowrap;">Địa chỉ máy chủ</span>
                <strong style="font-size: 0.78rem; text-align: right; word-break: break-all;"><?= htmlspecialchars($baseUrl) ?></strong>
            </div>
        </div>

        <div style="margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px solid var(--ios-border, rgba(255,255,255,0.08)); display: flex; flex-direction: column; gap: 0.45rem; font-size: 0.85rem;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--ios-text-secondary);">Khoá cài đặt nội bộ</span>
                <code style="font-size: 0.78rem;" title="Khoá cài đặt nội bộ">••••••</code>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--ios-text-secondary);">Biến môi trường</span>
                <code style="font-size: 0.78rem;" title="Biến môi trường">••••••</code>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--ios-text-secondary);">Khoá API (trong CSDL)</span>
                <?php if ($settingConfigured): ?>
                    <strong style="color: var(--ios-success);">Đã cấu hình</strong>
                <?php else: ?>
                    <strong style="color: var(--ios-danger);">Chưa cấu hình</strong>
                <?php endif; ?>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--ios-text-secondary);">Khoá API (tệp .env)</span>
                <?php if ($envConfigured): ?>
                    <strong style="color: var(--ios-success);">Đã cấu hình</strong>
                <?php else: ?>
                    <strong style="color: var(--ios-text-secondary);">Không dùng</strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Nhập API key -->
    <div class="glass-card" style="padding: 1.25rem;">
        <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Khoá API</h2>

        <?php if (!$settingConfigured && !$envConfigured): ?>
            <div style="padding: 0.75rem; margin-bottom: 0.85rem; border-left: 4px solid var(--ios-warning, #ff9f0a); font-size: 0.83rem; background: rgba(255,255,255,0.02); border-radius: var(--radius-sm);">
                Chưa cấu hình khoá API. Tác vụ AI sẽ không gọi được nhà cung cấp thật.
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/ai/settings/save" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <div style="margin-bottom: 0.75rem;">
                <label style="display: block; font-size: 0.8rem; color: var(--ios-text-secondary); margin-bottom: 0.25rem;">
                    Khoá API (<?= htmlspecialchars($aiLabels['map']['provider'][strtolower((string) $provider)] ?? (string) $provider) ?>)
                </label>
                <input type="password" name="api_key" required autocomplete="new-password" placeholder="Nhập khoá API..."
                       style="width: 100%; padding: 0.5rem 0.65rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); background: transparent; color: inherit; font-size: 0.85rem; box-sizing: border-box;">
                <p style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.35rem;">
                    Giá trị được lưu an toàn trong bảng cài đặt. Hệ thống KHÔNG bao giờ hiển thị lại khoá.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="glass-btn" style="white-space: nowrap;">💾 Lưu Khoá</button>
            </div>
        </form>

        <form method="POST" action="/admin/ai/settings/test" style="margin-top: 0.75rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <button type="submit" class="glass-btn" style="white-space: nowrap;">🔌 Kiểm Tra Kết Nối</button>
        </form>
    </div>
</div>

<!-- Phân hệ AI (gộp từ trang riêng /admin/ai/modules) -->
<div class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.75rem;">
        <div>
            <h2 style="font-size: 1rem; font-weight: 700;">Phân Hệ AI</h2>
            <p style="color: var(--ios-text-secondary); font-size: 0.78rem;">
                Danh mục phân hệ do hệ thống quy định sẵn. Quản trị viên chỉ bật/tắt và gán mô hình AI mặc định.
            </p>
        </div>
        <form method="POST" action="/admin/ai/modules/sync" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <button type="submit" class="glass-btn" style="white-space: nowrap;">⟳ Đồng Bộ Model</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>Đăng Ký DB</th>
                    <th>Tên Phân Hệ</th>
                    <th>Khả Năng</th>
                    <th>Bộ Prompt</th>
                    <th>Model Mặc Định</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($moduleRows as $row): ?>
                    <tr>
                        <td style="font-size: 0.82rem; white-space: nowrap;">
                            <?php if ($row['id'] === null): ?>
                                <span style="color: var(--ios-danger); font-weight: 600;">Chưa đăng ký</span>
                            <?php else: ?>
                                <span style="color: var(--ios-success); font-weight: 600;">#<?= (int) $row['id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600; font-size: 0.88rem;"><?= htmlspecialchars((string) $row['label']) ?></td>
                        <td>
                            <span style="background: rgba(0, 122, 255, 0.08); color: var(--ios-blue); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 600;">
                                <?= htmlspecialchars($aiLabels['map']['capability'][$row['capability']] ?? (string) $row['capability']) ?>
                            </span>
                        </td>
                        <td style="font-size: 0.82rem;">
                            <?= htmlspecialchars($aiLabels['prompts'][$row['prompt_key']] ?? (string) $row['prompt_key']) ?>
                        </td>
                        <td style="font-size: 0.82rem;">
                            <?php if ($row['id'] === null): ?>
                                <span style="color: var(--ios-text-secondary);">—</span>
                            <?php else: ?>
                                <form id="frm-default-model-<?= (int) $row['id'] ?>" method="POST" action="/admin/ai/modules/set-default-model" style="margin: 0; display: flex; gap: 0.35rem; align-items: center;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="module_key" value="<?= htmlspecialchars((string) $row['module_key']) ?>">
                                    <select name="default_model_id" style="padding: 0.3rem 0.45rem; border-radius: var(--radius-sm); border: 1px solid var(--ios-border, rgba(255,255,255,0.15)); background: transparent; color: inherit; font-size: 0.8rem; max-width: 13rem; width: 100%; box-sizing: border-box;">
                                        <option value="0">Chưa gán..</option>
                                        <?php foreach (($modelsByModule[(string) $row['module_key']] ?? []) as $model): ?>
                                            <option value="<?= (int) $model['id'] ?>" <?= (int) ($row['default_model_id'] ?? 0) === (int) $model['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) ($model['model_name'] ?: $model['model_key'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($row['id'] !== null): ?>
                                <div class="action-dropdown">
                                    <button type="button" class="action-btn" title="Thao tác">⋮</button>
                                    <div class="action-menu" style="min-width: 175px; white-space: nowrap;">
                                        <button type="submit" form="frm-default-model-<?= (int) $row['id'] ?>" class="action-item" style="font-family: inherit;">
                                            <span>💾</span> Lưu model
                                        </button>
                                        <form method="POST" action="/admin/ai/modules/toggle" style="margin: 0;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                            <input type="hidden" name="module_key" value="<?= htmlspecialchars((string) $row['module_key']) ?>">
                                            <button type="submit" class="action-item" style="font-family: inherit;">
                                                <span><?= $row['db_enabled'] === true ? '○' : '●' ?></span>
                                                <?= $row['db_enabled'] === true ? 'Tắt phân hệ' : 'Bật phân hệ' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 0.78rem; color: var(--ios-text-secondary);">Đồng bộ để quản lý</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (empty($models)): ?>
<div class="glass-card" style="padding: 1rem 1.25rem; margin-top: 1rem; border-left: 4px solid var(--ios-warning, #ff9f0a);">
    <strong style="font-size: 0.88rem;">Chưa có mô hình AI nào trong danh mục.</strong>
    <span style="font-size: 0.83rem; color: var(--ios-text-secondary);">
        Hệ thống KHÔNG tự bịa mô hình Kira. Hãy <a href="/admin/ai#ai-models-catalog" style="color: var(--ios-blue);">đồng bộ từ nhà cung cấp</a>
        (cần API key) hoặc nhập model theo tài liệu chính thức của Kira.
    </span>
</div>
<?php endif; ?>

<!-- Quick access to edit system rules -->
<div class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Nội Quy Hệ Thống</h2>
    <p style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
        Quản trị nhanh các nội quy AI (không phải nội dung sinh). Nhấn vào để chỉnh sửa trực tiếp.
    </p>
    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
        <?php $ruleKeys = ['rules_image','rules_video','rules_video_dubbing','rules_fanpage_content','rules_auto_reply']; ?>
        <?php foreach ($ruleKeys as $rk): ?>
            <a href="/admin/ai/prompts/create?prompt_key=<?= urlencode($rk) ?>" class="glass-btn" style="text-decoration: none; font-size: 0.82rem;">
                <?= htmlspecialchars($aiLabels['prompts'][$rk] ?? $rk) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Thông số kỹ thuật -->
<div class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem;">Thông Số Kỹ Thuật (chỉ xem)</h2>
    <p style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
        Các thông số này do hệ thống quy định trong cấu hình máy chủ — quản trị viên không cần thay đổi.
    </p>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.82rem;">

        <div>
            <div style="font-weight: 700; margin-bottom: 0.35rem;">Cơ Chế Thử Lại</div>
            <?php foreach ($retry as $key => $value): ?>
                <div style="display: flex; justify-content: space-between; gap: 0.5rem; color: var(--ios-text-secondary);">
                    <span><?= htmlspecialchars($cfgLabel((string) $key)) ?></span>
                    <span style="color: var(--ios-text); font-weight: 600; text-align: right; word-break: break-word;"><?= htmlspecialchars($cfgValue($value, (string) $key)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div>
            <div style="font-weight: 700; margin-bottom: 0.35rem;">Hàng Đợi Tác Vụ</div>
            <?php foreach ($task as $key => $value): ?>
                <div style="display: flex; justify-content: space-between; gap: 0.5rem; color: var(--ios-text-secondary);">
                    <span><?= htmlspecialchars($cfgLabel((string) $key)) ?></span>
                    <span style="color: var(--ios-text); font-weight: 600;"><?= htmlspecialchars($cfgValue($value, (string) $key)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div>
            <div style="font-weight: 700; margin-bottom: 0.35rem;">Thư Viện File</div>
            <?php foreach ($assets as $key => $value): ?>
                <div style="display: flex; justify-content: space-between; gap: 0.5rem; color: var(--ios-text-secondary);">
                    <span><?= htmlspecialchars($cfgLabel((string) $key)) ?></span>
                    <span style="color: var(--ios-text); font-weight: 600; text-align: right; word-break: break-all;"><?= htmlspecialchars($cfgValue($value, (string) $key)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
