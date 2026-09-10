<?php
return function (\Illuminate\Http\Request $request): array {
    app(\App\Http\Controllers\PriceManagementController::class)->applyDueScheduledPrices();
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Clients Module - Register New Client (A-1)
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$formsCssVersion = filemtime(public_path('css/forms.css'));

$success = '';
$error = '';

// Get barangays for dropdown
$stmt = $pdo->query("SELECT * FROM barangays ORDER BY barangay_name");
$barangays = $stmt->fetchAll();

// Only active admin-managed internet plans are available for new installations.
$stmt = $pdo->query("SELECT * FROM internet_plans WHERE status = 'active' AND archived = 0 ORDER BY monthly_price");
$plans = $stmt->fetchAll();

if ($request->isMethod('post')) {
    try {
        $clientCode = 'JFT-' . date('Y') . '-' . strtoupper(uniqid());
        $fullName = trim($request->input('full_name'));
        $contactNumber = trim($request->input('contact_number'));
        $email = trim($request->input('email'));
        $barangayId = $request->input('barangay_id');
        $exactAddress = trim($request->input('exact_address'));
        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        if ($latitude < 9.60 || $latitude > 9.90 || $longitude < 123.85 || $longitude > 124.10) {
            throw new \RuntimeException('Please place the client pin within the Balilihan, Bohol service area.');
        }
        $markerColor = $request->input('marker_color') ?: '#00BFFF';
        $planId = (int) $request->input('internet_plan_id');
        $planStmt = $pdo->prepare("SELECT * FROM internet_plans WHERE id = ? AND status = 'active' AND archived = 0");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (! $plan) {
            throw new \RuntimeException('Please select an active internet plan.');
        }
        // Snapshot the values on the customer record; never trust browser-supplied prices.
        $wifiPlan = $plan['plan_name'];
        $monthlyRate = $plan['monthly_price'];
        $installationFee = $plan['installation_fee'];
        $installationDate = $request->input('installation_date') ?: date('Y-m-d');

        $stmt = $pdo->prepare("INSERT INTO clients (client_code, full_name, contact_number, email, barangay_id, exact_address, latitude, longitude, marker_color, wifi_plan, internet_plan_id, monthly_rate, installation_fee, installation_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$clientCode, $fullName, $contactNumber, $email, $barangayId, $exactAddress, $latitude, $longitude, $markerColor, $wifiPlan, $planId, $monthlyRate, $installationFee, $installationDate]);

        // Create initial bill
        $clientId = $pdo->lastInsertId();
        $billNumber = 'BILL-' . date('Ymd') . '-' . strtoupper(uniqid());
        $billingStart = date('Y-m-d');
        $billingEnd = date('Y-m-d', strtotime($billingStart . ' +30 days'));
        // Due date policy: due = billing_period_start + 30 days
        $dueDate = date('Y-m-d', strtotime($billingStart . ' +30 days'));

        $stmt = $pdo->prepare("INSERT INTO bills (client_id, bill_number, billing_period_start, billing_period_end, amount, due_date, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$clientId, $billNumber, $billingStart, $billingEnd, $monthlyRate, $dueDate]);

        // Immediately generate any missing recurring bills for this client so
        // the UI reflects the upcoming due date (next month) right after
        // registration instead of waiting for the nightly scheduler.
        try {
            app(\App\Support\BillingService::class)->generateDueBills();
        } catch (Exception $e) {
            // Don't fail registration if bill generation has a transient error.
        }

        $success = 'Client registered successfully! Client Code: ' . $clientCode;
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
    return get_defined_vars();
};
