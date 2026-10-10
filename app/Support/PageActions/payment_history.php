<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    $paymentToken = session('payment_submission_token') ?? (string) \Illuminate\Support\Str::uuid();
    session(['payment_submission_token' => $paymentToken]);

/**
 * JFTECH WiFi Billing & Management System
 * Payment History Module (C)
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$paymentHistoryCssVersion = filemtime(public_path('css/payment_history.css'));

$success = '';
$error = '';
$selectedBarangay = trim((string) $request->query('barangay', ''));
$selectedBarangay = ctype_digit($selectedBarangay) && (int) $selectedBarangay > 0 ? $selectedBarangay : '';
$searchTerm = trim((string) $request->query('search', ''));

$stmt = $pdo->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name');
$barangays = $stmt->fetchAll();

// Get clients for dropdown
$stmt = $pdo->query("SELECT id, client_code, full_name FROM clients WHERE status = 'active' ORDER BY full_name");
$clients = $stmt->fetchAll();

// Get payments with optional barangay and client-name filters.
$paymentQuery = "SELECT p.*, c.full_name as client_name, br.barangay_name, u.full_name as received_by_name, b.bill_number, b.amount as bill_amount,
                 (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
                 FROM payments p
                 JOIN clients c ON p.client_id = c.id
                 LEFT JOIN barangays br ON c.barangay_id = br.id
                 LEFT JOIN users u ON p.received_by = u.id
                 LEFT JOIN bills b ON p.bill_id = b.id
                 WHERE TRUE";
$paymentParams = [];

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

// Record new payment
if ($request->isMethod('post') && $request->has('record_payment')) {
    try {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'bill_id' => ['required', 'integer', 'exists:bills,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'submission_token' => ['required', 'uuid'],
        ]);
        $clientId = $data['client_id'];
        $billId = $data['bill_id'];
        $amount = $data['amount'];
        $paymentDate = $data['payment_date'];
        $paymentMethod = $data['payment_method'];
        $referenceNumber = trim((string) ($data['reference_number'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $receivedBy = auth()->id();

        \Illuminate\Support\Facades\DB::transaction(function () use ($pdo, $data, $clientId, $billId, $amount, $paymentDate, $paymentMethod, $referenceNumber, $notes, $receivedBy) {
            $billStmt = $pdo->prepare("SELECT id, amount FROM bills WHERE id = ? AND client_id = ? AND status IN ('pending', 'overdue') FOR UPDATE");
            $billStmt->execute([$billId, $clientId]);
            $bill = $billStmt->fetch();
            if (! $bill) throw new \RuntimeException('Please select a valid pending bill before recording payment.');

            $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = ?");
            $paidStmt->execute([$billId]);
            $remaining = (float) $bill['amount'] - (float) $paidStmt->fetchColumn();
            if ((float) $amount > $remaining) throw new \RuntimeException('Payment amount cannot exceed the remaining bill balance of PHP ' . number_format(max(0, $remaining), 2) . '.');

            $stmt = $pdo->prepare("INSERT INTO payments (client_id, bill_id, amount, payment_date, payment_method, reference_number, notes, received_by, submission_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$clientId, $billId, $amount, $paymentDate, $paymentMethod, $referenceNumber, $notes, $receivedBy, $data['submission_token']]);
            // Mark the bill paid once its remaining balance is cleared. Using a
            // small tolerance avoids decimal floating-point rounding leaving a
            // fully paid bill stuck in Pending status.
            if (($remaining - (float) $amount) <= 0.005) {
                $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?")->execute([$billId]);
            }
        });

        try {
            // Refresh the client's complete billing timeline after payment:
            // remaining current bills, overdue status, and exactly one future
            // Incoming bill. The service prevents duplicates on repeat calls.
            app(\App\Support\BillingService::class)->syncClientBilling($clientId);
        } catch (Exception $e) {
            // Payment recording must remain successful even if next-bill generation fails.
        }

        $success = 'Payment recorded successfully!';
        session()->forget('payment_submission_token');
        $paymentToken = (string) \Illuminate\Support\Str::uuid();
        session(['payment_submission_token' => $paymentToken]);

        // Refresh payments
        $stmt = $pdo->query("SELECT p.*, c.full_name as client_name, c.client_code, br.barangay_name, u.full_name as received_by_name, b.bill_number, b.amount as bill_amount,
              (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
             FROM payments p
             JOIN clients c ON p.client_id = c.id
             LEFT JOIN barangays br ON c.barangay_id = br.id
             LEFT JOIN users u ON p.received_by = u.id
             LEFT JOIN bills b ON p.bill_id = b.id
             ORDER BY p.payment_date DESC, p.id DESC");
        $payments = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get pending bills for the selected client (for dropdown)
$pendingBills = [];
if ($request->has('client_id') && $request->query('client_id')) {
    $stmt = $pdo->prepare("SELECT b.*, GREATEST(COALESCE(b.amount, 0) - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.bill_id = b.id), 0), 0) AS balance FROM bills b WHERE b.client_id = ? AND b.status IN ('pending', 'overdue') ORDER BY b.due_date");
    $stmt->execute([$request->query('client_id')]);
    $pendingBills = $stmt->fetchAll();
}
    return get_defined_vars();
};
