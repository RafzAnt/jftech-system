<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Admin Dashboard
 */

$themeCssVersion = @filemtime(public_path('css/jftech-theme.css')) ?: 1;
$dashboardCssVersion = @filemtime(public_path('css/dashboard.css')) ?: 1;
$mainJsVersion = @filemtime(public_path('js/jftech-main.js')) ?: 1;
$today = now()->toDateString();
$revenueStart = now()->startOfYear()->toDateString();
$revenueEnd = now()->startOfYear()->addYear()->toDateString();

// A branch admin always sees their own branch. The super admin sees every branch
// (a ?branch= query still narrows it, but the page no longer offers a selector).
$branch = \App\Support\BranchScope::resolve($request->query('branch'));
$branchScopeLabel = $branch === null ? 'All branches' : $branch;
[$clientScope, $clientScopeParams] = \App\Support\BranchScope::clients($branch, 'c');
[$billScope, $billScopeParams] = \App\Support\BranchScope::viaClient($branch, 'b.client_id');
[$paymentScope, $paymentScopeParams] = \App\Support\BranchScope::viaClient($branch, 'p.client_id');
[$employeeScope, $employeeScopeParams] = \App\Support\BranchScope::employees($branch, 'e');
[$attendanceScope, $attendanceScopeParams] = \App\Support\BranchScope::attendance($branch, 'a');
[$userScope, $userScopeParams] = \App\Support\BranchScope::users($branch, 'u');
$scoped = static function (string $sql, array $params = []) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt;
};

// Get statistics
$stats = [];

// Match Clients Browse: archived clients are not part of the active client list.
$stmt = $scoped("SELECT COUNT(*) as total FROM clients c WHERE (c.archived IS NULL OR c.archived = 0)" . $clientScope, $clientScopeParams);
$stats['total_clients'] = $stmt->fetch()['total'];

// Active Clients
$stmt = $scoped("SELECT COUNT(*) as total FROM clients c WHERE c.status = 'active' AND (c.archived IS NULL OR c.archived = 0)" . $clientScope, $clientScopeParams);
$stats['active_clients'] = $stmt->fetch()['total'];

// Total Employees
$stmt = $scoped("SELECT COUNT(*) as total FROM employees e WHERE e.status = 'active'" . $employeeScope, $employeeScopeParams);
$stats['total_employees'] = $stmt->fetch()['total'];

// Today's attendance means an employee actually checked in. Some records can
// be created with a status (for example "late") before a QR/time-in exists;
// those must not appear as present on the dashboard. Older installations
// without an afternoon column remain supported as well.
$timeInConditions = ['morning_time_in IS NOT NULL'];
if (\Illuminate\Support\Facades\Schema::hasColumn('attendance', 'afternoon_time_in')) {
    $timeInConditions[] = 'afternoon_time_in IS NOT NULL';
}
$stmt = $scoped("SELECT COUNT(DISTINCT a.employee_id) AS total
    FROM attendance a
    WHERE a.attendance_date = ?
      AND (" . implode(' OR ', $timeInConditions) . ")" . $attendanceScope, [$today, ...$attendanceScopeParams]);
$stats['today_attendance'] = $stmt->fetch()['total'];

// Revenue comes from paid bills, not raw payment rows. This prevents duplicate
// or unlinked payment entries from inflating the dashboard figures.
$stmt = $scoped("SELECT COALESCE(SUM(b.amount), 0) as total FROM bills b WHERE b.status = 'paid'" . $billScope, $billScopeParams);
$stats['total_revenue'] = $stmt->fetch()['total'];

// Count affected active clients once, even when a client has multiple bills.
// This makes the dashboard number an actionable client count rather than a
// potentially inflated count of historic billing records.
$stmt = $scoped("SELECT COUNT(DISTINCT b.client_id) AS total
    FROM bills b
    JOIN clients c ON c.id = b.client_id
    WHERE b.status = 'pending'
      AND c.status = 'active'
      AND (c.archived IS NULL OR c.archived = 0)" . $clientScope, $clientScopeParams);
$stats['clients_with_pending_bills'] = $stmt->fetch()['total'];

// Keep overdue as an actual bill count because each bill needs follow-up.
$stmt = $scoped("SELECT COUNT(*) as total FROM bills b WHERE b.status = 'overdue'" . $billScope, $billScopeParams);
$stats['overdue_bills'] = $stmt->fetch()['total'];

// Keep this in the same order as the Payment History page. A payment may be
// recorded after its payment date, so created_at is not the transaction date.
$stmt = $scoped("SELECT p.*, c.full_name as client_name FROM payments p JOIN clients c ON p.client_id = c.id WHERE 1 = 1" . $clientScope . " ORDER BY p.payment_date DESC, p.id DESC LIMIT 5", $clientScopeParams);
$recentPayments = $stmt->fetchAll();

// Clients with open balances removed per request. Dashboard will no
// longer compute or return `clientsWithOpenBalance` to the view.

// Recent Login History
// Login activity is only listed for the accounts that belong to the branch on screen.
$stmt = $branch === null
    ? $pdo->query("SELECT * FROM login_history ORDER BY login_time DESC LIMIT 5")
    : $scoped("SELECT lh.* FROM login_history lh JOIN users u ON u.id = lh.user_id WHERE 1 = 1" . $userScope . " ORDER BY lh.login_time DESC LIMIT 5", $userScopeParams);
$recentLogins = $stmt->fetchAll();

// Load only the displayed billing months. Date bounds keep this query portable
// between MySQL and SQLite, while PHP combines all bills from the same month.
$stmt = $scoped("
    SELECT b.billing_period_start, b.amount
    FROM bills b
    WHERE b.status = 'paid' AND b.billing_period_start >= ? AND b.billing_period_start < ?" . $billScope . "
    ORDER BY b.billing_period_start ASC
", [$revenueStart, $revenueEnd, ...$billScopeParams]);
$monthlyRevenueRaw = $stmt->fetchAll();

$monthlyRevenueMap = [];
foreach ($monthlyRevenueRaw as $row) {
    $monthKey = date('Y-m', strtotime($row['billing_period_start']));
    $monthlyRevenueMap[$monthKey] = ($monthlyRevenueMap[$monthKey] ?? 0) + (float) $row['amount'];
}

$monthlyRevenue = [];
$revenueMonth = now()->startOfYear();
for ($index = 0; $index < 12; $index++) {
    $monthlyRevenue[] = [
        'label' => $revenueMonth->format('M'),
        'total' => $monthlyRevenueMap[$revenueMonth->format('Y-m')] ?? 0,
    ];
    $revenueMonth->addMonth();
}

// Revenue is based on paid bills, not on pending invoices or duplicate payments.
$currentMonthRevenue = $monthlyRevenueMap[now()->format('Y-m')] ?? 0;
$previousMonthRevenue = $monthlyRevenueMap[now()->subMonth()->format('Y-m')] ?? 0;
$monthlyRevenueChange = $previousMonthRevenue > 0
    ? (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100
    : null;
$revenueMonthLabel = date('F Y');

// Keep the dashboard focused on client account health instead of locations.
$stmt = $scoped("SELECT c.status, COUNT(*) as client_count
    FROM clients c
    WHERE (c.archived IS NULL OR c.archived = 0)" . $clientScope . "
    GROUP BY c.status
    ORDER BY client_count DESC", $clientScopeParams);
$clientStatusStats = $stmt->fetchAll();

// Surface the accounts that need action most prominently on the dashboard.
// The attendance count remains in the employee summary card above, avoiding a
// duplicate operational table in the main overview.
$stmt = $scoped("
    SELECT b.bill_number, b.amount, b.due_date, b.status, c.full_name AS client_name
    FROM bills b
    JOIN clients c ON c.id = b.client_id
    WHERE b.status IN ('pending', 'overdue')
      AND (c.archived IS NULL OR c.archived = 0)" . $clientScope . "
    ORDER BY CASE WHEN b.status = 'overdue' THEN 0 ELSE 1 END, b.due_date ASC, b.id ASC
    LIMIT 5
", $clientScopeParams);
$billingPriorities = $stmt->fetchAll();

// Total users count
$stmt = $scoped("SELECT COUNT(*) as total FROM users u WHERE u.status = 'active'" . $userScope, $userScopeParams);
$stats['total_users'] = $stmt->fetch()['total'];
    return get_defined_vars();
};
