<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payroll from the database side: it reads the business settings and the attendance of a period, lets
 * {@see PayrollCalculator} do the arithmetic, and writes automatic payroll for a week. The payroll page uses the same
 * methods for its preview and its manual form, so an automatic and a manual payroll are always worked out alike.
 */
class PayrollService
{
    /** The working rules (days per month, ...), from the business settings with the defaults behind them. */
    public function settings(): array
    {
        $keys = [
            'days_per_month' => 'payroll_days_per_month',
            'weeks_per_month' => 'payroll_weeks_per_month',
            'hours_per_day' => 'payroll_hours_per_day',
            'overtime_rate' => 'payroll_overtime_rate',
        ];
        $stored = DB::table('system_settings')->whereIn('setting_key', array_values($keys))->pluck('setting_value', 'setting_key')->all();

        $values = [];
        foreach ($keys as $name => $key) {
            $values[$name] = $stored[$key] ?? null;
        }

        return PayrollCalculator::settings($values);
    }

    /** @return array{morning_start: string, afternoon_start: string} */
    private function schedule(): array
    {
        $stored = DB::table('system_settings')
            ->whereIn('setting_key', ['qr_morning_start', 'qr_afternoon_start'])
            ->pluck('setting_value', 'setting_key')->all();

        return [
            'morning_start' => $stored['qr_morning_start'] ?? '08:00:00',
            'afternoon_start' => $stored['qr_afternoon_start'] ?? '13:00:00',
        ];
    }

    /**
     * What the attendance of the period says: every session with a time in is half a day; a session marked late costs the
     * minutes between its start and the time in; the overtime session (scanned or entered by hand) adds its minutes.
     *
     * @return array{days_worked: float, late_minutes: int, overtime_minutes: int, absent_sessions: int, total_hours: float, records: int}
     */
    public function attendance(int $employeeId, string $start, string $end): array
    {
        $schedule = $this->schedule();
        $starts = ['morning' => $schedule['morning_start'], 'afternoon' => $schedule['afternoon_start']];

        $summary = ['days_worked' => 0.0, 'late_minutes' => 0, 'overtime_minutes' => 0, 'absent_sessions' => 0, 'total_hours' => 0.0, 'records' => 0];
        $rows = DB::table('attendance')->where('employee_id', $employeeId)->whereBetween('attendance_date', [$start, $end])->get();

        foreach ($rows as $row) {
            $summary['records']++;
            $summary['total_hours'] += (float) ($row->total_hours ?? 0);
            $sunday = Carbon::parse($row->attendance_date)->isSunday();

            foreach ($starts as $part => $sessionStart) {
                $timeIn = $row->{"{$part}_time_in"} ?? null;
                if ($timeIn) {
                    $summary['days_worked'] += 0.5;
                    if (($row->{"status_{$part}"} ?? null) === 'late') {
                        $summary['late_minutes'] += AttendanceTime::minutesBetween($sessionStart, $timeIn);
                    }
                } elseif (($row->{"status_{$part}"} ?? null) === 'absent' && ! $sunday) {
                    $summary['absent_sessions']++;
                }
            }

            // The overtime session holds its minutes; an old record that has the times but no minutes is worked out from them.
            $overtime = (int) ($row->overtime_minutes ?? 0);
            if ($overtime === 0 && ! empty($row->overtime_time_in) && ! empty($row->overtime_time_out)) {
                $overtime = AttendanceTime::minutesBetween($row->overtime_time_in, $row->overtime_time_out);
            }
            $summary['overtime_minutes'] += $overtime;
        }

        return $summary;
    }

    /**
     * Every line of the payslip that follows from the rate and the attendance of the period.
     *
     * @param array<string, mixed> $override quantities the admin typed (undertime minutes, or any of the attendance counts)
     */
    public function compute(int $employeeId, float $dailyRate, string $start, string $end, array $override = []): array
    {
        $attendance = $this->attendance($employeeId, $start, $end);
        $lines = PayrollCalculator::compute($dailyRate, $override + $attendance, $this->settings());
        $lines['total_hours'] = round($attendance['total_hours'], 2);
        $lines['attendance_records'] = $attendance['records'];

        return $lines;
    }

    /**
     * The columns of a payroll record: the computed lines, the typed extras, and the totals worked out from both.
     *
     * @param array<string, mixed> $lines  from {@see compute()}, possibly with typed amounts over them
     * @param array<string, float> $extras typed earnings and deductions that have no formula
     */
    public function row(int $employeeId, float $dailyRate, string $start, string $end, array $lines, array $extras, string $mode, string $status): array
    {
        $lines = $extras + $lines;
        $totals = PayrollCalculator::totals($lines);

        $row = [
            'employee_id' => $employeeId,
            'payroll_period_start' => $start,
            'payroll_period_end' => $end,
            'total_days_worked' => $lines['total_days_worked'],
            'total_hours' => $lines['total_hours'] ?? 0,
            'daily_rate' => $dailyRate,
            'monthly_basic_salary' => $lines['monthly_basic_salary'],
            'hourly_rate' => $lines['hourly_rate'],
            'overtime_hours' => $lines['overtime_hours'],
            'late_minutes' => $lines['late_minutes'],
            'undertime_minutes' => $lines['undertime_minutes'],
            'absent' => $lines['absent'],
            'computation_mode' => $mode,
            'status' => $status,
        ];
        foreach (array_merge(PayrollCalculator::EARNINGS, PayrollCalculator::DEDUCTIONS) as $key) {
            $row[$key] = $lines[$key] ?? 0;
        }

        return $row + $totals;
    }

    /** The Monday and the Sunday of the week that contains the date. */
    public function weekOf(?Carbon $date = null): array
    {
        $date ??= now(config('app.timezone', 'Asia/Manila'));

        return [$date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $date->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()];
    }

    /**
     * Automatic payroll: one processed record for every employee (of the given ids, or all active ones) who has attendance in
     * the Monday-Sunday week and no payroll for it yet. Existing payroll is never overwritten.
     *
     * @param array<int>|null $employeeIds
     * @return array{period_start: string, period_end: string, created: int, existing: int, no_attendance: int, no_rate: int}
     */
    public function generateForWeek(?Carbon $date = null, ?array $employeeIds = null): array
    {
        [$start, $end] = $this->weekOf($date);
        $result = ['period_start' => $start, 'period_end' => $end, 'created' => 0, 'existing' => 0, 'no_attendance' => 0, 'no_rate' => 0];

        $query = DB::table('employees')->where('status', 'active')->orderBy('id');
        if ($employeeIds !== null) {
            $query->whereIn('id', $employeeIds ?: [0]);
        }

        foreach ($query->get() as $employee) {
            $outcome = DB::transaction(function () use ($employee, $start, $end): string {
                $dailyRate = (float) $employee->daily_rate;
                if ($dailyRate <= 0) {
                    return 'no_rate';
                }

                $exists = DB::table('payroll')
                    ->where('employee_id', $employee->id)->where('payroll_period_start', $start)->where('payroll_period_end', $end)
                    ->whereRaw('(archived = 0 OR archived IS NULL)')
                    ->lockForUpdate()->exists();
                if ($exists) {
                    return 'existing';
                }

                $lines = $this->compute((int) $employee->id, $dailyRate, $start, $end);
                if ($lines['total_days_worked'] <= 0 && $lines['overtime_hours'] <= 0) {
                    return 'no_attendance';
                }

                DB::table('payroll')->insert($this->row((int) $employee->id, $dailyRate, $start, $end, $lines, [], 'auto', 'processed') + ['archived' => false, 'created_at' => now()]);

                return 'created';
            });
            $result[$outcome]++;
        }

        return $result;
    }
}
