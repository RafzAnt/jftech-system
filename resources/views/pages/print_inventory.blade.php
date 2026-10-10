<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <title>Inventory Stock Report</title>
    <style>
        @media print { *, *::before, *::after { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } }
        @media print { .doc-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; flex-wrap: nowrap; } .doc-header .print-company-brand { min-width: 0; } .doc-title { text-align: right; } }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #ffffff;
            margin: 12px;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .doc-header .company {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #1e3a8a;
        }
        .doc-header .sub {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }
        .print-company-brand { display: flex; align-items: flex-start; gap: 10px; }
        .print-company-logo { width: auto; height: 48px; object-fit: contain; }
        .doc-title {
            text-align: right;
            font-size: 14px;
            color: #334155;
        }
        .doc-title strong {
            font-size: 16px;
            color: #0f172a;
        }
        .meta {
            font-size: 12px;
            color: #475569;
            margin-bottom: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead th {
            background: #1e3a8a;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #1e3a8a;
        }
        tbody td {
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .amount { text-align: right; white-space: nowrap; }
        tfoot td {
            padding: 9px 10px;
            border: 1px solid #1e3a8a;
            font-weight: 700;
            background: #eef2ff;
        }
        .footer {
            margin-top: 24px;
            font-size: 11px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .no-print { display: none; }
        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            body { margin: 0; }
            .toolbar { display: none; }
            table { table-layout: fixed; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; page-break-inside: avoid; }
            th, td { overflow-wrap: anywhere; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print" style="margin-bottom: 16px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; font-size: 14px; cursor: pointer;">Print / Save as PDF</button>
    </div>

    <div class="doc-header">
        @include('partials.print_company')
        <div class="doc-title">
            <strong>INVENTORY STOCK REPORT</strong><br>
            Prepared: {{ date('M d, Y h:i A') }}
        </div>
    </div>

    <div class="meta">
        @if ($categoryLabel)
        Category: <strong>{{ htmlspecialchars($categoryLabel) }}</strong> &nbsp;|&nbsp;
        @endif
        Total Items: <strong>{{ number_format($totalItems) }}</strong> &nbsp;|&nbsp;
        Total Quantity: <strong>{{ number_format($totalQuantity) }}</strong> &nbsp;|&nbsp;
        Total Value: <strong>₱{{ number_format($totalValue, 2) }}</strong> &nbsp;|&nbsp;
        Low Stock: <strong>{{ number_format($lowStockItems) }}</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Item No.</th>
                <th>Item Name</th>
                <th>Category</th>
                <th class="amount" style="width: 12%;">Quantity</th>
                <th class="amount" style="width: 12%;">Unit Price</th>
                <th class="amount" style="width: 14%;">Total Value</th>
                <th style="width: 12%;">Supplier</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inventoryItems as $item)
            <tr>
                <td>#{{ $item['id'] }}</td>
                <td>{{ htmlspecialchars($item['item_name']) }}</td>
                <td>{{ htmlspecialchars($item['category']) }}</td>
                <td class="amount">{{ number_format((int) $item['quantity']) }}</td>
                <td class="amount">₱{{ number_format((float) $item['unit_price'], 2) }}</td>
                <td class="amount">₱{{ number_format((float) $item['quantity'] * (float) $item['unit_price'], 2) }}</td>
                <td>{{ htmlspecialchars($item['supplier'] ?: 'N/A') }}</td>
                <td>{{ ucfirst($item['status']) }}</td>
            </tr>
            @endforeach
            @php
if (empty($inventoryItems)) { echo '<tr><td colspan="8" style="text-align: center; color: #64748b; padding: 18px;">No inventory items found.</td></tr>'; } 
@endphp
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTAL</td>
                <td class="amount">{{ number_format($totalQuantity) }}</td>
                <td class="amount">-</td>
                <td class="amount">₱{{ number_format($totalValue, 2) }}</td>
                <td colspan="2">{{ number_format($totalItems) }} Items</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        This report is system-generated by JFTECH WiFi Billing & Management System.
    </div>

    <script>
        window.onload = function () { window.print(); };
    </script>
</body>
</html>
