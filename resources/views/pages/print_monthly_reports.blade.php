@extends('layouts.print_report')
@use('App\Support\ReportFormat')

@php
    $reportTitle = 'Business Summary Report';
    $reportCoverage = ReportFormat::coverage($from, $to);
    $reportBranch = ReportFormat::branch($selectedBranch);
    $orientation = 'portrait';
    $b = $report['bills'];
    $pay = $report['payments'];
    $att = $report['attendance'];
    $payroll = $report['payroll'];
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    $share = fn ($part, $whole) => $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '—';
    $hours = rtrim(rtrim(number_format($att['hours'], 2), '0'), '.');
@endphp

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Billed' => [$peso($b['amount']), number_format($b['count']) . ' bill(s) · ' . number_format($b['billedClients']) . ' client(s)'],
        'Payments Received' => [$peso($pay['collected']), number_format($pay['count']) . ' payment(s)'],
        'Outstanding Balance' => [$peso($b['outstanding']), 'after partial payments'],
        'Net Payroll' => [$peso($payroll['net']), number_format($payroll['entries']) . ' payroll entr' . ($payroll['entries'] === 1 ? 'y' : 'ies')],
    ]])

    <section class="report-section">
        <div class="section-heading">1. Billing Summary</div>
        <table class="report-table">
            <thead><tr><th>Status</th><th class="num">No. of Bills</th><th class="num">Amount</th><th class="num">% of Total Billed</th></tr></thead>
            <tbody>
                @foreach ([['Paid', 'paid'], ['Pending', 'pending'], ['Overdue', 'overdue']] as [$label, $key])
                    <tr><td>{{ $label }}</td><td class="num">{{ number_format($b[$key . 'Count']) }}</td><td class="num">{{ $peso($b[$key . 'Amount']) }}</td><td class="num">{{ $share($b[$key . 'Amount'], $b['amount']) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>TOTAL BILLED</td><td class="num">{{ number_format($b['count']) }}</td><td class="num">{{ $peso($b['amount']) }}</td><td class="num">{{ $b['amount'] > 0 ? '100.0%' : '—' }}</td></tr></tfoot>
        </table>
        @include('partials.report_particulars', ['rows' => array_filter([
            'Clients billed' => number_format($b['billedClients']),
            'Clients with overdue bills' => number_format($b['overdueClients']),
            'Cancelled bills (not counted above)' => $b['cancelledCount'] > 0 ? number_format($b['cancelledCount']) : null,
        ], fn ($value) => $value !== null), 'total' => ['OUTSTANDING BALANCE (after partial payments)', $peso($b['outstanding'])]])
    </section>
    <section class="report-section">
        <div class="section-heading">2. Collection Summary</div>
        <table class="report-table">
            <thead><tr><th>Payment Method</th><th class="num">No. of Payments</th><th class="num">Amount</th><th class="num">% of Collections</th></tr></thead>
            <tbody>
                @forelse ($pay['methods'] as $method)
                    <tr><td>{{ ReportFormat::paymentMethod($method->payment_method) }}</td><td class="num">{{ number_format($method->payment_count) }}</td><td class="num">{{ $peso($method->total) }}</td><td class="num">{{ $share($method->total, $pay['collected']) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty">No payments received in this period.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><td>TOTAL PAYMENTS RECEIVED</td><td class="num">{{ number_format($pay['count']) }}</td><td class="num">{{ $peso($pay['collected']) }}</td><td class="num">{{ $pay['collected'] > 0 ? '100.0%' : '—' }}</td></tr></tfoot>
        </table>
    </section>
    <section class="report-section">
            <div class="section-heading">3. Client Summary</div>
            @include('partials.report_particulars', ['rows' => [
                'New installations' => number_format($report['clients']['new']),
                'Monthly rate of the new installations' => $peso($report['clients']['monthly']),
            ]])
        </section>

        <section class="report-section">
            <div class="section-heading">4. Attendance Summary</div>
            @include('partials.report_particulars', ['rows' => [
                'Employees with attendance' => number_format($att['employees']),
                'Attendance records' => number_format($att['records']),
                'Present' => number_format($att['present']),
                'Late' => number_format($att['late']),
                'Absent' => number_format($att['absent']),
                'Total hours worked' => $hours,
            ]])
        </section>

        <section class="report-section">
            <div class="section-heading">5. Payroll Summary</div>
            @include('partials.report_particulars', ['rows' => [
                'Payroll entries' => number_format($payroll['entries']),
                'Gross pay' => $peso($payroll['gross']),
                'Less: total deductions' => $peso($payroll['deductions']),
            ], 'total' => ['NET PAY', $peso($payroll['net'])]])
        </section>

        @if ($report['requests'] !== null)
            @php($req = $report['requests'])
            <section class="report-section">
                <div class="section-heading">6. Inventory Requests Summary</div>
                <table class="report-table">
                    <thead><tr><th>Status</th><th class="num">No. of Requests</th></tr></thead>
                    <tbody>
                        <tr><td>Approved</td><td class="num">{{ number_format($req['approved']) }}</td></tr>
                        <tr><td>Pending</td><td class="num">{{ number_format($req['pending']) }}</td></tr>
                        <tr><td>Denied</td><td class="num">{{ number_format($req['denied']) }}</td></tr>
                    </tbody>
                    <tfoot><tr><td>TOTAL REQUESTS (quantity asked for: {{ number_format($req['quantity']) }})</td><td class="num">{{ number_format($req['total']) }}</td></tr></tfoot>
                </table>
            </section>
        @endif

        @if ($report['branches'] !== null)
            @php($br = $report['branches'])
            <section class="report-section">
                <div class="section-heading">{{ $report['requests'] !== null ? '7' : '6' }}. Summary by Branch</div>
                <table class="report-table dense">
                    <thead><tr><th>Branch</th><th class="num">New Clients</th><th class="num">Billed</th><th class="num">Collected</th><th class="num">Outstanding</th><th class="num">Net Payroll</th></tr></thead>
                    <tbody>
                        @forelse ($br['rows'] as $row)
                            <tr><td>{{ $row['branch'] }}</td><td class="num">{{ number_format($row['newClients']) }}</td><td class="num">{{ $peso($row['billed']) }}</td><td class="num">{{ $peso($row['collected']) }}</td><td class="num">{{ $peso($row['outstanding']) }}</td><td class="num">{{ $peso($row['payroll']) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="empty">No activity in this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><td>TOTAL</td><td class="num">{{ number_format($br['total']['newClients']) }}</td><td class="num">{{ $peso($br['total']['billed']) }}</td><td class="num">{{ $peso($br['total']['collected']) }}</td><td class="num">{{ $peso($br['total']['outstanding']) }}</td><td class="num">{{ $peso($br['total']['payroll']) }}</td></tr></tfoot>
                </table>
            </section>
        @endif
    @endsection
