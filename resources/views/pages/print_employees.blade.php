@extends('layouts.print_report')

@php
    $reportTitle = 'Employee Report';
    $reportCoverage = 'As of ' . \App\Support\ReportFormat::date(now());
    $reportDetails = $deptLabel ? ['Department' => $deptLabel] : [];
    $employeeColumns = [['count', 'No. of Employees', 'int'], ['rate', 'Total Daily Rate', 'peso']];
@endphp

@section('content')

    {{-- The employee form has no department, so when none is recorded the report groups by position instead of one "Not specified" row. --}}
    @if ($deptLabel || ! $departments)
        @include('partials.report_breakdown', [
            'heading' => 'By Position', 'labelHeading' => 'Position', 'groups' => $byPosition, 'share' => 'count',
            'columns' => $employeeColumns, 'empty' => 'No employees found.',
        ])
    @else
        @include('partials.report_breakdown', [
            'heading' => 'By Department', 'labelHeading' => 'Department', 'groups' => $byDepartment, 'share' => 'count',
            'columns' => $employeeColumns, 'empty' => 'No employees found.',
        ])
    @endif
    @include('partials.report_totals', ['items' => [
        'Total Employees' => number_format($totalEmployees),
        'Active' => number_format($activeEmployees),
        'Inactive' => number_format($inactiveEmployees),
        'Former Employees' => number_format($terminatedEmployees),
        'Total Daily Rate' => '₱' . number_format($totalDailyRate, 2),
    ]])
@endsection
