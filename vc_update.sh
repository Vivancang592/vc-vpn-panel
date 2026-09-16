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
        git -C "$APP_PATH" fetch --all
        git -C "$APP_PATH" reset --hard origin/main || git -C "$APP_PATH" pull
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
composer install --no-dev --optimize-autoloader --working-dir="$APP_PATH" > /dev/null 2>&1
echo -e " ${GREEN}✔ Hoàn tất cập nhật thư viện PHP.${NC}"

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