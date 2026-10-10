<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Stock Requests</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/inventory_requests.css') }}?{{ $inventoryRequestsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Stock Requests</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Approve technician inventory requests</p>
            </div>
            @if ($pendingCount > 0)
            <span class="badge badge-warning">{{ $pendingCount }} Pending</span>
            @endif
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <div class="filter-tabs">
            <a href="inventory_requests.php?status=all" class="filter-tab {{ $filterStatus === 'all' ? 'active' : '' }}">All</a>
            <a href="inventory_requests.php?status=pending" class="filter-tab {{ $filterStatus === 'pending' ? 'active' : '' }}">
                Pending @if ($pendingCount > 0)({{ $pendingCount }})@endif
            </a>
            <a href="inventory_requests.php?status=approved" class="filter-tab {{ $filterStatus === 'approved' ? 'active' : '' }}">Approved</a>
            <a href="inventory_requests.php?status=denied" class="filter-tab {{ $filterStatus === 'denied' ? 'active' : '' }}">Denied</a>
        </div>

        <div class="card">
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Request No.</th>
                                <th>Employee</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Request Details</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Handled By</th>
                                <th class="actions-column" aria-label="Request menu"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $req)
                            @php
$statusClass = 'status-' . $req['status'];
                            $processedBy = '';
                            if ($req['status'] !== 'pending') {
                                $processedBy = $req['approved_by_full_name'] ?: $req['approved_by_name'] ?: 'System';
                            }
                            
@endphp
                            <tr>
                                <td>#{{ $req['id'] }}</td>
                                <td><strong>{{ htmlspecialchars($req['employee_name']) }}</strong></td>
                                <td>{{ htmlspecialchars($req['item_name']) }}</td>
                                <td>{{ $req['quantity'] }}</td>
                                <td style="max-width: 250px; padding: 1rem;">
                                    @if ($req['comments'])
                                        <span style="color: var(--text-secondary); font-size: 0.9rem;">{{ nl2br(htmlspecialchars($req['comments'])) }}</span>
                                    @else
                                        <span style="color: var(--text-muted);">-</span>
                                    @endif
                                </td>
                                <td><span class="status-badge {{ $statusClass }}">{{ ucfirst($req['status']) }}</span></td>
                                <td>{{ date('M d, Y h:i A', strtotime($req['created_at'])) }}</td>
                                <td style="font-size: 0.85rem; color: var(--text-secondary);">{{ $processedBy ?: '-' }}</td>
                                <td class="actions-column">
                                    @if ($req['status'] === 'pending')
                                    <div class="request-action-menu">
                                        <button class="request-action-menu-btn" type="button" aria-label="Actions for request #{{ $req['id'] }}" aria-expanded="false" onclick="toggleRequestActionMenu(this)">&#8942;</button>
                                        <div class="request-action-menu-dropdown">
                                        <form method="POST" action="{{ route('inventory-requests.decide', ['inventoryRequest' => $req['id'], 'decision' => 'approve']) }}" onsubmit="return confirm('Approve this request and release stock?');">
            @csrf
                                            <button type="submit" class="request-menu-item">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory-requests.decide', ['inventoryRequest' => $req['id'], 'decision' => 'deny']) }}" onsubmit="return confirm('Deny this request?');">
            @csrf
                                            <button type="submit" class="request-menu-item request-menu-item-danger">Deny</button>
                                        </form>
                                        </div>
                                    </div>
                                    @else
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Processed</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($requests))
                            <tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">No requests found</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
    function closeRequestActionMenus() {
        document.querySelectorAll('.request-action-menu-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
            if (menu._requestMenuPlaceholder) {
                menu.removeAttribute('style');
                menu._requestMenuPlaceholder.replaceWith(menu);
                delete menu._requestMenuPlaceholder;
            }
        });
        document.querySelectorAll('.request-action-menu-btn[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    }
    function toggleRequestActionMenu(button) {
        const menu = button.nextElementSibling;
        const willOpen = !menu.classList.contains('show');
        closeRequestActionMenus();
        if (!willOpen) return;
        const placeholder = document.createComment('request action menu');
        menu.parentNode.replaceChild(placeholder, menu);
        menu._requestMenuPlaceholder = placeholder;
        document.body.appendChild(menu);
        menu.style.cssText = 'position:fixed;visibility:hidden;z-index:1000;right:auto;bottom:auto';
        menu.classList.add('show');
        const buttonRect = button.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const top = buttonRect.top >= menuRect.height + 8 ? buttonRect.top - menuRect.height - 6 : Math.min(window.innerHeight - menuRect.height - 8, buttonRect.bottom + 6);
        const left = Math.max(8, Math.min(window.innerWidth - menuRect.width - 8, buttonRect.right - menuRect.width));
        menu.style.top = Math.max(8, top) + 'px';
        menu.style.left = left + 'px';
        menu.style.visibility = 'visible';
        button.setAttribute('aria-expanded', 'true');
    }
    document.addEventListener('click', event => {
        if (!event.target.closest('.request-action-menu') && !event.target.closest('.request-action-menu-dropdown')) closeRequestActionMenus();
    });
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
