## Plan: Chatbot AI tư vấn & fanpage chuyển đổi

Triển khai một lớp AI Assistant đa kênh cho web và Facebook Page, dùng nhà cung cấp chính OpenAI/Gemini, trả lời FAQ/hướng dẫn sử dụng dịch vụ VPN, hỗ trợ tư vấn trước mua và có cơ chế chuyển người thật khi bot không chắc chắn. Cách làm ít xâm lấn: tận dụng kiến trúc Controller/Service/Setting hiện có, lưu cấu hình nhanh trong vc_settings, ghi log và chỉ số chuyển đổi bằng pattern AccessLog/SystemLog đang dùng.

**Steps**
1. Phase 1 - Kiến trúc & dữ liệu nền (blocking)
1.1 Xác định ma trận use-case: FAQ kỹ thuật, hướng dẫn mua gói, xử lý phản hồi fanpage, trigger chuyển nhân viên hỗ trợ.
1.2 Chuẩn hóa policy bot: chỉ tư vấn, không phát coupon, không cam kết vượt chính sách hệ thống, không yêu cầu thông tin nhạy cảm.
1.3 Thiết kế schema tối thiểu để lưu hội thoại, trạng thái chuyển người thật, nguồn kênh (web/fanpage), và sự kiện funnel. Có thể dùng bảng mới để tránh quá tải vc_system_logs.

2. Phase 2 - Cấu hình quản trị AI (depends on 1)
2.1 Mở rộng tab Cài đặt admin để thêm nhóm “AI Chatbot”: provider mặc định, model name, API key OpenAI/Gemini, ngưỡng confidence/handoff, system prompt kinh doanh, giờ làm việc hỗ trợ người thật.
2.2 Bổ sung cơ chế validate đầu vào trong controller save settings (không log lộ key, sanitize và giới hạn độ dài).
2.3 Áp dụng quy tắc hiển thị key dạng masked ở UI admin, chỉ ghi đè khi nhập giá trị mới.

3. Phase 3 - Lớp service tích hợp LLM (depends on 2)
3.1 Tạo dịch vụ trung gian AIProviderService (factory + adapter) để gọi OpenAI/Gemini thống nhất payload và output.
3.2 Tạo ChatbotService: xây context từ FAQ/posts/plans/settings, gọi model, chấm điểm tự tin, quyết định handoff.
3.3 Tạo FanpageService (Meta webhook): xác thực webhook, parse message event, map vào ChatbotService, gửi reply về Graph API.
3.4 Thêm retry + timeout + circuit-breaker nhẹ để tránh treo request và kiểm soát chi phí token.

4. Phase 4 - API endpoints & luồng web chat (depends on 3)
4.1 Thêm endpoint API cho web widget: tạo session chat, gửi message, lấy lịch sử gần, đánh dấu handoff.
4.2 Thêm endpoint webhook fanpage: GET verify token + POST nhận tin nhắn.
4.3 Cắm widget vào layout công khai và user dashboard, ưu tiên hiển thị ở trang chủ/plans/checkout và giữ hiệu năng tải trang.
4.4 Tạo CTA ngữ cảnh theo hành vi: nếu người dùng đang ở plans/checkout thì bot ưu tiên nội dung so sánh gói, hướng dẫn thanh toán, giải đáp rào cản.

5. Phase 5 - Guardrails bán hàng & chuyển đổi (parallel with 4.3/4.4 after 4.1)
5.1 Xây prompt template theo vai trò “tư vấn viên dịch vụ”: nêu lợi ích gói, điều kiện sử dụng, câu hỏi sàng lọc nhu cầu.
5.2 Thêm rule chuyển người thật khi: bot không chắc chắn, câu hỏi thanh toán nhạy cảm, khiếu nại, yêu cầu hoàn tiền phức tạp.
5.3 Chuẩn hóa thông điệp chốt mềm: dẫn link checkout, nhưng không giảm giá/coupon tự phát theo quyết định hiện tại.

6. Phase 6 - Đo lường & báo cáo (depends on 4 and 5)
6.1 Ghi sự kiện funnel: chat_started, plan_recommended, checkout_clicked, payment_intent, handoff_requested, fanpage_lead.
6.2 Nối số liệu vào dashboard admin qua truy vấn tổng hợp theo ngày/nguồn để đo tỷ lệ chat->checkout và chat->paid.
6.3 Thêm log vận hành: lỗi webhook, timeout AI, số lượt fallback sang người thật.

7. Phase 7 - Kiểm thử & hardening (depends on 6)
7.1 Test chức năng: web chat, fanpage webhook verify/signature, fallback provider.
7.2 Test bảo mật: CSRF cho form nội bộ, verify token cho webhook, hạn chế spam/rate limit cơ bản theo IP/session.
7.3 Test nghiệp vụ chuyển đổi: kịch bản user mới hỏi gói, user đang checkout hỏi lỗi thanh toán, fanpage inbox hỏi đăng ký.

**Relevant files**
- c:/laragon/www/vc-vpn-2027/routes/web.php — thêm route admin cấu hình chatbot và route trang quản lý hội thoại nếu cần.
- c:/laragon/www/vc-vpn-2027/routes/api.php — thêm endpoint web chat API và Meta webhook (GET verify/POST events).
- c:/laragon/www/vc-vpn-2027/app/Controllers/Admin/SettingController.php — validate/lưu setting chatbot, masking key.
- c:/laragon/www/vc-vpn-2027/resources/views/admin/settings/index.php — thêm tab/section “AI Chatbot & Fanpage”.
- c:/laragon/www/vc-vpn-2027/app/Services — thêm AIProviderService, ChatbotService, FanpageService.
- c:/laragon/www/vc-vpn-2027/app/Controllers/Api — thêm ChatbotController/FanpageWebhookController hoặc mở rộng controller API hiện có.
- c:/laragon/www/vc-vpn-2027/resources/views/layouts/footer.php — mount container chatbot widget.
- c:/laragon/www/vc-vpn-2027/public/assets/js/app.js — logic UI chat widget, gọi API, fallback/handoff state.
- c:/laragon/www/vc-vpn-2027/app/Models/Setting.php — helper đọc key chatbot/provider.
- c:/laragon/www/vc-vpn-2027/database/vpn_service.sql — bổ sung bảng chat sessions/messages/events (hoặc tạo migration SQL mới).
- c:/laragon/www/vc-vpn-2027/app/Models/AccessLog.php — tái dùng pattern attribution cho event nguồn chuyển đổi.
- c:/laragon/www/vc-vpn-2027/app/Controllers/Admin/DashboardController.php — thêm dữ liệu KPI chatbot/fanpage.

**Verification**
1. Tạo checklist manual cho 3 hành trình: web visitor chưa login, user đang checkout, khách nhắn fanpage.
2. Gọi thử endpoint webhook với payload mẫu đúng/sai chữ ký để xác nhận chặn request giả mạo.
3. Kiểm thử timeout/fallback provider bằng cách ngắt key OpenAI và xác nhận Gemini nhận tải.
4. So sánh số liệu trước/sau triển khai 7-14 ngày cho các KPI: chat->checkout rate, checkout completion rate, handoff rate, response SLA.
5. Review log tại storage/logs và bảng sự kiện để đảm bảo không ghi lộ API key/PII nhạy cảm.

**Decisions**
- Dùng OpenAI + Gemini cho giai đoạn 1, cần lớp adapter để dễ mở rộng provider.
- Kênh fanpage phạm vi hiện tại: Facebook Page Messenger webhook.
- Mức tự động: bot tự trả lời + chuyển người thật khi cần.
- Chính sách bán hàng: bot chỉ tư vấn, không tự phát coupon.
- Lưu API key giai đoạn 1 trong vc_settings để triển khai nhanh.

**Further Considerations**
1. Bảo mật trung hạn: nên chuyển API key sang ENV/KMS sau khi MVP ổn định để giảm rủi ro lộ key trong DB backup.
2. Khuyến nghị thêm trang “Hộp thư hội thoại hợp nhất” trong admin để nhân sự tiếp quản nhanh khi bot handoff.
3. Cân nhắc A/B test hai prompt bán hàng khác nhau cho trang plans và checkout để tối ưu conversion.
