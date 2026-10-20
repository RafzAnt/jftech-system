<?php

namespace Tests\Feature;

use App\Support\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Overtime is a third attendance session: the employee scans the overtime QR code to start and to end it (or the admin enters it
 * by hand), and its minutes go to the payroll.
 *
 * Seed: employees 1 (Eli, Balilihan) and 2 (Fay, Loon); users 1 super admin, 2 Balilihan admin, 3 Loon admin. The employees page
 * declares helper functions every time it runs, so each test runs in its own process and calls it once.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AttendanceOvertimeTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
    }

    private function page(string $page, array $query = [], array $post = []): array
    {
        $uri = '/' . $page . ($query ? '?' . http_build_query($query) : '');
        $request = Request::create($uri, $post ? 'POST' : 'GET', $post);
        $request->setLaravelSession(app('session')->driver('array'));
        app()->instance('request', $request);

        $action = require app_path("Support/PageActions/{$page}.php");

        return $action($request);
    }

    /** An open overtime QR code (17:00 to 22:00), and the time it is. */
    private function openOvertimeQr(string $at = '17:05', ?string $branch = 'Balilihan'): void
    {
        DB::table('qr_codes')->insert(['qr_code' => 'QR-OVERTIME', 'qr_type' => 'overtime', 'valid_date' => now()->toDateString(), 'valid_from' => '17:00:00', 'valid_until' => '22:00:00', 'status' => 'active', 'branch_name' => $branch]);
        $this->travelTo(now()->setTimeFromTimeString($at));
    }

    private function overtimeQr(string $code, ?string $branch, string $status = 'active'): void
    {
        DB::table('qr_codes')->insert(['qr_code' => $code, 'qr_type' => 'overtime', 'valid_date' => now()->toDateString(), 'valid_from' => '17:00:00', 'valid_until' => '22:00:00', 'status' => $status, 'branch_name' => $branch]);
    }

    private function scan(int $employeeId = 1)
    {
        return $this->postJson('/backend/api/scan_attendance.php', ['qr_code' => 'QR-OVERTIME'], $this->appHeaders($employeeId));
    }

    private function attendanceOf(int $employeeId): ?object
    {
        return DB::table('attendance')->where('employee_id', $employeeId)->whereDate('attendance_date', now()->toDateString())->first();
    }

    // ---- the overtime QR code in the app ---------------------------------------------------------

    public function test_the_first_scan_starts_the_overtime_and_the_second_ends_it(): void
    {
        $this->openOvertimeQr('17:05');
        $this->scan()->assertOk()->assertJson(['success' => true, 'scan_type' => 'Overtime In', 'overtime_minutes' => 0, 'employee_name' => 'Eli Santos']);
        $this->assertSame('17:05:00', $this->attendanceOf(1)->overtime_time_in);
        $this->assertNull($this->attendanceOf(1)->overtime_time_out);

        $this->travelTo(now()->setTimeFromTimeString('18:35'));
        $this->scan()->assertOk()->assertJson(['success' => true, 'scan_type' => 'Overtime Out', 'overtime_minutes' => 90]);

        $row = $this->attendanceOf(1);
        $this->assertSame('18:35:00', $row->overtime_time_out);
        $this->assertSame(90, (int) $row->overtime_minutes);
        $this->assertSame('08:00:00', $row->morning_time_in, 'the morning of the same row is untouched');
    }

    public function test_a_third_scan_is_refused(): void
    {
        $this->openOvertimeQr('17:05');
        $this->scan()->assertOk();
        $this->travelTo(now()->setTimeFromTimeString('18:00'));
        $this->scan()->assertOk();

        $this->travelTo(now()->setTimeFromTimeString('19:00'));
        $this->scan()->assertStatus(422)->assertJson(['success' => false, 'message' => 'Your overtime for today is already recorded.']);
        $this->assertSame(55, (int) $this->attendanceOf(1)->overtime_minutes);
    }

    public function test_a_second_scan_within_the_same_minute_does_not_end_the_overtime(): void
    {
        $this->openOvertimeQr('17:05');
        $this->scan()->assertOk();

        $this->scan()->assertStatus(422)->assertJsonPath('message', 'You have just timed in for overtime. Scan again when your overtime ends.');
        $this->assertNull($this->attendanceOf(1)->overtime_time_out);
    }

    public function test_an_overtime_qr_code_only_works_for_the_employees_of_its_branch(): void
    {
        $this->openOvertimeQr();

        $loon = $this->scan(2);
        $loon->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('not for your branch', $loon->json('message'));
        $this->assertNull($this->attendanceOf(2)->overtime_time_in, 'an employee of another branch records no overtime');

        $this->scan(1)->assertOk()->assertJson(['success' => true, 'scan_type' => 'Overtime In']);
    }

    public function test_an_overtime_qr_code_without_a_branch_is_not_accepted(): void
    {
        // What the earlier shared code looks like: no branch.
        $this->openOvertimeQr('17:05', null);

        $this->scan(1)->assertStatus(422);
        $this->assertNull($this->attendanceOf(1)->overtime_time_in);
    }

    public function test_the_overtime_qr_code_only_works_in_its_window(): void
    {
        $this->openOvertimeQr('16:30');

        $this->scan()->assertStatus(422)->assertJsonPath('message', 'QR code is outside its valid time window.');
        $this->assertNull($this->attendanceOf(1)->overtime_time_in);
    }

    public function test_overtime_is_added_to_the_row_of_the_day_with_the_branch_of_the_employee(): void
    {
        $this->openOvertimeQr('17:10', 'Loon');

        $this->scan(2)->assertOk()->assertJson(['scan_type' => 'Overtime In', 'branch_name' => 'Loon']);

        $row = $this->attendanceOf(2);
        $this->assertSame('Loon', $row->branch_name);
        $this->assertSame('QR-OVERTIME', $row->qr_code_used);
        $this->assertSame('08:05:00', $row->morning_time_in, 'the seeded morning scan of the same row is untouched');
    }

    public function test_the_app_sees_the_overtime_of_today_and_is_on_duty_while_it_runs(): void
    {
        $this->openOvertimeQr('17:05');
        $this->scan()->assertOk();

        $this->getJson('/backend/api/me.php', $this->appHeaders(1))
            ->assertOk()
            ->assertJsonPath('today.overtime_time_in', '17:05:00')
            ->assertJsonPath('today.overtime_time_out', null)
            ->assertJsonPath('today.on_duty', true);

        $this->travelTo(now()->setTimeFromTimeString('18:05'));
        $this->scan()->assertOk();
        $this->getJson('/backend/api/me.php', $this->appHeaders(1))
            ->assertJsonPath('today.overtime_minutes', 60)
            ->assertJsonPath('today.on_duty', true);
    }

    public function test_the_minutes_of_a_scanned_overtime_reach_the_payroll(): void
    {
        $this->openOvertimeQr('17:05');
        $this->scan()->assertOk();
        $this->travelTo(now()->setTimeFromTimeString('18:35'));
        $this->scan()->assertOk();

        $summary = app(PayrollService::class)->attendance(1, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString());

        $this->assertSame(90, $summary['overtime_minutes']);
        // 62.50 an hour x 1.25 x 1.5 hours
        $this->assertSame(117.19, app(PayrollService::class)->compute(1, 500, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString())['overtime_pay']);
    }

    // ---- the admin enters overtime by hand ---------------------------------------------------------

    private function manual(array $fields, int $user = 2): array
    {
        $this->signIn($user);

        return $this->page('employees', ['tab' => 'attendance'], [
            'manual_attendance' => 1, 'manual_employee_id' => 1, 'manual_date' => now()->subDay()->toDateString(),
        ] + $fields);
    }

    private function yesterdayRow(): ?object
    {
        return DB::table('attendance')->where('employee_id', 1)->where('attendance_date', now()->subDay()->toDateString())->first();
    }

    public function test_an_admin_records_overtime_by_hand_with_its_minutes(): void
    {
        $page = $this->manual(['manual_morning_in' => '08:00', 'manual_morning_out' => '12:00', 'manual_overtime_in' => '17:00', 'manual_overtime_out' => '19:30']);

        $this->assertStringStartsWith('Attendance saved for Eli Santos', $page['success']);
        $row = $this->yesterdayRow();
        $this->assertSame('17:00:00', $row->overtime_time_in);
        $this->assertSame('19:30:00', $row->overtime_time_out);
        $this->assertSame(150, (int) $row->overtime_minutes);
        $this->assertSame('08:00:00', $row->morning_time_in);
    }

    public function test_overtime_alone_is_a_valid_record(): void
    {
        $page = $this->manual(['manual_overtime_in' => '08:00', 'manual_overtime_out' => '12:00']);

        $this->assertStringStartsWith('Attendance saved', $page['success']);
        $this->assertSame(240, (int) $this->yesterdayRow()->overtime_minutes);
    }

    public function test_overtime_needs_both_times_in_the_right_order_and_no_more_than_12_hours(): void
    {
        $this->assertSame('Enter both the overtime time in and the overtime time out.', $this->manual(['manual_overtime_in' => '17:00'])['error']);
        $this->assertNull($this->yesterdayRow());
    }

    public function test_overtime_that_ends_before_it_starts_is_refused(): void
    {
        $this->assertSame('Overtime time out must be after time in.', $this->manual(['manual_overtime_in' => '19:00', 'manual_overtime_out' => '18:00'])['error']);
        $this->assertNull($this->yesterdayRow());
    }

    public function test_more_than_12_hours_of_overtime_is_refused(): void
    {
        $this->assertSame('Overtime cannot be longer than 12 hours in a day.', $this->manual(['manual_overtime_in' => '06:00', 'manual_overtime_out' => '18:30'])['error']);
        $this->assertNull($this->yesterdayRow());
    }

    public function test_overtime_can_be_taken_away_again(): void
    {
        DB::table('attendance')->insert(['employee_id' => 1, 'attendance_date' => now()->subDay()->toDateString(), 'morning_time_in' => '08:00:00', 'overtime_time_in' => '17:00:00', 'overtime_time_out' => '18:00:00', 'overtime_minutes' => 60, 'status_morning' => 'present']);

        $page = $this->manual(['manual_overtime_clear' => 1]);

        $this->assertStringStartsWith('Attendance saved', $page['success']);
        $row = $this->yesterdayRow();
        $this->assertNull($row->overtime_time_in);
        $this->assertNull($row->overtime_time_out);
        $this->assertSame(0, (int) $row->overtime_minutes);
        $this->assertSame('08:00:00', $row->morning_time_in);
    }

    public function test_a_manual_record_still_needs_at_least_one_session(): void
    {
        $this->assertSame('Fill in at least one session (morning, afternoon or overtime).', $this->manual([])['error']);
    }

    public function test_a_branch_admin_cannot_record_overtime_for_an_admin_employee(): void
    {
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Admin', 'position' => 'Admin', 'branch_name' => 'Balilihan', 'status' => 'active']);
        $this->signIn(2);

        $page = $this->page('employees', ['tab' => 'attendance'], [
            'manual_attendance' => 1, 'manual_employee_id' => 3, 'manual_date' => now()->subDay()->toDateString(), 'manual_overtime_in' => '17:00', 'manual_overtime_out' => '18:00',
        ]);

        $this->assertSame('Attendance for Admin employees is recorded by the super admin.', $page['error']);
        $this->assertSame(0, DB::table('attendance')->where('employee_id', 3)->whereNotNull('overtime_time_in')->count());
    }

    // ---- the QR code and the settings ------------------------------------------------------------------

    public function test_a_branch_admin_generates_the_overtime_qr_of_their_branch_with_the_default_window(): void
    {
        $this->signIn(2);

        $page = $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'overtime']);

        $this->assertSame('Overtime QR Code generated successfully!', $page['success']);
        $qr = DB::table('qr_codes')->where('qr_type', 'overtime')->first();
        $this->assertSame('17:00:00', $qr->valid_from);
        $this->assertSame('22:00:00', $qr->valid_until);
        $this->assertSame('Balilihan', $qr->branch_name, 'the code carries the branch of its admin');
        $this->assertStringStartsWith('JFTECH-OVERTIME-', $qr->qr_code);
        $this->assertSame('overtime', collect($page['qrCodes'])->firstWhere('status', 'active')['qr_type']);
    }

    public function test_the_overtime_qr_follows_the_times_of_the_settings(): void
    {
        DB::table('system_settings')->insert([['setting_key' => 'qr_overtime_start', 'setting_value' => '17:30'], ['setting_key' => 'qr_overtime_end', 'setting_value' => '20:00']]);
        $this->signIn(2);

        $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'overtime']);

        $qr = DB::table('qr_codes')->where('qr_type', 'overtime')->first();
        $this->assertSame('17:30:00', $qr->valid_from);
        $this->assertSame('20:00:00', $qr->valid_until);
    }

    public function test_generating_the_overtime_qr_again_replaces_only_the_one_of_that_branch(): void
    {
        $this->overtimeQr('QR-OLD-BALILIHAN', 'Balilihan');
        $this->overtimeQr('QR-LOON', 'Loon');
        $this->signIn(2);

        $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'overtime']);

        $this->assertSame('expired', DB::table('qr_codes')->where('qr_code', 'QR-OLD-BALILIHAN')->value('status'));
        $this->assertSame('active', DB::table('qr_codes')->where('qr_code', 'QR-LOON')->value('status'), "another branch's code is not touched");
        $this->assertSame(1, DB::table('qr_codes')->where('qr_type', 'overtime')->where('branch_name', 'Balilihan')->where('status', 'active')->count());
    }

    public function test_each_branch_has_its_own_overtime_qr_the_same_day(): void
    {
        $this->overtimeQr('QR-LOON', 'Loon');
        $this->signIn(2);

        $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'overtime']);

        $codes = DB::table('qr_codes')->where('qr_type', 'overtime')->where('status', 'active')->orderBy('branch_name')->pluck('branch_name')->all();
        $this->assertSame(['Balilihan', 'Loon'], $codes, 'both branches have a code the same day');
    }

    public function test_a_qr_type_that_is_not_a_session_is_refused(): void
    {
        $this->signIn(1);

        $page = $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => "morning_start' OR '1'='1"]);

        $this->assertSame('Error: Choose the morning, afternoon or overtime QR code.', $page['error']);
        $this->assertSame(0, DB::table('qr_codes')->count());
    }

    public function test_the_super_admin_cannot_generate_an_overtime_qr(): void
    {
        $this->signIn(1);

        try {
            $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'overtime']);
            $this->fail('generating the QR code should be refused');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame(0, DB::table('qr_codes')->count());
        }
    }

    #[DataProvider('sharedQrTypes')]
    public function test_a_branch_admin_still_cannot_generate_the_shared_morning_and_afternoon_qr(string $type): void
    {
        $this->signIn(2);

        try {
            $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => $type]);
            $this->fail("the {$type} QR code is the super admin's");
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('qr_codes')->count());
    }

    public static function sharedQrTypes(): array
    {
        return ['morning' => ['morning'], 'afternoon' => ['afternoon']];
    }

    public function test_the_attendance_tab_shows_the_overtime_column_and_the_manual_fields(): void
    {
        DB::table('attendance')->where('id', 1)->update(['overtime_time_in' => '17:00:00', 'overtime_time_out' => '18:30:00', 'overtime_minutes' => 90]);
        $this->signIn(1);

        $html = $this->get('/employees?tab=attendance')->assertOk()->getContent();

        $this->assertStringContainsString('<th>Overtime</th>', $html);
        $this->assertStringContainsString('05:00 PM - 06:30 PM', $html);
        $this->assertStringContainsString('90 min', $html);
        $this->assertStringContainsString('name="manual_overtime_in"', $html);
        $this->assertStringContainsString('name="manual_overtime_clear"', $html);
    }

    public function test_a_branch_admin_sees_the_overtime_qr_of_their_branch_and_a_button_to_make_it(): void
    {
        $this->overtimeQr('QR-BALILIHAN', 'Balilihan');
        $this->overtimeQr('QR-LOON', 'Loon');
        $this->signIn(2);

        $html = $this->get('/employees?tab=attendance')->assertOk()->getContent();

        $this->assertStringContainsString('id="overtimeQR"', $html);
        $this->assertStringContainsString('QR-BALILIHAN', $html);
        $this->assertStringNotContainsString('QR-LOON', $html, "another branch's code is not shown");
        $this->assertStringContainsString('Generate Overtime QR', $html);
        $this->assertStringContainsString('Only for Balilihan.', $html);
        $this->assertStringContainsString('id="printOvertimeQr"', $html);
        $this->assertStringContainsString('Overtime · Balilihan', $html);
    }

    public function test_a_branch_admin_without_a_code_is_told_so(): void
    {
        $this->overtimeQr('QR-LOON', 'Loon');
        $this->signIn(2);

        $html = $this->get('/employees?tab=attendance')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="overtimeQR"', $html);
        $this->assertStringContainsString('No active QR code', $html);
        $this->assertStringContainsString('Generate Overtime QR', $html);
    }

    public function test_the_super_admin_sees_which_branches_have_an_overtime_qr_but_cannot_make_one(): void
    {
        $this->overtimeQr('QR-BALILIHAN', 'Balilihan');
        $this->overtimeQr('QR-LOON', 'Loon');
        $this->overtimeQr('QR-OLD', 'Loon', 'expired');
        $this->signIn(1);

        $page = $this->get('/employees?tab=attendance')->assertOk();
        $html = $page->getContent();

        $this->assertSame(['Balilihan', 'Loon'], array_column($page->viewData('overtimeQrByBranch'), 'branch_name'));
        $this->assertStringContainsString('Each branch admin makes the overtime QR code of their own branch.', $html);
        $this->assertStringContainsString('<strong>Loon</strong>', $html);
        $this->assertStringNotContainsString('Generate Overtime QR', $html);
        $this->assertStringNotContainsString('id="overtimeQR"', $html, 'the super admin has no overtime code of their own');
        $this->assertStringNotContainsString('id="printOvertimeQr"', $html);
    }

    public function test_the_printed_attendance_sheet_has_the_shared_codes_and_the_overtime_code_of_the_own_branch(): void
    {
        DB::table('qr_codes')->insert([
            ['qr_code' => 'QR-MORNING', 'qr_type' => 'morning', 'valid_date' => now()->toDateString(), 'valid_from' => '08:00:00', 'valid_until' => '12:00:00', 'status' => 'active'],
        ]);
        $this->overtimeQr('QR-BALILIHAN', 'Balilihan');
        $this->overtimeQr('QR-LOON', 'Loon');

        $this->signIn(2);
        $mine = array_column($this->page('print_attendance')['attendanceQrCodes'], 'qr_code');
        sort($mine);
        $this->assertSame(['QR-BALILIHAN', 'QR-MORNING'], $mine);

        $this->signIn(1);
        $all = array_column($this->page('print_attendance')['attendanceQrCodes'], 'qr_code');
        $this->assertSame(['QR-MORNING'], $all, 'the super admin has no overtime code to print');
    }

    public function test_the_super_admin_sets_the_overtime_qr_times(): void
    {
        $this->signIn(1);

        $page = $this->page('settings', [], ['update_settings' => 1, 'settings' => ['qr_overtime_start' => '18:00', 'qr_overtime_end' => '21:30']]);

        $this->assertSame('System settings updated successfully!', $page['success']);
        $this->assertSame('18:00', DB::table('system_settings')->where('setting_key', 'qr_overtime_start')->value('setting_value'), 'a database without the rows gets them');
        $this->assertSame('21:30', DB::table('system_settings')->where('setting_key', 'qr_overtime_end')->value('setting_value'));
    }

    public function test_overtime_qr_times_must_be_times_and_in_order(): void
    {
        $this->signIn(1);

        $this->assertSame('Error: Enter the overtime QR times as valid times.', $this->page('settings', [], ['update_settings' => 1, 'settings' => ['qr_overtime_start' => 'later']])['error']);
        $this->assertSame('Error: The overtime QR end time must be after its start time.', $this->page('settings', [], ['update_settings' => 1, 'settings' => ['qr_overtime_start' => '20:00', 'qr_overtime_end' => '19:00']])['error']);
        $this->assertSame(0, DB::table('system_settings')->where('setting_key', 'like', 'qr_overtime%')->count());
    }

    public function test_the_printed_attendance_lists_the_overtime(): void
    {
        DB::table('attendance')->where('id', 1)->update(['overtime_time_in' => '17:00:00', 'overtime_time_out' => '18:30:00', 'overtime_minutes' => 90]);
        $this->signIn(1);

        $html = $this->get('/print-attendance?start=' . now()->startOfMonth()->toDateString() . '&end=' . now()->endOfMonth()->toDateString())->assertOk()->getContent();

        $this->assertStringContainsString('Overtime', $html);
        $this->assertStringContainsString('05:00 PM - 06:30 PM (90 min)', $html);
    }
}
