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

# 3.1. Áp dụng migration audit đơn hàng (an toàn khi chạy lại nhiều lần)
ORDER_AUDIT_MIGRATION="$APP_PATH/database/migrations/20260918_add_order_audit_users.sql"
if [ -f "$ORDER_AUDIT_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$ORDER_AUDIT_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất cập nhật cấu trúc audit đơn hàng.${NC}"
fi

# 3.2. Áp dụng migration AI Core (an toàn khi chạy lại nhiều lần)
AI_CORE_MIGRATION="$APP_PATH/database/migrations/20260927_add_ai_core_tables.sql"
if [ -f "$AI_CORE_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$AI_CORE_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất cập nhật cấu trúc AI Core.${NC}"
fi

# 3.3. Dọn bảng AI chết (P6: 7 bảng, P9: 2 bảng prompt — chạy lại an toàn)
AI_DROP_MIGRATION="$APP_PATH/database/migrations/20260928_drop_unused_ai_tables.sql"
AI_DROP_PROMPT_MIGRATION="$APP_PATH/database/migrations/20260928_drop_prompt_tables.sql"
for MIG in "$AI_DROP_MIGRATION" "$AI_DROP_PROMPT_MIGRATION"; do
    if [ -f "$MIG" ] && [ -f "$APP_PATH/.env" ]; then
        set -a
        . "$APP_PATH/.env"
        set +a
        MYSQL_PWD="${DB_PASSWORD:-}" mysql \
            -h "${DB_HOST:-127.0.0.1}" \
            -P "${DB_PORT:-3306}" \
            -u "${DB_USERNAME:-root}" \
            "${DB_DATABASE:-vpn_service}" < "$MIG"
    fi
done
if [ -f "$AI_DROP_MIGRATION" ]; then
    echo -e " ${GREEN}✔ Hoàn tất dọn bảng AI chết (7 bảng P6 + 2 bảng prompt P9).${NC}"
fi

# 3.4. Liên kết Bài Viết AI với hàng đợi đăng Fanpage (an toàn khi chạy lại)
OUTPUT_LINK_MIGRATION="$APP_PATH/database/migrations/20260930_add_output_id_scheduled_posts.sql"
if [ -f "$OUTPUT_LINK_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$OUTPUT_LINK_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất liên kết Bài Viết AI ↔ hàng đợi đăng Fanpage (output_id).${NC}"
fi

# 3.5. Cột khóa mạng 1 phút khi vượt số thiết bị (an toàn khi chạy lại)
DEVICE_LOCK_MIGRATION="$APP_PATH/database/migrations/20261005_add_network_locked_until.sql"
if [ -f "$DEVICE_LOCK_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$DEVICE_LOCK_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất thêm cột network_locked_until (khóa mạng 1 phút).${NC}"
fi

# 3.6. Cột số IP đang kết nối theo inbound (Phase 2, an toàn khi chạy lại)
INBOUND_CONN_MIGRATION="$APP_PATH/database/migrations/20261006_add_connected_devices.sql"
if [ -f "$INBOUND_CONN_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$INBOUND_CONN_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất thêm cột connected_devices (số IP đang kết nối inbound).${NC}"
fi

# 3.7. Tồn kho gói + cờ giữ suất (reserveForPurchase/restock — cần cho 3 nút đơn hàng)
STOCK_MIGRATION="$APP_PATH/database/migrations/20261002_add_vpn_plan_stock.sql"
if [ -f "$STOCK_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$STOCK_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất thêm cột stock_quantity/stock_reserved (tồn kho đơn hàng).${NC}"
fi

# 3.7.1. Hoan luot mua khi goi bi huy (vc_subscriptions.stock_state — idempotent)
SUB_STATE_MIGRATION="$APP_PATH/database/migrations/20261007_add_subscription_stock_state.sql"
if [ -f "$SUB_STATE_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$SUB_STATE_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất thêm cột stock_state (hoàn lượt mua khi hủy gói).${NC}"
fi

# 3.8. Cột error_msg cho vc_node_tasks (contract §4 — update_task_status)
TASK_ERR_MIGRATION="$APP_PATH/database/migrations/20261006_add_task_error_msg.sql"
if [ -f "$TASK_ERR_MIGRATION" ] && [ -f "$APP_PATH/.env" ]; then
    set -a
    . "$APP_PATH/.env"
    set +a
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "${DB_HOST:-127.0.0.1}" \
        -P "${DB_PORT:-3306}" \
        -u "${DB_USERNAME:-root}" \
        "${DB_DATABASE:-vpn_service}" < "$TASK_ERR_MIGRATION"
    echo -e " ${GREEN}✔ Hoàn tất thêm cột error_msg (báo lỗi task từ VPS).${NC}"
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