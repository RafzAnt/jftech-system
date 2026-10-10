@php
    $currencyCode = strtoupper(trim((string) ($currency ?? 'PHP')));
    $currencySymbol = $currencyCode === 'PHP' ? '₱' : $currencyCode;
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode . ' ';
@endphp
@php
    $statCards = [
        ['label' => 'Total Products', 'value' => number_format($stats['total_products'] ?? 0), 'color' => 'var(--accent)'],
        ['label' => 'Products With Prices', 'value' => number_format($stats['products_with_prices'] ?? 0), 'color' => 'var(--success)'],
        ['label' => 'Products Without Prices', 'value' => number_format($stats['products_without_prices'] ?? 0), 'color' => 'var(--warning)'],
        ['label' => 'Active Prices', 'value' => number_format($stats['active_prices'] ?? 0), 'color' => 'var(--success)'],
        ['label' => 'Products With Discounts', 'value' => number_format($stats['products_with_discounts'] ?? 0), 'color' => 'var(--info)'],
        ['label' => 'Scheduled Prices', 'value' => number_format($stats['scheduled_prices'] ?? 0), 'color' => 'var(--accent-secondary)'],
        ['label' => 'Expired Prices', 'value' => number_format($stats['expired_prices'] ?? 0), 'color' => 'var(--danger)'],
    ];
@endphp

<!-- Stats Grid -->
<div class="stats-grid">
    @foreach ($statCards as $card)
    <div class="stat-card" style="--stat-color: {{ $card['color'] }};">
        <div class="stat-value" style="color: {{ $card['color'] }};">{{ $card['value'] }}</div>
        <div class="stat-label">{{ $card['label'] }}</div>
    </div>
    @endforeach
</div>

<!-- Pricing Actions -->
<div class="quick-actions">
    <button class="btn btn-success" onclick="openModal('addPriceModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Price
    </button>
    <button class="btn btn-primary" onclick="openModal('changePriceModal')" data-product-select="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M15.41 14l-5.41 5.41A2 2 0 0 1 9 18V5a2 2 0 0 1 2-2h7a2 2 0 0 1 1.42 3.42L11 12V8l5.41 5.41z"/></svg></svg>
        Change Price
    </button>
    <button class="btn btn-info" onclick="openModal('schedulePriceModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Schedule Price Change
    </button>
    <button class="btn btn-warning" onclick="openModal('bulkUpdateModal')" style="color: var(--primary);">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.89 1.78L3 12v8h2l1.5-3h8.41l2.58-5.17L8 5.5V3H3z"/></svg>
        Update Multiple Prices
    </button>
</div>

<!-- Recent Price Changes + Upcoming Price Changes -->
<div class="row-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Recent Price Changes -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Price Changes</span>
            <a href="price_management.php?view=history" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Previous</th>
                            <th>New</th>
                            <th>Difference</th>
                            <th>Changed By</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($recentChanges ?? []) as $change)
                        @php
                            $diff = ($change['new_price'] ?? 0) - ($change['previous_price'] ?? 0);
                            $diffClass = $diff >= 0 ? 'price-diff-positive' : 'price-diff-negative';
                            $diffSign = $diff >= 0 ? '+' : '';
                        @endphp
                        <tr>
                            <td><strong>{{ htmlspecialchars($change['product_name'] ?? 'N/A') }}</strong></td>
                            <td>{{ $change['previous_price'] ? $currencyPrefix . number_format($change['previous_price'], 2) : '-' }}</td>
                            <td>{{ $change['new_price'] ? $currencyPrefix . number_format($change['new_price'], 2) : '-' }}</td>
                            <td class="{{ $diffClass }}">{{ $diffSign }}{{ $currencyPrefix }}{{ number_format($diff, 2) }}</td>
                            <td>{{ htmlspecialchars($change['admin_name'] ?? 'N/A') }}</td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">{{ date('M d, Y h:i A', strtotime($change['changed_at'])) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No price changes recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Upcoming Price Changes -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Upcoming Price Changes</span>
            <a href="price_management.php?view=scheduled" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Price</th>
                            <th>Scheduled Price</th>
                            <th>Start Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($upcomingChanges ?? []) as $upcoming)
                        @php
                            $currentPrice = $upcoming['base_price'] ?? 0;
                            $scheduledPrice = $currentPrice;
                        @endphp
                        <tr>
                            <td><strong>{{ htmlspecialchars($upcoming['product_name'] ?? 'N/A') }}</strong></td>
                            <td>{{ $currencyPrefix }}{{ number_format((float)($upcoming['base_price'] ?? 0), 2) }}</td>
                            <td>{{ $currencyPrefix }}{{ number_format((float)($upcoming['base_price'] ?? 0), 2) }}</td>
                            <td>{{ date('M d, Y', strtotime($upcoming['effective_from'] ?? '')) }}</td>
                            <td>
                                <span class="badge badge-info">Scheduled</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No upcoming price changes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
