<?php

$pageTitle = 'Tải Ứng Dụng - ' . ($settings['site_title'] ?? 'VC VPN 2027');
$extraCss = 'home';

ob_start();
?>

<section class="public-page home-page home-subpage home-download-page">
<div class="glass-card home-download-shell" style="
    width: 100%;
    margin: 0 auto;
    padding: 2rem;
">

    <!-- Header -->
    <div class="home-subpage-header" style="
        text-align: center;
        margin-bottom: 2rem;
    ">
        <h1 class="home-download-title" style="
            margin: 0 0 .75rem;
            color: #020af4;
            font-family: emoji;
        ">
            TẢI ỨNG DỤNG
        </h1>

        <p style="
            max-width: 850px;
            margin: 0 auto;
            color: var(--ios-text-secondary);
            line-height: 1.7;
            font-size: .95rem;
        ">
            Tải ứng dụng Karing phù hợp với thiết bị của bạn.
            Các ứng dụng kết nối là phần mềm của bên thứ ba và được phát triển
            bởi nhà phát triển tương ứng. Vui lòng tải đúng phiên bản cho hệ điều hành
            đang sử dụng và tham khảo hướng dẫn của nhà phát triển khi cài đặt.
        </p>
    </div>

    <!-- Download platforms -->
    <div class="home-download-grid" style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.25rem;
    ">

        <!-- iPhone & iPad -->
        <a
            class="glass-btn home-download-card"
            href="/client?tag=ios-stable"
            data-no-loader
            data-youtube=""
            style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .6rem;
                min-height: 120px;
                padding: 1.25rem 1rem;
                border-radius: 16px;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(167, 139, 250, 0.15) 100%);
                border: 1px solid rgba(167, 139, 250, 0.50);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                transition: transform 0.25s ease, box-shadow 0.25s ease;
            "
        >
            <svg width="36" height="36" viewBox="0 0 24 24" fill="#a78bfa" style="filter: drop-shadow(0 2px 6px rgba(167,139,250,0.4));">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.32c.62-.75 1.04-1.8 0.93-2.85-.9.04-1.99.6-2.63 1.35-.58.67-.98 1.74-.84 2.78 1.01.08 2.03-.53 2.54-1.28z"/>
            </svg>
            <strong style="color: #a78bfa; font-size: 1.05rem;">iPhone &amp; iPad</strong>
            <small style="opacity: .75; color: #4b78c5;">iOS / iPadOS</small>
            <small style="opacity: .75; color: #4b78c5;">Phiên bản theo thời điểm</small>
        </a>

        <!-- Android -->
        <a
            class="glass-btn home-download-card"
            href="/client?tag=android-stable"
            data-no-loader
            data-youtube=""
            style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .6rem;
                min-height: 120px;
                padding: 1.25rem 1rem;
                border-radius: 16px;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(52, 211, 153, 0.15) 100%);
                border: 1px solid rgba(52, 211, 153, 0.50);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                transition: transform 0.25s ease, box-shadow 0.25s ease;
            "
        >
            <svg width="36" height="36" viewBox="0 0 24 24" fill="#34d399" style="filter: drop-shadow(0 2px 6px rgba(52,211,153,0.4));">
                <path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997 0-.551.4482-.9993.9993-.9993.5519 0 .9997.4483.9997.9993 0 .5511-.4478.9997-.9997.9997m11.4045-6.02l1.9973-3.4592a.416.416 0 00-.1521-.5676.416.416 0 00-.5676.1521l-2.0223 3.503C15.5898 8.169 13.8552 7.75 12 7.75c-1.8552 0-3.5898.419-5.1368 1.1997L4.8409 5.4467a.416.416 0 00-.5676-.1521.416.416 0 00-.1521.5676l1.9973 3.4592C2.6889 11.2867.3333 14.8872.3333 19h23.3334c0-4.1128-2.3556-7.7133-5.7887-9.6786"/>
            </svg>
            <strong style="color: #34d399; font-size: 1.05rem;">Android</strong>
            <small style="opacity: .75; color: #4b78c5;">APK ARM64</small>
            <small style="opacity: .75; color: #4b78c5;">Karing v1.2.25.2802</small>
        </a>

        <!-- Windows -->
        <a
            class="glass-btn home-download-card"
            href="/client?tag=windows-stable"
            data-no-loader
            data-youtube=""
            style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .6rem;
                min-height: 120px;
                padding: 1.25rem 1rem;
                border-radius: 16px;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(56, 189, 248, 0.15) 100%);
                border: 1px solid rgba(56, 189, 248, 0.50);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                transition: transform 0.25s ease, box-shadow 0.25s ease;
            "
        >
            <svg width="36" height="36" viewBox="0 0 24 24" fill="#38bdf8" style="filter: drop-shadow(0 2px 6px rgba(56,189,248,0.4));">
                <path d="M0 3.449L9.75 2.1v9.451H0zm10.55-1.509L24 0v11.4H10.55zM0 12.6h9.75v9.451L0 20.701zm10.55 0H24V24l-13.45-1.899z"/>
            </svg>
            <strong style="color: #38bdf8; font-size: 1.05rem;">Windows</strong>
            <small style="opacity: .75; color: #4b78c5;">Windows x64</small>
            <small style="opacity: .75; color: #4b78c5;">Karing v1.2.25.2802</small>
        </a>

        <!-- macOS -->
        <a
            class="glass-btn home-download-card"
            href="/client?tag=macos-stable"
            data-no-loader
            data-youtube=""
            style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .6rem;
                min-height: 120px;
                padding: 1.25rem 1rem;
                border-radius: 16px;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(192, 132, 252, 0.15) 100%);
                border: 1px solid rgba(192, 132, 252, 0.50);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                transition: transform 0.25s ease, box-shadow 0.25s ease;
            "
        >
            <svg width="36" height="36" viewBox="0 0 24 24" fill="#c084fc" style="filter: drop-shadow(0 2px 6px rgba(192,132,252,0.4));">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.32c.62-.75 1.04-1.8 0.93-2.85-.9.04-1.99.6-2.63 1.35-.58.67-.98 1.74-.84 2.78 1.01.08 2.03-.53 2.54-1.28z"/>
            </svg>
            <strong style="color: #c084fc; font-size: 1.05rem;">macOS</strong>
            <small style="opacity: .75; color: #4b78c5;">Universal DMG</small>
            <small style="opacity: .75; color: #4b78c5;">Karing v1.2.25.2802</small>
        </a>

        <!-- Linux -->
        <a
            class="glass-btn home-download-card"
            href="/client?tag=linux-stable"
            data-no-loader
            data-youtube=""
            style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .6rem;
                min-height: 120px;
                padding: 1.25rem 1rem;
                border-radius: 16px;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(251, 146, 60, 0.15) 100%);
                border: 1px solid rgba(251, 146, 60, 0.50);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                transition: transform 0.25s ease, box-shadow 0.25s ease;
            "
        >
            <svg width="36" height="36" viewBox="0 0 24 24" fill="#fb923c" style="filter: drop-shadow(0 2px 6px rgba(251,146,60,0.4));">
                <path d="M12.002 2c-2.83 0-4.66 1.928-4.66 4.333 0 1.056.368 2.083.896 2.875C6.547 10.02 5.5 11.64 5.5 13.5c0 1.632.81 3.064 2.052 3.965-.486 1.15-1.42 2.226-2.902 2.922-.224.105-.316.375-.205.596.11.222.373.317.595.205 2.502-1.18 3.935-2.73 4.673-4.22.753.18 1.55.282 2.387.282.837 0 1.634-.102 2.387-.282.738 1.49 2.17 3.04 4.673 4.22.222.112.485.017.595-.205.11-.22-.02-.49-.205-.596-1.482-.696-2.416-1.772-2.902-2.922 1.242-.901 2.052-2.333 2.052-3.965 0-1.86-1.047-3.48-2.738-4.292.528-.792.896-1.819.896-2.875 0-2.405-1.83-4.333-4.66-4.333z"/>
            </svg>
            <strong style="color: #fb923c; font-size: 1.05rem;">Linux</strong>
            <small style="opacity: .75; color: #4b78c5;">AppImage x64</small>
            <small style="opacity: .75; color: #4b78c5;">Karing v1.2.25.2802</small>
        </a>

    </div>

    <!-- Information -->
    <div class="home-download-note" style="
        margin-top: 1.75rem;
        padding: 1rem 1.1rem;
        border-radius: 14px;
        background: rgba(127, 127, 127, .08);
        color: var(--ios-text-secondary);
        font-size: .85rem;
        line-height: 1.6;
        text-align: center;
    ">
        <strong style="color: #fb923c;">
            Lưu ý:
        </strong>
        Hãy chọn đúng hệ điều hành của thiết bị để tải phiên bản tương ứng.
    </div>

</div>
</section>

<script>
(function () {

    let downloadTimer = null;
    let countdownInterval = null;

    function showDownloadNotice(youtubeUrl) {

        /* Xóa timer và thông báo cũ nếu đang chạy */
        if (downloadTimer) clearTimeout(downloadTimer);
        if (countdownInterval) clearInterval(countdownInterval);

        const oldNotice = document.getElementById('vc-download-notice');
        if (oldNotice) {
            oldNotice.remove();
        }

        /* Tạo thông báo */
        const notice = document.createElement('div');
        notice.id = 'vc-download-notice';

        notice.style.cssText = [
            'position:fixed',
            'left:50%',
            'top:50%',
            'transform:translate(-50%,-50%) scale(0.85)',
            'width:min(92vw,520px)',
            'box-sizing:border-box',
            'padding:20px 24px',
            'background:#18181b',
            'color:#ffffff',
            'border-radius:18px',
            'box-shadow:0 16px 48px rgba(0,0,0,.45)',
            'z-index:2147483647',
            'font-family:inherit',
            'text-align:center',
            'line-height:1.5',
            'opacity:0',
            'transition:transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease'
        ].join(';');

        /* Tiêu đề */
        const title = document.createElement('div');
        title.style.cssText = [
            'font-size:16px',
            'font-weight:700',
            'margin-bottom:8px'
        ].join(';');
        title.textContent = '⬇️ Ứng dụng đang được tải xuống';
        notice.appendChild(title);

        /* Nội dung */
        const message = document.createElement('div');
        message.style.cssText = [
            'font-size:14px',
            'color:rgba(255,255,255,.85)'
        ].join(';');

        let secondsLeft = 4;

        function updateMessageText() {
            if (youtubeUrl !== '') {
                message.innerHTML = 'Trong khi chờ, chúng tôi sẽ chuyển bạn đến video hướng dẫn cấu hình sau <strong style="color:#60a5fa;font-size:15px;">' + secondsLeft + 's</strong>...';
            } else {
                message.textContent = 'Vui lòng kiểm tra danh sách tệp đã tải xuống trên thiết bị.';
            }
        }

        updateMessageText();
        notice.appendChild(message);

        /* Nút đóng (X) */
        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = '×';
        close.style.cssText = [
            'position:absolute',
            'top:8px',
            'right:12px',
            'border:0',
            'background:transparent',
            'color:#ffffff',
            'font-size:24px',
            'line-height:1',
            'cursor:pointer',
            'opacity:.7'
        ].join(';');

        close.addEventListener('click', function () {
            /* Hủy chuyển hướng và đếm ngược khi nhấn X */
            if (downloadTimer) clearTimeout(downloadTimer);
            if (countdownInterval) clearInterval(countdownInterval);

            notice.style.transform = 'translate(-50%,-50%) scale(0.85)';
            notice.style.opacity = '0';
            setTimeout(function () {
                if (notice.parentNode) {
                    notice.remove();
                }
            }, 300);
        });

        notice.appendChild(close);
        document.body.appendChild(notice);

        /* Hiệu ứng nảy lên mượt mà */
        requestAnimationFrame(function () {
            notice.style.transform = 'translate(-50%,-50%) scale(1)';
            notice.style.opacity = '1';
        });

        /* Đếm ngược 4s và chuyển hướng YouTube */
        if (youtubeUrl !== '') {

            countdownInterval = setInterval(function () {
                secondsLeft--;
                if (secondsLeft > 0) {
                    updateMessageText();
                } else {
                    clearInterval(countdownInterval);
                }
            }, 1000);

            downloadTimer = setTimeout(function () {
                window.open(youtubeUrl, '_blank', 'noopener,noreferrer');

                notice.style.transform = 'translate(-50%,-50%) scale(0.85)';
                notice.style.opacity = '0';
                setTimeout(function () {
                    if (notice.parentNode) {
                        notice.remove();
                    }
                }, 300);
            }, 4000);

        } else {

            setTimeout(function () {
                if (notice.parentNode) {
                    notice.style.opacity = '0';
                    setTimeout(function () {
                        if (notice.parentNode) notice.remove();
                    }, 300);
                }
            }, 6000);

        }
    }


    function startDownload(downloadUrl) {

        /*
         * Dùng iframe ẩn để kích hoạt /client.
         * Trang hiện tại không bị chuyển đi.
         */
        const iframe = document.createElement('iframe');

        iframe.src = downloadUrl;

        iframe.style.cssText = [
            'position:fixed',
            'width:1px',
            'height:1px',
            'left:-9999px',
            'top:-9999px',
            'border:0',
            'opacity:0',
            'pointer-events:none'
        ].join(';');

        iframe.setAttribute('aria-hidden', 'true');

        document.body.appendChild(iframe);

        /*
         * Xóa iframe sau 30 giây.
         */
        setTimeout(function () {

            if (iframe.parentNode) {
                iframe.parentNode.removeChild(iframe);
            }

        }, 30000);
    }


    function initDownloadButtons() {

        const buttons = document.querySelectorAll(
            'a[data-youtube][href^="/client?tag="]'
        );

        buttons.forEach(function (button) {

            /*
             * Tránh đăng ký event nhiều lần.
             */
            if (button.dataset.downloadInitialized === '1') {
                return;
            }

            button.dataset.downloadInitialized = '1';

            button.addEventListener('click', function (event) {

                const downloadUrl =
                    (button.getAttribute('href') || '').trim();

                const youtubeUrl =
                    (button.getAttribute('data-youtube') || '').trim();

                if (downloadUrl === '') {
                    return;
                }

                /*
                 * Kiểm tra thiết bị iOS / iPadOS hoặc nút dành cho iOS.
                 */
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) ||
                    downloadUrl.indexOf('tag=ios') !== -1;

                if (isIOS) {
                    /*
                     * Thiết bị iOS: Cho phép mở liên kết tự nhiên sang trang ứng dụng,
                     * không hiện thông báo và không mở link YouTube.
                     */
                    return;
                }

                /*
                 * Các hệ điều hành khác (Android, Windows, macOS, Linux):
                 */
                event.preventDefault();
                event.stopPropagation();

                showDownloadNotice(youtubeUrl);
                startDownload(downloadUrl);

            });

        });
    }


    /*
     * Khởi tạo khi DOM đã sẵn sàng.
     */
    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            initDownloadButtons
        );

    } else {

        initDownloadButtons();

    }

})();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>