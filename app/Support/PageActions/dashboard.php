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

// Get statistics
$stats = [];

// Total Clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients");
$stats['total_clients'] = $stmt->fetch()['total'];

// Active Clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE status = 'active'");
$stats['active_clients'] = $stmt->fetch()['total'];

// Total Employees
$stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
$stats['total_employees'] = $stmt->fetch()['total'];

// Today's Attendance
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) as total FROM attendance WHERE attendance_date = ?");
$stmt->execute([$today]);
$stats['today_attendance'] = $stmt->fetch()['total'];

// Revenue comes from paid bills, not raw payment rows. This prevents duplicate
// or unlinked payment entries from inflating the dashboard figures.
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM bills WHERE status = 'paid'");
$stats['total_revenue'] = $stmt->fetch()['total'];

// Pending Bills
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bills WHERE status = 'pending'");
$stats['pending_bills'] = $stmt->fetch()['total'];

// Overdue Bills
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bills WHERE status = 'overdue'");
$stats['overdue_bills'] = $stmt->fetch()['total'];

// Recent Payments
$stmt = $pdo->query("SELECT p.*, c.full_name as client_name FROM payments p JOIN clients c ON p.client_id = c.id ORDER BY p.created_at DESC LIMIT 5");
$recentPayments = $stmt->fetchAll();

// Clients with open balances removed per request. Dashboard will no
// longer compute or return `clientsWithOpenBalance` to the view.

// Recent Login History
$stmt = $pdo->query("SELECT * FROM login_history ORDER BY login_time DESC LIMIT 5");
$recentLogins = $stmt->fetchAll();

// Load only the displayed billing months. Date bounds keep this query portable
// between MySQL and SQLite, while PHP combines all bills from the same month.
$stmt = $pdo->prepare("
    SELECT billing_period_start, amount
    FROM bills
    WHERE status = 'paid' AND billing_period_start >= ? AND billing_period_start < ?
    ORDER BY billing_period_start ASC
");
$stmt->execute([$revenueStart, $revenueEnd]);
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

// Use the marker color from the latest registered client in each barangay.
// The dashboard displays one donut slice per barangay, while the map retains
// each individual client's marker color.
$stmt = $pdo->query("SELECT b.barangay_name, COUNT(c.id) as client_count,
    COALESCE(
        (SELECT NULLIF(c2.marker_color, '')
         FROM clients c2
         WHERE c2.barangay_id = b.id
         ORDER BY c2.created_at DESC, c2.id DESC
         LIMIT 1),
        '#00BFFF'
    ) as marker_color
    FROM barangays b
    LEFT JOIN clients c ON b.id = c.barangay_id
    GROUP BY b.id, b.barangay_name
    ORDER BY client_count DESC
    LIMIT 10");
$barangayStats = $stmt->fetchAll();

// Get today's attendance details
$stmt = $pdo->prepare("SELECT a.*, e.full_name as employee_name FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.attendance_date = ? ORDER BY a.morning_time_in DESC");
$stmt->execute([$today]);
$todayAttendanceDetails = $stmt->fetchAll();

// Total users count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$stats['total_users'] = $stmt->fetch()['total'];
    return get_defined_vars();
};
