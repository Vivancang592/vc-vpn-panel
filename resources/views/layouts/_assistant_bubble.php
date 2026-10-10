<?php
// Bong bóng Trợ Lý Admin — nổi toàn trang admin, nhấp vào mở popup toàn màn hình.
// Partial được require 1 lần ở layouts/admin.php (nhánh trang đầy đủ; nhánh fragment
// JSON exit trước đó) nên KHÔNG set $pageTitle/$activeMenu và KHÔNG ob_start().
// Lịch sử/model/kế hoạch tải LẦN ĐẦU qua endpoint bootstrap khi admin mở bong bóng.
$csrf_token = $csrf_token ?? ($_SESSION['csrf_token'] ?? '');

/** @var array<int, array<string, mixed>> $conversations */
$conversations = $conversations ?? [];
/** @var array<int, array<string, mixed>> $plans */
$plans = $plans ?? [];
// Model list KHÔNG nhúng ở đây — select được populate trong bootstrap() khi mở bong bóng.

$planKindIcons = [
    'general' => vc_admin_icon('GEN', 14),
    'sales'   => vc_admin_icon('BIZ', 14),
    'content' => vc_admin_icon('CNT', 14),
    'other'   => vc_admin_icon('OTH', 14),
];
$suggestions = [
    'Doanh thu tháng này bao nhiêu?',
    'Gói cước nào bán chạy nhất?',
    'Khách hàng mới 7 ngày qua?',
    'Chi phí tháng này gồm những gì?',
    'Trạng thái đăng ký VPN hiện tại?',
];
?>

<style>
    .asst-head {
        display: flex; justify-content: space-between; align-items: center;
        gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; width: 100%; box-sizing: border-box;
    }
    /* Tiêu đề rõ ràng: icon + tên đậm + mô tả ngắn — màu theo theme hệ thống. */
    .asst-head-info { display: flex; align-items: center; gap: 0.7rem; min-width: 0; }
    .asst-head-icon { font-size: 1.7rem; line-height: 1; }
    .asst-head h1 {
        font-size: 1.4rem; font-weight: 800; margin: 0 0 0.15rem;
        color: var(--ios-text, #132238); letter-spacing: 0.2px;
    }
    .asst-head p { color: var(--ios-text-secondary, #617087); font-size: 0.82rem; margin: 0; line-height: 1.45; }
    .asst-head p code { color: var(--ios-blue, #1677ff); }
    .asst-tools { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .asst-tools select {
        padding: 0.45rem 0.6rem; border-radius: var(--radius-sm);
        border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.2);
        color: var(--ios-text, #132238); font-size: 0.85rem; max-width: 220px;
    }

    .asst-wrap {
        display: flex; gap: 1rem; width: 100%; box-sizing: border-box;
        height: clamp(520px, calc(100vh - 250px), 920px);
    }
    .asst-panel {
        background: var(--glass-bg); border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg); backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur); overflow: hidden;
    }

    /* --- Sidebar: lịch sử chat + kế hoạch --- */
    .asst-side { flex: 0 0 264px; width: 264px; display: flex; flex-direction: column; min-height: 0; }
    .asst-side-top { padding: 0.75rem; border-bottom: 1px solid var(--glass-border); display: flex; flex-direction: column; gap: 0.5rem; }
    .asst-new-btn {
        display: block; width: 100%; box-sizing: border-box; text-align: center;
        padding: 0.55rem 0.7rem; border-radius: var(--radius-sm); border: 1px dashed var(--ios-blue);
        background: rgba(0, 122, 255, 0.08); color: var(--ios-blue);
        font-weight: 700; font-size: 0.85rem; cursor: pointer;
    }
    .asst-new-btn:hover { background: rgba(0, 122, 255, 0.16); }
    .asst-plan-btn {
        border-style: solid; border-color: var(--glass-border);
        background: rgba(255, 255, 255, 0.06); color: inherit; font-weight: 600;
    }
    .asst-plan-btn:hover { border-color: var(--ios-blue); color: var(--ios-blue); background: rgba(0, 122, 255, 0.12); }
    .asst-sec-label {
        padding: 0.6rem 0.75rem 0.25rem; font-size: 0.72rem; font-weight: 700;
        letter-spacing: 0.04em; text-transform: uppercase; color: var(--ios-text-secondary);
        display: flex; justify-content: space-between; align-items: center;
    }
    .asst-list { flex: 1; min-height: 0; overflow-y: auto; padding: 0.25rem 0.5rem 0.5rem; }
    .asst-list::-webkit-scrollbar { width: 6px; }
    .asst-list::-webkit-scrollbar-thumb { background: var(--glass-border); border-radius: 3px; }
    .asst-item {
        display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.55rem;
        border-radius: var(--radius-sm); cursor: pointer; border: 1px solid transparent;
        margin-bottom: 2px;
    }
    .asst-item:hover { background: rgba(255, 255, 255, 0.05); }
    .asst-item.active { background: rgba(0, 122, 255, 0.14); border-color: rgba(0, 122, 255, 0.35); }
    .asst-item-main { flex: 1; min-width: 0; }
    .asst-item-title {
        font-size: 0.82rem; font-weight: 600; white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis;
    }
    .asst-item-meta { font-size: 0.7rem; color: var(--ios-text-secondary); }
    .asst-item-del {
        flex: 0 0 auto; border: none; background: none; color: var(--ios-text-secondary);
        font-size: 0.9rem; cursor: pointer; padding: 0.15rem 0.3rem; border-radius: 4px;
        opacity: 0; transition: opacity 0.15s;
    }
    .asst-item:hover .asst-item-del { opacity: 1; }
    .asst-item-del:hover { color: var(--ios-danger); background: rgba(255, 69, 58, 0.12); }
    .asst-side-plans { border-top: 1px solid var(--glass-border); max-height: 42%; display: flex; flex-direction: column; min-height: 0; }
    .asst-empty-note { padding: 0.5rem 0.75rem; font-size: 0.75rem; color: var(--ios-text-secondary); }

    /* --- Khung chat --- */
    .asst-main { flex: 1; min-width: 0; display: flex; flex-direction: column; min-height: 0; }
    .asst-msgs { flex: 1; min-height: 0; overflow-y: auto; padding: 1.1rem 1.25rem; display: flex; flex-direction: column; gap: 0.9rem; }
    .asst-msgs::-webkit-scrollbar { width: 6px; }
    .asst-msgs::-webkit-scrollbar-thumb { background: var(--glass-border); border-radius: 3px; }
    .asst-row { display: flex; gap: 0.6rem; max-width: 860px; }
    .asst-row.user { align-self: flex-end; flex-direction: row-reverse; }
    .asst-avatar {
        flex: 0 0 auto; width: 30px; height: 30px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; background: rgba(255, 255, 255, 0.07);
        border: 1px solid var(--glass-border);
    }
    .asst-bubble {
        padding: 0.65rem 0.9rem; border-radius: 14px; font-size: 0.88rem;
        line-height: 1.55; word-break: break-word; min-width: 0;
        background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border);
    }
    .asst-row.user .asst-bubble { background: rgba(0, 122, 255, 0.18); border-color: rgba(0, 122, 255, 0.35); }
    .asst-bubble.failed { border-color: var(--ios-danger); }
    .asst-retry {
        border: 1px solid var(--ios-danger);
        background: rgba(255, 59, 48, 0.08);
        color: var(--ios-danger);
        border-radius: 7px;
        padding: 1px 8px;
        font-size: 0.66rem;
        font-weight: 700;
        cursor: pointer;
        margin-left: 4px;
    }
    .asst-retry:hover { background: rgba(255, 59, 48, 0.16); }
    .asst-bubble p { margin: 0 0 0.5rem; }
    .asst-bubble p:last-child { margin-bottom: 0; }
    .asst-bubble h2, .asst-bubble h3, .asst-bubble h4 { margin: 0.6rem 0 0.35rem; font-size: 0.95rem; }
    .asst-bubble ul, .asst-bubble ol { margin: 0.3rem 0 0.5rem; padding-left: 1.25rem; }
    .asst-bubble li { margin: 0.15rem 0; }
    .asst-bubble pre {
        background: rgba(0, 0, 0, 0.35); border: 1px solid var(--glass-border);
        border-radius: 8px; padding: 0.6rem 0.75rem; overflow-x: auto;
        font-size: 0.78rem; margin: 0.4rem 0;
    }
    .asst-bubble code { font-family: Consolas, Monaco, monospace; font-size: 0.82em; }
    .asst-bubble :not(pre) > code {
        background: rgba(0, 122, 255, 0.12); padding: 0.08em 0.35em; border-radius: 4px;
    }
    .asst-ts { font-size: 0.66rem; color: var(--ios-text-secondary); margin-top: 0.3rem; text-align: right; }
    .asst-img {
        max-width: 220px; max-height: 220px; border-radius: 8px;
        display: block; margin: 0.35rem 0; border: 1px solid var(--glass-border);
    }
    .asst-file-chip {
        display: inline-flex; align-items: center; gap: 0.3rem; margin: 0.15rem 0.25rem 0.15rem 0;
        padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.75rem;
        background: rgba(255, 255, 255, 0.08); border: 1px solid var(--glass-border);
    }
    .asst-typing .asst-bubble { color: var(--ios-text-secondary); }
    .asst-dot { animation: asst-blink 1.2s infinite both; font-size: 1.1rem; }
    .asst-dot:nth-child(2) { animation-delay: 0.2s; }
    .asst-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes asst-blink { 0% { opacity: 0.2; } 50% { opacity: 1; } 100% { opacity: 0.2; } }

    /* --- Khung chào / gợi ý --- */
    .asst-welcome { margin: auto; text-align: center; max-width: 560px; padding: 1rem; }
    .asst-welcome-icon { font-size: 2.4rem; margin-bottom: 0.5rem; }
    .asst-welcome h3 { margin: 0 0 0.35rem; font-size: 1.1rem; }
    .asst-welcome p { margin: 0 0 1rem; color: var(--ios-text-secondary); font-size: 0.85rem; }
    .asst-sugs { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
    .asst-sug {
        padding: 0.45rem 0.8rem; border-radius: 999px; font-size: 0.8rem; cursor: pointer;
        border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.05);
        color: inherit;
    }
    .asst-sug:hover { border-color: var(--ios-blue); color: var(--ios-blue); }

    /* --- Composer --- */
    .asst-composer { border-top: 1px solid var(--glass-border); padding: 0.7rem 0.9rem 0.85rem; }
    .asst-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.5rem; }
    .asst-chips:empty { display: none; }
    .asst-chip {
        display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.22rem 0.5rem;
        border-radius: 999px; font-size: 0.75rem; background: rgba(255, 255, 255, 0.07);
        border: 1px solid var(--glass-border); max-width: 220px;
    }
    .asst-chip span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .asst-chip img { width: 20px; height: 20px; object-fit: cover; border-radius: 4px; }
    .asst-chip button { border: none; background: none; color: var(--ios-text-secondary); cursor: pointer; padding: 0; font-size: 0.85rem; }
    .asst-chip button:hover { color: var(--ios-danger); }
    /* Ô nhập kiểu GEMINI: nút thêm ảnh (trái) + ô soạn (giữa) + nút gửi (phải)
       nằm TRONG một khung bo góc duy nhất, không tách rời nhau. */
    .asst-input-row {
        position: relative; display: flex; gap: 0.25rem; align-items: flex-end;
        border: 1px solid var(--glass-border); border-radius: 14px;
        background: rgba(0, 0, 0, 0.18); padding: 4px;
    }
    .asst-input-row:focus-within { border-color: var(--ios-blue); }
    .asst-ico-btn {
        flex: 0 0 auto; width: 36px; height: 36px; border-radius: 10px; cursor: pointer;
        border: none; background: transparent; color: #9aa4b2;
        font-size: 1.15rem; display: flex; align-items: center; justify-content: center;
    }
    .asst-ico-btn:hover { color: var(--ios-blue); background: rgba(0, 122, 255, 0.14); }
    .asst-ico-btn:disabled { opacity: 0.45; cursor: not-allowed; }
    .asst-input {
        flex: 1; min-width: 0; resize: none; padding: 0.5rem 0.35rem;
        border-radius: 10px; border: none; background: transparent; color: inherit;
        font-size: 0.88rem; font-family: inherit; line-height: 1.45; min-height: 36px;
        overflow-y: hidden; /* tự mở rộng theo hàng — không cuộn, không tràn scrollbar ra ngoài bo góc */
    }
    .asst-input:focus { outline: none; border: none; }
    .asst-send {
        flex: 0 0 auto; width: 36px; height: 36px; border-radius: 10px; border: none;
        background: var(--ios-blue); color: #fff; font-size: 1rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .asst-send:disabled { opacity: 0.45; cursor: not-allowed; }
    .asst-note { font-size: 0.7rem; color: var(--ios-text-secondary); margin-top: 0.4rem; }

    /* Menu chọn nguồn ảnh khi bấm nút thêm ảnh (máy / thư mục AI) */
    .asst-attach-menu {
        position: absolute; left: 0; bottom: calc(100% + 8px); z-index: 30;
        min-width: 250px; padding: 0.3rem; display: flex; flex-direction: column; gap: 2px;
        background: var(--glass-bg, #101726); border: 1px solid var(--glass-border);
        border-radius: 12px; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
    }
    .asst-attach-menu[hidden] { display: none; }
    .asst-attach-menu button {
        display: flex; align-items: center; gap: 0.55rem; width: 100%; box-sizing: border-box;
        padding: 0.55rem 0.65rem; border: none; border-radius: 8px; background: transparent;
        color: #f2f4f8; font-size: 0.85rem; cursor: pointer; text-align: left;
    }
    .asst-attach-menu button:hover { background: rgba(0, 122, 255, 0.16); }

    /* Lưới ảnh do AI tạo (modal chọn ảnh từ thư mục AI) */
    .asst-ai-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.65rem; }
    .asst-ai-card {
        display: flex; flex-direction: column; padding: 0; cursor: pointer; color: inherit; text-align: left;
        border: 1px solid var(--glass-border); border-radius: 10px; overflow: hidden;
        background: rgba(255, 255, 255, 0.04);
    }
    .asst-ai-card:hover { border-color: var(--ios-blue); }
    .asst-ai-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; background: rgba(0, 0, 0, 0.35); }
    .asst-ai-card span { font-size: 0.72rem; padding: 0.4rem 0.5rem; color: var(--ios-text-secondary); word-break: break-all; }
    .asst-ai-empty { color: var(--ios-text-secondary); font-size: 0.85rem; text-align: center; padding: 1.5rem 0.5rem; }

    /* --- Modal kế hoạch --- */
    .asst-modal {
        position: fixed; inset: 0; z-index: 10040; display: none;
        align-items: center; justify-content: center; padding: 1rem;
        background: rgba(0, 0, 0, 0.55);
    }
    .asst-modal.open { display: flex; }
    .asst-modal-box {
        width: min(80vw, 1600px); max-height: 86vh; overflow: hidden;
        display: flex; flex-direction: column;
        background: var(--glass-bg, #101726); border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg); padding: 1.25rem 1.35rem;
        box-shadow: var(--glass-shadow);
    }
    .asst-modal-box h3 {
        margin: 0 0 0.85rem; font-size: 1.05rem; display: flex; justify-content: space-between;
        gap: 1rem; align-items: center; flex: 0 0 auto; color: var(--ios-text, #172033);
    }
    /* Vùng cuộn riêng: tiêu đề + nút X đứng yên, không cuộn theo nội dung;
       scrollbar nằm trong padding của modal nên KHÔNG tràn ra ngoài bo góc. */
    .asst-modal-scroll {
        flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden;
        overscroll-behavior: contain; padding: 0 6px 4px 0;
    }
    .asst-modal-close { border: none; background: none; color: var(--ios-text, #172033); font-size: 1.3rem; cursor: pointer; line-height: 1; }
    .asst-modal-close:hover { color: var(--ios-danger); }
    .asst-field { margin-bottom: 0.8rem; }
    .asst-field label { display: block; font-size: 0.78rem; font-weight: 600; margin-bottom: 0.3rem; color: var(--ios-text-secondary); }
    .asst-field input[type="text"], .asst-field select, .asst-field textarea {
        width: 100%; box-sizing: border-box; padding: 0.5rem 0.7rem;
        border-radius: var(--radius-sm); border: 1px solid var(--glass-border);
        background: rgba(0, 0, 0, 0.18); color: inherit; font-size: 0.86rem; font-family: inherit;
    }
    .asst-field textarea { min-height: 84px; resize: vertical; }
    .asst-field input:focus, .asst-field select:focus, .asst-field textarea:focus { outline: none; border-color: var(--ios-blue); }
    .asst-modal-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; flex-wrap: wrap; flex: 0 0 auto; }
    .asst-btn {
        padding: 0.5rem 1rem; border-radius: var(--radius-sm); font-size: 0.85rem;
        font-weight: 600; cursor: pointer; border: 1px solid var(--glass-border);
        background: rgba(255, 255, 255, 0.18); color: var(--ios-text, #132238);
    }
    .asst-btn:hover { border-color: var(--ios-text-secondary); }
    .asst-btn.primary { background: var(--ios-blue); border-color: var(--ios-blue); color: #fff; }
    .asst-btn.primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .asst-btn.danger { color: var(--ios-danger); border-color: rgba(255, 69, 58, 0.4); }
    .asst-plan-card-head { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; font-size: 0.93rem; font-weight: 600; padding-bottom: 0.5rem; margin-bottom: 0.5rem; border-bottom: 1px dashed var(--glass-border); }
    .asst-plan-card-meta { font-size: 0.72rem; font-weight: 400; color: var(--ios-text-secondary); margin-left: auto; }
    .asst-plan-body { font-size: 0.88rem; line-height: 1.6; }
    .asst-plan-body h2, .asst-plan-body h3, .asst-plan-body h4 { margin: 0.8rem 0 0.4rem; }
    .asst-plan-body p { margin: 0 0 0.5rem; }
    .asst-plan-body ul, .asst-plan-body ol { margin: 0.3rem 0 0.6rem; padding-left: 1.3rem; }
    .asst-plan-body pre {
        background: rgba(0, 0, 0, 0.35); border: 1px solid var(--glass-border);
        border-radius: 8px; padding: 0.6rem 0.75rem; overflow-x: auto; font-size: 0.78rem;
    }

    /* —— Card bài viết xem trước (post_draft) — CHƯA lưu, bấm "Lưu Bài" —— */
    .asst-post-card .asst-post-head {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding-bottom: 8px; margin-bottom: 10px;
        border-bottom: 1px solid rgba(22, 119, 255, 0.2);
    }
    .asst-post-title { font-weight: 700; color: var(--ios-text, #132238); font-size: 14px; }
    .asst-post-chip {
        font-size: 11px; padding: 2px 9px; border-radius: 999px; font-weight: 600;
        background: rgba(22, 119, 255, 0.1); color: var(--ios-blue, #1677ff);
        border: 1px solid rgba(22, 119, 255, 0.32);
    }
    .asst-post-chip.saved {
        background: rgba(34, 160, 107, 0.12); color: var(--ios-success, #22a06b);
        border-color: rgba(34, 160, 107, 0.38);
    }
    .asst-post-chip.pending {
        background: rgba(217, 119, 6, 0.12); color: var(--ios-warning, #d97706);
        border-color: rgba(217, 119, 6, 0.38);
    }
    .asst-post-body { font-size: 13.5px; }
    .asst-post-body h1, .asst-post-body h2, .asst-post-body h3, .asst-post-body h4 { color: var(--ios-text, #132238); margin: 10px 0 6px; }
    .asst-post-body p { margin: 6px 0; color: var(--ios-text, #132238); }
    .asst-post-body ul, .asst-post-body ol { padding-left: 18px; margin: 6px 0; }
    .asst-post-body li { margin: 3px 0; color: var(--ios-text, #132238); }
    .asst-post-body a { color: var(--ios-blue, #1677ff); }
    .asst-post-body img { max-width: 100%; border-radius: 8px; margin: 6px 0; }
    .asst-post-body pre {
        background: #0f172a; color: #e2e8f0; border: 1px solid rgba(15, 23, 42, 0.4);
        border-radius: 8px; padding: 0.6rem 0.75rem; overflow-x: auto; font-size: 0.78rem;
    }
    .asst-post-foot {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        margin-top: 10px; padding-top: 10px;
        border-top: 1px solid rgba(22, 119, 255, 0.2);
    }
    .asst-post-hint { font-size: 12px; color: var(--ios-text-secondary, #617087); }

    /* —— Card hành động chờ xác nhận (action-confirm) — AI CHƯA chạy gì —— */
    .asst-confirm-card {
        border: 1px solid rgba(217, 119, 6, 0.45);
        box-shadow: 0 0 0 1px rgba(217, 119, 6, 0.08) inset;
    }
    .asst-confirm-card .asst-post-head {
        border-bottom-color: rgba(217, 119, 6, 0.28);
    }
    .asst-confirm-card .asst-post-title { font-size: 13.5px; }
    .asst-confirm-chip {
        font-size: 11px; padding: 2px 9px; border-radius: 999px; font-weight: 600;
        background: rgba(217, 119, 6, 0.12); color: var(--ios-warning, #d97706);
        border: 1px solid rgba(217, 119, 6, 0.38);
    }
    .asst-confirm-chip.done {
        background: rgba(34, 160, 107, 0.12); color: var(--ios-success, #22a06b);
        border-color: rgba(34, 160, 107, 0.38);
    }
    .asst-confirm-chip.off {
        background: rgba(100, 116, 139, 0.14); color: var(--ios-text-secondary, #617087);
        border-color: rgba(100, 116, 139, 0.34);
    }
    .asst-confirm-body { font-size: 13.5px; color: var(--ios-text, #132238); white-space: pre-wrap; word-break: break-word; }
    .asst-confirm-meta { font-size: 11.5px; color: var(--ios-text-secondary, #617087); margin-top: 6px; }
    .asst-confirm-result { font-size: 13px; margin-top: 8px; padding: 7px 9px; border-radius: 8px; white-space: pre-wrap; word-break: break-word; }
    .asst-confirm-result.ok { background: rgba(34, 160, 107, 0.1); border: 1px solid rgba(34, 160, 107, 0.3); }
    .asst-confirm-result.err { background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.3); }
    /* Bảng so sánh "trước → sau" trên thẻ xác nhận */
    .asst-diff { margin-top: 8px; border: 1px solid rgba(19, 34, 56, 0.12); border-radius: 9px; overflow: hidden; }
    .asst-diff-head { margin: 0; padding: 6px 9px; background: rgba(19, 34, 56, 0.05); font-weight: 600; }
    .asst-diff-table { width: 100%; border-collapse: collapse; font-size: 12.5px; table-layout: fixed; }
    .asst-diff-table td { padding: 5px 8px; border-top: 1px solid rgba(19, 34, 56, 0.08); vertical-align: top; word-break: break-word; }
    .asst-diff-col { width: 30%; color: var(--ios-text-secondary, #617087); }
    .asst-diff-old { color: #b0453f; text-decoration: line-through; text-decoration-color: rgba(176, 69, 63, 0.45); }
    .asst-diff-arrow { width: 22px; text-align: center; color: var(--ios-text-secondary, #617087); }
    .asst-diff-new { color: #1d7a52; font-weight: 600; }
    .asst-confirm-link { display: inline-block; margin-top: 7px; font-size: 12.5px; color: var(--ios-primary, #0a66ff); text-decoration: none; }
    .asst-confirm-link:hover { text-decoration: underline; }
    .asst-plan-body code { font-family: Consolas, Monaco, monospace; font-size: 0.85em; }
    .asst-plan-body :not(pre) > code { background: rgba(0, 122, 255, 0.12); padding: 0.08em 0.35em; border-radius: 4px; }

    @media (max-width: 860px) {
        .asst-wrap { flex-direction: column; height: auto; }
        .asst-side { flex: none; width: 100%; height: auto; }
        .asst-list { flex: none; height: auto; max-height: 170px; overflow-y: auto; }
        .asst-side-plans { max-height: none; }
        .asst-main { height: 70vh; }
    }

    /* ================= Bong bóng nổi + popup toàn màn hình ================= */
    .vc-asst-fab {
        position: fixed; right: 22px; bottom: 22px; width: 50px; height: 50px; border-radius: 50%;
        border: none; cursor: pointer; z-index: 10035;
        background: #02f7f7; font-size: 2rem; /* bỏ nền — chỉ hiển thị icon */
        display: flex; align-items: center; justify-content: center;
        filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.45));
        transition: transform 0.15s ease;
    }
    .vc-asst-fab:hover { transform: scale(1.12); border: 1px solid rgba(2, 96, 247, 0.8); }
    .vc-asst-popup {
        position: fixed; inset: 0; z-index: 10038; display: none;
        background: rgba(7, 11, 20, 0.97);
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    .vc-asst-popup.open { display: flex; flex-direction: column; }
    .vc-asst-popup-inner {
        flex: 1; min-height: 0; display: flex; flex-direction: column;
        padding: 1rem 1.25rem; overflow: hidden;
    }
    .vc-asst-popup .asst-head { flex: 0 0 auto; }
    .vc-asst-popup .asst-wrap { flex: 1; height: auto; min-height: 0; }

    @media (max-width: 860px) {
        .vc-asst-popup-inner { padding: 0.6rem; }
        .vc-asst-popup .asst-wrap { flex-direction: column; height: auto; }
        .vc-asst-popup .asst-main { height: auto; flex: 1; min-height: 0; }
        .vc-asst-popup .asst-side { flex: none; width: 100%; max-height: 30vh; }
        .vc-asst-popup .asst-list { max-height: 120px; }
    }
</style>

<!-- Bong bóng nổi toàn trang admin → nhấp vào mở popup toàn màn hình -->
<button type="button" class="vc-asst-fab" id="vc-asst-fab" title="Mở Trợ Lý Admin"><?= vc_admin_icon_svg('bot', 34, 'asst-fab-icon') ?></button>
<div class="vc-asst-popup" id="vc-asst-popup" aria-hidden="true">
<div class="vc-asst-popup-inner">

<div class="asst-head">
    <div class="asst-head-info">
        <span class="asst-head-icon" aria-hidden="true"><?= vc_admin_icon_svg('bot', 27) ?></span>
        <div>
            <h1>Trợ Lý Admin</h1>
            <p>Trợ lý AI của admin — tra cứu số liệu, khách hàng, chi phí &amp; soạn kế hoạch. Lịch sử lưu tại <code>storage/assistant/</code>.</p>
        </div>
    </div>
    <div class="asst-tools">
        <select id="asst-model" title="Chọn model AI" disabled>
            <option value="">Đang tải model...</option>
        </select>
        <button type="button" class="asst-btn" id="vc-asst-close" title="Đóng trợ lý">✕ Đóng</button>
    </div>
</div>

<div class="glass-alert" id="vc-asst-nomodel" hidden
     style="padding: 0.8rem 1rem; margin-bottom: 1rem; border-left: 4px solid var(--ios-warning, #ff9f0a); font-size: 0.85rem;">
    ▲ Không có model chat nào được API key mở khóa — hãy kiểm tra khóa ở trang Cấu Hình AI.
</div>

<div class="asst-wrap">
    <!-- Sidebar: lịch sử đoạn chat + kế hoạch -->
    <aside class="asst-panel asst-side">
        <div class="asst-side-top">
            <button type="button" class="asst-new-btn" id="asst-new">＋ Đoạn Chat Mới</button>
            <button type="button" class="asst-new-btn asst-plan-btn" id="asst-plan-new">+ Kế Hoạch</button>
        </div>
        <div class="asst-sec-label"><span>Lịch Sử Chat</span><span id="asst-count"><?= count($conversations) ?></span></div>
        <div class="asst-list" id="asst-conv-list"></div>

        <div class="asst-side-plans">
            <div class="asst-sec-label"><span>Kế Hoạch Đã Lưu</span><span id="asst-plan-count"><?= count($plans) ?></span></div>
            <div class="asst-list" id="asst-plan-list"></div>
        </div>
    </aside>

    <!-- Khung chat -->
    <section class="asst-panel asst-main">
        <div class="asst-msgs" id="asst-msgs"></div>

        <form class="asst-composer" id="asst-form" autocomplete="off">
            <div class="asst-chips" id="asst-chips"></div>
            <div class="asst-input-row">
                <button type="button" class="asst-ico-btn" id="asst-attach" title="Thêm ảnh / file (tối đa 5)">＋</button>
                <div class="asst-attach-menu" id="asst-attach-menu" hidden>
                    <button type="button" data-attach="device">▣ Chọn ảnh / file từ máy</button>
                    <button type="button" data-attach="ai">AI Chọn ảnh do AI tạo</button>
                </div>
                <textarea class="asst-input" id="asst-input" rows="1" placeholder="Nhập câu hỏi cho trợ lý... (Enter gửi, Shift+Enter xuống dòng)"></textarea>
                <button type="submit" class="asst-send" id="asst-send" title="Gửi">➤</button>
            </div>
            <div class="asst-note">Ảnh jpg/png/webp/gif ≤5MB · file văn bản ≤2MB (txt, md, csv, json, log, php, js, py...) · tối đa 5 file/lượt.</div>
            <input type="file" id="asst-file" multiple hidden
                   accept="image/jpeg,image/png,image/webp,image/gif,.txt,.md,.csv,.json,.log,.xml,.yaml,.yml,.ini,.sql,.php,.js,.ts,.py,.html,.css,.env,.conf">
        </form>
    </section>
</div>

<!-- Modal: tạo kế hoạch -->
<div class="asst-modal" id="asst-modal-new" role="dialog" aria-modal="true" aria-labelledby="asst-modal-new-title">
    <div class="asst-modal-box">
        <h3 id="asst-modal-new-title">Kế Hoạch Mới
            <button type="button" class="asst-modal-close" data-close aria-label="Đóng">&times;</button>
        </h3>
        <div class="asst-modal-scroll">
        <div class="asst-field">
            <label for="asst-plan-title">Tên kế hoạch</label>
            <input type="text" id="asst-plan-title" maxlength="160" placeholder="VD: Chiến dịch tăng trưởng tháng 12">
        </div>
        <div class="asst-field">
            <label for="asst-plan-kind">Loại kế hoạch</label>
            <select id="asst-plan-kind">
                <option value="general"> Tổng quát / vận hành</option>
                <option value="sales">Kinh doanh / bán hàng</option>
                <option value="content">Nội dung / truyền thông</option>
            </select>
        </div>
        <div class="asst-field">
            <label for="asst-plan-details">Yêu cầu bổ sung (không bắt buộc)</label>
            <textarea id="asst-plan-details" placeholder="Mục tiêu, thời gian, nguồn lực, đối tượng khách hàng..."></textarea>
        </div>
        </div><!-- /.asst-modal-scroll -->
        <div class="asst-modal-actions">
            <button type="button" class="asst-btn" data-close>Huỷ</button>
            <button type="button" class="asst-btn primary" id="asst-plan-submit">Tạo Kế Hoạch</button>
        </div>
    </div>
</div>

<!-- Modal: chọn ảnh từ thư mục AI tạo ra (public/uploads/ai/image/) -->
<div class="asst-modal" id="asst-modal-aiimg" role="dialog" aria-modal="true" aria-labelledby="asst-modal-aiimg-title">
    <div class="asst-modal-box">
        <h3 id="asst-modal-aiimg-title">Ảnh Do AI Tạo
            <button type="button" class="asst-modal-close" data-close aria-label="Đóng">&times;</button>
        </h3>
        <div class="asst-modal-scroll">
            <div class="asst-ai-grid" id="asst-ai-grid">
                <div class="asst-ai-empty">Đang tải danh sách ảnh...</div>
            </div>
        </div>
        <div class="asst-modal-actions">
            <button type="button" class="asst-btn" data-close>Đóng</button>
        </div>
    </div>
</div>

<template id="asst-welcome-tpl">
    <div class="asst-welcome">
        <div class="asst-welcome-icon"><?= vc_admin_icon_svg('bot', 40) ?></div>
        <h3>Trợ lý admin sẵn sàng</h3>
        <p>Hỏi bất cứ điều gì về số liệu bán hàng, khách hàng, gói cước, chi phí — hoặc nhờ soạn thông báo, kế hoạch. Nếu chưa có hướng dẫn sẵn, trợ lý sẽ tự tra số liệu mới nhất để trả lời.</p>
        <div class="asst-sugs">
            <?php foreach ($suggestions as $s): ?>
                <button type="button" class="asst-sug" data-q="<?= htmlspecialchars($s, ENT_QUOTES) ?>"><?= htmlspecialchars($s) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</template>

</div><!-- /.vc-asst-popup-inner -->
</div><!-- /.vc-asst-popup -->

<script>
(function () {
    'use strict';

    var CSRF     = <?= json_encode((string) ($csrf_token ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    // Dữ liệu tải LẦN ĐẦU qua bootstrap() khi mở bong bóng — partial nằm trong
    // layouts/admin.php nên không nhúng PHP data (tránh trùng lặp giữa các trang admin).
    var CONVS    = [];
    var PLANS    = [];
    var HAS_MODEL = false;
    var booted    = false;   // true sau khi bootstrap xong
    var KIND_ICONS = <?= json_encode($planKindIcons, JSON_UNESCAPED_UNICODE) ?>;
    // Avatar bong bóng chat: trợ lý AI = icon Bot, admin = icon Users (Lucide inline <svg>).
    var AVATAR_AI   = <?= json_encode(vc_admin_icon_svg('bot', 17, 'asst-avatar-icon'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var AVATAR_USER = <?= json_encode(vc_admin_icon_svg('users', 17, 'asst-avatar-icon'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    var STORE_CONV  = 'vcAsstConv';
    var STORE_MODEL = 'vcAsstModel';
    var MAX_FILES   = 5;
    var IMG_EXT  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    var TXT_EXT  = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yaml', 'yml', 'ini', 'sql', 'php', 'js', 'ts', 'py', 'html', 'css', 'env', 'conf'];

    var $ = function (id) { return document.getElementById(id); };
    var msgsEl = $('asst-msgs'), convListEl = $('asst-conv-list'), planListEl = $('asst-plan-list');
    var inputEl = $('asst-input'), sendBtn = $('asst-send'), chipsEl = $('asst-chips');
    var fileEl = $('asst-file'), modelSel = $('asst-model');
    var modalNew = $('asst-modal-new');
    var modalAi = $('asst-modal-aiimg'), aiGridEl = $('asst-ai-grid');
    var attachMenuEl = $('asst-attach-menu');

    var current  = '';
    var pending  = [];   // File chờ gửi
    var busy     = false;
    var lastError = '';  // Lỗi lần gửi trước (cho nút "Gửi lại")
    var ctrl     = new AbortController();
    var planCtrl = null;   // AbortController riêng cho lần tạo kế hoạch (bấm Huỷ → dừng ngay)
    var planReqId = null;  // Mã yêu cầu lần tạo hiện tại (server hủy theo req_id qua beacon)

    try { current = localStorage.getItem(STORE_CONV) || ''; } catch (e) { current = ''; }
    // KHÔNG validate current tại đây — CONVS rỗng cho tới khi bootstrap(); validate sau khi tải.
    try {
        var savedModel = localStorage.getItem(STORE_MODEL);
        if (savedModel && modelSel && modelSel.querySelector('option[value="' + savedModel + '"]')) {
            modelSel.value = savedModel;
        }
    } catch (e) { /* noop */ }

    /* ================= HTTP ================= */
    function post(url, fd, signal) {
        if (!(fd instanceof FormData)) {
            var f = new FormData();
            Object.keys(fd || {}).forEach(function (k) { f.append(k, fd[k]); });
            fd = f;
        }
        if (!fd.has('csrf_token')) fd.append('csrf_token', CSRF);
        return fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd, signal: signal || ctrl.signal })
            .then(function (r) { return r.json(); });
    }
    function get(url) {
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal })
            .then(function (r) { return r.json(); });
    }

    /* ================= Toast (viền theo trạng thái, góc trên phải) ================= */
    function toast(type, msg) {
        var el = document.createElement('div');
        el.className = 'glass-alert vc-toast vc-toast--' + type;
        el.setAttribute('role', 'alert');
        var icons = { success: '&#10003;', danger: '&#10005;', warning: '&#9888;', info: '&#8505;' };
        el.innerHTML = '<span class="vc-toast__icon" aria-hidden="true">' + (icons[type] || icons.info) + '</span>'
            + '<span class="vc-toast__msg"></span>'
            + '<button type="button" class="alert-close vc-toast__close" title="Đóng" aria-label="Đóng">&times;</button>';
        el.querySelector('.vc-toast__msg').textContent = msg;
        document.body.appendChild(el);
        var kill = function () { if (el.parentNode) el.remove(); };
        var timer = setTimeout(kill, 4000);
        el.querySelector('.alert-close').addEventListener('click', function () { clearTimeout(timer); kill(); });
        el.vcKill = function () { clearTimeout(timer); kill(); };
    }

    /* ================= Markdown nhẹ (escape trước, an toàn XSS) ================= */
    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function inline(s) {
        var codes = [];
        s = s.replace(/`([^`\n]+)`/g, function (m, c) { codes.push(c); return '\u0000C' + (codes.length - 1) + '\u0000'; });
        s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<em>$2</em>');
        s = s.replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/g, function (m, t, u) {
            // Link kế hoạch → mở popup tại chỗ (không tab mới); link khác giữ tab mới.
            var blank = u.indexOf('assistant/plan/view') === -1 ? ' target="_blank" rel="noopener"' : '';
            return '<a href="' + esc(u) + '"' + blank + '>' + t + '</a>';
        });
        s = s.replace(/\u0000C(\d+)\u0000/g, function (m, i) { return '<code>' + codes[+i] + '</code>'; });
        return s;
    }
    function mdBlock(src) {
        var lines = String(src).split('\n');
        var out = '', list = null, para = [];
        function closeList() { if (list) { out += '</' + list + '>'; list = null; } }
        function flushPara() {
            if (para.length) { out += '<p>' + inline(para.join('<br>')) + '</p>'; para = []; }
        }
        lines.forEach(function (ln) {
            var h = ln.match(/^(#{1,6})\s+(.*)$/);
            var ul = ln.match(/^\s*[-*•]\s+(.*)$/);
            var ol = ln.match(/^\s*\d+\.\s+(.*)$/);
            if (h) { flushPara(); closeList(); out += '<h4>' + inline(esc(h[2])) + '</h4>'; return; }
            if (ul) {
                flushPara();
                if (list !== 'ul') { closeList(); out += '<ul>'; list = 'ul'; }
                out += '<li>' + inline(esc(ul[1])) + '</li>'; return;
            }
            if (ol) {
                flushPara();
                if (list !== 'ol') { closeList(); out += '<ol>'; list = 'ol'; }
                out += '<li>' + inline(esc(ol[1])) + '</li>'; return;
            }
            if (ln.trim() === '') { flushPara(); closeList(); return; }
            closeList(); para.push(esc(ln));
        });
        flushPara(); closeList();
        return out;
    }
    function md(src) {
        var parts = String(src).split('```'), html = '';
        for (var i = 0; i < parts.length; i++) {
            if (i % 2 === 1) {
                var t = parts[i], nl = t.indexOf('\n');
                // Khối bài xem trước (post_draft) → render card THAY block code thường.
                var fl = nl > -1 ? t.slice(0, nl).trim() : t.trim();
                if (fl === 'post-preview') {
                    var po = null;
                    try { po = JSON.parse(t.slice(nl + 1).trim()); } catch (e) { po = null; }
                    if (po && po.preview_id) {
                        // Backtick trong JSON đã thay U+2027 lúc gắn marker (chống phá
                        // fence) — khôi phục tại đây để hiển thị/lưu đúng gốc.
                        po.content = String(po.content || '').split('‧').join('`');
                        po.title = String(po.title || '').split('‧').join('`');
                        POSTS[po.preview_id] = po;
                        html += postPreviewInner(po);
                        continue;
                    }
                }
                if (fl === 'action-confirm') {
                    var co = null;
                    try { co = JSON.parse(t.slice(nl + 1).trim()); } catch (e) { co = null; }
                    if (co && co.token) {
                        CONFIRMS[co.token] = co;
                        html += confirmInner(co);
                        continue;
                    }
                }
                if (nl > -1 && /^[a-zA-Z0-9+#._-]*\s*$/.test(t.slice(0, nl))) t = t.slice(nl + 1);
                html += '<pre><code>' + esc(t.replace(/\n$/, '')) + '</code></pre>';
            } else if (parts[i] !== '') {
                html += mdBlock(parts[i]);
            }
        }
        return html;
    }

    /* Nội dung tin nhắn user: marker đính kèm → ảnh/chip, phần còn lại → md */
    function renderUserContent(text) {
        var extra = '', rest = String(text);
        rest = rest.replace(/\[HÌNH ẢNH ĐÍNH KÈM: ([^\]]+)\]\(([^)]+)\)/g, function (m, name, path) {
            var slash = path.indexOf('/');
            var conv = slash > 0 ? path.slice(0, slash) : '';
            var file = slash > 0 ? path.slice(slash + 1) : path;
            extra += '<img class="asst-img" src="/admin/assistant/attachment?conv=' + encodeURIComponent(conv)
                + '&file=' + encodeURIComponent(file) + '" alt="' + esc(name) + '" loading="lazy">';
            return '';
        });
        rest = rest.replace(/\[FILE "([^"]+)"\]/g, function (m, name) {
            extra += '<span class="asst-file-chip">▣ ' + esc(name) + '</span>';
            return '';
        });
        var body = rest.trim() ? '<div>' + md(rest) + '</div>' : '';
        return extra + body;
    }

    /* ============ Bài viết xem trước (post_draft): card trong chat ============
     * AI soạn bài → post_draft trả payload preview (CHƯA ghi DB) → hệ thống
     * nhúng ```post-preview\n{json}\n``` vào reply → md() render card TRƯỚC khi
     * lưu; admin bấm "Lưu Bài" → POST post/save mới ghi bài nháp.            */
    var POSTS = {};

    /* ============ Hành động chờ xác nhận (action-confirm) ==================
     * ToolRegistry::needsConfirm() chặn ghi DB → stageConfirm() phát token một
     * lần; reply nhúng ```action-confirm\n{json}\n``` → card có 2 nút.
     * Chỉ khi admin bấm "Xác Nhận Thực Hiện" server mới chạy tool (đúng 1 lần). */
    var CONFIRMS = {};
    var TOOL_LABELS = {
        campaign_create: 'Tạo chiến dịch Remarketing Facebook',
        campaign_status: 'Đổi trạng thái chiến dịch',
        page_edit: 'Sửa dữ liệu trên trang admin đang mở',
        entity_create: 'Thêm bản ghi mới',
        entity_update: 'Sửa bản ghi',
        entity_delete: 'Xóa bản ghi',
        action_run: 'Chạy nghiệp vụ hệ thống'
    };
    var MODE_LABELS = { create: 'Sẽ thêm', update: 'Sẽ đổi', delete: 'Sẽ xóa', action: 'Sẽ thực hiện' };

    /* Bảng "sẽ thay đổi gì" — server gửi lên từ args + ảnh chụp dữ liệu thật,
     * đã che cột mật khẩu/token. Admin nhìn rõ trước khi bấm xác nhận. */
    function previewInner(p) {
        if (!p || !p.rows || !p.rows.length) { return ''; }
        var rows = p.rows.slice(0, 24).map(function (r) {
            var b = (r.before === null || r.before === undefined || r.before === '') ? '—' : String(r.before);
            var a = (r.after === null || r.after === undefined || r.after === '') ? '—' : String(r.after);
            return '<tr><td class="asst-diff-col">' + esc(r.column || '') + '</td>'
                + '<td class="asst-diff-old">' + esc(b) + '</td>'
                + '<td class="asst-diff-arrow">→</td>'
                + '<td class="asst-diff-new">' + esc(a) + '</td></tr>';
        }).join('');
        var more = p.rows.length > 24 ? '<div class="asst-confirm-meta">… ' + (p.rows.length - 24) + ' thay đổi khác không hiển thị.</div>' : '';
        var head = (MODE_LABELS[p.mode] || 'Thay đổi') + (p.label ? ': ' + p.label : '') + (p.id ? ' #' + p.id : '');
        return '<div class="asst-diff">'
            + '<div class="asst-confirm-meta asst-diff-head">' + esc(head) + '</div>'
            + '<table class="asst-diff-table"><tbody>' + rows + '</tbody></table>' + more
            + '</div>';
    }

    function confirmInner(c) {
        var state = c.state || 'pending';
        var label = TOOL_LABELS[c.tool] ? TOOL_LABELS[c.tool] : ('Công cụ ' + (c.tool || '?'));
        var chip = state === 'done' ? '<span class="asst-confirm-chip done">Đã thực hiện</span>'
            : state === 'cancelled' ? '<span class="asst-confirm-chip off">Đã bỏ qua</span>'
            : state === 'failed' ? '<span class="asst-confirm-chip off">Không thành công</span>'
            : state === 'expired' ? '<span class="asst-confirm-chip off">Hết hiệu lực</span>'
            : '<span class="asst-confirm-chip">Chờ bạn xác nhận</span>';
        var link = (c.preview && c.preview.url)
            ? '<a class="asst-confirm-link" href="' + escAttr(c.preview.url) + '" target="_blank" rel="noopener">Mở bản ghi ↗</a>'
            : '';
        var foot = state === 'pending'
            ? '<button type="button" class="asst-btn primary" data-confirm-action="' + escAttr(c.token) + '">Xác Nhận Thực Hiện</button>'
                + '<button type="button" class="asst-btn danger" data-cancel-action="' + escAttr(c.token) + '">Bỏ Qua</button>'
                + '<span class="asst-post-hint">Hệ thống CHƯA thay đổi dữ liệu — bấm "Xác Nhận" để thi hành.</span>'
            : '<span class="asst-post-hint">' + (state === 'cancelled' ? 'Hành động đã bị bỏ qua, dữ liệu không thay đổi.' : '') + '</span>';
        return '<div class="asst-plan-card asst-post-card asst-confirm-card" data-confirm-card="' + escAttr(c.token) + '">'
            + '<div class="asst-post-head"><span style="font-size:16px">⚠</span>'
            + '<span class="asst-post-title">' + esc(label) + '</span>' + chip + '</div>'
            + '<div class="asst-confirm-body">' + esc(c.summary || '(không có mô tả)') + '</div>'
            + previewInner(c.preview)
            + (c.expires_at ? '<div class="asst-confirm-meta">Hiệu lực đến ' + esc(c.expires_at) + ' (30 phút, chạy đúng một lần).</div>' : '')
            + link
            + (c.message ? '<div class="asst-confirm-result ' + (state === 'done' ? 'ok' : 'err') + '">' + esc(c.message) + '</div>' : '')
            + '<div class="asst-post-foot">' + foot + '</div>'
            + '</div>';
    }

    function markConfirm(token, patch) {
        if (!CONFIRMS[token]) { CONFIRMS[token] = { token: token }; }
        Object.keys(patch).forEach(function (k) { CONFIRMS[token][k] = patch[k]; });
        var card = document.querySelector('[data-confirm-card="' + token + '"]');
        if (card) { card.innerHTML = confirmInner(CONFIRMS[token]); }
    }

    function runConfirm(btn) {
        var token = btn.getAttribute('data-confirm-action');
        var c = CONFIRMS[token];
        if (!c || c.state && c.state !== 'pending') { return; }
        btn.disabled = true;
        btn.textContent = 'Đang thực hiện...';
        var fd = new FormData();
        fd.append('conv_id', current);
        fd.append('token', token);
        var pg = pageSnapshot();
        fd.append('page_url', pg.url);
        fd.append('page_title', pg.title);
        fd.append('page_text', pg.text);
        post('/admin/assistant/action/confirm', fd).then(function (res) {
            if (!res || !res.ok) {
                btn.disabled = false;
                btn.textContent = 'Xác Nhận Thực Hiện';
                markConfirm(token, { state: 'failed', message: (res && res.error) || 'Không thực hiện được.' });
                toast('danger', (res && res.error) || 'Không thực hiện được hành động.');
                return;
            }
            markConfirm(token, { state: 'done', message: res.message || 'Đã thực hiện.' });
            toast('success', res.message || 'Đã thực hiện hành động.');
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Xác Nhận Thực Hiện';
            toast('danger', 'Mất kết nối — hành động CHƯA được thực hiện.');
        });
    }

    function dropConfirm(btn) {
        var token = btn.getAttribute('data-cancel-action');
        var c = CONFIRMS[token];
        if (!c || (c.state && c.state !== 'pending')) { return; }
        btn.disabled = true;
        post('/admin/assistant/action/cancel', { conv_id: current, token: token }).then(function (res) {
            if (!res || !res.ok) {
                btn.disabled = false;
                toast('danger', (res && res.error) || 'Không bỏ qua được.');
                return;
            }
            markConfirm(token, { state: 'cancelled' });
            toast('success', 'Đã bỏ qua — dữ liệu không thay đổi.');
        }).catch(function () { btn.disabled = false; });
    }

    /* Sau khi mở lại đoạn chat: ẩn các thẻ action-confirm đã chạy/bỏ qua, và
     * thẻ quá hạn (server không còn token) → đánh dấu hết hiệu lực. */
    function syncConfirms() {
        var tokens = [];
        Object.keys(CONFIRMS).forEach(function (k) {
            if (!CONFIRMS[k].state || CONFIRMS[k].state === 'pending') { tokens.push(k); }
        });
        if (!tokens.length) { return; }
        get('/admin/assistant/action/pending?conv_id=' + encodeURIComponent(current)).then(function (res) {
            var alive = {};
            ((res && res.items) || []).forEach(function (it) { alive[it.token] = it; });
            tokens.forEach(function (k) {
                if (!alive[k]) { markConfirm(k, { state: 'expired', message: 'Hành động đã hết hiệu lực (quá 30 phút hoặc đã chạy).' }); }
                else if (alive[k].preview) { markConfirm(k, { preview: alive[k].preview }); }
            });
        }).catch(function () { /* noop */ });
    }

    function postPreviewInner(p) {
        var typeLabels = { news: 'Tin tức', tutorial: 'Hướng dẫn', faq: 'FAQ', popup: 'Popup' };
        var saved = p.saved_post_id;
        var foot = saved
            ? '<a class="asst-btn" href="/admin/posts/edit?id=' + saved + '">Mở bài nháp</a>'
                + '<span class="asst-post-hint">Đã lưu — hiển thị trong Danh Sách Bài Viết.</span>'
            : '<button type="button" class="asst-btn primary" data-post-save="' + escAttr(p.preview_id) + '">Lưu Bài</button>'
                + '<span class="asst-post-hint">Bài CHƯA lưu — bấm "Lưu Bài" để vào Danh Sách Bài Viết.</span>';
        return '<div class="asst-plan-card asst-post-card" data-post-card="' + escAttr(p.preview_id) + '">'
            + '<div class="asst-post-head"><span style="font-size:16px">▣</span>'
            + '<span class="asst-post-title">' + esc(p.title || '(không có tiêu đề)') + '</span>'
            + '<span class="asst-post-chip">' + esc(typeLabels[p.type] || p.type || 'news') + '</span>'
            + (saved
                ? '<span class="asst-post-chip saved">Đã lưu</span>'
                : '<span class="asst-post-chip pending">Chưa lưu</span>')
            + '</div>'
            + '<div class="asst-post-body">' + md(p.content || '') + '</div>'
            + '<div class="asst-post-foot">' + foot + '</div>'
            + '</div>';
    }

    function savePostCard(btn) {
        var id = btn.getAttribute('data-post-save');
        var p = POSTS[id];
        if (!p || p.saved_post_id) { return; }
        btn.disabled = true;
        btn.textContent = 'Đang lưu...';
        post('/admin/assistant/post/save', {
            preview_id: id,
            title: p.title,
            content: p.content,
            type: p.type || 'news',
            slug: p.slug || ''
        }).then(function (res) {
            if (!res.ok) {
                btn.disabled = false;
                btn.textContent = 'Lưu Bài';
                toast('danger', res.error || 'Không lưu được bài.');
                return;
            }
            p.saved_post_id = res.post_id;
            var card = document.querySelector('[data-post-card="' + id + '"]');
            if (card) { card.innerHTML = postPreviewInner(p); }
            toast('success', res.already ? 'Bài đã lưu trước đó (không tạo trùng).' : 'Đã lưu NHÁP — vào Danh Sách Bài Viết.');
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Lưu Bài';
            toast('danger', 'Mất kết nối — chưa lưu được bài.');
        });
    }

    /* Sau khi tải lịch sử: đồng bộ trạng thái đã lưu của các card bài viết
     * (tránh bấm "Lưu Bài" lần 2 sau khi mở lại đoạn chat). */
    function syncSavedPosts() {
        var ids = [];
        Object.keys(POSTS).forEach(function (k) {
            if (!POSTS[k].saved_post_id) { ids.push(k); }
        });
        if (!ids.length) { return; }
        get('/admin/assistant/post/status?ids=' + ids.join(',')).then(function (res) {
            var saved = (res && res.ok && res.saved) ? res.saved : {};
            Object.keys(saved).forEach(function (id) {
                if (!POSTS[id]) { return; }
                POSTS[id].saved_post_id = saved[id];
                var card = document.querySelector('[data-post-card="' + id + '"]');
                if (card) { card.innerHTML = postPreviewInner(POSTS[id]); }
            });
        }).catch(function () { /* noop */ });
    }

    /* ================= Sidebar ================= */
    function escAttr(s) { return esc(s); }
    function findConv(id) {
        for (var i = 0; i < CONVS.length; i++) if (CONVS[i].id === id) return CONVS[i];
        return null;
    }
    function shortTime(s) {
        s = String(s || '');
        return s.length >= 16 ? s.slice(5, 16) : s;
    }
    function renderConvs() {
        $('asst-count').textContent = CONVS.length;
        if (!CONVS.length) {
            convListEl.innerHTML = '<div class="asst-empty-note">Chưa có đoạn chat nào.</div>';
            return;
        }
        convListEl.innerHTML = CONVS.map(function (c) {
            return '<div class="asst-item' + (c.id === current ? ' active' : '') + '" data-id="' + escAttr(c.id) + '" title="' + escAttr(c.title || '') + '">'
                + '<div class="asst-item-main">'
                + '<div class="asst-item-title">' + esc(c.title || 'Đoạn chat mới') + '</div>'
                + '<div class="asst-item-meta">' + (c.msg_count || 0) + ' tin · ' + shortTime(c.updated_at) + '</div>'
                + '</div>'
                + '<button type="button" class="asst-item-del" data-del="' + escAttr(c.id) + '" title="Xoá đoạn chat">✕</button>'
                + '</div>';
        }).join('');
    }
    function renderPlans() {
        $('asst-plan-count').textContent = PLANS.length;
        if (!PLANS.length) {
            planListEl.innerHTML = '<div class="asst-empty-note">Chưa có kế hoạch nào. Bấm “Thêm Kế Hoạch”.</div>';
            return;
        }
        planListEl.innerHTML = PLANS.map(function (p) {
            var icon = KIND_ICONS[p.kind] || KIND_ICONS.other;
            return '<div class="asst-item" data-plan="' + escAttr(p.id) + '" title="' + escAttr(p.title || '') + '">'
                + '<div class="asst-item-main">'
                + '<div class="asst-item-title">' + icon + ' ' + esc(p.title || 'Kế hoạch') + '</div>'
                + '<div class="asst-item-meta">' + shortTime(p.created_at) + '</div>'
                + '</div>'
                + '<button type="button" class="asst-item-del" data-delplan="' + escAttr(p.id) + '" title="Xoá kế hoạch">✕</button>'
                + '</div>';
        }).join('');
    }

    /* ================= Khung chat ================= */
    function welcomeHtml() {
        var tpl = $('asst-welcome-tpl');
        return tpl && tpl.content ? tpl.content.cloneNode(true) : document.createTextNode('');
    }
    function showWelcome() {
        msgsEl.innerHTML = '';
        msgsEl.appendChild(welcomeHtml());
        scrollBottom();
    }
    function scrollBottom() { msgsEl.scrollTop = msgsEl.scrollHeight; }

    function bubble(role, html, ts, failed) {
        var row = document.createElement('div');
        row.className = 'asst-row ' + role;
        row.innerHTML = '<div class="asst-avatar">' + (role === 'user' ? AVATAR_USER : AVATAR_AI) + '</div>'
            + '<div class="asst-bubble' + (failed ? ' failed' : '') + '">' + html
            + (ts ? '<div class="asst-ts">' + esc(ts) + '</div>' : '')
            + '</div>';
        msgsEl.appendChild(row);
        return row;
    }

    function openConv(id) {
        var meta = findConv(id);
        if (!meta) {
            current = '';
            try { localStorage.setItem(STORE_CONV, ''); } catch (e) { /* noop */ }
            renderConvs();
            showWelcome();
            return;
        }
        current = id;
        try { localStorage.setItem(STORE_CONV, id); } catch (e) { /* noop */ }
        renderConvs();
        get('/admin/assistant/history?id=' + encodeURIComponent(id)).then(function (res) {
            if (!res.ok) { toast('danger', res.error || 'Không mở được lịch sử chat.'); return; }
            if (current !== id) return; // đã chuyển sang đoạn khác
            msgsEl.innerHTML = '';
            var list = res.messages || [];
            if (!list.length) { showWelcome(); return; }
            list.forEach(function (m) {
                var html = m.role === 'user' ? renderUserContent(m.content) : md(m.content);
                bubble(m.role === 'user' ? 'user' : 'assistant', html, shortTime(m.ts));
            });
            if (modelSel && res.meta && res.meta.model
                && modelSel.querySelector('option[value="' + res.meta.model + '"]')) {
                modelSel.value = res.meta.model;
            }
            syncSavedPosts();
            syncConfirms();
            scrollBottom();
        }).catch(function () { toast('danger', 'Mất kết nối khi tải lịch sử chat.'); });
    }

    function newConv() {
        if (!booted) { toast('warning', 'Dữ liệu chưa tải xong — thử lại giây lát.'); bootstrap(); return Promise.resolve(''); }
        if (!HAS_MODEL) { toast('warning', 'Không có model chat khả dụng.'); return Promise.resolve(''); }
        return post('/admin/assistant/new', { model: modelSel.value }).then(function (res) {
            if (!res.ok) { toast('danger', res.error || 'Không tạo được đoạn chat.'); return ''; }
            CONVS.unshift({ id: res.id, title: res.title, model: res.model, msg_count: 0, updated_at: '' });
            current = res.id;
            try { localStorage.setItem(STORE_CONV, res.id); } catch (e) { /* noop */ }
            renderConvs();
            msgsEl.innerHTML = '';
            scrollBottom();
            inputEl.focus();
            return res.id;
        }).catch(function () { toast('danger', 'Mất kết nối — không tạo được đoạn chat.'); return ''; });
    }

    function delConv(id) {
        var meta = findConv(id);
        if (!confirm('Xoá đoạn chat "' + (meta && meta.title ? meta.title : id) + '"? Hành động này không hoàn tác.')) return;
        post('/admin/assistant/delete', { id: id }).then(function (res) {
            if (!res.ok) { toast('danger', res.error || 'Không xoá được đoạn chat.'); return; }
            toast('success', 'Đã xoá đoạn chat.');
            CONVS = CONVS.filter(function (c) { return c.id !== id; });
            if (current === id) {
                current = CONVS.length ? CONVS[0].id : '';
                try { localStorage.setItem(STORE_CONV, current); } catch (e) { /* noop */ }
                if (current) openConv(current); else showWelcome();
            }
            renderConvs();
        }).catch(function () { toast('danger', 'Mất kết nối — không xoá được.'); });
    }

    /* ================= Đính kèm ================= */
    function extOf(name) {
        var i = String(name).lastIndexOf('.');
        return i > -1 ? String(name).slice(i + 1).toLowerCase() : '';
    }
    function humanSize(n) {
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
    }
    function addFiles(fileList) {
        Array.prototype.forEach.call(fileList, function (f) {
            if (pending.length >= MAX_FILES) { toast('warning', 'Tối đa ' + MAX_FILES + ' file mỗi lượt gửi.'); return; }
            var ext = extOf(f.name);
            var isImg = IMG_EXT.indexOf(ext) > -1;
            var isTxt = TXT_EXT.indexOf(ext) > -1;
            if (!isImg && !isTxt) {
                toast('danger', 'File .' + ext + ' không được hỗ trợ (chỉ ảnh jpg/png/webp/gif hoặc file văn bản).');
                return;
            }
            if (isImg && f.size > 5242880) { toast('danger', 'Ảnh "' + f.name + '" vượt 5MB.'); return; }
            if (isTxt && f.size > 2097152) { toast('danger', 'File "' + f.name + '" vượt 2MB.'); return; }
            pending.push(f);
        });
        renderChips();
    }
    function renderChips() {
        chipsEl.innerHTML = pending.map(function (f, i) {
            var isImg = IMG_EXT.indexOf(extOf(f.name)) > -1;
            var thumb = isImg ? '<img src="' + URL.createObjectURL(f) + '" alt="">' : '▣';
            return '<span class="asst-chip">' + thumb + '<span>' + esc(f.name) + ' (' + humanSize(f.size) + ')</span>'
                + '<button type="button" data-rm="' + i + '" title="Bỏ file">✕</button></span>';
        }).join('');
    }

    /* Chọn ảnh từ thư mục AI tạo ra (public/uploads/ai/image/) → fetch về
       thành File rồi đẩy vào pending như chọn file từ máy (giữ chung limit 5MB/5 file). */
    function openAiPicker() {
        openModal(modalAi);
        aiGridEl.innerHTML = '<div class="asst-ai-empty">Đang tải danh sách ảnh...</div>';
        get('/admin/assistant/ai-images').then(function (res) {
            if (!res || !res.ok) {
                aiGridEl.innerHTML = '<div class="asst-ai-empty">Không tải được danh sách ảnh.</div>';
                return;
            }
            var imgs = res.images || [];
            if (!imgs.length) {
                aiGridEl.innerHTML = '<div class="asst-ai-empty">Thư mục AI chưa có ảnh nào.</div>';
                return;
            }
            aiGridEl.innerHTML = imgs.map(function (im) {
                return '<button type="button" class="asst-ai-card" data-url="' + esc(im.url) + '" data-name="' + esc(im.name) + '" title="Bấm để đính kèm ảnh này">'
                    + '<img src="' + esc(im.url) + '" loading="lazy" alt="">'
                    + '<span>' + esc(im.name) + '</span></button>';
            }).join('');
        }).catch(function () {
            aiGridEl.innerHTML = '<div class="asst-ai-empty">Mất kết nối — không tải được ảnh.</div>';
        });
    }
    aiGridEl.addEventListener('click', function (e) {
        var card = e.target.closest ? e.target.closest('.asst-ai-card') : null;
        if (!card) return;
        var url = card.getAttribute('data-url') || '';
        var name = card.getAttribute('data-name') || 'ai-image.png';
        if (!url) return;
        fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.blob();
        }).then(function (b) {
            if (b.size > 5242880) { toast('danger', 'Ảnh "' + name + '" vượt 5MB.'); return; }
            var f;
            try {
                f = new File([b], name, { type: b.type || 'image/png' });
            } catch (err) {
                f = new Blob([b], { type: b.type || 'image/png' });
                f.name = name;
            }
            addFiles([f]);
            closeModal(modalAi);
        }).catch(function () {
            toast('danger', 'Không tải được ảnh: ' + name);
        });
    });

    /* ================= Gửi tin ================= */
    function setBusy(b) {
        busy = b;
        sendBtn.disabled = b;
        inputEl.disabled = b;
        $('asst-attach').disabled = b;
        if (b && attachMenuEl) attachMenuEl.hidden = true;
    }
    function showTyping() {
        var row = document.createElement('div');
        row.className = 'asst-row assistant asst-typing';
        row.id = 'asst-typing';
        row.innerHTML = '<div class="asst-avatar">' + AVATAR_AI + '</div><div class="asst-bubble">'
            + '<span class="asst-dot">●</span> <span class="asst-dot">●</span> <span class="asst-dot">●</span></div>';
        msgsEl.appendChild(row);
        scrollBottom();
    }
    function hideTyping() { var t = $('asst-typing'); if (t) t.remove(); }

    function optimisticHtml(text, files) {
        var html = '';
        files.forEach(function (f) {
            if (IMG_EXT.indexOf(extOf(f.name)) > -1) {
                html += '<img class="asst-img" src="' + URL.createObjectURL(f) + '" alt="' + esc(f.name) + '">';
            } else {
                html += '<span class="asst-file-chip">▣ ' + esc(f.name) + '</span>';
            }
        });
        if (text.trim()) html += '<div>' + md(text) + '</div>';
        return html;
    }
    function optimisticUser(text, files) {
        return bubble('user', optimisticHtml(text, files), '');
    }

    /* Gắn nhãn lỗi + nút GỬI LẠI (giữ nguyên text + file để gửi lại y nguyên). */
    function addFailed(row, text, files) {
        row.querySelector('.asst-bubble').classList.add('failed');
        var err = document.createElement('div');
        err.className = 'asst-ts';
        err.textContent = '▲ ' + (lastError || 'Gửi thất bại.');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'asst-retry';
        btn.textContent = '↻ Gửi lại';
        btn.addEventListener('click', function () {
            row.remove();
            startSend(text, files);
        });
        err.appendChild(document.createTextNode(' '));
        err.appendChild(btn);
        row.querySelector('.asst-bubble').appendChild(err);
    }

    function doSend() {
        if (busy) return;
        if (!booted) { toast('warning', 'Dữ liệu chưa tải xong — thử lại giây lát.'); bootstrap(); return; }
        var text = inputEl.value;
        var files = pending.slice();
        if (!text.trim() && !files.length) return;
        if (!HAS_MODEL) { toast('warning', 'Không có model chat khả dụng.'); return; }

        inputEl.value = '';
        inputEl.style.height = 'auto';
        pending = [];
        renderChips();
        startSend(text, files);
    }

    /* Bối cảnh trang admin bong bóng đang mở — CHỈ đọc .admin-content innerText
       (KHÔNG chạm value ô nhập: API key/mật khẩu trong <input value> không lọt vào đây)
       + redact client-side vài dạng token phổ biến (lớp 2; server sanitize thêm). */
    function pageSnapshot() {
        try {
            var root = document.querySelector('.admin-content');
            var text = root ? String(root.innerText || '') : '';
            text = text
                .replace(/sk-[A-Za-z0-9_\-]{8,}/g, '[BẢO MẬT ẨN]')
                .replace(/ghp_[A-Za-z0-9]{10,}/g, '[BẢO MẬT ẨN]')
                .replace(/EAAG[A-Za-z0-9]+/g, '[BẢO MẬT ẨN]')
                .replace(/Bearer\s+[A-Za-z0-9._\-]+/gi, 'Bearer [BẢO MẬT ẨN]')
                .replace(/\b(api[_-]?key|secret|token|password)\b\s*[:=]\s*["']?[^\s"',;]{4,}/gi, '$1=[BẢO MẬT ẨN]');
            return {
                url: location.pathname + location.search,
                title: document.title || '',
                text: text.slice(0, 6000)
            };
        } catch (e) { return { url: '', title: '', text: '' }; }
    }

    function startSend(text, files) {
        if (busy) return;
        setBusy(true);
        var ensure = current ? Promise.resolve(current) : newConv();

        ensure.then(function (id) {
            if (!id) { setBusy(false); return ''; }
            var row = optimisticUser(text, files);
            showTyping();

            var fd = new FormData();
            fd.append('conv_id', id);
            fd.append('model', modelSel.value);
            fd.append('message', text);
            var pg = pageSnapshot();
            if (pg.url) {
                fd.append('page_url', pg.url);
                fd.append('page_title', pg.title);
                fd.append('page_text', pg.text);
            }
            files.forEach(function (f) { fd.append('files[]', f, f.name); });

            return post('/admin/assistant/send', fd).then(function (res) {
                hideTyping();
                if (!res.ok) {
                    lastError = res.error || 'Gửi thất bại.';
                    addFailed(row, text, files);
                    toast('danger', res.error || 'Gửi tin thất bại.');
                    return;
                }
                bubble('assistant', md(res.reply || ''), '');
                scrollBottom();
                // AI vừa tạo/cập nhật kế hoạch (đã KHÔNG còn chèn link) → luôn nạp lại
                // sidebar Kế Hoạch NGAY sau mỗi lượt trả lời — không cần F5.
                refreshPlans();
                if (res.meta) {
                    // đưa đoạn đang chat lên đầu danh sách (meta server đã có title/msg_count mới)
                    CONVS = [res.meta].concat(CONVS.filter(function (c) { return c.id !== id; }));
                    renderConvs();
                }
            }).catch(function (e) {
                hideTyping();
                if (e && e.name === 'AbortError') return;
                lastError = 'Mất kết nối — tin nhắn chưa được trả lời.';
                addFailed(row, text, files);
                toast('danger', lastError);
            }).then(function () { setBusy(false); inputEl.focus(); });
        }).catch(function () { setBusy(false); });
    }

    /* ================= Kế hoạch ================= */
    /* Tải lại danh sách Kế Hoạch từ server (gọi sau khi AI tạo/cập nhật kế hoạch). */
    function refreshPlans() {
        get('/admin/assistant/plans').then(function (res) {
            if (res && res.ok && Array.isArray(res.plans)) {
                PLANS = res.plans;
                renderPlans();
            }
        }).catch(function () { /* mất kết nối — sidebar giữ danh sách cũ */ });
    }
    function openModal(m) {
        m.classList.add('open');
        var sc = m.querySelector('.asst-modal-scroll');
        if (sc) sc.scrollTop = 0;
    }
    function closeModal(m) { m.classList.remove('open'); }
    function anyOpen() { return modalNew.classList.contains('open') || modalAi.classList.contains('open'); }

    /* Báo server hủy yêu cầu tạo kế hoạch (gửi kèm khi abort — không tin được
       connection_status trên Windows/Apache, nên client phải "tự thú" bằng beacon). */
    function sendPlanCancel(reqId) {
        if (!reqId) return;
        try {
            var fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('req_id', reqId);
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/admin/assistant/plan/cancel', fd);
            } else {
                fetch('/admin/assistant/plan/cancel', { method: 'POST', body: fd, keepalive: true }).catch(function () { /* noop */ });
            }
        } catch (e) { /* noop */ }
    }

    function createPlan() {
        if (!booted) { toast('warning', 'Dữ liệu chưa tải xong — thử lại giây lát.'); bootstrap(); return; }
        var title = $('asst-plan-title').value.trim();
        if (!title) { toast('warning', 'Hãy đặt tên cho kế hoạch.'); $('asst-plan-title').focus(); return; }
        if (!HAS_MODEL) { toast('warning', 'Không có model chat khả dụng.'); return; }
        var btn = $('asst-plan-submit');
        btn.disabled = true;
        btn.textContent = '◷ Đang tạo...';
        planCtrl = new AbortController();
        var reqId = planReqId = 'r' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        post('/admin/assistant/plan/new', {
            title: title,
            kind: $('asst-plan-kind').value,
            details: $('asst-plan-details').value.trim(),
            model: modelSel.value,
            conv_id: current,
            req_id: reqId
        }, planCtrl.signal).then(function (res) {
            planCtrl = null;
            planReqId = null;
            btn.disabled = false;
            btn.textContent = 'Tạo Kế Hoạch';
            if (!res.ok) { toast('danger', res.error || 'Không tạo được kế hoạch.'); return; }
            closeModal(modalNew);
            $('asst-plan-title').value = '';
            $('asst-plan-details').value = '';
            PLANS = [res.plan].concat(PLANS.filter(function (p) { return p.id !== res.plan.id; }));
            renderPlans();
            toast('success', 'Đã tạo kế hoạch: ' + (res.plan.title || ''));
            showPlanInChat(res.plan.id);
        }).catch(function (err) {
            planCtrl = null;
            btn.disabled = false;
            btn.textContent = 'Tạo Kế Hoạch';
            if (err && err.name === 'AbortError') {
                planReqId = null;
                sendPlanCancel(reqId);
                toast('info', 'Đã hủy — không tạo kế hoạch nữa.');
                return;
            }
            planReqId = null;
            toast('danger', 'Mất kết nối — không tạo được kế hoạch.');
        });
    }

    /* Bấm Huỷ / đóng modal khi đang tạo kế hoạch → dừng request ngay */
    function cancelPlanCreate() {
        if (!planCtrl) return;
        try { planCtrl.abort(); } catch (e) { /* noop */ }
        planCtrl = null;
    }

    /* Kế hoạch mở NGAY TRONG đoạn chat (không popup, không tab mới): bấm kế hoạch
       ở cột trái hoặc link [Xem kế hoạch] cũ trong tin nhắn → nội dung hiện thành bong bóng chat. */
    function showPlanInChat(id) {
        get('/admin/assistant/plan/view?id=' + encodeURIComponent(id)).then(function (res) {
            if (!res.ok) { toast('danger', res.error || 'Không mở được kế hoạch.'); return; }
            var p = res.plan || {};
            var w = msgsEl.querySelector('.asst-welcome');
            if (w) w.remove();
            // Chỉ giữ MỘT thẻ kế hoạch: mở bản mới → thay thẻ cũ (không xếp nhiều card),
            // và KHÔNG bọc thêm hộp phụ — bong bóng chat là lớp viền duy nhất.
            var old = msgsEl.querySelector('[data-plan-card]');
            if (old && old.parentNode) old.parentNode.removeChild(old);
            var html = '<div data-plan-card>'
                + '<div class="asst-plan-card-head">' + (KIND_ICONS[p.kind] || KIND_ICONS.other)
                + ' <strong>' + esc(p.title || 'Kế hoạch') + '</strong>'
                + '<span class="asst-plan-card-meta">' + esc(p.created_at || '') + '</span></div>'
                + '<div class="asst-plan-body">' + md(res.content || '') + '</div>'
                + '</div>';
            bubble('assistant', html, '');
            scrollBottom();
        }).catch(function () { toast('danger', 'Mất kết nối — không mở được kế hoạch.'); });
    }

    function delPlan(id) {
        var meta = null;
        PLANS.forEach(function (p) { if (p.id === id) meta = p; });
        if (!confirm('Xoá kế hoạch "' + (meta && meta.title ? meta.title : id) + '"?')) return;
        post('/admin/assistant/plan/delete', { id: id }).then(function (res) {
            if (!res.ok) { toast('danger', res.error || 'Không xoá được kế hoạch.'); return; }
            toast('success', 'Đã xoá kế hoạch.');
            PLANS = PLANS.filter(function (p) { return p.id !== id; });
            renderPlans();
        }).catch(function () { toast('danger', 'Mất kết nối — không xoá được.'); });
    }

    /* ================= Sự kiện ================= */
    $('asst-new').addEventListener('click', newConv);

    convListEl.addEventListener('click', function (e) {
        var del = e.target.closest ? e.target.closest('[data-del]') : null;
        if (del) { e.stopPropagation(); delConv(del.getAttribute('data-del')); return; }
        var item = e.target.closest ? e.target.closest('[data-id]') : null;
        if (item) openConv(item.getAttribute('data-id'));
    });

    planListEl.addEventListener('click', function (e) {
        var del = e.target.closest ? e.target.closest('[data-delplan]') : null;
        if (del) { e.stopPropagation(); delPlan(del.getAttribute('data-delplan')); return; }
        var item = e.target.closest ? e.target.closest('[data-plan]') : null;
        if (item) showPlanInChat(item.getAttribute('data-plan'));
    });

    // Gợi ý trong khung chào + ảnh bấm để xem + nút Lưu Bài trên card bài viết
    msgsEl.addEventListener('click', function (e) {
        var psv = e.target.closest ? e.target.closest('[data-post-save]') : null;
        if (psv) { savePostCard(psv); return; }
        var cfm = e.target.closest ? e.target.closest('[data-confirm-action]') : null;
        if (cfm) { runConfirm(cfm); return; }
        var ccl = e.target.closest ? e.target.closest('[data-cancel-action]') : null;
        if (ccl) { dropConfirm(ccl); return; }
        var sug = e.target.closest ? e.target.closest('.asst-sug') : null;
        if (sug) { inputEl.value = sug.getAttribute('data-q') || ''; inputEl.focus(); autoGrow(); return; }
        var img = e.target.closest ? e.target.closest('.asst-img') : null;
        if (img && img.src) window.open(img.src, '_blank', 'noopener');
    });

    // Nút thêm ảnh → mở menu chọn NGUỒN (máy / thư mục AI), không mở picker ngay
    $('asst-attach').addEventListener('click', function (e) {
        e.stopPropagation();
        if (this.disabled) return;
        attachMenuEl.hidden = !attachMenuEl.hidden;
    });
    attachMenuEl.addEventListener('click', function (e) {
        e.stopPropagation();
        var pick = e.target.closest ? e.target.closest('[data-attach]') : null;
        if (!pick) return;
        attachMenuEl.hidden = true;
        if (pick.getAttribute('data-attach') === 'device') { fileEl.click(); return; }
        openAiPicker();
    });
    fileEl.addEventListener('change', function () { addFiles(fileEl.files); fileEl.value = ''; });
    chipsEl.addEventListener('click', function (e) {
        var rm = e.target.closest ? e.target.closest('[data-rm]') : null;
        if (!rm) return;
        pending.splice(parseInt(rm.getAttribute('data-rm'), 10) || 0, 1);
        renderChips();
    });

    function autoGrow() {
        inputEl.style.height = 'auto';
        // Tự mở rộng theo hàng, KHÔNG cuộn: chặn vừa khung chat (giữ chỗ cho khu vực tin nhắn)
        // để scrollbar không bao giờ hiện ra tràn bo góc.
        var wrapEl = document.querySelector('.vc-asst-popup .asst-wrap');
        var room = wrapEl && wrapEl.clientHeight ? (wrapEl.clientHeight - 210)
            : Math.round(window.innerHeight * 0.55);
        var cap = Math.max(200, room);
        inputEl.style.height = Math.min(inputEl.scrollHeight, cap) + 'px';
    }
    inputEl.addEventListener('input', autoGrow);
    window.addEventListener('resize', function () {
        if (popupEl && popupEl.classList.contains('open')) autoGrow();
    });
    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); }
    });
    $('asst-form').addEventListener('submit', function (e) { e.preventDefault(); doSend(); });

    if (modelSel) modelSel.addEventListener('change', function () {
        try { localStorage.setItem(STORE_MODEL, modelSel.value); } catch (e) { /* noop */ }
    });

    $('asst-plan-new').addEventListener('click', function () {
        if (!HAS_MODEL) { toast('warning', 'Không có model chat khả dụng.'); return; }
        openModal(modalNew);
        $('asst-plan-title').focus();
    });
    $('asst-plan-submit').addEventListener('click', createPlan);

    document.addEventListener('click', function (e) {
        // Đóng menu nguồn ảnh khi bấm ra ngoài
        if (attachMenuEl && !attachMenuEl.hidden
            && !(e.target && e.target.closest && e.target.closest('#asst-attach-menu, #asst-attach'))) {
            attachMenuEl.hidden = true;
        }
        // Link kế hoạch ở BẤT KỲ đâu trong chat → hiển thị NGAY trong đoạn chat (không popup, không mở tab mới).
        var plink = e.target && e.target.closest ? e.target.closest('a[href*="assistant/plan/view"]') : null;
        if (plink) {
            e.preventDefault();
            var pm = (plink.getAttribute('href') || '').match(/[?&]id=([^&]+)/);
            if (pm) showPlanInChat(decodeURIComponent(pm[1]));
            return;
        }
        if (e.target && e.target.closest && e.target.closest('[data-close]')) {
            var m = e.target.closest('.asst-modal');
            if (m) {
                if (m === modalNew) cancelPlanCreate();
                closeModal(m);
            }
        }
    });
    function onKey(e) {
        if (e.key !== 'Escape') return;
        if (anyOpen()) {
            if (modalNew.classList.contains('open')) cancelPlanCreate();
            closeModal(modalNew); closeModal(modalAi);
            return;
        }
        // Không có modal nào mở → đóng popup trợ lý
        var pop = $('vc-asst-popup');
        if (pop && pop.classList.contains('open')) closeAsst();
    }
    document.addEventListener('keydown', onKey);

    /* ================= Bong bóng: mở/đóng + bootstrap ================= */
    var popupEl = $('vc-asst-popup');
    var booting = false;

    function openAsst() {
        if (!popupEl) return;
        popupEl.classList.add('open');
        popupEl.setAttribute('aria-hidden', 'false');
        if (!booted) bootstrap();
        setTimeout(function () { inputEl.focus(); }, 60);
    }
    function closeAsst() {
        if (!popupEl) return;
        popupEl.classList.remove('open');
        popupEl.setAttribute('aria-hidden', 'true');
    }
    function applyBootstrap(res) {
        CONVS = res.conversations || [];
        PLANS = res.plans || [];
        var models = res.models || [];
        HAS_MODEL = !!(res.has_model && models.length);
        booted = true;

        var saved = '';
        try { saved = localStorage.getItem(STORE_MODEL) || ''; } catch (e) { saved = ''; }
        if (modelSel) {
            modelSel.innerHTML = '';
            if (HAS_MODEL) {
                models.forEach(function (m) {
                    var o = document.createElement('option');
                    o.value = m.model_key;
                    o.textContent = m.model_name || m.model_key;
                    modelSel.appendChild(o);
                });
                modelSel.disabled = false;
                if (saved && modelSel.querySelector('option[value="' + saved + '"]')) modelSel.value = saved;
            } else {
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = 'Không có model khả dụng';
                modelSel.appendChild(o0);
                modelSel.disabled = true;
            }
        }
        var banner = $('vc-asst-nomodel');
        if (banner) banner.hidden = HAS_MODEL;

        renderConvs();
        renderPlans();

        // Validate đoạn chat đang chọn SAU khi có dữ liệu (không xoá localStorage ở init)
        if (!findConv(current)) current = CONVS.length ? CONVS[0].id : '';
        if (current) openConv(current); else showWelcome();
    }
    function bootstrap() {
        if (booted || booting) return;
        booting = true;
        get('/admin/assistant/bootstrap').then(function (res) {
            booting = false;
            if (!res || res.ok === false) {
                toast('danger', (res && res.error) || 'Không tải được dữ liệu trợ lý.');
                showWelcome();
                return;   // booted vẫn false → lần bấm sau tự thử lại
            }
            applyBootstrap(res);
        }).catch(function () {
            booting = false;
            toast('danger', 'Mất kết nối — không tải được dữ liệu trợ lý.');
            showWelcome();
        });
    }

    var fabEl = $('vc-asst-fab');
    if (fabEl) fabEl.addEventListener('click', openAsst);
    var closeEl = $('vc-asst-close');
    if (closeEl) closeEl.addEventListener('click', closeAsst);

    /* ================= Khởi tạo ================= */
    // Không render dữ liệu vội — chờ admin mở bong bóng → bootstrap.
    // Bong bóng sống sót mọi điều hướng fragment (admin.js chỉ thay .admin-content),
    // nên KHÔNG treo listener vc:partial-leave như bản trang cũ.
    window.VCAsst = { open: openAsst, ready: true };
    document.dispatchEvent(new CustomEvent('vc-asst-ready'));
})();
</script>

<?php
// Partial nhúng vào layouts/admin.php — KHÔNG tự render trang và KHÔNG ob_get_clean().

