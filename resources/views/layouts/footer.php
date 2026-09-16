<footer class="vc-footer">
    <div class="vc-footer-container">
        
        <!-- Tên Website ở giữa -->
        <div class="vc-footer-title-wrap">
            <h3 class="vc-footer-title">
                <?= htmlspecialchars($settings['site_title'] ?? 'VC VPN 2027') ?>
            </h3>
        </div>
        
        <!-- Bố cục tự động co giãn -->
        <div class="vc-footer-links-grid">

            <!-- Cột 1: Mạng Xã Hội -->
            <div class="vc-footer-col">
                <h4>Mạng Xã Hội</h4>
                <?php if (!empty($settings)): ?>
                    <div class="vc-footer-col-content">
                        <?php if (!empty($settings['fanpage_url'])): ?>
                            <a href="<?= htmlspecialchars($settings['fanpage_url']) ?>" target="_blank" rel="noopener noreferrer" class="vc-color-blue">
                                <span>🌐</span> Fanpage Hỗ Trợ
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($settings['zalo_url'])): ?>
                            <a href="<?= htmlspecialchars($settings['zalo_url']) ?>" target="_blank" rel="noopener noreferrer" class="vc-color-green">
                                <span>💬</span> Zalo
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($settings['youtube_url'])): ?>
                            <a href="<?= htmlspecialchars($settings['youtube_url']) ?>" target="_blank" rel="noopener noreferrer" class="vc-color-red">
                                <span>▶️</span> Youtube
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cột 2: Chính Sách -->
            <div class="vc-footer-col">
                <h4>Chính Sách</h4>
                <div class="vc-footer-col-content">
                    <a href="/terms" class="vc-color-blue">Điều Khoản Dịch Vụ</a>
                    <a href="/privacy" class="vc-color-green">Quyền Riêng Tư</a>
                    <a href="/refund" class="vc-color-orange">Chính Sách Hoàn Tiền</a>
                </div>
            </div>

            <!-- Cột 3: Liên Hệ Hỗ Trợ -->
            <div class="vc-footer-col">
                <h4>Liên Hệ Hỗ Trợ</h4>
                <?php if (!empty($settings)): ?>
                    <div class="vc-footer-col-content">
                        <?php if (!empty($settings['contact_email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($settings['contact_email']) ?>" class="vc-color-purple">
                                <span>📧</span> <?= htmlspecialchars($settings['contact_email']) ?>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($settings['wechat_id'])): ?>
                            <span class="wechat-text vc-color-darkgreen">
                                <span>💬</span> WeChat: <?= htmlspecialchars($settings['wechat_id']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Bản quyền & Đường phân cách -->
        <div class="vc-footer-copyright-wrap">
            <p class="vc-footer-copyright-text">&copy; <?= date('Y') ?> <?= htmlspecialchars($settings['site_title'] ?? 'VC VPN 2027') ?>. All rights reserved.</p>
        </div>

    </div>
</footer>

<!-- Luôn tải app.js với tham số xóa cache -->
<script src="/assets/js/app.js?v=<?= time() ?>"></script>

<!-- Chỉ tải extraJs nếu khác file app.js -->
<?php if (isset($extraJs) && $extraJs !== 'app'): ?>
    <script src="/assets/js/<?= $extraJs ?>.js?v=<?= time() ?>"></script>
<?php endif; ?>
</body>
</html>