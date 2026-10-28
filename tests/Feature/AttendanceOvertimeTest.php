<?php

namespace Tests\Feature;

use App\Support\AttendanceTime;
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
 * Overtime has no QR code of its own: the afternoon QR code works until the last scan of the day (22:00 by default), and the scan
 * after the end of the afternoon (17:00) is the time out of the day, so the time between the two is the overtime. The admin can also
 * enter it by hand. Its minutes go to the payroll, which pays a day of overtime only from one hour.
 *
 * Seed: employees 1 (Eli, Balilihan) and 2 (Fay, Loon); users 1 super admin, 2 Balilihan admin, 3 Loon admin. Both have today's morning
 * time in. The employees page declares helper functions every time it runs, so each test runs in its own process and calls it once.
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

    /** Today's afternoon QR code, as the page makes it (open until the last scan of the day). */
    private function afternoonQr(string $until = '22:00:00'): void
    {
        DB::table('qr_codes')->insert(['qr_code' => 'QR-AFTERNOON', 'qr_type' => 'afternoon', 'valid_date' => now()->toDateString(), 'valid_from' => '13:00:00', 'valid_until' => $until, 'status' => 'active']);
    }

    private function scanAt(string $at, int $employeeId = 1, string $code = 'QR-AFTERNOON')
    {
        $this->travelTo(now()->setTimeFromTimeString($at));

        return $this->postJson('/backend/api/scan_attendance.php', ['qr_code' => $code], $this->appHeaders($employeeId));
    }

    /** The employee is at work in the afternoon: the time in at 13:00. */
    private function timedIn(int $employeeId = 1): void
    {
        $this->afternoonQr();
        $this->scanAt('13:00', $employeeId)->assertOk()->assertJson(['scan_type' => 'Time In']);
    }

    private function attendanceOf(int $employeeId): ?object
    {
        return DB::table('attendance')->where('employee_id', $employeeId)->whereDate('attendance_date', now()->toDateString())->first();
    }

    // ---- overtime from the afternoon time out ----------------------------------------------------

    public function test_the_time_out_after_the_afternoon_ends_gives_the_time_after_it_as_overtime(): void
    {
        $this->timedIn();

        $this->scanAt('18:30')->assertOk()->assertJson([
            'success' => true, 'scan_type' => 'Time Out', 'overtime_minutes' => 90, 'overtime_counted' => true, 'overtime_minimum_minutes' => 60,
            'message' => 'Attendance recorded. Overtime: 1 hr 30 min.', 'employee_name' => 'Eli Santos',
        ]);

        $row = $this->attendanceOf(1);
        $this->assertSame('17:00:00', $row->afternoon_time_out, 'the afternoon ends at its usual time');
        $this->assertSame('17:00:00', $row->overtime_time_in);
        $this->assertSame('18:30:00', $row->overtime_time_out);
        $this->assertSame(90, (int) $row->overtime_minutes);
        $this->assertSame('08:00:00', $row->morning_time_in, 'the morning of the same row is untouched');
        $this->assertEquals(4.0, (float) $row->total_hours, 'the hours are those of the sessions, without the overtime');
        $this->assertSame(0, AttendanceTime::undertimeOf($row->afternoon_time_in, $row->afternoon_time_out, '17:00:00'));
    }

    public function test_a_time_out_before_the_afternoon_ends_is_not_overtime(): void
    {
        $this->timedIn();

        $this->scanAt('16:57')->assertOk()->assertJson(['scan_type' => 'Time Out', 'overtime_minutes' => 0, 'overtime_counted' => false, 'message' => 'Attendance recorded.']);

        $row = $this->attendanceOf(1);
        $this->assertSame('16:57:00', $row->afternoon_time_out);
        $this->assertNull($row->overtime_time_in);
        $this->assertSame(0, (int) $row->overtime_minutes);
    }

    public function test_overtime_under_an_hour_is_recorded_but_the_employee_is_told_it_is_not_paid(): void
    {
        $this->timedIn();

        $this->scanAt('17:40')->assertOk()->assertJson([
            'overtime_minutes' => 40, 'overtime_counted' => false,
            'message' => 'Attendance recorded. Your overtime of 40 min is noted, but overtime is paid only from 1 hr a day.',
        ]);

        $this->assertSame(40, (int) $this->attendanceOf(1)->overtime_minutes, 'the record keeps what was worked');
        $payroll = app(PayrollService::class)->attendance(1, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString());
        $this->assertSame(0, $payroll['overtime_minutes'], 'the payroll does not count it');
    }

    public function test_a_scan_a_few_minutes_after_the_end_is_overtime_minutes_and_not_undertime(): void
    {
        $this->timedIn();

        $this->scanAt('17:03')->assertOk()->assertJson(['overtime_minutes' => 3, 'overtime_counted' => false]);
        $this->assertSame('17:00:00', $this->attendanceOf(1)->afternoon_time_out);
    }

    public function test_the_last_scan_of_the_day_is_the_time_out(): void
    {
        $this->timedIn();
        $this->scanAt('19:00')->assertOk()->assertJson(['overtime_minutes' => 120]);

        $this->scanAt('19:30')->assertOk()->assertJson(['scan_type' => 'Time Out', 'overtime_minutes' => 150]);

        $row = $this->attendanceOf(1);
        $this->assertSame('19:30:00', $row->overtime_time_out);
        $this->assertSame(150, (int) $row->overtime_minutes);
    }

    public function test_someone_who_timed_out_on_time_and_stays_can_scan_again_for_the_overtime(): void
    {
        $this->timedIn();
        $this->scanAt('16:57')->assertOk();

        $this->scanAt('19:00')->assertOk()->assertJson(['overtime_minutes' => 120]);

        $row = $this->attendanceOf(1);
        $this->assertSame('17:00:00', $row->afternoon_time_out);
        $this->assertSame(120, (int) $row->overtime_minutes);
    }

    public function test_a_second_scan_within_the_same_minute_is_not_taken_as_leaving(): void
    {
        $this->afternoonQr();
        $this->scanAt('13:00')->assertOk();

        $this->scanAt('13:00')->assertStatus(422)->assertJsonPath('message', 'You have just timed in. Scan again when your shift ends.');
        $this->assertNull($this->attendanceOf(1)->afternoon_time_out);
    }

    public function test_nobody_can_time_in_for_the_afternoon_after_it_has_ended(): void
    {
        $this->afternoonQr();

        // Fay has today's morning only; Gus has no record at all.
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Technician', 'position' => 'Technician', 'branch_name' => 'Balilihan', 'status' => 'active']);
        foreach ([2, 3] as $employeeId) {
            $this->scanAt('18:00', $employeeId)->assertStatus(422)->assertJson(['success' => false, 'message' => 'The afternoon session has ended, so you can no longer time in. Ask your branch admin to record your attendance.']);
        }
        $this->assertNull($this->attendanceOf(2)->afternoon_time_in);
        $this->assertNull($this->attendanceOf(3), 'no record is made');
    }

    public function test_the_afternoon_code_works_until_the_last_scan_of_the_day(): void
    {
        $this->timedIn();

        $this->scanAt('22:01')->assertStatus(422)->assertJsonPath('message', 'QR code is outside its valid time window.');
        $this->assertNull($this->attendanceOf(1)->afternoon_time_out);
        $this->scanAt('22:00')->assertOk()->assertJson(['overtime_minutes' => 300]);
    }

    public function test_the_last_scan_of_the_day_follows_the_settings(): void
    {
        DB::table('system_settings')->insert(['setting_key' => 'qr_overtime_end', 'setting_value' => '20:00']);
        $this->timedIn();

        $this->scanAt('20:01')->assertStatus(422)->assertJsonPath('message', 'QR code is outside its valid time window.');
        $this->scanAt('19:59')->assertOk()->assertJson(['overtime_minutes' => 179]);
    }

    public function test_an_afternoon_code_made_with_the_earlier_window_also_works_until_the_last_scan(): void
    {
        // Made before this change, when the afternoon code ended at 17:00.
        $this->afternoonQr('17:00:00');
        $this->scanAt('13:00')->assertOk();

        $this->scanAt('18:30')->assertOk()->assertJson(['overtime_minutes' => 90]);
    }

    public function test_the_morning_code_still_ends_with_the_morning(): void
    {
        DB::table('attendance')->delete();
        DB::table('qr_codes')->insert(['qr_code' => 'QR-MORNING', 'qr_type' => 'morning', 'valid_date' => now()->toDateString(), 'valid_from' => '08:00:00', 'valid_until' => '12:00:00', 'status' => 'active']);

        $this->scanAt('12:30', 1, 'QR-MORNING')->assertStatus(422)->assertJsonPath('message', 'QR code is outside its valid time window.');
        $this->assertNull($this->attendanceOf(1));
    }

    public function test_the_overtime_qr_code_of_the_earlier_system_is_not_accepted_and_not_taken_for_an_afternoon_one(): void
    {
        DB::table('qr_codes')->insert(['qr_code' => 'QR-OVERTIME', 'qr_type' => 'overtime', 'valid_date' => now()->toDateString(), 'valid_from' => '17:00:00', 'valid_until' => '22:00:00', 'status' => 'active', 'branch_name' => 'Balilihan']);

        $response = $this->scanAt('17:05', 1, 'QR-OVERTIME');

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('no longer used', $response->json('message'));
        $row = $this->attendanceOf(1);
        $this->assertNull($row->overtime_time_in);
        $this->assertNull($row->afternoon_time_in, 'it was not recorded as an afternoon scan');
    }

    public function test_the_app_sees_the_overtime_of_today_and_is_not_on_duty_any_more(): void
    {
        $this->timedIn();
        $this->getJson('/backend/api/me.php', $this->appHeaders(1))->assertOk()->assertJsonPath('today.on_duty', true);

        $this->scanAt('18:30')->assertOk();

        $this->getJson('/backend/api/me.php', $this->appHeaders(1))
            ->assertOk()
            ->assertJsonPath('today.overtime_time_in', '17:00:00')
            ->assertJsonPath('today.overtime_time_out', '18:30:00')
            ->assertJsonPath('today.overtime_minutes', 90)
            ->assertJsonPath('today.overtime_minimum_minutes', 60)
            ->assertJsonPath('today.on_duty', false);
    }

    public function test_the_minutes_of_a_scanned_overtime_reach_the_payroll(): void
    {
        $this->timedIn();
        $this->scanAt('18:30')->assertOk();

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

    public function test_overtime_needs_its_time_out(): void
    {
        $this->assertSame('Enter the overtime time out.', $this->manual(['manual_overtime_in' => '17:00'])['error']);
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

    // The same rule as the scan: the time after the end of the afternoon is the overtime.

    public function test_a_manual_afternoon_time_out_after_the_afternoon_ends_is_saved_as_overtime(): void
    {
        $page = $this->manual(['manual_morning_in' => '08:00', 'manual_morning_out' => '12:00', 'manual_afternoon_in' => '13:00', 'manual_afternoon_out' => '18:30']);

        $this->assertStringStartsWith('Attendance saved', $page['success']);
        $row = $this->yesterdayRow();
        $this->assertSame('17:00:00', $row->afternoon_time_out, 'the afternoon ends at its usual time');
        $this->assertSame('17:00:00', $row->overtime_time_in);
        $this->assertSame('18:30:00', $row->overtime_time_out);
        $this->assertSame(90, (int) $row->overtime_minutes);
        $this->assertEquals(8.0, (float) $row->total_hours, 'the hours are those of the sessions');
    }

    public function test_a_manual_afternoon_time_out_a_few_minutes_late_is_overtime_minutes_and_not_undertime(): void
    {
        $this->manual(['manual_afternoon_in' => '13:00', 'manual_afternoon_out' => '17:03']);

        $row = $this->yesterdayRow();
        $this->assertSame('17:00:00', $row->afternoon_time_out);
        $this->assertSame(3, (int) $row->overtime_minutes);
    }

    public function test_a_manual_afternoon_time_out_before_the_end_has_no_overtime(): void
    {
        $this->manual(['manual_afternoon_in' => '13:00', 'manual_afternoon_out' => '16:30']);

        $row = $this->yesterdayRow();
        $this->assertSame('16:30:00', $row->afternoon_time_out);
        $this->assertNull($row->overtime_time_in);
        $this->assertSame(0, (int) $row->overtime_minutes);
    }

    public function test_only_the_overtime_out_is_enough_and_the_afternoon_ends_at_its_usual_time(): void
    {
        $page = $this->manual(['manual_afternoon_in' => '13:00', 'manual_overtime_out' => '19:00']);

        $this->assertStringStartsWith('Attendance saved', $page['success']);
        $row = $this->yesterdayRow();
        $this->assertSame('17:00:00', $row->afternoon_time_out, 'no time out was typed for the afternoon');
        $this->assertSame('17:00:00', $row->overtime_time_in, 'the overtime starts when the afternoon ends');
        $this->assertSame('19:00:00', $row->overtime_time_out);
        $this->assertSame(120, (int) $row->overtime_minutes);
    }

    public function test_the_overtime_out_of_a_day_that_was_scanned_in_closes_the_afternoon(): void
    {
        DB::table('attendance')->insert(['employee_id' => 1, 'attendance_date' => now()->subDay()->toDateString(), 'morning_time_in' => '08:00:00', 'afternoon_time_in' => '13:00:00', 'status_morning' => 'present', 'status_afternoon' => 'present', 'qr_code_used' => 'QR-AFTERNOON']);

        $this->manual(['manual_overtime_out' => '18:30']);

        $row = $this->yesterdayRow();
        $this->assertSame('13:00:00', $row->afternoon_time_in, 'the scanned time in is kept');
        $this->assertSame('17:00:00', $row->afternoon_time_out);
        $this->assertSame(90, (int) $row->overtime_minutes);
    }

    public function test_the_overtime_that_is_typed_wins_over_an_afternoon_time_out_after_the_end(): void
    {
        $this->manual(['manual_afternoon_in' => '13:00', 'manual_afternoon_out' => '18:30', 'manual_overtime_in' => '17:30', 'manual_overtime_out' => '19:00']);

        $row = $this->yesterdayRow();
        $this->assertSame('17:00:00', $row->afternoon_time_out);
        $this->assertSame('17:30:00', $row->overtime_time_in);
        $this->assertSame(90, (int) $row->overtime_minutes);
    }

    public function test_no_overtime_takes_it_away_even_with_an_afternoon_time_out_after_the_end(): void
    {
        $this->manual(['manual_afternoon_in' => '13:00', 'manual_afternoon_out' => '18:30', 'manual_overtime_clear' => 1]);

        $row = $this->yesterdayRow();
        $this->assertSame('17:00:00', $row->afternoon_time_out);
        $this->assertNull($row->overtime_time_in);
        $this->assertSame(0, (int) $row->overtime_minutes);
    }

    public function test_an_overtime_out_before_the_afternoon_ends_is_refused_when_no_start_is_typed(): void
    {
        $this->assertSame('Overtime must end after the afternoon ends (5:00 PM).', $this->manual(['manual_afternoon_in' => '13:00', 'manual_overtime_out' => '16:00'])['error']);
        $this->assertNull($this->yesterdayRow());
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

    public function test_the_afternoon_qr_code_is_made_with_the_window_up_to_the_last_scan_of_the_day(): void
    {
        $this->signIn(1);

        $page = $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'afternoon']);

        $this->assertSame('Afternoon QR Code generated successfully!', $page['success']);
        $qr = DB::table('qr_codes')->where('qr_type', 'afternoon')->first();
        $this->assertSame('13:00:00', $qr->valid_from);
        $this->assertSame('22:00:00', $qr->valid_until);
        $this->assertNull($qr->branch_name, 'one code for every branch');
    }

    public function test_the_morning_qr_code_still_ends_with_the_morning(): void
    {
        $this->signIn(1);

        $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'morning']);

        $qr = DB::table('qr_codes')->where('qr_type', 'morning')->first();
        $this->assertSame('08:00:00', $qr->valid_from);
        $this->assertSame('12:00:00', $qr->valid_until);
    }

    public function test_the_afternoon_qr_follows_the_last_scan_of_the_settings(): void
    {
        DB::table('system_settings')->insert(['setting_key' => 'qr_overtime_end', 'setting_value' => '20:00']);
        $this->signIn(1);

        $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'afternoon']);

        $this->assertSame('20:00:00', DB::table('qr_codes')->where('qr_type', 'afternoon')->value('valid_until'));
    }

    public function test_the_afternoon_qr_never_ends_before_the_afternoon(): void
    {
        DB::table('system_settings')->insert(['setting_key' => 'qr_overtime_end', 'setting_value' => '16:00']);
        $this->signIn(1);

        $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'afternoon']);

        $this->assertSame('17:00:00', DB::table('qr_codes')->where('qr_type', 'afternoon')->value('valid_until'), 'a last scan before the end of the afternoon does not shorten it');
    }

    public function test_generating_the_afternoon_qr_again_replaces_the_one_of_the_day(): void
    {
        $this->afternoonQr();
        $this->signIn(1);

        $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'afternoon']);

        $this->assertSame('expired', DB::table('qr_codes')->where('qr_code', 'QR-AFTERNOON')->value('status'));
        $this->assertSame(1, DB::table('qr_codes')->where('qr_type', 'afternoon')->where('status', 'active')->count());
    }

    public function test_a_qr_type_that_is_not_a_session_is_refused(): void
    {
        $this->signIn(1);

        $page = $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => "morning_start' OR '1'='1"]);

        $this->assertSame('Error: Choose the morning or afternoon QR code.', $page['error']);
        $this->assertSame(0, DB::table('qr_codes')->count());
    }

    public function test_there_is_no_overtime_qr_code_to_generate_any_more(): void
    {
        $this->signIn(1);

        $page = $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'overtime']);

        $this->assertSame('Error: Choose the morning or afternoon QR code.', $page['error']);
        $this->assertSame(0, DB::table('qr_codes')->count());
    }

    #[DataProvider('qrTypes')]
    public function test_a_branch_admin_cannot_generate_a_qr_code(string $type): void
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

    public static function qrTypes(): array
    {
        return ['morning' => ['morning'], 'afternoon' => ['afternoon'], 'overtime' => ['overtime']];
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

    public function test_a_day_of_overtime_under_an_hour_is_marked_as_not_paid_in_the_records(): void
    {
        DB::table('attendance')->where('id', 1)->update(['overtime_time_in' => '17:00:00', 'overtime_time_out' => '17:40:00', 'overtime_minutes' => 40]);
        DB::table('attendance')->where('id', 2)->update(['overtime_time_in' => '17:00:00', 'overtime_time_out' => '18:30:00', 'overtime_minutes' => 90]);
        $this->signIn(1);

        $html = $this->get('/employees?tab=attendance')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '>not paid</span>'), 'only the 40 minutes');
    }

    #[DataProvider('adminUsers')]
    public function test_the_attendance_tab_has_no_overtime_qr_and_says_the_afternoon_code_runs_to_the_last_scan(int $user): void
    {
        $this->afternoonQr();
        DB::table('qr_codes')->insert(['qr_code' => 'QR-OLD-OVERTIME', 'qr_type' => 'overtime', 'valid_date' => now()->toDateString(), 'valid_from' => '17:00:00', 'valid_until' => '22:00:00', 'status' => 'active', 'branch_name' => 'Balilihan']);
        $this->signIn($user);

        $html = $this->get('/employees?tab=attendance')->assertOk()->getContent();

        $this->assertStringNotContainsString('Generate Overtime QR', $html);
        $this->assertStringNotContainsString('id="overtimeQR"', $html);
        $this->assertStringNotContainsString('id="printOvertimeQr"', $html);
        $this->assertStringNotContainsString('QR-OLD-OVERTIME', $html, 'a code of the earlier system is not shown');
        $this->assertStringContainsString('Scan out until 10:00 PM. The time after 5:00 PM is overtime.', $html);
        $this->assertStringContainsString('id="afternoonQR"', $html);
    }

    public static function adminUsers(): array
    {
        return ['super admin' => [1], 'branch admin' => [2]];
    }

    #[DataProvider('adminUsers')]
    public function test_the_printed_attendance_sheet_has_the_morning_and_afternoon_codes_only(int $user): void
    {
        DB::table('qr_codes')->insert([
            ['qr_code' => 'QR-MORNING', 'qr_type' => 'morning', 'valid_date' => now()->toDateString(), 'valid_from' => '08:00:00', 'valid_until' => '12:00:00', 'status' => 'active'],
        ]);
        $this->afternoonQr();
        DB::table('qr_codes')->insert(['qr_code' => 'QR-OLD-OVERTIME', 'qr_type' => 'overtime', 'valid_date' => now()->toDateString(), 'valid_from' => '17:00:00', 'valid_until' => '22:00:00', 'status' => 'active', 'branch_name' => 'Balilihan']);
        $this->signIn($user);

        $codes = array_column($this->page('print_attendance')['attendanceQrCodes'], 'qr_code');
        sort($codes);

        $this->assertSame(['QR-AFTERNOON', 'QR-MORNING'], $codes);
    }

    public function test_the_super_admin_sets_the_last_scan_of_the_day(): void
    {
        $this->signIn(1);

        $page = $this->page('settings', [], ['update_settings' => 1, 'settings' => ['qr_overtime_end' => '21:30']]);

        $this->assertSame('System settings updated successfully!', $page['success']);
        $this->assertSame('21:30', DB::table('system_settings')->where('setting_key', 'qr_overtime_end')->value('setting_value'), 'a database without the row gets it');
    }

    public function test_the_last_scan_of_the_day_must_be_a_time_after_the_afternoon_ends(): void
    {
        $this->signIn(1);

        $this->assertSame('Error: Enter the last scan of the day as a valid time.', $this->page('settings', [], ['update_settings' => 1, 'settings' => ['qr_overtime_end' => 'later']])['error']);
        $this->assertSame('Error: The last scan of the day must be after the afternoon QR end time.', $this->page('settings', [], ['update_settings' => 1, 'settings' => ['qr_afternoon_end' => '17:00', 'qr_overtime_end' => '16:00']])['error']);
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
