<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Inventory</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}?{{ $inventoryCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">{{ $tab === 'movements' ? 'Stock Update History' : 'Inventory' }}</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $tab === 'movements' ? 'List of all stock updates' : 'Manage equipment and supplies' }}</p>
            </div>
            <div style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:center;">
                @if (! \App\Support\BranchScope::isRestricted())
                <form method="GET" action="" class="dashboard-branch-form">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    @if ($showArchived)<input type="hidden" name="view" value="archived">@endif
                    <label class="form-label" for="inventoryBranch" style="margin:0;">Branch</label>
                    <select name="branch" id="inventoryBranch" class="form-select" onchange="this.form.submit()" style="width:auto;min-width:170px;">
                        <option value="">All branches</option>
                        @foreach ($inventoryBranches as $inventoryBranch)
                        <option value="{{ $inventoryBranch }}" {{ $branch === $inventoryBranch ? 'selected' : '' }}>{{ $inventoryBranch }}</option>
                        @endforeach
                    </select>
                </form>
                @else
                <span class="badge badge-info">{{ $branch ?: 'No branch assigned' }} branch</span>
                @endif
                @if (!$showArchived && $tab === 'stock')
                <a class="btn btn-secondary" href="inventory.php?tab=movements">View Stock Updates</a>
                @if ($canManageInventory)
                <button class="btn btn-success" onclick="openNewItemModal()">+ Add Item</button>
                @endif
                @endif
            </div>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        @if ($tab === 'stock')
        <!-- Stats -->
        <div class="inventory-grid">
            <div class="stat-card">
                <div class="stat-value">{{ $totalItems }}</div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);">₱{{ number_format($totalValue, 2) }}</div>
                <div class="stat-label">Total Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--warning);">{{ $lowStock }}</div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            <a href="nap_boxes.php{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '?branch=' . urlencode($branch) : '' }}" class="stat-card" style="text-decoration: none; color: inherit;">
                <div class="stat-value" style="color: var(--accent);">{{ $napBoxCount }}</div>
                <div class="stat-label">NAP Boxes installed · {{ $napInStock }} in stock · {{ $napPortsAvailable }} ports free</div>
            </a>
        </div>

        <!-- Category Quick Filters -->
        @if (!$showArchived && !empty($categories))
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body" style="padding: 1rem;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">CATEGORIES:</span>
                    <a href="inventory.php{{ $showArchived ? '?view=archived' : '' }}" 
                       class="btn btn-sm {{ !$filterCategory ? 'btn-primary' : 'btn-secondary' }}">
                        All Categories
                    </a>
                    @foreach ($categories as $cat)
                    <a href="inventory.php?category={{ urlencode($cat) }}{{ $showArchived ? '&view=archived' : '' }}" 
                       class="btn btn-sm {{ $filterCategory === $cat ? 'btn-primary' : 'btn-secondary' }}">
                        {{ htmlspecialchars($cat) }}
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        <div class="card">
            <div class="card-header">
                <span class="card-title">Inventory Items</span>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <div class="payslip-view-tabs">
                        <a href="inventory.php" class="payslip-view-tab {{ !$showArchived ? 'active' : '' }}">Active</a>
                        <a href="inventory.php?view=archived" class="payslip-view-tab {{ $showArchived ? 'active' : '' }}">Archived ({{ $archivedCount }})</a>
                        @if (!$showArchived)<a href="inventory.php?tab=movements" class="payslip-view-tab">Stock Updates</a>@endif
                        <a href="nap_boxes.php{{ $branch && ! \App\Support\BranchScope::isRestricted() ? '?branch=' . urlencode($branch) : '' }}" class="payslip-view-tab">NAP Boxes ({{ $napBoxCount }})</a>
                    </div>
                    <a class="btn btn-sm btn-secondary" target="_blank" rel="noopener" href="{{ url('/pages/print_inventory.php?category=' . urlencode($filterCategory) . ($showArchived ? '&archived=1' : '') . (! \App\Support\BranchScope::isRestricted() && $branch ? '&branch=' . urlencode($branch) : '')) }}">Print</a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                @if (! \App\Support\BranchScope::isRestricted())<th>Branch</th>@endif
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Reserved</th>
                                <th>Available</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>Status</th>
                                <th aria-label="Item menu"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filteredItems as $item)
                            @php
                            $isLowStock = $item['available_quantity'] <= $item['reorder_level'];
@endphp
                            <tr class="{{ $isLowStock ? 'row-warning' : '' }}">
                                <td><strong>{{ htmlspecialchars($item['item_name']) }}</strong></td>
                                @if (! \App\Support\BranchScope::isRestricted())<td><span class="badge badge-info">{{ $item['branch_name'] }}</span></td>@endif
                                <td>{{ $item['category'] }}</td>
                                <td>
                                    <span style="color: {{ $isLowStock ? 'var(--warning)' : 'inherit' }}; font-weight: 600;">
                                        {{ $item['quantity'] }}
                                    </span>
                                    @if ($isLowStock)
                                    <span class="badge badge-warning" style="margin-left: 0.5rem;">Low Stock</span>
                                    @endif
                                </td>
                                <td>{{ $item['reserved_quantity'] ?: '—' }}</td>
                                <td><strong style="color: {{ $isLowStock ? 'var(--warning)' : 'var(--success)' }};">{{ $item['available_quantity'] }}</strong></td>
                                <td>₱{{ number_format($item['unit_price'], 2) }}</td>
                                <td style="font-weight: 600;">₱{{ number_format($item['quantity'] * $item['unit_price'], 2) }}</td>
                                <td>
                                    <span class="badge badge-{{ $item['status'] === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($item['status']) }}
                                    </span>
                                </td>
                                <td class="inventory-menu-cell">
                                    @if ($canManageInventory)
                                    <div class="inventory-action-menu">
                                        <button class="inventory-action-menu-btn" type="button" aria-label="Actions for {{ htmlspecialchars($item['item_name']) }}" aria-expanded="false" onclick="toggleInventoryActionMenu(this)">&#8942;</button>
                                        <div class="inventory-action-menu-dropdown">
                                        @if ($showArchived)
                                        <form method="POST" action="{{ route('inventory.restore', $item['id']) }}">
            @csrf
                                            <button type="submit" class="inventory-menu-item">Restore</button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory.destroy', $item['id']) }}" onsubmit="return confirm('Permanently delete this archived item? This cannot be undone.')">
            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inventory-menu-item inventory-menu-item-danger">Delete Permanently</button>
                                        </form>
                                        @else
                                        <button class="inventory-menu-item" type="button" data-inventory-item='@json($item)' onclick="openStockMovementFromButton(this); closeInventoryActionMenus()">Update Stock</button>
                                        <button class="inventory-menu-item" type="button" data-inventory-item='@json($item)' onclick="editItemFromButton(this); closeInventoryActionMenus()">Edit Details</button>
                                        <form method="POST" action="{{ route('inventory.archive', $item['id']) }}" onsubmit="return confirm('Archive this item?')">
            @csrf
                                            <button type="submit" class="inventory-menu-item inventory-menu-item-danger">Archive</button>
                                        </form>
                                        @endif
                                        </div>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($filteredItems))
                            <tr><td colspan="10" style="text-align: center; color: var(--text-muted); padding: 2rem;">{{ $showArchived ? 'No archived inventory items.' : 'No inventory items found.' }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-header">
                <span class="card-title">Recent Stock Updates</span>
                <a href="inventory.php" class="btn btn-sm btn-secondary">Back to Stock</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Date &amp; Time</th><th>Item</th>@if (! \App\Support\BranchScope::isRestricted())<th>Branch</th>@endif<th>Update Type</th><th>Quantity Change</th><th>Stock After</th><th>Employee</th><th>Client / NAP</th><th>Handled By</th><th>Notes</th></tr></thead>
                        <tbody>
                            @forelse ($movements as $movement)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($movement->created_at)->format('M d, Y h:i A') }}</td>
                                <td><strong>{{ $movement->item_name }}</strong><br><span style="color: var(--text-muted); font-size: .8rem;">{{ $movement->category }}</span></td>
                                @if (! \App\Support\BranchScope::isRestricted())<td>{{ $movement->branch_name }}</td>@endif
                                <td>{{ \App\Support\StockMovements::label($movement->movement_type) }}</td>
                                @if (in_array($movement->movement_type, ['job_used', 'job_damaged'], true))
                                {{-- Used on a job: the items left the stock when they were released, so this line only records where they went. --}}
                                <td style="color: var(--text-muted);">—</td>
                                @else
                                <td style="font-weight: 700; color: {{ $movement->quantity_change >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $movement->quantity_change >= 0 ? '+' : '' }}{{ $movement->quantity_change }}</td>
                                @endif
                                <td>{{ $movement->balance_after }}</td>
                                <td>{{ $movement->employee_name ?? '—' }}</td>
                                <td>{{ \App\Support\StockMovements::target($movement->client_name ?? null, $movement->client_code ?? null, $movement->nap_box_name ?? null) ?? '—' }}</td>
                                <td>{{ $movement->performed_by_name ?: 'System' }}</td>
                                <td style="max-width: 260px;">{{ $movement->remarks ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="10" style="text-align: center; color: var(--text-muted); padding: 2rem;">No stock updates recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="inventoryModal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title" id="modalTitle">Add Item</h3>
                </div>
                <button class="modal-close" onclick="closeModal('inventoryModal')">&times;</button>
            </div>
            <form method="POST" action="" id="inventoryForm" onsubmit="return submitInventoryForm(this)">
            @csrf
                <input type="hidden" name="item_id" id="itemId">
                <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                <input type="hidden" name="add_quantity" id="itemAddQuantity" value="0">
                <input type="hidden" name="reorder_level" id="itemReorder" value="5">
                <input type="hidden" name="supplier" id="itemSupplier" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Item Name *</label>
                        <input type="text" name="item_name" class="form-input" id="itemName" list="inventoryItemSuggestions" autocomplete="off" required oninput="validateInventoryItemName()">
                        <datalist id="inventoryItemSuggestions">
                            @foreach ($inventoryNameSuggestions as $itemName)
                            <option value="{{ $itemName }}"></option>
                            @endforeach
                        </datalist>
                        <small id="itemNameHelp" style="color: var(--text-muted);">Start typing to see common and existing item names.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category_select" class="form-input" id="categorySelect" required onchange="toggleCustomCategory()">
                            <option value="">Select Category</option>
                            @foreach ($modalCategories as $cat)
                            <option value="{{ htmlspecialchars($cat) }}">{{ htmlspecialchars($cat) }}</option>
                            @endforeach
                            <option value="__other__">Other (add new category)</option>
                        </select>
                        <input type="text" name="category" class="form-input" id="customCategory" placeholder="Enter new category name" style="display: none; margin-top: 0.5rem;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="itemQuantityLabel">Starting Stock *</label>
                        <input type="number" name="quantity" class="form-input" id="itemQuantity" required min="0">
                        <small id="itemQuantityHelp" style="display:none;color:var(--text-muted);">This amount will be added to the current stock and saved in the stock update history.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price (₱)</label>
                        <input type="number" name="unit_price" class="form-input" id="itemPrice" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" id="itemDescription" rows="2"></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('inventoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-success" id="saveInventoryButton">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="stockMovementModal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Update Stock</h3>
                </div>
                <button class="modal-close" onclick="closeModal('stockMovementModal')">&times;</button>
            </div>
            <form method="POST" id="stockMovementForm">
                @csrf
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Item</label><input id="movementItemName" class="form-input calc-readonly" readonly></div>
                    <div class="form-group"><label class="form-label">Update Type *</label><select name="movement_type" id="movementType" class="form-input" onchange="syncMovementQuantityLabel()"><option value="stock_received">Receive Stock</option><option value="stock_issued">Release Stock</option><option value="stock_returned">Return Stock</option><option value="damaged">Damaged or Lost</option><option value="stock_count">Count Stock</option></select></div>
                    <div class="form-group"><label class="form-label" id="movementQuantityLabel">Quantity Received *</label><input type="number" name="quantity" id="movementQuantity" class="form-input" min="0" required></div>
                    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Reference No. / Notes *</label><textarea name="remarks" class="form-input" rows="3" required placeholder="Delivery receipt, job order, or reason for the stock update."></textarea></div>
                </div>
                <p style="margin:0;color:var(--text-muted);font-size:.8rem;">Each update changes the item stock and is saved in the history.</p>
                <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:1rem;"><button type="button" class="btn btn-secondary" onclick="closeModal('stockMovementModal')">Cancel</button><button type="submit" class="btn btn-success">Save Stock Update</button></div>
            </form>
        </div>
    </div>

    <script>
    function toggleCustomCategory() {
        const select = document.getElementById('categorySelect');
        const custom = document.getElementById('customCategory');
        if (select.value === '__other__') {
            custom.style.display = 'block';
            custom.required = true;
            custom.value = '';
        } else {
            custom.style.display = 'none';
            custom.required = false;
            custom.value = select.value;
        }
    }

    function editItem(item) {
        document.getElementById('modalTitle').textContent = 'Edit Item';
        document.getElementById('itemId').value = item.id;
        document.getElementById('itemName').value = item.item_name;
        validateInventoryItemName();
        const categorySelect = document.getElementById('categorySelect');
        const customCategory = document.getElementById('customCategory');
        if (categorySelect && customCategory) {
            const options = Array.from(categorySelect.options).map(o => o.value);
            if (options.includes(item.category)) {
                categorySelect.value = item.category;
                customCategory.style.display = 'none';
                customCategory.required = false;
                customCategory.value = item.category;
            } else {
                categorySelect.value = '__other__';
                customCategory.value = item.category;
                customCategory.style.display = 'block';
                customCategory.required = true;
            }
        }
        document.getElementById('itemAddQuantity').value = '1';
        document.getElementById('itemQuantity').value = '0';
        document.getElementById('itemQuantity').readOnly = false;
        document.getElementById('itemQuantity').classList.remove('calc-readonly');
        document.getElementById('itemQuantityLabel').textContent = 'Stock to Add';
        document.getElementById('itemQuantityHelp').style.display = 'block';
        document.getElementById('itemPrice').value = item.unit_price;
        document.getElementById('itemReorder').value = item.reorder_level;
        document.getElementById('itemSupplier').value = item.supplier;
        document.getElementById('itemDescription').value = item.description || '';
        openModal('inventoryModal');
    }

    function inventoryItemFromButton(button) {
        try {
            return JSON.parse(button.dataset.inventoryItem || '{}');
        } catch (error) {
            console.error('Unable to load inventory item details.', error);
            return null;
        }
    }

    function editItemFromButton(button) {
        const item = inventoryItemFromButton(button);
        if (item?.id) editItem(item);
    }

    function openStockMovementFromButton(button) {
        const item = inventoryItemFromButton(button);
        if (item?.id) openStockMovement(item);
    }
    function openNewItemModal() {
        document.getElementById('inventoryForm').reset();
        document.getElementById('modalTitle').textContent = 'Add Item';
        document.getElementById('itemId').value = '';
        document.getElementById('itemAddQuantity').value = '0';
        document.getElementById('itemNameHelp').textContent = 'Start typing to see common and existing item names.';
        document.getElementById('itemNameHelp').style.color = 'var(--text-muted)';
        document.getElementById('itemQuantity').readOnly = false;
        document.getElementById('itemQuantity').classList.remove('calc-readonly');
        document.getElementById('itemQuantityLabel').textContent = 'Starting Stock *';
        document.getElementById('itemQuantityHelp').style.display = 'none';
        toggleCustomCategory();
        openModal('inventoryModal');
    }

    function openStockMovement(item) {
        document.getElementById('movementItemName').value = item.item_name + ' (Stock: ' + item.quantity + ', reserved: ' + item.reserved_quantity + ')';
        document.getElementById('movementQuantity').value = '';
        document.getElementById('movementType').value = 'stock_received';
        document.getElementById('stockMovementForm').action = '{{ url('/inventory') }}/' + item.id + '/movement';
        syncMovementQuantityLabel();
        openModal('stockMovementModal');
    }
    function syncMovementQuantityLabel() {
        const labels = {stock_received: 'Quantity Received *', stock_issued: 'Quantity Released *', stock_returned: 'Quantity Returned *', damaged: 'Quantity Damaged or Lost *', stock_count: 'Actual Quantity Counted *'};
        document.getElementById('movementQuantityLabel').textContent = labels[document.getElementById('movementType').value];
    }
    function submitInventoryForm(form) {
        const categorySelect = document.getElementById('categorySelect');
        const category = document.getElementById('customCategory');
        const submitButton = document.getElementById('saveInventoryButton');
        category.value = categorySelect.value === '__other__' ? category.value.trim() : categorySelect.value;
        if (!validateInventoryItemName() || submitButton.disabled) return false;
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';
        return true;
    }

    const existingInventoryNames = @json($existingInventoryNames);

    function normalizeInventoryItemName(value) {
        return String(value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase();
    }

    function validateInventoryItemName() {
        const input = document.getElementById('itemName');
        const help = document.getElementById('itemNameHelp');
        const saveButton = document.getElementById('saveInventoryButton');
        const currentId = document.getElementById('itemId').value;
        const name = normalizeInventoryItemName(input.value);
        const duplicate = name !== '' && !currentId && existingInventoryNames.some(item => normalizeInventoryItemName(item) === name);

        input.setCustomValidity(duplicate ? 'This inventory item already exists.' : '');
        help.textContent = duplicate
            ? 'This item is already listed. Choose it from the suggestions or select Update Stock to add more.'
            : 'Start typing to see common and existing item names.';
        help.style.color = duplicate ? 'var(--danger)' : 'var(--text-muted)';
        saveButton.disabled = duplicate;
        return !duplicate;
    }
    function closeInventoryActionMenus() {
        document.querySelectorAll('.inventory-action-menu-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
            if (menu._inventoryMenuPlaceholder) {
                menu.removeAttribute('style');
                menu._inventoryMenuPlaceholder.replaceWith(menu);
                delete menu._inventoryMenuPlaceholder;
            }
        });
        document.querySelectorAll('.inventory-action-menu-btn[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    }
    function toggleInventoryActionMenu(button) {
        const menu = button.nextElementSibling;
        const willOpen = !menu.classList.contains('show');
        closeInventoryActionMenus();
        if (!willOpen) return;
        const placeholder = document.createComment('inventory action menu');
        menu.parentNode.replaceChild(placeholder, menu);
        menu._inventoryMenuPlaceholder = placeholder;
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
        if (!event.target.closest('.inventory-action-menu') && !event.target.closest('.inventory-action-menu-dropdown')) closeInventoryActionMenus();
    });
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
