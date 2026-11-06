@extends('layouts.print_report')

@php
    $reportTitle = $reportName;
    $reportCoverage = $rangeLabel;
    $reportDetails = ['View Type' => ucfirst($reportViewType)];
    $completionRate = $summary['total'] > 0 ? round($summary['completed'] / $summary['total'] * 100, 1) : 0;
    $overdueRate = $summary['total'] > 0 ? round($summary['overdue'] / $summary['total'] * 100, 1) : 0;
    $perPeriod = $performance ? ($performance['period_type'] === 'day' ? 'Day' : 'Month') : '';
    $change = fn ($key) => isset($percentageChanges[$key]) ? number_format($percentageChanges[$key], 2) . '%' : 'N/A';
@endphp

@section('content')
    @if ($previousSummary && $reportViewType !== 'daily' && $reportViewType !== 'custom')
        <div class="section-heading">Comparison with Previous Period ({{ $comparisonPeriod }})</div>
        <table class="report-table">
            <thead><tr><th>Metric</th><th class="num">Current</th><th class="num">Previous</th><th class="num">Change (%)</th></tr></thead>
            <tbody>
                @foreach (['total' => 'Total Entries', 'completed' => 'Completed', 'pending' => 'Pending', 'cancelled' => 'Cancelled', 'overdue' => 'Overdue'] as $key => $label)
                    <tr><td>{{ $label }}</td><td class="num">{{ number_format($summary[$key]) }}</td><td class="num">{{ number_format($previousSummary[$key]) }}</td><td class="num">{{ $change($key) }}</td></tr>
                @endforeach
                <tr><td>Total Amount</td><td class="num">₱{{ number_format((float) $summary['amount'], 2) }}</td><td class="num">₱{{ number_format((float) $previousSummary['amount'], 2) }}</td><td class="num">{{ $change('amount') }}</td></tr>
            </tbody>
        </table>
    @endif

    @include('partials.report_breakdown', [
        'heading' => 'By Record Type', 'labelHeading' => 'Record Type', 'groups' => $byRecordType,
        'columns' => [['count', 'Total', 'int'], ['completed', 'Completed', 'int'], ['pending', 'Pending', 'int'], ['cancelled', 'Cancelled', 'int'], ['overdue', 'Overdue', 'int'], ['amount', 'Amount', 'peso'], ['quantity', 'Quantity', 'int']],
        'empty' => 'No records found for the selected period.', 'totalLabel' => 'TOTAL ENTRIES',
    ])
    @include('partials.report_totals', ['items' => array_filter([
        'Total Entries' => number_format($summary['total']),
        'Completed' => number_format($summary['completed']),
        'Pending' => number_format($summary['pending']),
        'Cancelled' => number_format($summary['cancelled']),
        'Overdue' => number_format($summary['overdue']),
        'Total Amount' => '₱' . number_format((float) $summary['amount'], 2),
        'Total Quantity' => number_format((int) $summary['quantity']),
        'Completion Rate' => $completionRate . '%',
        'Overdue Rate' => $overdueRate . '%',
        "Average Entries per {$perPeriod}" => $performance ? number_format($performance['avg_per_day'], 1) : null,
        "Average Amount per {$perPeriod}" => $performance ? '₱' . number_format($performance['avg_amount_per_day'], 2) : null,
    ], fn ($value) => $value !== null)])
@endsection
