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

// The settings are shared by every branch, so a branch admin can read them but only the super admin changes them.
$canEditSettings = (bool) auth()->user()?->isSuperAdmin();

// Update settings
if ($request->isMethod('post')) {
    try {
        if (! $canEditSettings) {
            throw new Exception('Only the super admin can change the system settings.');
        }
        if ($request->has('update_settings')) {
            $submittedSettings = $request->input('settings', []);
            if (isset($submittedSettings['company_contact']) && ! preg_match('/^\d{11}$/', trim((string) $submittedSettings['company_contact']))) {
                throw new Exception('Business mobile number must contain exactly 11 digits.');
            }
            // The working rules of the payroll, with the range each may take and the name it is saved under.
            $payrollRules = [
                'payroll_days_per_month' => [1, 31, 'Working days in a month'],
                'payroll_weeks_per_month' => [1, 5, 'Payrolls in a month (contributions are divided by this)'],
                'payroll_hours_per_day' => [1, 24, 'Working hours in a day'],
                'payroll_overtime_rate' => [1, 3, 'Overtime pay multiplier'],
            ];
            // The last scan of the day: the afternoon QR code works until then, and the time after the end of the afternoon is overtime.
            $ensureSettings = ['qr_overtime_end' => 'Last scan of the day (overtime ends)'];
            foreach (array_keys($ensureSettings) as $timeKey) {
                if (isset($submittedSettings[$timeKey]) && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $submittedSettings[$timeKey])) {
                    throw new Exception('Enter the last scan of the day as a valid time.');
                }
            }
            if (isset($submittedSettings['qr_afternoon_end'], $submittedSettings['qr_overtime_end']) && strcmp(substr($submittedSettings['qr_overtime_end'], 0, 5), substr($submittedSettings['qr_afternoon_end'], 0, 5)) <= 0) {
                throw new Exception('The last scan of the day must be after the afternoon QR end time.');
            }
            foreach ($submittedSettings as $key => $value) {
                if (str_starts_with((string) $key, 'payroll_') && (! is_numeric($value) || (float) $value < 0)) {
                    throw new Exception('The payroll settings must be valid positive numbers.');
                }
                if (isset($payrollRules[$key]) && ((float) $value < $payrollRules[$key][0] || (float) $value > $payrollRules[$key][1])) {
                    throw new Exception($payrollRules[$key][2] . ' must be between ' . $payrollRules[$key][0] . ' and ' . $payrollRules[$key][1] . '.');
                }
            }
            foreach ($submittedSettings as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
                $ensureLabel = isset($payrollRules[$key]) ? $payrollRules[$key][2] : ($ensureSettings[$key] ?? null);
                if ($ensureLabel !== null) {
                    // A database that has not got this rule yet gets it now.
                    $exists = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
                    $exists->execute([$key]);
                    if ((int) $exists->fetchColumn() === 0) {
                        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_label) VALUES (?, ?, ?)")->execute([$key, $value, $ensureLabel]);
                    }
                }
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

// The contribution tables worked out for some daily rates, so the numbers can be checked against the payslips.
$payrollRuleSettings = \App\Support\PayrollCalculator::settings([
    'days_per_month' => $settings['payroll_days_per_month']['setting_value'] ?? null,
    'weeks_per_month' => $settings['payroll_weeks_per_month']['setting_value'] ?? null,
    'hours_per_day' => $settings['payroll_hours_per_day']['setting_value'] ?? null,
    'overtime_rate' => $settings['payroll_overtime_rate']['setting_value'] ?? null,
]);
[$rateScope, $rateScopeParams] = \App\Support\BranchScope::employees(\App\Support\BranchScope::current(), 'e');
$rateStmt = $pdo->prepare("SELECT DISTINCT e.daily_rate FROM employees e WHERE e.status = 'active' AND e.daily_rate > 0" . $rateScope);
$rateStmt->execute($rateScopeParams);
$sampleRates = array_unique(array_merge([500, 550, 580, 600, 630], array_map('floatval', $rateStmt->fetchAll(\PDO::FETCH_COLUMN))));
sort($sampleRates);
$payrollSamples = [];
foreach (array_slice($sampleRates, 0, 14) as $sampleRate) {
    $payrollSamples[] = ['rate' => $sampleRate] + \App\Support\PayrollCalculator::contributions((float) $sampleRate, $payrollRuleSettings);
}

    return get_defined_vars();
};
