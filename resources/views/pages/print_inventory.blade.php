@extends('layouts.print_report')

@php
    $reportTitle = 'Inventory Stock Report';
    $reportCoverage = 'As of ' . \App\Support\ReportFormat::date(now());
    $reportBranch = \App\Support\ReportFormat::branch($scopeBranch);
    $reportDetails = $categoryLabel ? ['Category' => $categoryLabel] : [];
@endphp

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Items' => number_format($totalItems),
        'Total Quantity' => number_format($totalQuantity),
        'Total Value' => '₱' . number_format($totalValue, 2),
        'Low Stock Items' => number_format($lowStockItems),
    ]])

    <table class="report-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Supplier</th>
                <th>Status</th>
                <th class="num">Quantity</th>
                <th class="num">Unit Price</th>
                <th class="num">Total Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inventoryItems as $item)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['category'] }}</td>
                    <td>{{ $item['supplier'] ?: '—' }}</td>
                    <td>{{ ucfirst($item['status']) }}</td>
                    <td class="num">{{ number_format((int) $item['quantity']) }}</td>
                    <td class="num">₱{{ number_format((float) $item['unit_price'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $item['quantity'] * (float) $item['unit_price'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No inventory items found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">TOTAL ({{ number_format($totalItems) }} {{ $totalItems === 1 ? 'item' : 'items' }})</td>
                <td class="num">{{ number_format($totalQuantity) }}</td>
                <td></td>
                <td class="num">₱{{ number_format($totalValue, 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
