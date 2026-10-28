<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Installations</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/inventory_requests.css') }}?{{ @filemtime(public_path('css/inventory_requests.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/nap_picker.css') }}?{{ $napPickerCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/installations.css') }}?{{ $installationsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>

    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Inventory · Installations</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $canDecide ? 'Check what your technicians installed, fix any detail, then confirm: the client is added and the inventory is updated' : 'Installation reports of the technicians, by branch' }}</p>
            </div>
            <div style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:center;">
                @if (! \App\Support\BranchScope::isRestricted())
                <form method="GET" action="" class="dashboard-branch-form">
                    <input type="hidden" name="status" value="{{ $filterStatus }}">
                    <label class="form-label" for="installBranch" style="margin:0;">Branch</label>
                    <select name="branch" id="installBranch" class="form-select" onchange="this.form.submit()" style="width:auto;min-width:170px;">
                        <option value="">All branches</option>
                        @foreach ($inventoryBranches as $installBranch)
                        <option value="{{ $installBranch }}" {{ $branch === $installBranch ? 'selected' : '' }}>{{ $installBranch }}</option>
                        @endforeach
                    </select>
                </form>
                @else
                <span class="badge badge-info">{{ $branch ?: 'No branch assigned' }} branch</span>
                @endif
                @if ($counts['submitted'] > 0)
                <span class="badge badge-warning">{{ $counts['submitted'] }} Waiting</span>
                @endif
            </div>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        @php
            $tabQuery = ! \App\Support\BranchScope::isRestricted() && $branch ? '&branch=' . urlencode($branch) : '';
        @endphp
        <div class="filter-tabs">
            <a href="installations.php?status=submitted{{ $tabQuery }}" class="filter-tab {{ $filterStatus === 'submitted' ? 'active' : '' }}">Waiting for admin @if ($counts['submitted'] > 0)({{ $counts['submitted'] }})@endif</a>
            <a href="installations.php?status=confirmed{{ $tabQuery }}" class="filter-tab {{ $filterStatus === 'confirmed' ? 'active' : '' }}">Confirmed ({{ $counts['confirmed'] }})</a>
            <a href="installations.php?status=rejected{{ $tabQuery }}" class="filter-tab {{ $filterStatus === 'rejected' ? 'active' : '' }}">Rejected ({{ $counts['rejected'] }})</a>
            <a href="installations.php?status=all{{ $tabQuery }}" class="filter-tab {{ $filterStatus === 'all' ? 'active' : '' }}">All</a>
        </div>

        @forelse ($reports as $report)
        <div class="card install-card" id="report-{{ $report['id'] }}">
            <div class="card-header install-card-head">
                <div>
                    <span class="card-title">Installation {{ $report['code'] }} · {{ $report['client_name'] }}</span>
                    <div class="install-meta">
                        {{ $report['employee_name'] ?: 'Employee' }}
                        @if (! \App\Support\BranchScope::isRestricted()) · {{ $report['branch_name'] }}@endif
                        · {{ \Carbon\Carbon::parse($report['submitted_at'])->format('M d, Y h:i A') }}
                    </div>
                </div>
                <span class="status-badge status-{{ $report['status'] === 'submitted' ? 'pending' : ($report['status'] === 'confirmed' ? 'approved' : 'denied') }}">{{ $report['status_label'] }}</span>
            </div>
            <div class="card-body">
                <dl class="install-facts">
                    <div><dt>Contact</dt><dd>{{ $report['contact_number'] }}@if ($report['email']) · {{ $report['email'] }}@endif</dd></div>
                    <div><dt>Address</dt><dd>{{ $report['exact_address'] }}, {{ $report['barangay_name'] }}</dd></div>
                    <div><dt>Plan</dt><dd>{{ $report['plan_name'] ?: '—' }}@if ($report['monthly_price'] !== null) · ₱{{ number_format($report['monthly_price'], 2) }}/mo @endif</dd></div>
                    <div><dt>Mode of payment</dt><dd>{{ $report['payment_mode_label'] }}</dd></div>
                    <div><dt>IP address</dt><dd>{{ $report['ip_address'] ?: '—' }}</dd></div>
                    <div><dt>Facebook</dt><dd>{{ $report['facebook_account'] ?: '—' }}</dd></div>
                    <div><dt>Google coordinates</dt><dd><a href="{{ $report['map_url'] }}" target="_blank" rel="noopener">{{ $report['coordinates'] }}</a></dd></div>
                    <div><dt>NAP box / port</dt><dd>{{ $report['nap_box_name'] ? $report['nap_box_name'] . ' · Port ' . $report['port_number'] : '—' }}</dd></div>
                    <div><dt>Installed on</dt><dd>{{ \Carbon\Carbon::parse($report['installation_date'])->format('M d, Y') }}</dd></div>
                    @if ($report['client_code'])
                    <div><dt>Client</dt><dd><a href="{{ url('/clients') }}?search={{ urlencode($report['client_code']) }}">{{ $report['client_code'] }}</a></dd></div>
                    @endif
                </dl>

                <div class="install-lists">
                    <div>
                        <strong>Items taken</strong>
                        @forelse ($report['taken'] as $line)
                        <div class="install-line">{{ $line['quantity'] }} × {{ $line['item_name'] }} <span class="install-muted">(request #{{ $line['id'] }})</span></div>
                        @empty
                        <div class="install-muted">None linked to this report.</div>
                        @endforelse
                    </div>
                    <div>
                        <strong>Items brought back</strong>
                        @forelse ($report['returns'] as $line)
                        <div class="install-line">
                            {{ $line['quantity'] }} × {{ $line['item_name'] }}
                            <span class="install-muted">({{ $line['item_condition'] ?: 'Good' }}@if ($line['accepted_quantity'] !== null) · {{ $line['accepted_quantity'] }} put back in stock @endif)</span>
                        </div>
                        @empty
                        <div class="install-muted">Nothing brought back.</div>
                        @endforelse
                    </div>
                </div>

                @if ($report['remarks'])
                <p class="install-note"><strong>Technician's remarks:</strong> {{ $report['remarks'] }}</p>
                @endif
                @if ($report['status'] !== 'submitted' && $report['admin_remarks'])
                <p class="install-note"><strong>Admin's remarks:</strong> {{ $report['admin_remarks'] }}</p>
                @endif
                @if ($report['status'] !== 'submitted' && $report['reviewed_at'])
                <p class="install-muted">{{ $report['status_label'] }} {{ $report['reviewer_name'] ? 'by ' . $report['reviewer_name'] : '' }} on {{ \Carbon\Carbon::parse($report['reviewed_at'])->format('M d, Y h:i A') }}</p>
                @endif

                @if ($report['status'] === 'submitted')
                <div class="install-actions">
                    @if ($canDecide)
                    <button type="button" class="btn btn-primary" onclick="openReview({{ $report['id'] }})">Review &amp; Confirm</button>
                    @else
                    <span class="install-muted">Waiting for the branch admin.</span>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="card"><div class="card-body" style="text-align:center;color:var(--text-muted);padding:2rem;">
            {{ $filterStatus === 'submitted' ? 'No installation report is waiting for you.' : 'No installation reports found.' }}
        </div></div>
        @endforelse
    </div>

    @if ($canDecide)
    @foreach ($reports as $report)
    @if ($report['status'] === 'submitted')
    <div class="modal-overlay" id="reviewModal{{ $report['id'] }}" onclick="if (event.target === this) closeModal('reviewModal{{ $report['id'] }}');">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Check Installation {{ $report['code'] }}</h3>
                </div>
                <button class="modal-close" type="button" onclick="closeModal('reviewModal{{ $report['id'] }}')">&times;</button>
            </div>
            <form method="POST" action="" id="reviewForm{{ $report['id'] }}" class="cd-body">
                @csrf
                <input type="hidden" name="installation_id" value="{{ $report['id'] }}">
                <p class="install-muted" style="margin-top:0;">Reported by {{ $report['employee_name'] }}. Fix anything that is wrong, then confirm to add the client.</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-input" maxlength="100" required value="{{ $report['client_name'] }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile Number *</label>
                        <input type="text" name="contact_number" class="form-input" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" required value="{{ $report['contact_number'] }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" maxlength="100" value="{{ $report['email'] }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">WiFi Plan *</label>
                        <select name="internet_plan_id" class="form-select" required>
                            <option value="">Select Plan</option>
                            @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" {{ (int) $report['internet_plan_id'] === (int) $plan->id ? 'selected' : '' }}>{{ $plan->plan_name }} ({{ $plan->speed_mbps }} Mbps) - ₱{{ number_format($plan->monthly_price, 2) }}/mo</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mode of Payment (MOP) *</label>
                        <select name="payment_mode" class="form-select" required>
                            @foreach ($paymentModes as $modeValue => $modeLabel)
                            <option value="{{ $modeValue }}" {{ $report['payment_mode'] === $modeValue ? 'selected' : '' }}>{{ $modeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Installation Date *</label>
                        <input type="date" name="installation_date" class="form-input" required value="{{ $report['installation_date'] }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Barangay *</label>
                        @if ($branchBarangays !== [])
                        <select name="barangay_name" class="form-select" required>
                            @if (! in_array($report['barangay_name'], $branchBarangays, true))
                            <option value="{{ $report['barangay_name'] }}" selected>{{ $report['barangay_name'] }}</option>
                            @endif
                            @foreach ($branchBarangays as $barangayOption)
                            <option value="{{ $barangayOption }}" {{ $report['barangay_name'] === $barangayOption ? 'selected' : '' }}>{{ $barangayOption }}</option>
                            @endforeach
                        </select>
                        @else
                        <input type="text" name="barangay_name" class="form-input" maxlength="100" required value="{{ $report['barangay_name'] }}">
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label">House No. / Street / Purok / Sitio *</label>
                        <input type="text" name="exact_address" class="form-input" maxlength="255" required value="{{ $report['exact_address'] }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="ip_address" class="form-input" maxlength="45" placeholder="192.168.1.20" value="{{ $report['ip_address'] }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Facebook Account</label>
                        <input type="text" name="facebook_account" class="form-input" maxlength="150" value="{{ $report['facebook_account'] }}">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Google Coordinates *</label>
                        <input type="text" name="coordinates" class="form-input" maxlength="300" required value="{{ $report['coordinates'] }}">
                        <small><a href="{{ $report['map_url'] }}" target="_blank" rel="noopener">Check the place on Google Maps</a></small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">NAP Box and Port *</label>
                        <input type="hidden" name="nap_port_id" value="{{ $report['nap_port_id'] }}">
                        <div class="nap-picker"></div>
                    </div>
                </div>

                <div class="section-title">Items</div>
                <div class="install-lists">
                    <div>
                        <strong>Items taken</strong>
                        @forelse ($report['taken'] as $line)
                        <div class="install-line">{{ $line['quantity'] }} × {{ $line['item_name'] }}</div>
                        @empty
                        <div class="install-muted">None linked to this report.</div>
                        @endforelse
                    </div>
                    <div>
                        <strong>Items brought back (put in stock when you confirm)</strong>
                        @forelse ($report['returns'] as $line)
                        <div class="install-line install-return">
                            <span>{{ $line['item_name'] }} <span class="install-muted">({{ $line['item_condition'] ?: 'Good' }}, reported {{ $line['quantity'] }})</span></span>
                            <input type="number" name="accepted[{{ $line['id'] }}]" class="form-input" min="0" max="{{ $line['quantity'] }}" value="{{ $line['quantity'] }}" aria-label="Quantity of {{ $line['item_name'] }} put back in stock">
                        </div>
                        @empty
                        <div class="install-muted">Nothing brought back.</div>
                        @endforelse
                    </div>
                </div>

                @if ($report['remarks'])
                <p class="install-note"><strong>Technician's remarks:</strong> {{ $report['remarks'] }}</p>
                @endif
                <div class="form-group">
                    <label class="form-label">Your remarks <span style="color: var(--text-muted);">(required to reject; the employee sees it)</span></label>
                    <textarea name="admin_remarks" class="form-input" rows="2" maxlength="1000">{{ $report['admin_remarks'] }}</textarea>
                </div>

                <div style="display:flex;gap:1rem;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reviewModal{{ $report['id'] }}')">Cancel</button>
                    <button type="submit" name="decision" value="reject" class="btn btn-danger" formnovalidate onclick="return confirm('Reject this report? Nothing will be added.')">Reject</button>
                    <button type="submit" name="decision" value="confirm" class="btn btn-success">Confirm &amp; Add Client</button>
                </div>
            </form>
        </div>
    </div>
    @endif
    @endforeach

    <script src="{{ asset('js/nap_picker.js') }}?{{ $napPickerJsVersion }}"></script>
    <script>
    // The NAP picker of a report is drawn when its form is opened.
    const reviewPickers = {};
    function openReview(id) {
        openModal('reviewModal' + id);
        if (reviewPickers[id]) return;
        const form = document.getElementById('reviewForm' + id);
        const barangay = form.querySelector('[name="barangay_name"]');
        const port = form.querySelector('[name="nap_port_id"]');
        reviewPickers[id] = NapPicker.create({
            root: form.querySelector('.nap-picker'),
            input: port,
            lookupUrl: @json(route('nap-boxes.lookup')),
            getBarangay: () => barangay.value,
            getExcept: () => id,
            selectedPortId: Number(port.value) || 0,
        });
        barangay.addEventListener('change', () => reviewPickers[id].refresh());
        barangay.addEventListener('input', () => reviewPickers[id].refresh());
    }
    @if ($openReport)
    document.addEventListener('DOMContentLoaded', () => openReview({{ (int) $openReport }}));
    @endif
    </script>
    @endif
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
