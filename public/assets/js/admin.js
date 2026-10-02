/* =========================================================================
 * ADMIN.JS — khởi tạo trang admin.
 *
 * Trang admin đi lại bằng "partial-load": admin.js bắt link/GET trong khu
 * /admin, tải fragment từ server (header X-VC-Partial) và chỉ thay vùng
 * .admin-content + sidebar → không reload cả trang, không nháy màn hình,
 * giữ nguyên vị trí cuộn. Mọi code khởi tạo trang PHẢI nằm trong một hàm
 * push vào window.vcPageInits — vcInitPageContent(root) sẽ chạy lại toàn
 * bộ sau MỖI lần sơn nội dung. Code đặt ngoài hàm sẽ chỉ chạy đúng 1 lần
 * rồi "chết" khi điều hướng nội bộ.
 * ========================================================================= */
window.vcPageInits = window.vcPageInits || [];

/* ===== 1. Menu ba chấm (action dropdown) trên bảng dữ liệu ===== */
window.vcPageInits.push(function vcInitActionDropdown(root) {
    'use strict';
    // 1. Xử lý toggle Menu Ba Chấm (Action Dropdown)
    const actionBtns = root.querySelectorAll('.action-btn');

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
                const viewportHeight = window.visualViewport?.height || document.documentElement.clientHeight;
                const spaceAbove = rect.top - 8;

                currentMenu.style.maxHeight = Math.max(0, spaceAbove) + 'px';
                currentMenu.style.top = 'auto';
                currentMenu.style.bottom = Math.max(8, viewportHeight - rect.top + 4) + 'px';
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

});

/* ===== 2. Thông báo flash: tự ẩn sau 4 giây, nút ✕ tắt ngay ===== */
window.vcPageInits.push(function vcInitGlassAlerts(root) {
    'use strict';
    const alerts = root.querySelectorAll('.glass-alert');
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

/* ===== Chuyển tab trang Cài đặt (giữ lựa chọn qua localStorage) ===== */
window.vcPageInits.push(function vcInitSettingsTabs(root) {
    'use strict';
    const tabBtns = root.querySelectorAll('.settings-tab-btn');
    const tabPanes = root.querySelectorAll('.settings-tab-pane');
    
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

/* ===== Biểu đồ doanh thu (dashboard) — chart.js tải theo trang ===== */
window.vcPageInits.push(function vcInitDashboardChart(root) {
    'use strict';
    const chartCanvas = root.querySelector('#revenueComparisonChart');
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

/* =========================================================================
 * PARTIAL-LOAD — điều hướng nội bộ trong khu /admin KHÔNG reload cả trang.
 *
 * • Chạy NGAY lúc file được parse (trước DOMContentLoaded) để e.preventDefault
 *   được set đồng bộ → app.js không kích hoạt preloader, không nháy màn hình.
 * • Server (layouts/admin.php) trả JSON fragment {title, html, sidebar} khi
 *   có header X-VC-Partial; client chỉ thay .admin-content + sidebar.
 * • Cùng trang (lọc/phân trang dạng GET) → GIỮ nguyên vị trí cuộn.
 *   Khác trang → khôi phục theo bộ nhớ nội bộ / về đầu trang.
 * • History back/forward (popstate) cũng tải fragment, có fallback nạp
 *   lại đầy đủ khi lỗi mạng / redirect đăng nhập / phản hồi không phải JSON.
 * ========================================================================= */
(function () {
    'use strict';
    if (!window.fetch || !window.URL) return;
    const startsWithAdmin = (p) => p === '/admin' || p.indexOf('/admin/') === 0;
    if (!startsWithAdmin(location.pathname)) return;

    let vcKey = location.pathname + location.search;
    let controller = null;
    let navGen = 0;
    let progressEl = null;
    const scrollMem = Object.create(null);

    // Thư viện đã nạp qua <script src> — KHÔNG tái-evaluate khi quay lại
    // trang: re-run Chart.js/TinyMCE thay constructor trên window làm mất
    // instance đang gắn với canvas/DOM.
    const loadedSrcs = new Set();
    const recordLoadedSrcs = () => {
        Array.prototype.forEach.call(document.querySelectorAll('script[src]'), (s) => {
            if (s.src) loadedSrcs.add(s.src);
        });
    };
    recordLoadedSrcs();

    const getScroller = () =>
        document.querySelector('.admin-main') || document.scrollingElement || document.documentElement;
    const saveScroll = () => { scrollMem[vcKey] = getScroller().scrollTop; };

    // Vị trí cuộn hiện tại — ghi cả khi idle để navigate luôn có điểm chụp.
    saveScroll();
    window.addEventListener('scroll', saveScroll, { passive: true, capture: true });

    /* Thanh tiến trình mảnh phía trên + fade nội dung: phản hồi cảm giác
       "đang tải" mà không cần preloader che cả màn hình. */
    const progress = {
        start() {
            if (!progressEl) {
                progressEl = document.createElement('div');
                progressEl.className = 'vc-partial-progress';
                document.body.appendChild(progressEl);
            }
            progressEl.classList.remove('vc-progress-done');
            void progressEl.offsetWidth; // restart animation
            progressEl.classList.add('vc-progress-run');
        },
        done() {
            if (progressEl) progressEl.classList.add('vc-progress-done');
        }
    };

    function eligibleUrl(href) {
        if (!href || /^(#|javascript:|mailto:|tel:)/i.test(href)) return null;
        let u;
        try { u = new URL(href, location.href); } catch (e) { return null; }
        if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
        if (u.origin !== location.origin) return null;
        if (!startsWithAdmin(u.pathname)) return null;
        if (u.pathname === location.pathname && u.search === location.search && u.hash) return null; // anchor cùng trang
        return u;
    }

    // Chạy lại <script> trong fragment theo đúng thứ tự (src chờ load xong
    // mới chạy script kế tiếp) — script trong template server KHÔNG tự chạy
    // khi gán qua innerHTML.
    function runScripts(root) {
        return new Promise((resolve) => {
            const nodes = Array.prototype.slice.call(root.querySelectorAll('script'));
            let idx = 0;
            const step = () => {
                while (idx < nodes.length) {
                    const old = nodes[idx++];
                    if (!old.parentNode) continue;
                    const s = document.createElement('script');
                    for (let i = 0; i < old.attributes.length; i++) {
                        const a = old.attributes[i];
                        if (a.name !== 'src') s.setAttribute(a.name, a.value); // src gán riêng bên dưới
                    }
                    if (old.src) {
                        if (loadedSrcs.has(old.src)) continue; // lib đã chạy → giữ nguyên instance
                        s.async = false; // đặt TRƯỚC src để giữ đúng thứ tự thực thi
                        s.onload = () => { loadedSrcs.add(old.src); step(); };
                        s.onerror = step; // CDN lỗi → vẫn chạy tiếp các script sau
                        s.src = old.src;
                        old.parentNode.replaceChild(s, old);
                        return; // step tiếp tục từ onload/onerror
                    }
                    s.textContent = old.textContent; // inline script → giữ nguyên code
                    try { old.parentNode.replaceChild(s, old); } catch (e) { /* script lỗi → bỏ qua */ }
                }
                resolve();
            };
            try { step(); } catch (e) { resolve(); }
        });
    }

    function applyFragment(json, url, push) {
        const u = new URL(url);
        const prevPathname = location.pathname;
        const newKey = u.pathname + u.search;
        const content = document.querySelector('.admin-content');
        if (!content) { location.href = url; return; }

        saveScroll(); // vị trí trang CŨ trước khi thay DOM
        recordLoadedSrcs(); // nhớ script src đang có trong trang cũ (trước khi bị gỡ)

        // Dọn phần tử tạm của trang cũ (menu 3 chấm đã đẩy ra body)
        document.querySelectorAll('body > .action-menu').forEach((m) => m.remove());
        // Báo view cũ dọn listener cấp document (vd phím Escape của popup)
        try { document.dispatchEvent(new CustomEvent('vc:partial-leave')); } catch (e) { /* noop */ }
        // TinyMCE (trang bài viết) gắn vào DOM → remove trước khi thay.
        // v6 KHÔNG có tinymce.editors (undefined) → chỉ check window.tinymce,
        // remove() không đối số = dọn toàn bộ (an toàn khi rỗng)
        try { if (window.tinymce) window.tinymce.remove(); } catch (e) { /* noop */ }

        // Thay sidebar + vùng nội dung (navbar/footer giữ nguyên → listener
        // profile dropdown / chatbot không bị mất)
        const sidebar = document.querySelector('.admin-sidebar');
        if (sidebar && typeof json.sidebar === 'string') sidebar.innerHTML = json.sidebar;
        content.classList.add('vc-fading'); // opacity 0 cùng frame với nội dung mới
        content.innerHTML = json.html || '';
        if (json.title) document.title = json.title;

        if (push) history.pushState({ vcKey: newKey }, '', newKey);
        else history.replaceState({ vcKey: newKey }, '', newKey);
        vcKey = newKey;

        // Cuộn: cùng pathname hoặc cùng nhóm section (vd /admin/logs ↔
        // /admin/logs/system — tab con) → GIỮ nguyên; khác nhóm → khôi phục
        // vị trí đã nhớ hoặc về đầu.
        const scroller = getScroller();
        const sectionOf = (p) => '/' + (p.split('/').filter(Boolean).slice(0, 2).join('/') || '');
        let y = 0;
        if (sectionOf(u.pathname) === sectionOf(prevPathname)) y = scroller.scrollTop;
        else if (typeof scrollMem[newKey] === 'number') y = scrollMem[newKey];
        scroller.scrollTop = y;
        scrollMem[newKey] = y;

        // Preloader (phòng thủ) + dọn key sessionStorage của app.js để
        // lần nạp đầy đủ tới không ghi đè vị trí vừa khôi phục.
        const pre = document.getElementById('page-preloader');
        if (pre) pre.classList.add('preloader-hidden');
        try {
            sessionStorage.removeItem('vcScroll:' + prevPathname);
            sessionStorage.removeItem('vcScroll:' + u.pathname);
        } catch (e) { /* noop */ }

        // Fade-in nội dung mới (2 frame để browser sơn trạng thái opacity 0 trước)
        requestAnimationFrame(() => {
            requestAnimationFrame(() => content.classList.remove('vc-fading'));
        });

        runScripts(content).then(() => {
            try { window.vcInitPageContent(content); } catch (e) { console.error('[vc] init sau partial:', e); }
            // Popup lớp trên (tox-tinymce-aux…) còn sót từ trang cũ
            document.querySelectorAll('.tox-tinymce-aux').forEach((n) => n.remove());
        });
    }

    function navigate(url, push) {
        if (controller) controller.abort();
        const myGen = ++navGen;
        controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        progress.start();
        fetch(url, {
            headers: { 'X-VC-Partial': '1', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            redirect: 'follow',
            signal: controller ? controller.signal : undefined
        }).then((resp) => {
            if (myGen !== navGen) return null;
            if (resp.redirected) { location.href = resp.url; return null; } // hết quyền / đăng nhập
            const ct = resp.headers.get('content-type') || '';
            if (ct.indexOf('application/json') === -1) { location.href = url; return null; }
            return resp.json();
        }).then((json) => {
            if (myGen !== navGen || !json) return;
            if (!json.ok) { location.href = url; return; }
            applyFragment(json, url, push);
            progress.done();
        }).catch(() => {
            if (myGen !== navGen) return; // bị abort bởi lần điều hướng mới hơn
            location.href = url; // lỗi mạng / parse → nạp lại đầy đủ
        });
    }

    // --- Bắt click link trong /admin ---
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        const link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        if (link.hasAttribute('data-no-partial') || link.hasAttribute('data-no-loader')) return;
        const u = eligibleUrl(link.getAttribute('href'));
        if (!u) return;
        e.preventDefault(); // đồng bộ → app.js thấy defaultPrevented, không hiện preloader
        navigate(u.href, true);
    });

    // --- Bắt submit form GET (bộ lọc / tìm / phân trang) ---
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        if ((form.getAttribute('method') || 'get').toUpperCase() !== 'GET') return;
        if (form.hasAttribute('data-no-partial') || form.hasAttribute('data-no-loader')) return;
        if (form.target && form.target !== '_self') return;
        const action = form.getAttribute('action') || location.pathname;
        let u;
        try { u = new URL(action, location.href); } catch (err) { return; }
        if (u.origin !== location.origin || !startsWithAdmin(u.pathname)) return;
        let qs = '';
        try { qs = new URLSearchParams(new FormData(form)).toString(); } catch (err) { return; }
        e.preventDefault();
        navigate(location.origin + u.pathname + (qs ? '?' + qs : ''), true);
    });

    // --- Back/Forward: tải fragment cho URL history vừa quay lại ---
    window.addEventListener('popstate', (e) => {
        const key = (e.state && e.state.vcKey) || (location.pathname + location.search);
        if (key === vcKey) return;
        navigate(location.href, false);
    });

    history.replaceState({ vcKey }, '', location.href);
})();

/* Gọi toàn bộ hàm khởi tạo trang — chạy lúc trang tải đầy đủ và sau MỖI
   lần partial-load (root = .admin-content vừa được thay). */
window.vcInitPageContent = function (root) {
    root = root || document;
    (window.vcPageInits || []).forEach((fn) => {
        try { fn(root); } catch (e) { console.error('[vc] vcInitPageContent:', e); }
    });
    // Binder từ app.js (idempotent qua dataset guard)
    try { if (window.vcBindActionMenus) window.vcBindActionMenus(root); } catch (e) { /* noop */ }
    try { if (window.vcBindCopyButtons) window.vcBindCopyButtons(root); } catch (e) { /* noop */ }
};

document.addEventListener('DOMContentLoaded', () => window.vcInitPageContent(document));