<?php

namespace Database\Seeders;

use App\Support\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The weekly payroll of the sample attendance, with its deductions: for every Monday-to-Sunday week that is complete inside the
 * attendance window (see BranchStaffAttendanceSeeder::window()), one payroll per employee, worked out by the system from the daily
 * rate and the attendance exactly as `payroll:generate-weekly` does (days worked, late and undertime by the minute, absences,
 * overtime, and the SSS, PhilHealth and Pag-IBIG shares). The older weeks are marked paid, the latest one processed. A week the
 * window only reaches part of is left for the admin to generate.
 *
 *   php artisan db:seed --class=BranchPayrollSeeder
 *
 * An employee that already has payroll for a week is skipped, so running it again changes nothing.
 */
class BranchPayrollSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('The sample payroll is only for a development or demo database.');
        }

        [$first, $last] = BranchStaffAttendanceSeeder::window();
        $service = app(PayrollService::class);

        $weeks = [];
        for ($monday = $first->copy()->startOfWeek(Carbon::MONDAY); $monday->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()->lte($last); $monday->addWeek()) {
            $weeks[] = $monday->copy();
        }

        $created = 0;
        foreach ($weeks as $index => $monday) {
            $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY);
            $after = (int) DB::table('payroll')->max('id');

            $created += $service->generateForWeek($monday->copy())['created'];

            // Made after the week ended, and paid once a newer week exists.
            DB::table('payroll')->where('id', '>', $after)->where('payroll_period_start', $monday->toDateString())->update([
                'created_at' => $sunday->copy()->addDay()->setTime(9, 0),
                'status' => $index < count($weeks) - 1 ? 'paid' : 'processed',
            ]);
        }

        $this->command?->info("Payroll: {$created} weekly record(s) made for " . count($weeks) . ' week(s).');
    }
}
