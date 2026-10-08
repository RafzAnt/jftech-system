<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WeeklyPayrollService
{
    /**
     * Generate one processed payroll per active employee for the Monday–Sunday
     * period containing the supplied date. Existing payroll for the same
     * employee and period is deliberately retained and never overwritten.
     */
    public function generateForWeek(?Carbon $date = null): int
    {
        $date ??= now(config('app.timezone', 'Asia/Manila'));
        $periodStart = $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $periodEnd = $date->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $settings = DB::table('system_settings')
            ->whereIn('setting_key', $this->deductionSettingKeys())
            ->pluck('setting_value', 'setting_key')
            ->all();

        $created = 0;
        DB::table('employees')->where('status', 'active')->orderBy('id')->each(function ($employee) use ($periodStart, $periodEnd, $settings, &$created) {
            $wasCreated = DB::transaction(function () use ($employee, $periodStart, $periodEnd, $settings) {
                $alreadyGenerated = DB::table('payroll')
                    ->where('employee_id', $employee->id)
                    ->where('payroll_period_start', $periodStart)
                    ->where('payroll_period_end', $periodEnd)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyGenerated) {
                    return false;
                }

                $dailyRate = (float) $employee->daily_rate;
                if ($dailyRate <= 0) {
                    return false;
                }

                $attendance = DB::table('attendance')
                    ->where('employee_id', $employee->id)
                    ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                    ->selectRaw("COUNT(CASE WHEN COALESCE(total_hours, 0) > 0 OR morning_time_in IS NOT NULL OR afternoon_time_in IS NOT NULL THEN 1 END) AS days_worked")
                    ->selectRaw('COALESCE(SUM(total_hours), 0) AS total_hours')
                    ->selectRaw("SUM(CASE WHEN status_morning = 'late' THEN 1 ELSE 0 END) + SUM(CASE WHEN status_afternoon = 'late' THEN 1 ELSE 0 END) AS late_count")
                    ->selectRaw("SUM(CASE WHEN status_morning = 'absent' THEN 1 ELSE 0 END) + SUM(CASE WHEN status_afternoon = 'absent' THEN 1 ELSE 0 END) AS absent_count")
                    ->first();

                $daysWorked = (int) ($attendance->days_worked ?? 0);
                $totalHours = (float) ($attendance->total_hours ?? 0);
                $lateAmount = (int) ($attendance->late_count ?? 0) * ($dailyRate / 8);
                $absentAmount = (int) ($attendance->absent_count ?? 0) * ($dailyRate / 2);
                $deductions = $this->deductionsFor($dailyRate, $settings);
                $basicSalary = $daysWorked * $dailyRate;
                $totalDeductions = $deductions['sss'] + $deductions['philhealth'] + $deductions['pagibig'];

                DB::table('payroll')->insert([
                    'employee_id' => $employee->id,
                    'payroll_period_start' => $periodStart,
                    'payroll_period_end' => $periodEnd,
                    'total_days_worked' => $daysWorked,
                    'total_hours' => $totalHours,
                    'daily_rate' => $dailyRate,
                    'monthly_basic_salary' => $dailyRate * 26,
                    'hourly_rate' => $dailyRate / 8,
                    'overtime_hours' => 0,
                    'late' => $lateAmount,
                    'undertime' => 0,
                    'absent' => $absentAmount,
                    'basic_salary' => $basicSalary,
                    'overtime_pay' => 0,
                    'regular_holiday' => 0,
                    'special_holiday' => 0,
                    'night_differential' => 0,
                    'incentives' => 0,
                    'allowances' => 0,
                    'leave_pay' => 0,
                    'rest_day_pay' => 0,
                    'salary_adjustment' => 0,
                    'other_earnings' => 0,
                    'gross_pay' => $basicSalary,
                    'sss' => $deductions['sss'],
                    'philhealth' => $deductions['philhealth'],
                    'pagibig' => $deductions['pagibig'],
                    'withholding_tax' => 0,
                    'cash_advance' => 0,
                    'salary_loan' => 0,
                    'hdmf_loan' => 0,
                    'other_deductions' => 0,
                    'deductions' => $totalDeductions,
                    'net_pay' => max(0, $basicSalary - $totalDeductions),
                    'status' => 'processed',
                    'archived' => false,
                    'created_at' => now(),
                ]);

                return true;
            });

            if ($wasCreated) {
                $created++;
            }
        });

        return $created;
    }

    private function deductionsFor(float $dailyRate, array $settings): array
    {
        $rate = (string) (int) round($dailyRate);
        if (! in_array($rate, ['500', '580', '600'], true)) {
            return ['sss' => 0, 'philhealth' => 0, 'pagibig' => 0];
        }

        return [
            'sss' => (float) ($settings["payroll_{$rate}_sss"] ?? 0),
            'philhealth' => (float) ($settings["payroll_{$rate}_philhealth"] ?? 0),
            'pagibig' => (float) ($settings["payroll_{$rate}_pagibig"] ?? 0),
        ];
    }

    private function deductionSettingKeys(): array
    {
        $keys = [];
        foreach ([500, 580, 600] as $rate) {
            foreach (['sss', 'philhealth', 'pagibig'] as $deduction) {
                $keys[] = "payroll_{$rate}_{$deduction}";
            }
        }

        return $keys;
    }
}
