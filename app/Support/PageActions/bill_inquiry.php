<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Client Records Module
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$billInquiryCssVersion = filemtime(public_path('css/bill_inquiry.css'));
$mainJsVersion = @filemtime(public_path('js/jftech-main.js')) ?: 1;
$paymentToken = session('client_record_payment_token') ?? (string) \Illuminate\Support\Str::uuid();
session(['client_record_payment_token' => $paymentToken]);
$paymentSuccess = '';
$paymentError = '';

$selectedClient = null;
$bills = [];
$payments = [];
$planChangeHistory = [];
$totalDue = 0;
$totalPaid = 0;
$openBillsCount = 0;
$incomingBillsCount = 0;
$selectedBarangay = trim((string) $request->query('barangay', ''));
$showArchived = $request->query('view') === 'archived';
$showOverdue = $request->boolean('overdue');
$clientActionSuccess = '';
$clientActionError = '';

// Barangays for the client-records filter.
$stmt = $pdo->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name');
$barangays = $stmt->fetchAll();

if ($request->isMethod('post') && $request->has('client_action') && $request->has('client_id')) {
    try {
        $clientId = (int) $request->input('client_id');
        if ($request->input('client_action') === 'archive') {
            $pdo->prepare("UPDATE clients SET archived = 1, status = 'inactive' WHERE id = ?")->execute([$clientId]);
            $clientActionSuccess = 'Client archived.';
        } elseif ($request->input('client_action') === 'restore') {
            $pdo->prepare("UPDATE clients SET archived = 0, status = 'active' WHERE id = ?")->execute([$clientId]);
            $clientActionSuccess = 'Client restored as active.';
        } elseif ($request->input('client_action') === 'set_status') {
            $status = (string) $request->input('status');
            if (! in_array($status, ['active', 'inactive', 'suspended'], true)) {
                throw new \RuntimeException('Please select a valid client status.');
            }
            $pdo->prepare('UPDATE clients SET status = ? WHERE id = ? AND (archived IS NULL OR archived = 0)')->execute([$status, $clientId]);
            $clientActionSuccess = 'Client status updated to ' . ucfirst($status) . '.';
        }
    } catch (\Throwable $e) {
        $clientActionError = 'Unable to update client: ' . $e->getMessage();
    }
}

// Record a payment from the Client Records payment modal.
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($pdo, $data): void {
            $billStmt = $pdo->prepare("SELECT id, amount FROM bills WHERE id = ? AND client_id = ? AND status IN ('pending', 'overdue') FOR UPDATE");
            $billStmt->execute([$data['bill_id'], $data['client_id']]);
            $bill = $billStmt->fetch();
            if (! $bill) {
                throw new \RuntimeException('Please select a valid pending bill before recording payment.');
            }

            $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = ?');
            $paidStmt->execute([$data['bill_id']]);
            $remaining = (float) $bill['amount'] - (float) $paidStmt->fetchColumn();
            if ((float) $data['amount'] > $remaining) {
                throw new \RuntimeException('Payment amount cannot exceed the remaining bill balance of PHP ' . number_format(max(0, $remaining), 2) . '.');
            }

            $stmt = $pdo->prepare('INSERT INTO payments (client_id, bill_id, amount, payment_date, payment_method, reference_number, notes, received_by, submission_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['client_id'], $data['bill_id'], $data['amount'], $data['payment_date'], $data['payment_method'],
                trim((string) ($data['reference_number'] ?? '')), trim((string) ($data['notes'] ?? '')), auth()->id(), $data['submission_token'],
            ]);

            if (($remaining - (float) $data['amount']) <= 0.005) {
                $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?")->execute([$data['bill_id']]);
            }
        });

        app(\App\Support\BillingService::class)->syncClientBilling((int) $data['client_id']);
        $paymentSuccess = 'Payment recorded successfully.';
        session()->forget('client_record_payment_token');
        $paymentToken = (string) \Illuminate\Support\Str::uuid();
        session(['client_record_payment_token' => $paymentToken]);
    } catch (\Throwable $e) {
        $paymentError = 'Unable to record payment: ' . $e->getMessage();
    }
}

// Search and select client
if ($request->has('client_id') && $request->query('client_id')) {
    $stmt = $pdo->prepare("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.id = ?");
    $stmt->execute([$request->query('client_id')]);
    $selectedClient = $stmt->fetch();

    if ($selectedClient) {
        // Keep this client current whenever their record is opened. The service
        // is idempotent: it creates missing monthly bills once, marks overdue
        // unpaid bills, and keeps the next future period visible as Incoming.
        app(\App\Support\BillingService::class)->syncClientBilling((int) $selectedClient['id']);

        $stmt = $pdo->prepare("SELECT b.*, GREATEST(COALESCE(b.amount, 0) - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.bill_id = b.id), 0), 0) AS balance FROM bills b WHERE b.client_id = ? AND b.status IN ('pending', 'overdue') ORDER BY b.billing_period_start ASC");
        $stmt->execute([$request->query('client_id')]);
        $bills = $stmt->fetchAll();
        $today = today()->toDateString();
        $openBillsCount = count(array_filter($bills, static fn (array $bill): bool =>
            $bill['billing_period_start'] <= $today && (float) $bill['balance'] > 0
        ));
        $incomingBillsCount = count(array_filter($bills, static fn (array $bill): bool =>
            $bill['billing_period_start'] > $today
        ));

        $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.amount as bill_amount, b.status as bill_status, u.full_name as received_by_name,
              (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
             FROM payments p
             LEFT JOIN bills b ON p.bill_id = b.id
             LEFT JOIN users u ON p.received_by = u.id
             WHERE p.client_id = ?
             ORDER BY p.payment_date DESC");
        $stmt->execute([$request->query('client_id')]);
        $payments = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT h.*, u.full_name AS changed_by_name FROM client_plan_change_histories h LEFT JOIN users u ON u.id = h.changed_by WHERE h.client_id = ? ORDER BY h.created_at DESC");
        $stmt->execute([$request->query('client_id')]);
        $planChangeHistory = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = b.id), 0), 0)), 0) AS total FROM bills b WHERE b.client_id = ? AND b.status IN ('pending', 'overdue') AND b.billing_period_start <= ?");
        $stmt->execute([$request->query('client_id'), $today]);
        $totalDue = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE client_id = ?");
        $stmt->execute([$request->query('client_id')]);
        $totalPaid = $stmt->fetch()['total'];
    }
}

// Search clients. Barangay and search terms can be used together.
$searchResults = [];
if ($request->filled('search')) {
    $search = '%' . trim((string) $request->query('search')) . '%';
    $query = 'SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.full_name LIKE ?';
    $params = [$search];

    $query .= $showArchived ? ' AND c.archived = 1' : ' AND (c.archived IS NULL OR c.archived = 0)';

    if ($selectedBarangay !== '') {
        $query .= ' AND c.barangay_id = ?';
        $params[] = $selectedBarangay;
    }

    if ($showOverdue) {
        $query .= " AND EXISTS (SELECT 1 FROM bills overdue_bills WHERE overdue_bills.client_id = c.id AND overdue_bills.status = 'overdue')";
    }

    $query .= ' ORDER BY c.full_name LIMIT 20';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll();
}

// Get clients for quick select, optionally limited to one barangay.
$quickSelectQuery = 'SELECT id, full_name, contact_number, email, wifi_plan, status, barangay_id FROM clients WHERE ' . ($showArchived ? 'archived = 1' : '(archived IS NULL OR archived = 0)');
$quickSelectParams = [];
if ($selectedBarangay !== '') {
    $quickSelectQuery .= ' AND barangay_id = ?';
    $quickSelectParams[] = $selectedBarangay;
}
if ($showOverdue) {
    $quickSelectQuery .= " AND EXISTS (SELECT 1 FROM bills overdue_bills WHERE overdue_bills.client_id = clients.id AND overdue_bills.status = 'overdue')";
}
$quickSelectQuery .= ' ORDER BY full_name';
$stmt = $pdo->prepare($quickSelectQuery);
$stmt->execute($quickSelectParams);
$allClients = $stmt->fetchAll();
$archivedCount = (int) $pdo->query('SELECT COUNT(*) FROM clients WHERE archived = 1')->fetchColumn();
    return get_defined_vars();
};
