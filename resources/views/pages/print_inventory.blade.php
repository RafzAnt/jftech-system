@extends('layouts.print_report')

@php
    $reportTitle = 'Inventory Report';
    $reportCoverage = 'As of ' . \App\Support\ReportFormat::date(now());
    $reportBranch = \App\Support\ReportFormat::branch($scopeBranch);
    $reportDetails = $categoryLabel ? ['Category' => $categoryLabel] : [];
    $stockColumns = [['count', 'No. of Items', 'int'], ['quantity', 'Quantity', 'int'], ['value', 'Total Value', 'peso']];
@endphp

@section('content')

    @if (! $categoryLabel)
        @include('partials.report_breakdown', [
            'heading' => 'By Category', 'labelHeading' => 'Category', 'groups' => $byCategory, 'share' => 'value',
            'columns' => $stockColumns, 'empty' => 'No inventory items found.',
        ])
    @endif
    @include('partials.report_totals', ['items' => [
        'Total Items' => number_format($totalItems),
        'Total Quantity' => number_format($totalQuantity),
        'Total Value' => '₱' . number_format($totalValue, 2),
        'Low Stock Items' => number_format($lowStockItems),
    ]])
@endsection
