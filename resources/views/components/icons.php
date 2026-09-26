<?php
/**
 * Sprite icon SVG dùng cho khu vực User.
 * Nạp 1 lần qua layouts/app.php (khi $pageArea === 'user').
 *
 * Cách dùng trong view:
 *   <svg class="u-icon" aria-hidden="true"><use href="#vc-i-wallet"></use></svg>
 *   <svg class="u-icon u-icon--lg" aria-hidden="true"><use href="#vc-i-shield"></use></svg>
 *
 * Quy ước: viewBox 24x24, stroke="currentColor", fill="none",
 * stroke-width theo chuẩn lucide (2). Class .u-icon trong user.css
 * đã set stroke/fill/size nên chỉ cần <use>.
 */
?>
<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
  <defs>
    <!-- Điều hướng / chung -->
    <symbol id="vc-i-dashboard" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></symbol>
    <symbol id="vc-i-home" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/></symbol>
    <symbol id="vc-i-menu" viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></symbol>
    <symbol id="vc-i-chevron-down" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></symbol>
    <symbol id="vc-i-chevron-right" viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></symbol>
    <symbol id="vc-i-arrow-left" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 18 5 12 11 6"/></symbol>
    <symbol id="vc-i-arrow-right" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/></symbol>
    <symbol id="vc-i-external" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></symbol>

    <!-- Bảo mật / hệ thống -->
    <symbol id="vc-i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></symbol>
    <symbol id="vc-i-lock" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1.4"/></symbol>
    <symbol id="vc-i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4.5"/><path d="M11.5 11.5 20 3"/><path d="M17 6l2.5 2.5"/><path d="M15 8l2.5 2.5"/></symbol>
    <symbol id="vc-i-server" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><line x1="7" y1="7.5" x2="7.01" y2="7.5"/><line x1="7" y1="16.5" x2="7.01" y2="16.5"/></symbol>
    <symbol id="vc-i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3a15.3 15.3 0 0 1 4 9 15.3 15.3 0 0 1-4 9 15.3 15.3 0 0 1-4-9 15.3 15.3 0 0 1 4-9z"/></symbol>
    <symbol id="vc-i-zap" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></symbol>
    <symbol id="vc-i-refresh" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></symbol>

    <!-- Tài chính -->
    <symbol id="vc-i-wallet" viewBox="0 0 24 24"><path d="M20 12V8H6a2 2 0 0 1 0-4h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></symbol>
    <symbol id="vc-i-credit-card" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></symbol>
    <symbol id="vc-i-receipt" viewBox="0 0 24 24"><path d="M5 3h14v18l-2.5-1.6L14 21l-2-1.6L10 21l-2.5-1.6L5 21z"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/></symbol>
    <symbol id="vc-i-coins" viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.66 3.58 3 8 3s8-1.34 8-3V6"/><path d="M4 12v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></symbol>
    <symbol id="vc-i-trending" viewBox="0 0 24 24"><polyline points="3 17 9.5 10.5 13.5 14.5 21 7"/><polyline points="15 7 21 7 21 13"/></symbol>
    <symbol id="vc-i-bank" viewBox="0 0 24 24"><path d="M3 10.5 12 4l9 6.5"/><line x1="5" y1="10.5" x2="5" y2="19"/><line x1="9.5" y1="10.5" x2="9.5" y2="19"/><line x1="14.5" y1="10.5" x2="14.5" y2="19"/><line x1="19" y1="10.5" x2="19" y2="19"/><line x1="3" y1="19" x2="21" y2="19"/><line x1="3" y1="21.5" x2="21" y2="21.5"/></symbol>

    <!-- Đơn hàng / dịch vụ -->
    <symbol id="vc-i-cart" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.6"/><circle cx="17.5" cy="20" r="1.6"/><path d="M3 4h2.2l2.3 11.2a1.6 1.6 0 0 0 1.6 1.3h8.4a1.6 1.6 0 0 0 1.6-1.3L21 8H6"/></symbol>
    <symbol id="vc-i-box" viewBox="0 0 24 24"><path d="M21 8.5 12 3.5 3 8.5v7L12 20.5l9-5z"/><polyline points="3 8.5 12 13.5 21 8.5"/><line x1="12" y1="13.5" x2="12" y2="20.5"/></symbol>
    <symbol id="vc-i-layers" viewBox="0 0 24 24"><polygon points="12 2.5 21 7.5 12 12.5 3 7.5 12 2.5"/><polyline points="3 12.5 12 17.5 21 12.5"/><polyline points="3 17 12 22 21 17"/></symbol>
    <symbol id="vc-i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></symbol>
    <symbol id="vc-i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></symbol>

    <!-- Hỗ trợ / thông báo -->
    <symbol id="vc-i-ticket" viewBox="0 0 24 24"><path d="M21 5H3a1 1 0 0 0-1 1v3.5a2.5 2.5 0 0 1 0 5V18a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-3.5a2.5 2.5 0 0 1 0-5V6a1 1 0 0 0-1-1z"/><line x1="9" y1="5" x2="9" y2="19" stroke-dasharray="2.5 3"/></symbol>
    <symbol id="vc-i-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 1 0-12 0c0 6-2.5 7.5-2.5 7.5h17S18 15 18 9z"/><path d="M10.3 20a2 2 0 0 0 3.4 0"/></symbol>
    <symbol id="vc-i-chat" viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z"/></symbol>
    <symbol id="vc-i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3.5 7 12 13 20.5 7"/></symbol>

    <!-- Người dùng -->
    <symbol id="vc-i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.8 7.8 0 0 1 15 0"/></symbol>
    <symbol id="vc-i-users" viewBox="0 0 24 24"><circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 20a6.7 6.7 0 0 1 13 0"/><path d="M16 5.5a3.5 3.5 0 0 1 0 6.6"/><path d="M17.8 14.4A6.7 6.7 0 0 1 21.5 20"/></symbol>
    <symbol id="vc-i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.56V21a2 2 0 1 1-4 0v-.11a1.7 1.7 0 0 0-1.11-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1H3a2 2 0 1 1 0-4h.11A1.7 1.7 0 0 0 4.6 8.9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.56V3a2 2 0 1 1 4 0v.11a1.7 1.7 0 0 0 1 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.56 1H21a2 2 0 1 1 0 4h-.11a1.7 1.7 0 0 0-1.49 1z"/></symbol>
    <symbol id="vc-i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></symbol>

    <!-- Hành động -->
    <symbol id="vc-i-check" viewBox="0 0 24 24"><polyline points="4.5 12.5 9.5 17.5 19.5 6.5"/></symbol>
    <symbol id="vc-i-x" viewBox="0 0 24 24"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></symbol>
    <symbol id="vc-i-plus" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
    <symbol id="vc-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></symbol>
    <symbol id="vc-i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="vc-i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></symbol>
    <symbol id="vc-i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
    <symbol id="vc-i-upload" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></symbol>
    <symbol id="vc-i-trash" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/></symbol>
    <symbol id="vc-i-edit" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></symbol>
    <symbol id="vc-i-more" viewBox="0 0 24 24"><circle cx="12" cy="5.5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="18.5" r="1.6"/></symbol>
    <symbol id="vc-i-filter" viewBox="0 0 24 24"><polygon points="21 4 3 4 10.5 12.5 10.5 19 13.5 20.5 13.5 12.5 21 4"/></symbol>

    <!-- Trạng thái -->
    <symbol id="vc-i-alert" viewBox="0 0 24 24"><path d="M10.3 4 2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 4a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9.5" x2="12" y2="14"/><line x1="12" y1="17" x2="12.01" y2="17"/></symbol>
    <symbol id="vc-i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16.5"/><line x1="12" y1="7.8" x2="12.01" y2="7.8"/></symbol>
    <symbol id="vc-i-check-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="8 12.5 11 15.5 16.5 9.5"/></symbol>
    <symbol id="vc-i-x-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></symbol>
    <symbol id="vc-i-pause-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="10" y1="9" x2="10" y2="15"/><line x1="14" y1="9" x2="14" y2="15"/></symbol>
    <symbol id="vc-i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.6 9.5a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.1-2.4 3.6"/><line x1="12" y1="17" x2="12.01" y2="17"/></symbol>

    <!-- Nội dung -->
    <symbol id="vc-i-book" viewBox="0 0 24 24"><path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/></symbol>
    <symbol id="vc-i-file" viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="14 3 14 9 20 9"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></symbol>
    <symbol id="vc-i-image" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.8"/><polyline points="21 16 15.5 10.5 6 20"/></symbol>
    <symbol id="vc-i-megaphone" viewBox="0 0 24 24"><path d="M3 11v3a1 1 0 0 0 1 1h2l3 5h2v-5.5L19 17a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1l-11 4H4a1 1 0 0 0-1 1z"/><path d="M21 10a4 4 0 0 1 0 4"/></symbol>

    <!-- Thanh toán / QR -->
    <symbol id="vc-i-qr" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><line x1="14" y1="14" x2="17" y2="14"/><line x1="20.5" y1="14" x2="20.5" y2="17"/><line x1="14" y1="17.5" x2="14" y2="21"/><line x1="17.5" y1="21" x2="21" y2="21"/><line x1="20.5" y1="17.5" x2="21" y2="17.5"/></symbol>
    <symbol id="vc-i-link" viewBox="0 0 24 24"><path d="M10.5 13.5a4.5 4.5 0 0 0 6.4 0l2.6-2.6a4.5 4.5 0 0 0-6.4-6.4L11.7 6"/><path d="M13.5 10.5a4.5 4.5 0 0 0-6.4 0l-2.6 2.6a4.5 4.5 0 0 0 6.4 6.4l1.4-1.5"/></symbol>

    <!-- Khác -->
    <symbol id="vc-i-star" viewBox="0 0 24 24"><polygon points="12 3 14.8 8.7 21 9.6 16.5 14 17.6 20.2 12 17.3 6.4 20.2 7.5 14 3 9.6 9.2 8.7 12 3"/></symbol>
    <symbol id="vc-i-gift" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8"/><line x1="12" y1="8" x2="12" y2="21"/><path d="M12 8S9.5 8 8.5 7a1.8 1.8 0 0 1 0-3.5C10.5 3.5 12 8 12 8z"/><path d="M12 8s2.5 0 3.5-1a1.8 1.8 0 0 0 0-3.5C13.5 3.5 12 8 12 8z"/></symbol>
    <symbol id="vc-i-rocket" viewBox="0 0 24 24"><path d="M12 2.5s5.5 3 5.5 9.5c0 2.4-.8 4.6-2 6.3H8.5a10.6 10.6 0 0 1-2-6.3C6.5 5.5 12 2.5 12 2.5z"/><circle cx="12" cy="10.5" r="2"/><path d="M8.5 18.3 6 21.5l3.5-1"/><path d="M15.5 18.3 18 21.5l-3.5-1"/></symbol>
    <symbol id="vc-i-database" viewBox="0 0 24 24"><ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.66 3.58 3 8 3s8-1.34 8-3v-13"/><path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3"/></symbol>
  </defs>
</svg>
