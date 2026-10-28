<?php

namespace Tests\Feature;

use App\Support\AttendanceTime;
use App\Support\PayrollCalculator;
use App\Support\PayrollService;
use Database\Seeders\BranchFieldDataSeeder;
use Database\Seeders\BranchStaffAttendanceSeeder;
use Database\Seeders\BranchStockNapSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The sample staff, attendance, stock room, NAP boxes, item requests and weekly payroll of the branches (BranchFieldDataSeeder).
 *
 * It is 2026-10-28, a Wednesday, 14:00, so the attendance runs from Monday 2026-09-21 to Wednesday 2026-10-21 (a week before today).
 * Six branches are loaded: Balilihan, Loon and Albur (they have the seed's six clients: two in Balilihan / Boctol, two in Loon / Agsoso and
 * two in Albur / Bahi) and Bilar, Carmen and Clarin, whose overtime weekdays differ. The seed has employees 1 (Balilihan) and 2 (Loon)
 * with a branch admin each (users 2 and 3) and the super admin (user 1).
 */
class BranchFieldDataSeederTest extends TestCase
{
    use BranchTestData;

    private const LAST_DAY = '2026-10-21';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        $this->travelTo('2026-10-28 14:00:00');

        $branches = ['Balilihan', 'Loon', 'Albur', 'Bilar', 'Carmen', 'Clarin'];
        config([
            'bohol_branches' => array_intersect_key(config('bohol_branches'), array_flip($branches)),
            'bohol_barangays' => array_intersect_key(config('bohol_barangays'), array_flip($branches)),
        ]);

        DB::table('attendance')->delete();
        DB::table('inventory_requests')->delete();
        DB::table('inventory_stock_movements')->delete();
        DB::table('inventory')->delete();
        DB::table('clients')->delete();
        DB::table('payroll')->delete();
        DB::table('employees')->update(['date_hired' => '2026-01-05', 'position' => 'Technician I']);

        $clients = [['Balilihan', 'Boctol'], ['Balilihan', 'Boctol'], ['Loon', 'Agsoso'], ['Loon', 'Agsoso'], ['Albur', 'Bahi'], ['Albur', 'Bahi']];
        foreach ($clients as $index => [$branch, $barangay]) {
            DB::table('clients')->insert([
                'client_code' => 'JFT-DEMO-' . ($index + 1), 'full_name' => 'Sample Client ' . ($index + 1), 'branch_name' => $branch,
                'registered_barangay_name' => $barangay, 'installation_date' => '2026-10-' . sprintf('%02d', 5 + $index * 3), 'status' => 'active', 'archived' => false,
            ]);
        }
    }

    private function seedAll(): void
    {
        $this->seed(BranchFieldDataSeeder::class);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = [];
        foreach (['users', 'employees', 'attendance', 'qr_codes', 'inventory', 'inventory_stock_movements', 'nap_boxes', 'nap_ports', 'inventory_requests', 'payroll'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    // ---- staff ---------------------------------------------------------------------------------

    public function test_every_barangay_has_a_technician_with_an_app_account(): void
    {
        $this->seedAll();

        foreach (config('bohol_barangays') as $branch => $barangays) {
            foreach ($barangays as $barangay) {
                $employee = DB::table('employees')->where('branch_name', $branch)->where('address', "{$barangay}, {$branch}, Bohol")->first();
                $this->assertNotNull($employee, "{$branch} / {$barangay} has a technician");
                $this->assertStringStartsWith('Technician', $employee->position);
                $this->assertSame('active', $employee->status);
                $this->assertGreaterThan(0, (float) $employee->daily_rate);

                $user = DB::table('users')->where('id', $employee->user_id)->first();
                $this->assertNotNull($user, "{$employee->full_name} has an account");
                $this->assertSame('employee', $user->role);
                $this->assertSame($branch, $user->branch_name);
                $this->assertSame('active', $user->status);
                $this->assertSame($employee->full_name, $user->full_name);
            }
        }
        $this->assertCount(DB::table('employees')->count(), DB::table('employees')->pluck('full_name')->unique());
        $this->assertCount(DB::table('users')->count(), DB::table('users')->pluck('username')->unique());
        $this->assertCount(DB::table('employees')->count(), DB::table('employees')->pluck('employee_code')->unique());
    }

    public function test_the_accounts_sign_in_to_the_app_with_the_demo_password(): void
    {
        $this->seedAll();

        foreach (['Bilar', 'Carmen', 'Clarin'] as $branch) {
            $employee = DB::table('employees')->where('branch_name', $branch)->where('employee_code', 'like', 'EMP-DEMO-%')->orderBy('id')->first();
            $username = DB::table('users')->where('id', $employee->user_id)->value('username');

            $this->postJson('/backend/api/employee_login.php', ['username' => $username, 'password' => BranchStaffAttendanceSeeder::DEFAULT_PASSWORD])
                ->assertOk()->assertJson(['success' => true, 'user' => ['employee_id' => $employee->id, 'branch_name' => $branch]]);
        }
        $this->postJson('/backend/api/employee_login.php', ['username' => $username, 'password' => 'not-the-password'])->assertStatus(401);
    }

    // ---- attendance ----------------------------------------------------------------------------

    public function test_every_barangay_has_attendance_with_overtime_and_undertime_in_every_branch(): void
    {
        $this->seedAll();
        $schedule = AttendanceTime::schedule();
        $kinds = [];

        $rows = DB::table('attendance as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->get(['a.*', 'e.branch_name as employee_branch', 'e.address']);
        foreach ($rows as $row) {
            $figures = AttendanceTime::dayFigures($row, $schedule);
            $branch = $row->employee_branch;
            $barangay = trim(explode(',', (string) $row->address)[0]);
            $kinds[$branch]['all'][$barangay] = true;
            if ($row->morning_time_in && $row->afternoon_time_in && $row->status_morning === 'present' && $row->status_afternoon === 'present' && $figures['undertime_minutes'] === 0) {
                $kinds[$branch]['complete'] = true;
            }
            foreach (['late' => $figures['late_minutes'], 'undertime' => $figures['undertime_minutes'], 'overtime' => (int) $row->overtime_minutes] as $kind => $minutes) {
                if ($minutes > 0) {
                    $kinds[$branch][$kind] = true;
                }
            }
        }

        foreach (config('bohol_barangays') as $branch => $barangays) {
            foreach (['complete', 'late', 'undertime', 'overtime'] as $kind) {
                $this->assertTrue($kinds[$branch][$kind] ?? false, "{$branch} has a {$kind} day");
            }
            foreach ($barangays as $barangay) {
                $this->assertTrue($kinds[$branch]['all'][$barangay] ?? false, "{$branch} / {$barangay} has attendance");
            }
        }
    }

    public function test_every_employee_has_a_late_day_an_undertime_day_and_an_overtime_day(): void
    {
        $this->seedAll();
        $schedule = AttendanceTime::schedule();

        foreach (DB::table('employees')->get() as $employee) {
            $has = ['late' => false, 'undertime' => false, 'overtime' => false];
            foreach (DB::table('attendance')->where('employee_id', $employee->id)->get() as $row) {
                $figures = AttendanceTime::dayFigures($row, $schedule);
                $has['late'] = $has['late'] || $figures['late_minutes'] > 0;
                $has['undertime'] = $has['undertime'] || $figures['undertime_minutes'] > 0;
                $has['overtime'] = $has['overtime'] || $row->overtime_minutes > 0;
            }
            $this->assertSame(['late' => true, 'undertime' => true, 'overtime' => true], $has, "{$employee->full_name} ({$employee->employee_code})");
        }
    }

    public function test_the_attendance_ends_a_week_before_today(): void
    {
        $this->seedAll();

        $this->assertSame('2026-09-21', substr((string) DB::table('attendance')->min('attendance_date'), 0, 10), 'it starts on a Monday');
        $this->assertSame(self::LAST_DAY, substr((string) DB::table('attendance')->max('attendance_date'), 0, 10));
        $this->assertSame(0, DB::table('attendance')->whereDate('attendance_date', '>', self::LAST_DAY)->count());
        $this->assertSame(0, DB::table('attendance')->whereDate('attendance_date', '2026-10-28')->count(), 'nothing is recorded for today');
        $this->assertSame(0, DB::table('qr_codes')->whereDate('valid_date', '>', self::LAST_DAY)->count(), 'no code for the days after it');
    }

    public function test_every_time_is_one_the_qr_code_would_have_accepted(): void
    {
        $this->seedAll();
        $threshold = 30;

        foreach (DB::table('attendance')->get() as $row) {
            $date = substr((string) $row->attendance_date, 0, 10);
            $this->assertFalse(\Carbon\Carbon::parse($date)->isSunday(), "{$date} is a rest day");
            $this->assertLessThanOrEqual(self::LAST_DAY, $date);

            foreach (['morning' => ['08:00:00', '12:00:00'], 'afternoon' => ['13:00:00', '17:00:00']] as $part => [$start, $end]) {
                $in = $row->{"{$part}_time_in"};
                $out = $row->{"{$part}_time_out"};
                if ($in) {
                    $this->assertGreaterThanOrEqual($start, $in, "{$part} in on {$date}");
                    $this->assertLessThanOrEqual($end, $in);
                    $late = $in > date('H:i:s', strtotime($start) + $threshold * 60);
                    $this->assertSame($late ? 'late' : 'present', $row->{"status_{$part}"}, "{$part} status of {$in}");
                }
                if ($out) {
                    $this->assertNotNull($in, 'a time out has a time in');
                    $this->assertGreaterThan($in, $out);
                    $this->assertLessThanOrEqual($end, $out, "{$part} out on {$date}");
                }
            }
            if ($row->overtime_time_in) {
                $this->assertGreaterThanOrEqual('17:00:00', $row->overtime_time_in);
                $this->assertLessThanOrEqual('22:00:00', $row->overtime_time_out);
                $this->assertSame(AttendanceTime::minutesBetween($row->overtime_time_in, $row->overtime_time_out), (int) $row->overtime_minutes);
            } else {
                $this->assertSame(0, (int) $row->overtime_minutes);
            }
            $this->assertEqualsWithDelta(AttendanceTime::totalHours($row), (float) $row->total_hours, 0.001, "hours on {$date}");
        }
    }

    public function test_the_overtime_belongs_to_the_branch_and_the_days_it_approved(): void
    {
        $this->seedAll();

        $overtimeCodes = DB::table('qr_codes')->where('qr_type', 'overtime')->get();
        $this->assertGreaterThan(0, $overtimeCodes->count());
        foreach ($overtimeCodes as $code) {
            $this->assertNotNull($code->branch_name, 'an overtime code belongs to one branch');
            $this->assertSame('17:00:00', $code->valid_from);
        }

        $usual = [];
        foreach (DB::table('attendance as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.overtime_minutes', '>', 0)->get(['a.attendance_date', 'a.qr_code_used', 'e.branch_name']) as $row) {
            $code = DB::table('qr_codes')->where('qr_code', $row->qr_code_used)->first();
            $this->assertNotNull($code, 'the record points at a code that exists');
            $this->assertSame('overtime', $code->qr_type);
            $this->assertSame($row->branch_name, $code->branch_name, 'the code of the employee\'s own branch');
            $this->assertSame(substr($row->attendance_date, 0, 10), substr($code->valid_date, 0, 10));
            $usual[$row->branch_name][] = \Carbon\Carbon::parse($row->attendance_date)->dayOfWeekIso;
        }
        // Branches do not all work late on the same weekday.
        $mostCommon = collect($usual)->map(fn (array $days) => collect($days)->countBy()->sortDesc()->keys()->first())->unique();
        $this->assertGreaterThan(1, $mostCommon->count());
    }

    public function test_the_morning_and_afternoon_codes_are_shared_and_one_per_day(): void
    {
        $this->seedAll();

        foreach (['morning', 'afternoon'] as $type) {
            $codes = DB::table('qr_codes')->where('qr_type', $type)->get();
            $this->assertCount($codes->count(), $codes->pluck('valid_date')->map(fn ($date) => substr($date, 0, 10))->unique(), "one {$type} code per day");
            $this->assertNull($codes->whereNotNull('branch_name')->first());
        }
        $this->assertSame(1, DB::table('qr_codes')->where('qr_type', 'morning')->whereDate('valid_date', self::LAST_DAY)->count());
    }

    public function test_a_record_the_admin_entered_by_hand_is_left_alone_and_the_last_run_after_the_window_is_removed(): void
    {
        DB::table('attendance')->insert(['employee_id' => 1, 'attendance_date' => '2026-10-20', 'morning_time_in' => '08:10:00', 'morning_time_out' => '12:00:00', 'status_morning' => 'present', 'qr_code_used' => 'manual', 'notes' => 'Manual entry by admin', 'total_hours' => 3.83, 'branch_name' => 'Balilihan']);
        DB::table('attendance')->insert(['employee_id' => 1, 'attendance_date' => '2026-10-19', 'status_morning' => 'absent', 'status_afternoon' => 'absent', 'branch_name' => 'Balilihan']);

        // What the first version of this seeder left for the days up to today: a record that points at a code it generated, and the code.
        $generated = 'JFTECH-MORNING-20261027-' . substr(md5('morning2026-10-27'), 0, 8);
        DB::table('qr_codes')->insert(['qr_code' => $generated, 'qr_type' => 'morning', 'valid_date' => '2026-10-27', 'valid_from' => '08:00:00', 'valid_until' => '12:00:00', 'status' => 'active']);
        DB::table('attendance')->insert(['employee_id' => 1, 'attendance_date' => '2026-10-27', 'morning_time_in' => '08:05:00', 'morning_time_out' => '11:58:00', 'status_morning' => 'present', 'qr_code_used' => $generated, 'total_hours' => 3.88, 'branch_name' => 'Balilihan']);
        // A real scan of a code the super admin made is not touched, and neither is that code.
        DB::table('qr_codes')->insert(['qr_code' => 'JFTECH-MORNING-20261027-realcode', 'qr_type' => 'morning', 'valid_date' => '2026-10-27', 'valid_from' => '08:00:00', 'valid_until' => '12:00:00', 'status' => 'active']);
        DB::table('attendance')->insert(['employee_id' => 2, 'attendance_date' => '2026-10-27', 'morning_time_in' => '08:01:00', 'status_morning' => 'present', 'qr_code_used' => 'JFTECH-MORNING-20261027-realcode', 'branch_name' => 'Loon']);

        $this->seedAll();

        $kept = DB::table('attendance')->where('employee_id', 1)->whereDate('attendance_date', '2026-10-20')->get();
        $this->assertCount(1, $kept);
        $this->assertSame('manual', $kept[0]->qr_code_used);
        $this->assertNull($kept[0]->afternoon_time_in, 'nothing was added to the hand-made day');

        $filled = DB::table('attendance')->where('employee_id', 1)->whereDate('attendance_date', '2026-10-19')->get();
        $this->assertCount(1, $filled, 'the empty absent mark was filled in, not doubled');
        $this->assertNotNull($filled[0]->morning_time_in);

        $this->assertSame(0, DB::table('attendance')->where('employee_id', 1)->whereDate('attendance_date', '2026-10-27')->count(), 'the generated record after the window is gone');
        $this->assertSame(0, DB::table('qr_codes')->where('qr_code', $generated)->count(), 'and so is its code');
        $this->assertSame(1, DB::table('attendance')->where('employee_id', 2)->whereDate('attendance_date', '2026-10-27')->count(), 'a real scan stays');
        $this->assertSame(1, DB::table('qr_codes')->where('qr_code', 'JFTECH-MORNING-20261027-realcode')->count());
    }

    // ---- stock and NAP boxes -------------------------------------------------------------------

    public function test_every_barangay_has_a_nap_box_with_its_clients_on_the_ports(): void
    {
        $this->seedAll();

        $barangays = array_sum(array_map('count', config('bohol_barangays')));
        $this->assertSame($barangays, DB::table('nap_boxes')->count());
        $this->assertCount($barangays, DB::table('nap_boxes')->get()->map(fn ($box) => $box->branch_name . '|' . $box->barangay_name)->unique());

        foreach (DB::table('nap_boxes')->get() as $box) {
            $this->assertContains((int) $box->total_ports, [8, 16]);
            $this->assertSame((int) $box->total_ports, DB::table('nap_ports')->where('nap_box_id', $box->id)->count());
            $this->assertEqualsWithDelta(9.85, (float) $box->latitude, 0.5, 'a place in Bohol');
            $this->assertEqualsWithDelta(124.1, (float) $box->longitude, 0.7, 'a place in Bohol');
            $this->assertNotEmpty($box->location);
            $this->assertLessThanOrEqual(2, DB::table('nap_ports')->where('nap_box_id', $box->id)->where('status', 'faulty')->count());

            $clients = DB::table('clients')->where('branch_name', $box->branch_name)->where('registered_barangay_name', $box->barangay_name)->pluck('id')->all();
            $plugged = DB::table('nap_ports')->where('nap_box_id', $box->id)->where('status', 'occupied')->pluck('client_id')->all();
            $this->assertEqualsCanonicalizing($clients, $plugged, "{$box->name}: the clients of {$box->barangay_name} are on its ports");
        }
        foreach (DB::table('nap_ports')->where('status', '!=', 'occupied')->get() as $port) {
            $this->assertNull($port->client_id);
        }
        foreach (DB::table('clients')->get() as $client) {
            $this->assertNotNull($client->latitude, 'a client has a place near its box');
            $this->assertSame(1, DB::table('nap_ports')->where('client_id', $client->id)->count(), 'a client is on one port');
        }
    }

    public function test_the_nap_boxes_came_out_of_the_stock_and_the_stock_history_adds_up(): void
    {
        $this->seedAll();

        foreach (array_keys(config('bohol_branches')) as $branch) {
            $boxes = DB::table('nap_boxes')->where('branch_name', $branch)->count();
            $item = DB::table('inventory')->where('branch_name', $branch)->where('category', 'NAP Box')->first();
            $this->assertNotNull($item, "{$branch} has NAP boxes in stock");
            $this->assertSame($boxes, DB::table('inventory_stock_movements')->where('inventory_id', $item->id)->where('movement_type', 'nap_installed')->count());
            $this->assertGreaterThanOrEqual(4, $item->quantity, 'some spare boxes are left on the shelf');
            $this->assertLessThanOrEqual(9, $item->quantity);
        }

        // Every item: the history in order is a running balance that ends at the quantity on the shelf (a restock keeps it from going below 0).
        foreach (DB::table('inventory')->get() as $item) {
            $balance = 0;
            foreach (DB::table('inventory_stock_movements')->where('inventory_id', $item->id)->orderBy('created_at')->orderBy('id')->get() as $movement) {
                $balance += $movement->quantity_change;
                $this->assertSame($balance, (int) $movement->balance_after, "{$item->item_name} ({$item->branch_name}) history");
                $this->assertGreaterThanOrEqual(0, $balance);
            }
            $this->assertSame($balance, (int) $item->quantity, "{$item->item_name} ({$item->branch_name}) ends at what is on the shelf");
        }
    }

    // ---- requests ------------------------------------------------------------------------------

    public function test_the_technicians_have_requests_the_app_shows_with_their_decisions(): void
    {
        $this->seedAll();

        $statuses = DB::table('inventory_requests')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all();
        foreach (['approved', 'pending', 'denied'] as $status) {
            $this->assertGreaterThan(0, $statuses[$status] ?? 0, "some requests are {$status}");
        }
        $this->assertGreaterThan($statuses['pending'], $statuses['approved'], 'most are decided');
        $this->assertLessThan(DB::table('employees')->count(), DB::table('inventory_requests')->distinct()->count('employee_id') + 1, 'not every technician asks for items');

        foreach (DB::table('inventory_requests')->get() as $request) {
            $this->assertNotSame('NAP Box', DB::table('inventory')->where('id', $request->inventory_id)->value('category'));
            $this->assertSame(
                DB::table('employees')->where('id', $request->employee_id)->value('branch_name'),
                DB::table('inventory')->where('id', $request->inventory_id)->value('branch_name'),
                'a request is for the stock of the employee\'s own branch',
            );
            if ($request->status === 'pending') {
                $this->assertNull($request->approved_by);
                $this->assertNull($request->approved_at);
            } else {
                $this->assertNotNull($request->approved_by);
                $this->assertGreaterThanOrEqual($request->created_at, $request->approved_at);
            }
        }
        // Nobody returns more than they were given, and waiting requests never ask for more than the shelf holds.
        $held = [];
        foreach (DB::table('inventory_requests')->where('status', 'approved')->orderBy('created_at')->orderBy('id')->get() as $request) {
            $key = $request->employee_id . '|' . $request->item_name;
            $held[$key] = ($held[$key] ?? 0) + ($request->request_type === 'return' ? -1 : 1) * $request->quantity;
            $this->assertGreaterThanOrEqual(0, $held[$key], "{$request->employee_name} returned only what was taken out");
        }
        foreach (DB::table('inventory')->get() as $item) {
            $this->assertLessThanOrEqual((int) $item->quantity, (int) DB::table('inventory_requests')->where('inventory_id', $item->id)->where('status', 'pending')->where('request_type', 'out')->sum('quantity'));
        }

        $employeeId = (int) DB::table('inventory_requests')->where('status', 'approved')->value('employee_id');
        $mine = $this->getJson('/backend/api/transactions.php?type=inventory', $this->appHeaders($employeeId))->assertOk()->json('data');
        $this->assertCount(DB::table('inventory_requests')->where('employee_id', $employeeId)->count(), $mine);
        $decided = collect($mine)->firstWhere('status', 'approved');
        $this->assertNotNull($decided['decided_at']);
        $this->assertNotNull($decided['decided_by']);

        $history = $this->getJson('/backend/api/attendance_history.php', $this->appHeaders($employeeId))->assertOk()->json();
        $this->assertGreaterThan(15, count($history['data']));
        $this->assertGreaterThan(0, $history['summary']['days_worked']);
        $this->assertGreaterThan(0, $history['summary']['undertime_minutes']);
        $this->assertGreaterThan(0, $history['summary']['overtime_minutes']);
    }

    // ---- payroll -------------------------------------------------------------------------------

    public function test_every_complete_week_has_a_payroll_for_each_employee_with_its_deductions(): void
    {
        $this->seedAll();

        $weeks = DB::table('payroll')->select('payroll_period_start', 'payroll_period_end')->distinct()->orderBy('payroll_period_start')->get();
        $this->assertSame(
            [['2026-09-21', '2026-09-27'], ['2026-09-28', '2026-10-04'], ['2026-10-05', '2026-10-11'], ['2026-10-12', '2026-10-18']],
            $weeks->map(fn ($week) => [substr($week->payroll_period_start, 0, 10), substr($week->payroll_period_end, 0, 10)])->all(),
            'the weeks that are complete inside the attendance; Monday 2026-10-19 to Sunday 2026-10-25 is not',
        );
        $this->assertSame(0, DB::table('payroll')->where('payroll_period_start', '2026-10-19')->count());
        $this->assertSame(['paid', 'processed'], DB::table('payroll')->distinct()->orderBy('status')->pluck('status')->all());
        $this->assertSame(0, DB::table('payroll')->where('payroll_period_start', '2026-10-12')->where('status', 'paid')->count(), 'the latest week is still to be paid');

        $service = app(PayrollService::class);
        $withLate = $withUndertime = $withAbsent = $withOvertime = 0;
        foreach (DB::table('payroll')->get() as $payroll) {
            $start = substr($payroll->payroll_period_start, 0, 10);
            $end = substr($payroll->payroll_period_end, 0, 10);
            $this->assertSame('auto', $payroll->computation_mode);
            $this->assertEquals(0, $payroll->archived);

            // The payslip is what the attendance of that week says.
            $attendance = $service->attendance((int) $payroll->employee_id, $start, $end);
            $this->assertEquals($attendance['days_worked'], (float) $payroll->total_days_worked);
            $this->assertSame($attendance['late_minutes'], (int) $payroll->late_minutes);
            $this->assertSame($attendance['undertime_minutes'], (int) $payroll->undertime_minutes);
            $this->assertEqualsWithDelta(round($attendance['overtime_minutes'] / 60, 2), (float) $payroll->overtime_hours, 0.011);

            // Its deductions: late and undertime by the minute, and the three contributions.
            $rate = (float) $payroll->daily_rate;
            $this->assertEqualsWithDelta(PayrollCalculator::minutesPay($rate, $attendance['late_minutes']), (float) $payroll->late, 0.005);
            $this->assertEqualsWithDelta(PayrollCalculator::minutesPay($rate, $attendance['undertime_minutes']), (float) $payroll->undertime, 0.005);
            $this->assertGreaterThan(0, (float) $payroll->sss);
            $this->assertGreaterThan(0, (float) $payroll->philhealth);
            $this->assertGreaterThan(0, (float) $payroll->pagibig);
            $this->assertEqualsWithDelta((float) $payroll->late + (float) $payroll->undertime + (float) $payroll->sss + (float) $payroll->philhealth + (float) $payroll->pagibig, (float) $payroll->deductions, 0.011);
            $this->assertEqualsWithDelta(max(0, (float) $payroll->gross_pay - (float) $payroll->deductions), (float) $payroll->net_pay, 0.011);

            $withLate += (float) $payroll->late > 0 ? 1 : 0;
            $withUndertime += (float) $payroll->undertime > 0 ? 1 : 0;
            $withAbsent += (float) $payroll->absent > 0 ? 1 : 0;
            $withOvertime += (float) $payroll->overtime_pay > 0 ? 1 : 0;
        }
        foreach (['late' => $withLate, 'undertime' => $withUndertime, 'absent' => $withAbsent, 'overtime' => $withOvertime] as $kind => $count) {
            $this->assertGreaterThan(0, $count, "some payslips have {$kind}");
        }

        // One payroll per employee and week; everyone who worked that week has one.
        $this->assertSame(0, DB::table('payroll')->selectRaw('employee_id, payroll_period_start, COUNT(*) AS n')->groupBy('employee_id', 'payroll_period_start')->havingRaw('COUNT(*) > 1')->get()->count());
        $worked = DB::table('attendance')->whereBetween('attendance_date', ['2026-09-28', '2026-10-04'])->where(fn ($q) => $q->whereNotNull('morning_time_in')->orWhereNotNull('afternoon_time_in'))->distinct()->count('employee_id');
        $this->assertSame($worked, DB::table('payroll')->where('payroll_period_start', '2026-09-28')->count());
    }

    // ---- running it again ----------------------------------------------------------------------

    public function test_running_everything_again_changes_nothing(): void
    {
        $this->seedAll();
        $before = $this->counts();
        $attendance = DB::table('attendance')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $payroll = DB::table('payroll')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->seedAll();

        $this->assertSame($before, $this->counts());
        $this->assertSame($attendance, DB::table('attendance')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($payroll, DB::table('payroll')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
    }

    public function test_the_stock_and_boxes_can_be_loaded_without_the_staff(): void
    {
        $this->seed(BranchStockNapSeeder::class);

        $this->assertSame(array_sum(array_map('count', config('bohol_barangays'))), DB::table('nap_boxes')->count());
        $this->assertSame(0, DB::table('inventory_requests')->count());
    }
}
