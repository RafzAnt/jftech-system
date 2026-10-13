<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Management Report</title>
    <style>
        @media print { *, *::before, *::after { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } .header { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; flex-wrap:nowrap; } .header .print-company-brand { min-width:0; } .header > :last-child { text-align:right !important; } }
        *{box-sizing:border-box} body{margin:0;padding:20px;background:#f1f5f9;color:#0f172a;font:13px/1.4 Arial,sans-serif}.toolbar{max-width:950px;margin:0 auto 14px;text-align:right}button{padding:9px 16px;border:0;border-radius:6px;background:#1d4ed8;color:#fff;cursor:pointer;font-weight:700}.report{max-width:950px;margin:auto;background:#fff;border:1px solid #94a3b8}.header{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:20px 24px;background:#1e3a8a;color:#fff}.company{font-size:19px;font-weight:800;letter-spacing:.04em}.muted,.print-company-brand .sub{font-size:12px;opacity:.9}.print-company-brand{display:flex;align-items:flex-start;gap:10px}.print-company-logo{width:auto;height:48px;object-fit:contain}.body{padding:22px 24px}.section-title{margin:18px 0 7px;padding:7px 10px;background:#1e3a8a;color:#fff;font-weight:700;text-transform:uppercase;font-size:12px}.meta,.summary,.items{width:100%;border-collapse:collapse}.meta td,.summary td,.items th,.items td{padding:8px 10px;border:1px solid #cbd5e1;vertical-align:top}.meta td{width:50%}.label{display:block;color:#64748b;font-size:10px;text-transform:uppercase;margin-bottom:3px}.summary td{width:25%}.summary strong{font-size:16px}.items th{background:#e2e8f0;text-align:left;font-size:11px}.items td:last-child,.items th:last-child{text-align:right;white-space:nowrap}.items tr{break-inside:avoid}.total td{font-weight:700;background:#f8fafc}.signatures{display:flex;justify-content:space-between;gap:24px;margin-top:28px}.signature{width:45%;padding-top:8px;border-top:1px solid #64748b;font-size:12px}@media(max-width:650px){.header,.signatures{flex-direction:column}.signature{width:100%}.summary td{width:50%}}@media print{@page{size:A4 portrait;margin:10mm}body{padding:0;background:#fff}.toolbar{display:none}.report{max-width:none}.section-title{break-after:avoid}.items,.meta,.summary{table-layout:fixed}.items thead{display:table-header-group}.items tr,.meta tr,.summary tr{break-inside:avoid;page-break-inside:avoid}.items th,.items td,.meta td,.summary td{overflow-wrap:anywhere}}
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    <main class="report">
        <header class="header">
            @include('partials.print_company')
            <div style="text-align:right"><strong>BUSINESS SUMMARY REPORT</strong><br><span class="muted">{{ $report['label'] }}</span></div>
        </header>
        @php($b = $report['bills'])
        <div class="body">
            <table class="meta"><tr><td><span class="label">Report Period</span><strong>{{ date('F d, Y', strtotime($from)) }} – {{ date('F d, Y', strtotime($to)) }}</strong></td><td><span class="label">Date Prepared</span><strong>{{ now()->format('M d, Y h:i A') }}</strong></td></tr></table>

            <div class="section-title">Billing Summary</div>
            <table class="summary"><tr>
                <td><span class="label">Total Bills</span><strong>₱{{ number_format($b['amount'], 2) }}</strong><br>{{ number_format($b['count']) }} bill(s) &middot; {{ number_format($b['billedClients']) }} client(s)</td>
                <td><span class="label">Paid</span><strong>₱{{ number_format($b['paidAmount'], 2) }}</strong><br>{{ number_format($b['paidCount']) }} bill(s) &middot; {{ number_format($b['paidRate'], 1) }}%</td>
                <td><span class="label">Pending</span><strong>₱{{ number_format($b['pendingAmount'], 2) }}</strong><br>{{ number_format($b['pendingCount']) }} bill(s)</td>
                <td><span class="label">Overdue</span><strong>₱{{ number_format($b['overdueAmount'], 2) }}</strong><br>{{ number_format($b['overdueCount']) }} bill(s)</td>
            </tr></table>
            <table class="items" style="margin-top:8px">
                <tr class="total"><td>Outstanding balance (after partial payments)</td><td>₱{{ number_format($b['outstanding'], 2) }}</td></tr>
                @if ($b['cancelledCount'] > 0)<tr><td>Cancelled bills (not counted above)</td><td>{{ number_format($b['cancelledCount']) }}</td></tr>@endif
            </table>

            <div class="section-title">Payments Received</div>
            <table class="items">
                <thead><tr><th>Payment Method</th><th>Payments</th><th>Amount</th></tr></thead>
                <tbody>
                    @forelse ($report['payments']['methods'] as $method)
                    <tr><td>{{ ucwords(str_replace('_', ' ', $method->payment_method)) }}</td><td style="text-align:right">{{ number_format($method->payment_count) }}</td><td>₱{{ number_format($method->total, 2) }}</td></tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#64748b">No payments received in this period.</td></tr>
                    @endforelse
                    <tr class="total"><td>Total payments received</td><td style="text-align:right">{{ number_format($report['payments']['count']) }}</td><td>₱{{ number_format($report['payments']['collected'], 2) }}</td></tr>
                </tbody>
            </table>

            <div class="section-title">Clients, Staff and Inventory</div>
            <table class="summary">
                <tr>
                    <td><span class="label">New Installations</span><strong>{{ number_format($report['clients']['new']) }}</strong></td>
                    <td><span class="label">Clients With Overdue Bills</span><strong>{{ number_format($b['overdueClients']) }}</strong></td>
                    <td><span class="label">Attendance Records</span><strong>{{ number_format($report['attendance']['records']) }}</strong><br>{{ number_format($report['attendance']['present']) }} present &middot; {{ number_format($report['attendance']['late']) }} late &middot; {{ number_format($report['attendance']['absent']) }} absent</td>
                    <td><span class="label">Payroll Net Pay</span><strong>₱{{ number_format($report['payroll']['net'], 2) }}</strong><br>{{ number_format($report['payroll']['entries']) }} entr{{ $report['payroll']['entries'] === 1 ? 'y' : 'ies' }}</td>
                </tr>
                <tr>
                    <td><span class="label">Inventory Requests</span><strong>{{ number_format($report['requests']['total']) }}</strong></td>
                    <td><span class="label">Approved</span><strong>{{ number_format($report['requests']['approved']) }}</strong></td>
                    <td><span class="label">Pending</span><strong>{{ number_format($report['requests']['pending']) }}</strong></td>
                    <td><span class="label">Denied</span><strong>{{ number_format($report['requests']['denied']) }}</strong></td>
                </tr>
            </table>
            <div class="signatures"><div class="signature">Prepared By:<br><br>____________________________</div><div class="signature">Checked / Approved By:<br><br>____________________________</div></div>
        </div>
    </main>
    <script>window.addEventListener('load',function(){window.print()});</script>
</body>
</html>
