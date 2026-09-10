<div class="card">
    <div class="card-header">
        <span class="card-title">Scheduled Prices</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Current Price</th>
                        <th>Scheduled Price</th>
                        <th>Effective From</th>
                        <th>Effective Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($scheduled ?? []) as $item)
                    @php
                        $statusClass = \App\Models\Price::statusColor($item['status'] ?? 'scheduled');
                    @endphp
                    <tr data-price-id="{{ $item['id'] }}">
                        <td><strong>{{ htmlspecialchars($item['product_name'] ?? 'N/A') }}</strong></td>
                        <td>{{ htmlspecialchars($item['sku'] ?? 'N/A') }}</td>
                        <td>{{ htmlspecialchars($item['category'] ?? 'N/A') }}</td>
                        <td>{{ $currency }} {{ number_format((float)($item['base_price'] ?? 0), 2) }}</td>
                        <td style="font-weight: 600; color: var(--accent);">{{ $currency }} {{ number_format((float)($item['final_price'] ?? 0), 2) }}</td>
                        <td>{{ $item['effective_from'] ? date('M d, Y', strtotime($item['effective_from'])) : '-' }}</td>
                        <td>{{ $item['effective_until'] ? date('M d, Y', strtotime($item['effective_until'])) : '-' }}</td>
                        <td>
                            <span class="badge badge-{{ $statusClass }}">{{ ucfirst($item['status'] ?? 'unknown') }}</span>
                        </td>
                        <td>
                            <div class="action-menu">
                                <button class="action-menu-btn" onclick="toggleActionMenu(this)">
                                    Actions
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.3rem;"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                                <div class="action-menu-dropdown">
                                    <a href="/price-management/prices/{{ $item['id'] }}" class="dropdown-item">View</a>
                                    <button class="dropdown-item" onclick="openEditPriceModal({{ htmlspecialchars(json_encode($item)) }})">Edit</button>
                                    <form method="POST" action="price_management.php" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="action" value="activate_price">
                                        <input type="hidden" name="price_id" value="{{ $item['id'] }}">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Activate this scheduled price now?')">Activate Now</button>
                                    </form>
                                    <form method="POST" action="price_management.php" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="action" value="deactivate_price">
                                        <input type="hidden" name="price_id" value="{{ $item['id'] }}">
                                        <input type="hidden" name="reason" value="Cancelled schedule">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Deactivate this scheduled price?')" style="color: var(--danger);">Cancel Schedule</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">No scheduled prices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
