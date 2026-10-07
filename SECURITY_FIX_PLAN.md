# Kế hoạch vá lỗi bảo mật — vc-vpn-2027

> Thứ tự cập nhật bắt buộc. Đánh dấu `[x]` khi hoàn tất từng mục, `[~]` khi đang làm.
> Chỉ bắt đầu mục tiếp theo khi mục trước đã hoàn tất và kiểm tra xong.

## Giai đoạn 1 — CRITICAL

- [x] **#1 Payment webhook secret mặc định hardcode** — ✅ Hoàn tất
  - File: `config/app.php` (line 28) — bỏ literal fallback secret mặc định hardcode trong source
  - File: `app/Controllers/Api/PaymentController.php` (lines 88-98) — `validKeys` fail-closed *(đã sẵn có: `empty → 403`, chỉ cần bỏ default)*
  - File: `vc_install.sh`, `.env.example` — sinh/expose `MACRODROID_SECRET`

## Giai đoạn 2 — HIGH

- [x] **#2 Upload ảnh admin ghi `.php` vào webroot → RCE** — ✅ Hoàn tất
  - File: `app/AI/Assets/AssetManager.php` (`extensionFor()` lines 356-363) — chỉ lấy extension từ MIME map
  - File: `app/Controllers/Admin/AiVideoController.php` (lines 121-125) — kiểm tra ảnh thật (polyglot)
  - File: `public/uploads/.htaccess` (mới) — `php_flag engine off`

- [x] **#3 OTP đặt lại mật khẩu brute force** — ✅ Hoàn tất
  - File: `app/Controllers/AuthController.php` (`forgotPassword()` lines 518-560) — đếm lỗi, hủy OTP sau ≤5 lần sai, khóa theo email

## Giai đoạn 3 — MEDIUM

- [x] **#4 Cron secret mặc định hardcode** — ✅ Hoàn tất
  - File: `app/Controllers/CronController.php` (lines 53, 311) — bỏ fallback secret mặc định hardcode, fail-closed
  - Bổ sung: header `X-Cron-Key`, sinh key trong `vc_install.sh`/`vc_update.sh`, cập nhật README

- [x] **#5 Fanpage webhook fail-open** — ✅ Hoàn tất
  - File: `app/Services/FanpageService.php` (`validateSignature()` lines 604-618) — return false khi secret rỗng

## Giai đoạn 4 — LOW

- [x] **#6 Xóa thông báo bằng GET (CSRF)** — ✅ Hoàn tất
  - File: `routes/web.php` (line 94) — chuyển sang POST + CSRF
  - File: `app/Controllers/Admin/DashboardController.php` (`deleteNotification`)
  - File: `resources/views/admin/notifications/index.php` — nút X thành form POST (giữ nguyên giao diện)

## Kiểm tra & dọn dẹp

- [x] Lint `php -l` toàn bộ file đã sửa — ✅ 9/9 file pass; `bash -n` 2 script pass
- [x] Rà soát lại không đổi tên biến/hàm/file, không đổi UI/UX, không đổi luồng nghiệp vụ — ✅ (`git diff`: 13 files, 86+/28-)
- [x] Dọn file tạm, báo cáo cuối — ✅
- [x] **Bổ sung:** Bỏ theo dõi `.env` khỏi git (`git rm --cached .env`) — file đang bị track dù `.gitignore` có rule → secret sinh ra sẽ bị commit nhầm. Local giữ nguyên secret; `vc_update.sh` đã backup/restore `.env` quanh `git pull` nên deploy an toàn

## ✅ HOÀN TẤT TOÀN BỘ (06/06 mục)

**Kết quả kiểm tra cuối:**
- `php -l`: 9/9 file PHP đạt (config/app.php, AssetManager, AiVideoController, DashboardController, AuthController, CronController, FanpageService, routes/web.php, admin/notifications/index.php)
- `bash -n`: vc_install.sh, vc_update.sh đạt
- Không còn secret hardcode trong mã nguồn (chỉ còn trong lịch sử git cũ — nên rotate key ở hạ tầng)
- Tất cả route thay đổi trạng thái còn lại đều là POST + CSRF
- File tạm đã dọn; `.env` local đã sinh 2 secret (gitignored)
