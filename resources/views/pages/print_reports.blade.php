@extends('layouts.print_report')

@php
    $reportTitle = $reportName;
    $reportCoverage = $rangeLabel;
    $reportDetails = ['View Type' => ucfirst($reportViewType)];
    $completionRate = $summary['total'] > 0 ? round($summary['completed'] / $summary['total'] * 100, 1) : 0;
    $overdueRate = $summary['total'] > 0 ? round($summary['overdue'] / $summary['total'] * 100, 1) : 0;
    $perPeriod = $performance ? ($performance['period_type'] === 'day' ? 'Day' : 'Month') : '';
    // The TOTAL row adds up the rows printed below it.
    $listedAmount = array_sum(array_map(fn ($act) => (float) $act['amount'], $records));
    $listedQuantity = array_sum(array_map(fn ($act) => (int) $act['quantity'], $records));
    $change = fn ($key) => isset($percentageChanges[$key]) ? number_format($percentageChanges[$key], 2) . '%' : 'N/A';
@endphp

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Entries' => number_format($summary['total']),
        'Completed' => number_format($summary['completed']),
        'Pending' => number_format($summary['pending']),
        'Cancelled' => number_format($summary['cancelled']),
        'Overdue' => number_format($summary['overdue']),
    ]])
    @include('partials.report_summary', ['items' => array_filter([
        'Total Amount' => '₱' . number_format((float) $summary['amount'], 2),
        'Total Quantity' => number_format((int) $summary['quantity']),
        'Completion Rate' => $completionRate . '%',
        'Overdue Rate' => $overdueRate . '%',
        "Average Entries per {$perPeriod}" => $performance ? number_format($performance['avg_per_day'], 1) : null,
        "Average Amount per {$perPeriod}" => $performance ? '₱' . number_format($performance['avg_amount_per_day'], 2) : null,
    ], fn ($value) => $value !== null)])

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

    @if ($dailyBreakdown)
        <div class="section-heading">Daily Breakdown</div>
        <table class="report-table">
            <thead><tr><th>Date</th><th class="num">Total</th><th class="num">Completed</th><th class="num">Pending</th><th class="num">Cancelled</th><th class="num">Overdue</th><th class="num">Amount</th><th class="num">Quantity</th></tr></thead>
            <tbody>
                @foreach ($dailyBreakdown as $day)
                    <tr><td>{{ date('F j, Y', strtotime($day['day'])) }}</td><td class="num">{{ number_format($day['total']) }}</td><td class="num">{{ number_format($day['completed']) }}</td><td class="num">{{ number_format($day['pending']) }}</td><td class="num">{{ number_format($day['cancelled']) }}</td><td class="num">{{ number_format($day['overdue']) }}</td><td class="num">{{ $day['amount'] > 0 ? '₱' . number_format((float) $day['amount'], 2) : '—' }}</td><td class="num">{{ $day['quantity'] > 0 ? number_format((int) $day['quantity']) : '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($monthlyBreakdown)
        <div class="section-heading">Monthly Breakdown — {{ $selectedYear }}</div>
        <table class="report-table">
            <thead><tr><th>Month</th><th class="num">Total</th><th class="num">Completed</th><th class="num">Pending</th><th class="num">Cancelled</th><th class="num">Overdue</th><th class="num">Amount</th><th class="num">Quantity</th></tr></thead>
            <tbody>
                @foreach ($monthlyBreakdown as $m)
                    <tr><td>{{ $m['label'] }}</td><td class="num">{{ number_format($m['total']) }}</td><td class="num">{{ number_format($m['completed']) }}</td><td class="num">{{ number_format($m['pending']) }}</td><td class="num">{{ number_format($m['cancelled']) }}</td><td class="num">{{ number_format($m['overdue']) }}</td><td class="num">{{ $m['amount'] > 0 ? '₱' . number_format((float) $m['amount'], 2) : '—' }}</td><td class="num">{{ $m['quantity'] > 0 ? number_format((int) $m['quantity']) : '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-heading">Detailed Records</div>
    <table class="report-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Date &amp; Time</th>
                <th>Record No.</th>
                <th>Record Type</th>
                <th>Activity</th>
                <th>Name</th>
                <th>Status</th>
                <th class="num">Amount</th>
                <th class="num">Quantity</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $act)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>{{ $act['activity_datetime'] ? date('M j, Y h:i A', strtotime($act['activity_datetime'])) : '—' }}</td>
                    <td>{{ $act['record_id'] }}</td>
                    <td>{{ $reportTypes[$act['report_type']] ?? ucfirst(str_replace('_', ' ', $act['report_type'])) }}</td>
                    <td>{{ $act['activity_type'] }}</td>
                    <td>{{ $act['person'] ?: '—' }}</td>
                    <td>{{ ucfirst((string) $act['status']) }}</td>
                    <td class="num">{{ $act['amount'] !== null ? '₱' . number_format((float) $act['amount'], 2) : '—' }}</td>
                    <td class="num">{{ $act['quantity'] !== null ? number_format((int) $act['quantity']) : '—' }}</td>
                    <td>{{ $act['remarks'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">No records found for the selected period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">TOTAL ({{ number_format(count($records)) }} {{ count($records) === 1 ? 'entry' : 'entries' }})</td>
                <td class="num">₱{{ number_format($listedAmount, 2) }}</td>
                <td class="num">{{ number_format($listedQuantity) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@endsection
