<?php
$page_loader_role = $page_loader_role ?? 'customer';
?>
<!-- K Supermarket Professional Loader -->
<div id="page-loader" class="page-loader-<?php echo htmlspecialchars($page_loader_role, ENT_QUOTES, 'UTF-8'); ?>" aria-live="polite" aria-label="Loading">
    <div class="ks-loader-card">
        <div class="ks-logo-wrapper">
            <div class="ks-cart-icon">🛒</div>
            <div class="ks-logo-text">K</div>
        </div>
        <div class="ks-brand-name">SUPERMARKET</div>
        <div class="ks-progress-bar">
            <div class="ks-progress-fill"></div>
        </div>
    </div>
</div>

<style>
#page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 99999;
    transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.5s;
}

.ks-loader-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: rgba(255, 255, 255, 0.03);
    padding: 35px 50px;
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
}

.ks-logo-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02));
    border: 2px solid var(--theme-color, #10b981);
    box-shadow: 0 0 25px var(--theme-glow, rgba(16, 185, 129, 0.4));
    animation: pulseGlow 2s infinite ease-in-out;
}

.ks-cart-icon {
    font-size: 28px;
    animation: cartBounce 1.2s infinite ease-in-out;
}

.ks-logo-text {
    font-family: 'Poppins', sans-serif;
    font-size: 28px;
    font-weight: 900;
    color: #fff;
    margin-left: 2px;
}

.ks-brand-name {
    margin-top: 18px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 4px;
    color: #e2e8f0;
    animation: brandPulse 1.5s ease-in-out infinite;
}

.ks-progress-bar {
    width: 140px;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    margin-top: 20px;
    overflow: hidden;
}

.ks-progress-fill {
    width: 100%;
    height: 100%;
    background: var(--theme-color, #10b981);
    border-radius: 10px;
    animation: progressAnim 1.5s infinite ease-in-out;
}

@keyframes pulseGlow {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes cartBounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

@keyframes progressAnim {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes brandPulse {
    0%, 100% { opacity: 1; letter-spacing: 4px; }
    50% { opacity: 0.65; letter-spacing: 5px; }
}

#page-loader.loader-hidden {
    opacity: 0;
    visibility: hidden;
}

#page-loader.page-loader-admin {
    --theme-color: #ef4444;
    --theme-glow: rgba(239, 68, 68, 0.4);
}

#page-loader.page-loader-staff {
    --theme-color: #3b82f6;
    --theme-glow: rgba(59, 130, 246, 0.4);
}

#page-loader.page-loader-customer {
    --theme-color: #10b981;
    --theme-glow: rgba(16, 185, 129, 0.4);
}

/* Global Dark Mode Styles */
body.dark-mode {
    background-color: #0b0f19 !important;
    color: #f8fafc !important;
}

/* Sidebar Link Visibility Fix */
body.dark-mode .sidebar a,
body.dark-mode .sidebar .list-group-item,
body.dark-mode .sidebar-link,
body.dark-mode .sidebar-item {
    color: #94a3b8 !important;
}

body.dark-mode .sidebar a.active,
body.dark-mode .sidebar .list-group-item.active,
body.dark-mode .sidebar-link.active,
body.dark-mode .sidebar a:hover,
body.dark-mode .sidebar .list-group-item:hover,
body.dark-mode .sidebar-link:hover,
body.dark-mode .nav-link.active {
    color: #ffffff !important;
}

/* Cards, Tables and White Container Visibility */
body.dark-mode .card,
body.dark-mode .content-card,
body.dark-mode .stat-mini-card,
body.dark-mode .table-card,
body.dark-mode .product-card,
body.dark-mode .bg-white,
body.dark-mode .list-group-item,
body.dark-mode .white-container,
body.dark-mode .order-step-card,
body.dark-mode .sidebar,
body.dark-mode .modal-content,
body.dark-mode .cart-drawer,
body.dark-mode .dropdown-menu,
body.dark-mode table,
body.dark-mode div.box {
    background-color: #1e293b !important;
    color: #f8fafc !important;
    border-color: #334155 !important;
}

body.dark-mode .table {
    --bs-table-bg: #1e293b;
    --bs-table-color: #f8fafc;
    --bs-table-border-color: #334155;
    --bs-table-striped-bg: #263449;
    --bs-table-hover-bg: #334155;
    --bs-table-hover-color: #ffffff;
}

body.dark-mode .table-light,
body.dark-mode .table-light th,
body.dark-mode .table-light td {
    background-color: #334155 !important;
    color: #f8fafc !important;
    border-color: #475569 !important;
}

/* Form Labels, Inputs and Placeholders */
body.dark-mode label,
body.dark-mode .form-label {
    color: #cbd5e1 !important;
}

body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea,
body.dark-mode .form-control,
body.dark-mode .form-select {
    background-color: #334155 !important;
    color: #ffffff !important;
    border-color: #475569 !important;
}

body.dark-mode input::placeholder,
body.dark-mode textarea::placeholder {
    color: #64748b !important;
}

/* Secondary Subtitles and Stat Card Labels */
body.dark-mode small,
body.dark-mode .text-muted,
body.dark-mode .sub-text,
body.dark-mode p.desc,
body.dark-mode .sku-text,
body.dark-mode .item-count,
body.dark-mode .date-text {
    color: #94a3b8 !important;
}

body.dark-mode .text-dark,
body.dark-mode .text-secondary,
body.dark-mode .progress-step-label {
    color: #cbd5e1 !important;
}

body.dark-mode th,
body.dark-mode td,
body.dark-mode .table {
    color: #e2e8f0 !important;
    border-color: #334155 !important;
}

body.dark-mode .card-stat .text-muted,
body.dark-mode .stat-mini-card .text-muted,
body.dark-mode .card-stat h1,
body.dark-mode .card-stat h2,
body.dark-mode .card-stat h3,
body.dark-mode .card-stat h4,
body.dark-mode .card-stat h5,
body.dark-mode .card-stat h6 {
    color: #cbd5e1 !important;
}

body.dark-mode .sidebar-link:hover,
body.dark-mode .sidebar .list-group-item:hover:not(.active) {
    background-color: #334155 !important;
}

body.dark-mode .alert {
    border-color: #475569 !important;
}

/* Top Selling Products Cards */
body.dark-mode .table-card .bg-light-subtle,
body.dark-mode .top-selling-item,
body.dark-mode [class*="top-selling"] {
    background-color: #1e293b !important;
    border-color: #334155 !important;
}

body.dark-mode .table-card .bg-light-subtle .text-dark,
body.dark-mode .top-selling-item h5,
body.dark-mode .top-selling-item h6,
body.dark-mode .top-selling-item span,
body.dark-mode .top-selling-item p {
    color: #ffffff !important;
}

body.dark-mode .table-card .bg-light-subtle small,
body.dark-mode .top-selling-item small,
body.dark-mode .top-selling-item .text-muted {
    color: #cbd5e1 !important;
}

body.dark-mode .table-card .bg-light-subtle .bg-primary-subtle {
    background-color: #1e3a8a !important;
}

body.dark-mode .table-card .bg-light-subtle .text-primary {
    color: #bfdbfe !important;
}

/* Order Tracker Progress Bar */
body.dark-mode .progress-tracker,
body.dark-mode .order-tracker,
body.dark-mode .steps-container {
    background-color: #1e293b !important;
    color: #f8fafc !important;
}

body.dark-mode .progress-step-num,
body.dark-mode .step-node,
body.dark-mode .step-circle,
body.dark-mode .step-number {
    background-color: #334155 !important;
    color: #ffffff !important;
    border-color: #475569 !important;
}

body.dark-mode .progress-step.active .progress-step-num,
body.dark-mode .progress-step.completed .progress-step-num,
body.dark-mode .step-node.active,
body.dark-mode .step-circle.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
}

body.dark-mode .progress-step-label,
body.dark-mode .step-label,
body.dark-mode .step-text,
body.dark-mode .tracker-step p,
body.dark-mode .tracker-step span {
    color: #cbd5e1 !important;
}

body.dark-mode .progress-step.active .progress-step-label,
body.dark-mode .progress-step.completed .progress-step-label {
    color: #ffffff !important;
}

body.dark-mode .progress-connector {
    background-color: #475569 !important;
}

body.dark-mode .theme-toggle-btn {
    background: #f8fafc;
    color: #0f172a;
}

/* Theme Toggle Button */
.theme-toggle-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #1e293b;
    color: #f8fafc;
    border: 2px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    line-height: 1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    transition: all 0.3s ease;
}

.theme-toggle-btn:hover {
    transform: translateY(-2px);
}
</style>

<script>
(function () {
    var savedTheme = localStorage.getItem('ks_theme');
    if (savedTheme === 'dark' && document.body) {
        document.body.classList.add('dark-mode');
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('theme-toggle-btn')) {
        var btn = document.createElement('button');
        btn.id = 'theme-toggle-btn';
        btn.className = 'theme-toggle-btn';
        btn.type = 'button';
        btn.innerHTML = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
        btn.setAttribute('title', 'Toggle Dark/Light Mode');
        btn.setAttribute('aria-label', 'Toggle Dark/Light Mode');
        document.body.appendChild(btn);

        btn.addEventListener('click', function () {
            document.body.classList.toggle('dark-mode');
            var isDark = document.body.classList.contains('dark-mode');
            btn.innerHTML = isDark ? '☀️' : '🌙';
            localStorage.setItem('ks_theme', isDark ? 'dark' : 'light');
        });
    }

    // Use delegated listeners so links/forms added later by page scripts are covered.
    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        var sectionControl = event.target.closest ? event.target.closest('a[href^="#"], .sidebar-link, #sidebar-menu .list-group-item[onclick]') : null;

        // Dashboard section switches do not reload the document, so finish the
        // loader transition locally after the selected section is displayed.
        if (sectionControl && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey &&
            !event.defaultPrevented) {
            showPageLoader();
            window.setTimeout(function () {
                hidePageLoader();
            }, 450);
            return;
        }

        if (!link) {
            return;
        }

        var href = link.getAttribute('href') || '';
        if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ||
            href.indexOf('javascript:') === 0 ||
            link.hasAttribute('download') || (link.getAttribute('target') || '_self') !== '_self') {
            return;
        }

        try {
            var destination = new URL(href, window.location.href);
            if (destination.origin === window.location.origin) {
                showPageLoader();
            }
        } catch (error) {
            // Leave normal browser handling untouched for malformed URLs.
        }
    }, true);

    document.addEventListener('submit', function (event) {
        if (!event.defaultPrevented) {
            showPageLoader();
        }
    }, true);
});

function showPageLoader() {
    var loader = document.getElementById('page-loader');
    if (loader) {
        loader.classList.remove('loader-hidden');
    }
}

function hidePageLoader() {
    var loader = document.getElementById('page-loader');
    if (loader) {
        loader.classList.add('loader-hidden');
    }
}

window.addEventListener('load', function () {
    window.setTimeout(function () {
        hidePageLoader();
    }, 250);
});

window.addEventListener('pageshow', function () {
    hidePageLoader();
});
</script>
