@php
    $currencyCode = strtoupper(trim((string) ($currency ?? 'PHP')));
    $currencySymbol = $currencyCode === 'PHP' ? '₱' : $currencyCode;
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode . ' ';
@endphp
<div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem;">
    <button class="btn btn-success" onclick="openModal('schedulePriceModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Schedule New Discount
    </button>
    <span style="color: var(--text-muted); font-size: 0.85rem;">{{ count($discounts ?? []) }} product(s) have active discounts</span>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Discounts</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Product Code</th>
                        <th>Original Price</th>
                        <th>Discount Type</th>
                        <th>Discount Value</th>
                        <th>Final Price</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($discounts ?? []) as $discount)
                    @php
                        $statusClass = \App\Models\Price::statusColor($discount['status'] ?? 'inactive');
                    @endphp
                    <tr data-price-id="{{ $discount['id'] }}">
                        <td><strong>{{ htmlspecialchars($discount['product_name'] ?? 'N/A') }}</strong></td>
                        <td>{{ htmlspecialchars($discount['sku'] ?? 'N/A') }}</td>
                        <td>{{ $currencyPrefix }}{{ number_format((float)($discount['base_price'] ?? 0), 2) }}</td>
                        <td>{{ ucfirst($discount['discount_type'] ?? 'none') }}</td>
                        <td>
                            @if ($discount['discount_type'] === 'percentage')
                                {{ $discount['discount_value'] }}%
                            @else
                                {{ $currencyPrefix }}{{ number_format((float)($discount['discount_value'] ?? 0), 2) }}
                            @endif
                        </td>
                        <td style="font-weight: 600; color: var(--accent);">{{ $currencyPrefix }}{{ number_format((float)($discount['final_price'] ?? 0), 2) }}</td>
                        <td>{{ $discount['effective_from'] ? date('M d, Y', strtotime($discount['effective_from'])) : '-' }}</td>
                        <td>{{ $discount['effective_until'] ? date('M d, Y', strtotime($discount['effective_until'])) : '-' }}</td>
                        <td>
                            <span class="badge badge-{{ $statusClass }}">{{ ucfirst($discount['status'] ?? 'unknown') }}</span>
                        </td>
                        <td>
                            <div class="action-menu">
                                <button class="action-menu-btn" onclick="toggleActionMenu(this)">
                                    Actions
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.3rem;"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                                <div class="action-menu-dropdown">
                                    <button class="dropdown-item" onclick="openDiscountModal({{ htmlspecialchars(json_encode($discount)) }})">Edit Discount</button>
                                    <form method="POST" action="price_management.php" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="action" value="update_discount">
                                        <input type="hidden" name="price_id" value="{{ $discount['id'] }}">
                                        <input type="hidden" name="discount_type" value="none">
                                        <input type="hidden" name="discount_value" value="0">
                                        <input type="hidden" name="reason" value="Discount removed">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Remove this discount?')">Remove Discount</button>
                                    </form>
                                    <a href="/price-management/prices/{{ $discount['id'] }}" class="dropdown-item">View Details</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:2rem;">No discounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
