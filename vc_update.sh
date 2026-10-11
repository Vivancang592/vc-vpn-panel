#!/bin/bash
# vc_update.sh - Optimized & Clean Update CLI Interface

set -Eeuo pipefail

# Màu sắc hiển thị terminal
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
CYAN="\033[0;36m"
NC="\033[0m" # No Color

APP_PATH="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

clear
echo -e "${CYAN}=================================================${NC}"
echo -e "${GREEN}      CẬP NHẬT TỰ ĐỘNG VC_VPN_WEB LÊN AAPANEL    ${NC}"
echo -e "${CYAN}=================================================${NC}"
echo -e "Thư mục hiện tại: ${YELLOW}$APP_PATH${NC}\n"

# 1. Kiểm tra quyền root và môi trường
if [[ "$(id -u)" -ne 0 ]]; then
    echo -e "${RED}Lỗi: Vui lòng chạy script bằng quyền root: sudo ./vc_update.sh${NC}"
    exit 1
fi

if ! id www >/dev/null 2>&1; then
    echo -e "${RED}Lỗi: Không tìm thấy user 'www' của aaPanel.${NC}"
    exit 1
fi

# 2. Cập nhật mã nguồn từ Git (nếu có .git)
echo -e "${CYAN}[1/3] Cập nhật mã nguồn...${NC}"
if [ -d "$APP_PATH/.git" ]; then
    if command -v git &> /dev/null; then
        ENV_BACKUP=""
        if [ -f "$APP_PATH/.env" ]; then
            ENV_BACKUP="$(mktemp)"
            cp "$APP_PATH/.env" "$ENV_BACKUP"
        fi
        git -C "$APP_PATH" fetch --all
        git -C "$APP_PATH" reset --hard origin/main || git -C "$APP_PATH" pull
        if [ -n "$ENV_BACKUP" ]; then
            cp "$ENV_BACKUP" "$APP_PATH/.env"
            rm -f "$ENV_BACKUP"
        fi
        echo -e " ${GREEN}✔ Cập nhật code từ Git thành công.${NC}"
    else
        echo -e " ${YELLOW}ℹ Hệ thống chưa cài Git, bỏ qua git pull.${NC}"
    fi
else
    echo -e " ${YELLOW}ℹ Không tìm thấy thư mục .git, bỏ qua git pull.${NC}"
fi

# 3. Cập nhật các thư viện qua Composer
echo -e "${CYAN}[2/3] Cập nhật thư viện Composer...${NC}"
if ! command -v composer &> /dev/null; then
    php -r "copy('https://getcomposer.org/installer', '$APP_PATH/composer-setup.php');"
    php "$APP_PATH/composer-setup.php" --install-dir=/usr/local/bin --filename=composer
    rm -f "$APP_PATH/composer-setup.php"
fi
if ! composer install --no-dev --optimize-autoloader --working-dir="$APP_PATH"; then
    echo -e " ${RED}Lỗi: Không thể cài thư viện Composer, nên chưa thể tạo mã QR liên kết đăng ký.${NC}"
    exit 1
fi
echo -e " ${GREEN}✔ Hoàn tất cập nhật thư viện PHP.${NC}"

# 3.0. Sinh CRON_SECRET_KEY nếu chưa có (thay thế secret mặc định đã bị loại bỏ vì công khai)
if [ -f "$APP_PATH/.env" ] && ! grep -q "^CRON_SECRET_KEY=..*" "$APP_PATH/.env"; then
    CRON_SECRET_KEY_VALUE="$(php -r 'echo bin2hex(random_bytes(32));')"
    printf '\n# Secret xác thực CronJob (cập nhật URL crontab cho khớp)\nCRON_SECRET_KEY=%s\n' "$CRON_SECRET_KEY_VALUE" >> "$APP_PATH/.env"
    unset CRON_SECRET_KEY_VALUE
    echo -e " ${GREEN}✔ Đã sinh CRON_SECRET_KEY trong .env — hãy cập nhật URL crontab cho khớp.${NC}"
fi

# 3.1. Áp dụng toàn bộ migration trong database/migrations (idempotent — an toàn chạy lại)
MIGRATIONS_DIR="$APP_PATH/database/migrations"
if [ -f "$APP_PATH/.env" ] && [ -d "$MIGRATIONS_DIR" ]; then
    if ! command -v mysql &> /dev/null; then
        echo -e " ${RED}Lỗi: Không tìm thấy lệnh mysql nên không thể chạy migration.${NC}"
        exit 1
    fi
    set -a
    . "$APP_PATH/.env"
    set +a

    MIG_TOTAL=0
    MIG_FAILED=""
    while IFS= read -r MIG_FILE; do
        MIG_TOTAL=$((MIG_TOTAL + 1))
        echo -e " ${CYAN}→ Migration:${NC} $(basename "$MIG_FILE")"
        if ! MYSQL_PWD="${DB_PASSWORD:-}" mysql \
            -h "${DB_HOST:-127.0.0.1}" \
            -P "${DB_PORT:-3306}" \
            -u "${DB_USERNAME:-root}" \
            "${DB_DATABASE:-vpn_service}" < "$MIG_FILE"; then
            MIG_FAILED="$(basename "$MIG_FILE")"
            break
        fi
    done < <(find "$MIGRATIONS_DIR" -maxdepth 1 -type f -name '*.sql' | LC_ALL=C sort)

    if [ -n "$MIG_FAILED" ]; then
        echo -e " ${RED}✘ Migration thất bại: ${MIG_FAILED}${NC}"
        exit 1
    fi
    echo -e " ${GREEN}✔ Hoàn tất migration (${MIG_TOTAL} file, idempotent — chạy lại an toàn).${NC}"
else
    echo -e " ${YELLOW}ℹ Không tìm thấy .env hoặc thư mục database/migrations — bỏ qua migration.${NC}"
fi

# 4. Thiết lập lại phân quyền thư mục
echo -e "${CYAN}[3/3] Đặt lại phân quyền bảo mật thư mục...${NC}"
mkdir -p "$APP_PATH/storage/logs"

chown -R www:www "$APP_PATH"
find "$APP_PATH" -type d -exec chmod 755 {} \;
find "$APP_PATH" -type f -exec chmod 644 {} \;
chmod 640 "$APP_PATH/.env" 2>/dev/null || true
chmod +x "$APP_PATH/vc_install.sh" "$APP_PATH/vc_update.sh" 2>/dev/null || true
chmod -R 775 "$APP_PATH/storage"
echo -e " ${GREEN}✔ Phân quyền bảo mật thành công.${NC}"

echo -e "\n${CYAN}================================================="
echo -e "${GREEN}        CẬP NHẬT MÃ NGUỒN THÀNH CÔNG 100%!       ${NC}"
echo -e "${CYAN}=================================================${NC}"