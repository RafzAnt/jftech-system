<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * System Settings Module (G)
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$settingsCssVersion = filemtime(public_path('css/settings.css'));

$success = '';
$error = '';

// Get all settings
$stmt = $pdo->query("SELECT * FROM system_settings ORDER BY id");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row;
}

// Update settings
if ($request->isMethod('post')) {
    try {
        if ($request->has('update_settings')) {
            $submittedSettings = $request->input('settings', []);
            if (isset($submittedSettings['company_contact']) && ! preg_match('/^\d{11}$/', trim((string) $submittedSettings['company_contact']))) {
                throw new Exception('Business mobile number must contain exactly 11 digits.');
            }
            foreach ($submittedSettings as $key => $value) {
                if (str_starts_with((string) $key, 'payroll_') && (! is_numeric($value) || (float) $value < 0)) {
                    throw new Exception('Payroll deductions must be valid positive amounts.');
                }
            }
            foreach ($submittedSettings as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            $success = 'System settings updated successfully!';
        }

        // Refresh settings
        $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY id");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
    return get_defined_vars();
};
