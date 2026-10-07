<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PaperTrail Dashboard')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @stack('vendor-styles')
    <link rel="stylesheet" href="{{ asset('css/shared-document-lists.css') }}">
    <link rel="stylesheet" href="{{ asset('css/bac-resolution.css') }}?v={{ file_exists(public_path('css/bac-resolution.css')) ? filemtime(public_path('css/bac-resolution.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/purchase-order.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rfq.css') }}">
    <link rel="stylesheet" href="{{ asset('css/abstract.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app-form.css') }}?v={{ filemtime(public_path('css/app-form.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/annual-procurement-plan.css') }}?v={{ filemtime(public_path('css/annual-procurement-plan.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/supplemental-app.css') }}?v={{ filemtime(public_path('css/supplemental-app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/svp-tracking.css') }}">
    <link rel="stylesheet" href="{{ asset('css/e-signature.css') }}?v={{ file_exists(public_path('css/e-signature.css')) ? filemtime(public_path('css/e-signature.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/signature-requests.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-drafts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-attachments.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-records.css') }}?v={{ filemtime(public_path('css/document-records.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ppmp.css') }}?v={{ filemtime(public_path('css/ppmp.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/rule-configuration.css') }}">
    <link rel="stylesheet" href="{{ asset('css/procurement-chatbot.css') }}?v={{ filemtime(public_path('css/procurement-chatbot.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/assistant.css') }}?v={{ filemtime(public_path('css/assistant.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ai-completeness.css') }}?v={{ filemtime(public_path('css/ai-completeness.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ai-summary-dashboard.css') }}?v={{ filemtime(public_path('css/ai-summary-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/saas.css') }}?v={{ file_exists(public_path('css/saas.css')) ? filemtime(public_path('css/saas.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/document-forms.css') }}?v={{ file_exists(public_path('css/document-forms.css')) ? filemtime(public_path('css/document-forms.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/document-info.css') }}?v={{ file_exists(public_path('css/document-info.css')) ? filemtime(public_path('css/document-info.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/document-density.css') }}?v={{ file_exists(public_path('css/document-density.css')) ? filemtime(public_path('css/document-density.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/papertrail-density.css') }}?v={{ file_exists(public_path('css/papertrail-density.css')) ? filemtime(public_path('css/papertrail-density.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/ai-feature-tools.css') }}?v={{ file_exists(public_path('css/ai-feature-tools.css')) ? filemtime(public_path('css/ai-feature-tools.css')) : time() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/papertrail-dark-mode.css') }}?v={{ file_exists(public_path('css/papertrail-dark-mode.css')) ? filemtime(public_path('css/papertrail-dark-mode.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/papertrail-auth-shell.css') }}?v={{ file_exists(public_path('css/papertrail-auth-shell.css')) ? filemtime(public_path('css/papertrail-auth-shell.css')) : time() }}">
    <script>
        try {
            if (localStorage.getItem('papertrail_theme') === 'dark') {
                document.documentElement.classList.add('papertrail-dark-mode');
            }
        } catch (error) {
            // Theme preference is optional.
        }
    </script>
</head>
<body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Open sidebar" aria-controls="dashboard-sidebar" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
            <span class="sr-only">Toggle sidebar</span>
        </button>

        <div class="sidebar-backdrop" data-sidebar-backdrop></div>

        <x-dashboard.sidebar />

        <main class="dashboard-main">
            <x-dashboard.header />

            @if (session('status'))
                <div class="session-alert" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="session-alert session-alert-error" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div id="dashboardContent" class="dashboard-content stagger-root">
                <div class="pt-loading-overlay" data-progressive-loading hidden aria-hidden="true">
                    <div class="pt-content-loading" role="status" aria-live="polite">
                        <span class="sr-only">Loading page content</span>
                        <div class="pt-skeleton pt-skeleton-header"></div>
                        <div class="pt-skeleton-card-grid" aria-hidden="true">
                            <div class="pt-skeleton pt-skeleton-card"></div>
                            <div class="pt-skeleton pt-skeleton-card"></div>
                            <div class="pt-skeleton pt-skeleton-card"></div>
                            <div class="pt-skeleton pt-skeleton-card"></div>
                        </div>
                        <div class="pt-skeleton-table" aria-hidden="true">
                            <div class="pt-skeleton pt-skeleton-row"></div>
                            <div class="pt-skeleton pt-skeleton-row"></div>
                            <div class="pt-skeleton pt-skeleton-row"></div>
                            <div class="pt-skeleton pt-skeleton-row"></div>
                            <div class="pt-skeleton pt-skeleton-row"></div>
                        </div>
                    </div>
                </div>
                @yield('content')
            </div>
        </main>
    </div>

    @include('partials.ai-chatbot-preview')
    @stack('modals')

    <div class="pt-confirm-modal" data-pt-confirm-modal hidden>
        <div class="pt-confirm-backdrop" data-pt-confirm-cancel></div>
        <section class="pt-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="pt-confirm-title" aria-describedby="pt-confirm-message">
            <div class="pt-confirm-icon" data-pt-confirm-icon aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M12 8v5M12 17h.01M10.3 4.5 2.8 17.5A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.5L13.7 4.5a2 2 0 0 0-3.4 0Z" />
                </svg>
            </div>
            <div class="pt-confirm-copy">
                <span data-pt-confirm-eyebrow>Confirmation Required</span>
                <h2 id="pt-confirm-title" data-pt-confirm-title>Please Confirm</h2>
                <p id="pt-confirm-message" data-pt-confirm-message>Are you sure you want to continue?</p>
            </div>
            <div class="pt-confirm-actions">
                <button type="button" class="pt-confirm-cancel" data-pt-confirm-cancel>Cancel</button>
                <button type="button" class="pt-confirm-submit" data-pt-confirm-submit>Continue</button>
            </div>
        </section>
    </div>

    <div class="pt-crud-modal" data-crud-modal hidden>
        <div class="pt-crud-modal__backdrop" data-crud-modal-close></div>
        <section class="pt-crud-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pt-crud-modal-title">
            <header class="pt-crud-modal__header">
                <div>
                    <p data-crud-modal-eyebrow>PaperTrail</p>
                    <h2 id="pt-crud-modal-title" data-crud-modal-title>Manage Record</h2>
                </div>
                <button type="button" class="pt-crud-modal__close" data-crud-modal-close aria-label="Close modal">
                    <x-papertrail.icon name="close" />
                </button>
            </header>
            <div class="pt-crud-modal__body" data-crud-modal-body>
                <div class="pt-crud-modal__loading" data-crud-modal-loading>Loading...</div>
                <iframe data-crud-modal-frame title="PaperTrail management form" hidden></iframe>
                <div class="pt-crud-modal__content" data-crud-modal-content hidden></div>
            </div>
        </section>
    </div>

    <script>
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        const sessionHeartbeatUrl = @json(route('session.heartbeat'));
        const sessionTimeoutUrl = @json(route('session.timeout'));
        const sessionLoginUrl = @json(route('login'));
        const sessionHeartbeatToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const sessionHeartbeatInterval = 60 * 1000;
        const sessionIdleLimit = Math.max(@json((int) config('session.lifetime', 10) * 60 * 1000), 60 * 1000);
        let lastSessionHeartbeatAt = 0;
        let lastUserActivityAt = Date.now();
        let sessionIdleTimer = null;
        let sessionTimedOut = false;

        function sendSessionHeartbeat(force = false) {
            const now = Date.now();

            if (sessionTimedOut) {
                return;
            }

            if (!force && now - lastSessionHeartbeatAt < sessionHeartbeatInterval) {
                return;
            }

            lastSessionHeartbeatAt = now;

            fetch(sessionHeartbeatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': sessionHeartbeatToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).catch(() => {
                // The next authenticated page request will handle an expired session.
            });
        }

        function handleSessionIdleTimeout() {
            if (sessionTimedOut) {
                return;
            }

            const idleFor = Date.now() - lastUserActivityAt;

            if (idleFor < sessionIdleLimit) {
                scheduleSessionIdleTimeout();
                return;
            }

            sessionTimedOut = true;

            fetch(sessionTimeoutUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': sessionHeartbeatToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json().catch(() => ({})))
                .then((data) => {
                    window.location.assign(data.redirect || sessionLoginUrl);
                })
                .catch(() => {
                    window.location.assign(sessionLoginUrl);
                });
        }

        function scheduleSessionIdleTimeout() {
            window.clearTimeout(sessionIdleTimer);

            const idleFor = Date.now() - lastUserActivityAt;
            const timeoutIn = Math.max(sessionIdleLimit - idleFor, 1000);

            sessionIdleTimer = window.setTimeout(handleSessionIdleTimeout, timeoutIn);
        }

        function recordSessionActivity(forceHeartbeat = false) {
            if (sessionTimedOut) {
                return;
            }

            lastUserActivityAt = Date.now();
            scheduleSessionIdleTimeout();
            sendSessionHeartbeat(forceHeartbeat);
        }

        ['click', 'keydown', 'submit', 'change', 'scroll', 'pointerdown', 'touchstart'].forEach((eventName) => {
            window.addEventListener(eventName, () => recordSessionActivity(), {
                capture: true,
                passive: eventName !== 'submit',
            });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                return;
            }

            if (Date.now() - lastUserActivityAt >= sessionIdleLimit) {
                handleSessionIdleTimeout();
                return;
            }

            recordSessionActivity(true);
        });

        scheduleSessionIdleTimeout();

        const dashboardShell = document.querySelector('[data-dashboard-shell]');
        const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
        const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
        const sidebarCollapse = document.querySelector('[data-sidebar-collapse]');
        const collapseStorageKey = 'papertrail_sidebar_collapsed';
        const mobileSidebarQuery = window.matchMedia('(max-width: 980px)');

        function setSidebar(open) {
            dashboardShell.classList.toggle('sidebar-open', open);
            document.body.classList.toggle('sidebar-mobile-open', open);
            sidebarToggle?.setAttribute('aria-expanded', String(open));
        }

        function setCollapsed(collapsed) {
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            sidebarCollapse?.setAttribute('aria-expanded', String(!collapsed));
            sidebarCollapse?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }

        function getStoredSidebarCollapsed() {
            try {
                return localStorage.getItem(collapseStorageKey) === 'true';
            } catch (error) {
                return false;
            }
        }

        setCollapsed(mobileSidebarQuery.matches ? false : getStoredSidebarCollapsed());

        sidebarToggle?.addEventListener('click', () => {
            setSidebar(!dashboardShell.classList.contains('sidebar-open'));
        });

        sidebarCollapse?.addEventListener('click', () => {
            if (mobileSidebarQuery.matches) {
                setSidebar(false);
                return;
            }

            const collapsed = !document.body.classList.contains('sidebar-collapsed');
            setCollapsed(collapsed);

            try {
                localStorage.setItem(collapseStorageKey, String(collapsed));
            } catch (error) {
                // Ignore storage errors and keep the visible state for this page.
            }
        });

        sidebarBackdrop?.addEventListener('click', () => setSidebar(false));

        document.querySelectorAll('[data-sidebar-dropdown]').forEach((dropdown) => {
            const toggle = dropdown.querySelector('[data-sidebar-dropdown-toggle]');

            toggle?.addEventListener('click', () => {
                if (!mobileSidebarQuery.matches && document.body.classList.contains('sidebar-collapsed')) {
                    setCollapsed(false);

                    try {
                        localStorage.setItem(collapseStorageKey, 'false');
                    } catch (error) {
                        // Keep the expanded state for this page.
                    }
                }

                const isOpen = dropdown.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', String(isOpen));
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dashboardShell.classList.contains('sidebar-open')) {
                setSidebar(false);
            }
        });

        mobileSidebarQuery.addEventListener?.('change', (event) => {
            setSidebar(false);

            if (event.matches) {
                setCollapsed(false);
                return;
            }

            setCollapsed(getStoredSidebarCollapsed());
        });

        const themeStorageKey = 'papertrail_theme';
        const themeToggle = document.querySelector('[data-theme-toggle]');
        const fullscreenToggle = document.querySelector('[data-fullscreen-toggle]');

        function setTheme(theme) {
            const isDark = theme === 'dark';
            document.documentElement.classList.toggle('papertrail-dark-mode', isDark);
            document.body.classList.toggle('papertrail-dark-mode', isDark);
            themeToggle?.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            themeToggle?.setAttribute('title', isDark ? 'Light mode' : 'Dark mode');
        }

        try {
            setTheme(localStorage.getItem(themeStorageKey) === 'dark' ? 'dark' : 'light');
        } catch (error) {
            setTheme('light');
        }

        themeToggle?.addEventListener('click', () => {
            const nextTheme = document.documentElement.classList.contains('papertrail-dark-mode') ? 'light' : 'dark';
            setTheme(nextTheme);

            try {
                localStorage.setItem(themeStorageKey, nextTheme);
            } catch (error) {
                // Ignore storage errors and keep the visible theme.
            }
        });

        function syncFullscreenButton() {
            const isFullscreen = Boolean(document.fullscreenElement);
            fullscreenToggle?.classList.toggle('is-fullscreen', isFullscreen);
            fullscreenToggle?.setAttribute('aria-label', isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen');
            fullscreenToggle?.setAttribute('title', isFullscreen ? 'Exit fullscreen' : 'Fullscreen');
        }

        fullscreenToggle?.addEventListener('click', async () => {
            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                } else {
                    await document.documentElement.requestFullscreen();
                }
            } catch (error) {
                // Browser fullscreen can be blocked by user/browser policy.
            }

            syncFullscreenButton();
        });

        document.addEventListener('fullscreenchange', syncFullscreenButton);
        syncFullscreenButton();

        const notificationToggle = document.querySelector('[data-notification-toggle]');
        const notificationDropdown = document.querySelector('[data-notification-dropdown]');

        function closeNotificationMenu() {
            notificationDropdown?.classList.remove('is-open');
            notificationToggle?.setAttribute('aria-expanded', 'false');
        }

        notificationToggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            closeUserMenu();
            const isOpen = notificationDropdown?.classList.toggle('is-open');
            notificationToggle.setAttribute('aria-expanded', String(Boolean(isOpen)));
        });

        document.addEventListener('click', (event) => {
            if (!notificationDropdown?.classList.contains('is-open')) {
                return;
            }

            if (event.target.closest('[data-notification-dropdown]')) {
                return;
            }

            closeNotificationMenu();
        });

        const userMenu = document.querySelector('[data-user-menu]');
        const userMenuToggle = document.querySelector('[data-user-menu-toggle]');

        function closeUserMenu() {
            userMenu?.classList.remove('is-open');
            userMenuToggle?.setAttribute('aria-expanded', 'false');
        }

        userMenuToggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            closeNotificationMenu();
            const isOpen = userMenu?.classList.toggle('is-open');
            userMenuToggle.setAttribute('aria-expanded', String(Boolean(isOpen)));
        });

        document.addEventListener('click', (event) => {
            if (!userMenu?.classList.contains('is-open')) {
                return;
            }

            if (event.target.closest('[data-user-menu]')) {
                return;
            }

            closeUserMenu();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeUserMenu();
            }
        });

        const dashboardContent = document.querySelector('#dashboardContent');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const progressiveLoadingOverlay = dashboardContent?.querySelector('[data-progressive-loading]');
        let progressiveLoadingTimer = null;
        let progressiveInitialLoadingActive = false;

        if (dashboardContent && !prefersReducedMotion.matches) {
            const sectionDelays = [0, 120, 220, 300, 380];

            dashboardContent.querySelectorAll(':scope > :not([data-progressive-loading])').forEach((item, index) => {
                item.classList.add('pt-smooth-enter');
                item.style.setProperty('--pt-delay', `${sectionDelays[index] ?? 380}ms`);
            });

            dashboardContent.querySelectorAll('tbody tr, .report-registry-row, .future-report-row, .settings-row, .activity-list li, .task-list li').forEach((item, index) => {
                if (index > 8) return;

                item.classList.add('pt-smooth-enter-soft');
                item.style.setProperty('--pt-delay', `${Math.min(360 + index * 45, 620)}ms`);
            });
        }

        function setDashboardProgressiveLoading(isLoading, options = {}) {
            if (!dashboardContent || !progressiveLoadingOverlay) {
                return;
            }

            window.clearTimeout(progressiveLoadingTimer);

            const applyState = () => {
                dashboardContent.classList.toggle('is-soft-loading', isLoading);
                progressiveLoadingOverlay.hidden = !isLoading;
                progressiveLoadingOverlay.classList.toggle('is-visible', isLoading);
                progressiveLoadingOverlay.setAttribute('aria-hidden', String(!isLoading));
            };

            if (isLoading && !options.immediate && !prefersReducedMotion.matches) {
                progressiveLoadingTimer = window.setTimeout(applyState, 120);
                return;
            }

            applyState();
        }

        function isSamePageHashLink(url) {
            return url.origin === window.location.origin
                && url.pathname === window.location.pathname
                && url.search === window.location.search
                && Boolean(url.hash);
        }

        function shouldShowProgressiveLoadingForLink(link, event) {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
            ) {
                return false;
            }

            if (!(link instanceof HTMLAnchorElement) || !link.href) {
                return false;
            }

            if (
                link.hasAttribute('download')
                || link.target && link.target !== '_self'
                || link.matches('[data-no-progressive-loading], [data-crud-modal-src], [data-confirm], [data-pt-download-confirm]')
                || link.closest('[data-no-progressive-loading]')
            ) {
                return false;
            }

            let url;

            try {
                url = new URL(link.href, window.location.href);
            } catch (error) {
                return false;
            }

            return url.origin === window.location.origin && !isSamePageHashLink(url);
        }

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const link = target?.closest('a[href]');

            if (!shouldShowProgressiveLoadingForLink(link, event)) {
                return;
            }

            link.classList.add('is-loading');
            setDashboardProgressiveLoading(true);
        });

        document.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (
                form.matches('[data-no-progressive-loading], [data-assistant-form]')
                || form.target && form.target !== '_self'
                || String(form.method || '').toLowerCase() === 'dialog'
                || !form.checkValidity()
            ) {
                return;
            }

            setDashboardProgressiveLoading(true);
        });

        window.addEventListener('beforeunload', () => {
            setDashboardProgressiveLoading(true, { immediate: true });
        });

        window.addEventListener('pageshow', () => {
            if (progressiveInitialLoadingActive) {
                return;
            }

            setDashboardProgressiveLoading(false, { immediate: true });
        });

        function consumeLoginDashboardLoadingFlag() {
            try {
                const shouldShow = sessionStorage.getItem('papertrail_show_dashboard_loading_after_login') === 'true';
                sessionStorage.removeItem('papertrail_show_dashboard_loading_after_login');

                return shouldShow;
            } catch (error) {
                return false;
            }
        }

        if (consumeLoginDashboardLoadingFlag()) {
            progressiveInitialLoadingActive = true;
            setDashboardProgressiveLoading(true, { immediate: true });

            window.setTimeout(() => {
                progressiveInitialLoadingActive = false;
                setDashboardProgressiveLoading(false, { immediate: true });
            }, prefersReducedMotion.matches ? 350 : 760);
        }

        document.addEventListener('click', (event) => {
            const selectButton = event.target.closest('[data-select-group]');
            const clearButton = event.target.closest('[data-clear-group]');

            if (!selectButton && !clearButton) {
                return;
            }

            const group = event.target.closest('.permission-group');
            if (!group) {
                return;
            }

            group.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                input.checked = Boolean(selectButton);
            });
        });

        const confirmModal = document.querySelector('[data-pt-confirm-modal]');
        const confirmTitle = confirmModal?.querySelector('[data-pt-confirm-title]');
        const confirmEyebrow = confirmModal?.querySelector('[data-pt-confirm-eyebrow]');
        const confirmMessage = confirmModal?.querySelector('[data-pt-confirm-message]');
        const confirmSubmit = confirmModal?.querySelector('[data-pt-confirm-submit]');
        const confirmCancelButtons = confirmModal?.querySelectorAll('[data-pt-confirm-cancel]');
        const confirmToneClasses = [
            'pt-confirm-modal--submit',
            'pt-confirm-modal--approval',
            'pt-confirm-modal--danger',
            'pt-confirm-modal--return',
            'pt-confirm-modal--download',
            'pt-confirm-modal--info',
            'pt-confirm-modal--alert',
        ];
        let pendingConfirmForm = null;
        let pendingConfirmSubmitter = null;
        let pendingConfirmLink = null;
        let pendingConfirmResolver = null;

        function getStatusChangeMessage(form) {
            if (!form?.dataset?.confirmStatusChange) {
                return null;
            }

            const currentStatus = form.dataset.currentStatus || '';
            const statusField = form.querySelector('[name="status"]');
            const nextStatus = statusField?.value || '';

            if (!nextStatus || nextStatus === currentStatus) {
                return null;
            }

            return nextStatus === 'inactive' ? 'Deactivate this account?' : 'Activate this account?';
        }

        function extractInlineConfirmMessage(element) {
            const attributeValue = element?.getAttribute?.('onclick') || element?.getAttribute?.('onsubmit') || '';
            const match = attributeValue.match(/confirm\((['"])(.*?)\1\)/);

            return match?.[2] || null;
        }

        function inferConfirmType(message = '') {
            const text = String(message).toLowerCase();

            if (text.includes('delete') || text.includes('remove')) {
                return 'danger';
            }

            if (text.includes('download') || text.includes('export')) {
                return 'download';
            }

            if (text.includes('return') || text.includes('reject') || text.includes('decline')) {
                return 'return';
            }

            if (text.includes('approve') || text.includes('accept') || text.includes('complete') || text.includes('sign')) {
                return 'approval';
            }

            if (text.includes('submit') || text.includes('forward') || text.includes('route') || text.includes('acknowledge')) {
                return 'submit';
            }

            return 'info';
        }

        function defaultConfirmTitle(type, message = '') {
            const text = String(message).toLowerCase();

            if (type === 'download') return 'Download Document?';
            if (type === 'danger') return text.includes('remove') ? 'Remove Record?' : 'Delete Record?';
            if (type === 'return') return text.includes('reject') ? 'Reject Document?' : 'Return Document?';
            if (type === 'approval') return text.includes('sign') ? 'Sign Document?' : 'Confirm Approval?';
            if (type === 'submit') return text.includes('forward') || text.includes('route') ? 'Forward Document?' : 'Submit Document?';

            return 'Please Confirm';
        }

        function defaultConfirmLabel(type, message = '') {
            const text = String(message).toLowerCase();

            if (type === 'download') return 'Download';
            if (type === 'danger') return text.includes('remove') ? 'Remove' : 'Delete';
            if (type === 'return') return text.includes('reject') ? 'Confirm Rejection' : 'Confirm Return';
            if (type === 'approval') return text.includes('sign') ? 'Apply Signature' : 'Confirm Action';
            if (type === 'submit') return text.includes('forward') || text.includes('route') ? 'Forward' : 'Confirm Submission';

            return 'Continue';
        }

        function dialogOptionsFromSource(source = null, fallbackMessage = null) {
            const dataMessage = source?.dataset?.confirm;
            const message = dataMessage || fallbackMessage || 'Are you sure you want to continue?';
            const type = source?.dataset?.confirmType
                || (source?.hasAttribute?.('data-pt-download-confirm') ? 'download' : inferConfirmType(message));

            return {
                message,
                type,
                title: source?.dataset?.confirmTitle || defaultConfirmTitle(type, message),
                confirmLabel: source?.dataset?.confirmLabel || defaultConfirmLabel(type, message),
                eyebrow: source?.dataset?.confirmEyebrow || (type === 'info' ? 'PaperTrail Notice' : 'Confirmation Required'),
                hideCancel: source?.dataset?.confirmHideCancel === 'true',
            };
        }

        function extractConfirmMessage(form, submitter = null) {
            const statusMessage = getStatusChangeMessage(form);

            if (statusMessage) {
                return statusMessage;
            }

            const dataMessage = submitter?.dataset?.confirm || form?.dataset?.confirm;

            if (dataMessage) {
                return dataMessage;
            }

            return extractInlineConfirmMessage(submitter) || extractInlineConfirmMessage(form) || 'Are you sure you want to continue?';
        }

        function closeConfirmModal(result = false) {
            const resolver = pendingConfirmResolver;

            confirmModal?.setAttribute('hidden', '');
            confirmModal?.classList.remove(...confirmToneClasses);
            pendingConfirmForm = null;
            pendingConfirmSubmitter = null;
            pendingConfirmLink = null;
            pendingConfirmResolver = null;

            if (resolver) {
                resolver(result);
            }
        }

        function needsPaperTrailConfirmation(form, submitter = null) {
            if (!(form instanceof HTMLFormElement)) {
                return false;
            }

            if (form.dataset.ptConfirmApproved === 'true') {
                return false;
            }

            const formConfirm = form.dataset.confirm || form.getAttribute('onsubmit') || '';
            const submitterDataConfirm = submitter?.dataset?.confirm || '';
            const submitterConfirm = submitter?.getAttribute('onclick') || '';
            const statusMessage = getStatusChangeMessage(form);

            return Boolean(statusMessage)
                || Boolean(form.dataset.confirm)
                || Boolean(submitterDataConfirm)
                || formConfirm.includes('confirm(')
                || submitterConfirm.includes('confirm(');
        }

        function openConfirmModal(options = {}) {
            const type = options.type || inferConfirmType(options.message);

            confirmModal?.classList.remove(...confirmToneClasses);
            confirmModal?.classList.add(`pt-confirm-modal--${type}`);
            confirmModal?.classList.toggle('pt-confirm-modal--alert', Boolean(options.hideCancel));

            if (confirmTitle) {
                confirmTitle.textContent = options.title || defaultConfirmTitle(type, options.message);
            }

            if (confirmEyebrow) {
                confirmEyebrow.textContent = options.eyebrow || (type === 'info' ? 'PaperTrail Notice' : 'Confirmation Required');
            }

            if (confirmMessage) {
                confirmMessage.textContent = options.message || 'Are you sure you want to continue?';
            }

            if (confirmSubmit) {
                confirmSubmit.textContent = options.confirmLabel || defaultConfirmLabel(type, options.message);
            }

            confirmModal?.removeAttribute('hidden');
            confirmSubmit?.focus();
        }

        function convertNativeConfirmAttributes() {
            document.querySelectorAll('form[onsubmit*="confirm("]').forEach((form) => {
                const message = extractInlineConfirmMessage(form);

                if (message && !form.dataset.confirm) {
                    form.dataset.confirm = message;
                }

                form.removeAttribute('onsubmit');
            });

            document.querySelectorAll('button[onclick*="confirm("], input[type="submit"][onclick*="confirm("], a[onclick*="confirm("]').forEach((element) => {
                const message = extractInlineConfirmMessage(element);

                if (message && !element.dataset.confirm) {
                    element.dataset.confirm = message;
                }

                element.removeAttribute('onclick');
            });
        }

        function isDownloadConfirmationLink(link) {
            if (!(link instanceof HTMLAnchorElement) || !link.href || link.href.startsWith('#')) {
                return false;
            }

            if (link.dataset.ptConfirmApproved === 'true' || link.dataset.ptConfirmSkip === 'true') {
                return false;
            }

            const descriptor = [
                link.className,
                link.getAttribute('aria-label') || '',
                link.getAttribute('title') || '',
                link.textContent || '',
            ].join(' ').toLowerCase();

            return link.hasAttribute('data-pt-download-confirm')
                || link.hasAttribute('download')
                || link.classList.contains('document-record-download-btn')
                || link.classList.contains('attachment-download-action')
                || descriptor.includes('download document')
                || descriptor.includes('download');
        }

        function openConfirmForForm(form, submitter = null) {
            pendingConfirmForm = form;
            pendingConfirmSubmitter = submitter;

            openConfirmModal(dialogOptionsFromSource(
                submitter?.dataset?.confirm || submitter?.getAttribute?.('onclick') ? submitter : form,
                extractConfirmMessage(form, submitter)
            ));
        }

        function openConfirmForLink(link) {
            pendingConfirmLink = link;
            openConfirmModal(dialogOptionsFromSource(link, link.dataset.confirm || 'This file contains official procurement records.'));
        }

        convertNativeConfirmAttributes();

        window.PaperTrailDialog = {
            confirm(message, options = {}) {
                return new Promise((resolve) => {
                    pendingConfirmResolver = resolve;
                    openConfirmModal(dialogOptionsFromSource({
                        dataset: {
                            confirm: message,
                            confirmTitle: options.title || '',
                            confirmLabel: options.confirmLabel || '',
                            confirmType: options.type || '',
                            confirmEyebrow: options.eyebrow || '',
                        },
                        hasAttribute: () => false,
                    }, message));
                });
            },
            notice(message, options = {}) {
                return new Promise((resolve) => {
                    pendingConfirmResolver = resolve;
                    openConfirmModal({
                        message,
                        type: options.type || 'info',
                        title: options.title || 'PaperTrail Notice',
                        eyebrow: options.eyebrow || 'PaperTrail Notice',
                        confirmLabel: options.confirmLabel || 'OK',
                        hideCancel: true,
                    });
                });
            },
        };

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const link = target?.closest('a[href]');

            if (link?.dataset?.confirm && link.dataset.ptConfirmApproved !== 'true') {
                event.preventDefault();
                event.stopImmediatePropagation();
                openConfirmForLink(link);
                return;
            }

            if (isDownloadConfirmationLink(link)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openConfirmForLink(link);
                return;
            }

            const submitter = target?.closest('button[type="submit"], input[type="submit"]');
            const form = submitter?.form || submitter?.closest('form');

            if (!needsPaperTrailConfirmation(form, submitter)) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            openConfirmForForm(form, submitter);
        }, true);

        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!needsPaperTrailConfirmation(form)) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            openConfirmForForm(form);
        }, true);

        confirmSubmit?.addEventListener('click', () => {
            const form = pendingConfirmForm;
            const submitter = pendingConfirmSubmitter;
            const link = pendingConfirmLink;
            const resolver = pendingConfirmResolver;

            closeConfirmModal(true);

            if (resolver) {
                return;
            }

            if (link) {
                link.dataset.ptConfirmApproved = 'true';

                if (link.target && link.target !== '_self') {
                    window.open(link.href, link.target, 'noopener');
                } else {
                    window.location.href = link.href;
                }

                window.setTimeout(() => {
                    delete link.dataset.ptConfirmApproved;
                }, 800);
                return;
            }

            if (!form) {
                return;
            }

            form.dataset.ptConfirmApproved = 'true';
            form.removeAttribute('onsubmit');
            submitter?.removeAttribute('onclick');

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter || undefined);
                return;
            }

            form.submit();
        });

        confirmCancelButtons?.forEach((button) => {
            button.addEventListener('click', closeConfirmModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && confirmModal && !confirmModal.hasAttribute('hidden')) {
                closeConfirmModal();
            }
        });

        const crudModal = document.querySelector('[data-crud-modal]');
        const crudModalFrame = crudModal?.querySelector('[data-crud-modal-frame]');
        const crudModalContent = crudModal?.querySelector('[data-crud-modal-content]');
        const crudModalLoading = crudModal?.querySelector('[data-crud-modal-loading]');
        const crudModalTitle = crudModal?.querySelector('[data-crud-modal-title]');
        const crudModalEyebrow = crudModal?.querySelector('[data-crud-modal-eyebrow]');
        let lastCrudModalTrigger = null;

        function setCrudModalOpen(open) {
            if (!crudModal) return;
            crudModal.toggleAttribute('hidden', !open);
            document.body.classList.toggle('pt-crud-modal-open', open);

            if (open) {
                window.setTimeout(() => crudModal.querySelector('[data-crud-modal-close]')?.focus(), 0);
            }
        }

        function setCrudModalText(title, eyebrow = 'PaperTrail') {
            if (crudModalTitle) {
                crudModalTitle.textContent = title || 'Manage Record';
            }

            if (crudModalEyebrow) {
                crudModalEyebrow.textContent = eyebrow || 'PaperTrail';
            }
        }

        function closeCrudModal() {
            if (!crudModal) return;

            setCrudModalOpen(false);
            crudModal.classList.remove('pt-crud-modal--wide');

            if (crudModalFrame) {
                crudModalFrame.hidden = true;
                crudModalFrame.style.removeProperty('height');
                crudModalFrame.removeAttribute('src');
                delete crudModalFrame.dataset.returnUrl;
                delete crudModalFrame.dataset.initialized;
            }

            if (crudModalContent) {
                crudModalContent.hidden = true;
                crudModalContent.innerHTML = '';
            }

            crudModalLoading?.removeAttribute('hidden');
            lastCrudModalTrigger?.focus?.();
            lastCrudModalTrigger = null;
        }

        function setCrudModalSize(trigger) {
            if (!crudModal) return;
            crudModal.classList.toggle('pt-crud-modal--wide', trigger?.dataset?.crudModalSize === 'wide');
        }

        function resizeCrudModalFrame() {
            if (!crudModalFrame || crudModalFrame.hidden || crudModal?.hasAttribute('hidden')) {
                return;
            }

            try {
                const documentElement = crudModalFrame.contentDocument?.documentElement;
                const body = crudModalFrame.contentDocument?.body;
                const contentHeight = Math.max(
                    documentElement?.scrollHeight || 0,
                    body?.scrollHeight || 0,
                    320
                );
                const maxHeight = Math.max(window.innerHeight - 132, 360);
                const nextHeight = Math.min(contentHeight, maxHeight);
                crudModalFrame.style.height = `${nextHeight}px`;
            } catch (error) {
                crudModalFrame.style.height = '';
            }
        }

        function openCrudFrame(trigger) {
            if (!crudModal || !crudModalFrame) return;

            const source = trigger.dataset.crudModalSrc || trigger.href;
            if (!source) return;

            lastCrudModalTrigger = trigger;
            setCrudModalSize(trigger);
            setCrudModalText(trigger.dataset.crudModalTitle || trigger.getAttribute('aria-label') || trigger.textContent?.trim(), trigger.dataset.crudModalEyebrow || 'Management');
            crudModalContent.hidden = true;
            crudModalContent.innerHTML = '';
            crudModalLoading?.removeAttribute('hidden');
            crudModalFrame.hidden = false;
            crudModalFrame.style.removeProperty('height');
            crudModalFrame.dataset.returnUrl = trigger.dataset.crudModalReturn || window.location.href;
            delete crudModalFrame.dataset.initialized;
            crudModalFrame.src = source;
            setCrudModalOpen(true);
        }

        function openCrudTemplate(trigger) {
            if (!crudModal || !crudModalContent) return;

            const template = document.getElementById(trigger.dataset.crudModalTemplate);
            if (!template) return;

            lastCrudModalTrigger = trigger;
            setCrudModalSize(trigger);
            setCrudModalText(trigger.dataset.crudModalTitle || trigger.getAttribute('aria-label') || 'Record Details', trigger.dataset.crudModalEyebrow || 'Details');
            crudModalFrame.hidden = true;
            crudModalFrame.style.removeProperty('height');
            crudModalFrame.removeAttribute('src');
            crudModalContent.innerHTML = '';
            crudModalContent.appendChild(template.content.cloneNode(true));
            crudModalContent.hidden = false;
            crudModalLoading?.setAttribute('hidden', '');
            setCrudModalOpen(true);
        }

        crudModalFrame?.addEventListener('load', () => {
            crudModalLoading?.setAttribute('hidden', '');
            window.requestAnimationFrame(resizeCrudModalFrame);
            window.setTimeout(resizeCrudModalFrame, 160);

            try {
                const frameUrl = new URL(crudModalFrame.contentWindow.location.href);
                const returnUrl = new URL(crudModalFrame.dataset.returnUrl || window.location.href, window.location.href);
                const isInitialLoad = crudModalFrame.dataset.initialized !== 'true';
                crudModalFrame.dataset.initialized = 'true';

                if (!isInitialLoad && frameUrl.pathname === returnUrl.pathname && !frameUrl.searchParams.has('modal')) {
                    window.location.assign(frameUrl.href);
                }
            } catch (error) {
                // Same-origin modal frames are expected, but keep the modal usable if inspection fails.
            }
        });

        window.addEventListener('resize', resizeCrudModalFrame);

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const closeTrigger = target?.closest('[data-crud-modal-close]');

            if (closeTrigger) {
                event.preventDefault();
                closeCrudModal();
                return;
            }

            const templateTrigger = target?.closest('[data-crud-modal-template]');

            if (templateTrigger) {
                event.preventDefault();
                openCrudTemplate(templateTrigger);
                return;
            }

            const frameTrigger = target?.closest('[data-crud-modal-trigger]');

            if (frameTrigger instanceof HTMLAnchorElement) {
                event.preventDefault();
                openCrudFrame(frameTrigger);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && crudModal && !crudModal.hasAttribute('hidden')) {
                closeCrudModal();
            }
        });

        const chatbotWidgets = [...document.querySelectorAll('[data-chatbot-widget]')];
        chatbotWidgets.slice(1).forEach((widget) => widget.remove());
        const chatbotWidget = chatbotWidgets[0] || null;
        const chatbotToggle = chatbotWidget?.querySelector('[data-chatbot-toggle]');
        const chatbotPanel = chatbotWidget?.querySelector('[data-chatbot-panel]');
        const chatbotClose = chatbotWidget?.querySelector('[data-chatbot-close]');
        const chatbotPositionKey = 'papertrail_chatbot_position';
        const chatbotViewportMargin = 10;
        let chatbotClickSuppressed = false;
        let chatbotDragState = null;

        function setChatbot(open) {
            if (!chatbotToggle || !chatbotPanel) return;
            chatbotPanel.hidden = !open;
            chatbotToggle.setAttribute('aria-expanded', String(open));
            chatbotWidget?.classList.toggle('is-chatbot-open', open);
        }

        function clampChatbotPosition(left, top) {
            if (!chatbotWidget) {
                return { left, top };
            }

            const width = chatbotWidget.offsetWidth || 62;
            const height = chatbotWidget.offsetHeight || 62;
            const maxLeft = Math.max(chatbotViewportMargin, window.innerWidth - width - chatbotViewportMargin);
            const maxTop = Math.max(chatbotViewportMargin, window.innerHeight - height - chatbotViewportMargin);

            return {
                left: Math.min(Math.max(left, chatbotViewportMargin), maxLeft),
                top: Math.min(Math.max(top, chatbotViewportMargin), maxTop),
            };
        }

        function updateChatbotEdgeClass(left, top) {
            if (!chatbotWidget) return;
            chatbotWidget.classList.toggle('is-near-left', left < 170);
            chatbotWidget.classList.toggle('is-near-top', top < 280);
        }

        function applyChatbotPosition(left, top, persist = false) {
            if (!chatbotWidget) return;

            const position = clampChatbotPosition(left, top);

            chatbotWidget.classList.add('is-positioned');
            chatbotWidget.style.left = `${position.left}px`;
            chatbotWidget.style.top = `${position.top}px`;
            chatbotWidget.style.right = 'auto';
            chatbotWidget.style.bottom = 'auto';
            updateChatbotEdgeClass(position.left, position.top);

            if (persist) {
                try {
                    sessionStorage.setItem(chatbotPositionKey, JSON.stringify(position));
                } catch (error) {
                    // Position memory is optional.
                }
            }
        }

        function restoreChatbotPosition() {
            if (!chatbotWidget) return;

            try {
                const savedPosition = JSON.parse(sessionStorage.getItem(chatbotPositionKey) || 'null');

                if (
                    savedPosition
                    && Number.isFinite(savedPosition.left)
                    && Number.isFinite(savedPosition.top)
                ) {
                    applyChatbotPosition(savedPosition.left, savedPosition.top, false);
                }
            } catch (error) {
                // Ignore invalid session position data.
            }
        }

        chatbotToggle?.addEventListener('click', (event) => {
            if (chatbotClickSuppressed) {
                event.preventDefault();
                chatbotClickSuppressed = false;
                return;
            }

            setChatbot(Boolean(chatbotPanel?.hidden));
        });

        chatbotToggle?.addEventListener('pointerdown', (event) => {
            if (!chatbotWidget || event.button > 0) {
                return;
            }

            const rect = chatbotWidget.getBoundingClientRect();

            chatbotDragState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                left: rect.left,
                top: rect.top,
                moved: false,
            };

            chatbotToggle.setPointerCapture?.(event.pointerId);
        });

        chatbotToggle?.addEventListener('pointermove', (event) => {
            if (!chatbotDragState || chatbotDragState.pointerId !== event.pointerId) {
                return;
            }

            const deltaX = event.clientX - chatbotDragState.startX;
            const deltaY = event.clientY - chatbotDragState.startY;

            if (!chatbotDragState.moved && Math.hypot(deltaX, deltaY) < 4) {
                return;
            }

            chatbotDragState.moved = true;
            chatbotWidget?.classList.add('is-dragging');
            applyChatbotPosition(chatbotDragState.left + deltaX, chatbotDragState.top + deltaY);
            event.preventDefault();
        });

        function endChatbotDrag(event) {
            if (!chatbotDragState || chatbotDragState.pointerId !== event.pointerId) {
                return;
            }

            chatbotToggle?.releasePointerCapture?.(event.pointerId);
            chatbotWidget?.classList.remove('is-dragging');

            if (chatbotDragState.moved && chatbotWidget) {
                const rect = chatbotWidget.getBoundingClientRect();
                applyChatbotPosition(rect.left, rect.top, true);
                chatbotClickSuppressed = true;
                window.setTimeout(() => {
                    chatbotClickSuppressed = false;
                }, 0);
            }

            chatbotDragState = null;
        }

        chatbotToggle?.addEventListener('pointerup', endChatbotDrag);
        chatbotToggle?.addEventListener('pointercancel', endChatbotDrag);

        chatbotClose?.addEventListener('click', () => setChatbot(false));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && chatbotPanel && !chatbotPanel.hidden) {
                setChatbot(false);
            }
        });

        window.addEventListener('resize', () => {
            if (!chatbotWidget?.classList.contains('is-positioned')) {
                return;
            }

            const rect = chatbotWidget.getBoundingClientRect();
            applyChatbotPosition(rect.left, rect.top, true);
        });

        restoreChatbotPosition();

        document.querySelectorAll('[data-document-create-flow]').forEach((flow) => {
            const sourcePanel = flow.querySelector('[data-document-create-source]');
            const formPanel = flow.querySelector('[data-document-create-form]');
            const proceedButton = flow.querySelector('[data-document-create-proceed]');
            const errorMessage = flow.querySelector('[data-document-create-error]');
            const requiredFields = [...flow.querySelectorAll('[data-document-create-required]')];
            const shouldAutoShow = flow.dataset.documentCreateAutoshow === 'true';
            const contentRoot = flow.closest('#dashboardContent') || flow.parentElement || document;
            const relatedDraftPanels = [...contentRoot.querySelectorAll('.document-drafts-panel')]
                .filter((panel) => !flow.contains(panel));
            let stageTransitionTimer = null;

            if (!sourcePanel || !formPanel || !proceedButton) {
                return;
            }

            formPanel.hidden = false;
            flow.classList.add('is-document-create-initialized');

            function setPanelInactive(panel, inactive) {
                if (!panel) return;

                panel.setAttribute('aria-hidden', String(inactive));

                if ('inert' in panel) {
                    panel.inert = inactive;
                } else if (inactive) {
                    panel.setAttribute('inert', '');
                } else {
                    panel.removeAttribute('inert');
                }
            }

            function activePanel() {
                return flow.classList.contains('is-document-form-ready') ? formPanel : sourcePanel;
            }

            function setStageHeight(panel = activePanel()) {
                if (!panel) return;

                const height = Math.max(1, Math.ceil(panel.getBoundingClientRect().height));
                flow.style.setProperty('--document-create-stage-height', `${height}px`);
            }

            function fieldHasValue(field) {
                if (field instanceof HTMLInputElement && ['checkbox', 'radio'].includes(field.type)) {
                    if (field.name) {
                        return Boolean(flow.querySelector(`[name="${CSS.escape(field.name)}"]:checked`));
                    }

                    return field.checked;
                }

                return String(field.value || '').trim() !== '';
            }

            function sourceIsValid() {
                return requiredFields.length === 0 || requiredFields.every(fieldHasValue);
            }

            function revealForm(scrollIntoView = true) {
                window.clearTimeout(stageTransitionTimer);
                setStageHeight(formPanel);
                relatedDraftPanels.forEach((panel) => {
                    panel.classList.add('is-hidden-for-document-form-step');
                    panel.setAttribute('aria-hidden', 'true');
                });
                flow.classList.add('is-transitioning');
                flow.classList.add('is-document-form-ready');
                flow.classList.remove('is-source-invalid');
                setPanelInactive(sourcePanel, true);
                setPanelInactive(formPanel, false);

                if (errorMessage) {
                    errorMessage.hidden = true;
                }

                if (scrollIntoView) {
                    window.requestAnimationFrame(() => {
                        flow.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }

                stageTransitionTimer = window.setTimeout(() => {
                    flow.classList.remove('is-transitioning');
                    setStageHeight(formPanel);
                }, 380);
            }

            function showSourceError() {
                flow.classList.add('is-source-invalid');

                if (errorMessage) {
                    errorMessage.hidden = false;
                }

                requiredFields.find((field) => !fieldHasValue(field))?.focus();
                window.requestAnimationFrame(() => setStageHeight(sourcePanel));
            }

            setPanelInactive(sourcePanel, false);
            setPanelInactive(formPanel, true);
            setStageHeight(sourcePanel);
            window.requestAnimationFrame(() => setStageHeight(sourcePanel));

            if (shouldAutoShow) {
                revealForm(false);
            }

            proceedButton.addEventListener('click', () => {
                if (!sourceIsValid()) {
                    showSourceError();
                    return;
                }

                revealForm();
            });

            requiredFields.forEach((field) => {
                ['change', 'input'].forEach((eventName) => {
                    field.addEventListener(eventName, () => {
                        if (sourceIsValid()) {
                            flow.classList.remove('is-source-invalid');

                            if (errorMessage) {
                                errorMessage.hidden = true;
                            }

                            window.requestAnimationFrame(() => setStageHeight(sourcePanel));
                        }
                    });
                });
            });

            if ('ResizeObserver' in window) {
                const resizeObserver = new ResizeObserver(() => setStageHeight());
                resizeObserver.observe(sourcePanel);
                resizeObserver.observe(formPanel);
            }

            window.addEventListener('resize', () => setStageHeight());
        });

        const phtClock = document.querySelector('[data-pht-clock]');
        const phtDate = phtClock?.querySelector('[data-pht-date]');
        const phtTime = phtClock?.querySelector('[data-pht-time]');

        function updatePhtClock() {
            if (!phtClock || !phtDate || !phtTime) return;

            const now = new Date();
            const dateText = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            }).format(now);
            const timeText = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
            }).format(now);

            phtDate.textContent = dateText;
            phtTime.textContent = timeText;
            phtTime.dateTime = now.toISOString();
        }

        updatePhtClock();
        window.setInterval(updatePhtClock, 1000);
    </script>
    @stack('vendor-scripts')
    @stack('scripts')
    <script src="{{ asset('js/papertrail-action-icons.js') }}?v={{ filemtime(public_path('js/papertrail-action-icons.js')) }}" defer></script>
    <script src="{{ asset('js/signature-pad-basic.js') }}?v={{ file_exists(public_path('js/signature-pad-basic.js')) ? filemtime(public_path('js/signature-pad-basic.js')) : time() }}" defer></script>
</body>
</html>
