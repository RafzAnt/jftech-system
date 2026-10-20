<?php

namespace Tests\Feature;

use App\Support\PayrollService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Real payroll: the pay follows from the daily rate and the attendance, automatically or by hand.
 *
 * Seed: employee 1 (Eli, Technician, Balilihan) and employee 2 (Fay, Technician, Loon), both 500 a day; users 1 super admin,
 * 2 Balilihan admin, 3 Loon admin. The week of Monday 2026-03-02 holds the attendance of employee 1:
 *   Mon  morning 08:00 present, afternoon 13:00 present, overtime 17:00-18:00 (60 minutes) = 1 day
 *   Tue  morning 09:00 late (60 minutes), afternoon absent                              = half a day
 *   Wed  morning 08:00 present only                                                    = half a day
 *   Sun  both sessions absent (not counted as absences)
 * so 2 days worked, 60 minutes late, 60 minutes overtime, 1 absent session.
 */
class PayrollComputationTest extends TestCase
{
    use BranchTestData;

    private const START = '2026-03-02';
    private const END = '2026-03-08';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::table('attendance')->delete();
        DB::table('payroll')->delete();

        $blank = array_fill_keys(['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out', 'overtime_time_in', 'overtime_time_out', 'status_morning', 'status_afternoon'], null) + ['overtime_minutes' => 0];
        DB::table('attendance')->insert(array_map(fn (array $row) => $row + $blank, [
            ['employee_id' => 1, 'attendance_date' => '2026-03-02', 'morning_time_in' => '08:00:00', 'morning_time_out' => '12:00:00', 'afternoon_time_in' => '13:00:00', 'afternoon_time_out' => '17:00:00', 'overtime_time_in' => '17:00:00', 'overtime_time_out' => '18:00:00', 'overtime_minutes' => 60, 'status_morning' => 'present', 'status_afternoon' => 'present'],
            ['employee_id' => 1, 'attendance_date' => '2026-03-03', 'morning_time_in' => '09:00:00', 'morning_time_out' => '12:00:00', 'afternoon_time_in' => null, 'afternoon_time_out' => null, 'status_morning' => 'late', 'status_afternoon' => 'absent'],
            ['employee_id' => 1, 'attendance_date' => '2026-03-04', 'morning_time_in' => '08:00:00', 'morning_time_out' => null, 'afternoon_time_in' => null, 'afternoon_time_out' => null, 'status_morning' => 'present', 'status_afternoon' => null],
            ['employee_id' => 1, 'attendance_date' => '2026-03-08', 'status_morning' => 'absent', 'status_afternoon' => 'absent'],
        ]));
    }

    private function form(int $employeeId, array $extra = []): array
    {
        return $extra + ['employee_id' => $employeeId, 'payroll_period_start' => self::START, 'payroll_period_end' => self::END, 'status' => 'processed'];
    }

    private function payrollOf(int $employeeId): object
    {
        return DB::table('payroll')->where('employee_id', $employeeId)->orderByDesc('id')->first();
    }

    // ---- what the attendance says --------------------------------------------------------------

    public function test_the_attendance_gives_days_late_minutes_overtime_and_absences(): void
    {
        $summary = app(PayrollService::class)->attendance(1, self::START, self::END);

        $this->assertSame(2.0, $summary['days_worked']);
        $this->assertSame(60, $summary['late_minutes']);
        $this->assertSame(60, $summary['overtime_minutes']);
        $this->assertSame(1, $summary['absent_sessions']);
    }

    public function test_the_schedule_of_the_business_settings_is_used(): void
    {
        // A morning that starts at 07:00 makes the 09:00 scan two hours late.
        DB::table('system_settings')->insert(['setting_key' => 'qr_morning_start', 'setting_value' => '07:00:00']);

        $this->assertSame(120, app(PayrollService::class)->attendance(1, self::START, self::END)['late_minutes']);
    }

    public function test_the_overtime_of_every_day_is_added_up(): void
    {
        DB::table('attendance')->where('attendance_date', '2026-03-03')->update(['overtime_time_in' => '17:00:00', 'overtime_time_out' => '17:45:00', 'overtime_minutes' => 45]);

        $this->assertSame(105, app(PayrollService::class)->attendance(1, self::START, self::END)['overtime_minutes']);
    }

    public function test_overtime_with_times_but_no_minutes_is_worked_out_from_the_times(): void
    {
        DB::table('attendance')->where('attendance_date', '2026-03-02')->update(['overtime_minutes' => 0]);

        $this->assertSame(60, app(PayrollService::class)->attendance(1, self::START, self::END)['overtime_minutes']);
    }

    public function test_an_afternoon_time_out_after_the_end_of_the_session_is_not_overtime(): void
    {
        DB::table('attendance')->where('attendance_date', '2026-03-04')->update(['afternoon_time_in' => '13:00:00', 'afternoon_time_out' => '19:30:00', 'status_afternoon' => 'present']);

        $this->assertSame(60, app(PayrollService::class)->attendance(1, self::START, self::END)['overtime_minutes'], 'only the overtime session counts');
    }

    public function test_a_week_of_overtime_only_still_gets_an_automatic_payroll(): void
    {
        DB::table('attendance')->delete();
        DB::table('attendance')->insert(['employee_id' => 1, 'attendance_date' => '2026-03-08', 'overtime_time_in' => '08:00:00', 'overtime_time_out' => '12:00:00', 'overtime_minutes' => 240]);
        $this->signIn(2);

        $this->post('/payroll', ['payroll_action' => 'auto_generate', 'week_date' => self::START])->assertOk();

        $payroll = $this->payrollOf(1);
        $this->assertEquals(0, $payroll->total_days_worked);
        $this->assertEquals(4, $payroll->overtime_hours);
        $this->assertEquals(312.5, $payroll->overtime_pay);   // 62.50 x 1.25 x 4
    }

    // ---- an automatic payroll --------------------------------------------------------------------

    public function test_an_automatic_payroll_is_worked_out_from_the_rate_and_the_attendance(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk()->assertViewHas('success', 'Payroll record created successfully.');

        $payroll = $this->payrollOf(1);
        $this->assertSame('auto', $payroll->computation_mode);
        $this->assertEquals(2, $payroll->total_days_worked);
        $this->assertEquals(1000, $payroll->basic_salary);
        $this->assertEquals(60, $payroll->late_minutes);
        $this->assertEquals(62.5, $payroll->late);
        $this->assertEquals(1, $payroll->overtime_hours);
        $this->assertEquals(78.13, $payroll->overtime_pay);
        $this->assertEquals(12000, $payroll->monthly_basic_salary);
        $this->assertEquals(62.5, $payroll->hourly_rate);
        $this->assertEquals([150, 75, 50], [(float) $payroll->sss, (float) $payroll->philhealth, (float) $payroll->pagibig]);
        $this->assertEquals(250, $payroll->absent);
        // 1000 + 78.13 - (62.50 + 150 + 75 + 50)
        $this->assertEquals(1078.13, $payroll->gross_pay);
        $this->assertEquals(337.5, $payroll->deductions);
        $this->assertEquals(740.63, $payroll->net_pay);
    }

    public function test_an_automatic_payroll_ignores_amounts_typed_over_the_computed_ones(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto', 'basic_salary' => 99999, 'sss' => 1, 'late' => 0, 'total_days_worked' => 6]))->assertOk();

        $payroll = $this->payrollOf(1);
        $this->assertEquals(1000, $payroll->basic_salary);
        $this->assertEquals(150, $payroll->sss);
        $this->assertEquals(62.5, $payroll->late);
        $this->assertEquals(2, $payroll->total_days_worked);
    }

    public function test_an_automatic_payroll_still_takes_the_extras_and_the_undertime_minutes(): void
    {
        $this->signIn(2);
        // 30 minutes of undertime cost 31.25; 200 of incentives are added; a 100 cash advance is deducted.
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto', 'undertime_minutes' => 30, 'incentives' => 200, 'cash_advance' => 100]))->assertOk();

        $payroll = $this->payrollOf(1);
        $this->assertEquals(30, $payroll->undertime_minutes);
        $this->assertEquals(31.25, $payroll->undertime);
        $this->assertEquals(1278.13, $payroll->gross_pay);          // 1000 + 78.13 + 200
        $this->assertEquals(468.75, $payroll->deductions);          // 62.50 + 31.25 + 150 + 75 + 50 + 100
        $this->assertEquals(809.38, $payroll->net_pay);
    }

    public function test_an_automatic_payroll_needs_attendance(): void
    {
        $this->signIn(3);
        DB::table('employees')->where('id', 2)->update(['position' => 'Technician II']);

        $page = $this->post('/payroll', $this->form(2, ['computation_mode' => 'auto']))->assertOk();

        $this->assertStringContainsString('has no attendance in this period', $page->viewData('error'));
        $this->assertSame(0, DB::table('payroll')->count());
    }

    public function test_the_mode_defaults_to_automatic(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1))->assertOk();

        $this->assertSame('auto', $this->payrollOf(1)->computation_mode);
    }

    // ---- a manual payroll --------------------------------------------------------------------------

    public function test_a_manual_payroll_takes_the_quantities_and_works_out_the_amounts_from_them(): void
    {
        $this->signIn(2);
        // 3 days, 30 minutes late, 2 hours of overtime; no amount typed, so the standard computation fills them in.
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'manual', 'total_days_worked' => 3, 'late_minutes' => 30, 'overtime_hours' => 2]))->assertOk();

        $payroll = $this->payrollOf(1);
        $this->assertSame('manual', $payroll->computation_mode);
        $this->assertEquals(3, $payroll->total_days_worked);
        $this->assertEquals(1500, $payroll->basic_salary);
        $this->assertEquals(31.25, $payroll->late);
        $this->assertEquals(156.25, $payroll->overtime_pay);
        $this->assertEquals([150, 75, 50], [(float) $payroll->sss, (float) $payroll->philhealth, (float) $payroll->pagibig]);
    }

    public function test_a_manual_payroll_keeps_what_the_admin_typed_over_a_computed_amount(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, [
            'computation_mode' => 'manual', 'total_days_worked' => 3, 'basic_salary' => 1400, 'sss' => 100, 'philhealth' => 60, 'pagibig' => 40,
            'overtime_pay' => 500, 'late' => 10, 'allowances' => 300, 'other_deductions' => 20,
        ]))->assertOk();

        $payroll = $this->payrollOf(1);
        $this->assertEquals(1400, $payroll->basic_salary);
        $this->assertEquals([100, 60, 40], [(float) $payroll->sss, (float) $payroll->philhealth, (float) $payroll->pagibig]);
        $this->assertEquals(2200, $payroll->gross_pay);             // 1400 + 500 + 300
        $this->assertEquals(230, $payroll->deductions);             // 10 + 100 + 60 + 40 + 20
        $this->assertEquals(1970, $payroll->net_pay);
    }

    public function test_a_manual_payroll_leaves_the_contributions_to_the_standard_when_the_boxes_are_empty(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'manual', 'total_days_worked' => 2, 'sss' => '', 'philhealth' => '', 'pagibig' => '']))->assertOk();

        $payroll = $this->payrollOf(1);
        $this->assertEquals([150, 75, 50], [(float) $payroll->sss, (float) $payroll->philhealth, (float) $payroll->pagibig]);
        $this->assertEquals(337.5, $payroll->deductions, '275 of contributions and the 62.50 of the late minutes of the week');
    }

    public function test_a_contribution_typed_as_zero_stays_zero(): void
    {
        // An employee who is not covered: the admin types 0 on purpose, and that wins over the standard amount.
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'manual', 'total_days_worked' => 2, 'sss' => 0, 'philhealth' => 0, 'pagibig' => 0]))->assertOk();

        $this->assertEquals([0, 0, 0], [(float) $this->payrollOf(1)->sss, (float) $this->payrollOf(1)->philhealth, (float) $this->payrollOf(1)->pagibig]);
    }

    public function test_the_preview_gives_the_standard_contributions_even_without_attendance(): void
    {
        // The form fills the contributions of a manual payroll from this, so it must not depend on the days worked.
        DB::table('attendance')->delete();
        $this->signIn(2);

        $this->getJson('/payroll?action=preview&employee_id=1&start=' . self::START . '&end=' . self::END)
            ->assertOk()
            ->assertJsonPath('total_days_worked', 0)
            ->assertJsonPath('sss', 150)
            ->assertJsonPath('philhealth', 75)
            ->assertJsonPath('pagibig', 50);
    }

    public function test_the_form_shows_the_standard_amount_under_each_contribution_and_a_button_to_fill_them(): void
    {
        $this->signIn(2);

        $html = $this->get('/payroll')->assertOk()->getContent();

        foreach (['sss', 'philhealth', 'pagibig'] as $key) {
            $this->assertStringContainsString('id="hint_' . $key . '"', $html);
        }
        $this->assertStringContainsString('Fill standard amounts', $html);
    }

    public function test_a_manual_payroll_can_have_more_deductions_than_pay_and_the_net_is_zero(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'manual', 'total_days_worked' => 0.5, 'cash_advance' => 5000]))->assertOk();

        $this->assertEquals(0, $this->payrollOf(1)->net_pay);
    }

    public function test_the_days_worked_cannot_be_more_than_the_days_of_the_period(): void
    {
        $this->signIn(2);
        $page = $this->post('/payroll', $this->form(1, ['computation_mode' => 'manual', 'total_days_worked' => 8]))->assertOk();

        $this->assertSame('Error: Days worked cannot be more than the 7 day(s) of the period.', $page->viewData('error'));
        $this->assertSame(0, DB::table('payroll')->count());
    }

    public function test_a_number_that_is_not_a_number_or_too_large_is_refused(): void
    {
        $this->signIn(2);

        $this->assertSame('Error: SSS must be a number.', $this->post('/payroll', $this->form(1, ['computation_mode' => 'manual', 'sss' => 'lots']))->viewData('error'));
        $this->assertSame('Error: Incentives is too large.', $this->post('/payroll', $this->form(1, ['computation_mode' => 'manual', 'incentives' => 1000000]))->viewData('error'));
        $this->assertSame(0, DB::table('payroll')->count());
    }

    // ---- one payroll for each period -------------------------------------------------------------

    public function test_a_second_payroll_for_the_same_period_is_refused_but_an_edit_is_not(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk();

        $again = $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk();
        $this->assertSame('Error: Eli Santos already has payroll for this period. Edit that record instead.', $again->viewData('error'));
        $this->assertSame(1, DB::table('payroll')->count());

        $id = $this->payrollOf(1)->id;
        $edit = $this->post('/payroll', $this->form(1, ['payroll_id' => $id, 'computation_mode' => 'auto', 'status' => 'paid']))->assertOk();
        $this->assertSame('Payroll record updated successfully.', $edit->viewData('success'));
        $this->assertSame('paid', $this->payrollOf(1)->status);
        $this->assertSame(1, DB::table('payroll')->count());
    }

    public function test_an_archived_payroll_does_not_block_a_new_one(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk();
        DB::table('payroll')->update(['archived' => true]);

        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk()->assertViewHas('success', 'Payroll record created successfully.');
        $this->assertSame(2, DB::table('payroll')->count());
    }

    public function test_saving_an_automatic_payroll_again_follows_a_correction_of_the_attendance(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk();
        $id = $this->payrollOf(1)->id;

        // Wednesday afternoon turns out to have been worked after all.
        DB::table('attendance')->where('attendance_date', '2026-03-04')->update(['afternoon_time_in' => '13:00:00', 'status_afternoon' => 'present']);
        $this->post('/payroll', $this->form(1, ['payroll_id' => $id, 'computation_mode' => 'auto']))->assertOk();

        $this->assertEquals(2.5, $this->payrollOf(1)->total_days_worked);
        $this->assertEquals(1250, $this->payrollOf(1)->basic_salary);
    }

    // ---- the preview of the form -------------------------------------------------------------------

    public function test_the_preview_gives_the_numbers_and_the_notes_of_the_standard_computation(): void
    {
        $this->signIn(2);

        $this->getJson('/payroll?action=preview&employee_id=1&start=' . self::START . '&end=' . self::END)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_days_worked', 2)
            ->assertJsonPath('late_minutes', 60)
            ->assertJsonPath('late', 62.5)
            ->assertJsonPath('overtime_pay', 78.13)
            ->assertJsonPath('sss', 150)
            ->assertJsonPath('attendance_records', 4)
            ->assertJsonCount(7, 'notes');
    }

    public function test_the_preview_is_refused_for_an_employee_of_another_branch_or_a_bad_period(): void
    {
        $this->signIn(2);

        $this->getJson('/payroll?action=preview&employee_id=2&start=' . self::START . '&end=' . self::END)->assertStatus(422)->assertJsonPath('success', false);
        $this->getJson('/payroll?action=preview&employee_id=1&start=nonsense&end=' . self::END)->assertStatus(422);
        $this->getJson('/payroll?action=preview&employee_id=1&start=' . self::END . '&end=' . self::START)->assertStatus(422);
    }

    // ---- automatic payroll for a whole week -------------------------------------------------------

    private function addAdminEmployee(): void
    {
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Admin', 'position' => 'Admin', 'branch_name' => 'Balilihan', 'daily_rate' => 600, 'status' => 'active']);
        DB::table('attendance')->insert(['employee_id' => 3, 'attendance_date' => '2026-03-02', 'morning_time_in' => '08:00:00', 'afternoon_time_in' => '13:00:00', 'status_morning' => 'present', 'status_afternoon' => 'present']);
    }

    public function test_a_branch_admin_generates_the_week_for_the_technicians_of_their_branch(): void
    {
        $this->addAdminEmployee();
        DB::table('attendance')->insert(['employee_id' => 2, 'attendance_date' => '2026-03-02', 'morning_time_in' => '08:00:00', 'afternoon_time_in' => '13:00:00', 'status_morning' => 'present', 'status_afternoon' => 'present']);
        $this->signIn(2);

        $page = $this->post('/payroll', ['payroll_action' => 'auto_generate', 'week_date' => '2026-03-05'])->assertOk();

        $this->assertSame('Automatic payroll for Mar 02 - Mar 08, 2026: 1 created.', $page->viewData('success'));
        $this->assertSame([1], DB::table('payroll')->pluck('employee_id')->map(fn ($id) => (int) $id)->all(), 'not the Admin employee, not the other branch');
        $payroll = $this->payrollOf(1);
        $this->assertSame('auto', $payroll->computation_mode);
        $this->assertSame('processed', $payroll->status);
        $this->assertSame(self::START, substr((string) $payroll->payroll_period_start, 0, 10));
        $this->assertSame(self::END, substr((string) $payroll->payroll_period_end, 0, 10));
    }

    public function test_the_super_admin_generates_the_week_for_the_admin_employees_only(): void
    {
        $this->addAdminEmployee();
        $this->signIn(1);

        $this->post('/payroll', ['payroll_action' => 'auto_generate', 'week_date' => self::START])->assertOk();

        $this->assertSame([3], DB::table('payroll')->pluck('employee_id')->map(fn ($id) => (int) $id)->all());
        // 600 a day, one full day (both sessions on the Monday); the contributions of a 600 rate are 181.25, 90 and 50.
        $payroll = $this->payrollOf(3);
        $this->assertEquals(600, $payroll->basic_salary);
        $this->assertEquals([181.25, 90, 50], [(float) $payroll->sss, (float) $payroll->philhealth, (float) $payroll->pagibig]);
    }

    public function test_generating_the_week_again_skips_what_exists_and_who_has_no_attendance(): void
    {
        $this->signIn(2);
        DB::table('employees')->insert(['id' => 4, 'employee_code' => 'EMP-4', 'full_name' => 'Hal Tan', 'position' => 'Technician I', 'branch_name' => 'Balilihan', 'daily_rate' => 500, 'status' => 'active']);
        DB::table('employees')->insert(['id' => 5, 'employee_code' => 'EMP-5', 'full_name' => 'Ina Cruz', 'position' => 'Technician I', 'branch_name' => 'Balilihan', 'daily_rate' => 0, 'status' => 'active']);
        DB::table('attendance')->insert(['employee_id' => 5, 'attendance_date' => '2026-03-02', 'morning_time_in' => '08:00:00', 'status_morning' => 'present']);

        $this->post('/payroll', ['payroll_action' => 'auto_generate', 'week_date' => self::START])->assertOk();
        $second = $this->post('/payroll', ['payroll_action' => 'auto_generate', 'week_date' => self::START])->assertOk();

        $this->assertSame('Automatic payroll for Mar 02 - Mar 08, 2026: 0 created, 1 already had payroll, 1 had no attendance, 1 have no daily rate.', $second->viewData('success'));
        $this->assertSame(1, DB::table('payroll')->count());
    }

    public function test_a_week_that_is_not_a_date_is_refused(): void
    {
        $this->signIn(2);

        $this->assertSame('Error: Choose a date in the week to generate payroll for.', $this->post('/payroll', ['payroll_action' => 'auto_generate', 'week_date' => '2026-13-45'])->viewData('error'));
    }

    public function test_the_super_admin_with_no_admin_employees_cannot_generate_a_week(): void
    {
        $this->signIn(1);

        $page = $this->post('/payroll', ['payroll_action' => 'auto_generate', 'week_date' => self::START])->assertOk();

        $this->assertStringStartsWith('Error: The super admin generates payroll for the Admin employees only', $page->viewData('error'));
        $this->assertSame(0, DB::table('payroll')->count());
    }

    public function test_the_weekly_command_generates_payroll_for_everybody_with_attendance_that_week(): void
    {
        DB::table('attendance')->insert(['employee_id' => 2, 'attendance_date' => now()->startOfWeek()->toDateString(), 'morning_time_in' => '08:00:00', 'status_morning' => 'present']);
        DB::table('attendance')->insert(['employee_id' => 1, 'attendance_date' => now()->startOfWeek()->toDateString(), 'morning_time_in' => '08:00:00', 'status_morning' => 'present']);

        Artisan::call('payroll:generate-weekly');
        Artisan::call('payroll:generate-weekly');

        $this->assertSame([1, 2], DB::table('payroll')->orderBy('employee_id')->pluck('employee_id')->map(fn ($id) => (int) $id)->all());
        $this->assertEquals(250, $this->payrollOf(1)->basic_salary);
    }

    public function test_the_week_is_monday_to_sunday(): void
    {
        $this->assertSame(['2026-03-02', '2026-03-08'], app(PayrollService::class)->weekOf(Carbon::parse('2026-03-05')));
        $this->assertSame(['2026-03-02', '2026-03-08'], app(PayrollService::class)->weekOf(Carbon::parse('2026-03-08')));
        $this->assertSame(['2026-03-09', '2026-03-15'], app(PayrollService::class)->weekOf(Carbon::parse('2026-03-09')));
    }

    // ---- the payslip --------------------------------------------------------------------------------

    public function test_the_payroll_page_hides_the_weekly_generator_and_shows_the_late_and_undertime_of_a_computed_payslip(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk();

        $html = $this->get('/payroll')->assertOk()->getContent();

        $this->assertStringNotContainsString('Automatic Weekly Payroll', $html);
        $this->assertStringContainsString('payslip-mode-badge', $html);
        $this->assertStringContainsString('AUTOMATIC', $html);
        $this->assertStringContainsString('Late (60 min)', $html);
        $this->assertStringContainsString('<tr><td>Undertime</td>', $html);
    }

    public function test_an_old_payslip_keeps_its_old_deductions(): void
    {
        $this->signIn(2);
        // A record from before the real computation: no computation mode, its late amount was never deducted.
        DB::table('payroll')->insert(['employee_id' => 1, 'payroll_period_start' => '2026-02-02', 'payroll_period_end' => '2026-02-08', 'late' => 62.5, 'deductions' => 150, 'net_pay' => 900, 'status' => 'processed']);

        $html = $this->get('/payroll')->assertOk()->getContent();

        $this->assertStringNotContainsString('payslip-mode-badge', $html);
        $this->assertStringNotContainsString('<tr><td>Undertime</td>', $html);
    }

    public function test_a_branch_admin_without_employees_to_pay_does_not_get_the_weekly_generator(): void
    {
        DB::table('employees')->where('id', 1)->update(['position' => 'Admin']);
        $this->signIn(2);

        $this->assertStringNotContainsString('Automatic Weekly Payroll', $this->get('/payroll')->assertOk()->getContent());
    }

    public function test_the_print_page_lists_late_and_undertime_of_a_computed_payslip(): void
    {
        $this->signIn(2);
        $this->post('/payroll', $this->form(1, ['computation_mode' => 'auto']))->assertOk();

        $html = $this->get('/print-payroll?id=' . $this->payrollOf(1)->id)->assertOk()->getContent();

        $this->assertStringContainsString('Late (60 min)', $html);
        $this->assertStringContainsString('NET PAY', $html);
    }

    // ---- the business settings ---------------------------------------------------------------------

    public function test_the_settings_page_shows_the_contribution_table_of_the_daily_rates(): void
    {
        $this->signIn(1);
        $samples = $this->get('/settings')->assertOk()->viewData('payrollSamples');

        $byRate = [];
        foreach ($samples as $sample) {
            $byRate[(int) $sample['rate']] = $sample['weekly'];
        }
        $this->assertSame(['sss' => 150.0, 'philhealth' => 75.0, 'pagibig' => 50.0], $byRate[500]);
        $this->assertSame(['sss' => 181.25, 'philhealth' => 90.0, 'pagibig' => 50.0], $byRate[600]);
    }

    public function test_the_super_admin_changes_the_payroll_rules_and_the_payroll_follows(): void
    {
        $this->signIn(1);
        $saved = $this->post('/settings', ['update_settings' => 1, 'settings' => ['payroll_days_per_month' => 26, 'payroll_weeks_per_month' => 5, 'payroll_hours_per_day' => 8, 'payroll_overtime_rate' => 1.5]])->assertOk();
        $this->assertSame('System settings updated successfully!', $saved->viewData('success'));
        $this->assertSame('26', DB::table('system_settings')->where('setting_key', 'payroll_days_per_month')->value('setting_value'), 'a rule the database did not have yet is added');

        $this->assertSame(['days_per_month' => 26.0, 'weeks_per_month' => 5.0, 'hours_per_day' => 8.0, 'overtime_rate' => 1.5], app(PayrollService::class)->settings());
    }

    public function test_a_payroll_rule_out_of_range_is_refused(): void
    {
        $this->signIn(1);

        $this->assertSame('Error: Payrolls in a month (contributions are divided by this) must be between 1 and 5.', $this->post('/settings', ['update_settings' => 1, 'settings' => ['payroll_weeks_per_month' => 9]])->viewData('error'));
        $this->assertSame('Error: The payroll settings must be valid positive numbers.', $this->post('/settings', ['update_settings' => 1, 'settings' => ['payroll_overtime_rate' => 'lots']])->viewData('error'));
        $this->assertSame(0, DB::table('system_settings')->where('setting_key', 'like', 'payroll_%')->count());
    }

    public function test_a_branch_admin_cannot_change_the_payroll_rules(): void
    {
        $this->signIn(2);

        $page = $this->post('/settings', ['update_settings' => 1, 'settings' => ['payroll_days_per_month' => 20]])->assertOk();

        $this->assertSame('Error: Only the super admin can change the system settings.', $page->viewData('error'));
    }
}
