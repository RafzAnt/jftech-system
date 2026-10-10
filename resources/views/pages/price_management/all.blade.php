@php
    $currencyCode = strtoupper(trim((string) ($currency ?? 'PHP')));
    $currencySymbol = $currencyCode === 'PHP' ? '₱' : $currencyCode;
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode . ' ';
    $search = $filters['search'] ?? '';
    $filterCategory = $filters['category'] ?? '';
    $filterStatus = $filters['status'] ?? '';
    $filterDiscount = $filters['has_discount'] ?? '';
    $minPrice = $filters['min_price'] ?? '';
    $maxPrice = $filters['max_price'] ?? '';
    $effectiveFrom = $filters['effective_from'] ?? '';
    $effectiveUntil = $filters['effective_until'] ?? '';
    $sort = $filters['sort'] ?? 'created_at';
    $sortDir = $filters['sort_dir'] ?? 'desc';
    $perPage = $filters['per_page'] ?? 15;
    $currentPage = $pagination['current_page'] ?? 1;
    $lastPage = $pagination['last_page'] ?? 1;
    $total = $pagination['total'] ?? 0;
    $statusOptions = ['draft', 'active', 'scheduled', 'inactive', 'expired'];
@endphp

<!-- Toolbar: Search + Filter + Export -->
<div class="price-toolbar">
    <div class="search-box" style="max-width: 300px;">
        <input type="text" class="search-input" placeholder="Search by product name, product code, or number..." data-target="#pricesTable" value="{{ htmlspecialchars($search) }}"
               onkeyup="filterTable()">
    </div>

    <button class="btn btn-sm btn-secondary" onclick="toggleFilters()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M9 12h.01"/><path d="M9 18h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
        Filter
    </button>

    <form method="GET" action="price_management.php" style="display: inline;">
        <input type="hidden" name="view" value="all">
        <button type="submit" class="btn btn-sm btn-primary">Reset</button>
    </form>

    <a href="{{ url('/price-management/export?format=csv' . ($search ? '&search=' . urlencode($search) : '')) }}"
       class="btn btn-sm btn-success">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/></svg>
        Export
    </a>
</div>

<!-- Filter Section -->
<div class="filter-section" id="filterSection" style="display: none;">
    <form method="GET" id="priceFilterForm" style="display: block;">
        <input type="hidden" name="view" value="all">
        <div class="filter-grid">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" onchange="applyFilter()">
                    <option value="">All Categories</option>
                    @foreach ($categories as $cat)
                    <option value="{{ htmlspecialchars($cat) }}" {{ $filterCategory === $cat ? 'selected' : '' }}>{{ htmlspecialchars($cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" onchange="applyFilter()">
                    <option value="">All Statuses</option>
                    @foreach ($statusOptions as $status)
                    <option value="{{ $status }}" {{ $filterStatus === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Has Discount</label>
                <select name="has_discount" class="form-select" onchange="applyFilter()">
                    <option value="">All</option>
                    <option value="yes" {{ $filterDiscount === 'yes' ? 'selected' : '' }}>With Discount</option>
                    <option value="no" {{ $filterDiscount === 'no' ? 'selected' : '' }}>No Discount</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Min Price</label>
                <input type="number" name="min_price" class="form-input" placeholder="0.00" value="{{ $minPrice }}" step="0.01" onchange="applyFilter()">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Max Price</label>
                <input type="number" name="max_price" class="form-input" placeholder="0.00" value="{{ $maxPrice }}" step="0.01" onchange="applyFilter()">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Start Date</label>
                <input type="date" name="effective_from" class="form-input" value="{{ $effectiveFrom }}" onchange="applyFilter()">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">End Date</label>
                <input type="date" name="effective_until" class="form-input" value="{{ $effectiveUntil }}" onchange="applyFilter()">
            </div>
        </div>
    </form>
</div>

<!-- Update Multiple Prices Bar -->
<div class="bulk-update-bar" id="bulkUpdateBar">
    <label style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <input type="checkbox" class="select-all-checkbox" id="selectAllPrices" onchange="toggleBulkBar()">
        Select All ({{ $total }})
    </label>
    <span id="selectedCount" style="color: var(--text-muted);">0 selected</span>
    <button class="btn btn-sm btn-warning" onclick="openModal('bulkUpdateModal')" style="color: var(--primary); margin-left: auto;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.89 1.78L3 12v8h2l1.5-3h8.41l2.58-5.17L8 5.5V3H3z"/></svg>
        Update Selected Prices
    </button>
</div>

<!-- Buttons Row -->
<div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <button class="btn btn-success" onclick="openModal('addPriceModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Price
    </button>
    <button class="btn btn-warning" onclick="openModal('bulkUpdateModal')" style="color: var(--primary);">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.89 1.78L3 12v8h2l1.5-3h8.41l2.58-5.17L8 5.5V3H3z"/></svg>
        Update Multiple Prices
    </button>
</div>

<!-- Prices Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Prices</span>
        <div style="font-size: 0.8rem; color: var(--text-muted);">
            {{ number_format($total) }} records
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container">
            <table class="data-table" id="pricesTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="select-all-checkbox" id="checkAllPrices" style="cursor: pointer;"></th>
                        <th onclick="sortTable('product_name')" style="cursor: pointer;">
                            Product Name
                            @if($sort === 'product_name') <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th>Product Code</th>
                        <th>Category</th>
                        <th onclick="sortTable('base_price')" style="cursor: pointer;">Base Price @if($sort === 'base_price') <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span> @endif</th>
                        <th>Discount</th>
                        <th onclick="sortTable('final_price')" style="cursor: pointer;">Final Price @if($sort === 'final_price') <span>{{ $sortDir === 'asc' ? '↓' : '↑' }}</span> @endif</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th onclick="sortTable('status')" style="cursor: pointer;">Status @if($sort === 'status') <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span> @endif</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($prices ?? []) as $price)
                    @php
                        $statusClass = \App\Models\Price::statusColor($price['status'] ?? 'inactive');
                        $hasDiscount = ($price['discount_type'] ?? 'none') !== 'none' && (float)($price['discount_value'] ?? 0) > 0;
                    @endphp
                    <tr data-product-id="{{ $price['product_id'] }}" data-price-id="{{ $price['id'] }}">
                        <td><input type="checkbox" class="price-checkbox" value="{{ $price['id'] }}" onchange="updateBulkBar()"></td>
                        <td><strong>{{ htmlspecialchars($price['product_name'] ?? 'N/A') }}</strong></td>
                        <td>{{ htmlspecialchars($price['sku'] ?? 'N/A') }}</td>
                        <td>{{ htmlspecialchars($price['category'] ?? 'N/A') }}</td>
                        <td>{{ $currencyPrefix }}{{ number_format((float)($price['base_price'] ?? 0), 2) }}</td>
                        <td>
                            @if ($hasDiscount)
                                @if ($price['discount_type'] === 'percentage')
                                    {{ $price['discount_value'] }}% OFF
                                @else
                                    {{ $currencyPrefix }}{{ number_format((float)$price['discount_value'], 2) }} OFF
                                @endif
                            @else
                                <span style="color: var(--text-muted);">No Discount</span>
                            @endif
                        </td>
                        <td style="font-weight: 600; color: var(--accent);">{{ $currencyPrefix }}{{ number_format((float)($price['final_price'] ?? 0), 2) }}</td>
                        <td>{{ $price['effective_from'] ? date('M d, Y', strtotime($price['effective_from'])) : '-' }}</td>
                        <td>{{ $price['effective_until'] ? date('M d, Y', strtotime($price['effective_until'])) : '-' }}</td>
                        <td>
                            <span class="badge badge-{{ $statusClass }}">{{ ucfirst($price['status'] ?? 'unknown') }}</span>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $price['updated_at'] ? date('M d, h:i A', strtotime($price['updated_at'])) : '-' }}</td>
                        <td>
                            <div class="action-menu">
                                <button class="action-menu-btn" onclick="toggleActionMenu(this)">
                                    Actions
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.3rem;"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                                <div class="action-menu-dropdown" style="min-width: 160px;">
                                    <a href="/price-management/prices/{{ $price['id'] }}" class="dropdown-item">View</a>
                                    <button class="dropdown-item" onclick="openEditPriceModal({{ htmlspecialchars(json_encode($price)) }})">Edit</button>
                                    <button class="dropdown-item" onclick="openChangePriceModal({{ htmlspecialchars(json_encode($price)) }})">Change Price</button>
                                    <button class="dropdown-item" onclick="openDiscountModal({{ htmlspecialchars(json_encode($price)) }})">Add/Edit Discount</button>
                                    <button class="dropdown-item" onclick="openScheduleModal({{ htmlspecialchars(json_encode($price)) }})">Schedule Price Change</button>
                                    <a href="price_management.php?view=history&product_id={{ $price['product_id'] }}" class="dropdown-item">View History</a>
                                    <button class="dropdown-item" onclick="openDeactivateModal({{ $price['id'] }}, '{{ htmlspecialchars($price['product_name'] ?? 'N/A') }}')">Set Inactive</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="12" style="text-align:center;color:var(--text-muted);padding:2rem;">No prices found. Click "Add Price" to get started.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($lastPage > 1)
<!-- Pagination -->
<div class="pagination">
    @php
        $queryParams = array_filter(array_merge(request()->query(), ['view' => 'all']));
    @endphp
    @for ($i = 1; $i <= $lastPage; $i++)
    @php
        $queryParams['page'] = $i;
        $url = 'price_management.php?' . http_build_query($queryParams);
    @endphp
    <a href="{{ $url }}" class="page-btn {{ $currentPage === $i ? 'active' : '' }}">{{ $i }}</a>
    @endfor
</div>
@endif

<script>
function filterTable() {
    const searchTerm = document.querySelector('.search-input').value.toLowerCase();
    const rows = document.querySelectorAll('#pricesTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}
function toggleFilters() {
    const section = document.getElementById('filterSection');
    section.style.display = section.style.display === 'block' ? 'none' : 'block';
}
function toggleBulkBar() {
    const bar = document.getElementById('bulkUpdateBar');
    const anyChecked = document.querySelectorAll('.price-checkbox:checked').length > 0;
    bar.classList.toggle('show', anyChecked);
    updateSelectedCount();
}
function updateBulkBar() {
    toggleBulkBar();
}
function updateSelectedCount() {
    const count = document.querySelectorAll('.price-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' selected';
}
function sortTable(field) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', field);
    url.searchParams.set('sort_dir', '{{ $sortDir === "asc" ? "desc" : "asc" }}');
    url.searchParams.set('view', 'all');
    window.location = url.toString();
}
</script>
