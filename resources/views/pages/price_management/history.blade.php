@php
    $historyFilters = $filters ?? [];
@endphp

<!-- History Filters -->
<div style="margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="view" value="history">

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <input type="text" name="product_id" class="form-input" placeholder="Product ID" value="{{ htmlspecialchars($historyFilters['product_id'] ?? '') }}" style="font-size: 0.85rem; padding: 0.5rem;">
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <input type="date" name="date_from" class="form-input" value="{{ $historyFilters['date_from'] ?? '' }}" style="font-size: 0.85rem; padding: 0.5rem;">
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <input type="date" name="date_to" class="form-input" value="{{ $historyFilters['date_to'] ?? '' }}" style="font-size: 0.85rem; padding: 0.5rem;">
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <select name="admin_id" class="form-select" style="font-size: 0.85rem; padding: 0.5rem;">
                <option value="">All Admins</option>
                @foreach (($admins ?? []) as $admin)
                <option value="{{ $admin->id }}" {{ ($historyFilters['admin_id'] ?? '') == $admin->id ? 'selected' : '' }}>{{ htmlspecialchars($admin->full_name) }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <a href="price_management.php?view=history" class="btn btn-sm btn-secondary">Reset</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Price Change History</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Previous Price</th>
                        <th>New Price</th>
                        <th>Difference</th>
                        <th>%</th>
                        <th>Previous Discount</th>
                        <th>New Discount</th>
                        <th>Reason</th>
                        <th>Changed By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($history ?? []) as $item)
                    @php
                        $diff = ($item['new_price'] ?? 0) - ($item['previous_price'] ?? 0);
                        $diffClass = $diff >= 0 ? 'price-diff-positive' : 'price-diff-negative';
                        $diffSign = $diff >= 0 ? '+' : '';
                        $prevDiscount = $item['previous_discount_type'] ?? 'none';
                        $newDiscount = $item['new_discount_type'] ?? 'none';
                        $prevDiscountValue = $prevDiscount !== 'none' ? ($prevDiscount === 'percentage' ? number_format((float)$item['previous_discount_value'], 2) . '%' : $currency . ' ' . number_format((float)$item['previous_discount_value'], 2) . ' OFF') : 'No Discount';
                        $newDiscountValue = $newDiscount !== 'none' ? ($newDiscount === 'percentage' ? number_format((float)$item['new_discount_value'], 2) . '%' : $currency . ' ' . number_format((float)$item['new_discount_value'], 2) . ' OFF') : 'No Discount';
                    @endphp
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $item['changed_at'] ? date('M d, Y h:i A', strtotime($item['changed_at'])) : '-' }}</td>
                        <td><strong>{{ htmlspecialchars($item['product_name'] ?? 'N/A') }}</strong></td>
                        <td>{{ htmlspecialchars($item['sku'] ?? 'N/A') }}</td>
                        <td>{{ $currency }} {{ number_format((float)($item['previous_price'] ?? 0), 2) }}</td>
                        <td>{{ $currency }} {{ number_format((float)($item['new_price'] ?? 0), 2) }}</td>
                        <td class="{{ $diffClass }}">{{ $diffSign }}{{ $currency }} {{ number_format($diff, 2) }}</td>
                        <td class="{{ $diffClass }}">{{ $diffSign }}{{ number_format((float)($item['change_percentage'] ?? 0), 2) }}%</td>
                        <td>{{ $prevDiscountValue }}</td>
                        <td>{{ $newDiscountValue }}</td>
                        <td>{{ htmlspecialchars($item['reason'] ?? '-') }}</td>
                        <td>{{ htmlspecialchars($item['admin_name'] ?? 'N/A') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:2rem;">No price history found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if (isset($historyFilters['product_id']) && !empty($historyFilters['product_id']))
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <span class="card-title">Pricing Timeline</span>
    </div>
    <div class="card-body">
        <div class="timeline price-timeline">
            @foreach (($history ?? []) as $item)
            @if ($item['product_id'] == $historyFilters['product_id'])
            <div class="timeline-item">
                <div class="timeline-time">{{ $item['changed_at'] ? date('M d, Y h:i A', strtotime($item['changed_at'])) : '' }}</div>
                <div class="timeline-content">
                    <div style="font-weight: 600; display: flex; justify-content: space-between;">
                        <span>{{ $currency }} {{ number_format((float)($item['new_price'] ?? 0), 2) }}</span>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">{{ ucfirst($item['change_percentage'] ? 'Price Changed' : 'Initial Price') }}</span>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem;">
                        {{ htmlspecialchars($item['reason'] ?? '') }}
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
</div>
@endif
