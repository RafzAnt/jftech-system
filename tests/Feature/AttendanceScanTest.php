<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The morning and afternoon QR codes in the employee app: the first scan of a session is the time in, the next one the time out; the
 * hours of the day are worked out when the time out is recorded, and the history the app shows carries the late and undertime minutes.
 *
 * Seed: employee 1 (Eli Santos, Balilihan), employee 2 (Fay Garcia, Loon); users 2 and 3 are the branch admins.
 */
class AttendanceScanTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::table('attendance')->delete();
        $this->travelTo(now()->setTimeFromTimeString('07:00'));
        foreach (['morning' => ['08:00:00', '12:00:00'], 'afternoon' => ['13:00:00', '17:00:00']] as $type => [$from, $until]) {
            DB::table('qr_codes')->insert(['qr_code' => 'QR-' . strtoupper($type), 'qr_type' => $type, 'valid_date' => now()->toDateString(), 'valid_from' => $from, 'valid_until' => $until, 'status' => 'active']);
        }
    }

    private function scan(string $type, string $at, int $employeeId = 1)
    {
        $this->travelTo(now()->setTimeFromTimeString($at));

        return $this->postJson('/backend/api/scan_attendance.php', ['qr_code' => 'QR-' . strtoupper($type)], $this->appHeaders($employeeId));
    }

    private function today(int $employeeId = 1): ?object
    {
        return DB::table('attendance')->where('employee_id', $employeeId)->whereDate('attendance_date', now()->toDateString())->first();
    }

    public function test_the_morning_scans_are_a_time_in_and_a_time_out_and_the_hours_follow(): void
    {
        $this->scan('morning', '08:05')->assertOk()->assertJson(['success' => true, 'scan_type' => 'Time In', 'status' => 'present']);
        $this->assertSame('08:05:00', $this->today()->morning_time_in);
        $this->assertEquals(0, $this->today()->total_hours);

        $this->scan('morning', '11:58')->assertOk()->assertJson(['success' => true, 'scan_type' => 'Time Out', 'status' => 'present']);
        $this->assertSame('11:58:00', $this->today()->morning_time_out);
        $this->assertEquals(3.88, (float) $this->today()->total_hours, 'the hours are worked out when the session ends');
    }

    public function test_the_first_afternoon_scan_is_a_time_in_not_a_time_out(): void
    {
        $this->scan('afternoon', '13:04')->assertOk()->assertJson(['scan_type' => 'Time In', 'status' => 'present']);
        $this->assertSame('13:04:00', $this->today()->afternoon_time_in);
        $this->assertNull($this->today()->afternoon_time_out);

        $this->scan('afternoon', '16:57')->assertOk()->assertJson(['scan_type' => 'Time Out']);
        $this->assertSame('16:57:00', $this->today()->afternoon_time_out);
    }

    public function test_a_time_out_keeps_the_status_the_time_in_earned(): void
    {
        $this->scan('morning', '08:45')->assertOk()->assertJson(['scan_type' => 'Time In', 'status' => 'late']);
        $this->scan('morning', '11:59')->assertOk()->assertJson(['scan_type' => 'Time Out', 'status' => 'late']);
        $this->assertSame('late', $this->today()->status_morning);
    }

    public function test_a_second_scan_seconds_after_the_first_is_not_taken_as_leaving(): void
    {
        $this->scan('morning', '08:05')->assertOk();
        $this->scan('morning', '08:05')->assertStatus(422)->assertJson(['success' => false]);
        $this->assertNull($this->today()->morning_time_out);
    }

    public function test_the_hours_of_a_whole_day_add_both_sessions(): void
    {
        $this->scan('morning', '08:00');
        $this->scan('morning', '12:00');
        $this->scan('afternoon', '13:00');
        $this->scan('afternoon', '17:00')->assertOk();

        $this->assertEquals(8.0, (float) $this->today()->total_hours);
        $this->assertSame(1, DB::table('attendance')->where('employee_id', 1)->count(), 'one row for the day');
    }

    public function test_the_history_carries_late_and_undertime_and_the_totals(): void
    {
        // Three recent working days (Sunday is the rest day: an absence on it does not count).
        $days = [];
        for ($back = 1; count($days) < 3; $back++) {
            if (! now()->subDays($back)->isSunday()) {
                $days[] = now()->subDays($back)->toDateString();
            }
        }
        $day = fn (int $number) => $days[$number - 1];
        $rows = [
            // a complete day
            ['employee_id' => 1, 'attendance_date' => $day(1), 'morning_time_in' => '08:05:00', 'morning_time_out' => '11:58:00', 'afternoon_time_in' => '13:04:00', 'afternoon_time_out' => '16:57:00', 'status_morning' => 'present', 'status_afternoon' => 'present', 'total_hours' => 7.77, 'branch_name' => 'Balilihan'],
            // late in the morning (48 minutes after 08:00) and left 45 minutes early in the afternoon
            ['employee_id' => 1, 'attendance_date' => $day(2), 'morning_time_in' => '08:48:00', 'morning_time_out' => '11:58:00', 'afternoon_time_in' => '13:04:00', 'afternoon_time_out' => '16:15:00', 'status_morning' => 'late', 'status_afternoon' => 'present', 'total_hours' => 0, 'branch_name' => 'Balilihan'],
            // overtime, and the morning only
            ['employee_id' => 1, 'attendance_date' => $day(3), 'morning_time_in' => '08:00:00', 'morning_time_out' => '12:00:00', 'status_morning' => 'present', 'status_afternoon' => 'absent', 'overtime_time_in' => '17:05:00', 'overtime_time_out' => '18:35:00', 'overtime_minutes' => 90, 'total_hours' => 4, 'branch_name' => 'Balilihan'],
            // somebody else's day
            ['employee_id' => 2, 'attendance_date' => $day(1), 'morning_time_in' => '08:00:00', 'status_morning' => 'present', 'branch_name' => 'Loon'],
        ];
        foreach ($rows as $row) {
            DB::table('attendance')->insert($row);
        }

        $response = $this->getJson('/backend/api/attendance_history.php', $this->appHeaders(1))->assertOk()->assertJson(['success' => true]);
        $rows = collect($response->json('data'))->keyBy('attendance_date');
        $this->assertCount(3, $rows);

        $this->assertSame(0, $rows[$day(1)]['late_minutes']);
        $this->assertSame(0, $rows[$day(1)]['undertime_minutes'], 'two minutes before the end is the whole session');
        $this->assertSame(48, $rows[$day(2)]['late_minutes']);
        $this->assertSame(45, $rows[$day(2)]['undertime_minutes']);
        $this->assertEquals(6.35, $rows[$day(2)]['total_hours'], 'a record that never got its hours is worked out from its times');
        $this->assertSame(90, $rows[$day(3)]['overtime_minutes']);

        $summary = $response->json('summary');
        $this->assertSame(3, $summary['days_recorded']);
        $this->assertSame(48, $summary['late_minutes']);
        $this->assertSame(45, $summary['undertime_minutes']);
        $this->assertSame(90, $summary['overtime_minutes']);
        $this->assertEquals(2.5, $summary['days_worked'], 'half a day for every session with a time in');
        $this->assertSame(1, $summary['absent_sessions']);
    }

    public function test_the_history_says_who_decided_an_item_request(): void
    {
        DB::table('inventory_requests')->insert([
            ['id' => 10, 'employee_id' => 1, 'employee_name' => 'Eli Santos', 'inventory_id' => 1, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'approved', 'approved_by' => 2, 'approved_at' => '2026-10-14 13:18:00', 'created_at' => '2026-10-14 09:24:00', 'updated_at' => now()],
            ['id' => 11, 'employee_id' => 1, 'employee_name' => 'Eli Santos', 'inventory_id' => 1, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'denied', 'approved_by' => 1, 'approved_at' => '2026-10-15 10:00:00', 'created_at' => '2026-10-15 09:00:00', 'updated_at' => now()],
        ]);

        $rows = collect($this->getJson('/backend/api/transactions.php?type=inventory', $this->appHeaders(1))->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('approved', $rows[10]['status']);
        $this->assertSame('2026-10-14 13:18:00', $rows[10]['decided_at']);
        $this->assertSame(DB::table('users')->where('id', 2)->value('full_name'), $rows[10]['decided_by']);
        $this->assertSame('denied', $rows[11]['status']);
        // the request that waits (made by the seed) has no decision to show
        $waiting = collect($rows)->firstWhere('status', 'pending');
        $this->assertNotNull($waiting);
        $this->assertNull($waiting['decided_at']);
        $this->assertNull($waiting['decided_by']);
    }
}
