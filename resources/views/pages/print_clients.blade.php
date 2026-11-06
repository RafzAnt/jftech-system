@extends('layouts.print_report')

@php
    $reportTitle = 'Client Report';
    $reportCoverage = 'As of ' . \App\Support\ReportFormat::date(now());
    $reportDetails = $barangayLabel ? ['Barangay' => $barangayLabel] : [];
    $clientColumns = [['count', 'No. of Clients', 'int'], ['rate', 'Monthly Rate', 'peso']];
@endphp

@section('content')

    @include('partials.report_breakdown', [
        'heading' => 'By Plan', 'labelHeading' => 'Plan', 'groups' => $byPlan, 'share' => 'count',
        'columns' => $clientColumns, 'empty' => 'No clients found.',
    ])
    @include('partials.report_totals', ['items' => [
        'Total Clients' => number_format($totalClients),
        'Active' => number_format($activeClients),
        'Inactive' => number_format($inactiveClients),
        'Suspended' => number_format($suspendedClients),
        'Total Monthly Rate' => '₱' . number_format($totalMonthlyRate, 2),
    ]])
@endsection
