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
                <p>Management System</p>
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
            ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
            ['id' => 'clients_register', 'label' => 'Register Client', 'url' => 'clients_register.php'],
            ['id' => 'clients_browse', 'label' => 'Browse Clients', 'url' => 'clients_browse.php'],
            ['id' => 'bill_inquiry', 'label' => 'Client Records', 'url' => 'bill_inquiry.php'],
            ['id' => 'payment_history', 'label' => 'Record Payment', 'url' => 'payment_history.php'],
            ['id' => 'employees', 'label' => 'Employees', 'url' => 'employees.php'],
            ['id' => 'payroll', 'label' => 'Payroll', 'url' => 'payroll.php'],
            ['id' => 'inventory', 'label' => 'Inventory', 'url' => 'inventory.php', 'submenu' => [
                ['label' => 'Stock Management', 'url' => 'inventory.php'],
                ['label' => 'Movement History', 'url' => 'inventory.php?tab=movements'],
                ['label' => 'Requests', 'url' => 'inventory_requests.php'],
            ]],
            ['id' => 'price_management', 'label' => 'Price Management', 'url' => 'price_management.php?view=dashboard'],
            ['id' => 'reports', 'label' => 'Reports', 'url' => 'reports.php'],
            ['id' => 'settings', 'label' => 'System Settings', 'url' => 'settings.php'],
            ['id' => 'user_management', 'label' => 'User Management', 'url' => 'user_management.php'],
            ['id' => 'login_history', 'label' => 'Login History', 'url' => 'login_history.php'],
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
                        $subPath = basename(parse_url($sub['url'], PHP_URL_PATH), '.php');
                        parse_str(parse_url($sub['url'], PHP_URL_QUERY) ?? '', $subQuery);
                        if ($currentPage === $subPath && (!isset($subQuery['tab']) || $currentTab === $subQuery['tab']) && (!isset($subQuery['view']) || $currentView === $subQuery['view'])) {
                            $hasActiveSubmenu = true;
                            break;
                        }
                    }
                }
                $isActive = $currentPage === $item['id'] || $hasActiveSubmenu;
            @endphp
        <div class="nav-group">
            @if ($hasSubmenu)
                <button type="button"
                   class="nav-item nav-toggle {{ $isActive ? 'active' : '' }}"
                   data-submenu-target="{{ $submenuId }}"
                   aria-expanded="{{ $isActive ? 'true' : 'false' }}">
                    <span class="nav-icon">{!! $item['icon'] ?? '' !!}</span>
                    <span>{{ $item['label'] }}</span>
                    <span class="submenu-arrow">▼</span>
                </button>
                <div id="{{ $submenuId }}" class="submenu {{ $isActive ? 'show' : '' }}">
                    @foreach ($item['submenu'] as $sub)
                        @php
                            $subPath = basename(parse_url($sub['url'], PHP_URL_PATH), '.php');
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
                </a>
            @endif
        </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <form id="logout-form" method="POST" action="{{ url('/pages/logout.php') }}">
            @csrf
            <button type="button" class="btn-logout" data-logout-confirm>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
            </button>
        </form>
    </div>
</aside>

<dialog class="logout-dialog" id="logout-confirmation" aria-labelledby="logout-confirmation-title">
    <div class="logout-dialog__content">
        <h2 id="logout-confirmation-title">Do you want to log out?</h2>
        <div class="logout-dialog__actions">
            <button type="button" class="logout-dialog__no" data-logout-cancel>No</button>
            <button type="submit" form="logout-form" class="logout-dialog__yes">Yes</button>
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

<style>
.btn-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    width: 100%;
    margin-top: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(255, 23, 68, 0.1);
    border: 1px solid rgba(255, 23, 68, 0.3);
    border-radius: 10px;
    color: var(--danger);
    font-size: 0.85rem;
    font-weight: 600;
    transition: var(--transition);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-logout:hover {
    background: rgba(255, 23, 68, 0.2);
    border-color: var(--danger);
    transform: translateY(-1px);
}

.btn-logout svg {
    stroke: var(--danger);
}
.logout-dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    margin: 0;
    transform: translate(-50%, -50%);
    width: min(calc(100% - 2rem), 340px);
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--card-bg-solid);
    color: var(--text-primary);
    box-shadow: var(--shadow-lg);
}
.logout-dialog::backdrop {
    background: rgba(2, 10, 25, 0.65);
    backdrop-filter: blur(3px);
}
.logout-dialog__content { padding: 1.5rem; text-align: center; }
.logout-dialog h2 { font-size: 1rem; font-weight: 600; }
.logout-dialog__actions { display: flex; gap: 0.75rem; margin-top: 1.35rem; }
.logout-dialog__actions button { flex: 1; padding: 0.65rem 1rem; border-radius: 8px; font-weight: 700; cursor: pointer; }
.logout-dialog__no { border: 1px solid var(--border-color); background: transparent; color: var(--text-secondary); }
.logout-dialog__yes { border: 1px solid rgba(255, 23, 68, 0.4); background: var(--danger); color: #fff; }
.submenu {
    display: none;
    flex-direction: column;
    padding-bottom: 0.35rem;
}
.submenu.show {
    display: flex;
}
.nav-toggle {
    width: 100%;
    background: transparent;
    border: 0;
    text-align: left;
}
.submenu-arrow {
    margin-left: auto;
    font-size: 0.75rem;
    transition: transform 0.2s ease;
}
.nav-toggle[aria-expanded="true"] .submenu-arrow {
    transform: rotate(180deg);
}
.submenu-item {
    font-size: 0.85rem;
    padding: 0.625rem 1.5rem 0.625rem 3rem;
    border-left: 3px solid transparent;
}
.submenu-item.active {
    color: #ffffff;
    background: #1c1c1c;
    border-left-color: #8a8a8a;
}
@media (max-width: 480px) {
    .submenu-item {
        padding-left: 2rem;
        font-size: 0.8rem;
    }
}
</style>
<script>
    document.querySelector('[data-logout-confirm]')?.addEventListener('click', () => {
        document.getElementById('logout-confirmation')?.showModal();
    });
    document.querySelector('[data-logout-cancel]')?.addEventListener('click', () => {
        document.getElementById('logout-confirmation')?.close();
    });
</script>
