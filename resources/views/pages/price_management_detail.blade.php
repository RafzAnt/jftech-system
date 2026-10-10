@php
    $priceCurrencyCode = strtoupper(trim((string) ($price['currency'] ?? $currency ?? 'PHP')));
    $priceCurrencyDisplay = $priceCurrencyCode === 'PHP' ? '₱' : $priceCurrencyCode . ' ';
    $priceCurrencyName = $priceCurrencyCode === 'PHP' ? 'Philippine Peso (PHP)' : $priceCurrencyCode;
@endphp
<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>JFTECH - Price Record</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/price_management.css') }}?{{ $priceCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>

    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Price Record</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Price record for {{ htmlspecialchars($price['product_name'] ?? 'Unknown Product') }}</p>
            </div>
            <a href="price_management.php?view=all" class="btn btn-sm btn-secondary">Back to Price List</a>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Product Details</span>
            </div>
            <div class="card-body">
                <div class="price-detail-grid">
                    <div>
                        <div class="product-image-placeholder" style="width: 100px; height: 100px; font-size: 2.5rem;">
                            📦
                        </div>
                    </div>
                    <div>
                        <table class="data-table" style="font-size: 0.9rem;">
                            <tr><th>Product Name</th><td>{{ htmlspecialchars($price['product_name'] ?? 'N/A') }}</td></tr>
                            <tr><th>Product Code</th><td>{{ htmlspecialchars($price['sku'] ?? 'N/A') }}</td></tr>
                            <tr><th>Category</th><td>{{ htmlspecialchars($price['category'] ?? 'N/A') }}</td></tr>
                            <tr><th>Description</th><td>{{ htmlspecialchars($price['product']['description'] ?? 'N/A') }}</td></tr>
                            <tr><th>Supplier</th><td>{{ htmlspecialchars($price['product']['supplier'] ?? 'N/A') }}</td></tr>
                            <tr><th>Stock Available</th><td>{{ ($price['product']['quantity'] ?? 0) }} units</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="price-detail-grid">
            <!-- Pricing Section -->
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <span class="card-title">Pricing</span>
                    </div>
                    <div class="card-body">
                        <table class="data-table" style="font-size: 0.9rem;">
                            <tr><th>Base Price</th><td>{{ $priceCurrencyDisplay }}{{ number_format((float)($price['base_price'] ?? 0), 2) }}</td></tr>
                            <tr><th>Discount</th><td>
                                @if (($price['discount_type'] ?? 'none') !== 'none' && (float)($price['discount_value'] ?? 0) > 0)
                                    @if ($price['discount_type'] === 'percentage')
                                        {{ $price['discount_value'] }}% OFF
                                    @else
                                        {{ $priceCurrencyDisplay }}{{ number_format((float)($price['discount_value'] ?? 0), 2) }} OFF
                                    @endif
                                @else
                                    No discount applied
                                @endif
                            </td></tr>
                            <tr><th>Final Price</th><td style="font-weight: 700; color: var(--accent);">{{ $priceCurrencyDisplay }}{{ number_format((float)($price['final_price'] ?? 0), 2) }}</td></tr>
                            <tr><th>Currency</th><td>{{ $priceCurrencyName }}</td></tr>
                            <tr><th>Status</th><td>
                                @php $statusClass = \App\Models\Price::statusColor($price['status'] ?? 'inactive'); @endphp
                                <span class="badge badge-{{ $statusClass }}">{{ ucfirst($price['status'] ?? 'unknown') }}</span>
                            </td></tr>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Price Schedule</span>
                    </div>
                    <div class="card-body">
                        <table class="data-table" style="font-size: 0.9rem;">
                            <tr><th>Start Date</th><td>{{ $price['effective_from'] ? date('M d, Y', strtotime($price['effective_from'])) : '-' }}</td></tr>
                            <tr><th>End Date</th><td>{{ $price['effective_until'] ? date('M d, Y', strtotime($price['effective_until'])) : 'No end date' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Record Information -->
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <span class="card-title">Record Details</span>
                    </div>
                    <div class="card-body">
                        <table class="data-table" style="font-size: 0.9rem;">
                            <tr><th>Created By</th><td>{{ htmlspecialchars($price['creator']->full_name ?? ($price['created_by'] ?? 'N/A')) }}</td></tr>
                            <tr><th>Created At</th><td>{{ $price['created_at'] ? date('M d, Y h:i A', strtotime($price['created_at'])) : '-' }}</td></tr>
                            <tr><th>Updated By</th><td>{{ htmlspecialchars($price['updater']->full_name ?? ($price['updated_by'] ?? 'N/A')) }}</td></tr>
                            <tr><th>Updated At</th><td>{{ $price['updated_at'] ? date('M d, Y h:i A', strtotime($price['updated_at'])) : '-' }}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Price Actions</span>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; padding: 1rem;">
                            <button class="btn btn-sm btn-primary" onclick="openChangePriceModal(@json($price))">Update Price</button>
                            <button class="btn btn-sm btn-info" onclick="openDiscountModal(@json($price))" style="justify-content: flex-start;">Set Discount</button>
                            <button class="btn btn-sm btn-warning" onclick="openScheduleModal(@json($price))" style="color: var(--primary); justify-content: flex-start;">Set Price Change Date</button>
                            @if (in_array($price['status'] ?? '', ['active', 'scheduled', 'draft']))
                            <form method="POST" action="price_management.php" style="display: inline;">
                                @csrf
                                <input type="hidden" name="action" value="deactivate_price">
                                <input type="hidden" name="price_id" value="{{ $price['id'] }}">
                                <input type="hidden" name="reason" value="Set inactive from details page">
                                <button type="submit" class="btn btn-sm btn-danger" style="justify-content: flex-start;" onclick="return confirm('Set this price as inactive?')">Set Inactive</button>
                            </form>
                            @endif
                            @if ($price['status'] === 'scheduled')
                            <form method="POST" action="price_management.php" style="display: inline;">
                                @csrf
                                <input type="hidden" name="action" value="activate_price">
                                <input type="hidden" name="price_id" value="{{ $price['id'] }}">
                                <button type="submit" class="btn btn-sm btn-success" style="justify-content: flex-start;">Set Active</button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price History Timeline -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Price Change History</span>
                <a href="price_management.php?view=history&product_id={{ $price['product_id'] }}" class="btn btn-sm btn-primary">View Full History</a>
            </div>
            <div class="card-body">
                <div class="timeline price-timeline">
                    @forelse (($history ?? []) as $h)
                    <div class="timeline-item">
                        <div class="timeline-time">{{ $h['changed_at'] ? date('M d, Y h:i A', strtotime($h['changed_at'])) : '' }}</div>
                        <div class="timeline-content">
                            <div style="font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
                                <span>
                                    @if ($h['previous_price'] && $h['new_price'])
                                        {{ $priceCurrencyDisplay }}{{ number_format((float)$h['previous_price'], 2) }} → {{ $priceCurrencyDisplay }}{{ number_format((float)$h['new_price'], 2) }}
                                    @else
                                        {{ $priceCurrencyDisplay }}{{ number_format((float)($h['new_price'] ?? 0), 2) }}
                                    @endif
                                </span>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">
                                    @if (($h['change_percentage'] ?? 0) != 0)
                                        {{ $h['change_percentage'] > 0 ? '+' : '' }}{{ $h['change_percentage'] }}%
                                    @else
                                        Initial Price Set
                                    @endif
                                </span>
                            </div>
                            <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem;">
                                {{ htmlspecialchars($h['reason'] ?? '') }}
                                @if ($h['admin_name'])
                                    <span style="margin-left: 0.5rem; color: var(--accent);">— {{ htmlspecialchars($h['admin_name']) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <div style="color: var(--text-muted); text-align: center; padding: 2rem;">No price change history available.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@include('pages.price_management.modals')

<script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
<script src="{{ asset('js/price-management.js') }}?{{ $priceCssVersion }}" data-currency="{{ $priceCurrencyCode }}"></script>
</body>
</html>
