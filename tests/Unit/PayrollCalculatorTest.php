<?php

namespace Tests\Unit;

use App\Support\PayrollCalculator as Payroll;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The payroll arithmetic against the business's own examples: a rate of 500 a day is 12,000 a month, SSS 600,
 * PhilHealth 300 and Pag-IBIG 200 a month (150, 75 and 50 a week); an hour late costs 62.50 and an overtime hour pays 78.125.
 */
class PayrollCalculatorTest extends TestCase
{
    /** @return array<string, array{float, float, float, float, float}> rate => monthly salary, weekly SSS, PhilHealth, Pag-IBIG */
    public static function dailyRates(): array
    {
        return [
            '500 a day' => [500, 12000, 150, 75, 50],
            '550 a day' => [550, 13200, 162.5, 82.5, 50],
            '580 a day' => [580, 13920, 175, 87, 50],
            '600 a day' => [600, 14400, 181.25, 90, 50],
            '630 a day' => [630, 15120, 187.5, 94.5, 50],
        ];
    }

    #[DataProvider('dailyRates')]
    public function test_the_weekly_contributions_follow_from_the_daily_rate(float $rate, float $monthly, float $sss, float $philhealth, float $pagibig): void
    {
        $contributions = Payroll::contributions($rate);

        $this->assertSame($monthly, $contributions['monthly_salary']);
        $this->assertSame($sss, $contributions['weekly']['sss']);
        $this->assertSame($philhealth, $contributions['weekly']['philhealth']);
        $this->assertSame($pagibig, $contributions['weekly']['pagibig']);
    }

    public function test_the_monthly_shares_of_a_rate_of_500_are_the_ones_of_the_business_notes(): void
    {
        $contributions = Payroll::contributions(500);

        $this->assertSame(600.0, $contributions['sss']['employee']);
        // Employer: twice the employee share plus 10 for the EC contribution.
        $this->assertSame(1210.0, $contributions['sss']['employer'] + $contributions['sss']['ec']);
        $this->assertSame(300.0, $contributions['philhealth']['employee']);
        $this->assertSame(300.0, $contributions['philhealth']['employer']);
        $this->assertSame(200.0, $contributions['pagibig']['employee']);
        $this->assertSame(200.0, $contributions['pagibig']['employer']);
    }

    public function test_the_sss_salary_credit_is_the_salary_rounded_to_500_between_5000_and_35000(): void
    {
        $this->assertSame(5000.0, Payroll::sss(1000)['msc']);
        $this->assertSame(5000.0, Payroll::sss(5249.99)['msc']);
        $this->assertSame(5500.0, Payroll::sss(5250)['msc']);
        $this->assertSame(12000.0, Payroll::sss(11750)['msc']);
        $this->assertSame(12000.0, Payroll::sss(12249.99)['msc']);
        $this->assertSame(35000.0, Payroll::sss(34750)['msc']);
        $this->assertSame(35000.0, Payroll::sss(90000)['msc']);

        $top = Payroll::sss(90000);
        $this->assertSame(1750.0, $top['employee']);
        $this->assertSame(3500.0, $top['employer']);
    }

    public function test_the_sss_ec_contribution_is_10_below_a_salary_credit_of_15000_and_30_from_it(): void
    {
        $this->assertSame(10.0, Payroll::sss(14000)['ec']);
        $this->assertSame(10.0, Payroll::sss(14500)['ec']);
        $this->assertSame(30.0, Payroll::sss(15000)['ec']);
    }

    public function test_philhealth_counts_a_salary_of_10000_to_100000(): void
    {
        $this->assertSame(250.0, Payroll::philhealth(6000)['employee']);
        $this->assertSame(300.0, Payroll::philhealth(12000)['employee']);
        $this->assertSame(2500.0, Payroll::philhealth(250000)['employee']);
    }

    public function test_pagibig_is_two_percent_of_at_most_10000_and_one_percent_up_to_1500(): void
    {
        $this->assertSame(10.0, Payroll::pagibig(1000)['employee']);
        $this->assertSame(200.0, Payroll::pagibig(12000)['employee']);
        $this->assertSame(200.0, Payroll::pagibig(50000)['employee']);
        $this->assertSame(100.0, Payroll::pagibig(5000)['employee']);
    }

    public function test_an_hour_late_costs_an_hour_of_pay_and_half_an_hour_half_of_that(): void
    {
        $this->assertSame(62.5, Payroll::minutesPay(500, 60));
        $this->assertSame(31.25, Payroll::minutesPay(500, 30));
        $this->assertSame(93.75, Payroll::minutesPay(500, 90));
        $this->assertSame(0.0, Payroll::minutesPay(500, 0));
    }

    public function test_overtime_pays_the_hourly_rate_plus_25_percent(): void
    {
        $this->assertSame(78.13, Payroll::overtimePay(500, 1));   // 62.50 x 1.25 = 78.125
        $this->assertSame(39.06, Payroll::overtimePay(500, 0.5));
        $this->assertSame(156.25, Payroll::overtimePay(500, 2));
    }

    public function test_a_week_of_work_comes_out_as_the_payslip_lines(): void
    {
        $lines = Payroll::compute(500, ['days_worked' => 5.5, 'late_minutes' => 60, 'overtime_minutes' => 60, 'absent_sessions' => 1]);

        $this->assertSame(12000.0, $lines['monthly_basic_salary']);
        $this->assertSame(62.5, $lines['hourly_rate']);
        $this->assertSame(2750.0, $lines['basic_salary']);
        $this->assertSame(62.5, $lines['late']);
        $this->assertSame(1.0, $lines['overtime_hours']);
        $this->assertSame(78.13, $lines['overtime_pay']);
        $this->assertSame(250.0, $lines['absent']);
        $this->assertSame([150.0, 75.0, 50.0], [$lines['sss'], $lines['philhealth'], $lines['pagibig']]);

        // 2750 + 78.13 - (62.50 + 150 + 75 + 50)
        $this->assertSame(['gross_pay' => 2828.13, 'deductions' => 337.5, 'net_pay' => 2490.63], Payroll::totals($lines));
    }

    public function test_the_absent_amount_is_only_shown_and_never_deducted_again(): void
    {
        $lines = Payroll::compute(500, ['days_worked' => 3, 'absent_sessions' => 6]);

        $this->assertSame(1500.0, $lines['basic_salary']);
        $this->assertSame(1500.0, $lines['absent']);
        $this->assertSame(1500.0 - 275.0, Payroll::totals($lines)['net_pay']);
    }

    public function test_the_net_pay_is_never_below_zero(): void
    {
        $totals = Payroll::totals(['basic_salary' => 100, 'sss' => 150, 'philhealth' => 75]);

        $this->assertSame(100.0, $totals['gross_pay']);
        $this->assertSame(225.0, $totals['deductions']);
        $this->assertSame(0.0, $totals['net_pay']);
    }

    public function test_the_working_rules_can_be_changed(): void
    {
        // 26 days a month, 5 payrolls a month, a 9 hour day and double overtime.
        $rules = ['days_per_month' => 26, 'weeks_per_month' => 5, 'hours_per_day' => 9, 'overtime_rate' => 2];

        $this->assertSame(13000.0, Payroll::contributions(500, $rules)['monthly_salary']);
        $this->assertSame(130.0, Payroll::contributions(500, $rules)['weekly']['sss']);   // MSC 13,000 -> 650 / 5
        $this->assertSame(55.56, Payroll::minutesPay(500, 60, $rules));                  // 500 / 9
        $this->assertSame(111.11, Payroll::overtimePay(500, 1, $rules));
    }

    public function test_settings_that_are_not_positive_numbers_fall_back_to_the_defaults(): void
    {
        $this->assertSame(Payroll::DEFAULTS, Payroll::settings(['days_per_month' => 'abc', 'weeks_per_month' => 0, 'hours_per_day' => -3, 'overtime_rate' => null]));
    }

    public function test_the_notes_explain_the_computation(): void
    {
        $notes = implode("\n", Payroll::compute(500, ['days_worked' => 5, 'late_minutes' => 90, 'overtime_minutes' => 30])['notes']);

        $this->assertStringContainsString('₱500.00 x 24 days = ₱12,000.00', $notes);
        $this->assertStringContainsString('Late: 1 hr 30 min', $notes);
        $this->assertStringContainsString('Overtime: 30 min', $notes);
        $this->assertStringContainsString('employee share ₱600.00 / 4 = ₱150.00 (the employer adds ₱1,210.00)', $notes);
        $this->assertStringContainsString('₱12,000.00 x 5% = ₱600.00, employee half ₱300.00 / 4 = ₱75.00', $notes);
    }
}
