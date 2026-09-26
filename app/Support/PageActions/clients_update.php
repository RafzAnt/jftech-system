<?php
return function (\Illuminate\Http\Request $request): array {
    app(\App\Http\Controllers\PriceManagementController::class)->applyDueScheduledPrices();
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Clients Module - Update Client (A-3)
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$formsCssVersion = filemtime(public_path('css/forms.css'));

$success = '';
$error = '';
$client = null;
$searchResults = [];

// Get barangays for dropdown
$stmt = $pdo->query("SELECT * FROM barangays ORDER BY barangay_name");
$barangays = $stmt->fetchAll();

// Show active plans plus the client's current plan (even when inactive) so old
// installations remain editable without forcing a plan change.
$requestedClientId = (int) ($request->query('id') ?: $request->input('client_id', 0));
$stmt = $pdo->prepare("SELECT * FROM internet_plans WHERE (status = 'active' AND archived = 0) OR id = (SELECT internet_plan_id FROM clients WHERE id = ?) ORDER BY monthly_price");
$stmt->execute([$requestedClientId]);
$plans = $stmt->fetchAll();

// Search clients
if ($request->has('search') && $request->query('search')) {
    $search = '%' . $request->query('search') . '%';
    $stmt = $pdo->prepare("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.full_name LIKE ? OR c.client_code LIKE ? OR c.contact_number LIKE ? ORDER BY c.full_name");
    $stmt->execute([$search, $search, $search]);
    $searchResults = $stmt->fetchAll();
}

// Get client by ID
if ($request->has('id') && $request->query('id')) {
    $stmt = $pdo->prepare("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.id = ?");
    $stmt->execute([$request->query('id')]);
    $client = $stmt->fetch();
}

// Update client
if ($request->isMethod('post') && $request->has('update_client')) {
    try {
        $clientId = $request->input('client_id');
        $firstName = trim((string) $request->input('first_name'));
        $middleName = trim((string) $request->input('middle_name'));
        $lastName = trim((string) $request->input('last_name'));
        $fullName = implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($part) => $part !== ''));
        $contactNumber = trim($request->input('contact_number'));
        $email = trim($request->input('email'));
        $barangayId = $request->input('barangay_id');
        $exactAddress = trim($request->input('exact_address'));
        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        if ($firstName === '' || $lastName === '') {
            throw new \RuntimeException('First name and last name are required. Middle name is optional.');
        }
        if (! preg_match('/^\d{11}$/', $contactNumber)) {
            throw new \RuntimeException('Contact number must contain exactly 11 digits.');
        }
        if ($latitude < 9.60 || $latitude > 9.90 || $longitude < 123.85 || $longitude > 124.10) {
            throw new \RuntimeException('Please place the client pin within the Balilihan, Bohol service area.');
        }
        $markerColor = $request->input('marker_color');
        // Load the saved client first. A normal profile update must preserve the
        // original plan-price snapshot even if the plan's current price changed.
        $currentClientStmt = $pdo->prepare("SELECT internet_plan_id, wifi_plan, monthly_rate, installation_fee FROM clients WHERE id = ?");
        $currentClientStmt->execute([$clientId]);
        $currentClient = $currentClientStmt->fetch();
        if (! $currentClient) {
            throw new \RuntimeException('Client record was not found.');
        }

        $planId = (int) $request->input('internet_plan_id');
        $planStmt = $pdo->prepare("SELECT * FROM internet_plans WHERE id = ?");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (! $plan || ((($plan['status'] !== 'active') || (int) $plan['archived'] === 1) && (int) $currentClient['internet_plan_id'] !== $planId)) {
            throw new \RuntimeException('Please select an active internet plan for a plan change.');
        }
        $isPlanChange = (int) $currentClient['internet_plan_id'] !== $planId;
        // Only an intentional plan change receives the current plan price.
        // Keeping the same plan preserves the price charged at installation.
        $wifiPlan = $isPlanChange ? $plan['plan_name'] : $currentClient['wifi_plan'];
        $monthlyRate = $isPlanChange ? $plan['monthly_price'] : $currentClient['monthly_rate'];
        $installationFee = $isPlanChange ? $plan['installation_fee'] : $currentClient['installation_fee'];
        $status = $request->input('status');

        $stmt = $pdo->prepare("UPDATE clients SET full_name = ?, contact_number = ?, email = ?, barangay_id = ?, exact_address = ?, latitude = ?, longitude = ?, marker_color = ?, wifi_plan = ?, internet_plan_id = ?, monthly_rate = ?, installation_fee = ?, status = ? WHERE id = ?");
        $stmt->execute([$fullName, $contactNumber, $email, $barangayId, $exactAddress, $latitude, $longitude, $markerColor, $wifiPlan, $planId, $monthlyRate, $installationFee, $status, $clientId]);

        $success = 'Client updated successfully!';

        // Refresh client data
        $stmt = $pdo->prepare("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
    return get_defined_vars();
};
