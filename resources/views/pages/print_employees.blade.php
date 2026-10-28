@extends('layouts.print_report')

@php
    $reportTitle = 'Employee Masterlist Report';
    $reportCoverage = 'As of ' . \App\Support\ReportFormat::date(now());
    $reportDetails = $deptLabel ? ['Department' => $deptLabel] : [];
    $totalDailyRate = array_sum(array_map(fn ($employee) => (float) $employee['daily_rate'], $employees));
@endphp

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Employees' => number_format($totalEmployees),
        'Active' => number_format($activeEmployees),
        'Inactive' => number_format($inactiveEmployees),
        'Former Employees' => number_format($terminatedEmployees),
    ]])

    <table class="report-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Employee Code</th>
                <th>Full Name</th>
                <th>Position</th>
                <th>Department</th>
                <th>Mobile Number</th>
                <th>Status</th>
                <th class="num">Daily Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $emp)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>{{ $emp['employee_code'] }}</td>
                    <td>{{ $emp['full_name'] }}</td>
                    <td>{{ $emp['position'] ?: '—' }}</td>
                    <td>{{ $emp['department'] ?: '—' }}</td>
                    <td>{{ $emp['contact_number'] ?: '—' }}</td>
                    <td>{{ ucfirst($emp['status']) }}</td>
                    <td class="num">₱{{ number_format((float) $emp['daily_rate'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No employees found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">TOTAL ({{ number_format($totalEmployees) }} {{ $totalEmployees === 1 ? 'employee' : 'employees' }})</td>
                <td class="num">₱{{ number_format($totalDailyRate, 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
