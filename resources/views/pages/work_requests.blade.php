<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Work Requests</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/work_requests.css') }}?{{ $workRequestsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>

    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Work Requests</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $canReview ? 'Installation and repair requests of your technicians' : 'Installation and repair requests of the technicians, by branch' }}</p>
            </div>
            <form method="GET" action="" class="dashboard-branch-form" style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="status" value="{{ $tab }}">
                @if (! \App\Support\BranchScope::isRestricted())
                <label class="form-label" for="requestBranch" style="margin:0;">Branch</label>
                <select name="branch" id="requestBranch" class="form-select" style="width:auto;min-width:170px;" onchange="document.getElementById('requestBarangay').value = ''; this.form.submit();">
                    <option value="">All branches</option>
                    @foreach ($branches as $branchOption)
                    <option value="{{ $branchOption }}" {{ $branch === $branchOption ? 'selected' : '' }}>{{ $branchOption }}</option>
                    @endforeach
                </select>
                @else
                <span class="badge badge-info">{{ $branch ?: 'No branch assigned' }} branch</span>
                @endif
                <label class="form-label" for="requestBarangay" style="margin:0;">Barangay</label>
                <input type="text" name="barangay" id="requestBarangay" class="form-input" list="requestBarangaySuggestions" placeholder="All barangays" autocomplete="off" value="{{ $barangay }}" style="width:auto;min-width:170px;">
                <datalist id="requestBarangaySuggestions">
                    @foreach ($barangaySuggestions as $suggestion)<option value="{{ $suggestion }}"></option>@endforeach
                </datalist>
                <button type="submit" class="btn btn-primary">Filter</button>
                @if ($barangay !== '' || ($branch && ! \App\Support\BranchScope::isRestricted()))
                <a href="work_requests.php?status={{ $tab }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <div class="request-tabs">
            @foreach ($tabs as $key => $definition)
            <a class="request-tab {{ $tab === $key ? 'active' : '' }}" href="work_requests.php?status={{ $key }}{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '&branch=' . urlencode($branch) : '' }}{{ $barangay !== '' ? '&barangay=' . urlencode($barangay) : '' }}">
                {{ $definition['label'] }}
                <span class="request-tab-count">{{ $tabCounts[$key] }}</span>
            </a>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ $tabs[$tab]['label'] }}</span>
                <span class="badge badge-info">{{ count($requests) }} Request(s)</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Request</th>
                                <th>Employee</th>
                                @if (! \App\Support\BranchScope::isRestricted())<th>Branch</th>@endif
                                <th>Type</th>
                                <th>Client</th>
                                <th>Barangay</th>
                                <th>NAP Box / Port</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $row)
                            @php
                                $napLabel = $row['work_type'] === 'installation'
                                    ? (($row['actual_nap_box_name'] ?: $row['nap_box_name']) ? ($row['actual_nap_box_name'] ?: $row['nap_box_name']) . ' · port ' . ($row['actual_nap_port_number'] ?: $row['nap_port_number']) : '—')
                                    : '—';
                            @endphp
                            <tr>
                                <td><strong>{{ $row['request_code'] }}</strong></td>
                                <td>{{ $row['employee_name'] }}</td>
                                @if (! \App\Support\BranchScope::isRestricted())<td><span class="badge badge-info">{{ $row['branch_name'] }}</span></td>@endif
                                <td>{{ ucfirst($row['work_type']) }}</td>
                                <td>{{ $row['client_name'] }} @if ($row['is_new_client'])<span class="badge badge-warning">New</span>@endif</td>
                                <td>{{ $row['barangay_name'] ?: '—' }}</td>
                                <td>{{ $napLabel }}</td>
                                <td><span class="wr-status wr-status-{{ $row['status'] }}">{{ $row['status_label'] }}</span></td>
                                <td>{{ $row['submitted_at'] ? \Carbon\Carbon::parse($row['submitted_at'])->format('M d, Y h:i A') : '—' }}</td>
                                <td><button type="button" class="btn btn-sm btn-primary" onclick="openRequest({{ $row['id'] }})">{{ $canReview && in_array($row['status'], ['pending_approval', 'work_completed'], true) ? 'Review' : 'View' }}</button></td>
                            </tr>
                            @empty
                            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:2rem;">No requests here.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="requestModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title" id="requestModalTitle">Request</h3>
                </div>
                <button class="modal-close" type="button" onclick="closeModal('requestModal')">&times;</button>
            </div>
            <div id="requestModalBody"></div>
        </div>
    </div>

    {{-- One hidden template per request on this page; the modal shows a copy of the one that was opened. --}}
    @foreach ($requests as $row)
    @php($d = $details[$row['id']])
    <template id="request-{{ $d['id'] }}" data-title="{{ $d['request_code'] }} · {{ ucfirst($d['work_type']) }}">
        <p><span class="wr-status wr-status-{{ $d['status'] }}">{{ $d['status_label'] }}</span></p>
        <dl class="wr-facts">
            <div><dt>Employee</dt><dd>{{ $d['employee_name'] }} <span style="color: var(--text-muted);">· {{ $d['employee_position'] }}</span></dd></div>
            <div><dt>Branch</dt><dd>{{ $d['branch_name'] }}</dd></div>
            <div>
                <dt>Client</dt>
                <dd>{{ $d['client_name'] }} @if ($d['is_new_client'])<span class="badge badge-warning">New client</span>@elseif ($d['client_code'])<span style="color: var(--text-muted);">{{ $d['client_code'] }}</span>@endif</dd>
            </div>
            <div><dt>Mobile number</dt><dd>{{ $d['client_phone'] ?: '—' }}</dd></div>
            <div><dt>Barangay</dt><dd>{{ $d['barangay_name'] ?: '—' }}</dd></div>
            <div><dt>Address</dt><dd>{{ $d['client_address'] ?: '—' }}</dd></div>
            @if ($d['work_type'] === 'installation')
            <div><dt>Proposed NAP box / port</dt><dd>{{ $d['nap_box_name'] ? $d['nap_box_name'] . ' · port ' . $d['nap_port_number'] : '—' }}</dd></div>
            <div><dt>Location note</dt><dd>{{ $d['location'] ?: '—' }}</dd></div>
            @else
            <div class="wr-wide"><dt>Location</dt><dd>{{ $d['location'] ?: '—' }}</dd></div>
            <div class="wr-wide"><dt>Problem</dt><dd>{{ $d['problem_details'] }}</dd></div>
            @endif
            <div><dt>Submitted</dt><dd>{{ $d['submitted_at'] ? \Carbon\Carbon::parse($d['submitted_at'])->format('M d, Y h:i A') : '—' }}</dd></div>
            <div><dt>Reviewed</dt><dd>{{ $d['reviewed_at'] ? \Carbon\Carbon::parse($d['reviewed_at'])->format('M d, Y h:i A') : '—' }}</dd></div>
        </dl>

        @if ($d['admin_remarks'])
        <div class="wr-remarks"><strong>Admin remarks:</strong> {{ $d['admin_remarks'] }}</div>
        @endif

        @if (in_array($d['status'], ['work_completed', 'confirmed'], true))
        <div class="wr-section">Completion report</div>
        <dl class="wr-facts">
            @if ($d['work_type'] === 'installation')
            <div><dt>NAP box / port used</dt><dd>{{ $d['actual_nap_box_name'] ? $d['actual_nap_box_name'] . ' · port ' . $d['actual_nap_port_number'] : '—' }}</dd></div>
            @endif
            <div><dt>Reported</dt><dd>{{ $d['completed_at'] ? \Carbon\Carbon::parse($d['completed_at'])->format('M d, Y h:i A') : '—' }}</dd></div>
            <div class="wr-wide"><dt>{{ $d['work_type'] === 'repair' ? 'Repair details' : 'Notes' }}</dt><dd>{{ $d['completion_notes'] }}</dd></div>
        </dl>
        @if (count($d['materials']))
        <div class="data-table-container">
            <table class="data-table">
                <thead><tr><th>Material</th><th>Used</th><th>Returned</th><th>Damaged</th></tr></thead>
                <tbody>
                    @foreach ($d['materials'] as $material)
                    <tr><td>{{ $material['item_name'] }}</td><td>{{ $material['used_quantity'] }}</td><td>{{ $material['returned_quantity'] }}</td><td>{{ $material['damaged_quantity'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p style="color: var(--text-muted);">No materials were reported.</p>
        @endif
        @endif

        @if ($canReview && $d['status'] === 'pending_approval')
        <div class="wr-action-forms">
            <form method="POST" action="" class="wr-action-form">
                @csrf
                <input type="hidden" name="request_id" value="{{ $d['id'] }}">
                <div class="form-group">
                    <label class="form-label">Remarks (optional for approval, required to return or reject)</label>
                    <textarea name="remarks" class="form-input" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="wr-action-buttons">
                    <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this request?')">Reject</button>
                    <button type="submit" name="action" value="return" class="btn btn-warning">Return for correction</button>
                    <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
        @endif

        @if ($canReview && $d['status'] === 'work_completed')
        <div class="wr-action-forms">
            <form method="POST" action="" class="wr-action-form">
                @csrf
                <input type="hidden" name="request_id" value="{{ $d['id'] }}">
                @if ($d['is_new_client'])
                <div class="wr-section" style="margin-top: 0; border-top: 0; padding-top: 0;">New client — saved when you confirm</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Internet plan *</label>
                        <select name="internet_plan_id" class="form-select">
                            <option value="">Choose a plan</option>
                            @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->plan_name }} — ₱{{ number_format($plan->monthly_price, 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Installation date</label>
                        <input type="date" name="installation_date" class="form-input" value="{{ $d['completed_at'] ? \Carbon\Carbon::parse($d['completed_at'])->toDateString() : now()->toDateString() }}">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Email (optional)</label>
                        <input type="email" name="email" class="form-input" maxlength="100">
                    </div>
                </div>
                @endif
                <div class="form-group">
                    <label class="form-label">Remarks (optional to confirm, required to return the report)</label>
                    <textarea name="remarks" class="form-input" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="wr-action-buttons">
                    <button type="submit" name="action" value="return_completion" class="btn btn-warning">Return the report</button>
                    <button type="submit" name="action" value="confirm" class="btn btn-success">Confirm completion</button>
                </div>
            </form>
        </div>
        @endif
    </template>
    @endforeach

    <script>
    function openRequest(id) {
        const template = document.getElementById('request-' + id);
        if (!template) return;
        document.getElementById('requestModalTitle').textContent = template.dataset.title || 'Request';
        const body = document.getElementById('requestModalBody');
        body.replaceChildren(template.content.cloneNode(true));
        openModal('requestModal');
    }
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion ?? 1 }}"></script>
</body>
</html>
