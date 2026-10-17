<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - NAP Boxes</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/nap_boxes.css') }}?{{ $napBoxesCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>

    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">NAP Boxes</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $canManage ? 'The NAP boxes of your branch and what is connected to each port' : 'The NAP boxes of every branch and what is connected to each port' }}</p>
            </div>
            <div style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:center;">
                @if (! \App\Support\BranchScope::isRestricted())
                <form method="GET" action="" class="dashboard-branch-form">
                    @if ($showArchived)<input type="hidden" name="view" value="archived">@endif
                    <label class="form-label" for="napBranch" style="margin:0;">Branch</label>
                    <select name="branch" id="napBranch" class="form-select" onchange="this.form.submit()" style="width:auto;min-width:170px;">
                        <option value="">All branches</option>
                        @foreach ($branches as $branchOption)
                        <option value="{{ $branchOption }}" {{ $branch === $branchOption ? 'selected' : '' }}>{{ $branchOption }}</option>
                        @endforeach
                    </select>
                </form>
                @else
                <span class="badge badge-info">{{ $ownBranch ?: 'No branch assigned' }} branch</span>
                @endif
                @if ($canManage && ! $showArchived)
                <button class="btn btn-success" type="button" onclick="openBoxModal()">+ Add NAP Box</button>
                @endif
            </div>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card"><div class="stat-value">{{ number_format($stats['boxes']) }}</div><div class="stat-label">NAP Boxes</div></div>
            <div class="stat-card"><div class="stat-value">{{ number_format($stats['ports']) }}</div><div class="stat-label">Total Ports</div></div>
            <div class="stat-card"><div class="stat-value" style="color: var(--accent);">{{ number_format($stats['occupied']) }}</div><div class="stat-label">Occupied Ports</div></div>
            <div class="stat-card"><div class="stat-value" style="color: var(--success);">{{ number_format($stats['available']) }}</div><div class="stat-label">Available Ports</div></div>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body">
                <form method="GET" action="" class="box-filter-form">
                    @if ($branch && ! \App\Support\BranchScope::isRestricted())<input type="hidden" name="branch" value="{{ $branch }}">@endif
                    @if ($showArchived)<input type="hidden" name="view" value="archived">@endif
                    <div class="form-group">
                        <label class="form-label" for="filterBarangay">Barangay</label>
                        <input type="text" id="filterBarangay" name="barangay" class="form-input" list="barangaySuggestions" placeholder="Barangay" autocomplete="off" value="{{ $filterBarangay }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="filterSearch">Search</label>
                        <input type="text" id="filterSearch" name="q" class="form-input" placeholder="NAP name or location" value="{{ $search }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    @if ($filterBarangay !== '' || $search !== '')
                    <a class="btn btn-secondary" href="nap_boxes.php{{ $showArchived ? '?view=archived' : '' }}">Clear</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ $showArchived ? 'Archived NAP Boxes' : 'NAP Boxes' }}</span>
                <div class="payslip-view-tabs">
                    <a href="nap_boxes.php{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '?branch=' . urlencode($branch) : '' }}" class="payslip-view-tab {{ ! $showArchived ? 'active' : '' }}">Active</a>
                    <a href="nap_boxes.php?view=archived{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '&branch=' . urlencode($branch) : '' }}" class="payslip-view-tab {{ $showArchived ? 'active' : '' }}">Archived</a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>NAP Box</th>
                                @if (! \App\Support\BranchScope::isRestricted())<th>Branch</th>@endif
                                <th>Barangay</th>
                                <th>Location</th>
                                <th>Ports</th>
                                <th>Occupied</th>
                                <th>Reserved</th>
                                <th>Faulty</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($boxes as $box)
                            <tr>
                                <td><strong>{{ $box['name'] }}</strong></td>
                                @if (! \App\Support\BranchScope::isRestricted())<td><span class="badge badge-info">{{ $box['branch_name'] }}</span></td>@endif
                                <td>{{ $box['barangay_name'] }}</td>
                                <td class="cell-wrap">{{ $box['location'] ?: '—' }}</td>
                                <td>{{ $box['total_ports'] }}</td>
                                <td>{{ $box['occupied_ports'] }}</td>
                                <td>{{ $box['reserved_ports'] ?: '—' }}</td>
                                <td>{{ $box['faulty_ports'] ?: '—' }}</td>
                                <td><strong style="color: {{ $box['available_ports'] > 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $box['available_ports'] }}</strong></td>
                                <td><span class="badge badge-{{ $box['status'] === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($box['status']) }}</span></td>
                                <td>
                                    <div class="port-actions">
                                        <a class="btn btn-sm btn-secondary" href="nap_boxes.php?box={{ $box['id'] }}{{ $showArchived ? '&view=archived' : '' }}{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '&branch=' . urlencode($branch) : '' }}#ports">Ports</a>
                                        @if ($canManage)
                                            @if (! $showArchived)
                                            <button class="btn btn-sm btn-primary" type="button" data-box='@json($box, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP)' onclick="openBoxModal(JSON.parse(this.dataset.box))">Edit</button>
                                            @endif
                                            <form method="POST" action="">
                                                @csrf
                                                <input type="hidden" name="action" value="{{ $showArchived ? 'restore_box' : 'archive_box' }}">
                                                <input type="hidden" name="box_id" value="{{ $box['id'] }}">
                                                <button type="submit" class="btn btn-sm {{ $showArchived ? 'btn-success' : 'btn-danger' }}" @if (! $showArchived) onclick="return confirm('Archive this NAP box?')" @endif>{{ $showArchived ? 'Restore' : 'Archive' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:2rem;">{{ $showArchived ? 'No archived NAP boxes.' : 'No NAP boxes yet.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($selectedBox)
        <div class="card" id="ports" style="margin-top: 1.5rem;">
            <div class="card-header">
                <span class="card-title">{{ $selectedBox['name'] }} — {{ $selectedBox['barangay_name'] }}{{ $selectedBox['location'] ? ' · ' . $selectedBox['location'] : '' }}</span>
                <div class="nap-summary">
                    <span>Total <strong>{{ $selectedBox['total_ports'] }}</strong></span>
                    <span>Occupied <strong>{{ $selectedBox['occupied_ports'] }}</strong></span>
                    <span>Available <strong>{{ $selectedBox['available_ports'] }}</strong></span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Port</th><th>Status</th><th>Client</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($selectedBox['ports'] as $port)
                            <tr>
                                <td><strong>{{ $port['port_number'] }}</strong></td>
                                <td><span class="port-status port-status-{{ $port['status'] }}">{{ ucfirst($port['status']) }}</span></td>
                                <td>
                                    @if ($port['client_name'])
                                    {{ $port['client_name'] }} <span style="color: var(--text-muted);">{{ $port['client_code'] }}</span>
                                    @elseif ($port['reserved_by'])
                                    <span style="color: var(--text-muted);">Request {{ $port['reserved_by'] }}</span>
                                    @else
                                    —
                                    @endif
                                </td>
                                <td>
                                    @if ($canManage && empty($selectedBox['archived']))
                                    <div class="port-actions">
                                        @if ($port['status'] === 'available')
                                        <form method="POST" action="">
                                            @csrf
                                            <input type="hidden" name="action" value="port"><input type="hidden" name="port_id" value="{{ $port['id'] }}"><input type="hidden" name="do" value="assign">
                                            <input type="text" name="client_label" class="form-input port-assign-input" list="clientSuggestions" placeholder="Connect a client…" autocomplete="off" required>
                                            <button type="submit" class="btn btn-sm btn-primary">Connect</button>
                                        </form>
                                        @endif
                                        @if (in_array($port['status'], ['available', 'occupied', 'faulty'], true))
                                        <form method="POST" action="">
                                            @csrf
                                            <input type="hidden" name="action" value="port"><input type="hidden" name="port_id" value="{{ $port['id'] }}">
                                            @if ($port['status'] === 'available')
                                            <input type="hidden" name="do" value="faulty"><button type="submit" class="btn btn-sm btn-secondary">Mark faulty</button>
                                            @elseif ($port['status'] === 'occupied')
                                            <input type="hidden" name="do" value="free"><button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Disconnect this client from the port?')">Free port</button>
                                            @else
                                            <input type="hidden" name="do" value="available"><button type="submit" class="btn btn-sm btn-success">Mark available</button>
                                            @endif
                                        </form>
                                        @endif
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <datalist id="barangaySuggestions">
        @foreach ($barangaySuggestions as $suggestion)<option value="{{ $suggestion }}"></option>@endforeach
    </datalist>
    <datalist id="clientSuggestions">
        @foreach ($assignableClients as $label)<option value="{{ $label }}"></option>@endforeach
    </datalist>

    @if ($canManage)
    <div class="modal-overlay" id="boxModal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title" id="boxModalTitle">Add NAP Box</h3>
                </div>
                <button class="modal-close" type="button" onclick="closeModal('boxModal')">&times;</button>
            </div>
            <form method="POST" action="">
                @csrf
                <input type="hidden" name="action" value="save_box">
                <input type="hidden" name="box_id" id="boxId">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="boxName">Name *</label>
                        <input type="text" name="name" id="boxName" class="form-input" maxlength="50" placeholder="NAP-01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="boxPorts">Number of ports *</label>
                        <input type="number" name="total_ports" id="boxPorts" class="form-input" min="1" max="96" value="8" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="boxBarangay">Barangay *</label>
                        <input type="text" name="barangay" id="boxBarangay" class="form-input" list="barangaySuggestions" placeholder="Type a barangay" autocomplete="off" required>
                    </div>
                    <div class="form-group" id="boxStatusGroup" style="display:none;">
                        <label class="form-label" for="boxStatus">Status</label>
                        <select name="status" id="boxStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label" for="boxLocation">Location</label>
                        <input type="text" name="location" id="boxLocation" class="form-input" maxlength="255" placeholder="Purok 2, near the chapel">
                    </div>
                </div>
                <div style="display:flex;gap:1rem;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('boxModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save NAP Box</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
    function openBoxModal(box) {
        const editing = !!box;
        document.getElementById('boxModalTitle').textContent = editing ? 'Edit NAP Box' : 'Add NAP Box';
        document.getElementById('boxId').value = editing ? box.id : '';
        document.getElementById('boxName').value = editing ? box.name : '';
        document.getElementById('boxPorts').value = editing ? box.total_ports : 8;
        document.getElementById('boxBarangay').value = editing ? box.barangay_name : '';
        document.getElementById('boxLocation').value = editing ? (box.location || '') : '';
        document.getElementById('boxStatus').value = editing ? box.status : 'active';
        document.getElementById('boxStatusGroup').style.display = editing ? '' : 'none';
        openModal('boxModal');
    }
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion ?? 1 }}"></script>
</body>
</html>
