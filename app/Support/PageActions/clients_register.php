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
        $firstName = trim((string) $request->input('first_name'));
        $middleName = trim((string) $request->input('middle_name'));
        $lastName = trim((string) $request->input('last_name'));
        $fullName = implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($part) => $part !== ''));
        $contactNumber = trim($request->input('contact_number'));
        $email = trim($request->input('email'));
        $barangayId = $request->input('barangay_id');
        $exactAddress = trim($request->input('exact_address'));
        $installationDate = trim((string) $request->input('installation_date'));

        if ($firstName === '' || $lastName === '' || $contactNumber === '' || $email === '' || ! $barangayId || $exactAddress === '' || $installationDate === '') {
            throw new \RuntimeException('Please complete all required client registration fields.');
        }
        if (! preg_match('/^\d{11}$/', $contactNumber)) {
            throw new \RuntimeException('Contact number must contain exactly 11 digits.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Please enter a valid email address.');
        }
        $date = \DateTime::createFromFormat('Y-m-d', $installationDate);
        if (! $date || $date->format('Y-m-d') !== $installationDate) {
            throw new \RuntimeException('Please enter a valid installation date.');
        }
        $barangayStmt = $pdo->prepare('SELECT id FROM barangays WHERE id = ?');
        $barangayStmt->execute([(int) $barangayId]);
        if (! $barangayStmt->fetch()) {
            throw new \RuntimeException('Please select a valid barangay.');
        }
        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        if ($latitude < 9.60 || $latitude > 9.90 || $longitude < 123.85 || $longitude > 124.10) {
            throw new \RuntimeException('Please place the client pin within the Balilihan, Bohol service area.');
        }
        $markerColor = $request->input('marker_color');
        $markerColors = ['#00BFFF', '#00E676', '#FF1744', '#FFAB00', '#E040FB', '#2979FF', '#76FF03', '#FF4081'];
        if (! in_array($markerColor, $markerColors, true)) {
            throw new \RuntimeException('Please select a valid marker color.');
        }
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
