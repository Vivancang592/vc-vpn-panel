document.addEventListener('DOMContentLoaded', function () {
    // 1. Quản lý Màn hình chờ (Preloader)
    const preloader = document.getElementById('page-preloader');

    // Tự động ẩn Preloader khi trang đã tải xong
    if (preloader) {
        preloader.style.opacity = '0';
        setTimeout(() => {
            preloader.style.display = 'none';
        }, 300);
    }

    // Xử lý bật/tắt Preloader khi chuyển trang hoặc bấm nút xóa
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        // Bỏ qua các link nội bộ, javascript hoặc mở tab mới
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank') {
            return;
        }

        // Đợi kiểm tra sự kiện xem người dùng có bấm "Hủy" ở confirm() hay không
        setTimeout(() => {
            if (e.defaultPrevented) {
                // Nếu bấm "Hủy" (event bị hủy), ẩn Preloader ngay lập tức
                if (preloader) {
                    preloader.style.display = 'none';
                    preloader.style.opacity = '0';
                }
            } else if (preloader) {
                // Nếu bấm "OK" hoặc chuyển trang bình thường, hiển thị Preloader
                preloader.style.display = 'flex';
                preloader.style.opacity = '1';
            }
        }, 10);
    });

    // 2. Xử lý toggle Menu Ba Chấm (Action Dropdown)
    const actionBtns = document.querySelectorAll('.action-btn');

    function closeAllActionMenus() {
        document.querySelectorAll('.action-menu.show').forEach(menu => {
            menu.classList.remove('show');
            if (menu._triggerBtn) {
                menu._triggerBtn.classList.remove('active');
            } else if (menu.previousElementSibling) {
                menu.previousElementSibling.classList.remove('active');
            }
        });
    }

    actionBtns.forEach(btn => {
        if (btn.dataset.actionBound) return;
        btn.dataset.actionBound = '1';

        btn.addEventListener('click', function (e) {
            e.stopPropagation();

            // Lấy menu tương ứng (kề sau nút hoặc đã được gán trước đó)
            let currentMenu = this.nextElementSibling;
            if (!currentMenu || !currentMenu.classList.contains('action-menu')) {
                currentMenu = this._actionMenu;
            }

            if (!currentMenu) return;

            // Lưu tham chiếu 2 chiều giữa nút bấm và menu
            this._actionMenu = currentMenu;
            currentMenu._triggerBtn = this;

            const isCurrentlyShown = currentMenu.classList.contains('show');

            // Đóng tất cả các menu khác đang mở
            closeAllActionMenus();

            // Nếu menu chưa mở -> Đưa ra body và tính vị trí fixed chuẩn theo viewport
            if (!isCurrentlyShown) {
                if (currentMenu.parentNode !== document.body) {
                    document.body.appendChild(currentMenu);
                }

                currentMenu.classList.add('show');
                this.classList.add('active');

                const rect = this.getBoundingClientRect();
                currentMenu.style.top = (rect.bottom + 4) + 'px';
                currentMenu.style.left = 'auto';
                currentMenu.style.right = (window.innerWidth - rect.right) + 'px';
            }
        });
    });

    if (!window._actionDropdownEventsBound) {
        window._actionDropdownEventsBound = true;
        // Bấm ra ngoài vùng menu, cuộn trang hoặc đổi kích thước màn hình -> Tự động đóng tất cả dropdown
        document.addEventListener('click', closeAllActionMenus);
        window.addEventListener('scroll', closeAllActionMenus, true);
        window.addEventListener('resize', closeAllActionMenus);
    }

    // 3. Xử lý Tự động ẩn và Nút đóng (✕) cho Thông Báo Flash
    const alerts = document.querySelectorAll('.glass-alert');
    alerts.forEach(alert => {
        // Tự động ẩn sau 4 giây
        const timer = setTimeout(() => {
            dismissAlert(alert);
        }, 4000);

        // Bấm nút ✕ để tắt ngay lập tức
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                clearTimeout(timer);
                dismissAlert(alert);
            });
        }
    });

    function dismissAlert(alertEl) {
        alertEl.style.transition = 'opacity 0.4s ease, transform 0.4s ease, margin 0.4s ease, padding 0.4s ease';
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            alertEl.remove();
        }, 400);
    }
});

/* Script xử lý chuyển Tab & Lưu trạng thái cho trang Settings */
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.settings-tab-btn');
    const tabPanes = document.querySelectorAll('.settings-tab-pane');
    
    // Chỉ chạy script nếu đang ở trang có chứa settings-tabs
    if (tabBtns.length > 0 && tabPanes.length > 0) {
        const activeTabId = localStorage.getItem('vc_active_settings_tab') || 'tab-general';
        
        function activateTab(tabId) {
            tabBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.target === tabId);
            });
            tabPanes.forEach(pane => {
                pane.classList.toggle('active', pane.id === tabId);
            });
            localStorage.setItem('vc_active_settings_tab', tabId);
        }

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                activateTab(this.dataset.target);
            });
        });

        activateTab(activeTabId);
    }
});

function getSubUrl(uuid) {
    return window.location.origin + '/sub?token=' + uuid;
}

function copySubLink(uuid) {
    const url = getSubUrl(uuid);
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(() => {
            alert('Đã sao chép liên kết đăng ký vào bộ nhớ tạm!');
        }).catch(() => {
            fallbackCopyText(url);
        });
    } else {
        fallbackCopyText(url);
    }
}

function fallbackCopyText(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        alert('Đã sao chép liên kết đăng ký vào bộ nhớ tạm!');
    } catch (err) {
        alert('Không thể tự động sao chép. Vui lòng thử lại!');
    }
    document.body.removeChild(textArea);
}

function openQrModal(uuid) {
    const url = getSubUrl(uuid);
    const qrImg = document.getElementById('qrCodeImg');
    if (qrImg) {
        qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(url);
    }
    const modal = document.getElementById('qrModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeQrModal() {
    const modal = document.getElementById('qrModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function updatePriceHint(selectEl) {
    if (!selectEl) return;
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const price = selectedOption ? selectedOption.getAttribute('data-price') : null;
    const amountInput = document.getElementById('amount_input');
    if (price !== null && amountInput) {
        amountInput.value = price;
    }
}

function toggleSelectAllNodes(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.node-checkbox');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
}

function confirmBulkDeleteNodes() {
    const selected = document.querySelectorAll('.node-checkbox:checked');
    if (selected.length === 0) {
        alert('Vui lòng chọn ít nhất một nút kết nối để xóa!');
        return;
    }
    if (confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn ${selected.length} nút kết nối đã chọn?`)) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

function generatePlanCode(prefix = 'LS') {
    const codeInput = document.getElementById('code');
    if (codeInput) {
        const randomNum = Math.floor(100000 + Math.random() * 900000);
        codeInput.value = prefix + randomNum;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const chartCanvas = document.getElementById('revenueComparisonChart');
    if (chartCanvas && typeof Chart !== 'undefined') {
        const ctx = chartCanvas.getContext('2d');
        const currentMonthData = JSON.parse(chartCanvas.dataset.current || '[]');
        const lastMonthData = JSON.parse(chartCanvas.dataset.last || '[]');
        const selectedMonth = chartCanvas.dataset.month;
        const selectedYear = chartCanvas.dataset.year;
        const currencySymbol = chartCanvas.dataset.symbol || 'đ';
        const daysLabels = Array.from({ length: 31 }, (_, i) => 'Ngày ' + (i + 1));

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: daysLabels,
                datasets: [
                    {
                        label: `Tháng ${selectedMonth}/${selectedYear}`,
                        data: currentMonthData,
                        borderColor: '#007aff',
                        backgroundColor: 'rgba(0, 122, 255, 0.12)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3
                    },
                    {
                        label: 'Tháng Liền Trước',
                        data: lastMonthData,
                        borderColor: '#8e8e93',
                        backgroundColor: 'rgba(142, 142, 147, 0.08)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--ios-text').trim() || '#1c1c1e',
                            font: { weight: '600' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) {
                                    label += currencySymbol + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.08)' },
                        ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--ios-text-secondary').trim() || '#8e8e93' }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.08)' },
                        ticks: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--ios-text-secondary').trim() || '#8e8e93',
                            callback: function (value) {
                                return currencySymbol + new Intl.NumberFormat('en-US', { notation: 'compact' }).format(value);
                            }
                        }
                    }
                }
            }
        });
    }
});