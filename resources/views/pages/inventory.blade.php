<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Inventory</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}?{{ $inventoryCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">{{ $tab === 'movements' ? 'Stock Movement History' : 'Inventory' }}</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $tab === 'movements' ? 'Auditable record of every stock change' : 'Manage equipment and supplies' }}</p>
            </div>
            @if (!$showArchived && $tab === 'stock')
            <button class="btn btn-success" onclick="openModal('inventoryModal')">+ Add Item</button>
            @endif
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
                <div class="stat-value" style="color: var(--success);">PHP {{ number_format($totalValue, 2) }}</div>
                <div class="stat-label">Total Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--warning);">{{ $lowStock }}</div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>

        <!-- Category Quick Filters -->
        @if (!$showArchived && !empty($categories))
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body" style="padding: 1rem;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">CATEGORIES:</span>
                    <a href="inventory.php{{ $showArchived ? '?view=archived' : '' }}" 
                       class="btn btn-sm {{ !$filterCategory ? 'btn-primary' : 'btn-secondary' }}">
                        All
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
                    </div>
                    <a class="btn btn-sm btn-secondary" target="_blank" rel="noopener" href="{{ url('/pages/print_inventory.php?category=' . urlencode($filterCategory) . ($showArchived ? '&archived=1' : '')) }}">Print</a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filteredItems as $item)
                            @php
$isLowStock = $item['quantity'] <= $item['reorder_level']; 
@endphp
                            <tr class="{{ $isLowStock ? 'row-warning' : '' }}">
                                <td><strong>{{ htmlspecialchars($item['item_name']) }}</strong></td>
                                <td>{{ $item['category'] }}</td>
                                <td>
                                    <span style="color: {{ $isLowStock ? 'var(--warning)' : 'inherit' }}; font-weight: 600;">
                                        {{ $item['quantity'] }}
                                    </span>
                                    @if ($isLowStock)
                                    <span class="badge badge-warning" style="margin-left: 0.5rem;">Low Stock</span>
                                    @endif
                                </td>
                                <td>PHP {{ number_format($item['unit_price'], 2) }}</td>
                                <td style="font-weight: 600;">PHP {{ number_format($item['quantity'] * $item['unit_price'], 2) }}</td>
                                <td>{{ htmlspecialchars($item['supplier'] ?: 'N/A') }}</td>
                                <td>
                                    <span class="badge badge-{{ $item['status'] === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($item['status']) }}
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                        @if ($showArchived)
                                        <form method="POST" action="{{ route('inventory.restore', $item['id']) }}" style="display: inline;">
            @csrf
                                            <button type="submit" class="btn btn-sm btn-success btn-icon">Restore</button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory.destroy', $item['id']) }}" style="display: inline;" onsubmit="return confirm('Permanently delete this archived item? This cannot be undone.')">
            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger btn-icon">Del</button>
                                        </form>
                                        @else
                                        <button class="btn btn-sm btn-primary btn-icon" onclick="editItem({{ htmlspecialchars(json_encode($item)) }})">Edit</button>
                                        <form method="POST" action="{{ route('inventory.archive', $item['id']) }}" style="display: inline;" onsubmit="return confirm('Archive this item?')">
            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger">Archive</button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($filteredItems))
                            <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">{{ $showArchived ? 'No archived inventory items.' : 'No inventory items found.' }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-header">
                <span class="card-title">Latest Stock Movements</span>
                <a href="inventory.php" class="btn btn-sm btn-secondary">Back to Stock</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Date / Time</th><th>Item</th><th>Movement</th><th>Change</th><th>Balance After</th><th>Processed By</th><th>Remarks</th></tr></thead>
                        <tbody>
                            @forelse ($movements as $movement)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($movement->created_at)->format('M d, Y h:i A') }}</td>
                                <td><strong>{{ $movement->item_name }}</strong><br><span style="color: var(--text-muted); font-size: .8rem;">{{ $movement->category }}</span></td>
                                <td>{{ ucwords(str_replace('_', ' ', $movement->movement_type)) }}</td>
                                <td style="font-weight: 700; color: {{ $movement->quantity_change >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $movement->quantity_change >= 0 ? '+' : '' }}{{ $movement->quantity_change }}</td>
                                <td>{{ $movement->balance_after }}</td>
                                <td>{{ $movement->performed_by_name ?: 'System' }}</td>
                                <td style="max-width: 260px;">{{ $movement->remarks ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No stock movements recorded yet.</td></tr>
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
            <form method="POST" action="">
            @csrf
                <input type="hidden" name="item_id" id="itemId">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Item Name *</label>
                        <input type="text" name="item_name" class="form-input" id="itemName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category_select" class="form-input" id="categorySelect" required onchange="toggleCustomCategory()">
                            <option value="">Select Category</option>
                            @foreach ($categories as $cat)
                            <option value="{{ htmlspecialchars($cat) }}">{{ htmlspecialchars($cat) }}</option>
                            @endforeach
                            <option value="__other__">Other (add new category)</option>
                        </select>
                        <input type="text" name="category" class="form-input" id="customCategory" placeholder="Enter new category name" style="display: none; margin-top: 0.5rem;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="quantity" class="form-input" id="itemQuantity" required min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price (PHP)</label>
                        <input type="number" name="unit_price" class="form-input" id="itemPrice" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-input" id="itemReorder" value="5" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-input" id="itemSupplier">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" id="itemDescription" rows="2"></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('inventoryModal')">Cancel</button>
                    <button type="submit" name="save_item" class="btn btn-success">Save Item</button>
                </div>
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
        document.getElementById('itemQuantity').value = item.quantity;
        document.getElementById('itemPrice').value = item.unit_price;
        document.getElementById('itemReorder').value = item.reorder_level;
        document.getElementById('itemSupplier').value = item.supplier;
        document.getElementById('itemDescription').value = item.description || '';
        openModal('inventoryModal');
    }
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
