<?php

use Illuminate\Support\Facades\DB;

return function (\Illuminate\Http\Request $request): array {
    $month = (string) $request->query('month', now()->format('Y-m'));
    if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $month = now()->format('Y-m');
    }

    $from = (string) $request->query('from', '');
    $to = (string) $request->query('to', '');
    $isValidDate = static fn (string $date): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        && strtotime($date) !== false;

    // A complete date range takes priority over the month selector.
    if ($isValidDate($from) && $isValidDate($to)) {
        [$periodStart, $periodEnd] = $from <= $to ? [$from, $to] : [$to, $from];
    } else {
        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
    }

    $payments = DB::table('payments')->join('clients', 'clients.id', '=', 'payments.client_id')
        ->select('payments.payment_date', 'payments.reference_number', 'payments.payment_method', 'payments.amount', 'clients.full_name')
        ->whereBetween('payments.payment_date', [$periodStart, $periodEnd])->orderByDesc('payments.payment_date')->orderByDesc('payments.id')->get();
    $bills = DB::table('bills')->join('clients', 'clients.id', '=', 'bills.client_id')
        ->select('bills.bill_number', 'bills.due_date', 'bills.amount', 'bills.status', 'clients.full_name')
        ->whereBetween('bills.billing_period_start', [$periodStart, $periodEnd])
        ->orderBy('bills.due_date')->get();
    $openBills = $bills->whereIn('status', ['pending', 'overdue']);
    $lowStockItems = DB::table('inventory')->select('item_name', 'category', 'quantity', 'reorder_level')
        ->where('archived', 0)->where('status', 'active')->whereColumn('quantity', '<=', 'reorder_level')->orderBy('quantity')->limit(8)->get();
    $clientStatus = DB::table('clients')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');

    return [
        'currentPage' => 'reports',
        'themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1,
        'reportsCssVersion' => @filemtime(public_path('css/reports.css')) ?: 1,
        'month' => $month, 'periodStart' => $periodStart, 'periodEnd' => $periodEnd,
        'payments' => $payments, 'bills' => $bills, 'lowStockItems' => $lowStockItems, 'clientStatus' => $clientStatus,
        'metrics' => [
            'collections' => (float) DB::table('payments')->whereBetween('payment_date', [$periodStart, $periodEnd])->sum('amount'),
            'paymentCount' => DB::table('payments')->whereBetween('payment_date', [$periodStart, $periodEnd])->count(),
            'outstanding' => (float) $openBills->sum('amount'),
            'openBills' => $openBills->count(),
            'activeClients' => (int) ($clientStatus['active'] ?? 0),
            'totalClients' => (int) $clientStatus->sum(),
            'lowStock' => $lowStockItems->count(),
        ],
    ];
};
