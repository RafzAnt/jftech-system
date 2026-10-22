<?php

namespace Tests\Feature;

use App\Support\AttendanceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Lists show the newest record first.
 *
 * Seed: employees 1 (Balilihan) and 2 (Loon), both scanned in this morning (08:00 and 08:05); users 1 super admin, 2 Balilihan admin.
 */
// The page actions declare functions, so each test runs in its own process (like the other page tests).
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class LatestFirstTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
    }

    private function page(string $page, array $query = []): array
    {
        $request = Request::create('/' . $page . ($query ? '?' . http_build_query($query) : ''), 'GET');
        $request->setLaravelSession(app('session')->driver('array'));
        app()->instance('request', $request);

        $action = require app_path("Support/PageActions/{$page}.php");

        return $action($request);
    }

    public function test_attendance_puts_the_latest_scan_of_the_latest_day_first_and_unscanned_employees_last(): void
    {
        $rows = AttendanceOrder::latestFirst([
            ['employee_id' => 1, 'attendance_id' => null, 'attendance_date' => null],
            ['employee_id' => 2, 'attendance_id' => 10, 'attendance_date' => '2026-10-21', 'morning_time_in' => '11:00:00'],
            ['employee_id' => 3, 'attendance_id' => 11, 'attendance_date' => '2026-10-22', 'morning_time_in' => '08:00:00'],
            ['employee_id' => 4, 'attendance_id' => 12, 'attendance_date' => '2026-10-22', 'morning_time_in' => '08:05:00', 'afternoon_time_in' => '13:30:00'],
            ['employee_id' => 5, 'attendance_id' => 13, 'attendance_date' => '2026-10-22', 'morning_time_in' => '09:21:00'],
            ['employee_id' => 6, 'attendance_id' => null, 'attendance_date' => null],
        ]);

        $this->assertSame([4, 5, 3, 2, 1, 6], array_column($rows, 'employee_id'), 'a later scan of the day wins, then the earlier day, then those without a scan in their original order');
    }

    public function test_the_attendance_table_shows_the_newest_scan_on_top(): void
    {
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Troy Late', 'position' => 'Technician I', 'branch_name' => 'Balilihan', 'daily_rate' => 600, 'status' => 'active']);
        DB::table('attendance')->insert(['id' => 9, 'employee_id' => 3, 'attendance_date' => now()->toDateString(), 'morning_time_in' => '09:21:00', 'status_morning' => 'late', 'branch_name' => 'Balilihan']);
        $this->signIn(1);

        $page = $this->page('employees', ['tab' => 'attendance']);

        $this->assertSame([3, 2, 1], array_map('intval', array_column($page['attendanceRecords'], 'employee_id')));
    }

    public function test_the_employee_list_shows_the_newest_hire_first_but_the_dropdowns_stay_in_name_order(): void
    {
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Aaron Newest', 'position' => 'Technician I', 'branch_name' => 'Balilihan', 'daily_rate' => 600, 'status' => 'active', 'created_at' => now()]);
        $this->signIn(1);

        $page = $this->page('employees');

        $this->assertSame([3, 2, 1], array_map('intval', array_column($page['listedEmployees'], 'id')));
        $this->assertSame('Aaron Newest', $page['employees'][0]['full_name'], 'the dropdowns are alphabetical');
    }
}
