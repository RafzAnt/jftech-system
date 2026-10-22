<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Printable Client Masterlist Report
 */

$selectedBarangay = $request->query('barangay') ?? '';
$searchTerm = $request->query('search') ?? '';

$query = "SELECT c.*, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name
          FROM clients c 
          LEFT JOIN barangays b ON c.barangay_id = b.id 
          WHERE TRUE";
// A branch admin only prints their own branch's clients.
[$branchScope, $params] = \App\Support\BranchScope::clients(\App\Support\BranchScope::current(), 'c');
$query .= $branchScope;

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

$query .= " ORDER BY c.created_at DESC, c.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$totalClients = count($clients);
$activeClients = count(array_filter($clients, function($c) { return $c['status'] === 'active'; }));
$inactiveClients = count(array_filter($clients, function($c) { return $c['status'] === 'inactive'; }));
$suspendedClients = count(array_filter($clients, function($c) { return $c['status'] === 'suspended'; }));

$barangayLabel = '';
if ($selectedBarangay) {
    $stmt = $pdo->prepare("SELECT barangay_name FROM barangays WHERE id = ?");
    $stmt->execute([$selectedBarangay]);
    $barangayLabel = $stmt->fetchColumn() ?: '';
}
    return get_defined_vars();
};
