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

    return [
        'from' => $from,
        'to' => $to,
        'payments' => $payments,
        'bills' => $bills,
        'collections' => (float) $payments->sum('amount'),
        'outstanding' => (float) $bills->whereIn('status', ['pending', 'overdue'])->sum('amount'),
        'openBills' => $bills->whereIn('status', ['pending', 'overdue'])->count(),
    ];
};
