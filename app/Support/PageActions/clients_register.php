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
    abort_unless(auth()->user()?->canRegisterClients(), 403);
    // Clients are registered from the Clients page (Register New Client), which also takes the Google coordinates, the mode of
    // payment and the NAP port. This old form no longer saves anything.
    $error = 'Please register clients from the Clients page (Register New Client).';
}
    return get_defined_vars();
};
