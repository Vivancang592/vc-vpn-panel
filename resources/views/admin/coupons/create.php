<?php
$pageTitle = "Thêm Mã Giảm Giá Mới - Quản Trị Hệ Thống";
$activeMenu = "coupons";

$currencySymbol = $settings['currency_symbol'] ?? 'đ';
$currencyCode   = $settings['currency'] ?? 'VND';

ob_start();
?>

<div style="margin-bottom: 1.25rem;">
    <h1 style="font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px;">Thêm Mã Giảm Giá Mới</h1>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="glass-card glass-alert" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-left: 4px solid var(--ios-danger); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="alert-close" style="background: none; border: none; color: var(--ios-text-secondary); font-size: 1.25rem; cursor: pointer; padding: 0 0.25rem; line-height: 1;" title="Đóng">&times;</button>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<style>
    .ms-wrap { position: relative; }
    .ms-toggle { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; cursor: pointer; text-align: left; font: inherit; font-size: 0.8rem; color: var(--ios-text); }
    .ms-toggle .ms-summary { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ms-caret { flex-shrink: 0; transition: transform 160ms ease; opacity: 0.6; }
    .ms-toggle[aria-expanded="true"] .ms-caret { transform: rotate(180deg); }
    .ms-menu { position: absolute; z-index: 50; top: calc(100% + 6px); left: 0; right: 0; max-height: 260px; overflow-y: auto; padding: 0.35rem; border: 1px solid var(--glass-border); border-radius: var(--radius-md); background: rgba(8, 13, 22, 0.98); box-shadow: var(--shadow-lg); }
    .ms-menu[hidden] { display: none; }
    .ms-item { display: flex; align-items: center; gap: 0.55rem; padding: 0.55rem 0.6rem; border-radius: var(--radius-sm); font-size: 0.82rem; color: var(--ios-text); cursor: pointer; }
    .ms-item:hover { background: rgba(127, 127, 127, 0.14); }
    .ms-item input { width: 1rem; height: 1rem; margin: 0; accent-color: var(--ios-blue); cursor: pointer; flex-shrink: 0; }
    .ms-empty { padding: 0.6rem 0.65rem; font-size: 0.78rem; color: var(--ios-text-secondary); }
    @media (prefers-color-scheme: dark) { .ms-menu { background: rgba(28, 28, 30, 0.97); } }
</style>

<div class="glass-card" style="padding: 1.75rem; width: 100%;">
    <form method="POST" action="/admin/coupons/create" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mã Giảm Giá (Code) (*)</label>
                <input type="text" name="code" class="glass-input" required placeholder="Nhập mã (ví dụ: TET2027, KM50...)" autofocus style="width: 100%; text-transform: uppercase;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Loại Giảm Giá (*)</label>
                <select name="discount_type" class="glass-input" required style="width: 100%; cursor: pointer;">
                    <option value="percent">Giảm theo phần trăm (%)</option>
                    <option value="fixed">Giảm số tiền cố định (<?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencySymbol) ?>)</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giá Trị Giảm (*)</label>
                <input type="number" name="discount_value" class="glass-input" required placeholder="Nhập % hoặc số tiền..." min="0" step="any" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giới Hạn Lượt Dùng (Tối Đa)</label>
                <input type="number" name="max_uses" class="glass-input" value="0" min="0" placeholder="0 = Không giới hạn" style="width: 100%;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Ngày Hết Hạn</label>
                <input type="datetime-local" name="expires_at" class="glass-input" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái</label>
                <select name="status" class="glass-input" style="width: 100%; cursor: pointer;">
                    <option value="active" selected>Active (Hoạt động)</option>
                    <option value="inactive">Inactive (Khóa)</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gán Riêng Cho User</label>
                <div class="ms-wrap" data-empty="Công khai — mọi user">
                    <button type="button" class="glass-input ms-toggle" style="width: 100%;" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-summary">Công khai — mọi user</span>
                        <span class="ms-caret" aria-hidden="true">▾</span>
                    </button>
                    <div class="ms-menu" hidden>
                        <?php if (empty($users)): ?>
                            <div class="ms-empty">Không có user nào</div>
                        <?php else: foreach (($users ?? []) as $u): ?>
                            <label class="ms-item">
                                <input type="checkbox" name="assigned_user_ids[]" value="<?= (int)$u['id'] ?>" data-label="@<?= htmlspecialchars($u['username']) ?>">
                                <span>@<?= htmlspecialchars($u['username']) ?> (#<?= (int)$u['id'] ?>)</span>
                            </label>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.35rem;">Bỏ trống = công khai (mọi user dùng được). Chọn = chỉ user đó. Nhấn vào ô để mở danh sách.</div>
            </div>

            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gói Áp Dụng</label>
                <?php
                $plansList = array_filter($plans ?? [], static function ($p) {
                    return ($p['status'] ?? '') === 'active'
                        && ($p['stock_quantity'] === null || (int)$p['stock_quantity'] > 0);
                });
                ?>
                <div class="ms-wrap" data-empty="Mọi gói">
                    <button type="button" class="glass-input ms-toggle" style="width: 100%;" aria-haspopup="true" aria-expanded="false">
                        <span class="ms-summary">Mọi gói</span>
                        <span class="ms-caret" aria-hidden="true">▾</span>
                    </button>
                    <div class="ms-menu" hidden>
                        <?php if (empty($plansList)): ?>
                            <div class="ms-empty">Không có gói còn hàng nào</div>
                        <?php else: foreach ($plansList as $p): ?>
                            <label class="ms-item">
                                <input type="checkbox" name="plan_ids[]" value="<?= (int)$p['id'] ?>" data-label="<?= htmlspecialchars($p['name']) ?>">
                                <span><?= htmlspecialchars($p['name']) ?> — <?= number_format((float)$p['price'], 0, '.', ',') ?>đ</span>
                            </label>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div style="font-size: 0.75rem; color: var(--ios-text-secondary); margin-top: 0.35rem;">Bỏ trống = áp dụng mọi gói. Chỉ hiện gói còn hàng. Nhấn vào ô để mở danh sách.</div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
            <button type="submit" class="glass-btn" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">Tạo</button>
        </div>
    </form>
</div>

<script>
    (function () {
        function closeAll(except) {
            document.querySelectorAll('.ms-menu').forEach(function (m) {
                if (m !== except) m.hidden = true;
            });
            document.querySelectorAll('.ms-toggle').forEach(function (t) {
                if (!except || t.nextElementSibling !== except) t.setAttribute('aria-expanded', 'false');
            });
        }
        function render(wrap) {
            var picked = wrap.querySelectorAll('input[type="checkbox"]:checked');
            var summary = wrap.querySelector('.ms-summary');
            if (picked.length === 0) {
                summary.textContent = wrap.getAttribute('data-empty');
            } else if (picked.length === 1) {
                summary.textContent = picked[0].getAttribute('data-label');
            } else {
                summary.textContent = 'Đã chọn ' + picked.length;
            }
        }
        document.querySelectorAll('.ms-wrap').forEach(function (wrap) {
            var toggle = wrap.querySelector('.ms-toggle');
            var menu = wrap.querySelector('.ms-menu');
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var opening = menu.hidden;
                closeAll(opening ? menu : null);
                menu.hidden = !opening;
                toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            });
            menu.addEventListener('click', function (e) { e.stopPropagation(); });
            menu.addEventListener('change', function () { render(wrap); });
            render(wrap);
        });
        document.addEventListener('click', function () { closeAll(null); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(null); } });
    })();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>