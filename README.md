# 🛡️ VC VPN PANEL - System Management & Commerce

Hệ thống quản lý dịch vụ VPN, tự động hóa cấp phát tài khoản, quản lý gói cước, đồng bộ lưu lượng và tích hợp thanh toán.

---

## 🚀 Hướng Dẫn Cài Đặt Theo Thứ Tự (Step-by-Step)

### Bước 1: Cập nhật hệ thống VPS
* **Mục đích:** Cập nhật các gói phần mềm và vá lỗi bảo mật mới nhất cho hệ điều hành VPS.

```bash
sudo apt update && sudo apt upgrade -y
```

---

### Bước 2: Cài đặt aaPanel
* **Mục đích:** Cài đặt bảng điều khiển aaPanel để quản lý Web Server, Database và tên miền.

```bash
URL=https://www.aapanel.com/script/install_panel_en.sh && if [ -f /usr/bin/curl ];then curl -ksSO $URL ;else wget --no-check-certificate -O install_panel_en.sh $URL;fi;bash install_panel_en.sh ipssl
```

---

### Bước 3: Cài đặt các ứng dụng bắt buộc trên aaPanel
* **Mục đích:** Truy cập giao diện web aaPanel và chọn cài đặt các môi trường sau:
  * **Apache 2.4** (Web Server)
  * **MySQL 5.7** trở lên (Database)
  * **PHP 8.4** (Môi trường chạy ứng dụng)
  * **phpMyAdmin 5.2** (Giao diện quản lý Database)

---

### Bước 4: Cấu hình Môi Trường & Bảo Mật PHP (Trên aaPanel)
* **Mục đích:** Thiết lập đầy đủ thư viện và bảo mật cho PHP 8.4 ngay sau khi cài đặt thành công.

1. **Bật các PHP Extensions bắt buộc:** Vào **aaPanel > App Store > PHP 8.4 > Install extensions** và bật:
   * **pdo_mysql**: Kết nối và làm việc với cơ sở dữ liệu MySQL / MariaDB.
   * **openssl**: Mã hóa dữ liệu, mã hóa token và thông tin đăng nhập.
   * **mbstring**: Xử lý chuỗi văn bản đa ngôn ngữ (chuẩn UTF-8).
   * **curl**: Gửi yêu cầu API đến các Node VPN và cổng thanh toán.
   * **json**: Đọc/ghi file cấu hình JSON và dữ liệu JSONB.
  * **fileinfo**: Kiểm tra định dạng an toàn của tệp tải lên (Avatar, chứng từ).
  * Không cần cài thêm extension tạo QR: `vc_install.sh` tự chạy Composer để tải `endroid/qr-code`; mã QR liên kết đăng ký được xuất SVG, không phụ thuộc `gd`.

2. **Cấu hình bảo mật trong file `php.ini`:** Vào **aaPanel > App Store > PHP 8.4 > Configuration / Disabled functions**:
   * **Tắt các hàm nguy hiểm (Disabled functions):**
     ```ini
     disable_functions = exec, system, passthru, shell_exec, proc_open, popen
     ```
   * **Tắt hiển thị lỗi trực tiếp (Configuration > display_errors):**
     ```ini
     display_errors = Off
     ```
   * **Ẩn phiên bản PHP trên Header (expose_php):**
     ```ini
     expose_php = Off
     ```

---

### Bước 5: Di chuyển vào thư mục cài đặt
* **Mục đích:** Truy cập đúng thư mục gốc của trang web trên Server.

```bash
cd /www/wwwroot/vpn2s.linksub24h.com
```

---

### Bước 6: Tải mã nguồn từ GitHub
* **Mục đích:** Clone toàn bộ mã nguồn của dự án về thư mục hiện tại (Lưu ý dấu chấm ` .` ở cuối lệnh).

```bash
git clone https://github.com/Vivancang592/vc-vpn-panel.git .
```

---

### Bước 7: Phân quyền file cài đặt
* **Mục đích:** Cấp quyền thực thi (`+x`) cho file script `vc_install.sh`.

```bash
chmod +x vc_install.sh
```

---

### Bước 8: Chạy Script cài đặt tự động
* **Mục đích:** Khởi chạy quá trình tự động thiết lập hệ thống, cơ sở dữ liệu và cấu hình ban đầu.

```bash
./vc_install.sh
```

---

## 🌐 Cấu Hình Web Server & Điều Hướng (Apache / `.htaccess`)

* **Mục đích:** Cấu hình tệp `.htaccess` tại thư mục gốc của dự án (`vc_public/` hoặc thư mục web root) để chặn duyệt thư mục trái phép và chuyển hướng tất cả Request về tệp `index.php` phục vụ cơ chế Router.

Tạo hoặc chỉnh sửa tệp `.htaccess` với nội dung sau:

```apache
<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On

    # Xử lý Authorization Header cho các request API
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Điều hướng mọi URL không tồn tại về file index.php
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

* **Giải thích chi tiết:**
  * `Options -MultiViews -Indexes`: Ẩn danh sách tệp/thư mục khi không có file chỉ mục (`index`), ngăn ngừa lộ tài nguyên.
  * `RewriteEngine On`: Bật bộ máy điều hướng URL của Apache.
  * `RewriteCond %{REQUEST_FILENAME} !-f`: Kiểm tra nếu đường dẫn KHÔNG trỏ tới một tệp tin thực tế.
  * `RewriteCond %{REQUEST_FILENAME} !-d`: Kiểm tra nếu đường dẫn KHÔNG trỏ tới một thư mục thực tế.
  * `RewriteRule ^ index.php [L]`: Chuyển hướng toàn bộ yêu cầu còn lại vào file `index.php` làm lối vào duy nhất (Single Entry Point).

---

## 🔄 Hướng Dẫn Cập Nhật Hệ Thống

Khi có phiên bản mới trên GitHub, chạy lệnh sau để cập nhật mã nguồn và hệ thống:

* **Mục đích:** Tự động kéo mã nguồn mới nhất về và chạy quá trình cập nhật cấu hình/cơ sở dữ liệu.

```bash
git config --global --add safe.directory /www/wwwroot/vpn2s.linksub24h.com
```

```bash
cd /www/wwwroot/vpn2s.linksub24h.com
```

```bash
bash vc_update.sh
```

---
## Chạy cron trong temina

```bash
crontab -e
```

```bash
*/5 * * * * curl -s "https://vpn2s.linksub24h.com/api/cron/check-subscriptions?key=VC_VPN_CRON_2027_SECRET" > /dev/null 2>&1
```
---

* ##cấu trúc dự án:
```
vc-vpn-2027/
├── app/
│   ├── AI/
│   │   ├── Assets/
│   │   │   └── AssetManager.php
│   │   ├── Contracts/
│   │   │   ├── AICapability.php
│   │   │   ├── AIException.php
│   │   │   ├── AIProviderInterface.php
│   │   │   └── AIResult.php
│   │   ├── Core/
│   │   │   ├── AICore.php
│   │   │   ├── AILogger.php
│   │   │   ├── DirectMediaService.php
│   │   │   ├── ErrorHandler.php
│   │   │   ├── InputHandler.php
│   │   │   ├── ModelResolver.php
│   │   │   ├── ModuleRegistry.php
│   │   │   ├── ModuleSynchronizer.php
│   │   │   ├── OutputHandler.php
│   │   │   ├── PromptFileStore.php
│   │   │   ├── PromptRegistry.php
│   │   │   ├── RequestBuilder.php
│   │   │   ├── ResponseParser.php
│   │   │   ├── RetryPolicy.php
│   │   │   ├── TaskDispatcher.php
│   │   │   └── TaskRunner.php
│   │   └── Providers/
│   │       └── KiraProvider.php
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── AiBaseController.php
│   │   │   ├── AiConversationController.php
│   │   │   ├── AiDashboardController.php
│   │   │   ├── AiDubbingController.php
│   │   │   ├── AiFanpageController.php
│   │   │   ├── AiImageController.php
│   │   │   ├── AiModelController.php
│   │   │   ├── AiModuleController.php
│   │   │   ├── AiOutputController.php
│   │   │   ├── AiPromptController.php
│   │   │   ├── AiReplyController.php
│   │   │   ├── AiSettingController.php
│   │   │   ├── AiTaskController.php
│   │   │   ├── AiVideoController.php
│   │   │   ├── CouponController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── ExpenseController.php
│   │   │   ├── LogController.php
│   │   │   ├── NodeController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── PlanController.php
│   │   │   ├── PostController.php
│   │   │   ├── ReferralController.php
│   │   │   ├── ServerController.php
│   │   │   ├── ServerGroupController.php
│   │   │   ├── SettingController.php
│   │   │   ├── SubscriptionController.php
│   │   │   ├── TicketController.php
│   │   │   ├── UserController.php
│   │   │   └── WithdrawalController.php
│   │   ├── Api/
│   │   │   ├── ChatbotController.php
│   │   │   ├── ClientController.php
│   │   │   ├── FanpageWebhookController.php
│   │   │   ├── PaymentController.php
│   │   │   └── ServerController.php
│   │   ├── AuthController.php
│   │   ├── BaseController.php
│   │   ├── CronController.php
│   │   ├── HomeController.php
│   │   └── UserController.php
│   ├── Models/
│   │   ├── AccessLog.php
│   │   ├── AIAsset.php
│   │   ├── AIModel.php
│   │   ├── AIModule.php
│   │   ├── AIOutput.php
│   │   ├── AIOutputVersion.php
│   │   ├── AITask.php
│   │   ├── AITaskActivity.php
│   │   ├── BaseModel.php
│   │   ├── ChatAiCache.php
│   │   ├── ChatEvent.php
│   │   ├── ChatMessage.php
│   │   ├── ChatSession.php
│   │   ├── Coupon.php
│   │   ├── EmailLog.php
│   │   ├── Expense.php
│   │   ├── NodeInbound.php
│   │   ├── NodeTask.php
│   │   ├── Order.php
│   │   ├── Payment.php
│   │   ├── Post.php
│   │   ├── ReferralCommission.php
│   │   ├── ScheduledPost.php
│   │   ├── Server.php
│   │   ├── ServerGroup.php
│   │   ├── Setting.php
│   │   ├── Subscription.php
│   │   ├── SubscriptionAccessLog.php
│   │   ├── SupportTicket.php
│   │   ├── SystemLog.php
│   │   ├── TicketMessage.php
│   │   ├── User.php
│   │   ├── VpnPlan.php
│   │   └── Withdrawal.php
│   └── Services/
│       ├── AIProviderService.php
│       ├── ChatbotService.php
│       ├── FanpageService.php
│       ├── MailService.php
│       ├── NodeTaskService.php
│       ├── NotificationService.php
│       ├── OrderService.php
│       ├── PaymentService.php
│       └── VpnService.php
├── config/
│   ├── ai.php
│   ├── app.php
│   └── database.php
├── database/
│   ├── migrations/
│   │   ├── 20260927_add_ai_core_tables.sql
│   │   ├── 20260928_drop_prompt_tables.sql
│   │   ├── 20260928_drop_unused_ai_tables.sql
│   │   ├── 20260929_p12_drop_content_prompt.sql
│   │   ├── 20260930_add_output_id_scheduled_posts.sql
│   │   ├── 20261002_add_vpn_plan_stock.sql
│   │   ├── 20261002_retain_outputs_when_pruning_ai_tasks.sql
│   │   ├── 20261005_add_network_locked_until.sql
│   │   ├── 20261006_add_connected_devices.sql
│   │   └── 20261006_add_task_error_msg.sql
│   └── vpn_service.sql
├── docs/
│   └── task-contract.md
├── public/
│   ├── assets/
│   │   ├── audio/
│   │   │   └── voice-samples/
│   │   │       ├── Aoede.wav
│   │   │       ├── Charon.wav
│   │   │       ├── Fenrir.wav
│   │   │       ├── Kore.wav
│   │   │       └── Puck.wav
│   │   ├── css/
│   │   │   ├── admin.css
│   │   │   ├── app.css
│   │   │   ├── auth.css
│   │   │   ├── home.css
│   │   │   └── user.css
│   │   ├── images/
│   │   │   ├── apple-touch-icon.png
│   │   │   ├── favicon.png
│   │   │   ├── favicon-48.png
│   │   │   ├── icon.svg
│   │   │   ├── icon-192.png
│   │   │   ├── icon-512.png
│   │   │   └── logo.png
│   │   └── js/
│   │       ├── admin.js
│   │       ├── app.js
│   │       ├── auth.js
│   │       └── home.js
│   ├── uploads/
│   ├── .htaccess
│   ├── favicon.ico
│   ├── index.php
│   └── site.webmanifest
├── resources/
│   └── views/
│       ├── admin/
│       │   ├── ai/
│       │   │   ├── _flash.php
│       │   │   ├── _media-gallery.php
│       │   │   ├── _tab-header.php
│       │   │   ├── conversation-detail.php
│       │   │   ├── conversations.php
│       │   │   ├── dashboard.php
│       │   │   ├── output-detail.php
│       │   │   ├── prompt-edit.php
│       │   │   ├── settings.php
│       │   │   ├── tab-dubbing.php
│       │   │   ├── tab-fanpage.php
│       │   │   ├── tab-image.php
│       │   │   ├── tab-reply.php
│       │   │   ├── tab-video.php
│       │   │   └── task-detail.php
│       │   ├── coupons/
│       │   │   ├── create.php
│       │   │   ├── edit.php
│       │   │   └── index.php
│       │   ├── expenses/
│       │   │   ├── create.php
│       │   │   ├── edit.php
│       │   │   └── index.php
│       │   ├── logs/
│       │   │   ├── access.php
│       │   │   ├── email.php
│       │   │   ├── index.php
│       │   │   ├── macrodroid.php
│       │   │   └── system.php
│       │   ├── nodes/
│       │   │   ├── detail.php
│       │   │   └── index.php
│       │   ├── notifications/
│       │   │   └── index.php
│       │   ├── orders/
│       │   │   ├── create.php
│       │   │   ├── detail.php
│       │   │   └── index.php
│       │   ├── payments/
│       │   │   ├── detail.php
│       │   │   └── index.php
│       │   ├── plans/
│       │   │   ├── create.php
│       │   │   ├── edit.php
│       │   │   └── index.php
│       │   ├── posts/
│       │   │   ├── create.php
│       │   │   ├── detail.php
│       │   │   ├── edit.php
│       │   │   └── index.php
│       │   ├── referrals/
│       │   │   └── index.php
│       │   ├── server-groups/
│       │   │   ├── create.php
│       │   │   ├── edit.php
│       │   │   └── index.php
│       │   ├── servers/
│       │   │   ├── create.php
│       │   │   ├── detail.php
│       │   │   ├── edit.php
│       │   │   └── index.php
│       │   ├── settings/
│       │   │   └── index.php
│       │   ├── subscriptions/
│       │   │   ├── detail.php
│       │   │   └── index.php
│       │   ├── tickets/
│       │   │   ├── detail.php
│       │   │   └── index.php
│       │   ├── users/
│       │   │   ├── create.php
│       │   │   ├── detail.php
│       │   │   ├── edit.php
│       │   │   └── index.php
│       │   ├── withdrawals/
│       │   │   ├── detail.php
│       │   │   └── index.php
│       │   └── dashboard.php
│       ├── auth/
│       │   ├── forgot-password.php
│       │   ├── login.php
│       │   └── register.php
│       ├── components/
│       │   ├── alert.php
│       │   ├── icons.php
│       │   ├── modal.php
│       │   ├── pagination.php
│       │   ├── plan-card.php
│       │   ├── server-status.php
│       │   ├── status-badge.php
│       │   └── subscription-card.php
│       ├── emails/
│       │   ├── auth/
│       │   │   ├── register-otp.php
│       │   │   └── reset-password.php
│       │   ├── orders/
│       │   │   ├── cancelled.php
│       │   │   ├── new-order-admin.php
│       │   │   └── payment-completed.php
│       │   ├── subscriptions/
│       │   │   ├── data-exceeded.php
│       │   │   ├── device-locked.php
│       │   │   ├── expired.php
│       │   │   └── expiring-soon.php
│       │   ├── _footer.php
│       │   └── _header.php
│       ├── home/
│       │   ├── download.php
│       │   ├── faq.php
│       │   ├── index.php
│       │   └── post-detail.php
│       ├── layouts/
│       │   ├── admin.php
│       │   ├── admin-sidebar.php
│       │   ├── app.php
│       │   ├── auth.php
│       │   ├── footer.php
│       │   ├── header.php
│       │   ├── navbar.php
│       │   └── sidebar.php
│       ├── policies/
│       │   ├── privacy.php
│       │   ├── refund.php
│       │   └── terms.php
│       └── user/
│           ├── notifications/
│           │   └── index.php
│           ├── orders/
│           │   ├── detail.php
│           │   └── index.php
│           ├── payments/
│           │   ├── checkout.php
│           │   └── index.php
│           ├── plans/
│           │   ├── checkout.php
│           │   └── index.php
│           ├── profile/
│           │   └── index.php
│           ├── referrals/
│           │   └── index.php
│           ├── subscriptions/
│           │   ├── detail.php
│           │   └── index.php
│           ├── tickets/
│           │   ├── create.php
│           │   ├── detail.php
│           │   └── index.php
│           ├── wallet/
│           │   └── index.php
│           ├── withdrawals/
│           │   ├── create.php
│           │   └── index.php
│           ├── article-detail.php
│           ├── dashboard.php
│           ├── downloads.php
│           ├── guides.php
│           └── guides-detail.php
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
│   ├── _legacy_backup/
│   ├── backups/
│   ├── logs/
│   │   └── .gitkeep
│   ├── prompts/
│   │   ├── audio_tts.txt
│   │   ├── content_article.txt
│   │   ├── fanpage_comment.txt
│   │   ├── image_generation.txt
│   │   ├── publish_post.txt
│   │   ├── rules_auto_reply.txt
│   │   ├── rules_fanpage_content.txt
│   │   ├── rules_video.txt
│   │   ├── rules_video_dubbing.txt
│   │   ├── support_chat.txt
│   │   └── video_generation.txt
│   └── tmp/
├── tools/
│   ├── _p3_probe.php
│   ├── ai_core_selftest.php
│   ├── ai_worker.php
│   ├── kira_probe.php
│   ├── seed_prompts.php
│   └── verify_phase2_ai_flow.php
├── .env.example
├── .gitignore
├── .htaccess
├── composer.json
├── composer.lock
├── README.md
├── SKILL.md
├── vc_install.sh
└── vc_update.sh
```
