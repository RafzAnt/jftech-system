<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Period summary report.
 *
 * Every figure is limited to the selected period (a month or a custom date
 * range) and comes straight from system records. No "current snapshot" values
 * are mixed in, so choosing September shows only what happened in September.
 * The on-screen report and the printable report both use this class.
 */
class PeriodReport
{
    /** Resolve ?month=YYYY-MM or ?from=&to= into a valid [start, end, month] period. */
    public static function periodFromRequest(Request $request): array
    {
        $isDate = static fn (string $date): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));

        $month = (string) $request->query('month', '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        // A complete date range wins over the month picker.
        if ($isDate($from) && $isDate($to)) {
            [$start, $end] = $from <= $to ? [$from, $to] : [$to, $from];
        } else {
            $start = $month . '-01';
            $end = date('Y-m-t', strtotime($start));
        }

        return [$start, $end, substr($start, 0, 7)];
    }

    /** "September 2026" for a whole month, otherwise "Sep 01, 2026 – Sep 15, 2026". */
    public static function label(string $start, string $end): string
    {
        if ($start === date('Y-m-01', strtotime($start)) && $end === date('Y-m-t', strtotime($start))) {
            return date('F Y', strtotime($start));
        }

        return date('M d, Y', strtotime($start)) . ' – ' . date('M d, Y', strtotime($end));
    }

    /** Limit a query to one branch (null = every branch), using a BranchScope fragment. */
    private static function inBranch($query, array $fragment)
    {
        [$sql, $params] = $fragment;
        if ($sql !== '') {
            $query->whereRaw(substr($sql, strlen(' AND ')), $params);
        }

        return $query;
    }

    /** $branch: null = every branch, otherwise only that branch's clients and employees. */
    public static function build(string $start, string $end, ?string $branch = null): array
    {
        // Cancelled bills were never owed, so they stay out of the billed totals.
        $bills = self::inBranch(DB::table('bills')->whereBetween('billing_period_start', [$start, $end]), BranchScope::viaClient($branch, 'bills.client_id'))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END), 0) AS total_count,
                COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN amount ELSE 0 END), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END), 0) AS overdue_count,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END), 0) AS overdue_amount,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                COALESCE(SUM(CASE WHEN status IN ('pending', 'overdue') THEN GREATEST(COALESCE(amount, 0) - (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.bill_id = bills.id), 0) ELSE 0 END), 0) AS outstanding,
                COUNT(DISTINCT CASE WHEN status <> 'cancelled' THEN client_id END) AS billed_clients,
                COUNT(DISTINCT CASE WHEN status = 'overdue' THEN client_id END) AS overdue_clients
            ")->first();

        $paymentScope = BranchScope::viaClient($branch, 'payments.client_id');
        $payments = self::inBranch(DB::table('payments')->whereBetween('payment_date', [$start, $end]), $paymentScope)
            ->selectRaw('COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS collected')->first();
        $paymentMethods = self::inBranch(DB::table('payments')->whereBetween('payment_date', [$start, $end]), $paymentScope)
            ->select('payment_method', DB::raw('COUNT(*) AS payment_count'), DB::raw('SUM(amount) AS total'))
            ->groupBy('payment_method')->orderByDesc('total')->get();

        $newClients = self::inBranch(DB::table('clients')->whereBetween('installation_date', [$start, $end]), BranchScope::clients($branch, 'clients'))->count();

        $attendance = self::inBranch(DB::table('attendance')->whereBetween('attendance_date', [$start, $end]), BranchScope::attendance($branch, 'attendance'))
            ->selectRaw("
                COUNT(*) AS records,
                COUNT(DISTINCT employee_id) AS employees,
                COALESCE(SUM(CASE WHEN status_morning = 'present' OR status_afternoon = 'present' THEN 1 ELSE 0 END), 0) AS present,
                COALESCE(SUM(CASE WHEN status_morning = 'late' OR status_afternoon = 'late' THEN 1 ELSE 0 END), 0) AS late,
                COALESCE(SUM(CASE WHEN status_morning = 'absent' AND status_afternoon = 'absent' THEN 1 ELSE 0 END), 0) AS absent
            ")->first();

        $payroll = self::inBranch(DB::table('payroll')->where('archived', 0)->whereBetween('payroll_period_end', [$start, $end]), BranchScope::viaEmployee($branch, 'payroll.employee_id'))
            ->selectRaw('COUNT(*) AS entries, COALESCE(SUM(gross_pay), 0) AS gross, COALESCE(SUM(deductions), 0) AS deductions, COALESCE(SUM(net_pay), 0) AS net')->first();

        // Stock requests are not split by branch, so they only appear in the all-branches report.
        $requestStatus = $branch === null
            ? DB::table('inventory_requests')
                ->whereDate('created_at', '>=', $start)->whereDate('created_at', '<=', $end)
                ->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status')
            : null;

        $totalAmount = (float) $bills->total_amount;

        return [
            'start' => $start,
            'end' => $end,
            'label' => self::label($start, $end),
            'bills' => [
                'count' => (int) $bills->total_count,
                'amount' => $totalAmount,
                'paidCount' => (int) $bills->paid_count,
                'paidAmount' => (float) $bills->paid_amount,
                'pendingCount' => (int) $bills->pending_count,
                'pendingAmount' => (float) $bills->pending_amount,
                'overdueCount' => (int) $bills->overdue_count,
                'overdueAmount' => (float) $bills->overdue_amount,
                'cancelledCount' => (int) $bills->cancelled_count,
                'outstanding' => (float) $bills->outstanding,
                'paidRate' => $totalAmount > 0 ? ((float) $bills->paid_amount / $totalAmount) * 100 : 0,
                'billedClients' => (int) $bills->billed_clients,
                'overdueClients' => (int) $bills->overdue_clients,
            ],
            'payments' => [
                'count' => (int) $payments->payment_count,
                'collected' => (float) $payments->collected,
                'methods' => $paymentMethods,
            ],
            'clients' => ['new' => $newClients],
            'attendance' => [
                'records' => (int) $attendance->records,
                'employees' => (int) $attendance->employees,
                'present' => (int) $attendance->present,
                'late' => (int) $attendance->late,
                'absent' => (int) $attendance->absent,
            ],
            'payroll' => [
                'entries' => (int) $payroll->entries,
                'gross' => (float) $payroll->gross,
                'deductions' => (float) $payroll->deductions,
                'net' => (float) $payroll->net,
            ],
            'requests' => $requestStatus === null ? null : [
                'total' => (int) $requestStatus->sum(),
                'approved' => (int) ($requestStatus['approved'] ?? 0),
                'pending' => (int) ($requestStatus['pending'] ?? 0),
                'denied' => (int) ($requestStatus['denied'] ?? 0),
            ],
            'details' => self::details($start, $end, $branch),
        ];
    }

    /**
     * The records behind the summary figures, one list per section with its own total row.
     * Every total is added up from the listed rows, so the printed lines always agree with it.
     */
    private static function details(string $start, string $end, ?string $branch): array
    {
        $clientScope = BranchScope::clients($branch, 'clients');
        $employeeScope = BranchScope::employees($branch, 'employees');

        // Cancelled bills were never owed, so they are not listed (the summary counts them separately).
        $bills = self::inBranch(DB::table('bills')->join('clients', 'clients.id', '=', 'bills.client_id'), $clientScope)
            ->whereBetween('bills.billing_period_start', [$start, $end])
            ->where('bills.status', '<>', 'cancelled')
            ->select(
                'bills.bill_number', 'bills.billing_period_start', 'bills.billing_period_end', 'bills.due_date', 'bills.status', 'bills.amount',
                'clients.client_code', 'clients.full_name AS client_name',
                DB::raw('(SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.bill_id = bills.id) AS received')
            )
            ->orderBy('clients.full_name')->orderBy('bills.billing_period_start')->orderBy('bills.id')
            ->get()
            ->map(function ($row) {
                $row = (array) $row;
                $row['amount'] = (float) $row['amount'];
                $row['received'] = (float) $row['received'];
                // Same rule as the "outstanding" summary figure: only open bills still owe anything.
                $row['balance'] = in_array($row['status'], ['pending', 'overdue'], true) ? max($row['amount'] - $row['received'], 0.0) : 0.0;

                return $row;
            })->all();

        $payments = self::inBranch(DB::table('payments')
            ->join('clients', 'clients.id', '=', 'payments.client_id')
            ->leftJoin('bills', 'bills.id', '=', 'payments.bill_id'), $clientScope)
            ->whereBetween('payments.payment_date', [$start, $end])
            ->select('payments.payment_date', 'payments.payment_method', 'payments.reference_number', 'payments.amount', 'clients.client_code', 'clients.full_name AS client_name', 'bills.bill_number')
            ->orderBy('payments.payment_date')->orderBy('payments.id')
            ->get()
            ->map(fn ($row) => ['amount' => (float) $row->amount] + (array) $row)->all();

        $clients = self::inBranch(DB::table('clients'), $clientScope)
            ->whereBetween('clients.installation_date', [$start, $end])
            ->select('clients.client_code', 'clients.full_name', 'clients.branch_name', 'clients.registered_barangay_name', 'clients.wifi_plan', 'clients.monthly_rate', 'clients.installation_date')
            ->orderBy('clients.installation_date')->orderBy('clients.id')
            ->get()
            ->map(fn ($row) => ['monthly_rate' => (float) $row->monthly_rate] + (array) $row)->all();

        // One line per employee; a day counts the same way as in the summary figures.
        $attendance = self::inBranch(DB::table('attendance')->join('employees', 'employees.id', '=', 'attendance.employee_id'), BranchScope::attendance($branch, 'attendance'))
            ->whereBetween('attendance.attendance_date', [$start, $end])
            ->groupBy('employees.id', 'employees.employee_code', 'employees.full_name', 'employees.position')
            ->selectRaw("
                employees.employee_code, employees.full_name, employees.position,
                COUNT(*) AS records,
                COALESCE(SUM(CASE WHEN attendance.status_morning = 'present' OR attendance.status_afternoon = 'present' THEN 1 ELSE 0 END), 0) AS present,
                COALESCE(SUM(CASE WHEN attendance.status_morning = 'late' OR attendance.status_afternoon = 'late' THEN 1 ELSE 0 END), 0) AS late,
                COALESCE(SUM(CASE WHEN attendance.status_morning = 'absent' AND attendance.status_afternoon = 'absent' THEN 1 ELSE 0 END), 0) AS absent,
                COALESCE(SUM(attendance.total_hours), 0) AS hours
            ")
            ->orderBy('employees.full_name')
            ->get()
            ->map(fn ($row) => [
                'employee_code' => $row->employee_code, 'full_name' => $row->full_name, 'position' => $row->position,
                'records' => (int) $row->records, 'present' => (int) $row->present, 'late' => (int) $row->late,
                'absent' => (int) $row->absent, 'hours' => (float) $row->hours,
            ])->all();

        $payroll = self::inBranch(DB::table('payroll')->join('employees', 'employees.id', '=', 'payroll.employee_id'), $employeeScope)
            ->where('payroll.archived', 0)
            ->whereBetween('payroll.payroll_period_end', [$start, $end])
            ->select('employees.employee_code', 'employees.full_name', 'employees.position', 'payroll.payroll_period_start', 'payroll.payroll_period_end', 'payroll.total_days_worked', 'payroll.gross_pay', 'payroll.deductions', 'payroll.net_pay', 'payroll.status')
            ->orderBy('employees.full_name')->orderBy('payroll.payroll_period_start')->orderBy('payroll.id')
            ->get()
            ->map(fn ($row) => (array) $row)->all();

        // Stock requests are not split by branch, so they only appear in the all-branches report.
        $requests = $branch !== null ? null : DB::table('inventory_requests')
            ->whereDate('created_at', '>=', $start)->whereDate('created_at', '<=', $end)
            ->select('created_at', 'employee_name', 'item_name', 'quantity', 'status')
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)->all();

        $sum = static fn (array $rows, string $key): float => array_sum(array_map(static fn ($row) => (float) $row[$key], $rows));

        return [
            'bills' => [
                'rows' => $bills,
                'total' => ['amount' => $sum($bills, 'amount'), 'received' => $sum($bills, 'received'), 'balance' => $sum($bills, 'balance')],
            ],
            'payments' => [
                'rows' => $payments,
                'total' => ['amount' => $sum($payments, 'amount')],
            ],
            'clients' => [
                'rows' => $clients,
                'total' => ['monthly' => $sum($clients, 'monthly_rate')],
            ],
            'attendance' => [
                'rows' => $attendance,
                'total' => ['records' => (int) $sum($attendance, 'records'), 'present' => (int) $sum($attendance, 'present'), 'late' => (int) $sum($attendance, 'late'), 'absent' => (int) $sum($attendance, 'absent'), 'hours' => $sum($attendance, 'hours')],
            ],
            'payroll' => [
                'rows' => $payroll,
                'total' => ['days' => $sum($payroll, 'total_days_worked'), 'gross' => $sum($payroll, 'gross_pay'), 'deductions' => $sum($payroll, 'deductions'), 'net' => $sum($payroll, 'net_pay')],
            ],
            'requests' => $requests === null ? null : [
                'rows' => $requests,
                'total' => ['quantity' => (int) $sum($requests, 'quantity')],
            ],
        ];
    }
}
