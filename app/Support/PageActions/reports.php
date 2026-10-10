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
        && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));

    // A complete date range takes priority over the month selector.
    if ($isValidDate($from) && $isValidDate($to)) {
        [$periodStart, $periodEnd] = $from <= $to ? [$from, $to] : [$to, $from];
    } else {
        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
    }

    $paymentSummary = DB::table('payments')->whereBetween('payment_date', [$periodStart, $periodEnd])
        ->selectRaw('COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS collections')->first();
    $billSummary = DB::table('bills')->whereBetween('billing_period_start', [$periodStart, $periodEnd])
        ->selectRaw("COUNT(*) AS total_bills,
            COALESCE(SUM(amount), 0) AS billed_amount,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS settled_amount,
            COALESCE(SUM(CASE WHEN status IN ('pending', 'overdue') THEN GREATEST(COALESCE(amount, 0) - (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.bill_id = bills.id), 0) ELSE 0 END), 0) AS outstanding,
            COALESCE(SUM(CASE WHEN status IN ('pending', 'overdue') THEN 1 ELSE 0 END), 0) AS open_bills")
        ->first();
    $lowStockCount = DB::table('inventory')->where('archived', 0)->where('status', 'active')
        ->whereColumn('quantity', '<=', 'reorder_level')->count();
    $clientStatus = DB::table('clients')->where(function ($query) {
        $query->whereNull('archived')->orWhere('archived', 0);
    })->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
    $attendanceSummary = DB::table('attendance')
        ->whereBetween('attendance_date', [$periodStart, $periodEnd])
        ->selectRaw("COUNT(*) AS records, SUM(CASE WHEN status_morning = 'present' OR status_afternoon = 'present' THEN 1 ELSE 0 END) AS present, SUM(CASE WHEN status_morning = 'late' OR status_afternoon = 'late' THEN 1 ELSE 0 END) AS late, SUM(CASE WHEN status_morning = 'absent' AND status_afternoon = 'absent' THEN 1 ELSE 0 END) AS absent")
        ->first();
    $systemSnapshot = [
        'activeEmployees' => DB::table('employees')->where('status', 'active')->count(),
        'activeUsers' => DB::table('users')->where('status', 'active')->where(function ($query) {
            $query->whereNull('archived')->orWhere('archived', 0);
        })->count(),
        'inventoryValue' => (float) DB::table('inventory')->where('archived', 0)->where('status', 'active')->sum(DB::raw('quantity * unit_price')),
    ];

    $billedAmount = (float) $billSummary->billed_amount;
    $settledAmount = (float) $billSummary->settled_amount;

    return [
        'currentPage' => 'reports',
        'themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1,
        'reportsCssVersion' => @filemtime(public_path('css/reports.css')) ?: 1,
        'month' => $month, 'periodStart' => $periodStart, 'periodEnd' => $periodEnd,
        'clientStatus' => $clientStatus,
        'attendanceSummary' => $attendanceSummary, 'systemSnapshot' => $systemSnapshot,
        'metrics' => [
            'collections' => (float) $paymentSummary->collections,
            'paymentCount' => (int) $paymentSummary->payment_count,
            'totalBills' => (int) $billSummary->total_bills,
            'totalBillsAmount' => $billedAmount,
            'settlementRate' => $billedAmount > 0 ? ($settledAmount / $billedAmount) * 100 : 0,
            'outstanding' => (float) $billSummary->outstanding,
            'openBills' => (int) $billSummary->open_bills,
            'activeClients' => (int) ($clientStatus['active'] ?? 0),
            'totalClients' => (int) $clientStatus->sum(),
            'lowStock' => $lowStockCount,
        ],
    ];
};
