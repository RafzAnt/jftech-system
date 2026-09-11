<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - {{ $payment['bill_number'] }}</title>
    @php($thermal = request()->get('format', 'thermal') === 'thermal')
    <style>
        * { box-sizing: border-box; }
        .print-company-brand { display: flex; align-items: flex-start; gap: 8px; text-align: left; }
        .print-company-logo { width: auto; height: 42px; object-fit: contain; }
        .print-company-brand .company { font-size: 16px; font-weight: 800; line-height: 1.15; }
        .print-company-brand .sub { font-size: 11px; line-height: 1.3; margin-top: 2px; }
        body {
            margin: 0;
            padding: 12px;
            background: #fff;
            color: #111;
            font-family: 'Courier New', Courier, monospace;
            font-size: 16px;
            line-height: 1.35;
        }
        .receipt {
            width: 360px;
            max-width: 100%;
            margin: 0 auto;
            padding: 8px 10px;
            background: #fff;
        }
        .center { text-align: center; }
        .divider { border-top: 1px dashed #333; margin: 10px 0; }
        .items { width: 100%; border-collapse: collapse; }
        .items td { padding: 4px 0; vertical-align: top; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .small { font-size: 15px; }
        .tiny { font-size: 13px; }
        .brand { font-size: 19px; font-weight: 800; }
        .total { font-size: 20px; font-weight: 900; }
        .stars { letter-spacing: 1px; }
        .toolbar { text-align: center; margin-bottom: 12px; }
        .toolbar button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        @media print {
            body { margin: 0; }
            .receipt { width: 90mm; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="receipt">
        @include('partials.print_company', ['compact' => true])
        <div class="center small">TIN # 476-882-950-000</div>
        <div class="center small">{{ date('M d, Y', strtotime($payment['payment_date'])) }}</div>

        <div class="divider"></div>
        <div class="center small bold">PAYMENT RECEIPT</div>
        <table class="items">
            <tr><td class="small">Receipt #:</td><td class="small right">{{ str_pad((string)$payment['id'], 6, '0', STR_PAD_LEFT) }}</td></tr>
            <tr><td class="small">Client:</td><td class="small right">{{ $payment['client_name'] }}</td></tr>
            <tr><td class="small">Code:</td><td class="small right">{{ $payment['client_code'] }}</td></tr>
            <tr><td class="small">Bill #:</td><td class="small right">{{ $payment['bill_number'] ?: '—' }}</td></tr>
        </table>

        <div class="divider"></div>
        <table class="items">
            <tr><td class="small">Payment Method:</td><td class="small right">{{ ucfirst($payment['payment_method']) }}</td></tr>
            <tr><td class="small">Reference:</td><td class="small right">{{ $payment['reference_number'] ?: '—' }}</td></tr>
            <tr><td class="small">Received By:</td><td class="small right">{{ $payment['received_by_name'] ?: 'System' }}</td></tr>
        </table>

        <div class="divider"></div>
        <table class="items">
            <tr><td class="small">Amount Paid:</td><td class="small right total">PHP {{ number_format((float)$payment['amount'], 2) }}</td></tr>
            <tr><td class="small">Bill Amount:</td><td class="small right">{{ number_format((float)($payment['bill_amount'] ?? 0), 2) }}</td></tr>
            <tr><td class="small">Balance:</td><td class="small right">{{ number_format((float)($payment['balance'] ?? 0), 2) }}</td></tr>
        </table>

        <div class="divider"></div>
        <div class="center small">Thank you!</div>
        <div class="center tiny stars">************************</div>
    </main>

    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
