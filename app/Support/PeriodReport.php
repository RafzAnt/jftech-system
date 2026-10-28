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

        $newClients = self::inBranch(DB::table('clients')->whereBetween('installation_date', [$start, $end]), BranchScope::clients($branch, 'clients'))
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(monthly_rate), 0) AS monthly')->first();

        $attendance = self::inBranch(DB::table('attendance')->whereBetween('attendance_date', [$start, $end]), BranchScope::attendance($branch, 'attendance'))
            ->selectRaw("
                COUNT(*) AS records,
                COUNT(DISTINCT employee_id) AS employees,
                COALESCE(SUM(CASE WHEN status_morning = 'present' OR status_afternoon = 'present' THEN 1 ELSE 0 END), 0) AS present,
                COALESCE(SUM(CASE WHEN status_morning = 'late' OR status_afternoon = 'late' THEN 1 ELSE 0 END), 0) AS late,
                COALESCE(SUM(CASE WHEN status_morning = 'absent' AND status_afternoon = 'absent' THEN 1 ELSE 0 END), 0) AS absent,
                COALESCE(SUM(total_hours), 0) AS hours
            ")->first();

        $payroll = self::inBranch(DB::table('payroll')->where('archived', 0)->whereBetween('payroll_period_end', [$start, $end]), BranchScope::viaEmployee($branch, 'payroll.employee_id'))
            ->selectRaw('COUNT(*) AS entries, COALESCE(SUM(gross_pay), 0) AS gross, COALESCE(SUM(deductions), 0) AS deductions, COALESCE(SUM(net_pay), 0) AS net')->first();

        // Stock requests are not split by branch, so they only appear in the all-branches report.
        $requestStatus = $branch === null
            ? DB::table('inventory_requests')
                ->whereDate('created_at', '>=', $start)->whereDate('created_at', '<=', $end)
                ->select('status', DB::raw('COUNT(*) AS total'), DB::raw('COALESCE(SUM(quantity), 0) AS quantity'))->groupBy('status')->get()->keyBy('status')
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
            'clients' => ['new' => (int) $newClients->total, 'monthly' => (float) $newClients->monthly],
            'attendance' => [
                'records' => (int) $attendance->records,
                'employees' => (int) $attendance->employees,
                'present' => (int) $attendance->present,
                'late' => (int) $attendance->late,
                'absent' => (int) $attendance->absent,
                'hours' => (float) $attendance->hours,
            ],
            'payroll' => [
                'entries' => (int) $payroll->entries,
                'gross' => (float) $payroll->gross,
                'deductions' => (float) $payroll->deductions,
                'net' => (float) $payroll->net,
            ],
            'requests' => $requestStatus === null ? null : [
                'total' => (int) $requestStatus->sum('total'),
                'quantity' => (int) $requestStatus->sum('quantity'),
                'approved' => (int) ($requestStatus['approved']->total ?? 0),
                'pending' => (int) ($requestStatus['pending']->total ?? 0),
                'denied' => (int) ($requestStatus['denied']->total ?? 0),
            ],
            // Side by side, one line per branch; only for the report that covers every branch.
            'branches' => $branch === null ? self::byBranch($start, $end) : null,
        ];
    }

    /**
     * One line per branch for the all-branches report: what it billed, collected and still has outstanding,
     * its new installations and its net payroll. Only branches with something in the period are listed.
     * The lines add up to the same totals as the report above them.
     */
    private static function byBranch(string $start, string $end): array
    {
        $rows = [];
        $line = static function (?string $name) use (&$rows): string {
            $name = trim((string) $name) !== '' ? trim((string) $name) : 'No branch';
            $rows[$name] ??= ['branch' => $name, 'newClients' => 0, 'billed' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0, 'payroll' => 0.0];

            return $name;
        };

        // What each bill has received is worked out per bill first: MySQL does not allow it inside the grouped SUM.
        $billed = DB::table('bills')->whereBetween('billing_period_start', [$start, $end])->where('status', '<>', 'cancelled')
            ->select('client_id', 'amount', 'status', DB::raw('(SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.bill_id = bills.id) AS received'));
        $bills = DB::query()->fromSub($billed, 'b')->join('clients', 'clients.id', '=', 'b.client_id')
            ->groupBy('clients.branch_name')
            ->selectRaw("clients.branch_name AS branch, COALESCE(SUM(b.amount), 0) AS billed,
                COALESCE(SUM(CASE WHEN b.status IN ('pending', 'overdue') THEN GREATEST(COALESCE(b.amount, 0) - b.received, 0) ELSE 0 END), 0) AS outstanding")
            ->get();
        foreach ($bills as $row) {
            $name = $line($row->branch);
            $rows[$name]['billed'] = (float) $row->billed;
            $rows[$name]['outstanding'] = (float) $row->outstanding;
        }

        $payments = DB::table('payments')->join('clients', 'clients.id', '=', 'payments.client_id')
            ->whereBetween('payments.payment_date', [$start, $end])
            ->groupBy('clients.branch_name')->selectRaw('clients.branch_name AS branch, COALESCE(SUM(payments.amount), 0) AS collected')->get();
        foreach ($payments as $row) {
            $rows[$line($row->branch)]['collected'] = (float) $row->collected;
        }

        $clients = DB::table('clients')->whereBetween('installation_date', [$start, $end])
            ->groupBy('branch_name')->selectRaw('branch_name AS branch, COUNT(*) AS total')->get();
        foreach ($clients as $row) {
            $rows[$line($row->branch)]['newClients'] = (int) $row->total;
        }

        $payroll = DB::table('payroll')->join('employees', 'employees.id', '=', 'payroll.employee_id')
            ->where('payroll.archived', 0)->whereBetween('payroll.payroll_period_end', [$start, $end])
            ->groupBy('employees.branch_name')->selectRaw('employees.branch_name AS branch, COALESCE(SUM(payroll.net_pay), 0) AS net')->get();
        foreach ($payroll as $row) {
            $rows[$line($row->branch)]['payroll'] = (float) $row->net;
        }

        uasort($rows, static fn (array $a, array $b): int => [$b['billed'], $a['branch']] <=> [$a['billed'], $b['branch']]);
        $rows = array_values($rows);
        $sum = static fn (string $key) => array_sum(array_column($rows, $key));

        return [
            'rows' => $rows,
            'total' => ['newClients' => (int) $sum('newClients'), 'billed' => (float) $sum('billed'), 'collected' => (float) $sum('collected'), 'outstanding' => (float) $sum('outstanding'), 'payroll' => (float) $sum('payroll')],
        ];
    }
}
