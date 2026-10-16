<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Printable Payment Transactions Report (PDF via browser print-to-PDF)
 */

$from = $request->query('from') ?? '';
$to = $request->query('to') ?? '';

$where = [];
// A branch admin only prints payments of their own branch's clients.
[$branchScope, $params] = \App\Support\BranchScope::clients(\App\Support\BranchScope::current(), 'c');
if ($branchScope !== '') {
    $where[] = ltrim(substr($branchScope, strlen(' AND ')));
}
if ($from) {
    $where[] = 'p.payment_date >= ?';
    $params[] = $from;
}
if ($to) {
    $where[] = 'p.payment_date <= ?';
    $params[] = $to;
}

    $sql = "
        SELECT p.*, c.full_name AS client_name, c.client_code,
              b.bill_number, b.amount AS bill_amount,
              (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
        FROM payments p
        JOIN clients c ON p.client_id = c.id
        LEFT JOIN bills b ON p.bill_id = b.id
    ";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.payment_date DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$total = (float) array_sum(array_map(function ($p) { return (float) $p['amount']; }, $payments));
$count = count($payments);
$rangeLabel = ($from || $to) ? (($from ?: 'Start') . ' to ' . ($to ?: 'Today')) : 'All Dates';
    return get_defined_vars();
};
