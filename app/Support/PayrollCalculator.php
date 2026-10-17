<?php

namespace App\Support;

/**
 * The payroll arithmetic with no database in it: a daily rate, the attendance of the period and the contribution
 * tables go in, the amounts of the payslip come out.
 *
 * The rules (as the business gave them):
 *  - monthly salary = daily rate x 24 days; hourly rate = daily rate / 8
 *  - late: hourly rate x minutes late / 60 (one hour late costs one hour of pay, 30 minutes costs half of that)
 *  - overtime: hourly rate x 1.25 x overtime hours
 *  - SSS, PhilHealth and Pag-IBIG: the employee's monthly share from the table, divided by 4 for each weekly payroll
 *
 * The contribution tables are the ones in force in 2025/2026 (SSS Circular 2024-006, PhilHealth 5% premium, Pag-IBIG
 * maximum fund salary of 10,000). When a law changes them, only the three methods below need to change.
 */
final class PayrollCalculator
{
    /** The working rules a payroll uses; the business settings can change them. */
    public const DEFAULTS = [
        'days_per_month' => 24,
        'weeks_per_month' => 4,
        'hours_per_day' => 8,
        'overtime_rate' => 1.25,
    ];

    /** Everything that is added to the gross pay. */
    public const EARNINGS = [
        'basic_salary', 'overtime_pay', 'regular_holiday', 'special_holiday', 'night_differential', 'incentives',
        'allowances', 'leave_pay', 'rest_day_pay', 'salary_adjustment', 'other_earnings',
    ];

    /** Everything that is taken from the gross pay. */
    public const DEDUCTIONS = [
        'late', 'undertime', 'sss', 'philhealth', 'pagibig', 'withholding_tax', 'cash_advance', 'salary_loan', 'hdmf_loan', 'other_deductions',
    ];

    /** Earnings and deductions that are always typed by the admin; they have no formula. */
    public const EXTRA_EARNINGS = ['regular_holiday', 'special_holiday', 'night_differential', 'incentives', 'allowances', 'leave_pay', 'rest_day_pay', 'salary_adjustment', 'other_earnings'];
    public const EXTRA_DEDUCTIONS = ['withholding_tax', 'cash_advance', 'salary_loan', 'hdmf_loan', 'other_deductions'];

    /** The amounts the formulas produce; in a manual payroll the admin may overwrite each of them. */
    public const COMPUTED = ['basic_salary', 'overtime_pay', 'late', 'undertime', 'absent', 'sss', 'philhealth', 'pagibig'];

    /** @param array<string, mixed> $values */
    public static function settings(array $values = []): array
    {
        $settings = self::DEFAULTS;
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                $settings[$key] = (float) $values[$key];
            }
        }

        return $settings;
    }

    public static function money(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * SSS: the monthly salary credit (MSC) is the salary rounded to the nearest 500, from 5,000 up to 35,000. The employee
     * pays 5% of it, the employer 10% plus the EC contribution (10, or 30 from an MSC of 15,000).
     *
     * @return array{msc: float, employee: float, employer: float, ec: float}
     */
    public static function sss(float $monthlySalary): array
    {
        $msc = min(35000.0, max(5000.0, round($monthlySalary / 500) * 500));

        return [
            'msc' => $msc,
            'employee' => self::money($msc * 0.05),
            'employer' => self::money($msc * 0.10),
            'ec' => $msc < 15000 ? 10.0 : 30.0,
        ];
    }

    /**
     * PhilHealth: a 5% premium of the monthly basic salary (counted from 10,000 up to 100,000), shared equally.
     *
     * @return array{base: float, premium: float, employee: float, employer: float}
     */
    public static function philhealth(float $monthlySalary): array
    {
        $base = min(100000.0, max(10000.0, $monthlySalary));
        $premium = self::money($base * 0.05);

        return ['base' => $base, 'premium' => $premium, 'employee' => self::money($premium / 2), 'employer' => self::money($premium / 2)];
    }

    /**
     * Pag-IBIG (HDMF): 2% from the employee and 2% from the employer on a salary of up to 10,000, so 200 each at most.
     * Below 1,500 a month the employee pays 1%.
     *
     * @return array{fund_salary: float, rate: float, employee: float, employer: float}
     */
    public static function pagibig(float $monthlySalary): array
    {
        $fundSalary = min(10000.0, $monthlySalary);
        $rate = $monthlySalary <= 1500 ? 0.01 : 0.02;

        return ['fund_salary' => $fundSalary, 'rate' => $rate, 'employee' => self::money($fundSalary * $rate), 'employer' => self::money($fundSalary * 0.02)];
    }

    /**
     * What one payroll takes for the three contributions: the monthly employee share divided by the payrolls in a month.
     *
     * @return array{monthly_salary: float, sss: array, philhealth: array, pagibig: array, weekly: array{sss: float, philhealth: float, pagibig: float}}
     */
    public static function contributions(float $dailyRate, array $settings = []): array
    {
        $s = self::settings($settings);
        $monthly = $dailyRate * $s['days_per_month'];
        $sss = self::sss($monthly);
        $philhealth = self::philhealth($monthly);
        $pagibig = self::pagibig($monthly);

        return [
            'monthly_salary' => self::money($monthly),
            'sss' => $sss,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'weekly' => [
                'sss' => self::money($sss['employee'] / $s['weeks_per_month']),
                'philhealth' => self::money($philhealth['employee'] / $s['weeks_per_month']),
                'pagibig' => self::money($pagibig['employee'] / $s['weeks_per_month']),
            ],
        ];
    }

    public static function hourlyRate(float $dailyRate, array $settings = []): float
    {
        return $dailyRate / self::settings($settings)['hours_per_day'];
    }

    /** The pay lost to being late, undertime or any other number of minutes, at the hourly rate. */
    public static function minutesPay(float $dailyRate, float $minutes, array $settings = []): float
    {
        return self::money(self::hourlyRate($dailyRate, $settings) * max(0, $minutes) / 60);
    }

    /** The overtime pay: the hourly rate, plus 25%, for every hour. */
    public static function overtimePay(float $dailyRate, float $hours, array $settings = []): float
    {
        $s = self::settings($settings);

        return self::money(self::hourlyRate($dailyRate, $s) * $s['overtime_rate'] * max(0, $hours));
    }

    /**
     * The lines of a payslip that follow from the rate and the attendance.
     *
     * @param array{days_worked?: float, late_minutes?: float, undertime_minutes?: float, overtime_minutes?: float, absent_sessions?: float} $attendance
     */
    public static function compute(float $dailyRate, array $attendance, array $settings = []): array
    {
        $s = self::settings($settings);
        $daysWorked = round(max(0, (float) ($attendance['days_worked'] ?? 0)), 2);
        $lateMinutes = (int) round(max(0, (float) ($attendance['late_minutes'] ?? 0)));
        $undertimeMinutes = (int) round(max(0, (float) ($attendance['undertime_minutes'] ?? 0)));
        $overtimeMinutes = round(max(0, (float) ($attendance['overtime_minutes'] ?? 0)), 2);
        $absentSessions = max(0, (float) ($attendance['absent_sessions'] ?? 0));

        $hourly = self::hourlyRate($dailyRate, $s);
        $contributions = self::contributions($dailyRate, $s);

        return [
            'monthly_basic_salary' => $contributions['monthly_salary'],
            'hourly_rate' => self::money($hourly),
            'total_days_worked' => $daysWorked,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'basic_salary' => self::money($daysWorked * $dailyRate),
            'late' => self::minutesPay($dailyRate, $lateMinutes, $s),
            'undertime' => self::minutesPay($dailyRate, $undertimeMinutes, $s),
            'overtime_pay' => self::money($hourly * $s['overtime_rate'] * $overtimeMinutes / 60),
            // Days without attendance are not paid at all; this only shows what was lost.
            'absent' => self::money($absentSessions * $dailyRate / 2),
            'sss' => $contributions['weekly']['sss'],
            'philhealth' => $contributions['weekly']['philhealth'],
            'pagibig' => $contributions['weekly']['pagibig'],
            'notes' => self::notes($dailyRate, $daysWorked, $lateMinutes, $undertimeMinutes, $overtimeMinutes, $contributions, $s),
        ];
    }

    /** Gross pay, total deductions and net pay of a set of lines (the net pay is never negative). */
    public static function totals(array $lines): array
    {
        $gross = 0.0;
        foreach (self::EARNINGS as $key) {
            $gross += (float) ($lines[$key] ?? 0);
        }
        $deductions = 0.0;
        foreach (self::DEDUCTIONS as $key) {
            $deductions += (float) ($lines[$key] ?? 0);
        }

        return [
            'gross_pay' => self::money($gross),
            'deductions' => self::money($deductions),
            'net_pay' => self::money(max(0, $gross - $deductions)),
        ];
    }

    /** "1 hr 30 min", "45 min", "2 hrs". */
    public static function duration(float $minutes): string
    {
        $minutes = (int) round($minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . ($hours === 1 ? ' hr' : ' hrs');
        }
        if ($rest > 0 || $parts === []) {
            $parts[] = $rest . ' min';
        }

        return implode(' ', $parts);
    }

    /** The computation in words, so the admin can check it against the payslip. */
    private static function notes(float $dailyRate, float $daysWorked, int $lateMinutes, int $undertimeMinutes, float $overtimeMinutes, array $c, array $s): array
    {
        $m = static fn (float $amount): string => '₱' . number_format($amount, 2);
        $hourly = $dailyRate / $s['hours_per_day'];
        $n = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        $notes = [
            "Monthly salary: {$m($dailyRate)} x {$n($s['days_per_month'])} days = {$m($c['monthly_salary'])}. Hourly rate: {$m($dailyRate)} / {$n($s['hours_per_day'])} = {$m($hourly)}.",
            "Basic salary: {$n($daysWorked)} days worked x {$m($dailyRate)} = {$m($daysWorked * $dailyRate)}.",
        ];
        if ($lateMinutes > 0) {
            $notes[] = 'Late: ' . self::duration($lateMinutes) . " x {$m($hourly)} per hour = {$m(self::minutesPay($dailyRate, $lateMinutes, $s))} deducted.";
        }
        if ($undertimeMinutes > 0) {
            $notes[] = 'Undertime: ' . self::duration($undertimeMinutes) . " x {$m($hourly)} per hour = {$m(self::minutesPay($dailyRate, $undertimeMinutes, $s))} deducted.";
        }
        if ($overtimeMinutes > 0) {
            $notes[] = 'Overtime: ' . self::duration($overtimeMinutes) . " x {$m($hourly)} x {$n($s['overtime_rate'])} = {$m($hourly * $s['overtime_rate'] * $overtimeMinutes / 60)}.";
        }

        $weeks = $n($s['weeks_per_month']);
        $sss = $c['sss'];
        $philhealth = $c['philhealth'];
        $pagibig = $c['pagibig'];
        $notes[] = "SSS: salary credit {$m($sss['msc'])}, employee share {$m($sss['employee'])} / {$weeks} = {$m($c['weekly']['sss'])} (the employer adds {$m($sss['employer'] + $sss['ec'])}).";
        $notes[] = "PhilHealth: {$m($philhealth['base'])} x 5% = {$m($philhealth['premium'])}, employee half {$m($philhealth['employee'])} / {$weeks} = {$m($c['weekly']['philhealth'])}.";
        $notes[] = 'Pag-IBIG: ' . ($pagibig['rate'] * 100) . "% of {$m($pagibig['fund_salary'])} = {$m($pagibig['employee'])} / {$weeks} = {$m($c['weekly']['pagibig'])}.";

        return $notes;
    }
}
