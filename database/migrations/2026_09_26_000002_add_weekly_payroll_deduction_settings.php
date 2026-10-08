<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $settings = [
            ['payroll_500_sss', '125.00', 'PHP 500 daily rate — weekly SSS'],
            ['payroll_500_philhealth', '75.00', 'PHP 500 daily rate — weekly PhilHealth'],
            ['payroll_500_pagibig', '50.00', 'PHP 500 daily rate — weekly Pag-IBIG'],
            ['payroll_580_sss', '175.00', 'PHP 580 daily rate — weekly SSS'],
            ['payroll_580_philhealth', '87.00', 'PHP 580 daily rate — weekly PhilHealth'],
            ['payroll_580_pagibig', '50.00', 'PHP 580 daily rate — weekly Pag-IBIG'],
            ['payroll_600_sss', '181.25', 'PHP 600 daily rate — weekly SSS'],
            ['payroll_600_philhealth', '90.00', 'PHP 600 daily rate — weekly PhilHealth'],
            ['payroll_600_pagibig', '50.00', 'PHP 600 daily rate — weekly Pag-IBIG'],
        ];

        foreach ($settings as [$key, $value, $label]) {
            DB::table('system_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value, 'setting_label' => $label]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('setting_key', [
            'payroll_500_sss', 'payroll_500_philhealth', 'payroll_500_pagibig',
            'payroll_580_sss', 'payroll_580_philhealth', 'payroll_580_pagibig',
            'payroll_600_sss', 'payroll_600_philhealth', 'payroll_600_pagibig',
        ])->delete();
    }
};
