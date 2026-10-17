<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Employees and Payroll declare helper functions every time the page runs, so each
 * test runs in its own process and calls the page once.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class BranchStaffPagesTest extends TestCase
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

    private function ids(array $rows, string $key = 'id'): array
    {
        $ids = array_map('intval', array_column($rows, $key));
        sort($ids);

        return $ids;
    }

    /** employee 3: an Admin employee of Balilihan with a payroll record (id 3). */
    private function addBalilihanAdminEmployee(): void
    {
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Admin', 'position' => 'Admin', 'branch_name' => 'Balilihan', 'daily_rate' => 600, 'status' => 'active']);
        DB::table('payroll')->insert(['id' => 3, 'employee_id' => 3, 'payroll_period_start' => now()->startOfMonth()->toDateString(), 'payroll_period_end' => now()->endOfMonth()->toDateString(), 'net_pay' => 5000, 'status' => 'processed']);
    }

    private function payrollPost(int $employeeId, array $extra = []): array
    {
        // The week, so it is another period than the month of the payroll records the tests start with.
        return $extra + [
            'employee_id' => $employeeId,
            'payroll_period_start' => now()->startOfWeek()->toDateString(),
            'payroll_period_end' => now()->endOfWeek()->toDateString(),
            'computation_mode' => 'manual',
            'status' => 'draft',
        ];
    }

    // ---- employees --------------------------------------------------------------------------------

    public function test_a_branch_admin_sees_only_their_branchs_employees_and_attendance(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', ['tab' => 'attendance']);

        $this->assertSame([1], $this->ids($page['employees']));
        $this->assertSame([1], $this->ids($page['attendanceRecords'], 'employee_id'));
        $this->assertSame(1, (int) $page['todayAttendanceCount']);
        $this->assertSame(['Balilihan'], $page['employeeBranches']);
        $this->assertSame([1], $this->ids($page['payrollRecords'], 'employee_id'));
        $this->assertFalse($page['canGenerateQr']);
    }

    public function test_the_super_admin_sees_every_branch_and_may_generate_the_qr_code(): void
    {
        $this->signIn(1);
        $page = $this->page('employees', ['tab' => 'attendance']);

        $this->assertSame([1, 2], $this->ids($page['employees']));
        $this->assertSame([1, 2], $this->ids($page['attendanceRecords'], 'employee_id'));
        $this->assertSame(2, (int) $page['todayAttendanceCount']);
        $this->assertCount(19, $page['employeeBranches']);
        $this->assertTrue($page['canGenerateQr']);
    }

    public function test_a_branch_admin_cannot_replace_the_shared_qr_code(): void
    {
        $this->signIn(2);

        try {
            $this->page('employees', ['tab' => 'attendance'], ['generate_qr' => 1, 'qr_type' => 'morning']);
            $this->fail('generating the QR code should be refused');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame(0, DB::table('qr_codes')->count());
        }
    }

    public function test_manual_attendance_cannot_be_recorded_for_another_branchs_employee(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', ['tab' => 'attendance'], [
            'manual_attendance' => 1, 'manual_employee_id' => 2, 'manual_date' => now()->subDay()->toDateString(),
            'manual_morning_in' => '08:00', 'manual_morning_out' => '12:00',
        ]);

        $this->assertSame('Choose an active employee.', $page['error']);
        $this->assertSame(2, DB::table('attendance')->count());
    }

    public function test_manual_attendance_works_for_the_admins_own_employee(): void
    {
        $this->signIn(2);
        $yesterday = now()->subDay()->toDateString();
        $page = $this->page('employees', ['tab' => 'attendance'], [
            'manual_attendance' => 1, 'manual_employee_id' => 1, 'manual_date' => $yesterday,
            'manual_morning_in' => '08:00', 'manual_morning_out' => '12:00',
        ]);

        $this->assertStringStartsWith('Attendance saved for Eli Santos', $page['success']);
        $this->assertSame('Balilihan', DB::table('attendance')->where('employee_id', 1)->where('attendance_date', $yesterday)->value('branch_name'));
    }

    public function test_a_branch_admin_cannot_edit_another_branchs_employee(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'employee_id' => 2, 'first_name' => 'Hacked', 'last_name' => 'Name',
            'position' => 'Technician I', 'branch_name' => 'Balilihan',
        ]);

        $this->assertSame('Error: That employee does not belong to your branch.', $page['error']);
        $this->assertSame('Fay Garcia', DB::table('employees')->where('id', 2)->value('full_name'));
        $this->assertSame('Loon', DB::table('employees')->where('id', 2)->value('branch_name'));
    }

    public function test_a_branch_admin_cannot_add_an_employee_to_another_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'first_name' => 'New', 'last_name' => 'Hire', 'position' => 'Technician I', 'branch_name' => 'Loon',
        ]);

        $this->assertSame('Error: Select the branch this employee belongs to.', $page['error']);
        $this->assertSame(0, DB::table('employees')->where('full_name', 'New Hire')->count());
    }

    public function test_a_branch_admin_can_add_an_employee_to_their_own_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'first_name' => 'New', 'last_name' => 'Hire', 'position' => 'Technician I', 'branch_name' => 'Balilihan',
        ]);

        $this->assertStringStartsWith('Employee added successfully', $page['success']);
        $this->assertSame('Balilihan', DB::table('employees')->where('full_name', 'New Hire')->value('branch_name'));
        $this->assertSame([1, 3], $this->ids($page['employees']), 'the new employee is listed, Loon staff is not');
    }

    public function test_a_branch_admin_is_not_offered_the_admin_position(): void
    {
        $this->signIn(2);
        $this->assertSame(['Technician I', 'Technician II'], $this->page('employees')['selectablePositions']);
    }

    public function test_the_super_admin_is_only_offered_the_admin_position(): void
    {
        $this->signIn(1);
        $this->assertSame(['Admin'], $this->page('employees')['selectablePositions']);
    }

    public function test_the_super_admin_cannot_add_a_technician(): void
    {
        $this->signIn(1);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'first_name' => 'New', 'last_name' => 'Tech', 'position' => 'Technician I', 'branch_name' => 'Loon',
        ]);

        $this->assertSame('Error: The super admin only adds Admin employees. Branch admins add the technicians.', $page['error']);
        $this->assertSame(0, DB::table('employees')->where('full_name', 'New Tech')->count());
    }

    public function test_the_super_admin_can_still_edit_a_technician_without_changing_the_position(): void
    {
        $this->signIn(1);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'employee_id' => 2, 'first_name' => 'Fay', 'last_name' => 'Garcia', 'position' => 'Technician II',
            'branch_name' => 'Loon', 'contact_number' => '09173334444',
        ]);

        $this->assertSame('Employee updated successfully! User accounts are managed in User Accounts.', $page['success']);
        $this->assertSame('09173334444', DB::table('employees')->where('id', 2)->value('contact_number'));
    }

    public function test_the_super_admin_cannot_move_a_technician_to_another_technician_position(): void
    {
        $this->signIn(1);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'employee_id' => 1, 'first_name' => 'Eli', 'last_name' => 'Santos', 'position' => 'Technician II', 'branch_name' => 'Balilihan',
        ]);

        $this->assertSame('Error: The super admin only adds Admin employees. Branch admins add the technicians.', $page['error']);
        $this->assertSame('Technician I', DB::table('employees')->where('id', 1)->value('position'));
    }

    public function test_a_branch_admin_cannot_add_an_admin_employee(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'first_name' => 'Sneaky', 'last_name' => 'Admin', 'position' => 'Admin', 'branch_name' => 'Balilihan',
        ]);

        $this->assertSame('Error: Only the super admin can give an employee the Admin position.', $page['error']);
        $this->assertSame(0, DB::table('employees')->where('full_name', 'Sneaky Admin')->count());
    }

    public function test_a_branch_admin_cannot_promote_an_employee_to_admin(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'employee_id' => 1, 'first_name' => 'Eli', 'last_name' => 'Santos', 'position' => 'Admin', 'branch_name' => 'Balilihan',
        ]);

        $this->assertSame('Error: Only the super admin can give an employee the Admin position.', $page['error']);
        $this->assertSame('Technician I', DB::table('employees')->where('id', 1)->value('position'));
    }

    public function test_a_branch_admin_cannot_edit_an_admin_employee(): void
    {
        DB::table('employees')->where('id', 1)->update(['position' => 'Admin']);
        $this->signIn(2);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'employee_id' => 1, 'first_name' => 'Eli', 'last_name' => 'Santos', 'position' => 'Admin',
            'branch_name' => 'Balilihan', 'contact_number' => '09171112222',
        ]);

        $this->assertSame('Error: Admin employees are managed by the super admin.', $page['error']);
        $this->assertNull(DB::table('employees')->where('id', 1)->value('contact_number'));
    }

    public function test_the_super_admin_can_edit_an_admin_employee(): void
    {
        DB::table('employees')->where('id', 1)->update(['position' => 'Admin']);
        $this->signIn(1);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'employee_id' => 1, 'first_name' => 'Eli', 'last_name' => 'Santos', 'position' => 'Admin',
            'branch_name' => 'Balilihan', 'contact_number' => '09171112222',
        ]);

        $this->assertSame('Employee updated successfully! User accounts are managed in User Accounts.', $page['success']);
        $this->assertSame('09171112222', DB::table('employees')->where('id', 1)->value('contact_number'));
    }

    public function test_the_super_admin_can_give_the_admin_position_and_type_the_branch_in_any_case(): void
    {
        $this->signIn(1);
        $page = $this->page('employees', [], [
            'save_employee' => 1, 'first_name' => 'Gina', 'last_name' => 'Admin', 'position' => 'Admin', 'branch_name' => 'lOOn',
        ]);

        $this->assertStringStartsWith('Employee added successfully', $page['success']);
        $row = DB::table('employees')->where('full_name', 'Gina Admin')->first();
        $this->assertSame('Admin', $row->position);
        $this->assertSame('Loon', $row->branch_name, 'stored with the real branch name');
    }

    /** A Loon employee with no attendance yet, and an open QR code, like the mobile app would find. */
    private function scanFromTheApp(): void
    {
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Tan', 'position' => 'Technician I', 'branch_name' => 'Loon', 'status' => 'active']);
        DB::table('qr_codes')->insert(['qr_code' => 'QR-MORNING', 'qr_type' => 'morning', 'valid_date' => now()->toDateString(), 'valid_from' => '00:00:00', 'valid_until' => '23:59:59', 'status' => 'active']);

        // The scan acts as the employee who signed in (their token), whatever the body says.
        $this->postJson('/backend/api/scan_attendance.php', ['employee_id' => 1, 'qr_code' => 'QR-MORNING'], $this->appHeaders(3))
            ->assertOk()
            ->assertJson(['success' => true, 'branch_name' => 'Loon', 'employee_name' => 'Gus Tan']);
        $this->assertSame('Loon', DB::table('attendance')->where('employee_id', 3)->value('branch_name'), 'the scan is recorded under the employee branch');
    }

    public function test_an_app_scan_shows_up_for_the_employees_own_branch(): void
    {
        $this->scanFromTheApp();
        $this->signIn(3);
        $page = $this->page('employees', ['tab' => 'attendance']);

        $scanned = array_values(array_filter($page['attendanceRecords'], fn ($row) => (int) $row['employee_id'] === 3));
        $this->assertCount(1, $scanned);
        $this->assertNotNull($scanned[0]['attendance_id'], 'the Loon admin sees the scan');
        $this->assertSame('Loon', $scanned[0]['branch_name']);
        $this->assertSame(2, (int) $page['todayAttendanceCount'], 'Fay and Gus');
    }

    public function test_an_app_scan_is_not_visible_to_another_branch(): void
    {
        $this->scanFromTheApp();
        $this->signIn(2);
        $page = $this->page('employees', ['tab' => 'attendance']);

        $this->assertSame([1], $this->ids($page['attendanceRecords'], 'employee_id'), 'Balilihan only lists its own employee');
        $this->assertSame(1, (int) $page['todayAttendanceCount']);
        $this->assertSame([1], $this->ids($page['employees']));
    }

    public function test_the_super_admin_sees_every_branchs_scans(): void
    {
        $this->scanFromTheApp();
        $this->signIn(1);
        $page = $this->page('employees', ['tab' => 'attendance']);

        $this->assertSame([1, 2, 3], $this->ids($page['attendanceRecords'], 'employee_id'));
        $this->assertSame(3, (int) $page['todayAttendanceCount']);
    }

    public function test_attendance_recorded_in_a_branch_stays_there_after_the_employee_moves(): void
    {
        // Fay works in Loon now, but yesterday she scanned in at Balilihan.
        $yesterday = now()->subDay()->toDateString();
        DB::table('attendance')->insert(['id' => 3, 'employee_id' => 2, 'attendance_date' => $yesterday, 'morning_time_in' => '08:10:00', 'status_morning' => 'present', 'branch_name' => 'Balilihan']);

        $this->signIn(2);
        $page = $this->page('employees', ['tab' => 'attendance', 'attendance_date' => $yesterday]);
        $fay = array_values(array_filter($page['attendanceRecords'], fn ($row) => (int) $row['employee_id'] === 2));
        $this->assertCount(1, $fay, 'Balilihan still sees the day recorded in Balilihan');
        $this->assertSame(3, (int) $fay[0]['attendance_id']);
        $this->assertSame('Balilihan', $fay[0]['branch_name']);
    }

    public function test_another_branchs_day_is_not_shown_to_the_branch_the_employee_moved_to(): void
    {
        $yesterday = now()->subDay()->toDateString();
        DB::table('attendance')->insert(['id' => 3, 'employee_id' => 2, 'attendance_date' => $yesterday, 'morning_time_in' => '08:10:00', 'status_morning' => 'present', 'branch_name' => 'Balilihan']);

        $this->signIn(3);
        $page = $this->page('employees', ['tab' => 'attendance', 'attendance_date' => $yesterday]);
        $fay = array_values(array_filter($page['attendanceRecords'], fn ($row) => (int) $row['employee_id'] === 2));
        $this->assertCount(1, $fay, 'Fay is still listed as a Loon employee');
        $this->assertNull($fay[0]['attendance_id'], 'but the Balilihan day is not shown in Loon');
    }

    public function test_a_new_absent_mark_is_recorded_in_the_employees_branch(): void
    {
        $this->signIn(2);
        $day = now()->subDays(3)->toDateString();
        $page = $this->page('employees', [], ['mark_absent' => 1, 'employee_id' => 1, 'session' => 'morning', 'attendance_date' => $day]);

        $this->assertSame('Employee marked as absent for morning session!', $page['success']);
        $row = DB::table('attendance')->where('employee_id', 1)->where('attendance_date', $day)->first();
        $this->assertSame('absent', $row->status_morning);
        $this->assertSame('Balilihan', $row->branch_name);
    }

    public function test_marking_absent_is_limited_to_the_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('employees', [], ['mark_absent' => 1, 'employee_id' => 2, 'session' => 'morning', 'attendance_date' => now()->toDateString()]);

        $this->assertSame('Error: That employee does not belong to your branch.', $page['error']);
        $this->assertSame('present', DB::table('attendance')->where('employee_id', 2)->value('status_morning'));
    }

    public function test_a_branch_admin_cannot_record_attendance_for_an_admin_employee(): void
    {
        DB::table('employees')->where('id', 1)->update(['position' => 'Admin']);
        $yesterday = now()->subDay()->toDateString();
        $this->signIn(2);
        $page = $this->page('employees', [], [
            'manual_attendance' => 1, 'manual_employee_id' => 1, 'manual_date' => $yesterday,
            'manual_morning_in' => '08:00', 'manual_morning_status' => '',
        ]);

        $this->assertSame('Attendance for Admin employees is recorded by the super admin.', $page['error']);
        $this->assertSame(0, DB::table('attendance')->where('employee_id', 1)->where('attendance_date', $yesterday)->count());
    }

    public function test_a_branch_admin_cannot_mark_an_admin_employee_absent(): void
    {
        DB::table('employees')->where('id', 1)->update(['position' => 'Admin']);
        $day = now()->subDay()->toDateString();
        $this->signIn(2);
        $page = $this->page('employees', [], ['mark_absent' => 1, 'employee_id' => 1, 'session' => 'morning', 'attendance_date' => $day]);

        $this->assertSame('Error: Attendance for Admin employees is recorded by the super admin.', $page['error']);
        $this->assertSame(0, DB::table('attendance')->where('employee_id', 1)->where('attendance_date', $day)->count());
    }

    public function test_the_super_admin_can_record_attendance_for_an_admin_employee(): void
    {
        DB::table('employees')->where('id', 1)->update(['position' => 'Admin']);
        $yesterday = now()->subDay()->toDateString();
        $this->signIn(1);
        $page = $this->page('employees', [], [
            'manual_attendance' => 1, 'manual_employee_id' => 1, 'manual_date' => $yesterday,
            'manual_morning_in' => '08:00', 'manual_morning_status' => '',
        ]);

        $this->assertSame('', $page['error'] ?? '');
        $this->assertSame(1, DB::table('attendance')->where('employee_id', 1)->where('attendance_date', $yesterday)->count());
        $this->assertSame('Balilihan', DB::table('attendance')->where('employee_id', 1)->where('attendance_date', $yesterday)->value('branch_name'));
    }

    // ---- payroll ----------------------------------------------------------------------------------

    public function test_payroll_is_limited_to_the_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('payroll');

        $this->assertSame([1], $this->ids($page['payrollRecords']));
        $this->assertSame([1], $this->ids($page['employees']));
        $this->assertSame(1, $page['totalRecords']);
        $this->assertEquals(3000, $page['totalNetPay']);
    }

    public function test_payroll_for_the_super_admin_covers_every_branch(): void
    {
        $this->signIn(1);
        $page = $this->page('payroll');

        $this->assertSame([1, 2], $this->ids($page['payrollRecords']));
        $this->assertSame(2, $page['totalRecords']);
        $this->assertEquals(7000, $page['totalNetPay']);
    }

    public function test_another_branchs_payroll_record_cannot_be_overwritten(): void
    {
        $this->signIn(2);
        $page = $this->page('payroll', [], [
            'payroll_id' => 2, 'employee_id' => 1, 'payroll_period_start' => now()->startOfMonth()->toDateString(),
            'payroll_period_end' => now()->endOfMonth()->toDateString(), 'status' => 'paid',
        ]);

        $this->assertSame('Error: That payroll record does not belong to your branch.', $page['error']);
        $this->assertSame(2, (int) DB::table('payroll')->where('id', 2)->value('employee_id'));
        $this->assertSame('processed', DB::table('payroll')->where('id', 2)->value('status'));
    }

    public function test_another_branchs_payroll_record_cannot_be_archived(): void
    {
        $this->signIn(2);
        $this->page('payroll', ['delete' => 2]);

        $this->assertSame(0, (int) DB::table('payroll')->where('id', 2)->value('archived'));
    }

    public function test_a_branch_admin_can_archive_their_own_payroll_record(): void
    {
        $this->signIn(2);
        $page = $this->page('payroll', ['delete' => 1]);

        $this->assertSame('Payroll record archived successfully.', $page['success']);
        $this->assertSame(1, (int) DB::table('payroll')->where('id', 1)->value('archived'));
    }

    public function test_a_branch_admin_generates_payroll_for_a_technician_of_their_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('payroll', [], $this->payrollPost(1));

        $this->assertSame('Payroll record created successfully.', $page['success']);
        $this->assertSame(2, DB::table('payroll')->where('employee_id', 1)->count());
    }

    public function test_a_branch_admin_cannot_generate_payroll_for_an_admin_employee(): void
    {
        $this->addBalilihanAdminEmployee();
        $this->signIn(2);
        $page = $this->page('payroll', [], $this->payrollPost(3));

        $this->assertSame('Error: Payroll for the Admin employees is generated by the super admin.', $page['error']);
        $this->assertSame(1, DB::table('payroll')->where('employee_id', 3)->count());
    }

    public function test_the_super_admin_cannot_generate_payroll_for_a_technician(): void
    {
        $this->signIn(1);
        $page = $this->page('payroll', [], $this->payrollPost(2));

        $this->assertSame('Error: The super admin generates payroll for the Admin employees only; the branch admins generate it for their technicians.', $page['error']);
        $this->assertSame(1, DB::table('payroll')->where('employee_id', 2)->count());
    }

    public function test_the_super_admin_generates_payroll_for_an_admin_employee_of_any_branch(): void
    {
        $this->addBalilihanAdminEmployee();
        $this->signIn(1);
        $page = $this->page('payroll', [], $this->payrollPost(3));

        $this->assertSame('Payroll record created successfully.', $page['success']);
        $this->assertSame(2, DB::table('payroll')->where('employee_id', 3)->count());
    }

    public function test_the_payroll_form_only_offers_the_employees_the_account_may_pay(): void
    {
        $this->addBalilihanAdminEmployee();
        $this->signIn(2);
        $branchAdminPage = $this->page('payroll');
        $this->assertSame([1], $this->ids($branchAdminPage['payrollEmployees']), 'a branch admin pays the technicians');
        $this->assertSame([1, 3], $this->ids($branchAdminPage['employees']), 'but still sees every employee of the branch');
        $this->assertTrue($branchAdminPage['canGeneratePayroll']);
    }

    public function test_the_super_admin_payroll_form_offers_only_admin_employees(): void
    {
        $this->signIn(1);
        $noAdmins = $this->page('payroll');
        $this->assertSame([], $noAdmins['payrollEmployees']);
        $this->assertFalse($noAdmins['canGeneratePayroll'], 'no Admin employee yet, so there is nobody to pay');
    }

    public function test_payroll_records_say_whether_the_account_may_change_them(): void
    {
        $this->addBalilihanAdminEmployee();
        $this->signIn(1);
        $flags = [];
        foreach ($this->page('payroll')['payrollRecords'] as $record) {
            $flags[(int) $record['id']] = $record['can_manage'];
        }

        $this->assertSame([1 => false, 2 => false, 3 => true], $flags);
    }

    public function test_the_super_admin_cannot_edit_or_archive_a_technicians_payroll(): void
    {
        $this->signIn(1);
        $edit = $this->page('payroll', [], $this->payrollPost(2, ['payroll_id' => 2, 'status' => 'paid']));
        $this->assertSame('Error: The super admin generates payroll for the Admin employees only; the branch admins generate it for their technicians.', $edit['error']);
        $this->assertSame('processed', DB::table('payroll')->where('id', 2)->value('status'));
    }

    public function test_the_super_admin_cannot_archive_a_technicians_payroll(): void
    {
        $this->signIn(1);
        $page = $this->page('payroll', ['delete' => 2]);

        $this->assertStringContainsString('Admin employees only', $page['error']);
        $this->assertSame(0, (int) DB::table('payroll')->where('id', 2)->value('archived'));
    }

    public function test_a_branch_admin_cannot_archive_restore_or_delete_an_admin_employees_payroll(): void
    {
        $this->addBalilihanAdminEmployee();
        $this->signIn(2);
        $page = $this->page('payroll', ['delete' => 3]);
        $this->assertSame('Error: Payroll for the Admin employees is generated by the super admin.', $page['error']);
        $this->assertSame(0, (int) DB::table('payroll')->where('id', 3)->value('archived'));
    }

    public function test_a_branch_admin_cannot_purge_an_admin_employees_archived_payroll(): void
    {
        $this->addBalilihanAdminEmployee();
        DB::table('payroll')->where('id', 3)->update(['archived' => true]);
        $this->signIn(2);
        $page = $this->page('payroll', ['purge' => 3, 'view' => 'archived']);

        $this->assertSame('Error: Payroll for the Admin employees is generated by the super admin.', $page['error']);
        $this->assertSame(1, DB::table('payroll')->where('id', 3)->count());
    }

    public function test_the_super_admin_can_archive_an_admin_employees_payroll(): void
    {
        $this->addBalilihanAdminEmployee();
        $this->signIn(1);
        $page = $this->page('payroll', ['delete' => 3]);

        $this->assertSame('Payroll record archived successfully.', $page['success']);
        $this->assertSame(1, (int) DB::table('payroll')->where('id', 3)->value('archived'));
    }
}
