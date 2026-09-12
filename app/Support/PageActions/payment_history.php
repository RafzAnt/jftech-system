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

// Get clients for dropdown
$stmt = $pdo->query("SELECT id, client_code, full_name FROM clients WHERE status = 'active' ORDER BY full_name");
$clients = $stmt->fetchAll();

// Get all payments with client info
    $stmt = $pdo->query("SELECT p.*, c.full_name as client_name, c.client_code, u.full_name as received_by_name, b.bill_number, b.amount as bill_amount,
              (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
             FROM payments p
             JOIN clients c ON p.client_id = c.id
             LEFT JOIN users u ON p.received_by = u.id
             LEFT JOIN bills b ON p.bill_id = b.id
             ORDER BY p.payment_date DESC, p.id DESC");
    $payments = $stmt->fetchAll();

// Calculate totals
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments");
$totalPayments = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date = CURDATE()");
$todayPayments = $stmt->fetch()['total'];

// Record new payment
if ($request->isMethod('post') && $request->has('record_payment')) {
    try {
        $wasFullyPaid = false;
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'bill_id' => ['required', 'integer', 'exists:bills,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,gcash,bank_transfer,other'],
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($pdo, $data, $clientId, $billId, $amount, $paymentDate, $paymentMethod, $referenceNumber, $notes, $receivedBy, &$wasFullyPaid) {
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
            if ((float) $amount === $remaining) {
                $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?")->execute([$billId]);
                $wasFullyPaid = true;
            }
        });

        // If the bill was fully paid, generate the next bill(s) for this client immediately.
        if ($wasFullyPaid) {
            try {
                // Create the next billing period immediately so the client has
                // a new pending invoice for the upcoming month right after payment.
                app(\App\Support\BillingService::class)->generateNextBillForClient($clientId);
            } catch (Exception $e) {
                // Swallow errors from generation so payment recording doesn't fail for the user.
            }
        }

        $success = 'Payment recorded successfully!';
        session()->forget('payment_submission_token');
        $paymentToken = (string) \Illuminate\Support\Str::uuid();
        session(['payment_submission_token' => $paymentToken]);

        // Refresh payments
        $stmt = $pdo->query("SELECT p.*, c.full_name as client_name, c.client_code, u.full_name as received_by_name, b.bill_number, b.amount as bill_amount,
              (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
             FROM payments p
             JOIN clients c ON p.client_id = c.id
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
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE client_id = ? AND status IN ('pending', 'overdue') ORDER BY due_date");
    $stmt->execute([$request->query('client_id')]);
    $pendingBills = $stmt->fetchAll();
}
    return get_defined_vars();
};
