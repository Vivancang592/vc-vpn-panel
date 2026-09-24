<?php
$pageTitle = "Cài Đặt Hệ Thống - Quản Trị Hệ Thống";
$activeMenu = "settings";

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

<div style="margin-bottom: 1.5rem; width: 100%; box-sizing: border-box;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; word-break: break-word;">Cài Đặt Hệ Thống</h1>
        <p style="color: var(--ios-text-secondary); font-size: 0.85rem;">Quản lý toàn diện các thông số của hệ thống VPN</p>
    </div>
</div>

<!-- Nút chuyển Tab -->
<div class="settings-tabs">
    <button class="settings-tab-btn active" data-target="tab-general">⚙️ Cấu Hình Chung</button>
    <button class="settings-tab-btn" data-target="tab-finance">💰 Tài Chính & Ưu Đãi</button>
    <button class="settings-tab-btn" data-target="tab-trial">🎁 Dùng Thử</button>
    <button class="settings-tab-btn" data-target="tab-bank">🏦 Đa Cổng Thanh Toán</button>
    <button class="settings-tab-btn" data-target="tab-email">📧 Cấu Hình Email</button>
    <button class="settings-tab-btn" data-target="tab-ai">🤖 AI Chatbot & Fanpage</button>
</div>

<div class="settings-content">
    
    <!-- TAB 1: CẤU HÌNH CHUNG -->
    <div id="tab-general" class="settings-tab-pane active">
        <form method="POST" action="/admin/settings/save" class="glass-card settings-form" style="padding: 1.5rem; width: 100%; display: flex; flex-direction: column; gap: 1.25rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; color: var(--ios-blue);">
                Cấu Hình Website & Thương Hiệu
            </h2>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Tên hiển thị trên tiêu đề, thanh điều hướng và footer." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tên Trang Web (Site Title)</label>
                    <input type="text" name="settings[site_title]" class="glass-input" value="<?= htmlspecialchars($settings['site_title'] ?? 'VC VPN PANEL') ?>" style="width: 100%;">
                </div>  

                <div>
                    <label data-hint="Địa chỉ nhận các yêu cầu hỗ trợ từ người dùng." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Email Liên Hệ Hỗ Trợ</label>
                    <input type="email" name="settings[contact_email]" class="glass-input" value="<?= htmlspecialchars($settings['contact_email'] ?? 'support@vpn2s.linksub24h.com') ?>" style="width: 100%;">
                </div>
            </div>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Liên kết Facebook dùng để hỗ trợ khách hàng." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Kênh Fanpage Hỗ Trợ</label>
                    <input type="text" name="settings[fanpage_url]" class="glass-input" value="<?= htmlspecialchars($settings['fanpage_url'] ?? '') ?>" placeholder="https://facebook.com/..." style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Liên kết tới kênh YouTube chính thức của website." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Kênh Youtube</label>
                    <input type="text" name="settings[youtube_url]" class="glass-input" value="<?= htmlspecialchars($settings['youtube_url'] ?? '') ?>" placeholder="https://youtube.com/..." style="width: 100%;">
                </div>
            </div>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Đường dẫn Zalo hoặc số điện thoại để người dùng liên hệ." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Zalo / SĐT Zalo</label>
                    <input type="text" name="settings[zalo_url]" class="glass-input" value="<?= htmlspecialchars($settings['zalo_url'] ?? '') ?>" placeholder="https://zalo.me/... hoặc Số điện thoại" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="ID WeChat hiển thị trong phần liên hệ hỗ trợ." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">WeChat ID</label>
                    <input type="text" name="settings[wechat_id]" class="glass-input" value="<?= htmlspecialchars($settings['wechat_id'] ?? '') ?>" placeholder="Nhập WeChat ID..." style="width: 100%;">
                </div>
            </div>

            <div class="settings-field-row">
                <label data-hint="Mô tả ngắn của website, có thể được dùng cho công cụ tìm kiếm." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mô Tả Trang Web (Meta Description)</label>
                <textarea name="settings[site_description]" rows="2" class="glass-input" style="width: 100%; resize: vertical;"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="glass-btn" style="padding: 0.75rem 2rem; background: var(--ios-blue); color: #fff; border: none; font-weight: 600; cursor: pointer;">
                    💾 Lưu Cấu Hình Chung
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 2: TÀI CHÍNH & ƯU ĐÃI -->
    <div id="tab-finance" class="settings-tab-pane">
        <form method="POST" action="/admin/settings/save" class="glass-card settings-form" style="padding: 1.5rem; width: 100%; display: flex; flex-direction: column; gap: 1.25rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; color: var(--ios-success);">
                Cấu Hình Tài Chính & Ưu Đãi Giới Thiệu
            </h2>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Mã ISO tiền tệ chuẩn của hệ thống (VND)." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mã Tiền Tệ Mặc Định</label>
                    <input type="text" name="settings[currency]" class="glass-input" value="VND" readonly style="width: 100%; background: rgba(0,0,0,0.1); color: var(--ios-text-secondary); cursor: not-allowed;">
                </div>

                <div>
                    <label data-hint="Ký hiệu hiển thị kèm theo giá trị tiền tệ." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Ký Hiệu Tiền Tệ</label>
                    <input type="text" name="settings[currency_symbol]" class="glass-input" value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'đ') ?>" placeholder="đ" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Chọn vị trí của ký hiệu khi hiển thị số tiền." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Vị Trí Ký Hiệu Tiền Tệ</label>
                    <select name="settings[currency_position]" class="glass-input" style="width: 100%; cursor: pointer;">
                        <option value="right" <?= ($settings['currency_position'] ?? 'right') === 'right' ? 'selected' : '' ?>>Bên phải (VD: 100.000 đ)</option>
                        <option value="left" <?= ($settings['currency_position'] ?? 'right') === 'left' ? 'selected' : '' ?>>Bên trái (VD: đ 100.000)</option>
                    </select>
                </div>

                <div>
                    <label data-hint="Số chữ số thập phân cho VND (Mặc định 0)." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Số Chữ Số Thập Phân</label>
                    <input type="number" name="settings[currency_decimals]" class="glass-input" value="<?= htmlspecialchars($settings['currency_decimals'] ?? '0') ?>" min="0" max="4" step="1" style="width: 100%;" placeholder="0 cho VND">
                </div>

                <div>
                    <label data-hint="Số tiền nạp tối thiểu vào ví (VND)." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Nạp Tối Thiểu Vào Ví (VND)</label>
                    <input type="number" name="settings[min_deposit_amount]" class="glass-input" value="<?= htmlspecialchars($settings['min_deposit_amount'] ?? $settings['min_deposit'] ?? '10000') ?>" min="1000" step="1000" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Phần trăm hoa hồng cho đơn hàng giới thiệu thành công." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tỷ Lệ Hoa Hồng Giới Thiệu (%)</label>
                    <input type="number" name="settings[commission_rate]" class="glass-input" value="<?= htmlspecialchars($settings['commission_rate'] ?? '30') ?>" min="0" max="100" step="0.1" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Số dư tặng cho tài khoản đăng ký bằng mã giới thiệu." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Thưởng Khi Đăng Ký Có Mã Giới Thiệu (VND)</label>
                    <input type="number" name="settings[referral_bonus]" class="glass-input" value="<?= htmlspecialchars($settings['referral_bonus'] ?? '10000') ?>" min="0" step="any" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Giá trị nhỏ nhất người dùng được phép nạp vào ví." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Số Tiền Nạp Tối Thiểu</label>
                    <input type="number" name="settings[min_deposit]" class="glass-input" value="<?= htmlspecialchars($settings['min_deposit'] ?? '10000') ?>" min="0" step="any" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Đơn mua gói chưa thanh toán sau khoảng thời gian này sẽ tự động hủy khi cron chạy." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Thời Gian Tự Hủy Đơn Chờ (phút)</label>
                    <input type="number" name="settings[pending_order_timeout_minutes]" class="glass-input" value="<?= htmlspecialchars($settings['pending_order_timeout_minutes'] ?? '30') ?>" min="1" max="43200" step="1" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Gói đã hết hạn nhưng không được gia hạn sau số ngày này sẽ tự động hủy và xóa khỏi node VPN khi cron chạy." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giữ Gói Hết Hạn Trước Khi Hủy (ngày)</label>
                    <input type="number" name="settings[expired_subscription_cancel_after_days]" class="glass-input" value="<?= htmlspecialchars($settings['expired_subscription_cancel_after_days'] ?? '30') ?>" min="1" max="3650" step="1" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Giá trị nhỏ nhất người dùng được phép yêu cầu rút." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Số Tiền Rút Tối Thiểu</label>
                    <input type="number" name="settings[min_withdrawal]" class="glass-input" value="<?= htmlspecialchars($settings['min_withdrawal'] ?? '50000') ?>" min="0" step="any" style="width: 100%;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="glass-btn" style="padding: 0.75rem 2rem; background: var(--ios-success); color: #fff; border: none; font-weight: 600; cursor: pointer;">
                    💾 Lưu Tài Chính & Ưu Đãi
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 3: DÙNG THỬ -->
    <div id="tab-trial" class="settings-tab-pane">
        <form method="POST" action="/admin/settings/save" class="glass-card settings-form" style="padding: 1.5rem; width: 100%; display: flex; flex-direction: column; gap: 1.25rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; color: #af52de;">
                🎁 Cấu Hình Gói Dùng Thử Cho Tài Khoản Mới
            </h2>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Bật để tự tạo gói dùng thử cho tài khoản mới." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái Dùng Thử</label>
                    <select name="settings[trial_enabled]" class="glass-input" style="width: 100%; cursor: pointer;">
                        <option value="1" <?= ($settings['trial_enabled'] ?? '0') == '1' ? 'selected' : '' ?>>Bật</option>
                        <option value="0" <?= ($settings['trial_enabled'] ?? '0') == '0' ? 'selected' : '' ?>>Tắt</option>
                    </select>
                </div>

                <div>
                    <label data-hint="Gói cước được cấp khi người dùng đăng ký lần đầu." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gói Cước Dùng Thử (Plan)</label>
                    <select name="settings[trial_plan_id]" class="glass-input" style="width: 100%; cursor: pointer;">
                        <option value="">-- Chọn Gói Cước --</option>
                        <?php foreach (($plans ?? []) as $plan): ?>
                            <option value="<?= $plan['id'] ?>" <?= ($settings['trial_plan_id'] ?? '') == $plan['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($plan['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label data-hint="Số ngày gói dùng thử còn hiệu lực sau khi kích hoạt." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Thời Gian Dùng Thử (Ngày)</label>
                    <input type="number" name="settings[trial_duration_days]" class="glass-input" value="<?= htmlspecialchars($settings['trial_duration_days'] ?? '3') ?>" min="1" step="1" style="width: 100%;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="glass-btn" style="padding: 0.75rem 2rem; background: #af52de; color: #fff; border: none; font-weight: 600; cursor: pointer;">
                    💾 Lưu Cấu Hình Dùng Thử
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 4: THÔNG TIN NGÂN HÀNG & ĐA CỔNG (QR) -->
    <div id="tab-bank" class="settings-tab-pane">
        <form method="POST" action="/admin/settings/save" enctype="multipart/form-data" class="glass-card settings-form" style="padding: 1.5rem; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; color: #ff9500;">
                🏦 Cấu Hình Đa Cổng Thanh Toán
            </h2>

            <div class="settings-payment-list" style="display: flex; flex-direction: column; gap: 1rem; width: 100%; overflow-x: auto;">
                <!-- Dòng 1: Ngân Hàng VN -->
                <div class="settings-payment-gateway" data-title="Ngân hàng Việt Nam" data-hint="Thiết lập tài khoản nhận thanh toán qua VietQR." style="display: grid; grid-template-columns: 120px 1fr 1fr 1fr; gap: 1rem; align-items: end; background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px; border: 1px solid var(--glass-border); min-width: 800px;">
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Trạng Thái</label>
                        <select name="settings[enable_vietqr]" class="glass-input" style="width: 100%; cursor: pointer;">
                            <option value="1" <?= ($settings['enable_vietqr'] ?? '1') == '1' ? 'selected' : '' ?>>Bật</option>
                            <option value="0" <?= ($settings['enable_vietqr'] ?? '1') == '0' ? 'selected' : '' ?>>Tắt</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tên Ngân Hàng (Mã BIN)</label>
                        <input type="text" name="settings[bank_name]" class="glass-input" value="<?= htmlspecialchars($settings['bank_name'] ?? '') ?>" placeholder="VD: MB, VCB..." style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Số Tài Khoản</label>
                        <input type="text" name="settings[bank_account_number]" class="glass-input" value="<?= htmlspecialchars($settings['bank_account_number'] ?? '') ?>" placeholder="Nhập số tài khoản" style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tên Chủ Tài Khoản</label>
                        <input type="text" name="settings[bank_account_name]" class="glass-input" value="<?= htmlspecialchars($settings['bank_account_name'] ?? '') ?>" placeholder="VD: NGUYEN VAN A" style="width: 100%; text-transform: uppercase;">
                    </div>
                </div>

                <!-- Cấu Hình API Key Webhook / SePay -->
                <div class="settings-payment-gateway" data-title="API Key Webhook / SePay" data-hint="Khóa bảo mật xác thực Webhook tự động cho các cổng thanh toán." style="display: grid; grid-template-columns: 1fr; gap: 1rem; align-items: end; background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px; border: 1px solid var(--glass-border); min-width: 800px;">
                    <div>
                        <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">API Key / Secret Token Webhook (SePay / MacroDroid)</label>
                        <div style="display: flex; gap: 0.4rem;">
                            <input type="text" id="sepay_api_key_input" name="settings[sepay_api_key]" class="glass-input" value="<?= htmlspecialchars($settings['sepay_api_key'] ?? $settings['webhook_api_key'] ?? $settings['macrodroid_secret'] ?? '') ?>" placeholder="API Key / Secret Webhook" style="flex: 1; min-width: 0;">
                            <button type="button" class="glass-btn" onclick="generateRandomApiKey('sepay_api_key_input')" title="Tạo key ngẫu nhiên" style="padding: 0 0.75rem; white-space: nowrap; cursor: pointer; background: rgba(255,255,255,0.08);">
                                🎲
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Dòng cuối: Cú Pháp Nạp / Thanh Toán -->
                <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; align-items: end; border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                    <div>
                        <label data-hint="Hệ thống tự nối ID giao dịch phía sau, ví dụ NAPTIEN01." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tiền Tố Nạp Tiền</label>
                        <input type="text" name="settings[bank_transfer_syntax]" class="glass-input" value="<?= htmlspecialchars($settings['bank_transfer_syntax'] ?? 'NAPTIEN') ?>" placeholder="VD: NAPTIEN" style="width: 100%;">
                    </div>
                    <div>
                        <label data-hint="Hệ thống tự nối ID đơn hàng phía sau, ví dụ THANHTOAN01." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tiền Tố Thanh Toán Đơn</label>
                        <input type="text" name="settings[order_transfer_syntax]" class="glass-input" value="<?= htmlspecialchars($settings['order_transfer_syntax'] ?? 'THANHTOAN') ?>" placeholder="VD: THANHTOAN" style="width: 100%;">
                    </div>
                    <div>
                        <label data-hint="Hệ thống tự nối ID giao dịch phía sau, ví dụ GAHAN01." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tiền Tố Gia Hạn</label>
                        <input type="text" name="settings[renewal_transfer_syntax]" class="glass-input" value="<?= htmlspecialchars($settings['renewal_transfer_syntax'] ?? 'GAHAN') ?>" placeholder="VD: GAHAN" style="width: 100%;">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="glass-btn" style="padding: 0.75rem 2rem; background: #ff9500; color: #fff; border: none; font-weight: 600; cursor: pointer;">
                    💾 Lưu Cấu Hình Đa Cổng
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 5: CẤU HÌNH EMAIL -->
    <div id="tab-email" class="settings-tab-pane">
        <form method="POST" action="/admin/settings/save" class="glass-card settings-form" style="padding: 1.5rem; width: 100%; display: flex; flex-direction: column; gap: 1.25rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; color: var(--ios-danger);">
                Cấu Hình Máy Chủ Gửi Email (SMTP)
            </h2>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Tên miền hoặc địa chỉ máy chủ gửi email." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Máy Chủ SMTP (Host)</label>
                    <input type="text" name="settings[smtp_host]" class="glass-input" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="VD: smtp.gmail.com" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Cổng kết nối SMTP, thường là 465 hoặc 587." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Cổng (Port)</label>
                    <input type="number" name="settings[smtp_port]" class="glass-input" value="<?= htmlspecialchars($settings['smtp_port'] ?? '465') ?>" placeholder="VD: 465 hoặc 587" style="width: 100%;">
                </div>
                
                <div>
                    <label data-hint="Cơ chế bảo mật kết nối phù hợp với máy chủ SMTP." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Phương Thức Mã Hóa (Encryption)</label>
                    <select name="settings[smtp_encryption]" class="glass-input" style="width: 100%;">
                        <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Tài khoản được phép xác thực với máy chủ SMTP." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tài Khoản Đăng Nhập (Username)</label>
                    <input type="text" name="settings[smtp_username]" class="glass-input" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>" placeholder="Email của bạn" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Dùng App Password nếu nhà cung cấp email yêu cầu." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Mật Khẩu / App Password</label>
                    <input type="password" name="settings[smtp_password]" class="glass-input" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>" placeholder="Mật khẩu ứng dụng" style="width: 100%;">
                </div>
            </div>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label data-hint="Địa chỉ email hiển thị là người gửi thư hệ thống." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Địa Chỉ Gửi (Mail From Address)</label>
                    <input type="email" name="settings[mail_from_address]" class="glass-input" value="<?= htmlspecialchars($settings['mail_from_address'] ?? '') ?>" placeholder="no-reply@domain.com" style="width: 100%;">
                </div>

                <div>
                    <label data-hint="Tên hiển thị ở hộp thư đến của người nhận." style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Tên Người Gửi (Mail From Name)</label>
                    <input type="text" name="settings[mail_from_name]" class="glass-input" value="<?= htmlspecialchars($settings['mail_from_name'] ?? 'VC VPN') ?>" placeholder="VD: Hệ thống VC VPN" style="width: 100%;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="glass-btn" style="padding: 0.75rem 2rem; background: var(--ios-danger); color: #fff; border: none; font-weight: 600; cursor: pointer;">
                    💾 Lưu Cấu Hình Email
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 6: AI CHATBOT & FANPAGE -->
    <div id="tab-ai" class="settings-tab-pane">
        <form method="POST" action="/admin/settings/save" class="glass-card settings-form" style="padding: 1.5rem; width: 100%; display: flex; flex-direction: column; gap: 1.25rem;">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; color: #0a84ff;">
                Cấu Hình Trợ Lý AI Và Fanpage
            </h2>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Bật chatbot website</label>
                    <select name="settings[ai_chatbot_enabled]" class="glass-input" style="width: 100%;">
                        <option value="1" <?= ($settings['ai_chatbot_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Bật</option>
                        <option value="0" <?= ($settings['ai_chatbot_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Tắt</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Nhà cung cấp AI mặc định</label>
                    <select name="settings[ai_provider]" class="glass-input" style="width: 100%;">
                        <option value="openai" <?= ($settings['ai_provider'] ?? 'openai') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                        <option value="gemini" <?= ($settings['ai_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">OpenAI model</label>
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: center;">
                        <select id="openai-model-select" name="settings[ai_openai_model]" class="glass-input" style="width: 100%;">
                            <option value="<?= htmlspecialchars($settings['ai_openai_model'] ?? 'gpt-4o-mini') ?>" selected>
                                <?= htmlspecialchars($settings['ai_openai_model'] ?? 'gpt-4o-mini') ?>
                            </option>
                        </select>
                        <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                            <button type="button" id="check-openai-connection" class="glass-btn" style="padding: 0.55rem 0.9rem; white-space: nowrap; cursor: pointer;">Kiểm tra kết nối</button>
                            <button type="button" id="load-openai-models" class="glass-btn" style="padding: 0.55rem 0.9rem; white-space: nowrap; cursor: pointer;">Tải model</button>
                        </div>
                    </div>
                    <div id="openai-model-status" style="margin-top: 0.35rem; font-size: 0.78rem; color: var(--ios-text-secondary);"></div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gemini model</label>
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: center;">
                        <select id="gemini-model-select" name="settings[ai_gemini_model]" class="glass-input" style="width: 100%;">
                            <option value="<?= htmlspecialchars($settings['ai_gemini_model'] ?? 'gemini-1.5-flash') ?>" selected>
                                <?= htmlspecialchars($settings['ai_gemini_model'] ?? 'gemini-1.5-flash') ?>
                            </option>
                        </select>
                        <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                            <button type="button" id="check-gemini-connection" class="glass-btn" style="padding: 0.55rem 0.9rem; white-space: nowrap; cursor: pointer;">Kiểm tra kết nối</button>
                            <button type="button" id="load-gemini-models" class="glass-btn" style="padding: 0.55rem 0.9rem; white-space: nowrap; cursor: pointer;">Tải model</button>
                        </div>
                    </div>
                    <div id="gemini-model-status" style="margin-top: 0.35rem; font-size: 0.78rem; color: var(--ios-text-secondary);"></div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giới hạn output tokens</label>
                    <input type="number" name="settings[ai_max_output_tokens]" class="glass-input" value="<?= htmlspecialchars($settings['ai_max_output_tokens'] ?? '500') ?>" min="120" max="900" step="10" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Cooldown mỗi tin (giây)</label>
                    <input type="number" name="settings[ai_cooldown_seconds]" class="glass-input" value="<?= htmlspecialchars($settings['ai_cooldown_seconds'] ?? '1.5') ?>" min="0.5" max="6" step="0.1" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Giới hạn request mỗi phút</label>
                    <input type="number" name="settings[ai_rate_limit_per_minute]" class="glass-input" value="<?= htmlspecialchars($settings['ai_rate_limit_per_minute'] ?? '8') ?>" min="3" max="30" step="1" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Cửa sổ chặn tin trùng (giây)</label>
                    <input type="number" name="settings[ai_duplicate_window_seconds]" class="glass-input" value="<?= htmlspecialchars($settings['ai_duplicate_window_seconds'] ?? '4') ?>" min="2" max="20" step="1" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Thời gian cache AI (phút)</label>
                    <input type="number" name="settings[ai_cache_ttl_minutes]" class="glass-input" value="<?= htmlspecialchars($settings['ai_cache_ttl_minutes'] ?? '60') ?>" min="1" max="1440" step="1" style="width: 100%;">
                </div>
            </div>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem;">
                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">OpenAI API key</label>
                    <input type="password" name="settings[ai_openai_api_key]" class="glass-input" value="<?= !empty($settings['ai_openai_api_key']) ? '************' : '' ?>" placeholder="sk-..." style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Gemini API key</label>
                    <input type="password" name="settings[ai_gemini_api_key]" class="glass-input" value="<?= !empty($settings['ai_gemini_api_key']) ? '************' : '' ?>" placeholder="AIza..." style="width: 100%;">
                </div>
            </div>

            <div class="settings-field-row">
                <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Prompt hệ thống cho bot</label>
                <textarea name="settings[ai_system_prompt]" rows="4" class="glass-input" style="width: 100%; resize: vertical;"><?= htmlspecialchars($settings['ai_system_prompt'] ?? '') ?></textarea>
            </div>

            <h3 style="font-size: 1rem; font-weight: 700; margin-top: 0.25rem; color: #34c759;">Facebook Fanpage Webhook</h3>

            <div class="settings-field-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Verify token</label>
                    <input type="password" name="settings[fanpage_verify_token]" class="glass-input" value="<?= !empty($settings['fanpage_verify_token']) ? '************' : '' ?>" placeholder="token xác thực webhook" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">App secret (verify signature)</label>
                    <input type="password" name="settings[fanpage_app_secret]" class="glass-input" value="<?= !empty($settings['fanpage_app_secret']) ? '************' : '' ?>" placeholder="Meta app secret" style="width: 100%;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Page access token</label>
                    <input type="password" name="settings[fanpage_page_access_token]" class="glass-input" value="<?= !empty($settings['fanpage_page_access_token']) ? '************' : '' ?>" placeholder="EAAG..." style="width: 100%;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="glass-btn" style="padding: 0.75rem 2rem; background: #0a84ff; color: #fff; border: none; font-weight: 600; cursor: pointer;">
                    💾 Lưu Cấu Hình AI & Fanpage
                </button>
            </div>
        </form>
    </div>

</div>

<script>
function generateRandomApiKey(inputId) {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    var length = 32;
    var result = '';
    var cryptoObj = window.crypto || window.msCrypto;
    if (cryptoObj && cryptoObj.getRandomValues) {
        var values = new Uint32Array(length);
        cryptoObj.getRandomValues(values);
        for (var i = 0; i < length; i++) {
            result += chars[values[i] % chars.length];
        }
    } else {
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
    }
    var input = document.getElementById(inputId);
    if (input) {
        input.value = result;
        input.focus();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const openAiUi = {
        provider: 'openai',
        loadBtn: document.getElementById('load-openai-models'),
        checkBtn: document.getElementById('check-openai-connection'),
        select: document.getElementById('openai-model-select'),
        status: document.getElementById('openai-model-status'),
        providerLabel: 'OpenAI'
    };

    const geminiUi = {
        provider: 'gemini',
        loadBtn: document.getElementById('load-gemini-models'),
        checkBtn: document.getElementById('check-gemini-connection'),
        select: document.getElementById('gemini-model-select'),
        status: document.getElementById('gemini-model-status'),
        providerLabel: 'Gemini'
    };

    const renderDiagnostics = function (diagnostics) {
        if (!diagnostics || typeof diagnostics !== 'object') {
            return '';
        }

        const dnsIp = diagnostics.dns_host_ip || '-';
        const primaryIp = diagnostics.primary_ip || '-';
        const errno = diagnostics.curl_errno ?? '-';
        const httpStatus = diagnostics.http_status ?? '-';
        const timing = diagnostics.timing || {};
        const total = typeof timing.total === 'number' ? timing.total.toFixed(2) : '-';
        const connect = typeof timing.connect === 'number' ? timing.connect.toFixed(2) : '-';
        const lookup = typeof timing.namelookup === 'number' ? timing.namelookup.toFixed(2) : '-';

        return ' | DNS: ' + dnsIp
            + ' | IP: ' + primaryIp
            + ' | CURL errno: ' + errno
            + ' | HTTP: ' + httpStatus
            + ' | lookup/connect/total: ' + lookup + '/' + connect + '/' + total + 's';
    };

    const wireProviderActions = function (ui) {
        if (!ui.loadBtn || !ui.checkBtn || !ui.select || !ui.status) {
            return;
        }

        ui.checkBtn.addEventListener('click', async function () {
            ui.checkBtn.disabled = true;
            ui.checkBtn.textContent = 'Đang kiểm tra...';
            ui.status.textContent = 'Đang kiểm tra kết nối tới ' + ui.providerLabel + '...';

            try {
                const response = await fetch('/admin/settings/ai-connection-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams({
                        provider: ui.provider,
                        csrf_token: '<?= htmlspecialchars($csrf_token) ?>'
                    })
                });

                const result = await response.json();
                const diagnosticsText = renderDiagnostics(result && result.diagnostics ? result.diagnostics : null);
                ui.status.textContent = (result && result.message ? result.message : 'Không có phản hồi.') + diagnosticsText;
            } catch (_error) {
                ui.status.textContent = 'Lỗi mạng nội bộ khi gọi endpoint kiểm tra kết nối.';
            } finally {
                ui.checkBtn.disabled = false;
                ui.checkBtn.textContent = 'Kiểm tra kết nối';
            }
        });

        ui.loadBtn.addEventListener('click', async function () {
            ui.loadBtn.disabled = true;
            ui.loadBtn.textContent = 'Đang tải...';
            ui.status.textContent = 'Đang lấy danh sách model từ ' + ui.providerLabel + '...';

            try {
                const response = await fetch('/admin/settings/ai-models', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams({
                        provider: ui.provider,
                        csrf_token: '<?= htmlspecialchars($csrf_token) ?>'
                    })
                });

                const result = await response.json();
                if (!response.ok || !result.success || !Array.isArray(result.models)) {
                    const diagnosticsText = renderDiagnostics(result && result.diagnostics ? result.diagnostics : null);
                    ui.status.textContent = ((result && result.message) ? result.message : 'Không tải được danh sách model.') + diagnosticsText;
                    return;
                }

                const current = ui.select.value || (result.current || '');
                ui.select.innerHTML = '';

                result.models.forEach(function (modelId) {
                    const opt = document.createElement('option');
                    opt.value = modelId;
                    opt.textContent = modelId;
                    if (modelId === current) {
                        opt.selected = true;
                    }
                    ui.select.appendChild(opt);
                });

                if (ui.select.options.length === 0 && result.current) {
                    const fallback = document.createElement('option');
                    fallback.value = result.current;
                    fallback.textContent = result.current;
                    fallback.selected = true;
                    ui.select.appendChild(fallback);
                }

                if (current && !Array.from(ui.select.options).some(function (o) { return o.value === current; })) {
                    const custom = document.createElement('option');
                    custom.value = current;
                    custom.textContent = current + ' (custom)';
                    custom.selected = true;
                    ui.select.appendChild(custom);
                }

                ui.status.textContent = 'Đã tải ' + result.models.length + ' model từ ' + ui.providerLabel + '.';
            } catch (_error) {
                ui.status.textContent = 'Lỗi mạng hoặc timeout khi tải model.';
            } finally {
                ui.loadBtn.disabled = false;
                ui.loadBtn.textContent = 'Tải model';
            }
        });
    };

    wireProviderActions(openAiUi);
    wireProviderActions(geminiUi);
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
