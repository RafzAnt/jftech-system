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
    <link rel="stylesheet" href="{{ asset('css/client_filter.css') }}?{{ $clientFilterCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>

    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Inventory · NAP Boxes</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $canManage ? 'The NAP boxes of your branch, counted in your inventory, and what is connected to each port' : 'The NAP boxes of every branch, counted in the inventory, and what is connected to each port' }}</p>
            </div>
            <div style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:center;">
                <span class="badge badge-info">{{ $ownBranch ?: ($branch ? $branch . ' branch' : 'All branches') }}{{ $ownBranch ? ' branch' : '' }}</span>
                <a class="btn btn-secondary" href="inventory.php{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '?branch=' . urlencode($branch) : '' }}">Back to Inventory</a>
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
            <a href="inventory.php?category={{ urlencode(\App\Support\NapBoxes::STOCK_CATEGORY) }}{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '&branch=' . urlencode($branch) : '' }}" class="stat-card" style="text-decoration: none; color: inherit;"><div class="stat-value" style="color: var(--warning);">{{ number_format($napInStock) }}</div><div class="stat-label">NAP Boxes in Stock</div></a>
        </div>

        <!-- One search box: a branch, a barangay or a NAP box -->
        <form method="GET" action="" class="card client-filter-card" id="clientFilterForm" style="margin-bottom: 1.5rem;">
            @if ($branch && ! \App\Support\BranchScope::isRestricted())<input type="hidden" name="branch" value="{{ $branch }}">@endif
            @if ($showArchived)<input type="hidden" name="view" value="archived">@endif
            <div class="card-body">
                @php
                    $searchTerm = $search;
                    $scopeBranch = $ownBranch;
                    $filterSubject = 'NAP Boxes';
                    $filterSearchLabel = 'Search NAP Boxes';
                    $filterIdPrefix = 'nap';
                    unset($filterDate);
                @endphp
                @include('pages.partials.client_location_filter')
                @if ($search !== '')
                <a class="btn btn-secondary btn-sm" style="margin-top: 0.75rem;" href="nap_boxes.php{{ $showArchived ? '?view=archived' : '' }}">Clear search</a>
                @endif
            </div>
        </form>

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
                                <th>Coordinates</th>
                                <th>Ports</th>
                                <th>Occupied</th>
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
                                <td class="cell-wrap">
                                    @if ($box['map_url'])
                                    <a href="{{ $box['map_url'] }}" target="_blank" rel="noopener" title="Open in Google Maps">{{ $box['coordinates'] }}</a>
                                    @else
                                    <span class="badge badge-warning" title="Edit the box and add its Google coordinates">No coordinates</span>
                                    @endif
                                </td>
                                <td>{{ $box['total_ports'] }}</td>
                                <td>{{ $box['occupied_ports'] }}</td>
                                <td>{{ $box['faulty_ports'] ?: '—' }}</td>
                                <td><strong style="color: {{ $box['available_ports'] > 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $box['available_ports'] }}</strong></td>
                                <td><span class="badge badge-{{ $box['status'] === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($box['status']) }}</span></td>
                                <td class="nap-menu-cell">
                                    <div class="nap-action-menu">
                                        <button class="nap-action-menu-btn" type="button" aria-label="Actions for {{ $box['name'] }}" aria-expanded="false" onclick="toggleNapActionMenu(this)">&#8942;</button>
                                        <div class="nap-action-menu-dropdown">
                                            <a class="nap-menu-item" href="nap_boxes.php?box={{ $box['id'] }}{{ $showArchived ? '&view=archived' : '' }}{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '&branch=' . urlencode($branch) : '' }}#ports">Ports</a>
                                            @if ($canManage)
                                                @if (! $showArchived)
                                                <button class="nap-menu-item" type="button" data-box='@json($box, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP)' onclick="closeNapActionMenus(); openBoxModal(JSON.parse(this.dataset.box))">Edit</button>
                                                @endif
                                                <form method="POST" action="">
                                                    @csrf
                                                    <input type="hidden" name="action" value="{{ $showArchived ? 'restore_box' : 'archive_box' }}">
                                                    <input type="hidden" name="box_id" value="{{ $box['id'] }}">
                                                    <button type="submit" class="nap-menu-item {{ $showArchived ? '' : 'nap-menu-item-danger' }}" @if (! $showArchived) onclick="return confirm('Archive this NAP box?')" @endif>{{ $showArchived ? 'Restore' : 'Archive' }}</button>
                                                </form>
                                            @endif
                                        </div>
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
                <span class="card-title">{{ $selectedBox['name'] }} — {{ $selectedBox['barangay_name'] }}{{ $selectedBox['location'] ? ' · ' . $selectedBox['location'] : '' }}@if ($selectedBox['map_url']) · <a href="{{ $selectedBox['map_url'] }}" target="_blank" rel="noopener">{{ $selectedBox['coordinates'] }}</a>@endif</span>
                <div class="nap-summary">
                    <span>Total <strong>{{ $selectedBox['total_ports'] }}</strong></span>
                    <span>Occupied <strong>{{ $selectedBox['occupied_ports'] }}</strong></span>
                    <span>Available <strong>{{ $selectedBox['available_ports'] }}</strong></span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                @if ($selectedBox['has_photo'])
                <div class="nap-photo-wrap">
                    <a href="{{ route('nap-boxes.photo', $selectedBox['id']) }}?v={{ $selectedBox['photo_version'] }}" target="_blank" rel="noopener" title="Open the photo">
                        <img class="nap-photo" src="{{ route('nap-boxes.photo', $selectedBox['id']) }}?v={{ $selectedBox['photo_version'] }}" alt="Photo of {{ $selectedBox['name'] }}">
                    </a>
                </div>
                @endif
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Port</th><th>Status</th><th>Client</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($selectedBox['ports'] as $port)
                            <tr>
                                <td><strong>{{ $port['port_number'] }}</strong></td>
                                <td><span class="port-status port-status-{{ $port['reserved'] ? 'reserved' : $port['status'] }}" @if ($port['reserved']) title="Used in an installation report that is waiting for the admin" @endif>{{ $port['reserved'] ? 'Reserved' : ucfirst($port['status']) }}</span></td>
                                <td>
                                    @if ($port['client_name'])
                                    {{ $port['client_name'] }} <span style="color: var(--text-muted);">{{ $port['client_code'] }}</span>
                                    @else
                                    —
                                    @endif
                                </td>
                                <td>
                                    @if ($canManage && empty($selectedBox['archived']))
                                    <div class="port-actions">
                                        @if ($port['status'] === 'available' && ! $port['reserved'])
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

        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Inventory used on {{ $selectedBox['name'] }}</span>
                <a class="btn btn-sm btn-secondary" href="inventory.php?tab=movements{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '&branch=' . urlencode($branch) : '' }}">All stock transactions</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Date &amp; Time</th><th>Item</th><th>Type</th><th>Qty</th><th>Employee</th><th>Client</th><th>Notes</th></tr></thead>
                        <tbody>
                            @forelse ($selectedBox['inventory_used'] as $used)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($used['occurred_at'])->format('M d, Y h:i A') }}</td>
                                <td><strong>{{ $used['item_name'] }}</strong></td>
                                <td>{{ $used['type_label'] }}</td>
                                <td style="font-weight: 700; color: {{ $used['quantity_change'] < 0 ? 'var(--danger)' : ($used['quantity_change'] > 0 ? 'var(--success)' : 'inherit') }};">{{ $used['quantity_change'] === 0 ? '—' : ($used['quantity_change'] > 0 ? '+' : '') . $used['quantity_change'] }}</td>
                                <td>{{ $used['employee_name'] ?: '—' }}</td>
                                <td>{{ $used['client_name'] ? $used['client_name'] . ($used['client_code'] ? ' (' . $used['client_code'] . ')' : '') : '—' }}</td>
                                <td class="cell-wrap">{{ $used['remarks'] ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">No inventory has been used on this NAP box yet.</td></tr>
                            @endforelse
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
                    <div class="form-group" id="boxStockGroup" style="grid-column: 1 / -1;">
                        <label class="form-label" for="boxStock">Take from stock *</label>
                        @if ($napStockItems !== [])
                        <select name="stock_item_id" id="boxStock" class="form-select">
                            @foreach ($napStockItems as $stockItem)
                            <option value="{{ $stockItem['id'] }}" {{ $stockItem['quantity'] < 1 ? 'disabled' : '' }}>{{ $stockItem['item_name'] }} — {{ $stockItem['quantity'] }} in stock</option>
                            @endforeach
                        </select>
                        @else
                        <p style="margin: 0; color: var(--warning);" id="boxNoStock">There is no NAP Box in your stock. <a href="inventory.php">Add an item in Inventory</a> with the category "{{ \App\Support\NapBoxes::STOCK_CATEGORY }}" first.</p>
                        @endif
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
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label" for="boxCoordinates">Google coordinates *</label>
                        <input type="text" name="coordinates" id="boxCoordinates" class="form-input" maxlength="300" placeholder="9.6541, 124.1234" autocomplete="off" required>
                        <small style="color: var(--text-muted);">Exactly where the NAP box hangs. On Google Maps, press and hold (or right-click) the spot and copy the numbers at the top, or paste the map address.</small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label" for="boxPhoto">Photo of the NAP box</label>
                        <input type="file" id="boxPhoto" class="form-input" accept="image/jpeg,image/png,image/webp" onchange="choosePhoto(this)">
                        <input type="hidden" name="photo_data" id="boxPhotoData">
                        <img id="boxPhotoPreview" class="nap-photo nap-photo-preview" alt="Photo of the NAP box" hidden>
                        <label id="boxPhotoRemoveGroup" class="nap-photo-remove" hidden>
                            <input type="checkbox" name="remove_photo" value="1" id="boxPhotoRemove" onchange="document.getElementById('boxPhotoPreview').hidden = this.checked"> Remove the current photo
                        </label>
                    </div>
                </div>
                <div style="display:flex;gap:1rem;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('boxModal')">Cancel</button>
                    <button type="submit" class="btn btn-success" id="boxSave">Save NAP Box</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
    // The row actions (Ports, Edit, Archive) live in a three-dot menu. The open menu is moved to the page body so the table cannot clip it.
    function closeNapActionMenus() {
        document.querySelectorAll('.nap-action-menu-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
            if (menu._napMenuPlaceholder) {
                menu.removeAttribute('style');
                menu._napMenuPlaceholder.replaceWith(menu);
                delete menu._napMenuPlaceholder;
            }
        });
        document.querySelectorAll('.nap-action-menu-btn[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    }
    function toggleNapActionMenu(button) {
        const menu = button.nextElementSibling;
        const willOpen = !menu.classList.contains('show');
        closeNapActionMenus();
        if (!willOpen) return;
        const placeholder = document.createComment('nap action menu');
        menu.parentNode.replaceChild(placeholder, menu);
        menu._napMenuPlaceholder = placeholder;
        document.body.appendChild(menu);
        menu.style.cssText = 'position:fixed;visibility:hidden;z-index:1000;right:auto;bottom:auto';
        menu.classList.add('show');
        const buttonRect = button.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const top = buttonRect.bottom + menuRect.height + 8 <= window.innerHeight ? buttonRect.bottom + 6 : Math.max(8, buttonRect.top - menuRect.height - 6);
        const left = Math.max(8, Math.min(window.innerWidth - menuRect.width - 8, buttonRect.right - menuRect.width));
        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
        menu.style.visibility = 'visible';
        button.setAttribute('aria-expanded', 'true');
    }
    document.addEventListener('click', event => {
        if (!event.target.closest('.nap-action-menu') && !event.target.closest('.nap-action-menu-dropdown')) closeNapActionMenus();
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeNapActionMenus(); });
    window.addEventListener('scroll', closeNapActionMenus, true);
    window.addEventListener('resize', closeNapActionMenus);

    function openBoxModal(box) {
        const editing = !!box;
        document.getElementById('boxModalTitle').textContent = editing ? 'Edit NAP Box' : 'Add NAP Box';
        document.getElementById('boxId').value = editing ? box.id : '';
        document.getElementById('boxName').value = editing ? box.name : '';
        document.getElementById('boxPorts').value = editing ? box.total_ports : 8;
        document.getElementById('boxBarangay').value = editing ? box.barangay_name : '';
        document.getElementById('boxLocation').value = editing ? (box.location || '') : '';
        document.getElementById('boxCoordinates').value = editing ? (box.coordinates || '') : '';
        document.getElementById('boxStatus').value = editing ? box.status : 'active';
        document.getElementById('boxStatusGroup').style.display = editing ? '' : 'none';
        // A new box is taken from the stock; an existing one is only changed.
        document.getElementById('boxStockGroup').style.display = editing ? 'none' : '';
        const stockSelect = document.getElementById('boxStock');
        if (stockSelect) stockSelect.disabled = editing;
        document.getElementById('boxSave').disabled = !editing && !stockSelect;

        // The photo: nothing is chosen yet; an existing one is shown and can be removed.
        const preview = document.getElementById('boxPhotoPreview');
        document.getElementById('boxPhoto').value = '';
        document.getElementById('boxPhotoData').value = '';
        document.getElementById('boxPhotoRemove').checked = false;
        const hasPhoto = editing && box.has_photo;
        preview.hidden = !hasPhoto;
        preview.src = hasPhoto ? '{{ url('/nap-boxes') }}/' + box.id + '/photo?v=' + box.photo_version : '';
        document.getElementById('boxPhotoRemoveGroup').hidden = !hasPhoto;
        openModal('boxModal');
    }

    // The chosen picture is shrunk in the browser (longest side 1000 px, JPEG) and sent as text, so a phone photo of several
    // megabytes is saved as a few hundred kilobytes.
    function choosePhoto(input) {
        const data = document.getElementById('boxPhotoData');
        const preview = document.getElementById('boxPhotoPreview');
        data.value = '';
        document.getElementById('boxPhotoRemove').checked = false;
        const file = input.files && input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function() {
            const image = new Image();
            image.onload = function() {
                const scale = Math.min(1, 1000 / Math.max(image.width, image.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(image.width * scale));
                canvas.height = Math.max(1, Math.round(image.height * scale));
                const context = canvas.getContext('2d');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                data.value = canvas.toDataURL('image/jpeg', 0.82);
                preview.src = data.value;
                preview.hidden = false;
            };
            image.onerror = function() {
                input.value = '';
                alert('That file is not a picture. Choose a JPEG, PNG or WebP photo.');
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    }
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion ?? 1 }}"></script>
</body>
</html>
