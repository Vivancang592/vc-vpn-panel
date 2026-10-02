/* ===== GIỮ VỊ TRÍ CUỘN QUA POST → REDIRECT CÙNG TRANG =====
 * Nút không đổi tab/trang (Tạo ảnh/video/lời thoại, Xóa, Lưu lịch...)
 * đều POST rồi redirect về đúng URL → trang load lại đúng vị trí cũ,
 * không nhảy vọt lên đầu. Bỏ qua khi URL có #anchor (đã có đích cuộn riêng).
 * Lưu ý: trang admin cuộn trong container `.admin-main` (html/body ẩn chữ),
 * nên phải đọc/ghi scrollTop của container chứ không phải window.scrollY. */
(function () {
    const scrollKey = 'vcScroll:' + location.pathname + location.search;

    function getScroller() {
        const main = document.querySelector('.admin-main');
        if (main && main.scrollHeight > main.clientHeight + 4) return main;
        return document.scrollingElement || document.documentElement;
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        try {
            sessionStorage.setItem(scrollKey, JSON.stringify({ y: getScroller().scrollTop, t: Date.now() }));
        } catch (err) { /* sessionStorage lỗi → bỏ qua */ }
    });

    try {
        const saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
        sessionStorage.removeItem(scrollKey); // chỉ dùng đúng 1 lần
        if (saved && !location.hash && typeof saved.y === 'number' && saved.y > 0
            && (Date.now() - (saved.t || 0)) < 60000) {
            const restore = function () { getScroller().scrollTop = saved.y; };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', restore);
            } else {
                restore();
            }
        }
    } catch (err) { /* bỏ qua */ }
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
        if (preloader) {
            preloader.classList.add('preloader-hidden');
        }
    }

    function showPreloader() {
        if (preloader) {
            preloader.classList.remove('preloader-hidden');
        }
    }

    hidePreloader();
    window.addEventListener('load', hidePreloader);
    setTimeout(hidePreloader, 1500);

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
            !e.metaKey
        ) {
            showPreloader();
        }
    });

    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (form && !form.hasAttribute('data-no-loader') && !form.closest('#vc-chatbot')) {
            showPreloader();
        }
    });

    // Nút "Tạo" của form AI (ảnh/video/lời thoại): bấm là hiện spinner +
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

    document.querySelectorAll('[data-qr-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = document.getElementById(button.dataset.qrModalOpen || '');
            if (modal) modal.hidden = false;
        });
    });

    document.querySelectorAll('[data-qr-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = button.closest('.subscription-qr-modal');
            if (modal) modal.hidden = true;
        });
    });

    document.querySelectorAll('.subscription-qr-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) modal.hidden = true;
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.subscription-qr-modal').forEach(function (modal) {
                modal.hidden = true;
            });
        }
    });

    const planGroupTabs = document.querySelectorAll('[data-plan-group-filter]');
    const planCards = document.querySelectorAll('[data-plan-group-ids]');
    const planEmptyBlock = document.querySelector('[data-plan-empty]');
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

    const checkoutForm = document.querySelector('[data-checkout-form]');
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

    // 7. Logic tự động chạy và đồng bộ Slide Thông báo cho Dashboard
    const slider = document.getElementById('tutorialSlider');
    const dots = document.querySelectorAll('.tutorial-dot');
    const previousNoticeButton = document.querySelector('[data-notice-slider-previous]');
    const nextNoticeButton = document.querySelector('[data-notice-slider-next]');

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
        }

        function restartTimer() {
            if (autoTimer) clearInterval(autoTimer);
            startTimer();
        }

        startTimer();
    }

    // 8. Chọn nhanh số tiền nạp (trang Ví tiền & trang thanh toán nạp tiền)
    const depositAmountInput = document.getElementById('deposit-amount');
    const quickAmountButtons = document.querySelectorAll('.wallet-quick-amount');
    const quickAmountGroups = document.querySelectorAll('[data-quick-amount-group]');

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
            ? document.querySelector(group.dataset.quickAmountTarget)
            : null;
        bindQuickAmounts(Array.from(group.querySelectorAll('.wallet-quick-amount')), target);
    });

    document.querySelectorAll('[data-confirm-submit]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirmSubmit || 'Bạn có chắc muốn tiếp tục?')) {
                event.preventDefault();
            }
        });
    });

    // 9. Tự động ẩn thông báo sau 4 giây
    document.querySelectorAll('.user-record-alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-15px)';
            setTimeout(function () {
                alert.remove();
            }, 400);
        }, 4000);
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
                    currentMenu.style.top = (rect.bottom + 4) + 'px';
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

    // 11. Chatbot widget (web)
    const chatbotRoot = document.getElementById('vc-chatbot');
    if (chatbotRoot) {
        // Đưa chatbot ra trực tiếp thẻ body để tránh bị giam trong stacking context / overflow của navbar và layout
        if (chatbotRoot.parentElement && chatbotRoot.parentElement !== document.body) {
            document.body.appendChild(chatbotRoot);
        }

        if (!document.getElementById('vc-chatbot-styles')) {
            const styleTag = document.createElement('style');
            styleTag.id = 'vc-chatbot-styles';
            styleTag.textContent = `
@keyframes vcChatSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes vcSlideDownMobile { 0% { transform: translateY(-100%); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }
@keyframes vcFadeInDesktop { 0% { transform: translateY(12px) scale(0.98); opacity: 0; } 100% { transform: translateY(0) scale(1); opacity: 1; } }

#vc-chatbot {
    position: relative;
    z-index: 2147483647;
}

#vc-chatbot-toggle {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 2147483647;
    width: 56px;
    height: 56px;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    background: linear-gradient(135deg, #0a84ff, #34c759);
    color: #fff;
    box-shadow: 0 10px 24px rgba(10,132,255,.36);
    font-size: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
}
#vc-chatbot-toggle:hover {
    transform: scale(1.08);
    box-shadow: 0 12px 28px rgba(10,132,255,.45);
}

.vc-chatbot-panel-wrapper {
    position: fixed;
    right: 10px;
    bottom: 10px;
    z-index: 2147483647 !important;
    width: 550px;
    max-width: calc(100vw - 32px);
    height: min(574px, calc(100vh - 120px));
    background: #ffffff;
    border: 1px solid rgba(0,0,0,.12);
    border-radius: 18px;
    box-shadow: 0 24px 48px rgba(0,0,0,.22), 0 8px 16px rgba(0,0,0,.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: vcFadeInDesktop 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.vc-chatbot-panel-wrapper[hidden] {
    display: none !important;
}

.vc-chatbot-header {
    padding: 12px 16px;
    background: linear-gradient(135deg, #0a84ff, #34c759);
    color: #fff;
    font-weight: 700;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    flex-shrink: 0;
    position: relative;
    z-index: 10;
}

#vc-chatbot-close {
    border: none;
    background: rgba(255,255,255,0.25);
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, transform 0.15s ease;
    flex-shrink: 0;
}
#vc-chatbot-close:hover {
    background: rgba(255,255,255,0.4);
    transform: scale(1.05);
}

#vc-chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    background: #f6f9fc;
    display: flex;
    flex-direction: column;
    gap: 8px;
    -webkit-overflow-scrolling: touch;
}

#vc-chatbot-form {
    display: flex;
    gap: 8px;
    padding: 12px 14px;
    border-top: 1px solid rgba(0,0,0,.08);
    background: #ffffff;
    flex-shrink: 0;
}

#vc-chatbot-input {
    flex: 1;
    border: 1px solid rgba(0,0,0,.15);
    border-radius: 22px;
    padding: 10px 16px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #f8fafc;
}
#vc-chatbot-input:focus {
    border-color: #0a84ff;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(10,132,255,0.15);
}

#vc-chatbot-form button[type="submit"] {
    border: none;
    border-radius: 22px;
    background: #0a84ff;
    color: #fff;
    padding: 0 18px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: background 0.2s;
}
#vc-chatbot-form button[type="submit"]:hover {
    background: #0070e0;
}

/* Giao diện Full Màn Hình & Trượt từ trên xuống cho Mobile */
@media (max-width: 767px) {
    #vc-chatbot {
        position: fixed !important;
        inset: 0 !important;
        pointer-events: none;
        z-index: 2147483647 !important;
    }
    #vc-chatbot > * {
        pointer-events: auto;
    }

    .vc-chatbot-panel-wrapper {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        height: 100% !important;
        height: 100dvh !important;
        border-radius: 0 !important;
        border: none !important;
        box-shadow: none !important;
        z-index: 2147483647 !important;
        animation: vcSlideDownMobile 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards !important;
    }

    .vc-chatbot-header {
        padding: max(14px, env(safe-area-inset-top, 14px)) 16px 14px 16px !important;
        font-size: 1rem !important;
        min-height: 56px !important;
    }

    #vc-chatbot-close {
        width: 36px !important;
        height: 36px !important;
        font-size: 18px !important;
    }

    #vc-chatbot-form {
        padding: 10px 12px max(12px, env(safe-area-inset-bottom, 12px)) 12px !important;
    }

    body.vc-chatbot-open {
        overflow: hidden !important;
    }

    body.vc-chatbot-open .navbar-container {
        z-index: 1 !important;
    }
}
`;
            document.head.appendChild(styleTag);
        }

        const toggleBtn = document.getElementById('vc-chatbot-toggle');
        const closeBtn = document.getElementById('vc-chatbot-close');
        const panel = document.getElementById('vc-chatbot-panel');
        const form = document.getElementById('vc-chatbot-form');
        const input = document.getElementById('vc-chatbot-input');
        const box = document.getElementById('vc-chatbot-messages');
        const page = (chatbotRoot.dataset.page || '/').replace(/^\//, '') || 'home';
        const siteTitle = (chatbotRoot.dataset.siteTitle || 'VC VPN').trim();
        const chatLoggedIn = chatbotRoot.dataset.loggedIn === '1';
        const chatUserName = (chatbotRoot.dataset.userName || '').trim();

        // ── Tự động làm sạch sau 5 phút AI trả lời mà khách không phản hồi ──
        // Khi bắn timer: xóa nội dung popup + gọi /api/chat/reset — server đóng
        // phiên đang mở và xoay visitor token → bộ nhớ AI sạch, phiên chuyển
        // trạng thái "Đã đóng" (không còn treo open).
        const IDLE_CLOSE_MS = 5 * 60 * 1000;
        let idleCloseTimer = null;

        const clearIdleCloseTimer = function () {
            if (idleCloseTimer) {
                clearTimeout(idleCloseTimer);
                idleCloseTimer = null;
            }
        };

        const startIdleCloseTimer = function (delayMs) {
            clearIdleCloseTimer();
            idleCloseTimer = setTimeout(function () {
                idleCloseTimer = null;
                resetConversation();
                renderSystemNote('⏳ Bạn chưa phản hồi trong 5 phút nên hội thoại đã tự động đóng và bộ nhớ AI được làm mới. Nhắn tin bất cứ lúc nào để bắt đầu cuộc trò chuyện mới nhé!');
            }, typeof delayMs === 'number' ? delayMs : IDLE_CLOSE_MS);
        };

        const trackEvent = async function (eventName, meta) {
            try {
                await fetch('/api/chat/event', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({
                        event_name: eventName,
                        source: 'web',
                        meta: meta || {}
                    })
                });
            } catch (_e) {
                // no-op
            }
        };

        const formatMarkdown = function (rawText) {
            if (!rawText) return '';
            // Escape HTML
            let safe = rawText
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            // Bold **text**
            safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            // Italic *text*
            safe = safe.replace(/\*(.+?)\*/g, '<em>$1</em>');
            // Inline code `code`
            safe = safe.replace(/`([^`]+)`/g, '<code style="background:rgba(0,122,255,0.08);color:#0a84ff;padding:1px 5px;border-radius:4px;font-family:monospace;font-size:90%;font-weight:600;">$1</code>');
            // Markdown link [label](url)
            safe = safe.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]+)\)/g, function (_match, label, href) {
                return '<a href="' + href + '" style="color: #0a84ff; text-decoration: underline; font-weight: 600;" target="_self">' + label + '</a>';
            });
            // Newlines
            safe = safe.replace(/\n/g, '<br>');

            return safe;
        };

        // Ghi chú hệ thống (thông báo tự đóng phiên...) — căn giữa, không bong bóng chat.
        const renderSystemNote = function (text) {
            if (!box) return;
            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.justifyContent = 'center';
            row.style.margin = '10px 0 8px';

            const note = document.createElement('div');
            note.style.maxWidth = '90%';
            note.style.padding = '6px 11px';
            note.style.borderRadius = '10px';
            note.style.background = 'rgba(0,0,0,.05)';
            note.style.color = '#636366';
            note.style.fontSize = '11.5px';
            note.style.lineHeight = '1.45';
            note.style.textAlign = 'center';
            note.style.fontStyle = 'italic';
            note.textContent = text;

            row.appendChild(note);
            box.appendChild(row);
            box.scrollTop = box.scrollHeight;
        };

        const renderMessage = function (role, text) {
            if (!box) return;
            const isUser = role === 'user';

            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.flexDirection = 'column';
            row.style.alignItems = isUser ? 'flex-end' : 'flex-start';
            row.style.marginBottom = '10px';

            // Tên hiển thị theo từng dòng chat:
            // - AI → tên website (siteTitle); - Người dùng → tên của họ,
            //   chưa đăng nhập/chưa có tên → mặc định "Khách".
            const nameLabel = document.createElement('div');
            nameLabel.textContent = isUser ? (chatUserName || 'Khách') : siteTitle;
            nameLabel.style.fontSize = '10.5px';
            nameLabel.style.fontWeight = '600';
            nameLabel.style.color = '#8e8e93';
            nameLabel.style.margin = '0 3px 3px';
            nameLabel.style.letterSpacing = '.01em';
            row.appendChild(nameLabel);

            const bubble = document.createElement('div');
            bubble.style.maxWidth = '85%';
            bubble.style.padding = '8px 11px';
            bubble.style.borderRadius = '10px';
            bubble.style.fontSize = '13px';
            bubble.style.lineHeight = '1.5';

            if (isUser) {
                bubble.style.background = '#0a84ff';
                bubble.style.color = '#fff';
                bubble.style.whiteSpace = 'pre-wrap';
                bubble.textContent = text;
            } else {
                bubble.style.background = '#fff';
                bubble.style.color = '#1c1c1e';
                bubble.style.border = '1px solid rgba(0,0,0,.08)';
                bubble.style.wordBreak = 'break-word';
                bubble.innerHTML = formatMarkdown(text);
            }

            row.appendChild(bubble);
            box.appendChild(row);
            box.scrollTop = box.scrollHeight;
        };

        const showTypingIndicator = function () {
            if (!box) return;
            hideTypingIndicator();
            const row = document.createElement('div');
            row.id = 'vc-chatbot-typing-indicator';
            row.style.display = 'flex';
            row.style.justifyContent = 'flex-start';
            row.style.marginBottom = '8px';

            const bubble = document.createElement('div');
            bubble.style.background = '#fff';
            bubble.style.border = '1px solid rgba(0,0,0,.08)';
            bubble.style.padding = '8px 12px';
            bubble.style.borderRadius = '10px';
            bubble.style.fontSize = '12.5px';
            bubble.style.lineHeight = '1.4';
            bubble.style.display = 'inline-flex';
            bubble.style.alignItems = 'center';
            bubble.style.gap = '7px';
            bubble.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border:2px solid #0a84ff;border-top-color:transparent;border-radius:50%;animation:vcChatSpin 0.75s linear infinite;box-sizing:border-box;"></span> <span style="color:#636366;font-style:italic;">Đang trả lời...</span>';

            row.appendChild(bubble);
            box.appendChild(row);
            box.scrollTop = box.scrollHeight;
        };

        const hideTypingIndicator = function () {
            const el = document.getElementById('vc-chatbot-typing-indicator');
            if (el) el.remove();
        };

        const setLoading = function (loading) {
            if (!form) return;
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = loading;
                submitBtn.textContent = loading ? '...' : 'Gửi';
            }
            if (input) {
                input.disabled = loading;
            }
        };

        let historyLoaded = false;
        let historyRestored = false;

        const renderGreeting = function () {
            if (!box || box.childElementCount > 0) return;
            const greeting = chatLoggedIn
                ? 'Chào ' + (chatUserName || 'bạn') + '! Rất vui được hỗ trợ bạn trở lại. Mình có thể giúp kiểm tra gói đang dùng, hướng dẫn kết nối, nạp tiền hoặc tạo yêu cầu hỗ trợ. Hôm nay bạn cần mình xử lý việc gì?'
                : 'Xin chào bạn! Mình là trợ lý tư vấn ' + siteTitle + '. Bạn đang chat với tư cách khách (chưa đăng nhập). Bạn cần hỗ trợ về gói dịch vụ, giá cước hay hướng dẫn cài đặt nào ạ?';
            renderMessage('assistant', greeting);
        };

        const loadConversationHistory = async function () {
            if (historyLoaded) return;
            historyLoaded = true;

            try {
                const res = await fetch('/api/chat/history', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                const chat = data && data.data ? data.data : {};
                const history = Array.isArray(chat.history) ? chat.history : [];

                if (!res.ok || !data.success || chat.status !== 'open' || history.length === 0) return;

                box.innerHTML = '';
                history.forEach(function (item) {
                    renderMessage(item.role, item.content || '');
                });
                historyRestored = true;

                if (chat.last_role === 'assistant' && Number(chat.idle_remaining_seconds) > 0) {
                    startIdleCloseTimer(Number(chat.idle_remaining_seconds) * 1000);
                }
            } catch (_e) {
                // Không tải được lịch sử thì dùng lời chào thông thường.
            }
        };

        const openPanel = async function () {
            if (!panel) return;
            panel.hidden = false;
            if (toggleBtn) {
                toggleBtn.style.display = 'none';
            }
            document.body.classList.add('vc-chatbot-open');

            await loadConversationHistory();
            if (!historyRestored) renderGreeting();
            setTimeout(function () {
                if (input) input.focus();
            }, 100);
        };

        const resetConversation = async function () {
            if (box) box.innerHTML = '';
            if (input) input.value = '';
            historyLoaded = false;
            historyRestored = false;
            try {
                await fetch('/api/chat/reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' }
                });
            } catch (_e) {
                // no-op
            }
        };

        // Đóng panel CHỈ ẩn giao diện — KHÔNG reset hội thoại (bộ nhớ AI).
        // Việc làm sạch do cơ chế tự đóng 5 phút (idleCloseTimer) đảm nhiệm:
        // timer vẫn chạy tiếp cả khi panel đang ẩn → đóng vẫn tự làm mới đúng hạn.
        const closePanel = function () {
            if (!panel) return;
            panel.hidden = true;
            if (toggleBtn) {
                toggleBtn.style.display = 'flex';
            }
            document.body.classList.remove('vc-chatbot-open');
        };

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const isHidden = panel ? panel.hidden : true;
                if (isHidden) {
                    openPanel();
                    trackEvent('chat_opened', { page: page });
                } else {
                    closePanel();
                }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                closePanel();
            });
        }

        if (form && input) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                event.stopPropagation();
                hidePreloader();
                const message = input.value.trim();
                if (!message) return;

                // Khách vừa phản hồi → hủy đếm tự đóng cũ (sẽ đặt lại sau khi AI trả lời).
                clearIdleCloseTimer();
                renderMessage('user', message);
                input.value = '';
                setLoading(true);
                showTypingIndicator();

                try {
                    const res = await fetch('/api/chat/message', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json'
                        },
                        body: JSON.stringify({
                            message: message,
                            page: page
                        })
                    });

                    const data = await res.json();
                    hideTypingIndicator();

                    if (!res.ok || !data.success) {
                        const errMsg = data && data.message ? data.message : 'Mình chưa nhận được phản hồi từ AI. Bạn thử lại sau vài giây nhé.';
                        renderMessage('assistant', errMsg);
                        startIdleCloseTimer();
                        return;
                    }

                    const answer = data && data.data ? data.data.answer : '';
                    const handoff = data && data.data ? !!data.data.handoff : false;

                    if (answer) {
                        renderMessage('assistant', answer);
                    } else {
                        renderMessage('assistant', 'Mình chưa nhận được phản hồi từ AI. Bạn thử lại sau vài giây nhé.');
                    }

                    if (handoff) {
                        renderMessage('assistant', 'Nếu cần người hỗ trợ trực tiếp, bạn để lại email/SĐT hoặc nhắn fanpage giúp mình.');
                    }

                    // AI đã trả lời mà khách không phản hồi trong 5 phút → tự đóng.
                    startIdleCloseTimer();
                } catch (_error) {
                    hideTypingIndicator();
                    renderMessage('assistant', 'Kết nối đang gián đoạn. Bạn thử lại sau ít phút nhé.');
                    startIdleCloseTimer();
                } finally {
                    hideTypingIndicator();
                    setLoading(false);
                    if (input) input.focus();
                }
            });
        }
    }
});