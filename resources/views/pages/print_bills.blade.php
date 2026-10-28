@extends('layouts.print_report')

@php
    $reportTitle = 'Billing Report';
    $reportCoverage = $periodLabel;
    $reportDetails = $statusLabel ? ['Status' => $statusLabel] : [];
@endphp

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Bills' => number_format($totalBills),
        'Total Amount' => '₱' . number_format($totalAmount, 2),
        'Paid' => '₱' . number_format($totalPaid, 2),
        'Pending' => '₱' . number_format($totalPending, 2),
        'Overdue' => '₱' . number_format($totalOverdue, 2),
    ]])

    <table class="report-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Bill No.</th>
                <th>Client</th>
                <th>Client Code</th>
                <th>Billing Period</th>
                <th>Due Date</th>
                <th>Status</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bills as $bill)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>{{ $bill['bill_number'] }}</td>
                    <td>{{ $bill['full_name'] }}</td>
                    <td>{{ $bill['client_code'] }}</td>
                    <td>{{ date('M j', strtotime($bill['billing_period_start'])) }} – {{ date('M j, Y', strtotime($bill['billing_period_end'])) }}</td>
                    <td>{{ date('F j, Y', strtotime($bill['due_date'])) }}</td>
                    <td>{{ ucfirst($bill['status']) }}</td>
                    <td class="num">₱{{ number_format((float) $bill['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No billing records found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">TOTAL ({{ number_format($totalBills) }} {{ $totalBills === 1 ? 'bill' : 'bills' }})</td>
                <td class="num">₱{{ number_format($totalAmount, 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
