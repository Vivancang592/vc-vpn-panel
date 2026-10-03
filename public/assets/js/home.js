const initializeHomePage = (root = document) => {
    root.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (event) => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (!target) {
                return;
            }

            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            window.history.replaceState(null, '', anchor.getAttribute('href'));
        });
    });

    const revealItems = root.querySelectorAll('.home-reveal');

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    currentObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        revealItems.forEach((item) => observer.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    const tabs = [...root.querySelectorAll('.home-tab')];
    const panels = [...root.querySelectorAll('.home-tab-panel')];

    const activateTab = (tab) => {
        tabs.forEach((item) => {
            item.setAttribute('aria-selected', item === tab ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            panel.hidden = panel.id !== tab.getAttribute('aria-controls');
        });
        const activePanel = document.getElementById(tab.getAttribute('aria-controls'));
        if (activePanel) {
            activePanel.querySelectorAll('.home-reveal').forEach((item) => {
                item.classList.add('is-visible');
            });
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }

            event.preventDefault();
            const offset = event.key === 'ArrowRight' ? 1 : tabs.length - 1;
            const nextTab = tabs[(index + offset) % tabs.length];
            nextTab.focus();
            activateTab(nextTab);
        });
    });
};

// Đăng ký qua registry vcPageInits (app.js) để init chạy lại sau MỖI lần
// partial-load — fragment mới cần gắn lại smooth-scroll / tabs / reveal.
window.vcPageInits = window.vcPageInits || [];
window.vcPageInits.push(function (root) {
    initializeHomePage(root || document);
});
