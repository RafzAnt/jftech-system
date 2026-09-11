<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Receipt - {{ $client->client_code }}</title>
    @php($thermal = request()->get('format', 'thermal') === 'thermal')
    <style>
        * { box-sizing: border-box; }
        .print-company-brand { display: flex; align-items: flex-start; gap: 8px; text-align: left; }
        .print-company-logo { width: auto; height: 42px; object-fit: contain; }
        .print-company-brand .company { font-size: 16px; font-weight: 800; line-height: 1.15; }
        .print-company-brand .sub { font-size: 11px; line-height: 1.3; margin-top: 2px; }
        @if ($thermal)
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
        @media print { body { margin: 0; } .receipt { width: 90mm; } }
        @else
        body { margin: 0; padding: 24px; background: #f1f5f9; color: #0f172a; font: 14px/1.45 Arial, sans-serif; }
        .receipt { max-width: 680px; margin: auto; padding: 32px; background: #fff; border: 1px solid #cbd5e1; }
        .header { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding-bottom: 18px; border-bottom: 3px solid #1e3a8a; }
        h1 { margin: 0; color: #1e3a8a; font-size: 22px; letter-spacing: .04em; }
        .muted { color: #64748b; font-size: 12px; }
        h2 { margin: 24px 0 14px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 10px 12px; border: 1px solid #cbd5e1; }
        td:first-child { width: 42%; color: #475569; font-weight: 700; }
        .amount { margin-top: 18px; padding: 16px; background: #fff; border: 1px solid #cbd5e1; color: #334155; text-align: right; }
        .amount strong { font-size: 22px; }
        .toolbar { max-width: 680px; margin: 0 auto 16px; text-align: right; }
        button { padding: 9px 16px; border: 0; border-radius: 6px; background: #1d4ed8; color: #fff; cursor: pointer; font-weight: 700; }
        @media print { @page { size: A4 portrait; margin: 12mm; } body { padding: 0; background: #fff; } .receipt { max-width: none; } .toolbar { display: none; } table { table-layout: fixed; } tr { break-inside: avoid; page-break-inside: avoid; } th, td { overflow-wrap: anywhere; } }
        @endif
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    <main class="receipt">
        @if ($thermal)
            @include('partials.print_company', ['compact' => true])
            <div class="center small">TIN # 476-882-950-000</div>
            <div class="center small">{{ now()->format('M d, Y') }}</div>

            <div class="divider"></div>
            <table class="items">
                <tr>
                    <td class="small">Client:</td>
                    <td class="small right">{{ $client->full_name }}</td>
                </tr>
                <tr>
                    <td class="small">Code:</td>
                    <td class="small right">{{ $client->client_code }}</td>
                </tr>
            </table>

            <div class="divider"></div>
            <div class="center small bold">Month of {{ now()->format('F Y') }}</div>
            <table class="items">
                <tr>
                    <td class="small">1 x {{ number_format((float) $client->monthly_rate, 2) }}</td>
                    <td class="small right">{{ number_format((float) $client->monthly_rate, 2) }}</td>
                </tr>
            </table>

            <div class="divider"></div>
            <table class="items">
                <tr>
                    <td class="small bold">TOTAL:</td>
                    <td class="small right total">PHP {{ number_format((float) $client->monthly_rate, 2) }}</td>
                </tr>
            </table>

            <div class="divider"></div>
            <div class="center small">Thank you!</div>
            <div class="center tiny stars">************************</div>
        @else
            <header class="header">
                @include('partials.print_company')
                <div class="muted" style="text-align:right;">CLIENT INFORMATION<br>{{ now()->format('M d, Y h:i A') }}</div>
            </header>

            <h2>Client Details</h2>
            <table>
                <tr><td>Client Code</td><td>{{ $client->client_code }}</td></tr>
                <tr><td>Full Name</td><td>{{ $client->full_name }}</td></tr>
                <tr><td>Contact Number</td><td>{{ $client->contact_number ?: '-' }}</td></tr>
                <tr><td>Email</td><td>{{ $client->email ?: '-' }}</td></tr>
                <tr><td>Barangay</td><td>{{ $client->barangay_name ?: '-' }}</td></tr>
                <tr><td>Address</td><td>{{ $client->exact_address ?: '-' }}</td></tr>
                <tr><td>WiFi Plan</td><td>{{ $client->wifi_plan ?: '-' }}</td></tr>
                <tr><td>Account Status</td><td>{{ ucfirst($client->status) }}</td></tr>
            </table>

            <div class="amount">Monthly Rate<br><strong>PHP {{ number_format((float) $client->monthly_rate, 2) }}</strong></div>
        @endif
    </main>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
