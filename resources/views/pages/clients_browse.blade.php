<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Browse Clients</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/clients_browse.css') }}?{{ $clientsBrowseCssVersion }}">
    <style>
        #receiptModal .print-company-brand { display: flex; align-items: flex-start; gap: 8px; }
        #receiptModal .print-company-logo { width: auto; height: 36px; object-fit: contain; }
        #receiptModal .print-company-brand .company { font-size: 0.95rem; font-weight: 800; }
        #receiptModal .print-company-brand .sub { font-size: 0.7rem; margin-top: 2px; }
        @media print {
            body.printing-client-receipt * { visibility: hidden !important; }
            body.printing-client-receipt #receiptModal,
            body.printing-client-receipt #receiptModal * { visibility: visible !important; }
            body.printing-client-receipt #receiptModal {
                position: fixed;
                inset: 0;
                display: block;
                padding: 12mm;
                background: #fff;
            }
            body.printing-client-receipt #receiptModal .modal-content {
                width: 100%;
                max-width: none;
                margin: 0;
                box-shadow: none;
            }
            body.printing-client-receipt .modal-close,
            body.printing-client-receipt .receipt-print-actions { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Browse Clients</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">View and manage all registered clients</p>
            </div>
            <button type="button" class="btn btn-success" onclick="openModal('registerClientModal')">+ Register New Client</button>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

<!-- Tabs -->
<div class="tabs-container">
    <div class="tabs">
        <a href="clients_browse.php" class="tab-btn {{ !$showArchived ? 'active' : '' }}">Active Clients</a>
        <a href="clients_browse.php?view=archived" class="tab-btn {{ $showArchived ? 'active' : '' }}">Archived ({{ $archivedCount }})</a>
    </div>
</div>

<!-- Barangay Selector & Search -->
<div class="filter-bar card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div class="form-group" style="margin: 0; min-width: 250px;">
            <label class="form-label" style="margin-bottom: 0.35rem;">Select Barangay</label>
            <select class="form-select" id="barangayFilter" onchange="filterByBarangay(this.value)">
                <option value="">All Barangays</option>
                @foreach ($barangays as $barangay)
                <option value="{{ $barangay['id'] }}" {{ $selectedBarangay == $barangay['id'] ? 'selected' : '' }}>
                    {{ $barangay['barangay_name'] }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="search-box" style="margin: 0; margin-top: 1.25rem;">
            <input type="text" class="search-input" placeholder="Search clients..." id="searchInput" value="{{ htmlspecialchars($searchTerm) }}" onkeyup="searchClients()">
        </div>
        <div style="margin-top: 1.25rem;">
            <span class="badge badge-info">{{ count($clients) }} Client(s) Found</span>
        </div>
    </div>
</div>

<!-- Clients Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <span class="card-title">{{ $showArchived ? 'Archived Clients' : 'Client List' }}</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container">
            <table class="data-table" id="clientsTable">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Barangay</th>
                        <th>Contact</th>
                        <th>Plan</th>
                        <th>Rate</th>
                        <th>Installed</th>
                        <th aria-label="Client menu"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                    @php
                        $nameParts = preg_split('/\s+/', trim($client['full_name'])) ?: [];
                        $firstName = array_shift($nameParts) ?: '--';
                        $lastName = count($nameParts) ? array_pop($nameParts) : '--';
                        $middleName = implode(' ', $nameParts) ?: '--';
                    @endphp
                    <tr>
                        <td>{{ htmlspecialchars($firstName) }}</td>
                        <td>{{ htmlspecialchars($middleName) }}</td>
                        <td>{{ htmlspecialchars($lastName) }}</td>
                        <td>{{ $client['barangay_name'] ?: 'N/A' }}</td>
                        <td>{{ $client['contact_number'] }}</td>
                        <td>{{ $client['wifi_plan'] }}</td>
                        <td style="color: var(--success); font-weight: 600;">PHP {{ number_format($client['monthly_rate'], 2) }}</td>
                        <td>{{ $client['installation_date'] ? date('M d, Y', strtotime($client['installation_date'])) : '-' }}</td>
                        <td class="client-menu-cell">
                            <div class="client-action-menu">
                                <button class="client-action-menu-btn" type="button" aria-label="Actions for {{ htmlspecialchars($client['full_name']) }}" aria-expanded="false" onclick="toggleClientActionMenu(this)">&#8942;</button>
                                <div class="client-action-menu-dropdown">
                                @if ($showArchived)
                                    <form method="post" action="">
                                        @csrf
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button type="submit" class="client-menu-item">Restore</button>
                                    </form>
                                    <form method="post" action="" onsubmit="return confirm('Permanently delete this client? This cannot be undone.');">
                                        @csrf
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button type="submit" class="client-menu-item client-menu-item-danger">Delete Permanently</button>
                                    </form>
                                @else
                                    <a href="clients_update.php?id={{ $client['id'] }}" class="client-menu-item">Edit</a>
                                    <button type="button" class="client-menu-item" onclick="viewClient({{ $client['id'] }}); closeClientActionMenus()">View</button>
                                    <form method="post" action="">
                                        @csrf
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button type="submit" class="client-menu-item client-menu-item-danger">Archive</button>
                                    </form>
                                @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @if (empty($clients))
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            @if ($showArchived)
                                No archived clients.
                            @elseif ($selectedBarangayData)
                                No clients found in {{ $selectedBarangayData['barangay_name'] }}.
                                <a href="clients_register.php" style="color: var(--accent);">Register a new client</a>
                            @else
                                No clients found.
                                <a href="clients_register.php" style="color: var(--accent);">Register a new client</a>
                            @endif
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Register Client Modal -->
    <div class="modal-overlay" id="registerClientModal" onclick="if (event.target === this) closeModal('registerClientModal');">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Register New Client</h3>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('registerClientModal')">&times;</button>
            </div>
            <form method="POST" action="" class="cd-body">
                @csrf
                <input type="hidden" name="action" value="register">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-input" placeholder="Juan" required value="{{ old('first_name') }}"></div>
                    <div class="form-group"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-input" placeholder="Santos" value="{{ old('middle_name') }}"></div>
                    <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-input" placeholder="Dela Cruz" required value="{{ old('last_name') }}"></div>
                    <div class="form-group"><label class="form-label">Contact Number *</label><input type="text" name="contact_number" class="form-input" placeholder="09123456789" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" required value="{{ old('contact_number') }}"></div>
                    <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-input" placeholder="juan@email.com" required value="{{ old('email') }}"></div>
                    <div class="form-group"><label class="form-label">WiFi Plan *</label><select name="internet_plan_id" class="form-select" id="registerWifiPlan" required onchange="syncRegisterPlanPrice()"><option value="">Select Plan</option>@foreach ($plans as $plan)<option value="{{ $plan['id'] }}" data-price="{{ $plan['monthly_price'] }}" {{ old('internet_plan_id') == $plan['id'] ? 'selected' : '' }}>{{ $plan['plan_name'] }} ({{ $plan['speed_mbps'] }} Mbps) - ₱{{ number_format($plan['monthly_price'], 2) }}/mo</option>@endforeach</select></div>
                    <div class="form-group"><label class="form-label">Monthly Rate (PHP)</label><input type="number" class="form-input" id="registerMonthlyRate" step="0.01" readonly></div>
                    <div class="form-group"><label class="form-label">Installation Date *</label><input type="date" name="installation_date" class="form-input" value="{{ old('installation_date', date('Y-m-d')) }}" required></div>
                    <div class="form-group"><label class="form-label">Barangay *</label><select name="barangay_id" class="form-select" required><option value="">Select Barangay</option>@foreach ($barangays as $barangay)<option value="{{ $barangay['id'] }}" {{ old('barangay_id') == $barangay['id'] ? 'selected' : '' }}>{{ $barangay['barangay_name'] }}</option>@endforeach</select></div>
                    <div class="form-group"><label class="form-label">Exact Address *</label><input type="text" name="exact_address" class="form-input" placeholder="Purok 1, Sitio..." required value="{{ old('exact_address') }}"></div>
                </div>
                <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('registerClientModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Client</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client details are rendered dynamically via viewClient() (AJAX) to always reflect live billing/payment status -->

    <!-- Client Details Modal -->
    <div class="modal-overlay" id="clientModal" onclick="if (event.target === this) this.classList.remove('active');">
        <div class="modal-content cd-modal modal-large">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Client Details</h3>
                </div>
                <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div id="clientDetails" class="cd-body"></div>
        </div>
    </div>

    <!-- Receipt Print Modal -->
    <div class="modal-overlay" id="receiptModal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.print_company', ['noLogo' => true])
                    <h3 class="modal-title">Client Receipt</h3>
                </div>
                <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div id="receiptContent" style="background: white; color: black; padding: 1.5rem; border-radius: 8px;"></div>
            <div class="receipt-print-actions" style="text-align: center; margin-top: 1rem;">
                <button class="btn btn-primary" type="button" onclick="printClientReceiptContent()">Print Receipt</button>
            </div>
        </div>
    </div>

    <script>
    function syncRegisterPlanPrice() {
        const plan = document.getElementById('registerWifiPlan');
        document.getElementById('registerMonthlyRate').value = plan.options[plan.selectedIndex]?.dataset.price || '';
    }

    @if ($registerClientOpen)
    document.addEventListener('DOMContentLoaded', () => {
        syncRegisterPlanPrice();
        openModal('registerClientModal');
    });
    @endif

    const clients = {!! json_encode(array_map(function ($c) {
        return [
            'id' => $c['id'], 'name' => $c['full_name'],
            'barangay' => $c['barangay_name'], 'contact' => $c['contact_number'],
            'plan' => $c['wifi_plan'], 'rate' => $c['monthly_rate'], 'status' => $c['status']
        ];
    }, $clients), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    // Filter by barangay
    function filterByBarangay(barangayId) {
        const url = new URL(window.location.href);
        if (barangayId) {
            url.searchParams.set('barangay', barangayId);
        } else {
            url.searchParams.delete('barangay');
        }
        url.searchParams.delete('search');
        window.location.href = url.toString();
    }

    // Search clients
    function searchClients() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('clientsTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName('td');
            let found = false;
            for (let j = 0; j < td.length; j++) {
                if (td[j] && td[j].textContent.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            tr[i].style.display = found ? '' : 'none';
        }
    }

    // View client details
    function formatDate(dateString) {
        if (!dateString) return '-';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', options);
    }

    function viewClient(clientId) {
        const client = clients.find(c => String(c.id) === String(clientId));
        if (!client) return;

        fetch('../backend/api/get_client_billing.php?client_id=' + clientId)
            .then(r => r.json())
            .then(data => {
                const bills = data.bills || [];
                const payments = data.payments || [];

                let billsHTML = '';
                if (bills.length > 0) {
                    billsHTML = bills.map(bill => `
                        <tr>
                            <td class="col-bill"><strong>${bill.bill_number}</strong></td>
                            <td class="col-period">${formatDate(bill.billing_period_start)} - ${formatDate(bill.billing_period_end)}</td>
                            <td class="col-amount">PHP ${parseFloat(bill.amount).toFixed(2)}</td>
                            <td class="col-due">${formatDate(bill.due_date)}</td>
                            <td class="col-status"><span class="badge badge-${bill.status === 'paid' ? 'success' : bill.status === 'pending' ? 'warning' : 'danger'}">${bill.status.toUpperCase()}</span></td>
                        </tr>
                    `).join('');
                } else {
                    billsHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1rem;">No billing records</td></tr>';
                }

                let paymentsHTML = '';
                if (payments.length > 0) {
                    paymentsHTML = payments.map(p => `
                        <tr>
                            <td class="col-date">${formatDate(p.payment_date)}</td>
                            <td class="col-bill">${p.bill_number || '-'}</td>
                            <td class="col-amount">PHP ${(p.bill_amount ? parseFloat(p.bill_amount) : 0).toFixed(2)}</td>
                            <td class="col-amount" style="color: var(--success); font-weight: 600;">PHP ${parseFloat(p.amount).toFixed(2)}</td>
                            <td class="col-amount" style="color: ${Number(p.balance || 0) > 0 ? 'var(--warning)' : 'var(--text-muted)'}; font-weight: 600;">${Number(p.balance || 0) > 0 ? `PHP ${parseFloat(p.balance).toFixed(2)}` : '--'}</td>
                            <td class="col-method"><span class="badge badge-info">${p.payment_method.toUpperCase()}</span></td>
                        </tr>
                    `).join('');
                 } else {
                     paymentsHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1rem;">No payment records</td></tr>';
                }

                const details = `
                    <div class="cd-profile">
                        <div class="cd-avatar" style="background: var(--accent);">${client.name.charAt(0).toUpperCase()}</div>
                        <h3 class="cd-name">${client.name}</h3>
                        <span class="badge badge-${client.status === 'active' ? 'success' : client.status === 'inactive' ? 'warning' : 'danger'}">${client.status.toUpperCase()}</span>
                    </div>
                    <div class="cd-fields">
                        <div class="cd-grid-2">
                            <div class="client-detail-field cd-field"><span>Barangay</span><strong>${client.barangay}</strong></div>
                            <div class="client-detail-field cd-field"><span>Contact</span><strong>${client.contact}</strong></div>
                            <div class="client-detail-field cd-field"><span>Plan</span><strong>${client.plan}</strong></div>
                        </div>
                        <div class="client-detail-rate"><span>Monthly Rate</span><strong>PHP ${parseFloat(client.rate).toFixed(2)}</strong></div>
                    </div>
                    <section class="cd-section">
                        <div class="cd-section-title cd-billing">Billing History</div>
                        <div class="cd-table-wrap">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th class="col-bill">Bill #</th>
                                    <th class="col-period">Period</th>
                                    <th class="col-amount">Amount</th>
                                    <th class="col-due">Due Date</th>
                                    <th class="col-status">Status</th>
                                </tr>
                            </thead>
                            <tbody>${billsHTML}</tbody>
                        </table>
                        </div>
                    </section>
                    <section class="cd-section">
                    <div class="cd-section-title cd-payment">Payment History</div>
                    <div class="cd-table-wrap">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th class="col-date">Date</th>
                                    <th class="col-bill">Bill</th>
                                    <th class="col-amount">Bill Amount</th>
                                    <th class="col-amount">Paid Amount</th>
                                    <th class="col-amount">Balance</th>
                                    <th class="col-method">Method</th>
                                </tr>
                            </thead>
                            <tbody>${paymentsHTML}</tbody>
                        </table>
                    </div>
                    </section>
                `;

                document.getElementById('clientDetails').innerHTML = details;
                openModal('clientModal');

            })
            .catch(err => {
                console.error('Error loading client billing:', err);
                // Still show basic client details on error
                const details = `
                    <div class="cd-profile">
                        <div class="cd-avatar" style="background: var(--accent);">${client.name.charAt(0).toUpperCase()}</div>
                        <h3 class="cd-name">${client.name}</h3>
                        <span class="badge badge-${client.status === 'active' ? 'success' : client.status === 'inactive' ? 'warning' : 'danger'}">${client.status.toUpperCase()}</span>
                    </div>
                    <div class="cd-fields">
                        <div class="cd-grid-2">
                            <div class="client-detail-field cd-field"><span>Barangay</span><strong>${client.barangay}</strong></div>
                            <div class="client-detail-field cd-field"><span>Contact</span><strong>${client.contact}</strong></div>
                            <div class="client-detail-field cd-field"><span>Plan</span><strong>${client.plan}</strong></div>
                        </div>
                        <div class="client-detail-rate"><span>Monthly Rate</span><strong>PHP ${parseFloat(client.rate).toFixed(2)}</strong></div>
                    </div>
                    <div class="cd-actions">
                        <a href="bill_inquiry.php?client_id=${client.id}" class="btn btn-primary cd-btn-block">View Records</a>
                    </div>
                `;
                document.getElementById('clientDetails').innerHTML = details;
                openModal('clientModal');
            });
    }

    // Print receipt (open thermal print page)
    function printReceipt(clientId) {
        const url = '/pages/print_client.php?id=' + encodeURIComponent(clientId) + '&format=thermal';
        window.open(url, '_blank');
    }

    function printClientReceiptContent() {
        document.body.classList.add('printing-client-receipt');
        window.print();
    }

    function closeClientActionMenus() {
        document.querySelectorAll('.client-action-menu-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
            if (menu._clientMenuPlaceholder) {
                menu.removeAttribute('style');
                menu._clientMenuPlaceholder.replaceWith(menu);
                delete menu._clientMenuPlaceholder;
            }
        });
        document.querySelectorAll('.client-action-menu-btn[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    }
    function toggleClientActionMenu(button) {
        const menu = button.nextElementSibling;
        const willOpen = !menu.classList.contains('show');
        closeClientActionMenus();
        if (!willOpen) return;
        const placeholder = document.createComment('client action menu');
        menu.parentNode.replaceChild(placeholder, menu);
        menu._clientMenuPlaceholder = placeholder;
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
        if (!event.target.closest('.client-action-menu') && !event.target.closest('.client-action-menu-dropdown')) closeClientActionMenus();
    });

    // Keep row actions available after client details are loaded.
    window.viewClient = viewClient;
    window.printReceipt = printReceipt;
    window.printClientReceiptContent = printClientReceiptContent;
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-client-receipt'));
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
