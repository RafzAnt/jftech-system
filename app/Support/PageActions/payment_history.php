<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Payment History Module (C)
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$paymentHistoryCssVersion = filemtime(public_path('css/payment_history.css'));

$selectedBarangay = trim((string) $request->query('barangay', ''));
$selectedBarangay = ctype_digit($selectedBarangay) && (int) $selectedBarangay > 0 ? $selectedBarangay : '';
$searchTerm = trim((string) $request->query('search', ''));

$stmt = $pdo->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name');
$barangays = $stmt->fetchAll();

// Get payments with optional barangay and client-name filters.
$paymentQuery = "SELECT p.*, c.client_code, c.full_name as client_name, COALESCE(c.registered_barangay_name, br.barangay_name) AS barangay_name, c.branch_name, u.full_name as received_by_name, b.bill_number, b.amount as bill_amount,
                 (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
                 FROM payments p
                 JOIN clients c ON p.client_id = c.id
                 LEFT JOIN barangays br ON c.barangay_id = br.id
                 LEFT JOIN users u ON p.received_by = u.id
                 LEFT JOIN bills b ON p.bill_id = b.id
                 WHERE TRUE";
// A branch admin only sees payments of their own branch's clients.
[$branchScope, $paymentParams] = \App\Support\BranchScope::clients(\App\Support\BranchScope::current(), 'c');
$paymentQuery .= $branchScope;

if ($selectedBarangay !== '') {
    $paymentQuery .= ' AND c.barangay_id = ?';
    $paymentParams[] = $selectedBarangay;
}
if ($searchTerm !== '') {
    $paymentQuery .= ' AND c.full_name LIKE ?';
    $paymentParams[] = '%' . $searchTerm . '%';
}

$paymentQuery .= ' ORDER BY p.payment_date DESC, p.id DESC';
$stmt = $pdo->prepare($paymentQuery);
$stmt->execute($paymentParams);
$payments = $stmt->fetchAll();

// Keep the summary cards in sync with the selected barangay/search filter.
$totalPayments = array_sum(array_map(static fn (array $payment): float => (float) $payment['amount'], $payments));
$todayPayments = array_sum(array_map(
    static fn (array $payment): float => $payment['payment_date'] === now()->toDateString() ? (float) $payment['amount'] : 0,
    $payments
));

    return get_defined_vars();
};
