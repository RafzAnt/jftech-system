<?php

use Illuminate\Support\Facades\DB;

return function (\Illuminate\Http\Request $request): array {
    $from = (string) $request->query('from', now()->startOfMonth()->toDateString());
    $to = (string) $request->query('to', now()->endOfMonth()->toDateString());
    $isValidDate = static fn (string $date): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));

    if (! $isValidDate($from) || ! $isValidDate($to)) {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    $paymentSummary = DB::table('payments')->whereBetween('payment_date', [$from, $to])
        ->selectRaw('COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS collections')->first();
    $billSummary = DB::table('bills')->whereBetween('billing_period_start', [$from, $to])
        ->selectRaw("COUNT(*) AS total_bills,
            COALESCE(SUM(amount), 0) AS total_bills_amount,
            COALESCE(SUM(CASE WHEN status IN ('pending', 'overdue') THEN GREATEST(COALESCE(amount, 0) - (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.bill_id = bills.id), 0) ELSE 0 END), 0) AS outstanding,
            COALESCE(SUM(CASE WHEN status IN ('pending', 'overdue') THEN 1 ELSE 0 END), 0) AS open_bills")
        ->first();
    $attendanceSummary = DB::table('attendance')->whereBetween('attendance_date', [$from, $to])
        ->selectRaw("COUNT(*) AS records, SUM(CASE WHEN status_morning = 'present' OR status_afternoon = 'present' THEN 1 ELSE 0 END) AS present, SUM(CASE WHEN status_morning = 'late' OR status_afternoon = 'late' THEN 1 ELSE 0 END) AS late")
        ->first();
    $systemSnapshot = [
        'activeClients' => DB::table('clients')->where('status', 'active')->where(function ($query) { $query->whereNull('archived')->orWhere('archived', 0); })->count(),
        'activeEmployees' => DB::table('employees')->where('status', 'active')->count(),
        'lowStock' => DB::table('inventory')->where('archived', 0)->where('status', 'active')->whereColumn('quantity', '<=', 'reorder_level')->count(),
    ];

    return [
        'from' => $from,
        'to' => $to,
        'paymentCount' => (int) $paymentSummary->payment_count,
        'collections' => (float) $paymentSummary->collections,
        'totalBills' => (int) $billSummary->total_bills,
        'totalBillsAmount' => (float) $billSummary->total_bills_amount,
        'outstanding' => (float) $billSummary->outstanding,
        'openBills' => (int) $billSummary->open_bills,
        'attendanceSummary' => $attendanceSummary,
        'systemSnapshot' => $systemSnapshot,
    ];
};
