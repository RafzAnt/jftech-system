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
            foreach ($request->input('settings') as $key => $value) {
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
