@extends('layouts.print_report')
@use('App\Support\ReportFormat')

@php
    $reportTitle = 'Business Report';
    $reportCoverage = ReportFormat::coverage($from, $to);
    $reportBranch = ReportFormat::branch($selectedBranch);
    $bills = $report['bills'];
    $payments = $report['payments'];
    $clients = $report['clients'];
    $attendance = $report['attendance'];
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    $hours = rtrim(rtrim(number_format($attendance['hours'], 2), '0'), '.') ?: '0';
@endphp

@section('content')
    @if (\App\Support\PeriodReport::isEmpty($report))
        <p class="empty" style="text-align:center;margin:40px 0">No data for {{ $reportCoverage }} &middot; {{ $reportBranch }}.</p>
    @else
        <section class="report-section">
            <table class="report-table ledger">
                <thead>
                    <tr><th>Report Item</th><th class="num">Records</th><th class="num">Amount</th><th>Details</th></tr>
                </thead>
                <tbody>
                    <tr class="group"><td colspan="4">Billing</td></tr>
                    <tr><td>Total billed</td><td class="num">{{ number_format($bills['count']) }}</td><td class="num">{{ $peso($bills['amount']) }}</td><td>{{ number_format($bills['billedClients']) }} client(s) billed</td></tr>
                    <tr><td>Paid bills</td><td class="num">{{ number_format($bills['paidCount']) }}</td><td class="num">{{ $peso($bills['paidAmount']) }}</td><td>Paid status</td></tr>
                    <tr><td>Pending bills</td><td class="num">{{ number_format($bills['pendingCount']) }}</td><td class="num">{{ $peso($bills['pendingAmount']) }}</td><td>Awaiting payment</td></tr>
                    <tr><td>Overdue bills</td><td class="num">{{ number_format($bills['overdueCount']) }}</td><td class="num">{{ $peso($bills['overdueAmount']) }}</td><td>{{ number_format($bills['overdueClients']) }} client(s)</td></tr>

                    <tr class="group"><td colspan="4">Collections</td></tr>
                    <tr><td>Payments received</td><td class="num">{{ number_format($payments['count']) }}</td><td class="num">{{ $peso($payments['collected']) }}</td><td>{{ number_format($payments['methods']->count()) }} payment method(s)</td></tr>

                    <tr class="group"><td colspan="4">Clients</td></tr>
                    <tr><td>New installations</td><td class="num">{{ number_format($clients['new']) }}</td><td class="num">{{ $peso($clients['monthly']) }}</td><td>Combined monthly rate</td></tr>

                    <tr class="group"><td colspan="4">Attendance</td></tr>
                    <tr><td>Employees on record</td><td class="num">{{ number_format($attendance['employees']) }}</td><td class="num">—</td><td>{{ number_format($attendance['records']) }} attendance record(s)</td></tr>
                    <tr><td>Present</td><td class="num">{{ number_format($attendance['present']) }}</td><td class="num">—</td><td>{{ $hours }} total hour(s) worked</td></tr>
                    <tr><td>Late / Absent</td><td class="num">{{ number_format($attendance['late']) }} / {{ number_format($attendance['absent']) }}</td><td class="num">—</td><td>Attendance status</td></tr>

                    @if ($report['requests'] !== null)
                        @php($requests = $report['requests'])
                        <tr class="group"><td colspan="4">Inventory Requests</td></tr>
                        <tr><td>Total requests</td><td class="num">{{ number_format($requests['total']) }}</td><td class="num">{{ number_format($requests['quantity']) }} item(s)</td><td>Approved: {{ number_format($requests['approved']) }} · Pending: {{ number_format($requests['pending']) }} · Denied: {{ number_format($requests['denied']) }}</td></tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="grand"><td>Outstanding balance</td><td class="num">{{ number_format($bills['overdueClients']) }} overdue client(s)</td><td class="num">{{ $peso($bills['outstanding']) }}</td><td>After partial payments</td></tr>
                </tfoot>
            </table>
        </section>
    @endif
@endsection
