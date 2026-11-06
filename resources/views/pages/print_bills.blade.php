@extends('layouts.print_report')

@php
    $reportTitle = 'Billing Report';
    $reportCoverage = $periodLabel;
    $reportDetails = $statusLabel ? ['Status' => $statusLabel] : [];
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
@endphp

@section('content')
    @include('partials.report_totals', ['items' => [
        'Total Bills' => [number_format($totalBills), number_format($clientsBilled) . ' client(s)'],
        'Total Amount' => $peso($totalAmount),
        'Paid' => $peso($totalPaid),
        'Pending' => $peso($totalPending),
        'Overdue' => $peso($totalOverdue),
    ]])
@endsection
