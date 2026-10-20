<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Report</title>
    <style>
        @media print { *, *::before, *::after { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } .header { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; flex-wrap:nowrap; } .header .print-company-brand { min-width:0; } .header > :last-child { text-align:right !important; } }
        *{box-sizing:border-box} body{margin:0;padding:20px;background:#f1f5f9;color:#0f172a;font:13px/1.4 Arial,sans-serif}.toolbar{max-width:950px;margin:0 auto 14px;text-align:right}button{padding:9px 16px;border:0;border-radius:6px;background:#1d4ed8;color:#fff;cursor:pointer;font-weight:700}.report{max-width:950px;margin:auto;background:#fff;border:1px solid #94a3b8}.header{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:20px 24px;background:#1e3a8a;color:#fff}.company{font-size:19px;font-weight:800;letter-spacing:.04em}.muted,.print-company-brand .sub{font-size:12px;opacity:.9}.print-company-brand{display:flex;align-items:flex-start;gap:10px}.print-company-logo{width:auto;height:48px;object-fit:contain}.body{padding:22px 24px}.section-title{margin:18px 0 7px;padding:7px 10px;background:#1e3a8a;color:#fff;font-weight:700;text-transform:uppercase;font-size:12px}.meta,.summary,.items{width:100%;border-collapse:collapse}.meta td,.summary td,.items th,.items td{padding:8px 10px;border:1px solid #cbd5e1;vertical-align:top}.meta td{width:50%}.label{display:block;color:#64748b;font-size:10px;text-transform:uppercase;margin-bottom:3px}.summary td{width:25%}.summary strong{font-size:16px}.items th{background:#e2e8f0;text-align:left;font-size:11px}.items td:last-child,.items th:last-child{text-align:right;white-space:nowrap}.items tr{break-inside:avoid}.total td{font-weight:700;background:#f8fafc}.items.detail th,.items.detail td{padding:5px 6px;font-size:10.5px}.items .num{text-align:right!important;white-space:nowrap}.items tfoot td{font-weight:700;background:#f1f5f9;border-top:2px solid #64748b}.items .empty{text-align:center!important;color:#64748b}.items small{color:#64748b}.signatures{display:flex;justify-content:space-between;gap:24px;margin-top:28px}.signature{width:45%;padding-top:8px;border-top:1px solid #64748b;font-size:12px}@media(max-width:650px){.header,.signatures{flex-direction:column}.signature{width:100%}.summary td{width:50%}}@media print{@page{size:A4 portrait;margin:10mm}body{padding:0;background:#fff}.toolbar{display:none}.report{max-width:none}.section-title{break-after:avoid}.items,.meta,.summary{table-layout:fixed}.items thead{display:table-header-group}.items tfoot{display:table-row-group}.items.detail{table-layout:auto}.items tr,.meta tr,.summary tr{break-inside:avoid;page-break-inside:avoid}.items th,.items td,.meta td,.summary td{overflow-wrap:anywhere}}
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    <main class="report">
        <header class="header">
            @include('partials.print_company')
            <div style="text-align:right"><strong>BUSINESS REPORT</strong><br><span class="muted">{{ $report['label'] }}</span></div>
        </header>
        @php($b = $report['bills'])
        <div class="body">
            <table class="meta"><tr><td><span class="label">Report Period</span><strong>{{ date('F d, Y', strtotime($from)) }} – {{ date('F d, Y', strtotime($to)) }}</strong></td><td><span class="label">Branch</span><strong>{{ $branchLabel }}</strong></td><td><span class="label">Date Prepared</span><strong>{{ now()->format('M d, Y h:i A') }}</strong></td></tr></table>

            @php($d = $report['details'])
            @php($peso = fn ($amount) => '₱' . number_format((float) $amount, 2))
            <div class="section-title">Summary</div>
            <table class="summary"><tr>
                <td><span class="label">Total Bills</span><strong>₱{{ number_format($b['amount'], 2) }}</strong><br>{{ number_format($b['count']) }} bill(s) &middot; {{ number_format($b['billedClients']) }} client(s)</td>
                <td><span class="label">Paid</span><strong>₱{{ number_format($b['paidAmount'], 2) }}</strong><br>{{ number_format($b['paidCount']) }} bill(s) &middot; {{ number_format($b['paidRate'], 1) }}%</td>
                <td><span class="label">Pending</span><strong>₱{{ number_format($b['pendingAmount'], 2) }}</strong><br>{{ number_format($b['pendingCount']) }} bill(s)</td>
                <td><span class="label">Overdue</span><strong>₱{{ number_format($b['overdueAmount'], 2) }}</strong><br>{{ number_format($b['overdueCount']) }} bill(s)</td>
            </tr></table>
            <table class="items" style="margin-top:8px">
                <tr class="total"><td>Outstanding balance (after partial payments)</td><td>₱{{ number_format($b['outstanding'], 2) }}</td></tr>
                @if ($b['cancelledCount'] > 0)<tr><td>Cancelled bills (not counted)</td><td>{{ number_format($b['cancelledCount']) }}</td></tr>@endif
            </table>

            <div class="section-title">1. Billing</div>
            <table class="items detail">
                <thead><tr><th>#</th><th>Bill No.</th><th>Client</th><th>Billing Period</th><th>Due</th><th>Status</th><th class="num">Amount</th><th class="num">Received</th><th class="num">Balance</th></tr></thead>
                <tbody>
                    @forelse ($d['bills']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['bill_number'] }}</td><td>{{ $row['client_name'] }}<br><small>{{ $row['client_code'] }}</small></td><td>{{ date('M d', strtotime($row['billing_period_start'])) }} – {{ date('M d, Y', strtotime($row['billing_period_end'])) }}</td><td>{{ date('M d, Y', strtotime($row['due_date'])) }}</td><td>{{ ucfirst($row['status']) }}</td><td class="num">{{ $peso($row['amount']) }}</td><td class="num">{{ $peso($row['received']) }}</td><td class="num">{{ $peso($row['balance']) }}</td></tr>
                    @empty
                    <tr><td colspan="9" class="empty">No bills in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="6">Total ({{ number_format(count($d['bills']['rows'])) }} bill(s))</td><td class="num">{{ $peso($d['bills']['total']['amount']) }}</td><td class="num">{{ $peso($d['bills']['total']['received']) }}</td><td class="num">{{ $peso($d['bills']['total']['balance']) }}</td></tr></tfoot>
            </table>

            <div class="section-title">2. Payments Received</div>
            <table class="items detail">
                <thead><tr><th>Payment Method</th><th class="num">Payments</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    @forelse ($report['payments']['methods'] as $method)
                    <tr><td>{{ ucwords(str_replace('_', ' ', $method->payment_method)) }}</td><td class="num">{{ number_format($method->payment_count) }}</td><td class="num">{{ $peso($method->total) }}</td></tr>
                    @empty
                    <tr><td colspan="3" class="empty">No payments received in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td>Total payments received</td><td class="num">{{ number_format($report['payments']['count']) }}</td><td class="num">{{ $peso($report['payments']['collected']) }}</td></tr></tfoot>
            </table>
            <table class="items detail" style="margin-top:8px">
                <thead><tr><th>#</th><th>Date</th><th>Client</th><th>Bill No.</th><th>Method</th><th>Reference No.</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    @forelse ($d['payments']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ date('M d, Y', strtotime($row['payment_date'])) }}</td><td>{{ $row['client_name'] }}<br><small>{{ $row['client_code'] }}</small></td><td>{{ $row['bill_number'] ?: '—' }}</td><td>{{ ucwords(str_replace('_', ' ', $row['payment_method'])) }}</td><td>{{ $row['reference_number'] ?: '—' }}</td><td class="num">{{ $peso($row['amount']) }}</td></tr>
                    @empty
                    <tr><td colspan="7" class="empty">No payments received in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="6">Total ({{ number_format(count($d['payments']['rows'])) }} payment(s))</td><td class="num">{{ $peso($d['payments']['total']['amount']) }}</td></tr></tfoot>
            </table>

            <div class="section-title">3. New Installations</div>
            <table class="items detail">
                <thead><tr><th>#</th><th>Installed</th><th>Client</th><th>Branch</th><th>Barangay</th><th>Plan</th><th class="num">Monthly Rate</th></tr></thead>
                <tbody>
                    @forelse ($d['clients']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ date('M d, Y', strtotime($row['installation_date'])) }}</td><td>{{ $row['full_name'] }}<br><small>{{ $row['client_code'] }}</small></td><td>{{ $row['branch_name'] ?: '—' }}</td><td>{{ $row['registered_barangay_name'] ?: '—' }}</td><td>{{ $row['wifi_plan'] ?: '—' }}</td><td class="num">{{ $peso($row['monthly_rate']) }}</td></tr>
                    @empty
                    <tr><td colspan="7" class="empty">No new installations in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="6">Total ({{ number_format(count($d['clients']['rows'])) }} client(s))</td><td class="num">{{ $peso($d['clients']['total']['monthly']) }}</td></tr></tfoot>
            </table>

            <div class="section-title">4. Attendance</div>
            @php($t = $d['attendance']['total'])
            <table class="items detail">
                <thead><tr><th>#</th><th>Employee</th><th>Position</th><th class="num">Records</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Hours</th></tr></thead>
                <tbody>
                    @forelse ($d['attendance']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['full_name'] }}<br><small>{{ $row['employee_code'] }}</small></td><td>{{ $row['position'] ?: '—' }}</td><td class="num">{{ number_format($row['records']) }}</td><td class="num">{{ number_format($row['present']) }}</td><td class="num">{{ number_format($row['late']) }}</td><td class="num">{{ number_format($row['absent']) }}</td><td class="num">{{ number_format($row['hours'], 2) }}</td></tr>
                    @empty
                    <tr><td colspan="8" class="empty">No attendance recorded in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="3">Total</td><td class="num">{{ number_format($t['records']) }}</td><td class="num">{{ number_format($t['present']) }}</td><td class="num">{{ number_format($t['late']) }}</td><td class="num">{{ number_format($t['absent']) }}</td><td class="num">{{ number_format($t['hours'], 2) }}</td></tr></tfoot>
            </table>

            <div class="section-title">5. Payroll</div>
            @php($t = $d['payroll']['total'])
            <table class="items detail">
                <thead><tr><th>#</th><th>Employee</th><th>Pay Period</th><th>Status</th><th class="num">Days</th><th class="num">Gross Pay</th><th class="num">Deductions</th><th class="num">Net Pay</th></tr></thead>
                <tbody>
                    @forelse ($d['payroll']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['full_name'] }}<br><small>{{ $row['position'] ?: $row['employee_code'] }}</small></td><td>{{ date('M d', strtotime($row['payroll_period_start'])) }} – {{ date('M d, Y', strtotime($row['payroll_period_end'])) }}</td><td>{{ ucfirst($row['status']) }}</td><td class="num">{{ rtrim(rtrim(number_format((float) $row['total_days_worked'], 2), '0'), '.') }}</td><td class="num">{{ $peso($row['gross_pay']) }}</td><td class="num">{{ $peso($row['deductions']) }}</td><td class="num">{{ $peso($row['net_pay']) }}</td></tr>
                    @empty
                    <tr><td colspan="8" class="empty">No payroll entries in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="4">Total</td><td class="num">{{ rtrim(rtrim(number_format($t['days'], 2), '0'), '.') }}</td><td class="num">{{ $peso($t['gross']) }}</td><td class="num">{{ $peso($t['deductions']) }}</td><td class="num">{{ $peso($t['net']) }}</td></tr></tfoot>
            </table>

            @if ($d['requests'] !== null)
            <div class="section-title">6. Inventory Requests</div>
            <table class="items detail">
                <thead><tr><th>#</th><th>Date</th><th>Requested By</th><th>Item</th><th>Status</th><th class="num">Quantity</th></tr></thead>
                <tbody>
                    @forelse ($d['requests']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ date('M d, Y', strtotime($row['created_at'])) }}</td><td>{{ $row['employee_name'] }}</td><td>{{ $row['item_name'] }}</td><td>{{ ucfirst($row['status']) }}</td><td class="num">{{ number_format($row['quantity']) }}</td></tr>
                    @empty
                    <tr><td colspan="6" class="empty">No inventory requests in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="5">Total ({{ number_format($report['requests']['total']) }} request(s): {{ number_format($report['requests']['approved']) }} approved, {{ number_format($report['requests']['pending']) }} pending, {{ number_format($report['requests']['denied']) }} denied)</td><td class="num">{{ number_format($d['requests']['total']['quantity']) }}</td></tr></tfoot>
            </table>
            @endif

            <div class="signatures"><div class="signature">Prepared By:<br><br>____________________________</div><div class="signature">Checked / Approved By:<br><br>____________________________</div></div>
        </div>
    </main>
    <script>window.addEventListener('load',function(){window.print()});</script>
</body>
</html>
