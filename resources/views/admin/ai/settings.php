<?php
$pageTitle = 'Cấu Hình AI - Quản Trị Hệ Thống';
$activeMenu = 'ai-settings';

ob_start();

require __DIR__ . '/_flash.php';

/** Nhãn tiếng Việt cho các khoá cấu hình kỹ thuật (không hiển thị key thô). */
$cfgLabels = [
    // Cơ chế thử lại
    'enabled'              => 'Tự thử lại khi lỗi',
    'max_attempts'         => 'Thử lại tối đa',
    'base_delay_ms'        => 'Chờ trước lần thử lại đầu',
    'max_delay_ms'         => 'Chờ tối đa giữa các lần thử',
    'multiplier'           => 'Mức tăng thời gian chờ',
    'retryable_errors'     => 'Lỗi được thử lại',
    'non_retryable_errors' => 'Lỗi không thử lại',
    // Hàng đợi tác vụ
    'default_max_retries'  => 'Làm lại bài khi lỗi tạm thời',
    'lock_stale_seconds'   => 'Xử lý lại bài bị kẹt sau (giây)',
    'default_priority'     => 'Mức ưu tiên xử lý',
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

<div style="margin-bottom: 1rem; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Cấu Hình AI</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">
            Cấu hình nhà cung cấp AI, phân hệ và giới hạn chatbot. Các thông số hạ tầng còn lại do hệ thống quy định và chỉ xem.
        </p>
    </div>
    <a href="/admin/ai/prompts/create" class="glass-btn" style="text-decoration: none; white-space: nowrap;">Nội Quy</a>
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

        <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
        <form method="POST" action="/admin/ai/settings/save" autocomplete="off" style="flex: 1; min-width: 0;">
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
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="submit" class="glass-btn" style="white-space: nowrap;">Lưu</button>
            </div>
        </form>

        <form method="POST" action="/admin/ai/settings/test" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <button type="submit" class="glass-btn" style="white-space: nowrap;">Test</button>
        </form>
        </div>
    </div>
</div>

<!-- Cấu hình chatbot -->
<div id="chatbot-settings" class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <div style="margin-bottom: 1rem;">
        <h2 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.35rem;">Cấu Hình Chatbot</h2>
        <p style="color: var(--ios-text-secondary); font-size: 0.8rem; margin: 0;">Điều chỉnh tốc độ và giới hạn trả lời cho chat Website. Hệ thống vẫn kiểm tra lại các phạm vi an toàn khi lưu.</p>
    </div>
    <form method="POST" action="/admin/ai/settings/chatbot">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.9rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem;">Trạng Thái Chatbot</label>
                <select name="ai_chatbot_enabled" class="glass-input" style="width: 100%;">
                    <option value="1" <?= ($chatbotSettings['ai_chatbot_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Đang bật</option>
                    <option value="0" <?= ($chatbotSettings['ai_chatbot_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Tạm tắt</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem;">Chờ Giữa Hai Tin (giây)</label>
                <input type="number" name="ai_cooldown_seconds" class="glass-input" value="<?= htmlspecialchars($chatbotSettings['ai_cooldown_seconds'] ?? '1.5') ?>" min="0.5" max="6" step="0.1" required style="width: 100%;">
                <small style="color: var(--ios-text-secondary);">Từ 0,5 đến 6 giây.</small>
            </div>
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem;">Tối Đa Mỗi Phút</label>
                <input type="number" name="ai_rate_limit_per_minute" class="glass-input" value="<?= htmlspecialchars($chatbotSettings['ai_rate_limit_per_minute'] ?? '8') ?>" min="3" max="30" step="1" required style="width: 100%;">
                <small style="color: var(--ios-text-secondary);">Từ 3 đến 30 tin/phút.</small>
            </div>
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem;">Chống Gửi Trùng (giây)</label>
                <input type="number" name="ai_duplicate_window_seconds" class="glass-input" value="<?= htmlspecialchars($chatbotSettings['ai_duplicate_window_seconds'] ?? '4') ?>" min="2" max="20" step="1" required style="width: 100%;">
                <small style="color: var(--ios-text-secondary);">Từ 2 đến 20 giây.</small>
            </div>
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem;">Độ Dài Phản Hồi AI (tokens)</label>
                <input type="number" name="ai_max_output_tokens" class="glass-input" value="<?= htmlspecialchars($chatbotSettings['ai_max_output_tokens'] ?? '700') ?>" min="100" max="2000" step="50" required style="width: 100%;">
                <small style="color: var(--ios-text-secondary);">Từ 100 đến 2.000 tokens.</small>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
            <button type="submit" class="glass-btn" style="background: var(--ios-blue); color: var(--soc-ink); border: 0; font-weight: 600;">Lưu</button>
        </div>
    </form>
</div>

<?php if (empty($models)): ?>
<div class="glass-card" style="padding: 1rem 1.25rem; margin-top: 1rem; border-left: 4px solid var(--ios-warning, #ff9f0a);">
    <strong style="font-size: 0.88rem;">Chưa có mô hình AI nào trong danh mục.</strong>
    <span style="font-size: 0.83rem; color: var(--ios-text-secondary);">
        Hệ thống KHÔNG tự bịa mô hình Kira. Hãy <a href="/admin/ai#ai-models-catalog" style="color: var(--ios-blue);">Sync</a>
        (cần API key) hoặc nhập model theo tài liệu chính thức của Kira.
    </span>
</div>
<?php endif; ?>

<!-- Thông số vận hành thực tế — CSS gom trong public/assets/css/admin.css -->
<div id="ai-operational-settings" class="glass-card" style="padding: 1.25rem; margin-top: 1rem; width: 100%; box-sizing: border-box;">
    <h2 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem;">Cách Hệ Thống AI Hoạt Động</h2>
    <p style="font-size: 0.78rem; color: var(--ios-text-secondary); margin-bottom: 0.75rem;">
        Đây là các thiết lập hệ thống đang được dùng. Chúng chỉ để xem; giới hạn chatbot được chỉnh ở phần phía trên.
    </p>
    <div class="ai-operational-grid">

        <div class="ai-operational-retry">
            <div style="font-weight: 700; margin-bottom: 0.25rem;">Khi AI Gặp Lỗi Tạm Thời</div>
            <p style="font-size: 0.76rem; color: var(--ios-text-secondary); margin: 0 0 0.55rem;">Hệ thống sẽ chờ rồi tự thử lại khi nhà cung cấp AI bị lỗi tạm thời.</p>
            <div style="border-top: 1px solid var(--ios-border, rgba(128,128,128,.18));">
            <?php foreach ($retry as $key => $value): ?>
                <div class="ai-retry-row">
                    <span><?= htmlspecialchars($cfgLabel((string) $key)) ?></span>
                    <strong><?= htmlspecialchars($cfgValue($value, (string) $key)) ?></strong>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="ai-operational-media" style="min-width: 0;">
            <div style="font-weight: 700; margin-bottom: 0.25rem;">Tạo Ảnh</div>
            <p style="font-size: 0.76rem; color: var(--ios-text-secondary); margin: 0 0 0.55rem;">Chức năng này chạy ngay khi bấm nút, không phải chờ trong danh sách viết bài.</p>
            <div style="border-top: 1px solid var(--ios-border, rgba(128,128,128,.18));">
                <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 0.75rem; padding: 0.48rem 0; border-bottom: 1px solid var(--ios-border, rgba(128,128,128,.18));">
                    <span style="color: var(--ios-text-secondary);">Tạo Ảnh</span>
                    <strong style="color: var(--ios-text); text-align: right;">Có kết quả ngay</strong>
                </div>
            </div>
        </div>

        <div class="ai-operational-fanpage" style="min-width: 0;">
            <div style="font-weight: 700; margin-bottom: 0.25rem;">Danh Sách Viết Bài Fanpage</div>
            <p style="font-size: 0.76rem; color: var(--ios-text-secondary); margin: 0 0 0.55rem;">Chỉ dùng khi viết bài Fanpage: AI hoàn thành từng bài rồi mới chuyển sang bài tiếp theo. Ảnh không vào danh sách này.</p>
            <div style="border-top: 1px solid var(--ios-border, rgba(128,128,128,.18));">
                <?php foreach (['default_max_retries', 'lock_stale_seconds', 'default_priority'] as $key): ?>
                    <?php if (!array_key_exists($key, $task)) continue; ?>
                    <?php $value = $task[$key]; ?>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 0.75rem; padding: 0.48rem 0; border-bottom: 1px solid var(--ios-border, rgba(128,128,128,.18));">
                        <span style="color: var(--ios-text-secondary); line-height: 1.35;">
                            <?= htmlspecialchars($cfgLabel($key)) ?>
                        </span>
                        <strong style="color: var(--ios-text); text-align: right; white-space: nowrap;"><?= htmlspecialchars($cfgValue($value, $key)) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ai-operational-storage">
            <div style="font-weight: 700; margin-bottom: 0.25rem;">Nơi Lưu Ảnh</div>
            <p style="font-size: 0.76rem; color: var(--ios-text-secondary); margin: 0 0 0.55rem;">File được lưu trên máy chủ; cơ sở dữ liệu chỉ lưu thông tin để tìm lại file.</p>
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
