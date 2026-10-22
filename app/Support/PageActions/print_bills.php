<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Printable Billing Report
 */

$selectedStatus = $request->query('status') ?? '';
$selectedClient = $request->query('client_id') ?? '';
$selectedStart = $request->query('start') ?? '';
$selectedEnd = $request->query('end') ?? '';

$query = "SELECT b.*, c.full_name, c.client_code, c.wifi_plan 
          FROM bills b 
          JOIN clients c ON b.client_id = c.id 
          WHERE TRUE";
// A branch admin only prints bills of their own branch's clients.
[$branchScope, $params] = \App\Support\BranchScope::clients(\App\Support\BranchScope::current(), 'c');
$query .= $branchScope;

if ($selectedStatus) {
    $query .= " AND b.status = ?";
    $params[] = $selectedStatus;
}

if ($selectedClient) {
    $query .= " AND b.client_id = ?";
    $params[] = $selectedClient;
}

if ($selectedStart) {
    $query .= " AND b.billing_period_start >= ?";
    $params[] = $selectedStart;
}

if ($selectedEnd) {
    $query .= " AND b.billing_period_end <= ?";
    $params[] = $selectedEnd;
}

$query .= " ORDER BY b.billing_period_start DESC, b.due_date DESC, b.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bills = $stmt->fetchAll();

$totalBills = count($bills);
$totalAmount = array_sum(array_map(function($b) { return (float) $b['amount']; }, $bills));
$totalPending = array_sum(array_map(function($b) { return $b['status'] === 'pending' ? (float) $b['amount'] : 0; }, $bills));
$totalPaid = array_sum(array_map(function($b) { return $b['status'] === 'paid' ? (float) $b['amount'] : 0; }, $bills));
$totalOverdue = array_sum(array_map(function($b) { return $b['status'] === 'overdue' ? (float) $b['amount'] : 0; }, $bills));

$periodLabel = '';
if ($selectedStart && $selectedEnd) {
    $periodLabel = date('M d, Y', strtotime($selectedStart)) . ' to ' . date('M d, Y', strtotime($selectedEnd));
} elseif ($selectedStart) {
    $periodLabel = 'From ' . date('M d, Y', strtotime($selectedStart));
} elseif ($selectedEnd) {
    $periodLabel = 'Until ' . date('M d, Y', strtotime($selectedEnd));
} else {
    $periodLabel = 'All Dates';
}

$statusLabel = '';
if ($selectedStatus) {
    $statusLabel = ucfirst($selectedStatus) . ' Bills';
}
    return get_defined_vars();
};
