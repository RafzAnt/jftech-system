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
            <div style="text-align:right"><strong>MONTHLY BUSINESS REPORT</strong><br><span class="muted">{{ date('M d, Y', strtotime($from)) }} to {{ date('M d, Y', strtotime($to)) }}</span></div>
        </header>
        <div class="body">
            <table class="meta"><tr><td><span class="label">Report Period</span><strong>{{ date('F d, Y', strtotime($from)) }} – {{ date('F d, Y', strtotime($to)) }}</strong></td><td><span class="label">Date Prepared</span><strong>{{ now()->format('M d, Y h:i A') }}</strong></td></tr></table>
            <div class="section-title">Report Summary</div>
            <table class="summary"><tr><td><span class="label">Total Payments Received</span><strong>₱{{ number_format($collections, 2) }}</strong><br>{{ number_format($paymentCount) }} payment(s)</td><td><span class="label">Total Bills</span><strong>₱{{ number_format($totalBillsAmount, 2) }}</strong><br>{{ number_format($totalBills) }} bill(s)</td><td><span class="label">Outstanding Balance</span><strong>₱{{ number_format($outstanding, 2) }}</strong></td><td><span class="label">Open Bills</span><strong>{{ number_format($openBills) }}</strong></td></tr></table>
            <div class="section-title">Business Summary</div>
            <table class="summary"><tr><td><span class="label">Active Clients</span><strong>{{ number_format($systemSnapshot['activeClients']) }}</strong></td><td><span class="label">Active Employees</span><strong>{{ number_format($systemSnapshot['activeEmployees']) }}</strong></td><td><span class="label">Attendance Entries</span><strong>{{ number_format($attendanceSummary->records ?? 0) }}</strong></td><td><span class="label">Low Stock Items</span><strong>{{ number_format($systemSnapshot['lowStock']) }}</strong></td></tr></table>
            <div class="signatures"><div class="signature">Prepared By:<br><br>____________________________</div><div class="signature">Checked / Approved By:<br><br>____________________________</div></div>
        </div>
    </main>
    <script>window.addEventListener('load',function(){window.print()});</script>
</body>
</html>
