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

$selectedClient = null;
$bills = [];
$payments = [];
$totalDue = 0;
$totalPaid = 0;

// Search and select client
if ($request->has('client_id') && $request->query('client_id')) {
    $stmt = $pdo->prepare("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.id = ?");
    $stmt->execute([$request->query('client_id')]);
    $selectedClient = $stmt->fetch();

    if ($selectedClient) {
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE client_id = ? AND status IN ('pending', 'overdue') ORDER BY billing_period_start DESC");
        $stmt->execute([$request->query('client_id')]);
        $bills = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT p.*, b.bill_number, b.amount as bill_amount, b.status as bill_status, u.full_name as received_by_name,
              (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance
             FROM payments p
             LEFT JOIN bills b ON p.bill_id = b.id
             LEFT JOIN users u ON p.received_by = u.id
             WHERE p.client_id = ?
             ORDER BY p.payment_date DESC");
        $stmt->execute([$request->query('client_id')]);
        $payments = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = b.id), 0)), 0) AS total FROM bills b WHERE b.client_id = ? AND b.status IN ('pending', 'overdue')");
        $stmt->execute([$request->query('client_id')]);
        $totalDue = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE client_id = ?");
        $stmt->execute([$request->query('client_id')]);
        $totalPaid = $stmt->fetch()['total'];
    }
}

// Search clients
$searchResults = [];
if ($request->has('search') && $request->query('search')) {
    $search = '%' . $request->query('search') . '%';
    $stmt = $pdo->prepare("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.full_name LIKE ? OR c.client_code LIKE ? ORDER BY c.full_name LIMIT 20");
    $stmt->execute([$search, $search]);
    $searchResults = $stmt->fetchAll();
}

// Get all clients for quick select
$stmt = $pdo->query("SELECT id, client_code, full_name, wifi_plan, status FROM clients WHERE status = 'active' ORDER BY full_name");
$allClients = $stmt->fetchAll();
    return get_defined_vars();
};
