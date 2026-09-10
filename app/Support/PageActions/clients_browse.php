<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

    // Handle archive / restore / delete actions
    if ($request->isMethod('post') && $request->has('action') && $request->has('client_id')) {
        $action = $request->input('action');
        $cid = (int) $request->input('client_id');
        try {
            if ($action === 'archive') {
                $pdo->prepare("UPDATE clients SET archived = 1, status = 'inactive' WHERE id = ?")->execute([$cid]);
                $success = 'Client archived.';
            } elseif ($action === 'restore') {
                $pdo->prepare("UPDATE clients SET archived = 0, status = 'active' WHERE id = ?")->execute([$cid]);
                $success = 'Client restored.';
            } elseif ($action === 'delete') {
                \Illuminate\Support\Facades\DB::transaction(function () use ($pdo, $cid) {
                    $pdo->prepare("DELETE FROM payments WHERE client_id = ?")->execute([$cid]);
                    $pdo->prepare("DELETE FROM bills WHERE client_id = ?")->execute([$cid]);
                    $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$cid]);
                });
                $success = 'Client permanently deleted.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

/**
 * JFTECH WiFi Billing & Management System
 * Clients Module - Browse Clients (A-2)
 * Features: Barangay dropdown, Map with colored markers, Print Receipt
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$clientsBrowseCssVersion = filemtime(public_path('css/clients_browse.css'));
$mainJsVersion = @filemtime(public_path('js/jftech-main.js')) ?: 1;

$selectedBarangay = $request->query('barangay') ?? '';
$searchTerm = $request->query('search') ?? '';
$showArchived = $request->query('view') === 'archived';

// Get all barangays for dropdown
$stmt = $pdo->query("SELECT * FROM barangays ORDER BY barangay_name");
$barangays = $stmt->fetchAll();

if ($showArchived) {
    $stmt = $pdo->query("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.archived = 1 ORDER BY c.updated_at DESC");
    $clients = $stmt->fetchAll();
    $archivedCount = count($clients);
} else {
    // Build client query
    // Include installation date and compute the next pending/overdue bill's due date
    $query = "SELECT c.*, b.barangay_name, b.latitude as barangay_lat, b.longitude as barangay_lng,
              (SELECT due_date FROM bills WHERE client_id = c.id AND status IN ('pending','overdue') ORDER BY due_date ASC LIMIT 1) as next_due_date,
              (SELECT status FROM bills WHERE client_id = c.id AND status IN ('pending','overdue') ORDER BY due_date ASC LIMIT 1) as next_due_status
              FROM clients c 
              LEFT JOIN barangays b ON c.barangay_id = b.id 
              WHERE 1=1";
    $params = [];

    if ($selectedBarangay) {
        $query .= " AND c.barangay_id = ?";
        $params[] = $selectedBarangay;
    }

    if ($searchTerm) {
        $query .= " AND (c.full_name LIKE ? OR c.client_code LIKE ? OR c.contact_number LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }

    $query .= " AND (c.archived IS NULL OR c.archived = 0) ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    // Attach billing & payment history per client
    $billStmt = $pdo->prepare("SELECT bill_number, billing_period_start, billing_period_end, amount, status FROM bills WHERE client_id = ? ORDER BY billing_period_start DESC LIMIT 10");
    $payStmt = $pdo->prepare("SELECT p.payment_date, b.bill_number, p.amount, p.payment_method FROM payments p LEFT JOIN bills b ON p.bill_id = b.id WHERE p.client_id = ? ORDER BY p.payment_date DESC LIMIT 10");

    foreach ($clients as &$client) {
        $billStmt->execute([$client['id']]);
        $client['billing_history'] = $billStmt->fetchAll();

        $payStmt->execute([$client['id']]);
        $client['payment_history'] = $payStmt->fetchAll();
    }
    unset($client);

    $archivedCount = (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE archived = 1")->fetchColumn();
}

// Get barangay details if selected
$selectedBarangayData = null;
if ($selectedBarangay) {
    foreach ($barangays as $b) {
        if ($b['id'] == $selectedBarangay) {
            $selectedBarangayData = $b;
            break;
        }
    }
}
    return get_defined_vars();
};
