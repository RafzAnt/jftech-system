<!-- Mobile Menu Toggle -->
<button type="button" class="menu-toggle" id="menuToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation menu">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <img class="sidebar-brand__logo" src="{{ url('/assets/jftech-logo.png') }}" alt="JFTECH logo" width="44" height="44">

            <div class="sidebar-brand__text">
                <h1>JFTECH</h1>
                <p>Business Management System</p>
                @if (!empty($sessionScopeLabel))
                <p class="sidebar-scope">{{ $sessionScopeLabel }}</p>
                @endif
            </div>
        </div>
    </div>

    @php
        $currentPage = $currentPage ?? basename(parse_url(request()->getRequestUri(), PHP_URL_PATH), '.php');
        $currentTab = $currentTab ?? request()->query('tab');
        $currentView = $currentView ?? request()->query('view');
        $sessionFullName = $sessionFullName ?? auth()->user()?->full_name ?? 'System Administrator';
        $sessionRole = $sessionRole ?? auth()->user()?->role ?? 'administrator';
        $avatarLetter = $avatarLetter ?? strtoupper(substr($sessionFullName, 0, 1));
        $businessIcons = [
            'dashboard' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
            'clients' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 11a3 3 0 1 0-1.6-5.5M18 14a5 5 0 0 1 3 4.6V21"/></svg>',
            'clients_register' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M19 8v6M16 11h6"/></svg>',
            'clients_browse' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 11h5M18.5 8.5v5"/></svg>',
            'bill_inquiry' => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 14h4M7 17h7"/></svg>',
            'payment_history' => '<svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
            'employees' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 11a3 3 0 1 0-1.6-5.5M18 14a5 5 0 0 1 3 4.6V21"/></svg>',
            'payroll' => '<svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h3M15 15h1"/></svg>',
            'inventory' => '<svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg>',
            'price_management' => '<svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h3M8 16h5"/></svg>',
            'reports' => '<svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
            'settings' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4v-.2a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1-2.8-2.8.1-.1A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.6-1H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2.8-2.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1 2.8 2.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg>',
            'user_management' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 8h5M19.5 5.5v5"/></svg>',
            'login_history' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        ];
        $navItems = $navItems ?? [
            ['id' => 'dashboard', 'section' => 'Overview', 'label' => 'Overview', 'url' => url('/dashboard')],
            ['id' => 'clients_browse', 'section' => 'Clients & Billing', 'label' => 'Clients', 'url' => url('/clients')],
            ['id' => 'bill_inquiry', 'label' => 'Client Accounts', 'url' => url('/client-accounts')],
            ['id' => 'payment_history', 'label' => 'Payment History', 'url' => url('/payments')],
            ['id' => 'price_management', 'label' => 'Internet Plans', 'url' => url('/price-management?view=dashboard')],
            ['id' => 'employees', 'section' => 'Team', 'label' => 'Employees', 'url' => url('/employees')],
            ['id' => 'payroll', 'label' => 'Payroll', 'url' => url('/payroll')],
            ['id' => 'inventory', 'section' => 'Operations', 'label' => 'Inventory', 'url' => url('/inventory'), 'submenu' => [
                ['label' => 'Stock Management', 'url' => url('/inventory')],
                ['label' => 'Stock Update History', 'url' => url('/inventory?tab=movements')],
                ['label' => 'Stock Requests', 'url' => url('/inventory-requests'), 'page' => 'inventory_requests'],
            ]],
            ['id' => 'reports', 'label' => 'Reports', 'url' => url('/reports')],
            ['id' => 'settings', 'section' => 'Administration', 'label' => 'Business Settings', 'url' => url('/settings')],
            ['id' => 'user_management', 'label' => 'User Accounts', 'url' => url('/users')],
            ['id' => 'login_history', 'label' => 'Login History', 'url' => url('/login-history')],
        ];
    @endphp

    <nav class="sidebar-nav">
        @foreach ($navItems as $item)
            @php
                $hasSubmenu = isset($item['submenu']);
                $submenuId = $item['id'] . '_submenu';
                $hasActiveSubmenu = false;
                if ($hasSubmenu) {
                    foreach ($item['submenu'] as $sub) {
                        $subPath = $sub['page'] ?? basename(parse_url($sub['url'], PHP_URL_PATH), '.php');
                        parse_str(parse_url($sub['url'], PHP_URL_QUERY) ?? '', $subQuery);
                        if ($currentPage === $subPath && (!isset($subQuery['tab']) || $currentTab === $subQuery['tab']) && (!isset($subQuery['view']) || $currentView === $subQuery['view'])) {
                            $hasActiveSubmenu = true;
                            break;
                        }
                    }
                }
                $isActive = $currentPage === $item['id'] || $hasActiveSubmenu
                    || ($item['id'] === 'clients_browse' && in_array($currentPage, ['clients_register', 'clients_update'], true));
            @endphp
        @if (!empty($item['section']))
        <span class="nav-section-label">{{ $item['section'] }}</span>
        @endif
        <div class="nav-group">
            @if ($hasSubmenu)
                <button type="button"
                   class="nav-item nav-toggle {{ $isActive ? 'active' : '' }}"
                   data-submenu-target="{{ $submenuId }}"
                   aria-expanded="{{ $isActive ? 'true' : 'false' }}">
                    <span class="nav-icon">{!! $item['icon'] ?? '' !!}</span>
                    <span>{{ $item['label'] }}</span>
                    <span class="submenu-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                </button>
                <div id="{{ $submenuId }}" class="submenu {{ $isActive ? 'show' : '' }}">
                    @foreach ($item['submenu'] as $sub)
                        @php
                            $subPath = $sub['page'] ?? basename(parse_url($sub['url'], PHP_URL_PATH), '.php');
                            parse_str(parse_url($sub['url'], PHP_URL_QUERY) ?? '', $subQuery);
                            $isSubActive = $currentPage === $subPath && (!isset($subQuery['tab']) || $currentTab === $subQuery['tab']) && (!isset($subQuery['view']) || $currentView === $subQuery['view']);
                        @endphp
                    <a href="{{ $sub['url'] }}" class="nav-item submenu-item {{ $isSubActive ? 'active' : '' }}">
                        {{ $sub['label'] }}
                    </a>
                    @endforeach
                </div>
            @else
                <a href="{{ $item['url'] }}" class="nav-item {{ $isActive ? 'active' : '' }}">
                    <span class="nav-icon">{!! $item['icon'] ?? '' !!}</span>
                    <span>{{ $item['label'] }}</span>
                    @if (!empty($item['badge']))
                    <span class="nav-badge" title="Waiting for you">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endif
        </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <form id="logout-form" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="button" class="btn-logout" data-logout-confirm>Logout</button>
        </form>
    </div>
</aside>

<dialog class="logout-dialog" id="logout-confirmation" aria-labelledby="logout-confirmation-title">
    <div class="logout-dialog__content">
        <h2 id="logout-confirmation-title">Log out of JFTECH?</h2>
        <p>You will need to sign in again to continue.</p>
        <div class="logout-dialog__actions">
            <button type="button" class="logout-dialog__no" data-logout-cancel>Cancel</button>
            <button type="submit" form="logout-form" class="logout-dialog__yes">Log out</button>
        </div>
    </div>
</dialog>

<script>
// Keep the mobile menu and submenus usable even when the shared JavaScript
// file is slow to load through a forwarded port.
(function () {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    const closeSidebar = function () {
        sidebar?.classList.remove('open');
        sidebarOverlay?.classList.remove('active');
        menuToggle?.setAttribute('aria-expanded', 'false');
    };

    if (menuToggle && sidebar) {
        menuToggle.dataset.menuReady = 'true';
        menuToggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('open');
            sidebarOverlay?.classList.toggle('active', isOpen);
            menuToggle.setAttribute('aria-expanded', String(isOpen));
        });
    }

    sidebarOverlay?.addEventListener('click', closeSidebar);

    // The link list can scroll on short screens; start with the current page in view.
    // Run again after fonts/images settle, since they change the list height.
    const revealActiveLink = function () {
        const nav = document.querySelector('.sidebar-nav');
        const activeLinks = document.querySelectorAll('.sidebar-nav .nav-item.active');
        const link = activeLinks[activeLinks.length - 1];
        if (!nav || !link) return;
        const navBox = nav.getBoundingClientRect();
        const linkBox = link.getBoundingClientRect();
        if (linkBox.top < navBox.top || linkBox.bottom > navBox.bottom) {
            nav.scrollTop += linkBox.top - navBox.top - (nav.clientHeight - linkBox.height) / 2;
        }
    };
    revealActiveLink();
    window.addEventListener('load', revealActiveLink, { once: true });

document.querySelectorAll('[data-submenu-target]').forEach(function (toggle) {
    toggle.dataset.submenuReady = 'true';
    toggle.addEventListener('click', function () {
        const submenu = document.getElementById(toggle.dataset.submenuTarget);
        if (!submenu) return;

        const isOpen = !submenu.classList.contains('show');
        submenu.classList.toggle('show', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
    });
});
}());
</script>

<script>
    document.querySelector('[data-logout-confirm]')?.addEventListener('click', () => {
        document.getElementById('logout-confirmation')?.showModal();
    });
    document.querySelector('[data-logout-cancel]')?.addEventListener('click', () => {
        document.getElementById('logout-confirmation')?.close();
    });

    // Logs out after five minutes without a click, key, scroll or mouse move. The last-activity time is kept in
    // localStorage so every open tab shares one clock (a quiet tab must not log out someone working in another),
    // and a heartbeat tells the server about activity that never leaves the page, such as typing in a form;
    // the inactivity middleware enforces the same five minutes on its side.
    (() => {
        const logoutForm = document.getElementById('logout-form');
        if (!logoutForm) return;

        const timeoutMs = {{ \App\Http\Middleware\EnforceInactivityTimeout::TIMEOUT_SECONDS }} * 1000;
        const heartbeatMs = 30 * 1000;
        const storageKey = 'jftech-last-activity';
        const csrfToken = logoutForm.querySelector('input[name="_token"]')?.value ?? '';
        let lastActivity = Date.now();
        let lastHeartbeat = lastActivity;
        let heartbeatTimer = null;
        let idleTimer = null;
        let leaving = false;

        const remember = (time) => {
            lastActivity = time;
            try { localStorage.setItem(storageKey, String(time)); } catch (error) { /* private mode: this tab keeps its own clock */ }
        };

        const lastActivityInAnyTab = () => {
            try { return Math.max(lastActivity, Number(localStorage.getItem(storageKey)) || 0); } catch (error) { return lastActivity; }
        };

        const post = (url, body) => fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'manual',
            keepalive: true,
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body,
        });

        const toLogin = () => window.location.replace('/?timeout=1');

        const logoutForInactivity = () => {
            if (leaving) return;
            leaving = true;
            // A form submit would fail with "Page Expired" if the server already dropped the session (laptop asleep), so post it ourselves and go to the login page either way.
            post(logoutForm.action, new URLSearchParams()).catch(() => {}).finally(toLogin);
        };

        const checkIdle = () => {
            window.clearTimeout(idleTimer);
            const idleFor = Date.now() - lastActivityInAnyTab();
            if (idleFor >= timeoutMs) return logoutForInactivity();
            idleTimer = window.setTimeout(checkIdle, timeoutMs - idleFor);
        };

        const heartbeat = () => {
            heartbeatTimer = null;
            lastHeartbeat = Date.now();
            const idle = Math.floor((lastHeartbeat - lastActivityInAnyTab()) / 1000);
            post('{{ route('session.keepalive') }}', new URLSearchParams({ idle: String(idle) }))
                // 401 or a 419 "Page Expired" means the session is gone; a redirect (type "opaqueredirect") is the login page.
                .then((response) => { if (response.type === 'opaqueredirect' || response.status === 401 || response.status === 419) toLogin(); })
                .catch(() => {});
        };

        const onActivity = () => {
            const now = Date.now();
            if (leaving || now - lastActivity < 1000) return;
            // Coming back to a tab that sat idle past the limit (or woke from sleep) logs out instead of reviving the session.
            if (now - lastActivityInAnyTab() >= timeoutMs) return logoutForInactivity();
            remember(now);
            if (!heartbeatTimer) heartbeatTimer = window.setTimeout(heartbeat, Math.max(0, heartbeatMs - (now - lastHeartbeat)));
        };

        ['pointerdown', 'pointermove', 'keydown', 'wheel', 'touchstart', 'scroll'].forEach((eventName) => {
            // Capture so scrolling inside a panel counts too (scroll events do not bubble).
            window.addEventListener(eventName, onActivity, { capture: true, passive: true });
        });
        document.addEventListener('visibilitychange', () => { if (!document.hidden) checkIdle(); });

        // Opening a page is a request, so it counts as activity.
        remember(lastActivity);
        checkIdle();
    })();
</script>
