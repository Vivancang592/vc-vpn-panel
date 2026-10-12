/* ===== GIỮ VỊ TRÍ CUỘN QUA POST → REDIRECT + ĐỔI FILTER =====
 * Nút không đổi tab/trang (Tạo ảnh, Xóa, Lưu lịch...)
 * đều POST rồi redirect về đúng URL → trang load lại đúng vị trí cũ,
 * không nhảy vọt lên đầu. Nút LỌC / TÌM / PHÂN TRANG (GET đổi query)
 * cũng giữ chỗ: key chỉ theo pathname nên trang đích khác search vẫn
 * khôi phục được. Bỏ qua khi URL có #anchor (đã có đích cuộn riêng).
 * Lưu ý: trang admin cuộn trong container `.admin-main` (html/body ẩn chữ),
 * nên phải đọc/ghi scrollTop của container chứ không phải window.scrollY. */
(function () {
    const scrollKey = 'vcScroll:' + location.pathname; // bỏ search: lọc/phân trang đổi query vẫn giữ chỗ

    function getScroller() {
        const main = document.querySelector('.admin-main');
        if (main && main.scrollHeight > main.clientHeight + 4) return main;
        return document.scrollingElement || document.documentElement;
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        try {
            const sb = document.querySelector('.admin-sidebar');
            sessionStorage.setItem(scrollKey, JSON.stringify({ y: getScroller().scrollTop, s: sb ? sb.scrollTop : 0, t: Date.now() }));
        } catch (err) { /* sessionStorage lỗi → bỏ qua */ }
    });

    /* Lưu vị trí khi bấm link nội bộ (Thêm/Hủy/Phân trang dạng <a>): trang đích
     * hoặc lúc quay lại sẽ khôi phục đúng chỗ đang xem dù không có POST. */
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        const link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) return;
        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        if (link.getAttribute('target') === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-loader')) return;
        if (/^[a-z][a-z\d+.-]*:/i.test(href) && !/^https?:/i.test(href)) return; // scheme ngoài http(s)
        try {
            const sb = document.querySelector('.admin-sidebar');
            sessionStorage.setItem(scrollKey, JSON.stringify({ y: getScroller().scrollTop, s: sb ? sb.scrollTop : 0, t: Date.now() }));
        } catch (err) { /* sessionStorage lỗi → bỏ qua */ }
        setTimeout(function () { // handler khác hủy điều hướng → bỏ key vừa lưu
            if (e.defaultPrevented) {
                try { sessionStorage.removeItem(scrollKey); } catch (err) { /* bỏ qua */ }
            }
        }, 0);
    });

    try {
        const saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
        sessionStorage.removeItem(scrollKey); // chỉ dùng đúng 1 lần
        if (saved && !location.hash && (saved.y > 0 || saved.s > 0)
            && (Date.now() - (saved.t || 0)) < 1800000) { // giữ vị trí trong 30 phút
            const applyRestore = function () {
                getScroller().scrollTop = saved.y;
                if (typeof saved.s === 'number' && saved.s >= 0) { // giữ vị trí menu sidebar
                    const sb = document.querySelector('.admin-sidebar');
                    if (sb) sb.scrollTop = saved.s;
                }
            };
            /* Khôi phục sau 2 frame — chờ lần sơn đầu + layout ổn định.
             * Set ngay lúc DOM vừa dựng có thể trúng container chưa đủ chiều
             * cao → mất mục tiêu rồi nhảy lô-cô khi resize sau đó. */
            const restore = function () {
                requestAnimationFrame(function () {
                    requestAnimationFrame(applyRestore);
                });
                setTimeout(applyRestore, 250); // fallback nếu rAF bị chặn
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', restore);
            } else {
                restore();
            }
        }
    } catch (err) { /* bỏ qua */ }
})();

/* =========================================================================
 * PARTIAL-LOAD — điều hướng nội bộ (trang public + khu user) KHÔNG reload.
 *   • Chạy NGAY lúc parse (trước DOMContentLoaded) để e.preventDefault set
 *     đồng bộ → handler preloader của app.js thấy defaultPrevented → không
 *     hiện màn hình chờ, không nháy màn hình.
 *   • Server (layouts/app.php) trả JSON fragment {title, html, sidebar} khi
 *     có header X-VC-Partial; client chỉ thay .admin-content + sidebar.
 *   • Khu /admin do admin.js đảm nhiệm (module bên trong tự nhường).
 *   • Cùng trang (lọc/phân trang GET) → GIỮ vị trí cuộn; khác trang →
 *     khôi phục theo bộ nhớ nội bộ / về đầu trang.
 * ========================================================================= */
window.vcPageInits = window.vcPageInits || [];

/* Gọi toàn bộ hàm khởi tạo trang — chạy lúc trang tải đầy đủ và sau MỖI
   lần partial-load (root = .admin-content vừa được thay). admin.js (trang
   admin) sẽ ghi đè bản này — logic tương đương. */
window.vcInitPageContent = function (root) {
    root = root || document;
    (window.vcPageInits || []).forEach((fn) => {
        try { fn(root); } catch (e) { console.error('[vc] vcInitPageContent:', e); }
    });
    // Binder từ app.js (idempotent qua dataset guard)
    try { if (window.vcBindActionMenus) window.vcBindActionMenus(root); } catch (e) { /* noop */ }
    try { if (window.vcBindCopyButtons) window.vcBindCopyButtons(root); } catch (e) { /* noop */ }
};

(function () {
    'use strict';
    if (!window.fetch || !window.URL) return;
    const startsWithAdmin = (p) => p === '/admin' || p.indexOf('/admin/') === 0;
    if (startsWithAdmin(location.pathname)) return; // khu admin → admin.js lo

    /* Partial thất bại → đã nạp lại đầy đủ → GIỮ vị trí trang cũ. */
    try {
        if (sessionStorage.getItem('vcPartialFallback') === '1') {
            sessionStorage.removeItem('vcPartialFallback');
            const y = Number(sessionStorage.getItem('vcFallbackY') || 0);
            sessionStorage.removeItem('vcFallbackY');
            if (y > 0) {
                const applyY = function () {
                    const main = document.querySelector('.admin-main');
                    const el = (main && main.scrollHeight > main.clientHeight + 4)
                        ? main : (document.scrollingElement || document.documentElement);
                    el.scrollTop = y;
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function () {
                        requestAnimationFrame(function () { requestAnimationFrame(applyY); });
                    });
                } else {
                    applyY();
                }
            }
        }
    } catch (e) { /* noop */ }

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

    /* Chỉ nhận URL cùng origin, KHÔNG thuộc các nhánh phải nạp lại đầy đủ:
     * khu /admin (admin.js), trang auth (layouts/auth.php khác khung),
     * /client (redirect 302 ra ngoài), JSON endpoint (/orders/status...). */
    function eligibleUrl(href) {
        if (!href || /^(#|javascript:|mailto:|tel:)/i.test(href)) return null;
        let u;
        try { u = new URL(href, location.href); } catch (e) { return null; }
        if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
        if (u.origin !== location.origin) return null;
        const p = u.pathname;
        if (startsWithAdmin(p)) return null;
        if (u.pathname === location.pathname && u.search === location.search && u.hash) return null; // anchor cùng trang
        if (/^\/(login|register|forgot-password|logout|client)(\/|$)/.test(p)) return null;
        if (p.indexOf('/auth/') === 0) return null;
        if (p === '/orders/status' || p === '/payments/status') return null; // JSON endpoint GET
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
        const prevKey = vcKey; // key trang CŨ — vcKey đổi sang newKey trước phần cuộn
        const newKey = u.pathname + u.search;
        const content = document.querySelector('.admin-content');
        if (!content) { location.href = url; return; }

        // Trang cũ ↔ trang mới không cùng khung (vd guest home không sidebar
        // ↔ khu user có sidebar) → không thể vá bằng innerHTML → nạp đầy đủ.
        const hadSidebar = !!document.querySelector('.admin-sidebar');
        const hasSidebar = typeof json.sidebar === 'string' && json.sidebar.trim() !== '';
        if (hadSidebar !== hasSidebar) { location.href = url; return; }

        saveScroll(); // vị trí trang CŨ trước khi thay DOM
        recordLoadedSrcs(); // nhớ script src đang có trong trang cũ (trước khi bị gỡ)

        // Dọn phần tử tạm của trang cũ (menu 3 chấm đã đẩy ra body)
        document.querySelectorAll('body > .action-menu').forEach((m) => m.remove());
        // Báo view cũ dọn listener cấp document (vd phím Escape của popup)
        try { document.dispatchEvent(new CustomEvent('vc:partial-leave')); } catch (e) { /* noop */ }
        try { if (window.tinymce) window.tinymce.remove(); } catch (e) { /* noop */ }

        // Sprite icon nằm NGOÀI .admin-content → sống qua swap. Fragment trang
        // user có kèm sprite mới → gỡ sprite cũ trước, tránh trùng ID <symbol>.
        if (json.html && json.html.indexOf('data-vc-sprite') !== -1) {
            document.querySelectorAll('svg[data-vc-sprite]').forEach((s) => {
                if (!content.contains(s)) s.remove();
            });
        }

        // Thay sidebar + vùng nội dung (navbar/footer giữ nguyên → listener
        // profile dropdown không bị mất)
        const sidebar = document.querySelector('.admin-sidebar');
        const sidebarY = sidebar ? sidebar.scrollTop : 0; // vị trí menu sidebar trước khi thay
        if (sidebar && typeof json.sidebar === 'string' && json.sidebar.trim() !== '') {
            // Fragment (sidebar.php) mang nguyên <aside class="admin-sidebar">
            // đầy đủ → unwrap phần bên trong, tránh lồng aside vào aside
            // (aside ngoài mất khả năng cuộn → mất luôn vị trí menu).
            const tmp = document.createElement('div');
            tmp.innerHTML = json.sidebar;
            const first = tmp.firstElementChild;
            const sbInner = (first && first.tagName === 'ASIDE' && first.classList.contains('admin-sidebar'))
                ? first.innerHTML : tmp.innerHTML;
            sidebar.innerHTML = sbInner;
            sidebar.scrollTop = sidebarY; // GIỮ vị trí sidebar — không nhảy về đầu
        }
        content.classList.add('vc-fading'); // opacity 0 cùng frame với nội dung mới
        content.innerHTML = json.html || '';
        if (json.title) document.title = json.title;

        // Link CSS(fragment) → chuyển vào <head>: để trong .admin-content sẽ
        // bị xóa ở lần swap sau → style rớt giữa chừng; trùng href (trang
        // public luôn kèm home.css) thì giữ bản <head>, gỏ bản fragment.
        content.querySelectorAll('link[rel="stylesheet"]').forEach((l) => {
            const key = (l.getAttribute('href') || '').split('?')[0];
            const dup = Array.prototype.some.call(
                document.head.querySelectorAll('link[rel="stylesheet"]'),
                (h) => ((h.getAttribute('href') || '').split('?')[0]) === key
            );
            if (dup) l.remove(); else document.head.appendChild(l);
        });

        // Đồng bộ theme <body> theo trang đích — swap .admin-content không tự
        // đổi class; thiếu home-festival / thừa user-area làm nền + cascade
        // lệch so với tải đầy đủ (title trang chính sách căn trái, sai font).
        if (json.body_classes) {
            document.body.classList.toggle('home-festival', !!json.body_classes.home);
            document.body.classList.toggle('user-area', !!json.body_classes.user);
        }

        // Giữ #anchor trên URL (trang chủ #bang-gia / #cau-hoi-thuong-gap)
        const urlWithHash = newKey + (u.hash || '');
        if (push) history.pushState({ vcKey: newKey }, '', urlWithHash);
        else history.replaceState({ vcKey: newKey }, '', urlWithHash);
        vcKey = newKey;

        // Cuộn: URL có #anchor → nhắm đúng section; cùng pathname hoặc cùng
        // nhóm section (vd /user/guides ↔ /user/guides/detail) → GIỮ vị trí
        // ĐÃ LƯU (không đọc scrollTop sau khi DOM vừa thay — nội dung ngắn hơn
        // bị kẹp về 0/đầu trang); khác nhóm → khôi phục theo bộ nhớ hoặc về đầu.
        const scroller = getScroller();
        const sectionOf = (p) => '/' + (p.split('/').filter(Boolean).slice(0, 2).join('/') || '');
        let y = 0;
        let hashTarget = null;
        if (u.hash) {
            try { hashTarget = document.getElementById(decodeURIComponent(u.hash.slice(1))); } catch (e) { hashTarget = null; }
        }
        if (hashTarget) {
            hashTarget.scrollIntoView();
            y = scroller.scrollTop;
        } else if (sectionOf(u.pathname) === sectionOf(prevPathname)) {
            y = (typeof scrollMem[prevKey] === 'number') ? scrollMem[prevKey] : scroller.scrollTop;
        } else if (typeof scrollMem[newKey] === 'number') y = scrollMem[newKey];
        scroller.scrollTop = y;
        scrollMem[newKey] = y;

        // Anchor trang chủ: căn lại sau khi ảnh/font sảnh — trì hoãn 1 frame
        // + 300ms (bỏ qua nếu người dùng đã điều hướng tiếp).
        if (hashTarget) {
            const realign = () => {
                if (location.pathname + location.search !== newKey) return;
                const t = document.getElementById(hashTarget.id);
                if (t) t.scrollIntoView();
            };
            requestAnimationFrame(() => requestAnimationFrame(realign));
            setTimeout(realign, 300);
        }

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
            if (!json.ok || typeof json.html !== 'string') { location.href = url; return; }
            applyFragment(json, url, push);
            progress.done();
        }).catch(() => {
            if (myGen !== navGen) return; // bị abort bởi lần điều hướng mới hơn
            // Lỗi mạng / parse → nạp lại đầy đủ nhưng GIỮ vị trí trang cũ
            try {
                sessionStorage.setItem('vcPartialFallback', '1');
                sessionStorage.setItem('vcFallbackY', String(getScroller().scrollTop));
            } catch (e) { /* noop */ }
            location.href = url;
        });
    }

    // --- Bắt click link (trang public + khu user) ---
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        const link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        if (link.hasAttribute('data-no-partial') || link.hasAttribute('data-no-loader')) return;
        const hrefAttr = link.getAttribute('href');
        // Anchor cùng trang dạng "/#bang-gia" (navbar luôn render "/#..." ở mọi
        // trang) → cuộn mượt tại chỗ + giữ #hash, thay vì nhảy giật (native).
        if (hrefAttr && hrefAttr.charAt(0) !== '#') {
            try {
                const au = new URL(hrefAttr, location.href);
                if (au.origin === location.origin && au.hash &&
                    au.pathname === location.pathname && au.search === location.search) {
                    const el = document.getElementById(decodeURIComponent(au.hash.slice(1)));
                    if (el) {
                        e.preventDefault();
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        history.replaceState({ vcKey }, '', location.pathname + location.search + au.hash);
                        return;
                    }
                }
            } catch (err) { /* bỏ qua → rơi xuống eligibleUrl */ }
        }
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
        u = eligibleUrl(u.href);
        if (!u) return;
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

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const overlay = document.querySelector('.sidebar-overlay');
    const profileBtn = document.getElementById('profile-dropdown-btn');
    const profileMenu = document.getElementById('profile-dropdown-menu');
    const guestBtn = document.getElementById('guest-menu-btn');
    const guestMenu = document.getElementById('guest-dropdown-menu');
    const preloader = document.getElementById('page-preloader');

    function closeAll() {
        if (sidebar) sidebar.classList.remove('active');
        if (profileMenu) profileMenu.classList.remove('show');
        if (guestMenu) guestMenu.classList.remove('show');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.classList.remove('profile-mode');
        }
        document.body.classList.remove('overlay-open');
    }

    // 1. Toggle Sidebar Mobile
    if (sidebar && toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (profileMenu) profileMenu.classList.remove('show');
            if (guestMenu) guestMenu.classList.remove('show');

            const isSidebarActive = sidebar.classList.toggle('active');
            if (overlay) {
                overlay.classList.remove('profile-mode');
                if (isSidebarActive) {
                    overlay.classList.add('active');
                    document.body.classList.add('overlay-open');
                } else {
                    overlay.classList.remove('active');
                    document.body.classList.remove('overlay-open');
                }
            }
        });
    }

    // 2. Toggle Profile Menu
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar) sidebar.classList.remove('active');
            if (guestMenu) guestMenu.classList.remove('show');

            const isProfileShow = profileMenu.classList.toggle('show');
            if (overlay) {
                if (isProfileShow) {
                    overlay.classList.add('profile-mode');
                    overlay.classList.add('active');
                    document.body.classList.add('overlay-open');
                } else {
                    overlay.classList.remove('active');
                    overlay.classList.remove('profile-mode');
                    document.body.classList.remove('overlay-open');
                }
            }
        });
    }

    // 3. Toggle Guest Menu Mobile
    if (guestBtn && guestMenu) {
        guestBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar) sidebar.classList.remove('active');
            if (profileMenu) profileMenu.classList.remove('show');

            if (overlay) {
                overlay.classList.remove('active');
                overlay.classList.remove('profile-mode');
            }
            document.body.classList.remove('overlay-open');
            guestMenu.classList.toggle('show');
        });
    }

    // 4. Bấm vào màn đen -> Đóng tất cả
    if (overlay) {
        overlay.addEventListener('click', closeAll);
    }

    // 5. Bấm ra ngoài vùng menu -> Đóng tất cả menu & tắt màn đen
    document.addEventListener('click', function (e) {
        let isInsideMenu = false;

        if (profileBtn && profileMenu && (profileBtn.contains(e.target) || profileMenu.contains(e.target))) {
            isInsideMenu = true;
        }
        if (guestBtn && guestMenu && (guestBtn.contains(e.target) || guestMenu.contains(e.target))) {
            isInsideMenu = true;
        }

        if (!isInsideMenu) {
            closeAll();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeAll();
        }
    });

    // 6. Quản lý Preloader (Đang tải...)
    function hidePreloader() {
        if (!preloader || preloader.classList.contains('preloader-hidden')) return;
        let done = false;
        const start = function () {
            if (done) return;
            done = true;
            preloader.classList.add('preloader-hidden');
        };
        /* Trì hoãn 2 frame: để lớp phủ kịp sơn kín QUA lần sơn đầu tiên rồi
         * mới mờ dần. Nếu ẩn ngay lúc script chạy (thường trước first-paint),
         * trang đích lóe nội dung dưới lớp mờ mới hiện dần → cảm giác giật. */
        requestAnimationFrame(function () {
            requestAnimationFrame(start);
        });
        setTimeout(start, 300); // tab ẩn / rAF không chạy → vẫn tự ẩn, không kẹt
    }

    function showPreloader() {
        if (preloader) {
            preloader.classList.remove('preloader-hidden');
        }
    }

    hidePreloader();
    window.addEventListener('load', hidePreloader);
    setTimeout(hidePreloader, 1500);

    // Popup thông báo (bài type=popup) — markup nằm NGOÀI .admin-content nên
    // chỉ render khi nạp trang đầy đủ (login/tải lại); partial-nav không
    // đụng tới → không re-trigger. Hiện lần lượt từng bài sau preloader ẩn.
    (function initNoticePopups() {
        const wrap = document.getElementById('vc-notice-popups');
        if (!wrap || wrap.dataset.vcBound === '1') return;
        const list = Array.prototype.slice.call(wrap.querySelectorAll('.vc-notice-popup'));
        if (!list.length) return;
        wrap.dataset.vcBound = '1';
        let idx = 0;

        const showCurrent = () => {
            list.forEach((p, i) => { p.style.display = (i === idx) ? 'flex' : 'none'; });
            wrap.hidden = false;
            document.body.style.overflow = 'hidden';
        };
        const closeCurrent = () => {
            idx++;
            if (idx < list.length) { showCurrent(); return; }
            wrap.hidden = true;
            document.body.style.overflow = '';
        };

        wrap.addEventListener('click', (e) => {
            if (e.target && e.target.closest && e.target.closest('[data-vc-notice-close]')) closeCurrent();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !wrap.hidden) closeCurrent();
        });

        // Hiện sau khi preloader mờ (2 frame + 300ms) để không chồng lớp
        const reveal = () => { if (wrap.hidden && idx === 0) showCurrent(); };
        requestAnimationFrame(() => requestAnimationFrame(reveal));
        setTimeout(reveal, 600);
    })();

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            hidePreloader();
            // Quay lại trang từ bfcache (bấm Back sau khi submit) → khôi phục nút tạo
            document.querySelectorAll('button[data-busy="1"][data-busy-label]').forEach(function (b) {
                b.disabled = false;
                if (b.dataset.busyOriginal) b.innerHTML = b.dataset.busyOriginal;
                delete b.dataset.busy;
                delete b.dataset.busyOriginal;
            });
        }
    });

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        const target = link.getAttribute('target');
        const isCustomScheme = /^[a-z][a-z\d+.-]*:/i.test(href || '') && !/^https?:/i.test(href || '');

        if (
            href &&
            !href.startsWith('#') &&
            !href.startsWith('javascript:') &&
            !href.startsWith('mailto:') &&
            !href.startsWith('tel:') &&
            target !== '_blank' &&
            !link.hasAttribute('data-no-loader') &&
            !isCustomScheme &&
            !e.ctrlKey &&
            !e.metaKey &&
            !e.shiftKey &&
            !e.altKey
        ) {
            // Chờ hết pha click: nếu handler khác hủy điều hướng
            // (confirm bấm Hủy, validate...) thì không hiện preloader
            setTimeout(function () {
                if (e.defaultPrevented) {
                    hidePreloader();
                    return;
                }
                // Cùng URL (vd link anchor /#bang-gia trên trang chủ) → nhảy
                // cục bộ, KHÔNG nạp trang → không hiện preloader. Nếu vẫn hiện
                // sẽ kẹt màn "Đang tải..." vì không có lần reload nào tự ẩn.
                if (link.pathname === location.pathname && link.search === location.search) {
                    hidePreloader();
                    return;
                }
                showPreloader();
            }, 0);
        }
    });

    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (form && !form.hasAttribute('data-no-loader')) {
            showPreloader();
        }
    });

    // Nút "Tạo" của form AI (ảnh): bấm là hiện spinner +
    // "Đang tạo..." và khóa nút — có phản hồi ngay, chống bấm đôi.
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        const btn = form.querySelector('button[type="submit"][data-busy-label]');
        if (!btn) return;
        if (btn.dataset.busy === '1') { e.preventDefault(); return; }
        btn.dataset.busyOriginal = btn.innerHTML;
        btn.dataset.busy = '1';
        btn.disabled = true;
        btn.innerHTML = '<span class="btn-busy-spinner" aria-hidden="true"></span> ' + (btn.dataset.busyLabel || 'Đang xử lý...');
    });

    // Nút copy (data-copy-value) — bọc thành hàm toàn cục vcBindCopyButtons(root)
    // để cab Danh Sách Bài Viết nạp lại HTML bằng AJAX vẫn bind được nút mới.
    window.vcBindCopyButtons = function (root) {
        (root || document).querySelectorAll('[data-copy-value]').forEach(function (button) {
            if (button.dataset.copyBound === '1') return;
            button.dataset.copyBound = '1';
            button.addEventListener('click', async function (e) {
                // Chặn bubble lên document để menu 3 chấm KHÔNG đóng ngay —
                // người dùng còn thấy thông báo "Đã sao chép" trên chính nút.
                e.stopPropagation();

                const value = button.dataset.copyValue || '';
                if (!value) return;

                try {
                    await navigator.clipboard.writeText(value);
                } catch (error) {
                    const textArea = document.createElement('textarea');
                    textArea.value = value;
                    textArea.style.position = 'fixed';
                    textArea.style.opacity = '0';
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    textArea.remove();
                }

                // Khôi phục đúng HTML gốc (giữ nguyên icon + text đang hiển thị),
                // tránh lệch tên nút khi data-copy-label khác nhãn thực tế.
                const originalHTML = button.innerHTML;
                button.textContent = 'Đã sao chép';
                setTimeout(function () {
                    button.innerHTML = originalHTML;
                }, 1800);
            });
        });
    };
    window.vcBindCopyButtons(document);

    // QR modal (gói cước / thanh toán) — chạy lại sau partial-load
    window.vcPageInits.push(function (root) {
        root.querySelectorAll('[data-qr-modal-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                const modal = document.getElementById(button.dataset.qrModalOpen || '');
                if (modal) modal.hidden = false;
            });
        });

        root.querySelectorAll('[data-qr-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                const modal = button.closest('.subscription-qr-modal');
                if (modal) modal.hidden = true;
            });
        });

        root.querySelectorAll('.subscription-qr-modal').forEach(function (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) modal.hidden = true;
            });
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.subscription-qr-modal').forEach(function (modal) {
                modal.hidden = true;
            });
        }
    });

    // Bộ lọc nhóm gói cước — chạy lại sau partial-load
    window.vcPageInits.push(function (root) {
    const planGroupTabs = root.querySelectorAll('[data-plan-group-filter]');
    const planCards = root.querySelectorAll('[data-plan-group-ids]');
    const planEmptyBlock = root.querySelector('[data-plan-empty]');
    if (planGroupTabs.length && planCards.length) {
        planGroupTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const selectedGroup = tab.dataset.planGroupFilter || 'all';

                planGroupTabs.forEach(function (item) {
                    const isSelected = item === tab;
                    item.classList.toggle('is-active', isSelected);
                    item.setAttribute('aria-selected', String(isSelected));
                });

                let visibleCount = 0;
                planCards.forEach(function (card) {
                    const groupIds = (card.dataset.planGroupIds || '').split(',').filter(Boolean);
                    const shouldShow = selectedGroup === 'all' || groupIds.includes(selectedGroup);
                    card.hidden = !shouldShow;
                    card.style.display = shouldShow ? 'flex' : 'none';
                    if (shouldShow) visibleCount++;
                });

                if (planEmptyBlock) {
                    planEmptyBlock.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            });
        });
    }
    });

    // Trang checkout (mã giảm giá / số tiền) — chạy lại sau partial-load
    window.vcPageInits.push(function (root) {
    const checkoutForm = root.querySelector('[data-checkout-form]');
    if (checkoutForm) {
        const couponInput = checkoutForm.querySelector('#coupon_code');
        const applyCouponButton = checkoutForm.querySelector('[data-coupon-apply]');
        const removeCouponButton = checkoutForm.querySelector('[data-coupon-remove]');
        const couponFeedback = checkoutForm.querySelector('[data-coupon-feedback]');
        const couponFeedbackRow = checkoutForm.querySelector('[data-coupon-feedback-row]');
        const discountAmount = checkoutForm.querySelector('[data-checkout-discount]');
        const finalAmount = checkoutForm.querySelector('[data-checkout-final]');
        const originalAmount = Number(checkoutForm.dataset.originalAmount || 0);
        const csrfToken = checkoutForm.querySelector('[name="csrf_token"]');
        const planId = checkoutForm.querySelector('[name="plan_id"]');

        let currencySymbol = 'đ';
let currencyPosition = 'right';
let currencyDecimals = 0;

function setCurrency(data) {
    if (!data) return;

    if (typeof data.currency_symbol === 'string' && data.currency_symbol.trim() !== '') {
        currencySymbol = data.currency_symbol.trim();
    }

    if (data.currency_position === 'left' || data.currency_position === 'right') {
        currencyPosition = data.currency_position;
    }

    const decimals = Number.parseInt(data.currency_decimals, 10);

    if (Number.isFinite(decimals)) {
        currencyDecimals = Math.max(0, Math.min(4, decimals));
    }
}

function formatAmount(amount) {
    const formatted = Number(amount || 0).toLocaleString('en-US', {
        minimumFractionDigits: currencyDecimals,
        maximumFractionDigits: currencyDecimals
    });

    return currencyPosition === 'left'
        ? currencySymbol + formatted
        : formatted + ' ' + currencySymbol;
}

        function showCouponFeedback(message, state) {
            if (!couponFeedback) return;
            if (couponFeedbackRow) couponFeedbackRow.hidden = false;
            couponFeedback.textContent = message || '';
            couponFeedback.classList.toggle('is-success', state === 'success');
            couponFeedback.classList.toggle('is-error', state === 'error');
        }

        function resetCoupon() {
            if (couponInput) couponInput.value = '';
            if (discountAmount) discountAmount.textContent = '-' + formatAmount(0);
            if (finalAmount) finalAmount.textContent = formatAmount(originalAmount);
            if (removeCouponButton) removeCouponButton.hidden = true;
            showCouponFeedback('Đã bỏ mã giảm giá. Tổng tiền đã trở về giá gốc.', 'success');
        }

        if (removeCouponButton) {
            removeCouponButton.addEventListener('click', resetCoupon);
        }

        const depositInput = checkoutForm.querySelector('#deposit_amount');
        if (depositInput) {
            const syncDepositTotal = function () {
                const value = Number(depositInput.value || 0);
                if (finalAmount) finalAmount.textContent = formatAmount(value);
            };
            depositInput.addEventListener('input', syncDepositTotal);
            syncDepositTotal();
        }

        if (applyCouponButton && couponInput && csrfToken && planId) {
            applyCouponButton.addEventListener('click', async function () {
                const couponCode = couponInput.value.trim();
                if (!couponCode) {
                    showCouponFeedback('Hãy nhập mã giảm giá trước khi xác nhận.', 'error');
                    couponInput.focus();
                    return;
                }

                applyCouponButton.disabled = true;
                applyCouponButton.textContent = 'Đang kiểm tra...';
                showCouponFeedback('Đang kiểm tra mã giảm giá...', '');

                try {
                    const response = await fetch('/checkout/coupon', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                        body: new URLSearchParams({
                            csrf_token: csrfToken.value,
                            plan_id: planId.value,
                            coupon_code: couponCode
                        })
                    });
                    const result = await response.json();

setCurrency(result);

if (!response.ok || !result.valid) {
                        if (discountAmount) discountAmount.textContent = '-' + formatAmount(0);
                        if (finalAmount) finalAmount.textContent = formatAmount(originalAmount);
                        if (removeCouponButton) removeCouponButton.hidden = true;
                        showCouponFeedback(result.message || 'Mã giảm giá không hợp lệ.', 'error');
                        return;
                    }

                    couponInput.value = result.coupon_code || couponCode.toUpperCase();
                    if (discountAmount) discountAmount.textContent = '-' + formatAmount(result.discount_amount);
                    if (finalAmount) finalAmount.textContent = formatAmount(result.final_amount);
                    if (removeCouponButton) removeCouponButton.hidden = false;
                    showCouponFeedback(result.message || 'Áp dụng mã giảm giá thành công.', 'success');
                } catch (error) {
                    showCouponFeedback('Không thể kiểm tra mã lúc này. Vui lòng thử lại.', 'error');
                } finally {
                    applyCouponButton.disabled = false;
                    applyCouponButton.textContent = 'Xác nhận mã';
                }
            });
        }
    }
    });

    // 7. Logic tự động chạy và đồng bộ Slide Thông báo cho Dashboard
    // (chạy lại sau partial-load; dọn timer cũ tránh rò rỉ bộ đếm)
    window.vcPageInits.push(function (root) {
    if (window.__vcTutorialTimer) {
        clearInterval(window.__vcTutorialTimer);
        window.__vcTutorialTimer = null;
    }
    const slider = root.querySelector('#tutorialSlider');
    const dots = root.querySelectorAll('.tutorial-dot');
    const previousNoticeButton = root.querySelector('[data-notice-slider-previous]');
    const nextNoticeButton = root.querySelector('[data-notice-slider-next]');

    if (slider && dots.length > 0) {
        let currentIndex = 0;
        const totalSlides = dots.length;
        let autoTimer = null;

        function scrollToSlide(index) {
            const slideWidth = slider.clientWidth;
            if (slideWidth > 0) {
                slider.scrollTo({
                    left: slideWidth * index,
                    behavior: 'smooth'
                });
            }
        }

        window.goToSlide = function (index) {
            currentIndex = index;
            scrollToSlide(currentIndex);
            restartTimer();
        };

        function moveSlide(offset) {
            currentIndex = (currentIndex + offset + totalSlides) % totalSlides;
            scrollToSlide(currentIndex);
            restartTimer();
        }

        if (previousNoticeButton) {
            previousNoticeButton.addEventListener('click', function () {
                moveSlide(-1);
            });
        }

        if (nextNoticeButton) {
            nextNoticeButton.addEventListener('click', function () {
                moveSlide(1);
            });
        }

        slider.addEventListener('scroll', function () {
            const slideWidth = slider.clientWidth;
            if (slideWidth > 0) {
                const activeIndex = Math.round(slider.scrollLeft / slideWidth);
                dots.forEach((dot, idx) => {
                    dot.classList.toggle('active', idx === activeIndex);
                });
                currentIndex = activeIndex;
            }
        });

        function startTimer() {
            if (totalSlides <= 1) return;
            autoTimer = setInterval(() => {
                currentIndex = (currentIndex + 1) % totalSlides;
                scrollToSlide(currentIndex);
            }, 4000);
            window.__vcTutorialTimer = autoTimer;
        }

        function restartTimer() {
            if (autoTimer) clearInterval(autoTimer);
            startTimer();
        }

        startTimer();
    }
    });

    // 8. Chọn nhanh số tiền nạp (trang Ví tiền & trang thanh toán nạp tiền)
    // (chạy lại sau partial-load)
    window.vcPageInits.push(function (root) {
    const depositAmountInput = root.querySelector('#deposit-amount');
    const quickAmountButtons = root.querySelectorAll('.wallet-quick-amount');
    const quickAmountGroups = root.querySelectorAll('[data-quick-amount-group]');

    function bindQuickAmounts(buttons, input) {
        if (!input || buttons.length === 0) return;

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                input.value = button.dataset.amount;
                buttons.forEach(function (item) {
                    item.classList.toggle('active', item === button);
                });
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        input.addEventListener('input', function () {
            buttons.forEach(function (item) {
                item.classList.toggle('active', item.dataset.amount === String(input.value));
            });
        });
    }

    if (depositAmountInput && quickAmountButtons.length > 0) {
        bindQuickAmounts(Array.from(quickAmountButtons), depositAmountInput);
    }

    quickAmountGroups.forEach(function (group) {
        const target = group.dataset.quickAmountTarget
            ? root.querySelector(group.dataset.quickAmountTarget)
            : null;
        bindQuickAmounts(Array.from(group.querySelectorAll('.wallet-quick-amount')), target);
    });
    });

    // Form xác nhận trước khi gửi — chạy lại sau partial-load
    window.vcPageInits.push(function (root) {
        root.querySelectorAll('[data-confirm-submit]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.dataset.confirmSubmit || 'Bạn có chắc muốn tiếp tục?')) {
                    event.preventDefault();
                }
            });
        });
    });

    // 9. Tự động ẩn thông báo sau 4 giây — chạy lại sau partial-load
    window.vcPageInits.push(function (root) {
        root.querySelectorAll('.user-record-alert').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-15px)';
                setTimeout(function () {
                    alert.remove();
                }, 400);
            }, 4000);
        });
    });

    // 10. Xử lý toggle Menu Ba Chấm (Action Dropdown)
    //     → tách thành hàm toàn cục vcBindActionMenus(root) để cab Danh Sách
    //       Bài Viết nạp lại HTML bằng AJAX gọi bind lại cho nút/menu mới.
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

    window.vcBindActionMenus = function (root) {
        const actionBtns = (root || document).querySelectorAll('.action-btn');

        actionBtns.forEach(btn => {
            if (btn.dataset.actionBound) return;
            btn.dataset.actionBound = '1';

            btn.addEventListener('click', function (e) {
                e.stopPropagation();

                let currentMenu = this.nextElementSibling;
                if (!currentMenu || !currentMenu.classList.contains('action-menu')) {
                    currentMenu = this._actionMenu;
                }

                if (!currentMenu) return;

                this._actionMenu = currentMenu;
                currentMenu._triggerBtn = this;

                const isCurrentlyShown = currentMenu.classList.contains('show');

                closeAllActionMenus();

                if (!isCurrentlyShown) {
                    if (currentMenu.parentNode !== document.body) {
                        document.body.appendChild(currentMenu);
                    }

                    currentMenu.classList.add('show');
                    this.classList.add('active');

                    const rect = this.getBoundingClientRect();
                    const viewportHeight = window.visualViewport ? window.visualViewport.height : document.documentElement.clientHeight;
                    const spaceAbove = rect.top - 8;
                    const spaceBelow = viewportHeight - rect.bottom - 8;

                    // Menu luôn mở về phía còn nhiều chỗ trống hơn; maxHeight bám viewport
                    if (spaceBelow >= Math.min(spaceAbove, 240)) {
                        currentMenu.style.maxHeight = Math.max(0, spaceBelow) + 'px';
                        currentMenu.style.top = (rect.bottom + 4) + 'px';
                        currentMenu.style.bottom = 'auto';
                    } else {
                        currentMenu.style.maxHeight = Math.max(0, spaceAbove) + 'px';
                        currentMenu.style.top = 'auto';
                        currentMenu.style.bottom = Math.max(8, viewportHeight - rect.top + 4) + 'px';
                    }
                    currentMenu.style.left = 'auto';
                    currentMenu.style.right = (window.innerWidth - rect.right) + 'px';
                }
            });
        });
    };
    window.vcBindActionMenus(document);

    if (!window._actionDropdownEventsBound) {
        window._actionDropdownEventsBound = true;
        document.addEventListener('click', closeAllActionMenus);
        window.addEventListener('scroll', closeAllActionMenus, true);
        window.addEventListener('resize', closeAllActionMenus);
    }


    // Chạy toàn bộ init trang (lần đầu). Trang admin: admin.js đăng ký thêm
    // init lúc parse → app.js gọi sau cùng nên chạy đủ; guard chống gọi đôi
    // khi cả 2 file cùng thêm listener DOMContentLoaded.
    if (!window.__vcInitAtDom) {
        window.__vcInitAtDom = true;
        window.vcInitPageContent(document);
    }
});
