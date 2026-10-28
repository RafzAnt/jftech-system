@extends('layouts.print_report')

@php
    $reportTitle = 'Client Masterlist Report';
    $reportCoverage = 'As of ' . \App\Support\ReportFormat::date(now());
    $reportDetails = $barangayLabel ? ['Barangay' => $barangayLabel] : [];
    $totalMonthlyRate = array_sum(array_map(fn ($client) => (float) $client['monthly_rate'], $clients));
@endphp

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Clients' => number_format($totalClients),
        'Active' => number_format($activeClients),
        'Inactive' => number_format($inactiveClients),
        'Suspended' => number_format($suspendedClients),
        'Total Monthly Rate' => '₱' . number_format($totalMonthlyRate, 2),
    ]])

    <table class="report-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Client Code</th>
                <th>Full Name</th>
                <th>Mobile Number</th>
                <th>Branch</th>
                <th>Barangay</th>
                <th>Plan</th>
                <th>Status</th>
                <th class="num">Monthly Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clients as $client)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>{{ $client['client_code'] }}</td>
                    <td>{{ $client['full_name'] }}</td>
                    <td>{{ $client['contact_number'] ?: '—' }}</td>
                    <td>{{ $client['branch_name'] ?: '—' }}</td>
                    <td>{{ $client['barangay_name'] ?: '—' }}</td>
                    <td>{{ $client['wifi_plan'] ?: '—' }}</td>
                    <td>{{ ucfirst($client['status']) }}</td>
                    <td class="num">₱{{ number_format((float) $client['monthly_rate'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No clients found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8">TOTAL ({{ number_format($totalClients) }} {{ $totalClients === 1 ? 'client' : 'clients' }})</td>
                <td class="num">₱{{ number_format($totalMonthlyRate, 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
