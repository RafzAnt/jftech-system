<?php

use Illuminate\Support\Facades\DB;

return function (\Illuminate\Http\Request $request): array {
    $from = (string) $request->query('from', now()->startOfMonth()->toDateString());
    $to = (string) $request->query('to', now()->endOfMonth()->toDateString());
    $isValidDate = static fn (string $date): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        && strtotime($date) !== false;

    if (! $isValidDate($from) || ! $isValidDate($to)) {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    $payments = DB::table('payments')->join('clients', 'clients.id', '=', 'payments.client_id')
        ->select('payments.payment_date', 'payments.reference_number', 'payments.payment_method', 'payments.amount', 'clients.full_name')
        ->whereBetween('payments.payment_date', [$from, $to])->orderBy('payments.payment_date')->orderBy('payments.id')->get();
    $bills = DB::table('bills')->join('clients', 'clients.id', '=', 'bills.client_id')
        ->select('bills.bill_number', 'bills.billing_period_start', 'bills.billing_period_end', 'bills.due_date', 'bills.amount', 'bills.status', 'clients.full_name')
        ->whereBetween('bills.billing_period_start', [$from, $to])->orderBy('bills.due_date')->orderBy('bills.id')->get();
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
        'payments' => $payments,
        'bills' => $bills,
        'collections' => (float) $payments->sum('amount'),
        'outstanding' => (float) $bills->whereIn('status', ['pending', 'overdue'])->sum('amount'),
        'openBills' => $bills->whereIn('status', ['pending', 'overdue'])->count(),
        'attendanceSummary' => $attendanceSummary,
        'systemSnapshot' => $systemSnapshot,
    ];
};
