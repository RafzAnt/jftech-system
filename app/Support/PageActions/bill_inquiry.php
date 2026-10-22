<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Client Records Module
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$billInquiryCssVersion = filemtime(public_path('css/bill_inquiry.css'));
$clientFilterCssVersion = filemtime(public_path('css/client_filter.css'));
$mainJsVersion = @filemtime(public_path('js/jftech-main.js')) ?: 1;
$paymentToken = session('client_record_payment_token') ?? (string) \Illuminate\Support\Str::uuid();
session(['client_record_payment_token' => $paymentToken]);
$paymentSuccess = '';
$paymentError = '';
// The branch admin records the payments of their branch's clients; the super admin only sees the bills and the payments.
$canRecordPayments = auth()->user()?->canRecordPayments() ?? false;

$selectedClient = null;
$bills = [];
$payments = [];
$planChangeHistory = [];
$totalDue = 0;
$totalPaid = 0;
$openBillsCount = 0;
$incomingBillsCount = 0;
$showArchived = $request->query('view') === 'archived';
$showOverdue = $request->boolean('overdue');
$clientActionSuccess = '';
$clientActionError = '';

// Branch, barangay, and search filters (same behavior as Browse Clients).
$branches = config('bohol_branches', []);
$branchBarangays = config('bohol_barangays', []);
$searchTerm = trim((string) $request->query('search', ''));
// A branch admin only works with their own branch: it is the only option and is always selected.
$scopeBranch = \App\Support\BranchScope::current();
if ($scopeBranch !== null) {
    $ownBranches = array_filter(array_keys($branches), static fn (string $name): bool => strcasecmp($name, $scopeBranch) === 0);
    $branches = array_intersect_key($branches, array_flip($ownBranches));
}
$selectedBranch = $scopeBranch !== null ? array_key_first($branches) ?? '' : trim((string) $request->query('branch', ''));
if (! array_key_exists($selectedBranch, $branches)) {
    $selectedBranch = '';
}
$requestedBarangays = $request->query('barangays', []);
$selectedBarangays = is_array($requestedBarangays) ? $requestedBarangays : [];
$validBarangaysForBranch = $selectedBranch ? ($branchBarangays[$selectedBranch] ?? []) : [];
$selectedBarangays = array_values(array_unique(array_filter(array_map('trim', $selectedBarangays), static function ($name) use ($validBarangaysForBranch) {
    return in_array($name, $validBarangaysForBranch, true);
})));
[$locationSql, $locationParams] = \App\Support\BranchScope::clients($scopeBranch, 'c');
if ($selectedBranch) {
    $locationSql .= ' AND LOWER(COALESCE(c.branch_name, \'\')) = LOWER(?)';
    $locationParams[] = $selectedBranch;
}
if ($selectedBarangays) {
    $placeholders = implode(', ', array_fill(0, count($selectedBarangays), '?'));
    $locationSql .= " AND LOWER(COALESCE(c.registered_barangay_name, b.barangay_name, '')) IN ($placeholders)";
    array_push($locationParams, ...array_map('strtolower', $selectedBarangays));
}
$searchSql = '';
$searchParams = [];
if ($searchTerm !== '') {
    $searchSql = " AND (
        LOWER(COALESCE(c.full_name, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.client_code, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.contact_number, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.branch_name, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.registered_barangay_name, b.barangay_name, '')) LIKE LOWER(?)
    )";
    for ($i = 0; $i < 5; $i++) {
        $searchParams[] = '%' . $searchTerm . '%';
    }
}
$archivedSql = $showArchived ? 'c.archived = 1' : '(c.archived IS NULL OR c.archived = 0)';
$overdueSql = $showOverdue
    ? " AND EXISTS (SELECT 1 FROM bills overdue_bills WHERE overdue_bills.client_id = c.id AND overdue_bills.status = 'overdue')"
    : '';

if ($request->isMethod('post') && $request->has('client_action') && $request->has('client_id')) {
    \App\Support\BranchScope::abortUnlessClient($request->input('client_id'));
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
    abort_unless($canRecordPayments, 403);
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

        if (! \App\Support\BranchScope::canAccessClient($data['client_id'])) {
            throw new \RuntimeException('That client does not belong to your branch.');
        }

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
                throw new \RuntimeException('Payment amount cannot exceed the remaining bill balance of ₱' . number_format(max(0, $remaining), 2) . '.');
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
    \App\Support\BranchScope::abortUnlessClient($request->query('client_id'));
    $stmt = $pdo->prepare("SELECT c.*, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.id = ?");
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
             ORDER BY p.payment_date DESC, p.id DESC");
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

// Search clients. Branch, barangay, and search terms can be used together.
$searchResults = [];
if ($searchTerm !== '') {
    $stmt = $pdo->prepare('SELECT c.*, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE ' . $archivedSql . $locationSql . $searchSql . $overdueSql . ' ORDER BY c.id DESC LIMIT 20');
    $stmt->execute([...$locationParams, ...$searchParams]);
    $searchResults = $stmt->fetchAll();
}

// Get clients for quick select, optionally limited to a branch and barangays.
$stmt = $pdo->prepare('SELECT c.id, c.full_name, c.contact_number, c.email, c.wifi_plan, c.status, c.branch_name, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE ' . $archivedSql . $locationSql . $overdueSql . ' ORDER BY c.id DESC');
$stmt->execute($locationParams);
$allClients = $stmt->fetchAll();
[$archivedScope, $archivedScopeParams] = \App\Support\BranchScope::clients($scopeBranch, 'c');
$stmt = $pdo->prepare('SELECT COUNT(*) FROM clients c WHERE c.archived = 1' . $archivedScope);
$stmt->execute($archivedScopeParams);
$archivedCount = (int) $stmt->fetchColumn();
    return get_defined_vars();
};
