# 🛡️ VC VPN 2027 - System Management & Commerce

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
├── vc_install.sh
├── vc_update.sh
├── .env.example
├── .htaccess
├── composer.json
├── README.md
│
├── public/
│   ├── .htaccess
│   ├── index.php
│   ├── favicon.ico
│   └── assets/
│       ├── css/
│       │   ├── app.css
│       │   ├── admin.css
│       │   └── auth.css
│       │
│       ├── js/
│       │   ├── app.js
│       │   ├── admin.js
│       │   └── auth.js
│       │
│       └── images/
│           ├── logo.png
│           └── favicon.png
│
├── config/
│   ├── app.php
│   └── database.php
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── app/
│   ├── Controllers/
│   │   ├── BaseController.php
│   │   ├── HomeController.php
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── CronController.php
│   │   │
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── UserController.php
│   │   │   ├── ServerGroupController.php
│   │   │   ├── ServerController.php
│   │   │   ├── NodeController.php
│   │   │   ├── PlanController.php
│   │   │   ├── CouponController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── SubscriptionController.php
│   │   │   ├── ReferralController.php
│   │   │   ├── WithdrawalController.php
│   │   │   ├── PostController.php
│   │   │   ├── TicketController.php
│   │   │   ├── ExpenseController.php
│   │   │   ├── SettingController.php
│   │   │   └── LogController.php
│   │   │
│   │   └── Api/
│   │       ├── ClientController.php
│   │       ├── ServerController.php
│   │       └── PaymentController.php
│   │
│   ├── Models/
│   │   ├── BaseModel.php
│   │   ├── Setting.php
│   │   ├── User.php
│   │   ├── AccessLog.php
│   │   ├── ServerGroup.php
│   │   ├── Server.php
│   │   ├── NodeInbound.php
│   │   ├── NodeTask.php
│   │   ├── VpnPlan.php
│   │   ├── Coupon.php
│   │   ├── Order.php
│   │   ├── Payment.php
│   │   ├── Subscription.php
│   │   ├── ReferralCommission.php
│   │   ├── Withdrawal.php
│   │   ├── Post.php
│   │   ├── SupportTicket.php
│   │   ├── TicketMessage.php
│   │   ├── SystemLog.php
│   │   ├── EmailLog.php
│   │   └── Expense.php
│   │
│   └── Services/
│       ├── MailService.php
│       ├── VpnService.php
│       ├── OrderService.php
│       ├── PaymentService.php
│       └── ServerService.php
│
├── resources/
│   └── views/
│       ├── layouts/
│       │   ├── app.php
│       │   ├── admin.php
│       │   ├── auth.php
│       │   ├── header.php
│       │   ├── navbar.php
│       │   ├── sidebar.php
│       │   ├── admin-sidebar.php
│       │   └── footer.php
│       │
│       ├── components/
│       │   ├── alert.php
│       │   ├── modal.php
│       │   ├── pagination.php
│       │   ├── status-badge.php
│       │   ├── plan-card.php
│       │   ├── server-status.php
│       │   └── subscription-card.php
│       │
│       ├── emails/
│       │   ├── auth/
│       │   │   ├── register-otp.php
│       │   │   └── reset-password.php
│       │   ├── orders/
│       │   │   └── activated.php
│       │   └── subscriptions/
│       │       ├── expiring-soon.php
│       │       ├── expired.php
│       │       └── data-exceeded.php
│       │
│       ├── auth/
│       │   ├── login.php
│       │   ├── register.php
│       │   ├── forgot-password.php
│       │   └── reset-password.php
│       │
│       ├── home/
│       │   ├── index.php
│       │   ├── plans.php
│       │   ├── faq.php
│       │   └── contact.php
│       │
│       ├── policies/
│       │   ├── terms.php
│       │   ├── privacy.php
│       │   └── refund.php
│       │
│       ├── user/
│       │   ├── dashboard.php
│       │   ├── profile/
│       │   │   └── index.php
│       │   ├── plans/
│       │   │   ├── index.php
│       │   │   └── checkout.php
│       │   ├── orders/
│       │   │   ├── index.php
│       │   │   └── detail.php
│       │   ├── subscriptions/
│       │   │   ├── index.php
│       │   │   ├── detail.php
│       │   │   └── connect.php
│       │   ├── payments/
│       │   │   ├── index.php
│       │   │   └── deposit.php
│       │   ├── wallet/
│       │   │   └── index.php
│       │   ├── referrals/
│       │   │   └── index.php
│       │   ├── withdrawals/
│       │   │   ├── index.php
│       │   │   └── create.php
│       │   ├── tickets/
│       │   │   ├── index.php
│       │   │   ├── create.php
│       │   │   └── detail.php
│       │   └── notifications/
│       │       └── index.php
│       │
│       └── admin/
│           ├── dashboard.php
│           ├── users/
│           │   ├── index.php
│           │   ├── create.php
│           │   ├── edit.php
│           │   └── detail.php
│           ├── server-groups/
│           │   ├── index.php
│           │   ├── create.php
│           │   └── edit.php
│           ├── servers/
│           │   ├── index.php
│           │   ├── create.php
│           │   ├── edit.php
│           │   └── detail.php
│           ├── nodes/
│           │   ├── index.php
│           │   └── detail.php
│           ├── plans/
│           │   ├── index.php
│           │   ├── create.php
│           │   └── edit.php
│           ├── coupons/
│           │   ├── index.php
│           │   ├── create.php
│           │   └── edit.php
│           ├── orders/
│           │   ├── index.php
│           │   ├── create.php
│           │   └── detail.php
│           ├── payments/
│           │   ├── index.php
│           │   └── detail.php
│           ├── subscriptions/
│           │   ├── index.php
│           │   └── detail.php
│           ├── referrals/
│           │   └── index.php
│           ├── withdrawals/
│           │   ├── index.php
│           │   └── detail.php
│           ├── posts/
│           │   ├── index.php
│           │   ├── create.php
│           │   ├── edit.php
│           │   └── detail.php
│           ├── tickets/
│           │   ├── index.php
│           │   └── detail.php
│           ├── expenses/
│           │   ├── index.php
│           │   ├── create.php
│           │   └── edit.php
│           ├── settings/
│           │   └── index.php
│           └── logs/
│               ├── system.php
│               ├── access.php
│               ├── macrodroid.php
│               └── email.php
│
├── database/
│   └── vpn_service.sql
│
└── storage/
    └── logs/
        └── .gitkeep
```
