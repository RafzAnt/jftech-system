@extends('layouts.print_report')

@php
    $reportTitle = 'Payment Transactions Report';
    $reportCoverage = $rangeLabel;
@endphp

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Transactions' => number_format($count),
        'Total Amount Collected' => '₱' . number_format($total, 2),
    ]])

    <table class="report-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Date</th>
                <th>Client</th>
                <th>Client Code</th>
                <th>Bill No.</th>
                <th>Payment Method</th>
                <th class="num">Amount</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>{{ date('F j, Y', strtotime($p['payment_date'])) }}</td>
                    <td>{{ $p['client_name'] }}</td>
                    <td>{{ $p['client_code'] }}</td>
                    <td>{{ $p['bill_number'] ?: '—' }}</td>
                    <td>{{ \App\Support\ReportFormat::paymentMethod($p['payment_method']) }}</td>
                    <td class="num">₱{{ number_format((float) $p['amount'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) ($p['balance'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No payment transactions found for the selected period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">TOTAL ({{ number_format($count) }} {{ $count === 1 ? 'payment' : 'payments' }})</td>
                <td class="num">₱{{ number_format($total, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@endsection
