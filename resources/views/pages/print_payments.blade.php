@extends('layouts.print_report')

@php
    $reportTitle = 'Payment Collection Report';
    $reportCoverage = $rangeLabel;
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
@endphp

@section('content')

    @include('partials.report_breakdown', [
        'heading' => 'By Payment Method', 'labelHeading' => 'Payment Method', 'groups' => $byMethod, 'share' => 'amount',
        'columns' => [['count', 'No. of Payments', 'int'], ['amount', 'Amount', 'peso']],
        'empty' => 'No payment transactions found for the selected period.', 'totalLabel' => 'TOTAL COLLECTED',
    ])
    @include('partials.report_totals', ['items' => [
        'Total Transactions' => number_format($count),
        'Total Amount Collected' => $peso($total),
        'Clients Who Paid' => number_format($clientsPaid),
        'Average Payment' => $count > 0 ? $peso($total / $count) : '—',
    ]])
@endsection
