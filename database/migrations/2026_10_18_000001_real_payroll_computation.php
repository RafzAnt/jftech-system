<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real payroll: the pay follows from the daily rate and the attendance, and the SSS, PhilHealth and Pag-IBIG shares from
 * the contribution tables (see App\Support\PayrollCalculator). That needs half days (so total_days_worked takes decimals),
 * the minutes behind the late and undertime amounts, and the way a payroll was made (automatic or manual). Records from
 * before this change keep computation_mode empty, which tells the payslip that their late and undertime were never deducted.
 *
 * The five working rules replace the old fixed weekly amounts for the daily rates 500, 580 and 600.
 */
return new class extends Migration
{
    private const NEW_SETTINGS = [
        ['payroll_days_per_month', '24', 'Working days in a month'],
        ['payroll_weeks_per_month', '4', 'Payrolls in a month (contributions are divided by this)'],
        ['payroll_hours_per_day', '8', 'Working hours in a day'],
        ['payroll_overtime_rate', '1.25', 'Overtime pay multiplier'],
    ];

    private const OLD_SETTINGS = [
        'payroll_500_sss', 'payroll_500_philhealth', 'payroll_500_pagibig',
        'payroll_580_sss', 'payroll_580_philhealth', 'payroll_580_pagibig',
        'payroll_600_sss', 'payroll_600_philhealth', 'payroll_600_pagibig',
    ];

    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->decimal('total_days_worked', 5, 2)->default(0)->change();
            if (! Schema::hasColumn('payroll', 'late_minutes')) {
                $table->unsignedInteger('late_minutes')->default(0);
            }
            if (! Schema::hasColumn('payroll', 'undertime_minutes')) {
                $table->unsignedInteger('undertime_minutes')->default(0);
            }
            if (! Schema::hasColumn('payroll', 'computation_mode')) {
                $table->string('computation_mode', 10)->nullable();
            }
        });

        foreach (self::NEW_SETTINGS as [$key, $value, $label]) {
            if (! DB::table('system_settings')->where('setting_key', $key)->exists()) {
                DB::table('system_settings')->insert(['setting_key' => $key, 'setting_value' => $value, 'setting_label' => $label]);
            }
        }
        DB::table('system_settings')->whereIn('setting_key', self::OLD_SETTINGS)->delete();
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('setting_key', array_column(self::NEW_SETTINGS, 0))->delete();

        Schema::table('payroll', function (Blueprint $table) {
            foreach (['late_minutes', 'undertime_minutes', 'computation_mode'] as $column) {
                if (Schema::hasColumn('payroll', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
