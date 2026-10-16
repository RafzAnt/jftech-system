<?php
return function (\Illuminate\Http\Request $request): array {
    app(\App\Http\Controllers\PriceManagementController::class)->applyDueScheduledPrices();
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    $success = '';
    $error = '';
    // Only a branch admin registers clients; the super admin just looks at them.
    $canRegisterClients = auth()->user()?->canRegisterClients() ?? false;
    $registerClientOpen = $canRegisterClients && ($request->session()->pull('open_register_modal', false)
        || $request->query('register') === '1');

    // Handle archive / restore / delete actions
    if ($request->isMethod('post') && $request->has('action') && $request->has('client_id')) {
        $action = $request->input('action');
        $cid = (int) $request->input('client_id');
        \App\Support\BranchScope::abortUnlessClient($cid);
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

$searchTerm = $request->query('search') ?? '';
$showArchived = $request->query('view') === 'archived';

// Get all barangays for dropdown
$stmt = $pdo->query("SELECT * FROM barangays ORDER BY barangay_name");
$barangays = $stmt->fetchAll();

// The registration modal uses the same active plans as the standalone form.
$stmt = $pdo->query("SELECT * FROM internet_plans WHERE status = 'active' AND archived = 0 ORDER BY monthly_price");
$plans = $stmt->fetchAll();
$branches = config('bohol_branches', []);
$branchBarangays = config('bohol_barangays', []);
// A branch admin only works with their own branch: it is the only option and is always selected.
$scopeBranch = \App\Support\BranchScope::current();
if ($scopeBranch !== null) {
    $ownBranches = array_filter(array_keys($branches), static fn (string $name): bool => strcasecmp($name, $scopeBranch) === 0);
    $branches = array_intersect_key($branches, array_flip($ownBranches));
}
$selectedBranch = $scopeBranch !== null ? array_key_first($branches) ?? '' : trim((string) $request->query('branch', ''));
if (!array_key_exists($selectedBranch, $branches)) {
    $selectedBranch = '';
}
$requestedBarangays = $request->query('barangays', []);
$selectedBarangays = is_array($requestedBarangays) ? $requestedBarangays : [];
$validBarangaysForBranch = $selectedBranch ? ($branchBarangays[$selectedBranch] ?? []) : [];
$selectedBarangays = array_values(array_unique(array_filter(array_map('trim', $selectedBarangays), static function ($name) use ($validBarangaysForBranch) {
    return in_array($name, $validBarangaysForBranch, true);
})));
[$filterSql, $filterParams] = \App\Support\BranchScope::clients($scopeBranch, 'c');
if ($selectedBranch) {
    $filterSql .= ' AND LOWER(COALESCE(c.branch_name, \'\')) = LOWER(?)';
    $filterParams[] = $selectedBranch;
}
if ($selectedBarangays) {
    $placeholders = implode(', ', array_fill(0, count($selectedBarangays), '?'));
    $filterSql .= " AND LOWER(COALESCE(c.registered_barangay_name, b.barangay_name, '')) IN ($placeholders)";
    array_push($filterParams, ...array_map('strtolower', $selectedBarangays));
}
if ($searchTerm !== '') {
    $filterSql .= " AND (
        LOWER(COALESCE(c.full_name, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.client_code, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.contact_number, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.branch_name, '')) LIKE LOWER(?)
        OR LOWER(COALESCE(c.registered_barangay_name, '')) LIKE LOWER(?)
    )";
    for ($i = 0; $i < 5; $i++) {
        $filterParams[] = '%' . $searchTerm . '%';
    }
}

if ($request->isMethod('post') && $request->input('action') === 'register') {
    abort_unless($canRegisterClients, 403);
    $registerClientOpen = true;
    try {
        $firstName = trim((string) $request->input('first_name'));
        $middleName = trim((string) $request->input('middle_name'));
        $lastName = trim((string) $request->input('last_name'));
        $contactNumber = trim((string) $request->input('contact_number'));
        $email = trim((string) $request->input('email'));
        $branchInput = trim((string) $request->input('branch_name'));
        $branchName = null;
        foreach (array_keys($branches) as $branchOption) {
            if (strcasecmp($branchInput, $branchOption) === 0) {
                $branchName = $branchOption;
                break;
            }
        }
        $barangayName = trim((string) $request->input('registered_barangay_name'));
        $exactAddress = trim((string) $request->input('exact_address'));
        $installationDate = trim((string) $request->input('installation_date'));
        $planId = (int) $request->input('internet_plan_id');

        if ($firstName === '' || $lastName === '' || $contactNumber === '' || $email === '' || ! $branchName || $barangayName === '' || $exactAddress === '' || $installationDate === '') {
            throw new \RuntimeException('Please complete all required client registration fields.');
        }
        if (! preg_match('/^\d{11}$/', $contactNumber)) {
            throw new \RuntimeException('Mobile number must contain exactly 11 digits.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Please enter a valid email address.');
        }
        $date = \DateTime::createFromFormat('Y-m-d', $installationDate);
        if (! $date || $date->format('Y-m-d') !== $installationDate) {
            throw new \RuntimeException('Please enter a valid installation date.');
        }

        $planStmt = $pdo->prepare("SELECT * FROM internet_plans WHERE id = ? AND status = 'active' AND archived = 0");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (! $plan) {
            throw new \RuntimeException('Please select an active internet plan.');
        }

        $fullName = implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($part) => $part !== ''));
        $clientCode = 'JFT-' . date('Y') . '-' . strtoupper(uniqid());
        $barangayId = null;
        if ($branchName === 'Balilihan') {
            $barangayStmt = $pdo->prepare('SELECT id FROM barangays WHERE barangay_name = ? LIMIT 1');
            $barangayStmt->execute([$barangayName]);
            $barangayId = $barangayStmt->fetchColumn() ?: null;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($pdo, $clientCode, $fullName, $contactNumber, $email, $branchName, $barangayName, $barangayId, $exactAddress, $plan, $installationDate): void {
            $pdo->prepare("INSERT INTO clients (client_code, full_name, contact_number, email, branch_name, registered_barangay_name, barangay_id, exact_address, wifi_plan, internet_plan_id, monthly_rate, installation_fee, installation_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')")
                ->execute([$clientCode, $fullName, $contactNumber, $email, $branchName, $barangayName, $barangayId, $exactAddress, $plan['plan_name'], $plan['id'], $plan['monthly_price'], $plan['installation_fee'], $installationDate]);
            $clientId = $pdo->lastInsertId();
            $billingStart = date('Y-m-d');
            $pdo->prepare("INSERT INTO bills (client_id, bill_number, billing_period_start, billing_period_end, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
                ->execute([$clientId, 'BILL-' . date('Ymd') . '-' . strtoupper(uniqid()), $billingStart, date('Y-m-d', strtotime($billingStart . ' +30 days')), $plan['monthly_price'], date('Y-m-d', strtotime($billingStart . ' +30 days'))]);
        });

        $success = 'Client registered successfully!';
        $registerClientOpen = false;
    } catch (\Throwable $exception) {
        $error = 'Error: ' . $exception->getMessage();
    }
}

if ($showArchived) {
    $stmt = $pdo->prepare("SELECT c.*, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.archived = 1 $filterSql ORDER BY c.updated_at DESC");
    $stmt->execute($filterParams);
    $clients = $stmt->fetchAll();
    $archivedCount = count($clients);
} else {
    // Build client query.
    $query = "SELECT c.*, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name
              FROM clients c
              LEFT JOIN barangays b ON c.barangay_id = b.id 
              WHERE (c.archived IS NULL OR c.archived = 0) $filterSql ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($filterParams);
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

    [$archivedScope, $archivedScopeParams] = \App\Support\BranchScope::clients($scopeBranch, 'c');
    $archivedCountStmt = $pdo->prepare("SELECT COUNT(*) FROM clients c WHERE c.archived = 1" . $archivedScope);
    $archivedCountStmt->execute($archivedScopeParams);
    $archivedCount = (int) $archivedCountStmt->fetchColumn();
}

    return get_defined_vars();
};
