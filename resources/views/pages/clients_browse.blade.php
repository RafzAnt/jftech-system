<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Browse Clients</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/clients_browse.css') }}?{{ $clientsBrowseCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/client_filter.css') }}?{{ @filemtime(public_path('css/client_filter.css')) ?: 1 }}">
    @if ($canRegisterClients)
    <link rel="stylesheet" href="{{ asset('css/nap_picker.css') }}?{{ @filemtime(public_path('css/nap_picker.css')) ?: 1 }}">
    @endif
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
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $canRegisterClients ? 'View and manage all registered clients' : 'Every branch’s clients. Type a branch, a barangay or a name to narrow them down.' }}</p>
            </div>
            @if ($canRegisterClients)
            <button type="button" class="btn btn-success" onclick="openModal('registerClientModal')">+ Register New Client</button>
            @endif
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

<!-- One search box: a branch, a barangay or a client -->
<form method="get" class="filter-bar card client-filter-card" id="clientFilterForm" style="margin-bottom: 1.5rem;">
    @if ($showArchived)<input type="hidden" name="view" value="archived">@endif
    <div class="card-body">
        @php
            $filterSubject = 'Clients';
            $filterSearchLabel = 'Search Clients';
            $filterIdPrefix = 'client';
            unset($filterDate);
        @endphp
        @include('pages.partials.client_location_filter')
        <div style="margin-top: 0.75rem;"><span class="badge badge-info">{{ count($clients) }} Client(s) Found</span></div>
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
                            @elseif ($searchTerm !== '')
                                No clients found for “{{ $searchTerm }}”.
                            @else
                                No clients found.
                                @if ($canRegisterClients)
                                <button type="button" class="client-register-link" onclick="openModal('registerClientModal')">Register a new client</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

    @if ($canRegisterClients)
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
                    <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-input" placeholder="Juan" required value="{{ $registerValues['first_name'] ?? '' }}"></div>
                    <div class="form-group"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-input" placeholder="Santos" value="{{ $registerValues['middle_name'] ?? '' }}"></div>
                    <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-input" placeholder="Dela Cruz" required value="{{ $registerValues['last_name'] ?? '' }}"></div>
                    <div class="form-group"><label class="form-label">Mobile Number *</label><input type="text" name="contact_number" class="form-input" placeholder="09123456789" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" required value="{{ $registerValues['contact_number'] ?? '' }}"></div>
                    <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-input" placeholder="juan@email.com" required value="{{ $registerValues['email'] ?? '' }}"></div>
                    <div class="form-group"><label class="form-label">WiFi Plan *</label><select name="internet_plan_id" class="form-select" id="registerWifiPlan" required onchange="syncRegisterPlanPrice()"><option value="">Select Plan</option>@foreach ($plans as $plan)<option value="{{ $plan['id'] }}" data-price="{{ $plan['monthly_price'] }}" {{ ($registerValues['internet_plan_id'] ?? '') == $plan['id'] ? 'selected' : '' }}>{{ $plan['plan_name'] }} ({{ $plan['speed_mbps'] }} Mbps) - ₱{{ number_format($plan['monthly_price'], 2) }}/mo</option>@endforeach</select></div>
                    <div class="form-group"><label class="form-label">Monthly Rate (₱)</label><input type="number" class="form-input" id="registerMonthlyRate" step="0.01" readonly></div>
                    <div class="form-group"><label class="form-label">Installation Date *</label><input type="date" name="installation_date" class="form-input" value="{{ $registerValues['installation_date'] ?? date('Y-m-d') }}" required></div>
                    <div class="form-group">
                        <label class="form-label" for="registerBranch">Branch *</label>
                        <input type="text" name="branch_name" id="registerBranch" class="form-input" list="registerBranchSuggestions" placeholder="Type a branch name" autocomplete="off" required value="{{ $registerValues['branch_name'] ?? ($scopeBranch !== null ? $selectedBranch : '') }}" {{ $scopeBranch !== null ? 'readonly' : '' }}>
                        <datalist id="registerBranchSuggestions">
                            @foreach ($branches as $branchName => $psgcCode)
                            <option value="{{ $branchName }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="form-group location-field">
                        <label class="form-label" for="registerBarangay">Barangay *</label>
                        <input type="text" name="registered_barangay_name" id="registerBarangay" class="form-input" placeholder="Select a branch first" autocomplete="off" required value="{{ $registerValues['registered_barangay_name'] ?? '' }}" aria-autocomplete="list" aria-controls="registerBarangaySuggestions" aria-expanded="false" disabled>
                        <div id="registerBarangaySuggestions" class="location-suggestions" role="listbox" hidden></div>
                    </div>
                    <div class="form-group location-field">
                        <label class="form-label" for="registerExactAddress">House No. / Street / Purok / Sitio *</label>
                        <input type="text" name="exact_address" id="registerExactAddress" class="form-input" placeholder="Purok 1, Sitio name" autocomplete="off" aria-autocomplete="list" aria-controls="registerAddressSuggestions" aria-expanded="false" required value="{{ $registerValues['exact_address'] ?? '' }}">
                        <div id="registerAddressSuggestions" class="location-suggestions" role="listbox" hidden></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registerPaymentMode">Mode of Payment (MOP) *</label>
                        <select name="payment_mode" id="registerPaymentMode" class="form-select" required>
                            <option value="">Select mode of payment</option>
                            @foreach (\App\Support\ClientRegistration::PAYMENT_MODES as $modeValue => $modeLabel)
                            <option value="{{ $modeValue }}" {{ ($registerValues['payment_mode'] ?? '') === $modeValue ? 'selected' : '' }}>{{ $modeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registerIpAddress">IP Address</label>
                        <input type="text" name="ip_address" id="registerIpAddress" class="form-input" placeholder="192.168.1.20" maxlength="45" autocomplete="off" value="{{ $registerValues['ip_address'] ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registerFacebook">Facebook Account</label>
                        <input type="text" name="facebook_account" id="registerFacebook" class="form-input" placeholder="Name or link of the Facebook account" maxlength="150" autocomplete="off" value="{{ $registerValues['facebook_account'] ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registerCoordinates">Google Coordinates *</label>
                        <input type="text" name="coordinates" id="registerCoordinates" class="form-input" placeholder="Fills in by itself when the barangay is chosen" maxlength="300" autocomplete="off" required value="{{ $registerValues['coordinates'] ?? '' }}">
                        <small id="registerCoordinatesNote" style="color: var(--text-muted);">Fills in by itself from the barangay (and from the NAP box). To make it exact, copy the spot from Google Maps (press and hold) and paste it here.</small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">NAP Box and Port</label>
                        <input type="hidden" name="nap_port_id" id="registerNapPort" value="{{ $registerValues['nap_port_id'] ?? '' }}">
                        <div id="registerNapPicker" class="nap-picker"></div>
                    </div>
                </div>
                <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('registerClientModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Client</button>
                </div>
            </form>
        </div>
    </div>
    @endif

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

    @if ($canRegisterClients)
    <script src="{{ asset('js/nap_picker.js') }}?{{ @filemtime(public_path('js/nap_picker.js')) ?: 1 }}"></script>
    @endif
    <script>
    // Used by the branch/barangay filters (and by the register form when it exists).
    const registerBarangaysByBranch = @json($branchBarangays, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    @if ($canRegisterClients)
    function syncRegisterPlanPrice() {
        const plan = document.getElementById('registerWifiPlan');
        document.getElementById('registerMonthlyRate').value = plan.options[plan.selectedIndex]?.dataset.price || '';
    }

    const registerBranches = @json($branches, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const branchInput = document.getElementById('registerBranch');
    const barangayInput = document.getElementById('registerBarangay');
    const barangaySuggestions = document.getElementById('registerBarangaySuggestions');
    const addressInput = document.getElementById('registerExactAddress');
    const addressSuggestions = document.getElementById('registerAddressSuggestions');
    const oldBranchValue = branchInput.value.trim();
    const oldBarangayValue = barangayInput.value.trim();
    let restoreOldLocation = true;
    let activeBranchName = '';
    let availableBarangays = [];
    let activeSuggestionIndex = -1;
    let activeAddressSuggestionIndex = -1;

    // After the barangay is chosen, its NAP boxes and ports are offered; a free port can be picked.
    const napPicker = NapPicker.create({
        root: document.getElementById('registerNapPicker'),
        input: document.getElementById('registerNapPort'),
        lookupUrl: @json(route('nap-boxes.lookup')),
        getBarangay: () => {
            const typed = barangayInput.value.trim().toLocaleLowerCase();
            return availableBarangays.find(name => name.toLocaleLowerCase() === typed) || '';
        },
        optional: true,
        // The client is connected to this box, so its place is the best guess of where the client is.
        onPick: (box) => {
            if (box && box.coordinates) setAutoCoordinates(box.coordinates, `Filled in from the NAP box ${box.name} the client is connected to. Move it to the exact house if you can.`);
        },
    });

    // The coordinates fill in by themselves from the place: the barangay first, then the NAP box. They are only replaced while they are
    // empty or were filled in by this page; once somebody types or pastes their own they are left alone.
    const coordinatesInput = document.getElementById('registerCoordinates');
    const coordinatesNote = document.getElementById('registerCoordinatesNote');
    const coordinatesHint = coordinatesNote.textContent;
    let coordinatesAuto = coordinatesInput.value.trim() === '';
    let coordinatesFor = '';
    let coordinatesTicket = 0;

    function setAutoCoordinates(text, note) {
        if (coordinatesInput.value.trim() !== '' && !coordinatesAuto) return;
        coordinatesInput.value = text;
        coordinatesAuto = true;
        coordinatesNote.textContent = note;
    }

    function clearAutoCoordinates() {
        if (!coordinatesAuto) return;
        coordinatesInput.value = '';
        coordinatesNote.textContent = coordinatesHint;
    }

    async function fillCoordinatesFromBarangay() {
        const typed = barangayInput.value.trim().toLocaleLowerCase();
        const barangay = availableBarangays.find(name => name.toLocaleLowerCase() === typed) || '';
        const key = activeBranchName + '|' + barangay;
        if (key === coordinatesFor) return;
        coordinatesFor = key;
        const ticket = ++coordinatesTicket;
        if (!activeBranchName || !barangay) {
            clearAutoCoordinates();
            return;
        }
        if (coordinatesInput.value.trim() !== '' && !coordinatesAuto) return;

        coordinatesNote.textContent = 'Looking up ' + barangay + '…';
        try {
            const url = new URL(@json(route('locations.geocode')), window.location.href);
            url.searchParams.set('branch', activeBranchName);
            url.searchParams.set('barangay', barangay);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('lookup failed');
            const place = await response.json();
            if (ticket !== coordinatesTicket) return;
            if (place.found) {
                const where = place.precision === 'barangay' ? place.label : place.label + ' (the barangay is not on the map, so this is the town)';
                setAutoCoordinates(place.coordinates, 'Filled in from ' + where + '. It is near the client, not exact: choose the NAP box or paste the exact spot from Google Maps.');
            } else {
                clearAutoCoordinates();
                coordinatesNote.textContent = place.error ? 'The map could not be asked now. Paste the coordinates from Google Maps.' : 'The place was not found on the map. Paste the coordinates from Google Maps.';
                if (place.error) coordinatesFor = '';
            }
        } catch (error) {
            if (ticket !== coordinatesTicket) return;
            coordinatesFor = '';
            coordinatesNote.textContent = 'The map could not be asked now. Paste the coordinates from Google Maps.';
        }
    }

    // Typing in the box is the person's own value (the page itself never fires "input" when it fills the box).
    coordinatesInput.addEventListener('input', () => {
        coordinatesAuto = false;
        coordinatesNote.textContent = coordinatesHint;
    });

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
                napPicker.refresh();
                fillCoordinatesFromBarangay();
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
        napPicker.refresh();
        fillCoordinatesFromBarangay();
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
    barangayInput.addEventListener('input', () => { showBarangaySuggestions(); napPicker.refresh(); fillCoordinatesFromBarangay(); });
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
        // openModal() is only defined once jftech-main.js has run its own DOMContentLoaded, which comes after this one.
        document.getElementById('registerClientModal').classList.add('active');
    });
    @endif
    @endif

    const clients = {!! json_encode(array_map(function ($c) {
        return [
            'id' => $c['id'], 'name' => $c['full_name'],
            'branch' => $c['branch_name'] ?: 'N/A', 'barangay' => $c['barangay_name'] ?: 'N/A', 'contact' => $c['contact_number'],
            'plan' => $c['wifi_plan'], 'rate' => $c['monthly_rate'], 'status' => $c['status'],
            'nap' => ! empty($c['nap_box_name']) ? $c['nap_box_name'] . ' · Port ' . $c['nap_port_number'] : null,
            'mop' => \App\Support\ClientRegistration::PAYMENT_MODES[$c['payment_mode'] ?? ''] ?? null,
            'ip' => $c['ip_address'] ?? null, 'facebook' => $c['facebook_account'] ?? null,
            'coordinates' => \App\Support\Coordinates::format($c['latitude'] ?? null, $c['longitude'] ?? null) ?: null,
            'mapUrl' => \App\Support\Coordinates::mapUrl($c['latitude'] ?? null, $c['longitude'] ?? null),
        ];
    }, $clients), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

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
                            <div class="client-detail-field cd-field"><span>NAP Box / Port</span><strong>${escapeHtml(client.nap || 'Not connected')}</strong></div>
                            <div class="client-detail-field cd-field"><span>Mode of Payment</span><strong>${escapeHtml(client.mop || '—')}</strong></div>
                            <div class="client-detail-field cd-field"><span>IP Address</span><strong>${escapeHtml(client.ip || '—')}</strong></div>
                            <div class="client-detail-field cd-field"><span>Facebook Account</span><strong>${escapeHtml(client.facebook || '—')}</strong></div>
                            <div class="client-detail-field cd-field"><span>Google Coordinates</span><strong>${client.mapUrl ? `<a href="${escapeHtml(client.mapUrl)}" target="_blank" rel="noopener">${escapeHtml(client.coordinates)}</a>` : '—'}</strong></div>
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
