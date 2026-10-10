<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * ReportService
 *
 * Central data provider for the Reports module. Every metric is computed
 * from real system records (no hardcoded or dummy values). The same queries
 * are reused by the reports page, the printable view and the CSV export so the
 * rendered report always matches the on-screen results.
 */
class ReportService
{
    protected $pdo;

    public function __construct()
    {
        $this->pdo = DB::connection()->getPdo();
    }

    /**
     * The canonical UNION ALL that consolidates system activity from every
     * transactional table into a single, filterable feed.
     */
    protected function activityUnion(): string
    {
        return "
            SELECT c.id AS record_id, 'client' AS report_type, 'Client Registration' AS activity_type, c.created_at AS activity_datetime,
                c.full_name AS person, c.client_code AS record_code, c.status AS status,
                NULL AS amount, NULL AS quantity,
                CONCAT('Plan: ', COALESCE(NULLIF(c.wifi_plan,''), 'N/A'), ' | Rate: ₱', c.monthly_rate) AS remarks,
                'Clients' AS category,
                CASE WHEN c.status='active' THEN 'completed' ELSE 'pending' END AS outcome
            FROM clients c

            UNION ALL

            SELECT e.id, 'employee', 'Employee Record', e.created_at,
                e.full_name, e.employee_code, e.status,
                NULL, NULL,
                CONCAT('Dept: ', COALESCE(e.department,'N/A'), ' | Position: ', COALESCE(e.position,'N/A')),
                COALESCE(e.department,'N/A'),
                CASE WHEN e.status='active' THEN 'completed' WHEN e.status='terminated' THEN 'cancelled' ELSE 'pending' END
            FROM employees e

            UNION ALL

            SELECT b.id, 'bill', 'Bill Issued', b.created_at,
                c.full_name, b.bill_number, b.status,
                b.amount, NULL,
                CONCAT('Period: ', b.billing_period_start, ' to ', b.billing_period_end, ' | Due: ', b.due_date),
                'Billing',
                CASE WHEN b.status='paid' THEN 'completed' WHEN b.status='overdue' THEN 'overdue' WHEN b.status='cancelled' THEN 'cancelled' ELSE 'pending' END
             FROM bills b
             JOIN clients c ON b.client_id = c.id

            UNION ALL

            SELECT p.id, 'payment', 'Payment Received', p.created_at,
                COALESCE(u.full_name, c.full_name), p.reference_number, 'completed' AS status,
                p.amount, NULL,
                CONCAT('Payment Method: ', p.payment_method, ' | Reference: ', COALESCE(p.reference_number,'N/A')),
                'Payments',
                'completed' AS outcome
            FROM payments p
            JOIN clients c ON p.client_id = c.id
            LEFT JOIN users u ON p.received_by = u.id

            UNION ALL

            SELECT a.id, 'attendance', 'Attendance Entry', a.created_at,
                e.full_name, e.employee_code, CONCAT(a.status_morning,'/',a.status_afternoon),
                NULL, a.total_hours,
                CONCAT('Date: ', a.attendance_date, ' | Hours: ', a.total_hours),
                COALESCE(e.department,'N/A'),
                'completed' AS outcome
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id

            UNION ALL

            SELECT i.id, 'inventory', 'Stock Item', i.created_at,
                'System', i.category, i.status,
                i.unit_price, i.quantity,
                CONCAT('Category: ', COALESCE(i.category,'N/A'), ' | Supplier: ', COALESCE(i.supplier,'N/A')),
                COALESCE(i.category,'N/A'),
                CASE WHEN i.status='active' THEN 'completed' ELSE 'cancelled' END
            FROM inventory i

            UNION ALL

            SELECT ir.id, 'inventory_request', 'Stock Request', ir.created_at,
                ir.employee_name, ir.item_name, ir.status,
                NULL, ir.quantity,
                CONCAT('Quantity: ', ir.quantity, ' | Notes: ', COALESCE(ir.comments,'N/A')),
                'Stock Requests',
                CASE WHEN ir.status='approved' THEN 'completed' WHEN ir.status='denied' THEN 'cancelled' ELSE 'pending' END
            FROM inventory_requests ir

            UNION ALL

            SELECT py.id, 'payroll', 'Payroll Processed', py.created_at,
                e.full_name, e.employee_code, py.status,
                py.net_pay, py.total_days_worked,
                CONCAT('Period: ', py.payroll_period_start, ' to ', py.payroll_period_end, ' | Gross: ₱', py.gross_pay),
                COALESCE(e.department,'N/A'),
                CASE WHEN py.status='paid' THEN 'completed' WHEN py.status IN ('draft','processed') THEN 'pending' ELSE 'pending' END
            FROM payroll py
            JOIN employees e ON py.employee_id = e.id

            UNION ALL

            SELECT lh.id, 'login', 'User Login', lh.login_time,
                COALESCE(lh.full_name, lh.username), lh.username, lh.status,
                NULL, NULL,
                CONCAT('IP: ', COALESCE(lh.ip_address,'N/A'), ' | Role: ', COALESCE(lh.role,'N/A')),
                COALESCE(lh.role,'N/A'),
                CASE WHEN lh.status='success' THEN 'completed' ELSE 'cancelled' END
            FROM login_history lh
        ";
    }

    /**
     * Build WHERE clause + params for the activity feed from a filter array.
     * Supported keys: date, from, to, report_type, status, person, category.
     */
    protected function buildWhere(array $f): array
    {
        $where = ['activity_datetime IS NOT NULL'];
        $params = [];

        if (! empty($f['date'])) {
            $where[] = 'activity_datetime >= ?'; $params[] = $f['date'] . ' 00:00:00';
            $where[] = 'activity_datetime <= ?'; $params[] = $f['date'] . ' 23:59:59';
        }
        if (! empty($f['from'])) {
            $where[] = 'activity_datetime >= ?'; $params[] = $f['from'] . ' 00:00:00';
        }
        if (! empty($f['to'])) {
            $where[] = 'activity_datetime <= ?'; $params[] = $f['to'] . ' 23:59:59';
        }
        if (! empty($f['report_type']) && $f['report_type'] !== 'all') {
            $where[] = "report_type = ?"; $params[] = $f['report_type'];
        }
        if (! empty($f['status']) && $f['status'] !== 'all') {
            $where[] = "status = ?"; $params[] = $f['status'];
        }
        if (! empty($f['person'])) {
            $term = '%' . $f['person'] . '%';
            $where[] = "(person LIKE ? OR record_code LIKE ? OR remarks LIKE ?)";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if (! empty($f['category']) && $f['category'] !== 'all') {
            $where[] = "category = ?"; $params[] = $f['category'];
        }

        return [$where, $params];
    }

    /** Unified activity feed, optionally filtered and limited. */
    public function activityFeed(array $f, int $limit = 500): array
    {
        [$where, $params] = $this->buildWhere($f);
        $sql = "SELECT record_id, report_type, activity_type, activity_datetime, person, record_code, status, amount, quantity, remarks, category, outcome
                FROM (" . $this->activityUnion() . ") a
                WHERE " . implode(' AND ', $where) . "
                ORDER BY activity_datetime DESC
                LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Dashboard: aggregate counts across the workflow tables (real data). */
    public function dashboardStats(): array
    {
        $q = function (string $sql): int {
            return (int) $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC)['total'];
        };

        // Total number of records across every entity table.
        $totalRecords = $q("SELECT (SELECT COUNT(*) FROM clients)
            + (SELECT COUNT(*) FROM employees)
            + (SELECT COUNT(*) FROM bills)
            + (SELECT COUNT(*) FROM payments)
            + (SELECT COUNT(*) FROM attendance)
            + (SELECT COUNT(*) FROM inventory WHERE archived = 0)
            + (SELECT COUNT(*) FROM inventory_requests)
            + (SELECT COUNT(*) FROM payroll)
            + (SELECT COUNT(*) FROM login_history) AS total");

        // Completed activities.
        $completed = $q("SELECT (SELECT COUNT(*) FROM bills WHERE status = 'paid')
            + (SELECT COUNT(*) FROM payments)
            + (SELECT COUNT(*) FROM inventory_requests WHERE status = 'approved')
            + (SELECT COUNT(*) FROM payroll WHERE status = 'paid') AS total");

        // Pending activities (excluding overdue).
        $pending = $q("SELECT (SELECT COUNT(*) FROM bills WHERE status = 'pending')
            + (SELECT COUNT(*) FROM inventory_requests WHERE status = 'pending')
            + (SELECT COUNT(*) FROM payroll WHERE status IN ('draft','processed')) AS total");

        // Overdue bills.
        $overdue = $q("SELECT COUNT(*) AS total FROM bills WHERE status = 'overdue'");

        // Cancelled / rejected activities.
        $cancelled = $q("SELECT (SELECT COUNT(*) FROM bills WHERE status = 'cancelled')
            + (SELECT COUNT(*) FROM inventory_requests WHERE status = 'denied')
            + (SELECT COUNT(*) FROM login_history WHERE status = 'failed') AS total");

        return compact('totalRecords', 'completed', 'pending', 'cancelled', 'overdue');
    }

    /**
     * Business KPIs for the Reports landing page. These deliberately use the
     * source-of-truth tables instead of summing the mixed activity feed: bills
     * are amounts due, while payments are actual cash received.
     */
    public function businessMetrics(): array
    {
        $monthStart = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('first day of next month'));
        $stmt = $this->pdo->prepare("SELECT
            (SELECT COUNT(*) FROM clients WHERE status = 'active') AS active_clients,
            (SELECT COUNT(*) FROM bills WHERE status IN ('pending', 'overdue')) AS open_bills,
            (SELECT COALESCE(SUM(amount), 0) FROM bills WHERE status IN ('pending', 'overdue')) AS outstanding_balance,
            (SELECT COALESCE(SUM(amount), 0) FROM payments) AS payments_received,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date >= ? AND payment_date < ?) AS payments_this_month,
            (SELECT COALESCE(SUM(quantity * unit_price), 0) FROM inventory WHERE archived = 0 AND status = 'active') AS inventory_value,
            (SELECT COUNT(*) FROM inventory WHERE archived = 0 AND status = 'active' AND quantity <= reorder_level) AS low_stock_items");
        $stmt->execute([$monthStart, $nextMonth]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'active_clients' => (int) $row['active_clients'],
            'open_bills' => (int) $row['open_bills'],
            'outstanding_balance' => (float) $row['outstanding_balance'],
            'payments_received' => (float) $row['payments_received'],
            'payments_this_month' => (float) $row['payments_this_month'],
            'inventory_value' => (float) $row['inventory_value'],
            'low_stock_items' => (int) $row['low_stock_items'],
        ];
    }

    /** Today's activity broken down by outcome (completed/pending/cancelled). */
    public function todaySummary(): array
    {
        $sql = "SELECT outcome, COUNT(*) AS cnt FROM (" . $this->activityUnion() . ") a
                WHERE DATE(activity_datetime) = CURRENT_DATE AND outcome IS NOT NULL
                GROUP BY outcome";
        $rows = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $map = ['completed' => 0, 'pending' => 0, 'cancelled' => 0, 'overdue' => 0];
        foreach ($rows as $r) { $map[$r['outcome']] = (int) $r['cnt']; }
        $map['total'] = array_sum($map);
        return $map;
    }

    /** Daily activity totals for the last N days (real data). */
    public function dailyRollup(int $days = 14): array
    {
        $startDate = date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
        $sql = "SELECT DATE(activity_datetime) AS day, outcome, COUNT(*) AS cnt
                FROM (" . $this->activityUnion() . ") a
                WHERE activity_datetime >= ?
                GROUP BY day, outcome
                ORDER BY day DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $day = $r['day'];
            $out[$day]['total'] = ($out[$day]['total'] ?? 0) + (int) $r['cnt'];
            $out[$day][$r['outcome']] = ($out[$day][$r['outcome']] ?? 0) + (int) $r['cnt'];
        }
        // Ensure a continuous 14-day window even on empty days.
        for ($i = 0; $i < $days; $i++) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $out[$day] = $out[$day] ?? ['total' => 0, 'completed' => 0, 'pending' => 0, 'cancelled' => 0];
            $out[$day]['completed'] = $out[$day]['completed'] ?? 0;
            $out[$day]['pending'] = $out[$day]['pending'] ?? 0;
            $out[$day]['cancelled'] = $out[$day]['cancelled'] ?? 0;
        }
        return $out;
    }

    /** Monthly activity totals for the given year (real data). */
    public function monthlyRollup(int $year): array
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $monthExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', activity_datetime) AS INTEGER)"
            : "EXTRACT(MONTH FROM activity_datetime)";
        $sql = "SELECT $monthExpr AS month, outcome, COUNT(*) AS cnt
                FROM (" . $this->activityUnion() . ") a
                WHERE activity_datetime >= ? AND activity_datetime < ?
                GROUP BY month, outcome
                ORDER BY month ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$year . '-01-01', ($year + 1) . '-01-01']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $out[$key] = ['total' => 0, 'completed' => 0, 'pending' => 0, 'cancelled' => 0, 'label' => date('M', mktime(0, 0, 0, $m, 1))];
        }
        foreach ($rows as $r) {
            $m = str_pad((string) (int) $r['month'], 2, '0', STR_PAD_LEFT);
            if (! isset($out[$m])) continue;
            $out[$m]['total'] += (int) $r['cnt'];
            $out[$m][$r['outcome']] = ($out[$m][$r['outcome']] ?? 0) + (int) $r['cnt'];
        }
        return $out;
    }

    /**
     * Summary stats (counts + amount totals) for a filtered activity set.
     * Computed entirely in SQL for efficiency — no record-level hydration.
     */
    public function activitySummary(array $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN outcome = 'completed'  THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN outcome = 'pending'    THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN outcome = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN outcome = 'overdue'    THEN 1 ELSE 0 END) AS overdue,
                    COALESCE(SUM(amount), 0)  AS amount,
                    COALESCE(SUM(quantity), 0) AS quantity
                FROM (" . $this->activityUnion() . ") a
                WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'overdue'   => (int) ($row['overdue'] ?? 0),
            'amount'    => (float) ($row['amount'] ?? 0),
            'quantity'  => (int) ($row['quantity'] ?? 0),
        ];
    }

    /**
     * Daily breakdown within a date range. Returns one entry per day,
     * pre-filled with zero values so empty days still appear.
     *
     * @param array $f  Filter array — must contain 'from'/'date' and 'to' (or 'date').
     * @return array    Ordered by day, each row: day, label, total, completed, pending, cancelled, overdue, amount, quantity
     */
    public function dailyBreakdown(array $f): array
    {
        [$where, $params] = $this->buildWhere($f);

        $sql = "SELECT
                    DATE(activity_datetime) AS day,
                    outcome,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(amount), 0)   AS amount,
                    COALESCE(SUM(quantity), 0) AS quantity
                FROM (" . $this->activityUnion() . ") a
                WHERE " . implode(' AND ', $where) . "
                GROUP BY day, outcome
                ORDER BY day ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Pre-fill every day in the range so empty days show zero values.
        $out = [];
        $from = $f['from'] ?? ($f['date'] ?? null);
        $to   = $f['to'] ?? ($f['date'] ?? null);
        if ($from && $to) {
            $d = new \DateTime($from);
            $end = new \DateTime($to);
            while ($d <= $end) {
                $key = $d->format('Y-m-d');
                $out[$key] = $this->emptyDayRecord($key);
                $d->modify('+1 day');
            }
        }

        foreach ($rows as $r) {
            $d = $r['day'];
            $out[$d] ??= $this->emptyDayRecord($d);
            $out[$d]['total']     += (int) $r['cnt'];
            $out[$d][$r['outcome']] = ($out[$d][$r['outcome']] ?? 0) + (int) $r['cnt'];
            $out[$d]['amount']    += (float) $r['amount'];
            $out[$d]['quantity']  += (int) $r['quantity'];
        }
        ksort($out);
        return $out;
    }

    /**
     * Monthly breakdown for a full year. Returns 12 entries (Jan–Dec),
     * always pre-filled so months with no activity show zero.
     */
    public function monthlyBreakdown(int $year, array $f): array
    {
        [$where, $params] = $this->buildWhere($f);

        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $monthExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', activity_datetime) AS INTEGER)"
            : "EXTRACT(MONTH FROM activity_datetime)";

        $sql = "SELECT
                    $monthExpr AS month_num,
                    outcome,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(amount), 0)   AS amount,
                    COALESCE(SUM(quantity), 0) AS quantity
                FROM (" . $this->activityUnion() . ") a
                WHERE " . implode(' AND ', $where) . "
                GROUP BY month_num, outcome
                ORDER BY month_num ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $out[$key] = [
                'month'     => $key,
                'label'     => date('M', mktime(0, 0, 0, $m, 1)),
                'total'     => 0,
                'completed' => 0,
                'pending'   => 0,
                'cancelled' => 0,
                'overdue'   => 0,
                'amount'    => 0.0,
                'quantity'  => 0,
            ];
        }
        foreach ($rows as $r) {
            $m = str_pad((string) (int) $r['month_num'], 2, '0', STR_PAD_LEFT);
            if (! isset($out[$m])) continue;
            $out[$m]['total']     += (int) $r['cnt'];
            $out[$m][$r['outcome']] = ($out[$m][$r['outcome']] ?? 0) + (int) $r['cnt'];
            $out[$m]['amount']    += (float) $r['amount'];
            $out[$m]['quantity']  += (int) $r['quantity'];
        }
        return $out;
    }

    /**
     * Pre-filled empty day record (all outcome counts zeroed).
     */
    protected function emptyDayRecord(string $day): array
    {
        return [
            'day'       => $day,
            'label'     => date('M d', strtotime($day)),
            'total'     => 0,
            'completed' => 0,
            'pending'   => 0,
            'cancelled' => 0,
            'overdue'   => 0,
            'amount'    => 0.0,
            'quantity'  => 0,
        ];
    }

    /**
     * Percentage change between two values.
     * Returns null when the previous value is zero (division by zero).
     */
    public function percentageChange(float $current, float $previous): ?float
    {
        if ($previous == 0) return null;
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Daily trend data (total + amount per day) for chart rendering.
     * Returns labels and two data arrays for a line/bar chart.
     */
    public function chartDailyData(array $f): array
    {
        $breakdown = $this->dailyBreakdown($f);
        $labels = [];
        $totals = [];
        $amounts = [];
        $completed = [];
        $pending = [];
        $cancelled = [];
        $overdue = [];

        foreach ($breakdown as $d) {
            $labels[]    = $d['label'];
            $totals[]    = $d['total'];
            $amounts[]   = (float) $d['amount'];
            $completed[] = $d['completed'];
            $pending[]   = $d['pending'];
            $cancelled[] = $d['cancelled'];
            $overdue[]   = $d['overdue'];
        }

        return compact('labels', 'totals', 'amounts', 'completed', 'pending', 'cancelled', 'overdue');
    }

    /**
     * Monthly trend data for a full year. Returns labels and data arrays
     * for chart rendering (12 months always present).
     */
    public function chartMonthlyData(int $year, array $f): array
    {
        $breakdown = $this->monthlyBreakdown($year, $f);
        $labels = [];
        $totals = [];
        $amounts = [];
        $completed = [];
        $pending = [];
        $cancelled = [];
        $overdue = [];

        foreach ($breakdown as $m) {
            $labels[]    = $m['label'];
            $totals[]    = $m['total'];
            $amounts[]   = (float) $m['amount'];
            $completed[] = $m['completed'];
            $pending[]   = $m['pending'];
            $cancelled[] = $m['cancelled'];
            $overdue[]   = $m['overdue'];
        }

        return compact('labels', 'totals', 'amounts', 'completed', 'pending', 'cancelled', 'overdue');
    }

    /**
     * Chart data broken down by report_type (entity type) for a given filter set.
     * Useful for daily reports showing the mix of activities.
     */
    public function chartByReportType(array $f): array
    {
        [$where, $params] = $this->buildWhere($f);

        $sql = "SELECT
                    report_type,
                    outcome,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(amount), 0) AS amount
                FROM (" . $this->activityUnion() . ") a
                WHERE " . implode(' AND ', $where) . "
                GROUP BY report_type, outcome
                ORDER BY report_type ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $labels = [];
        $totals = [];
        $amounts = [];
        $completed = [];
        $pending = [];
        $cancelled = [];
        $overdue = [];

        $typeMap = $this->reportTypes();
        $aggregated = [];
        foreach ($rows as $r) {
            $rt = $r['report_type'];
            $aggregated[$rt]['label'] = $typeMap[$rt] ?? ucfirst($rt);
            $aggregated[$rt]['total']     = ($aggregated[$rt]['total'] ?? 0) + (int) $r['cnt'];
            $aggregated[$rt][$r['outcome']] = ($aggregated[$rt][$r['outcome']] ?? 0) + (int) $r['cnt'];
            $aggregated[$rt]['amount']    = ($aggregated[$rt]['amount'] ?? 0.0) + (float) $r['amount'];
        }
        ksort($aggregated);

        foreach ($aggregated as $rt => $data) {
            $labels[]    = $data['label'];
            $totals[]    = $data['total'];
            $amounts[]   = (float) $data['amount'];
            $completed[] = $data['completed'] ?? 0;
            $pending[]   = $data['pending'] ?? 0;
            $cancelled[] = $data['cancelled'] ?? 0;
            $overdue[]   = $data['overdue'] ?? 0;
        }

        return compact('labels', 'totals', 'amounts', 'completed', 'pending', 'cancelled', 'overdue');
    }

    /**
     * Performance summary metrics for a generated report.
     *
     * @param string $viewType  'daily', 'monthly', 'yearly', or 'custom'
     * @param array  $filters   Active filter set (with from/to or date)
     * @param int    $year      Selected year
     * @param int    $month     Selected month (1-12)
     * @param array  $summary   Pre-computed summary
     * @return array
     */
    public function performanceSummary(string $viewType, array $filters, int $year, int $month, array $summary): array
    {
        $periods = ['days' => 0, 'period_type' => 'day'];

        if ($viewType === 'monthly') {
            $days = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
            $periods = ['days' => $days, 'period_type' => 'day'];
        } elseif ($viewType === 'yearly') {
            $periods = ['days' => 365, 'period_type' => 'month', 'months' => 12];
        } elseif ($viewType === 'daily') {
            $periods = ['days' => 1, 'period_type' => 'day'];
        } elseif ($viewType === 'custom') {
            $from = $filters['from'] ?? '';
            $to   = $filters['to'] ?? '';
            $days = 1;
            if ($from && $to) {
                $d1 = new \DateTime($from);
                $d2 = new \DateTime($to);
                $days = (int) $d1->diff($d2)->days + 1;
            }
            $periods = ['days' => $days, 'period_type' => 'day'];
        }

        $total = $summary['total'] ?? 0;
        $days = $periods['days'];
        $divisor = $days > 0 ? $days : 1;

        return [
            'days_in_period'      => $days,
            'period_type'        => $periods['period_type'],
            'avg_per_day'        => $total > 0 ? round($total / $divisor, 2) : 0,
            'avg_amount_per_day'  => ($total > 0 && ($summary['amount'] ?? 0) > 0) ? round($summary['amount'] / $divisor, 2) : 0.0,
            'completion_rate'     => $total > 0 ? round(($summary['completed'] ?? 0) / $total * 100, 2) : 0,
            'overdue_rate'        => $total > 0 ? round(($summary['overdue'] ?? 0) / $total * 100, 2) : 0,
            'pending_rate'        => $total > 0 ? round(($summary['pending'] ?? 0) / $total * 100, 2) : 0,
            'total_amount'        => (float) ($summary['amount'] ?? 0),
        ];
    }

    /** Distinct list of users (active) for the person filter. */
    public function users(): array
    {
        $stmt = $this->pdo->query("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Distinct categories (departments/categories) drawn from real data. */
    public function categories(): array
    {
        $sql = "SELECT DISTINCT category FROM (" . $this->activityUnion() . ") a
                WHERE category IS NOT NULL AND category != 'N/A'
                ORDER BY category ASC";
        $rows = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    public function statuses(): array
    {
        return [
            'paid', 'pending', 'overdue', 'cancelled',
            'completed', 'approved', 'denied', 'processed', 'paid',
            'present', 'late', 'absent', 'active', 'inactive', 'suspended',
            'success', 'failed',
        ];
    }

    public function reportTypes(): array
    {
        return [
            'client' => 'Client',
            'employee' => 'Employee',
            'bill' => 'Bill',
            'payment' => 'Payment',
            'attendance' => 'Attendance',
            'inventory' => 'Stock Item',
            'inventory_request' => 'Stock Request',
            'payroll' => 'Payroll',
            'login' => 'User Account',
        ];
    }

    /** Persist a generated report definition to report_history. */
    public function logReport(array $data): int
    {
        $id = DB::table('report_history')->insertGetId([
            'report_name' => $data['report_name'],
            'report_type' => $data['report_type'],
            'date_from' => $data['date_from'] ?: null,
            'date_to' => $data['date_to'] ?: null,
            'filters' => json_encode($data['filters'] ?? []),
            'generated_by' => $data['generated_by'] ?? null,
            'record_count' => (int) ($data['record_count'] ?? 0),
            'status' => $data['status'] ?? 'generated',
            'generated_at' => now()->toDateTimeString(),
        ]);
        return (int) $id;
    }

    /** Fetch the report_history listing (most recent first). */
    public function reportHistory(): array
    {
        return DB::table('report_history as h')
            ->leftJoin('users as u', 'h.generated_by', '=', 'u.id')
            ->select('h.*', 'u.full_name as generated_by_name')
            ->orderByDesc('h.generated_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $row->filters = $row->filters ? json_decode($row->filters, true) : [];
                return $row;
            })
            ->toArray();
    }

    /** Delete a single report_history entry. */
    public function deleteReport(int $id): int
    {
        return DB::table('report_history')->where('id', $id)->delete();
    }
}
