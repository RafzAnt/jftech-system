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

$showArchived = $request->query('view') === 'archived';

// Get all barangays for dropdown
$stmt = $pdo->query("SELECT * FROM barangays ORDER BY barangay_name");
$barangays = $stmt->fetchAll();

// The registration modal uses the same active plans as the standalone form.
$stmt = $pdo->query("SELECT * FROM internet_plans WHERE status = 'active' AND archived = 0 ORDER BY monthly_price");
$plans = $stmt->fetchAll();
$branches = config('bohol_branches', []);
$branchBarangays = config('bohol_barangays', []);
// A branch admin only works with their own branch: it is the only option and is always selected (the register form needs it).
$scopeBranch = \App\Support\BranchScope::current();
if ($scopeBranch !== null) {
    $ownBranches = array_filter(array_keys($branches), static fn (string $name): bool => strcasecmp($name, $scopeBranch) === 0);
    $branches = array_intersect_key($branches, array_flip($ownBranches));
}
$selectedBranch = $scopeBranch !== null ? array_key_first($branches) ?? '' : '';

// The one search box: a branch, a barangay or a client, several words narrowing each other (see LocationFilter).
$location = \App\Support\LocationFilter::fromRequest($request);
$searchTerm = $location['searchTerm'];
[$filterSql, $filterParams] = \App\Support\BranchScope::clients($scopeBranch, 'c');
[$searchSql, $searchParams] = \App\Support\LocationFilter::clientConditions($location, 'c', 'b');
$filterSql .= $searchSql;
array_push($filterParams, ...$searchParams);

// What was typed in the Register New Client form, so a refused form is shown again with the same values.
$registerValues = [];

if ($request->isMethod('post') && $request->input('action') === 'register') {
    abort_unless($canRegisterClients, 403);
    $registerClientOpen = true;
    $registerValues = $request->only([
        'first_name', 'middle_name', 'last_name', 'contact_number', 'email', 'internet_plan_id', 'installation_date', 'branch_name',
        'registered_barangay_name', 'exact_address', 'payment_mode', 'ip_address', 'facebook_account', 'coordinates', 'nap_port_id',
    ]);
    try {
        $firstName = trim((string) $request->input('first_name'));
        $middleName = trim((string) $request->input('middle_name'));
        $lastName = trim((string) $request->input('last_name'));
        $branchInput = trim((string) $request->input('branch_name'));
        $branchName = null;
        foreach (array_keys($branches) as $branchOption) {
            if (strcasecmp($branchInput, $branchOption) === 0) {
                $branchName = $branchOption;
                break;
            }
        }

        if ($firstName === '' || $lastName === '' || ! $branchName) {
            throw new \RuntimeException('Please complete all required client registration fields.');
        }

        // The checks and the saving are shared with the confirmation of an installation report from the employee app.
        $client = \App\Support\ClientRegistration::clean([
            'full_name' => implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($part) => $part !== '')),
            'contact_number' => $request->input('contact_number'), 'email' => $request->input('email'),
            'barangay_name' => $request->input('registered_barangay_name'), 'exact_address' => $request->input('exact_address'),
            'installation_date' => $request->input('installation_date'), 'internet_plan_id' => $request->input('internet_plan_id'),
            'payment_mode' => $request->input('payment_mode'), 'ip_address' => $request->input('ip_address'),
            'facebook_account' => $request->input('facebook_account'), 'coordinates' => $request->input('coordinates'),
            'nap_port_id' => $request->input('nap_port_id'),
        ], $branchName, true, false);
        \App\Support\ClientRegistration::create($client);

        $success = 'Client registered successfully!';
        $registerClientOpen = false;
        $registerValues = [];
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
    $query = "SELECT c.*, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name,
              (SELECT nb.name FROM nap_ports np JOIN nap_boxes nb ON nb.id = np.nap_box_id WHERE np.client_id = c.id LIMIT 1) AS nap_box_name,
              (SELECT np.port_number FROM nap_ports np WHERE np.client_id = c.id LIMIT 1) AS nap_port_number
              FROM clients c
              LEFT JOIN barangays b ON c.barangay_id = b.id 
              WHERE (c.archived IS NULL OR c.archived = 0) $filterSql ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($filterParams);
    $clients = $stmt->fetchAll();

    // Attach billing & payment history per client
    $billStmt = $pdo->prepare("SELECT bill_number, billing_period_start, billing_period_end, amount, status FROM bills WHERE client_id = ? ORDER BY billing_period_start DESC LIMIT 10");
    $payStmt = $pdo->prepare("SELECT p.payment_date, b.bill_number, p.amount, p.payment_method FROM payments p LEFT JOIN bills b ON p.bill_id = b.id WHERE p.client_id = ? ORDER BY p.payment_date DESC, p.id DESC LIMIT 10");

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
