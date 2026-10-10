<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Browse Clients</title>
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

<!-- Branch, barangay, and client search filters -->
<form method="get" class="filter-bar card client-filter-card" id="clientFilterForm" style="margin-bottom: 1.5rem;">
    @if ($showArchived)<input type="hidden" name="view" value="archived">@endif
    <div class="card-body client-filter-controls">
        <div class="form-group client-filter-field client-location-field">
            <label class="form-label" for="clientLocationInput">Branch, Barangay</label>
            <input type="hidden" id="clientBranchFilter" name="branch" value="{{ $selectedBranch }}">
            <div class="client-barangay-picker">
                <input type="text" class="search-input client-barangay-input" id="clientLocationInput" placeholder="Type a branch name" autocomplete="off" aria-autocomplete="list" aria-controls="clientBarangaySuggestions" aria-expanded="false" value="{{ $selectedBranch ? $selectedBranch . ', ' . implode(', ', $selectedBarangays) : '' }}">
                <div id="clientBarangaySuggestions" class="location-suggestions client-filter-suggestions" role="listbox" hidden></div>
                <div id="clientBarangayHiddenInputs"></div>
            </div>
            <small class="location-input-help">Choose a branch first, then choose one or more barangays.</small>
        </div>
        <div class="form-group client-filter-field client-search-field">
            <label class="form-label" for="searchInput">Search Clients</label>
            <input type="text" class="search-input client-filter-text-input" placeholder="Name, client code, or mobile number" id="searchInput" name="search" value="{{ $searchTerm }}">
        </div>
        <div class="client-filter-submit">
            <button type="submit" class="btn btn-primary">Search</button>
            <span class="badge badge-info">{{ count($clients) }} Client(s) Found</span>
        </div>
    </div>
</form>

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
                        <th>Branch</th>
                        <th>Barangay</th>
                        <th>Mobile Number</th>
                        <th>Plan</th>
                        <th>Monthly Rate</th>
                        <th>Installation Date</th>
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
                        <td>{{ $client['branch_name'] ?: 'N/A' }}</td>
                        <td>{{ $client['barangay_name'] ?: 'N/A' }}</td>
                        <td>{{ $client['contact_number'] }}</td>
                        <td>{{ $client['wifi_plan'] }}</td>
                        <td style="color: var(--success); font-weight: 600;">₱{{ number_format($client['monthly_rate'], 2) }}</td>
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
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            @if ($showArchived)
                                No archived clients.
                            @elseif ($selectedBranch)
                                No clients found in {{ $selectedBranch }}.
                                <button type="button" class="client-register-link" onclick="openModal('registerClientModal')">Register a new client</button>
                            @else
                                No clients found.
                                <button type="button" class="client-register-link" onclick="openModal('registerClientModal')">Register a new client</button>
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
    <div class="modal-overlay" id="registerClientModal" role="dialog" aria-modal="true" aria-labelledby="registerClientModalTitle" onclick="if (event.target === this) closeModal('registerClientModal');">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title" id="registerClientModalTitle">Register New Client</h3>
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
                    <div class="form-group"><label class="form-label">Mobile Number *</label><input type="text" name="contact_number" class="form-input" placeholder="09123456789" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" required value="{{ old('contact_number') }}"></div>
                    <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-input" placeholder="juan@email.com" required value="{{ old('email') }}"></div>
                    <div class="form-group"><label class="form-label">WiFi Plan *</label><select name="internet_plan_id" class="form-select" id="registerWifiPlan" required onchange="syncRegisterPlanPrice()"><option value="">Select Plan</option>@foreach ($plans as $plan)<option value="{{ $plan['id'] }}" data-price="{{ $plan['monthly_price'] }}" {{ old('internet_plan_id') == $plan['id'] ? 'selected' : '' }}>{{ $plan['plan_name'] }} ({{ $plan['speed_mbps'] }} Mbps) - ₱{{ number_format($plan['monthly_price'], 2) }}/mo</option>@endforeach</select></div>
                    <div class="form-group"><label class="form-label">Monthly Rate (₱)</label><input type="number" class="form-input" id="registerMonthlyRate" step="0.01" readonly></div>
                    <div class="form-group"><label class="form-label">Installation Date *</label><input type="date" name="installation_date" class="form-input" value="{{ old('installation_date', date('Y-m-d')) }}" required></div>
                    <div class="form-group">
                        <label class="form-label" for="registerBranch">Branch *</label>
                        <input type="text" name="branch_name" id="registerBranch" class="form-input" list="registerBranchSuggestions" placeholder="Type a branch name" autocomplete="off" required value="{{ old('branch_name') }}">
                        <datalist id="registerBranchSuggestions">
                            @foreach ($branches as $branchName => $psgcCode)
                            <option value="{{ $branchName }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="form-group location-field">
                        <label class="form-label" for="registerBarangay">Barangay *</label>
                        <input type="text" name="registered_barangay_name" id="registerBarangay" class="form-input" placeholder="Select a branch first" autocomplete="off" required value="{{ old('registered_barangay_name') }}" aria-autocomplete="list" aria-controls="registerBarangaySuggestions" aria-expanded="false" disabled>
                        <div id="registerBarangaySuggestions" class="location-suggestions" role="listbox" hidden></div>
                        <small id="registerBarangayHelp" class="location-input-help" aria-live="polite">Type and select a branch to see barangay suggestions.</small>
                    </div>
                    <div class="form-group location-field">
                        <label class="form-label" for="registerExactAddress">House No. / Street / Purok / Sitio *</label>
                        <input type="text" name="exact_address" id="registerExactAddress" class="form-input" placeholder="Purok 1, Sitio name" autocomplete="off" aria-autocomplete="list" aria-controls="registerAddressSuggestions" aria-expanded="false" required value="{{ old('exact_address') }}">
                        <div id="registerAddressSuggestions" class="location-suggestions" role="listbox" hidden></div>
                        <small id="registerAddressHelp" class="location-input-help" aria-live="polite">Choose a purok or type a sitio name.</small>
                    </div>
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

    const registerBranches = @json($branches, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const registerBarangaysByBranch = @json($branchBarangays, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const branchInput = document.getElementById('registerBranch');
    const barangayInput = document.getElementById('registerBarangay');
    const barangaySuggestions = document.getElementById('registerBarangaySuggestions');
    const barangayHelp = document.getElementById('registerBarangayHelp');
    const addressInput = document.getElementById('registerExactAddress');
    const addressSuggestions = document.getElementById('registerAddressSuggestions');
    const oldBranchValue = branchInput.value.trim();
    const oldBarangayValue = barangayInput.value.trim();
    let restoreOldLocation = true;
    let activeBranchName = '';
    let availableBarangays = [];
    let activeSuggestionIndex = -1;
    let activeAddressSuggestionIndex = -1;

    function closeBarangaySuggestions() {
        barangaySuggestions.hidden = true;
        barangayInput.setAttribute('aria-expanded', 'false');
        barangayInput.removeAttribute('aria-activedescendant');
        activeSuggestionIndex = -1;
    }

    function showBarangaySuggestions() {
        const query = barangayInput.value.trim().toLocaleLowerCase();
        const matches = availableBarangays.filter(name => !query || name.toLocaleLowerCase().startsWith(query));
        barangaySuggestions.replaceChildren();
        activeSuggestionIndex = -1;

        matches.slice(0, 30).forEach((name, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'location-suggestion-option';
            option.id = `registerBarangayOption${index}`;
            option.setAttribute('role', 'option');
            option.textContent = name;
            option.addEventListener('mousedown', event => event.preventDefault());
            option.addEventListener('click', () => {
                barangayInput.value = name;
                closeBarangaySuggestions();
            });
            barangaySuggestions.appendChild(option);
        });

        barangaySuggestions.hidden = matches.length === 0;
        barangayInput.setAttribute('aria-expanded', String(matches.length > 0));
        if (matches.length > 0) barangayInput.removeAttribute('aria-activedescendant');
    }

    function matchingBranch(value) {
        const branchName = Object.keys(registerBranches).find(name => name.toLowerCase() === value.trim().toLowerCase());
        return branchName ? { name: branchName, apiName: registerBranches[branchName] } : null;
    }

    function loadBarangaySuggestions() {
        const branch = matchingBranch(branchInput.value);
        if (!branch) {
            activeBranchName = '';
            barangaySuggestions.replaceChildren();
            availableBarangays = [];
            closeBarangaySuggestions();
            barangayInput.value = '';
            barangayInput.disabled = true;
            barangayInput.placeholder = 'Select a branch first';
            barangayHelp.textContent = 'Type and select a branch to see barangay suggestions.';
            return;
        }

        branchInput.value = branch.name;
        if (activeBranchName === branch.name) return;
        const restoreBarangay = restoreOldLocation && oldBranchValue.toLowerCase() === branch.name.toLowerCase();
        restoreOldLocation = false;
        activeBranchName = branch.name;
        barangaySuggestions.replaceChildren();
        closeBarangaySuggestions();
        barangayInput.value = '';
        availableBarangays = registerBarangaysByBranch[branch.name] || [];
        if (restoreBarangay && oldBarangayValue && availableBarangays.some(name => name.toLowerCase() === oldBarangayValue.toLowerCase())) {
            barangayInput.value = availableBarangays.find(name => name.toLowerCase() === oldBarangayValue.toLowerCase());
        }
        barangayInput.disabled = availableBarangays.length === 0;
        barangayInput.placeholder = availableBarangays.length ? 'Type a barangay name' : 'No barangays listed';
        barangayHelp.textContent = availableBarangays.length
            ? `Barangays in ${branch.name}. Type one letter to see suggestions.`
            : `No barangays listed for ${branch.name}.`;
    }

    function closeAddressSuggestions() {
        addressSuggestions.hidden = true;
        addressInput.setAttribute('aria-expanded', 'false');
        addressInput.removeAttribute('aria-activedescendant');
        activeAddressSuggestionIndex = -1;
    }

    function showAddressSuggestions() {
        const fullValue = addressInput.value;
        const query = fullValue.split(',').pop().trim().toLocaleLowerCase();
        const hasAddressPrefix = fullValue.includes(',');
        const options = [
            ...(!hasAddressPrefix ? Array.from({ length: 20 }, (_, index) => `Purok ${index + 1}`) : []),
            ...(query.startsWith('sitio') ? ['Continue sitio name'] : [`Sitio${query ? ` ${query}` : ' (type name)'}`])
        ].filter(option => !query || option.toLocaleLowerCase().startsWith(query) || option.startsWith('Continue sitio'));

        addressSuggestions.replaceChildren();
        activeAddressSuggestionIndex = -1;
        options.forEach((label, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'location-suggestion-option';
            option.id = `registerAddressOption${index}`;
            option.setAttribute('role', 'option');
            option.textContent = label;
            option.addEventListener('mousedown', event => event.preventDefault());
            option.addEventListener('click', () => {
                const prefix = fullValue.includes(',') ? `${fullValue.substring(0, fullValue.lastIndexOf(',') + 1)} ` : '';
                if (label === 'Continue sitio name') {
                    closeAddressSuggestions();
                    return;
                }
                addressInput.value = label.startsWith('Sitio') ? `${prefix}${label}` : `${prefix}${label}, `;
                addressInput.focus();
                closeAddressSuggestions();
            });
            addressSuggestions.appendChild(option);
        });

        addressSuggestions.hidden = options.length === 0;
        addressInput.setAttribute('aria-expanded', String(options.length > 0));
    }

    branchInput.addEventListener('input', loadBarangaySuggestions);
    branchInput.addEventListener('change', loadBarangaySuggestions);
    barangayInput.addEventListener('input', showBarangaySuggestions);
    barangayInput.addEventListener('focus', showBarangaySuggestions);
    barangayInput.addEventListener('blur', () => setTimeout(closeBarangaySuggestions, 120));
    barangayInput.addEventListener('keydown', event => {
        const options = barangaySuggestions.querySelectorAll('.location-suggestion-option');
        if (!options.length || barangaySuggestions.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeSuggestionIndex = activeSuggestionIndex === -1
                ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                : (activeSuggestionIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeSuggestionIndex)));
            barangayInput.setAttribute('aria-activedescendant', options[activeSuggestionIndex].id);
        } else if (event.key === 'Enter' && activeSuggestionIndex >= 0) {
            event.preventDefault();
            options[activeSuggestionIndex].click();
        } else if (event.key === 'Escape') {
            closeBarangaySuggestions();
        }
    });
    addressInput.addEventListener('input', showAddressSuggestions);
    addressInput.addEventListener('focus', showAddressSuggestions);
    addressInput.addEventListener('blur', () => setTimeout(closeAddressSuggestions, 120));
    addressInput.addEventListener('keydown', event => {
        const options = addressSuggestions.querySelectorAll('.location-suggestion-option');
        if (!options.length || addressSuggestions.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeAddressSuggestionIndex = activeAddressSuggestionIndex === -1
                ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                : (activeAddressSuggestionIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeAddressSuggestionIndex)));
            addressInput.setAttribute('aria-activedescendant', options[activeAddressSuggestionIndex].id);
        } else if (event.key === 'Enter' && activeAddressSuggestionIndex >= 0) {
            event.preventDefault();
            options[activeAddressSuggestionIndex].click();
        } else if (event.key === 'Escape') {
            closeAddressSuggestions();
        }
    });
    loadBarangaySuggestions();

    @if ($registerClientOpen)
    document.addEventListener('DOMContentLoaded', () => {
        syncRegisterPlanPrice();
        openModal('registerClientModal');
    });
    @endif

    const clients = {!! json_encode(array_map(function ($c) {
        return [
            'id' => $c['id'], 'name' => $c['full_name'],
            'branch' => $c['branch_name'] ?: 'N/A', 'barangay' => $c['barangay_name'] ?: 'N/A', 'contact' => $c['contact_number'],
            'plan' => $c['wifi_plan'], 'rate' => $c['monthly_rate'], 'status' => $c['status']
        ];
    }, $clients), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    const clientBranchFilter = document.getElementById('clientBranchFilter');
    const clientLocationInput = document.getElementById('clientLocationInput');
    const clientBarangayHiddenInputs = document.getElementById('clientBarangayHiddenInputs');
    const clientBarangaySuggestions = document.getElementById('clientBarangaySuggestions');
    const selectedClientBarangays = @json($selectedBarangays, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const clientBranchNames = @json(array_keys($branches), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    let clientBarangaySuggestionIndex = -1;
    let currentClientBranch = clientBranchFilter.value;

    function closeClientBarangaySuggestions() {
        clientBarangaySuggestions.hidden = true;
        clientLocationInput.setAttribute('aria-expanded', 'false');
        clientLocationInput.removeAttribute('aria-activedescendant');
        clientBarangaySuggestionIndex = -1;
    }

    function renderClientBarangayInputs() {
        clientBarangayHiddenInputs.replaceChildren();
        selectedClientBarangays.forEach(name => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'barangays[]';
            hidden.value = name;
            clientBarangayHiddenInputs.appendChild(hidden);
        });
    }

    function updateClientLocationValue(query = '') {
        const parts = currentClientBranch ? [currentClientBranch, ...selectedClientBarangays] : [];
        clientLocationInput.value = parts.length ? `${parts.join(', ')}, ${query}` : query;
    }

    function showClientLocationSuggestions() {
        const value = clientLocationInput.value;
        const typedParts = value.split(',');
        let query = '';
        let options = [];
        if (!currentClientBranch) {
            query = (typedParts[0] || '').trim().toLocaleLowerCase();
            options = clientBranchNames.filter(name => !query || name.toLocaleLowerCase().startsWith(query));
        } else {
            const prefix = `${currentClientBranch},`;
            if (!value.toLocaleLowerCase().startsWith(prefix.toLocaleLowerCase())) {
                currentClientBranch = '';
                clientBranchFilter.value = '';
                selectedClientBarangays.splice(0);
                renderClientBarangayInputs();
                showClientLocationSuggestions();
                return;
            }
            const afterBranch = value.slice(prefix.length).split(',');
            const typedBarangays = afterBranch.slice(0, -1).map(name => name.trim()).filter(Boolean);
            query = (afterBranch[afterBranch.length - 1] || '').trim().toLocaleLowerCase();
            const selected = new Set([...selectedClientBarangays, ...typedBarangays].map(name => name.toLocaleLowerCase()));
            options = (registerBarangaysByBranch[currentClientBranch] || [])
                .filter(name => !selected.has(name.toLocaleLowerCase()) && (!query || name.toLocaleLowerCase().startsWith(query)));
        }
        options = options.slice(0, 30);
        clientBarangaySuggestions.replaceChildren();
        clientBarangaySuggestionIndex = -1;

        options.forEach((name, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'location-suggestion-option';
            option.id = `clientBarangayOption${index}`;
            option.setAttribute('role', 'option');
            option.textContent = name;
            option.addEventListener('mousedown', event => event.preventDefault());
            option.addEventListener('click', () => {
                if (!currentClientBranch) {
                    currentClientBranch = name;
                    clientBranchFilter.value = name;
                    selectedClientBarangays.splice(0);
                } else {
                    selectedClientBarangays.push(name);
                    renderClientBarangayInputs();
                }
                updateClientLocationValue();
                clientLocationInput.focus();
                closeClientBarangaySuggestions();
                showClientLocationSuggestions();
            });
            clientBarangaySuggestions.appendChild(option);
        });

        clientBarangaySuggestions.hidden = options.length === 0;
        clientLocationInput.setAttribute('aria-expanded', String(options.length > 0));
    }

    clientLocationInput.addEventListener('input', showClientLocationSuggestions);
    clientLocationInput.addEventListener('focus', showClientLocationSuggestions);
    clientLocationInput.addEventListener('blur', () => setTimeout(closeClientBarangaySuggestions, 120));
    clientLocationInput.addEventListener('keydown', event => {
        if (event.key === ',' || event.key === 'Enter') {
            const currentPart = clientLocationInput.value.split(',').pop().trim();
            if (!currentClientBranch) {
                const branchMatch = clientBranchNames.find(name => name.toLocaleLowerCase() === currentPart.toLocaleLowerCase());
                if (branchMatch) {
                    event.preventDefault();
                    currentClientBranch = branchMatch;
                    clientBranchFilter.value = branchMatch;
                    updateClientLocationValue();
                    showClientLocationSuggestions();
                    return;
                }
            } else {
                const barangayMatch = (registerBarangaysByBranch[currentClientBranch] || [])
                    .find(name => name.toLocaleLowerCase() === currentPart.toLocaleLowerCase());
                if (barangayMatch && !selectedClientBarangays.some(name => name.toLocaleLowerCase() === barangayMatch.toLocaleLowerCase())) {
                    event.preventDefault();
                    selectedClientBarangays.push(barangayMatch);
                    renderClientBarangayInputs();
                    updateClientLocationValue();
                    showClientLocationSuggestions();
                    return;
                }
            }
        }
        const options = clientBarangaySuggestions.querySelectorAll('.location-suggestion-option');
        if (!options.length || clientBarangaySuggestions.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            clientBarangaySuggestionIndex = clientBarangaySuggestionIndex === -1
                ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                : (clientBarangaySuggestionIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === clientBarangaySuggestionIndex)));
            clientLocationInput.setAttribute('aria-activedescendant', options[clientBarangaySuggestionIndex].id);
        } else if (event.key === 'Enter' && clientBarangaySuggestionIndex >= 0) {
            event.preventDefault();
            options[clientBarangaySuggestionIndex].click();
        } else if (event.key === 'Escape') {
            closeClientBarangaySuggestions();
        }
    });
    renderClientBarangayInputs();
    if (currentClientBranch) updateClientLocationValue();

    // View client details
    function formatDate(dateString) {
        if (!dateString) return '-';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-PH', options);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
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
                            <td class="col-amount">₱${parseFloat(bill.amount).toFixed(2)}</td>
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
                            <td class="col-amount">₱${(p.bill_amount ? parseFloat(p.bill_amount) : 0).toFixed(2)}</td>
                            <td class="col-amount" style="color: var(--success); font-weight: 600;">₱${parseFloat(p.amount).toFixed(2)}</td>
                            <td class="col-amount" style="color: ${Number(p.balance || 0) > 0 ? 'var(--warning)' : 'var(--text-muted)'}; font-weight: 600;">${Number(p.balance || 0) > 0 ? `₱${parseFloat(p.balance).toFixed(2)}` : '--'}</td>
                            <td class="col-method"><span class="badge badge-info">${p.payment_method.toUpperCase()}</span></td>
                        </tr>
                    `).join('');
                 } else {
                     paymentsHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1rem;">No payment records</td></tr>';
                }

                const details = `
                    <div class="cd-profile">
                        <div class="cd-avatar" style="background: var(--accent);">${escapeHtml(client.name.charAt(0).toUpperCase())}</div>
                        <h3 class="cd-name">${escapeHtml(client.name)}</h3>
                        <span class="badge badge-${client.status === 'active' ? 'success' : client.status === 'inactive' ? 'warning' : 'danger'}">${client.status.toUpperCase()}</span>
                    </div>
                    <div class="cd-fields">
                        <div class="cd-grid-2">
                            <div class="client-detail-field cd-field"><span>Barangay</span><strong>${escapeHtml(client.barangay)}</strong></div>
                            <div class="client-detail-field cd-field"><span>Branch</span><strong>${escapeHtml(client.branch)}</strong></div>
                            <div class="client-detail-field cd-field"><span>Mobile Number</span><strong>${escapeHtml(client.contact)}</strong></div>
                            <div class="client-detail-field cd-field"><span>Plan</span><strong>${escapeHtml(client.plan)}</strong></div>
                        </div>
                        <div class="client-detail-rate"><span>Monthly Rate</span><strong>₱${parseFloat(client.rate).toFixed(2)}</strong></div>
                    </div>
                    <section class="cd-section">
                        <div class="cd-section-title cd-billing">Billing History</div>
                        <div class="cd-table-wrap">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th class="col-bill">Bill #</th>
                                    <th class="col-period">Billing Period</th>
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
                                    <th class="col-method">Payment Method</th>
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
                        <div class="cd-avatar" style="background: var(--accent);">${escapeHtml(client.name.charAt(0).toUpperCase())}</div>
                        <h3 class="cd-name">${escapeHtml(client.name)}</h3>
                        <span class="badge badge-${client.status === 'active' ? 'success' : client.status === 'inactive' ? 'warning' : 'danger'}">${client.status.toUpperCase()}</span>
                    </div>
                    <div class="cd-fields">
                        <div class="cd-grid-2">
                            <div class="client-detail-field cd-field"><span>Barangay</span><strong>${escapeHtml(client.barangay)}</strong></div>
                            <div class="client-detail-field cd-field"><span>Branch</span><strong>${escapeHtml(client.branch)}</strong></div>
                            <div class="client-detail-field cd-field"><span>Mobile Number</span><strong>${escapeHtml(client.contact)}</strong></div>
                            <div class="client-detail-field cd-field"><span>Plan</span><strong>${escapeHtml(client.plan)}</strong></div>
                        </div>
                        <div class="client-detail-rate"><span>Monthly Rate</span><strong>₱${parseFloat(client.rate).toFixed(2)}</strong></div>
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
