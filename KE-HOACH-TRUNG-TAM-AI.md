# KẾ HOẠCH — YÊU CẦU MỚI: XOÁ SẠCH TOÀN BỘ HỆ THỐNG AI — vc-vpn-2027

> Trạng thái: 🔄 PHẦN 13 ĐANG THỰC HIỆN — admin yêu cầu xóa toàn bộ hệ thống AI (mã nguồn + SQL + giao diện), không chừa một từ liên quan, không đụng phần không phải AI.
> **PHẦN 0–12 dưới đây là lịch sử HẾT HIỆU LỰC** (đã build xong để nay xóa sạch) — kế hoạch hiện hành: **PHẦN 13** ở cuối file.
> Quy ước đánh dấu: ⬜ chưa làm · 🔄 đang làm · ✅ hoàn thành (kèm ghi chú file đã sửa)
> Yêu cầu bắt buộc: mọi thay đổi SQL phải cập nhật CÙNG LÚC `database/vpn_service.sql` (dump gốc) và `database/migrations/` (file mới) — không được để thiếu cột/bảng khi đưa lên chạy thật.
> Bàn giao: không để lại file test/temp; không để route/controller/view treo; `php -l` sạch toàn bộ file đã sửa.

---

## PHẦN 0 — Kết luận phân tích hiện trạng ✅

1. **Tab cũ (5)**: Tổng Quan · Tạo Ảnh · Nội Dung Fanpage · Trả Lời Tự Động · Cấu Hình AI.
   - `AiImageController` + `tab-image.php`: tab tạo ảnh riêng → **loại bỏ**, năng lực ảnh dồn vào tab Soạn Thảo.
   - `AiReplyController` + `tab-reply.php`: chứa cấu hình **AI bình luận fanpage (Bình Luận) → loại bỏ**, phần còn lại (auto-reply web/fanpage, danh sách hội thoại) chuyển vào 2 tab theo dõi mới.
   - `AiFanpageController` + `tab-fanpage.php` (1015 dòng): pipeline nội dung → chuyển thành tab Soạn Thảo; CAB4 remarketing chuyển sang 2 tab Hàng đợi.
2. **Trợ lý admin (bubble)**: `AdminAssistantService::send` lưu tin admin vào JSONL nhưng **lưu trả lời ngay, không có bước duyệt**; `MAX_AI_CALLS=5` nhưng **chỉ gọi được tool ở vòng đầu**; `ToolRegistry::protocolBlock()` tự mâu thuẫn ("chỉ gọi tool khi admin ra lệnh") trong khi prompt lại bảo chủ động tra cứu → AI trả lời mơ hồ, không kiểm tra hệ thống; `plan_create/plan_update/topics_stage` **chạy không cần xác nhận**; xóa hội thoại không dọn `vc_ai_conversation_state`/`vc_ai_tool_calls`/pending; cắt lịch sử khi >1GB (mâu thuẫn yêu cầu "giữ đến khi admin xóa").
3. **Hàng đợi**: engine `vc_campaigns` + `vc_campaign_sends` đã đầy đủ (cron gửi dần, chống trùng theo chu kỳ, daily_limit, opt-out, pause/resume/cancel, `cycleProgress`) → **dùng lại**, chỉ làm giao diện theo dõi mới. Không xây bảng queue song song (tránh trùng lặp).
4. **Chuyển giao người thật**: chatbot web `shouldHandoff()` chỉ set `status='handoff'` nhưng **AI vẫn trả lời tiếp**; khách chưa đăng nhập **không tạo được ticket**; không thu email; fanpage **không có điểm dừng AI khi người thật can thiệp** và không có nút trả lời admin.
5. **Provider**: `config/ai.php` chỉ có `['kira']`; `AICore::resolveProvider()` hard-code `new KiraProvider`; mọi dropdown model **không hiển thị nhà cung cấp**; `aiLabelMap` chỉ có Kira.
6. **SQL thiếu/già**: code cho phép `type='popup'` nhưng `vc_posts.type` ENUM (news,tutorial,faq) → chèn popup là lỗi im lặng trên MySQL strict. `vc_chat_messages.role` chưa có `admin`. Chưa có cột email khách/phiếu cho chat.
7. **File thừa**: `admin/ai/conversations.php` (view chết), route `/admin/assistant/list` + `/admin/assistant/tool-calls` (không nơi gọi), CSS `.ai-input-wrap/.ai-send` chết, `vcInitActionDropdown` (admin.js) trùng lặp `vcBindActionMenus` (app.js), `$moduleRows`/`$modelsByModule` truyền thừa vào settings.

---

## PHẦN 1 — Cấu trúc tab & điều hướng mới ✅

**Cấu trúc 8 tab** (nhóm `ai` trong `resources/views/layouts/admin.php`):

| # | tab id | Tên | Route | Controller | View |
|---|--------|-----|-------|-----------|------|
| 1 | `ai-dashboard` | Tổng Quan AI | `/admin/ai` | `AiDashboardController` (giữ) | `dashboard.php` |
| 2 | `ai-articles` | Bài Viết Hệ Thống | `/admin/ai/articles` | ~~`AiArticlesController`~~ ❌ **ĐÃ HỦ — xem Part 11** | ~~`tab-articles.php`~~ ❌ |
| 3 | `ai-queue-email` | Hàng Đợi Email | `/admin/ai/queue-email` | `AiQueueController` **mới** (kind=email) | `tab-queue.php` **mới** (1 view cho 2 kind) |
| 4 | `ai-queue-fb` | Hàng Đợi Facebook | `/admin/ai/queue-fb` | `AiQueueController` (kind=fb) | `tab-queue.php` |
| 5 | `ai-content` | Soạn Thảo Nội Dung | `/admin/ai/content` | `AiContentController` **mới** (gộp fanpage + image) | `tab-content.php` **mới** |
| 6 | `ai-fanpage` | Trả Lời Fanpage | `/admin/ai/fanpage` | `AiMonitorController` **mới** — method `fanpage()` (gộp 2 tab theo dõi; thay `AiFanpageController`) | `tab-fanpage.php` **viết lại** (theo dõi, dùng partial chung `_session-panel.php`) |
| 7 | `ai-chatbot` | Chatbot Website | `/admin/ai/chatbot` | `AiMonitorController` — method `chatbot()` (một controller cho 2 tab theo dõi) | `tab-chatbot.php` **mới** (dùng `_session-panel.php`) |
| 8 | `ai-settings` | Cấu Hình AI | `/admin/ai/settings` | `AiSettingController` | `settings.php` (viết lại đa provider) |

- Tab 6 & 7 dùng chung 1 partial `_session-panel.php` (timeline phiên + trả lời admin + tiếp quản/tiếp tục/đóng) — tránh trùng lặp code.
- Mọi tab có nút **"Yêu cầu AI"** mở bubble trợ lý với seed ngữ cảnh tab (`window.VCAsst.open(seed)`).
- Xóa tab `ai-image`, tab `ai-reply`; redirect cũ → tab đích; sidebar `$aiMenus` cập nhật theo danh sách trên.

**✅ Nhiệm vụ (hoàn thành):**
- ✅ Sửa `$adminGroups['ai']['tabs']` trong `layouts/admin.php` (8 tab, icon PST/EML/MSG/CNT/FPG/RPL)
- ✅ Sửa `TAB_CONFIGS` trong `AiBaseController.php` (8 entry mới, bỏ `ai-image`/`ai-reply`; thêm nhãn prompt `fanpage_reply`)
- ✅ Sửa `$aiMenus` trong `admin-sidebar.php`
- ✅ Dựng lại nhóm route AI trong `routes/web.php` (8 tab + queue/monitor/content actions; redirect cũ `image`→`content`, `reply`/`conversations`→`chatbot` qua `AiDashboardController::toContent/toChatbot`; gỡ route image-generate/delete, conversations-*)
- ✅ Thêm prompt `fanpage_reply` vào `PromptRegistry::DEFAULT_PROMPTS`; `EntityCatalog` list_url chat → `/admin/ai/chatbot`
- ✅ View/controller từng tab tạo đủ ở Phần 6–8 (Part 3–5 đã xong; route khai báo sẵn)

## PHẦN 2 — Trợ lý admin: luồng làm việc mới (điều phối toàn bộ) ✅

Yêu cầu: trả lời **đầy đủ ngay trong chat** cho admin đọc; admin duyệt/triệu hồi trực tiếp; **mọi hành động lưu/chuyển giai đoạn phải qua xác nhận**; bám sát **toàn bộ lịch sử phiên đến khi admin xóa**; trước khi trả lời phải **đọc kỹ câu hỏi + kiểm tra hệ thống bằng tool thật**, không trả lời qua loa.

**✅ Nhiệm vụ (hoàn thành):**
- ✅ `AdminAssistantService`: vòng lặp tool **đa round** (gọi liền nhiều round tới khi đủ dữ liệu, nâng trần an toàn), trả lời cuối cùng là bản đầy đủ; nếu AI lỗi → ghi rõ vào lịch sử (không để tin admin treo vô hồi âm)
- ✅ `ToolRegistry::protocolBlock()` + `POLICY`: **bắt buộc tra cứu hệ thống trước khi trả lời câu hỏi về hệ thống/kinh doanh**; mọi tool ghi (kể cả `plan_create`, `plan_update`, `topics_stage`) chuyển sang `needs_confirm=true` (stage → admin bấm Duyệt mới chạy)
- ✅ Kết quả xác nhận/từ chối được **ghi ngược vào JSONL** để admin thấy chiến dịch/bài/plan đã tạo thật ở ngay đoạn chat
- ✅ `AssistantStore`: **không tự cắt lịch sử** (bỏ cắt 1GB); `deleteConversation()` dọn sạch `vc_ai_conversation_state`, `vc_ai_tool_calls`, pending action của phiên
- ✅ Hệ thống prompt (`storage/prompts/admin_assistant.txt`): quy tắc "hiểu đúng câu hỏi → kiểm tra hệ thống → trả lời trọn vẹn → chờ duyệt", kèm dòng truy vết "🔍 Đã kiểm tra: …" khi có dùng tool
- ✅ `_assistant_bubble.php`: mở rộng `window.VCAsst.open(seed)` (mở bubble + điền ngữ cảnh tab), giữ nguyên lịch sử đầy đủ khi tải

## PHẦN 3 — Tab Bài Viết Hệ Thống + chuẩn hóa loại bài ✅

**✅ Nhiệm vụ (hoàn thành):**
- ✅ SQL: `ALTER vc_posts.type` → ENUM — sửa lỗ hổng `popup` (lịch sử: thêm `promo`,`notice` rồi **GỠ theo Part 11** — enum cuối `news,tutorial,faq,popup`)
  - file mới `database/migrations/20261014_extend_post_types.sql` (idempotent, guard `information_schema`) **+** cập nhật `database/vpn_service.sql` (ENUM L273)
- ✅ Đồng bộ whitelist `type` về `Post::TYPES` / `Post::TYPE_LABELS` (nguồn duy nhất): `PostController` (create/update), `AdminActionTools` (preview + savePreview), `ToolRegistry`, `PageEditTools`, `EntityCatalog`, `SiteKnowledge`, `ChatbotService`; view `posts/create|edit` (`popup`/`faq`) — nhãn `promo`/`notice` đã gỡ theo Part 11
- ❌ `AiArticlesController` + `tab-articles.php`: **ĐÃ XÓA (Part 11)** — hệ thống đã có `/admin/posts`; tab Nội Dung (Part 6) quản lý toàn bộ luồng viết
- ✅ File dùng lại: `resources/views/admin/ai/_write-queue.php` (partial CHUNG hàng đợi viết — tab-content dùng), `app/Support/ArticleQueue.php` (`live()` đọc + tự dọn batch kết thúc sạch, `clear()`) — 2 file này VẪN CÒN; chỉ `AiArticlesController`/`tab-articles.php` bị xóa
- ✅ `AiTaskController`: redirect `storeTopics`/`writeNext`/`queueCancel` → `/admin/ai/content` (Part 11 đổi từ `/admin/ai/articles`)
- ✅ Model localStorage của hàng đợi: key `vc_ai_model_writequeue` (dashboard TABS đã thêm); key handoff `vc_fanpage_topics` giữ nguyên (do `_assistant_bubble.php` ghi)
- 📝 Deviation so với kế hoạch ban đầu: bảng outputs "bài chờ lên lịch" chuyển sang tab Soạn Thảo Nội Dung (Part 6); hàng đợi viết là partial chung thay vì nhân bản trong 2 tab
- ✅ Kiểm tra hồi đó: `php -l` sạch; harness render `tab-articles` (15 PASS) + unit `ArticleQueue` (6 PASS) — tab-articles về sau bị gỡ (Part 11)

## PHẦN 4 — Tab Hàng Đợi Email ✅

**✅ Nhiệm vụ (hoàn thành):**
- ✅ `AiQueueController` (file mới) — `email()`/`fb()` render chung `tab-queue.php`; JSON: `status()` (polling `?kind=`), `toggle()` (pause/resume/cancel), `run-now()`, `delete()` — đều CSRF + ủy quyền `CampaignService`
- ✅ `CampaignService`: tách `sendCampaignBatch()` (cron giữ nguyên hành vi), thêm `runNow()` (bỏ guard hôm nay, mở chu kỳ 1 monthly, quota = daily_limit − đã dùng), `delete()` (từ chối `active`), `queueOverview()` (tổng quan + tiến độ chu kỳ + 10 lượt gửi gần nhất)
- ✅ `tab-queue.php` (1 view cho 2 kind): thẻ tổng hợp (tổng/đang hoạt động/tạm dừng/gửi hôm nay/tổng đã gửi), bảng chiến dịch + progress sent/failed/skipped/pending, điều khiển tạm dừng/tiếp tục/hủy/chạy ngay/xóa, hoạt động gần đây, polling JSON 8s cập nhật tại chỗ + chip "Tải lại" khi danh sách đổi
- ✅ CTA "Yêu cầu AI soạn email" (+ seed một lần/hàng tháng/nghiên cứu) → AI tạo chiến dịch qua `campaign_create` (stage xác nhận) → hiện ngay trong bảng
- ✅ Không thêm bảng/cột SQL mới — dùng `vc_campaigns`/`vc_campaign_sends` có sẵn
- ✅ Kiểm tra: `php -l`; harness render 4 chế độ (email/fb/empty/error — ALL PASS); smoke test service thật qua DB dev: `sendDueCampaigns(dry-run)` + `queueOverview` + guard `runNow/delete` (PASS)

## PHẦN 5 — Tab Hàng Đợi Facebook ✅

**✅ Nhiệm vụ (hoàn thành):**
- ✅ `AiQueueController::fb()` (kind=fb) — chung view/controller với Phần 4, bảng dữ liệu riêng theo `kind='fb'`
- ✅ Ghi chú tuân thủ Meta hiện riêng ở tab FB: cửa sổ 24h/7 ngày, opt-out "STOP", chiến dịch tự `skipped` người ngoài cửa sổ; lỗi gửi hiển thị trong "Hoạt động gần đây" (chi tiết từ lượt gửi)
- ✅ CTA "Yêu cầu AI soạn tin FB" (seed một lần/hàng tháng/nghiên cứu)
- 📝 Deviation: thay vì gắn `FanpageService` trực tiếp vào tab, phần gửi do `CampaignService` đảm nhiệm (đã xử lý kind=fb) — tab chỉ theo dõi + điều khiển

## PHẦN 6 — Tab Soạn Thảo Nội Dung (kế hoạch → hàng đợi → chờ lên lịch) ✅

**✅ Nhiệm vụ (hoàn thành):**
- ✅ `AiContentController` (gộp `AiFanpageController` + `AiImageController`): index, `imageGenerate`, `imageDelete` — hàng đợi viết dùng chung `ArticleQueue::live()` (đã refactor ở Phần 4), không nhân bản endpoint
- ✅ `tab-content.php` viết lại từ `tab-fanpage.php`: **bỏ CAB4 remarketing** (chuyển sang tab hàng đợi), giữ CAB1 chủ đề/hàng đợi viết (pump `writeNext`), CAB2 tiến trình, CAB3 bài chờ lên lịch (`schedule`), thêm **khối Kế hoạch & Nghiên cứu** (danh sách plan + modal xem `GET /admin/assistant/plan/view`) + **khối Ảnh minh họa** (sinh ảnh + thư viện `_media-gallery`)
- ✅ **Trích xuất partial chung `_outputs-list.php`** (từ CAB3 của tab-fanpage, tham số `$outBase`) — tab-content và tab-fanpage dùng chung; tab-fanpage splice còn 233 dòng (sửa luôn require partial via `__DIR__` theo chuẩn dự án)
- ✅ Nút model của `_write-queue` và form ảnhそれぞれ `id="wq-model"`/`#img-model` (localStorage không đụng nhau)
- ✅ Gỡ `ai-image` khỏi route/tab ở **Phần 10**; giữ `/admin/ai/image` là redirect bookmark → content (không 404)
- ✅ Harness render full/empty ALL PASS + smoke tab-fanpage splice ALL PASS (file harness ở session-state, xóa ở bàn giao)

## PHẦN 7 — Tab Theo Dõi Trả Lời Fanpage (AI dừng khi người thật can thiệp) ✅

**✅ Nhiệm vụ (hoàn thành):**
- ✅ `AiMonitorController` (file mới thay `AiFanpageController`, gộp CẢ 2 tab theo dõi Part 7+8): `fanpage()`/`chatbot()` → `renderMonitor($source)`; danh sách phiên theo nguồn, poll JSON `GET /admin/ai/monitor/messages?id=`, **trả lời admin** (fanpage gửi thật qua `FanpageService::sendMessage` trước — lỗi → báo, KHÔNG lưu; lưu `role='admin'`), **Tiếp quản** (set `handoff` → AI ngừng trả lời), **Tiếp tục cho AI**, **Đóng phiên**, xóa phiên đã đóng (1 phiên hoặc purge theo nguồn)
- ✅ Tự động tiếp quản khi admin gửi tin (open → handoff ngay) — đúng yêu cầu
- ✅ `FanpageService`: chặn gọi AI khi phiên `handoff`; **loại bỏ luồng AI trả lời comment fanpage** (xóa cấu hình `fanpage_comment`, prompt `storage/prompts/fanpage_comment.txt`)
- ✅ Partial chung `_session-panel.php` (timeline + form trả lời + điều khiển AI + polling 5s — dùng cho cả Phần 8)
- ✅ **Deviation (DRY)**: thêm partial `_monitor-list.php` (danh sách phiên, port từ tab-reply) — 2 tab theo dõi chỉ là bọc mỏng `tab-fanpage.php`/`tab-chatbot.php`, không nhân bản ~170 dòng markup
- ✅ `tab-fanpage.php` viết lại toàn bộ (233 dòng pipeline cũ → bọc monitor); route `/admin/ai/fanpage` không còn trỏ `AiFanpageController`
- ✅ CAB remarketing (chu kỳ tháng) đã chuyển vào **2 tab Hàng đợi** (`tab-queue.php` CAB5, kind-filter; `AiQueueController::renderQueue` truyền `cycleProgress`) — KE-HOACH Part 0/6
- ✅ Harness render list/panel × 2 tab: **26/26 PASS** (file harness ở session-state, xóa ở bàn giao); `php -l` sạch

## PHẦN 8 — Tab Theo Dõi Chatbot Website (ticket + đăng nhập/email) ✅

**✅ Nhiệm vụ (hoàn thành):**
- ✅ SQL: `vc_chat_messages.role` += `'admin'`; `vc_chat_sessions` + `guest_email`, `ticket_id`; `vc_support_tickets.user_id` cho phép NULL + `guest_email`
  - file mới `database/migrations/20261015_chat_takeover_guest_tickets.sql` **+** cập nhật `database/vpn_service.sql`
- ✅ `ChatbotController::send`: phiên `handoff` → **AI ngừng trả lời** (bỏ qua hẳn `ChatbotService`, trả phản hồi cố định `provider='handoff'`); yêu cầu người thật → tạo ticket ngay (đã đăng nhập) hoặc hỏi **đăng nhập/để lại email** (khách) → bắt email → tạo ticket gắn `guest_email` + `ticket_id`
  - helper mới: `handoffGateReply()` / `createHandoffTicketForSession()` (chống spam: ≤3 phiếu đang mở, trùng chủ đề 180s dùng lại phiếu cũ) / `linkSessionTicket()` / `ticketSubject()` / `extractEmail()`; sự kiện `ticket_created`
  - `history()`: trả hội thoại cả khi `handoff` (khách thấy tin nhân viên); chỉ tính `idle_remaining_seconds` khi `open` → timer tự đóng 5 phút không thể quay token làm mất dấu phiếu
- ✅ Tab chatbot: `AiMonitorController::chatbot()` + `tab-chatbot.php` (bọc `_monitor-list` + `_session-panel` chung với fanpage); panel hiển thị **link phiếu** (`/admin/tickets/detail?id=`) + **email khách**
- ✅ `public/assets/js/app.js`: `role='admin'` → bong bóng cam "Nhân viên hỗ trợ" (khác màu AI); tin `system`/cổng handoff render dạng ghi chú giữa; history khôi phục cả phiên không còn `open`; handoff → không bật idle timer
- ✅ `ChatMessage::add()` cho phép role `admin`
- ✅ Harness `harness_chatbot_gate` **27/27 PASS** (helper reflection + round-trip DB thật có dọn phiếu + chốt nguồn/panel/js; file ở session-state, xóa ở bàn giao); harness monitor cũ 26/26 PASS; `php -l` + `node --check` sạch

## PHẦN 9 — Tab Cấu Hình AI đa nhà cung cấp ✅

**Quyết định**: hỗ trợ **Kira + họ OpenAI-compatible** (OpenAI, Groq, DeepSeek, **OpenRouter**, Mistral, xAI, Ollama…) dùng chung 1 driver `/v1/chat/completions` — thêm nhà cung cấp mới chỉ cần khai báo trong `config/ai.php`.

**✅ Nhiệm vụ (hoàn thành — agent nền, 11 file lint sạch + smoke registry 9/9):**
- ✅ `config/ai.php`: registry **9 nhà cung cấp** (kira + 8 openai_compat: openrouter, openai, groq, deepseek, mistral, xai, ollama, gemini), mỗi provider có label/base_url/setting_key/legacy_setting_keys/default_models
- ✅ `AICore::resolveProvider()` → **factory**: `kira` → `KiraProvider`, còn lại → `OpenAiCompatProvider` (mới, implements cùng interface)
- ✅ `AiSettingController` + `settings.php`: **1 ô chọn nhà cung cấp + 1 ô nhập key** (lưu `ai_api_key_{id}` + `ai_active_provider`), badge đã/chưa cấu hình, nút **Lưu / Kiểm Tra** (formaction), fallback chuỗi env → `ai_api_key_{id}` → legacy (`kira_api_key`) — **không cần migration**
- ✅ Nhãn model **"model — Nhà cung cấp"** (`providerKeyState`/`maskKey`/`decorateModels`/`modelLabelMap` trong `AiBaseController`), card model theo provider đang chọn + saved-models gộp theo provider; bỏ hard-code kira trong `AiDashboardController`
- ✅ `modelsByCapability`/model picker các tab đã dùng helper chung; rà hard-code `'kira'` còn lại → hoàn tất ở **Phần 10** (xem mục đó)

## PHẦN 10 — Dọn dẹp & bàn giao ✅

**✅ Nhiệm vụ (hoàn tất):**
- ✅ Xóa 8 file chết: `AiImageController`, `tab-image.php`, `AiReplyController`, `tab-reply.php`, `AiConversationController`, `conversation-detail.php`, `conversations.php`, route `/admin/assistant/list`, `/admin/assistant/tool-calls` (khác nơi gọi); cập nhật docblock/README trees tương ứng
- ✅ CSS chết `.ai-input-wrap/.ai-input/.ai-send` gỡ khỏi `admin.css` (đánh số section 1–26); JS: xóa `vcInitActionDropdown` trùng lặp trong `admin.js`, gộp into `vcBindActionMenus` (app.js) với positioning kẹp viewport (flip lên/xuống theo không gian trống)
- ✅ Redirect cũ đã đủ: `/admin/ai/image`→content, `/admin/ai/reply`+`/admin/ai/conversations`→chatbot, `GET /admin/ai/models`→dashboard catalog (`AiModelController::index`), `GET /admin/ai/outputs`→content (`AiOutputController::index`); không còn route index `prompts` (chỉ `prompts/create` live từ settings)
- ✅ Gỡ biến chết `$moduleRows`/`$modelsByModule`/`$allModels` + method `moduleRows()` (AiSetting/AiPrompt/AiBase); dashboard TABS bỏ entry `vc_ai_model_fanpage` chết
- ✅ Rà hard-code `'kira'` (hồi Part 9 để ⏳): `ChatbotService` (log `ai_failed` + `provider` response), `AIProviderService::toLegacyText` → fallback `AIModel::activeProvider()` (provider thực đang chạy); `FanpageService` đã sạch từ Part 9; các chỗ `?? 'kira'` còn lại là default có chủ đích (AICore/AIModel/AiBase/ModelResolver)
- ✅ Xóa dead code `HomeController::plans()` (không route + view `home.plans` không tồn tại → sẽ crash nếu gọi)
- ✅ Kiểm tra cuối: `php -l` 266/266 file sạch; route integrity 219/219 (file+method tồn tại); render targets OK; `node --check` admin.js + app.js OK

## PHẦN 11 — REVISE SAU PHẢN HỒI CỦA ADMIN ✅

Phản hồi: bàn giao chưa kiểm tra thật (tab không mở được), tự thêm tab Bài Viết Hệ Thống + loại bài `promo`/`notice` (hệ thống đã có `/admin/posts`), lặp lại danh sách model ở tab cấu hình, chữ dài dòng lặpблок.

- ✅ **Tab không mở được**: 7 route key có 2 khoảng trắng sau `GET` không khớp key router (1 khoảng trắng) → chuẩn hoá key định nghĩa + router chuẩn hoá key khi so khớp (`public/index.php`)
- ✅ **Fatal 500**: `AiMonitorController::safeBack()` private đè `protected` của cha → đổi tên `monitorBack()`
- ✅ **Gỡ tab Bài Viết Hệ Thống**: route GET, `AiArticlesController`, `tab-articles.php`, entry `TAB_CONFIGS`, link nav; POST `/admin/ai/articles/*` giữ lại (đang chạy hàng đợi viết của tab Nội Dung) nhưng redirect về `/admin/ai/content`
- ✅ **Gỡ loại `promo`/`notice`** tại mọi nơi (whitelist tool, view, dump, DB live): enum cuối `ENUM('news','tutorial','faq','popup')` — `popup` giữ (lỗ hổng thật); migration `20261014` idempotent, chạy lại 2 lần xác nhận không đổi gì
- ✅ **Gỡ danh sách model khỏi tab Cấu Hình AI**: xoá 3 block trong `settings.php` + ~60 dòng tính toán chết trong `AiSettingController` — model chỉ xem ở Trang Tổng Quan
- ✅ **Header 1 nguồn**: `dashboard.php`/`settings.php` lấy title/subtitle từ `TAB_CONFIGS` (render qua `_tab-header.php` / `$tabConfig`), không hardcode riêng
- ✅ **Chống trùng tiêu đề**: đổi "Hoạt Động Gần Đây" của tab queue (trùng dashboard) → "Lịch Sử Gửi"; quét h1/h2/h3 7 tab: 0 trùng
- ✅ **Rút gọn chữ**: phụ đề 7 tab + text dashboard/settings/assistant/hàng đợi viết đều 1 dòng/điểm
- ✅ **Dọn file test phiên**: fixture `storage/assistant/tests_*.json` đã xoá; `tools/*_test*` là file dev sẵn có trong `.gitignore` (mục "không đưa lên release") — giữ
- ✅ **Kiểm tra thật cuối (browser HTTP)**: 7/7 tab + `/admin/ai/queue/status` → 200, không lỗi PHP, phụ đề render đúng `TAB_CONFIGS`, nav đúng 7 tab; `php -l` toàn bộ sạch



## PHẦN 12 — SỬA LỖI GỌI MODEL + GỠ BLOAT + CHỌN MODEL THEO NHÀ CUNG CẤP ✅

Phản hồi: không gọi được model nữa; bubble trợ lý đã có mà các tab lại thêm khối "CTA trợ lý" trùng lặp; phải phân biệt rõ model nào trả lời fanpage, model nào trả lời chatbot; hiển thị đúng cái cần.

- ✅ **Gọi model lại được (gốc: `ai_active_provider=openai` key hết credit)**: `ModelResolver::resolve()` không còn lọc theo provider hoạt động — **resolve model trước trên TOÀN catalog, gọi provider CHỨA model đó** (`provider` trả về từ model row); `AICore::chat()`/`execute()` đổi thứ tự: model → provider. E2E thật: `coding-mimo-v2.6-flash` → provider `kira`, trả lời OK (~3.3s)
- ✅ **Dropdown mọi nơi không rỗng**: `tabConfig()` + `modelsByCapability()` lấy model CỦA MỌI provider đang bật (provider hoạt động lên đầu, nhãn `tên — Nhà cung cấp`); chỉ lọc `is_active` + `key_unlocked` + capability của module
- ✅ **Model fanpage & chatbot rõ nhà cung cấp**: `_tab-header.php` thêm form `POST /admin/ai/modules/set-model-override` trên header 2 tab (badge module `fanpage_reply`/`support_chat`) — kiểm chứng POST flash + giữ giá trị; `setModelOverride()` check key trên toàn catalog mọi provider
- ✅ **Gỡ khối CTA trợ lý** ở `tab-content.php` + `tab-queue.php` (bubble `#vc-asst-fab` đã đủ; text rỗng hàng đợi đổi hướng dẫn "bấm bong bóng Trợ lý AI")
- ✅ **Check browser 7/7 tab**: 0 lỗi PHP, 0 khối CTA cũ; dropdown trợ lý 7 model chat (5 OpenRouter + 2 Kira), fanpage/chatbot selected `coding-mimo-v2.6-flash — Kira AI`; gửi tin qua bubble trả lời thật OK

- [x] `php -l` sạch mọi file đã sửa/thêm (266/266, JS OK)
- [x] Không còn route trỏ controller không tồn tại, view không còn nơi render (219 routes OK; render targets OK; xóa `HomeController::plans()` dead)
- [x] `database/vpn_service.sql` khớp DB live: 41/41 bảng, 0 lệch cột có ý nghĩa (chỉ cosmetic display-width/whitespace enum); migration `20261014` đã áp cho DB live; bảng chết `vc_ai_providers` (không code nào dùng — registry nằm ở `config/ai.php` + `vc_settings`) đã DROP, dump giữ nguyên
- [x] Không còn tham chiếu hoạt động của `ai-image`, `ai-reply`, `fanpage_comment`, nhiều ô key (chỉ còn: redirect bookmark, migration lịch sử, decode log cũ `fanpage_comment_*` ở trang Logs, comment mô tả "đã XÓA"; settings có đúng 1 ô key + ô chọn nhà cung cấp)
- [x] Trợ lý: tra cứu trước khi trả lời, mọi tác vụ ghi qua xác nhận, lịch sử đầy đủ đến khi admin xóa (Parts 1–8, harness từng phần PASS)
- [x] Không còn file test/temp trong dự án (harness/`fake`/`out_*.txt` nằm ngoài project, đã dọn; dải kiểm tra project sạch)

---

# PHẦN 13 — XOÁ SẠCH TOÀN BỘ HỆ THỐNG AI

> Yêu cầu admin: xóa toàn bộ hệ thống AI — không chừa một từ liên quan — cả mã nguồn, SQL và giao diện; **không xóa nhầm phần không phải AI**. Đánh dấu ✅ từng phần khi xong. SQL lockstep dump + migration. Dọn file test khi bàn giao.

## 13.1 ✅ Rà soát ranh giới — chốt danh sách GIỮ / XÓA

### GIỮ (không phải AI — cấm đụng)
| Hệ thống | Thành phần |
|---|---|
| Bài Viết hệ thống | `vc_posts` (ENUM `type` giữ `news/tutorial/faq/popup`), `Post`, `Admin/PostController`, `views/admin/posts/*`, trang bài viết user |
| Mail SMTP + Log Email | `MailService::send()` + template `resources/views/emails/*`, `EmailLog`, `vc_email_logs`, `LogController::email`, tab Email |
| Ticket hỗ trợ | `vc_support_tickets`, `SupportTicket`, `TicketMessage` |
| Log hệ thống | `vc_system_logs` (giữ bảng; chỉ xóa dòng `action` AI), tab system / access / macrondroid |
| Settings liên hệ | `contact_email`, `fanpage_url` (footer), `youtube_url`, `zalo_url`… |
| ERP cơ bản | dashboard, orders, payments, subscriptions, nodes, coupons, withdrawals, referrals, `NotificationService`, `OrderService`, `PaymentService`, `VpnService`, `NodeTaskService` |

### XÓA (hệ thống AI)
- **Lõi**: `app/AI/**` (36 file), `config/ai.php`, `storage/assistant/**`, `storage/prompts/**` (4 prompt), `public/uploads/ai/**`
- **Controller**: 12 × `app/Controllers/Admin/Ai*Controller.php`, `Api/ChatbotController.php`, `Api/FanpageWebhookController.php`
- **Route**: `routes/web.php` — `/admin/ai/*`, `/admin/assistant/*`, `/admin/logs/chatbot`; `routes/api.php` — `/api/chat/*`, `/api/fanpage/webhook`, cron remarketing
- **View**: `resources/views/admin/ai/**` (17), `layouts/_assistant_bubble.php`, widget chatbot `layouts/footer.php`, tab chatbot `admin/logs/index.php`, khối Webhook `admin/settings/index.php`
- **JS/ảnh**: section chatbot `app.js` (~51 dòng), comment chatbot `admin.js` L373, icon AI `components/admin-icons.php` (chỉ khi 0 chỗ dùng)
- **Model (16)**: AIAsset, AiConversationState, AIModel, AIModule, AIOutput, AIOutputVersion, AITask, AITaskActivity, AiToolCall, AiUiMap, ChatAiCache, ChatEvent, ChatMessage, ChatSession, FbContact, ScheduledPost
- **Service (5)**: `AIProviderService`, `ChatbotService`, `FanpageService`, `CampaignService`, `RemarketingService` (+ `MailService::sendHtml()` chỉ campaign dùng)
- **Cron**: `CronController::sendDueCampaigns()`, `autoPostFanpage()`, `monthlyRemarketing()`; giữ cron khác
- **Tools (7)**: `ai_chatbot_eval`, `ai_core_selftest`, `ai_tool_registry_test`, `ai_ui_map_build`, `ai_worker`, `seed_prompts`, `verify_phase2_ai_flow`
- **BaseController**: xóa `replaceDomainMacro()` + comment AI (hết chỗ dùng sau khi xóa cron/trợ lý)

### Kết luận ranh giới (đã chốt — quyết định tự chủ, không hỏi lại)
1. **Chiến dịch email/FB + Remarketing** (`vc_campaigns`, `vc_campaign_sends`, `vc_fb_contacts`, `CampaignService`, `RemarketingService`): chỉ AI tạo/quản lý (tool trợ lý, tab hàng đợi AI), không có UI khác → **XÓA** (mail thường qua `MailService` giữ).
2. **Đăng bài fanpage lên lịch** (`vc_scheduled_posts`, `ScheduledPost`, cron `autoPostFanpage`): nguồn duy nhất là bài AI (`output_id`) → **XÓA**.
3. **Webhook fanpage** (`FanpageWebhookController`, `FanpageService`, settings `fanpage_verify_token/app_secret/page_access_token`): chỉ phục vụ trả lời AI + gửi campaign AI + đăng bài AI → **XÓA**; GIỮ `fanpage_url` (footer).
4. **Chatbot website** (widget footer, `/api/chat/*`, `ChatbotService`, `vc_chat_*`): là AI → **XÓA**; hệ thống ticket giữ.
5. **`vc_posts.type`**: ENUM đã đúng (promo/notice gỡ từ PHẦN 11) → giữ DB nguyên; **xóa** file migration `20261014` (chữ AI, chạy lại là no-op).
6. **Log**: xóa dòng `vc_system_logs.action` bắt đầu `ai_`/`AI_`; GIỮ `CREATE_POST`/`DELETE_POST` và mọi log khác.

## 13.2 ✅ Routes + Controllers + Views AI
- [x] `routes/web.php`: gỡ block `/admin/ai/*`, `/admin/assistant/*`, `/admin/logs/chatbot`
- [x] `routes/api.php`: gỡ `/api/chat/*` (4), `/api/fanpage/webhook` (2), route cron remarketing
- [x] Xóa 14 controller: 12 × `Admin/Ai*Controller` + `Api/ChatbotController` + `Api/FanpageWebhookController`
- [x] Xóa `resources/views/admin/ai/**` (17 file) + `resources/views/layouts/_assistant_bubble.php`
- [x] `layouts/admin.php`: gỡ menu AI (~L43–54), include bubble (~L145), `$activeMenu` nhóm ai
- [x] `layouts/admin-sidebar.php`: gỡ link "Trung Tâm AI" (~L43)

## 13.3 ✅ Giao diện site + JS + Logs + Settings
- [x] `layouts/footer.php`: xóa widget chatbot (L29–117 + đọc `ai_chatbot_enabled`); GIỮ link `fanpage_url` (L7)
- [x] `public/assets/js/app.js`: xóa section chatbot (~51 dòng)
- [x] `public/assets/js/admin.js`: sửa comment L373 (bỏ chữ "chatbot")
- [x] `admin/logs/index.php`: gỡ tab "Chatbot AI" (L29–34), nhánh render chatbot (L249–345), badge `ai_reply`/`ai_failed`/`fanpage_comment_*`
- [x] `LogController`: xóa `chatbot()`, property `chatEventModel`, `use` ChatEvent/AI; giữ email/system/access/macrondroid
- [x] `admin/settings/index.php`: xóa khối "Facebook Fanpage Webhook & Graph API" (~L366–412); GIỮ `fanpage_url` + liên hệ khác
- [x] `components/admin-icons.php`: xóa icon AI (kiểm tra 0 tham chiếu trước)

## 13.4 ✅ Lõi AI: app/AI + Models + Services + Config + Tools + Cron
- [x] Gỡ `use App\AI\` ngoài AI: `DashboardController` (2 chỗ), `CronController` (1 chỗ) — xem kỹ chỉ gỡ dòng AI
- [x] Xóa thư mục `app/AI/` (36 file)
- [x] Xóa 16 model (list ở 13.1)
- [x] Xóa 5 service; `MailService`: xóa `sendHtml()` + comment AI, giữ `send()`/template/EmailLog
- [x] Xóa `config/ai.php` + mọi require/`Config('ai.')`
- [x] Xóa `storage/assistant/**`, `storage/prompts/**`, `public/uploads/ai/**`
- [x] Xóa 7 file `tools/` AI
- [x] `CronController`: xóa 3 method AI + use/call; `BaseController`: xóa `replaceDomainMacro()` + comment

## 13.5 ✅ SQL lockstep (migration + dump + DB live)
- [x] **Tạo** `database/migrations/20261016_drop_ai_system.sql` idempotent:
  - `DROP TABLE IF EXISTS` 18 bảng: vc_ai_assets, vc_ai_conversation_state, vc_ai_models, vc_ai_modules, vc_ai_outputs, vc_ai_output_versions, vc_ai_tasks, vc_ai_task_activities, vc_ai_tool_calls, vc_ai_ui_map, vc_chat_ai_cache, vc_chat_events, vc_chat_messages, vc_chat_sessions, vc_campaigns, vc_campaign_sends, vc_fb_contacts, vc_scheduled_posts
  - `DELETE vc_settings` key: ai_active_provider, ai_api_key_openai, ai_api_key_openrouter, ai_cache_ttl_minutes, ai_chatbot_enabled, ai_cooldown_seconds, ai_duplicate_window_seconds, ai_max_output_tokens, ai_rate_limit_per_minute, ai_system_prompt, kira_api_key, fanpage_verify_token, fanpage_app_secret, fanpage_page_access_token (rà thêm key thật trong dump/DB)
  - `DELETE vc_system_logs` WHERE `action LIKE 'ai\_%' OR action LIKE 'AI\_%'`
- [x] Xóa 7 migration AI cũ: 20261010, 20261011, 20261012, 20261013, 20261014, 20261015_chat_takeover, 20261015_rename_fanpage_comment (migrations chỉ còn file drop)
- [x] Cập nhật `database/vpn_service.sql`: grep `vc_ai_|vc_chat_|vc_campaign|vc_fb_|vc_scheduled_posts` = 0; xóa INSERT settings AI/webhook
- [x] Chạy file drop trên DB live dev; kiểm tra: bảng AI = 0, key AI = 0, log `ai_%` = 0, dump khớp DB

## 13.6 ✅ Quét toàn dự án + dọn file test + tài liệu
- [x] `README.md`: xóa cron ai_worker/đăng bài fanpage/remarketing (L160–180), section "🤖 Cấu Hình AI Tự Động" (L182+), cây thư mục AI (L200–240+)
- [x] `vc_install.sh`: sửa comment L105–106 (không còn `vc_campaigns.recurrence`)
- [x] `.gitignore`: gỡ mục dev/test AI (nếu có)
- [x] Grep sweep (loại vendor): `ai_|Ai[A-Z]|AI[A-Z]|assistant|chatbot|Kira|vc_ai|vc_chat|vc_campaign|vc_fb_contact|vc_scheduled_posts|App\AI|FanpageService|CampaignService|RemarketingService|ChatbotService` → chỉ còn từ được phép (fanpage_url, CREATE_POST…)
- [x] Xóa mọi file test/temp phát sinh trong task này

## 13.7 ✅ Kiểm chứng bàn giao
- [x] `php -l` toàn bộ file sửa lại = 0 lỗi; JS sạch
- [x] Browser admin: Dashboard, Bài Viết, Logs (Email/MacroDroid), Settings mở tốt; **không còn menu/tab AI**; `/admin/ai/*`, `/admin/assistant/*`, `/api/chat/*` → 404
- [x] Trang chủ: footer không widget chatbot, link fanpage còn; bài viết/ticket/order không đổi
- [x] DB: bảng/key/log AI = 0; `vc_posts`/`vc_email_logs`/settings liên hệ nguyên vẹn
- [x] `git status`: chỉ thay đổi trong phạm vi AI; file test/temp = 0; đánh dấu ✅ từng phần

### Kết quả kiểm chứng 13.7 (2026-10-16)
- php -l 167 file PHP = 0 lỗi; 
ode --check app.js/admin.js = OK.
- Browser (đăng nhập admin): /admin, /admin/logs, /admin/settings = 200, 0 chuỗi AI; /admin/ai, /admin/ai/articles, /admin/assistant = 404.
- Trang chủ / = 200, không còn widget chatbot; link liên hệ fanpage giữ nguyên.
- DB dev: 18 bảng AI đã drop, key AI = 0, log AI = 0; dump pn_service.sql load vào DB rỗng = 23 bảng, 0 chuỗi AI.
- Migrations chỉ còn 20261016_drop_ai_system.sql; file test/temp = 0 (ArticleQueue.php mồ côi đã xóa).