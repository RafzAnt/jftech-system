<?php

namespace Database\Seeders;

use App\Support\AttendanceTime;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A technician with an app account in every barangay of every branch, and a month of attendance for every active employee:
 * complete days, late arrivals, early leaving (undertime), overtime on the days each branch approves it, half days and absences.
 *
 * - Every barangay gets a technician (the barangays that already have one from an earlier run keep it). Each has a user account
 *   (role employee, branch set) so they can sign in to the employee app; the employee code is EMP-DEMO-<BRANCH>-<n> and the
 *   address names the barangay, so searching a barangay on the Employees page finds who works there. They all share one demo
 *   password: DEMO_STAFF_PASSWORD in .env, or Jftech@2026.
 * - Attendance covers the weeks up to one week before today (see {@see window()}), Monday to Saturday (Sunday is the rest day), in the
 *   form the app and the admin write it: times that fall inside the QR windows, a status per session, the hours, and the QR code of the
 *   day. Nothing is recorded for the last week, so the dates are never today's. Every employee has at least one late day, one
 *   undertime day and one overtime day in it. A day that already has times, or that the admin entered by hand, is left alone; a record
 *   that only holds the automatic "absent" marks is filled in; a record this seeder made earlier after the window is removed.
 * - Each branch has its own overtime weekday and its own overtime QR codes (an overtime QR belongs to one branch).
 *
 *   php artisan db:seed --class=BranchStaffAttendanceSeeder
 *
 * Running it again changes nothing. To remove the staff: delete the employees whose code starts with EMP-DEMO- (their attendance,
 * requests and tokens go with them) and then their user accounts.
 */
class BranchStaffAttendanceSeeder extends Seeder
{
    /** The attendance ends this many days before today. */
    public const END_DAYS_AGO = 7;

    /** About how many days it reaches back from its end (it starts on the Monday of that week). */
    public const DAYS = 30;

    public const DEFAULT_PASSWORD = 'Jftech@2026';

    private const FIRST_NAMES = [
        'Rodel', 'Jerome', 'Mark', 'Kevin', 'Ryan', 'Jayson', 'Arnel', 'Reynaldo', 'Joseph', 'Michael',
        'Dennis', 'Allan', 'Noel', 'Ronnie', 'Bryan', 'Christian', 'Jomar', 'Lloyd', 'Rey', 'Eddie',
        'Ferdinand', 'Glenn', 'Harold', 'Ian', 'Jun-jun', 'Keith', 'Leonardo', 'Marlon', 'Nelson', 'Orlando',
        'Pio', 'Raymund', 'Sherwin', 'Teodoro', 'Vincent', 'Wilmer', 'Alvin', 'Benedict', 'Carlito', 'Danilo',
    ];

    private const LAST_NAMES = [
        'Bullecer', 'Cabalit', 'Digamon', 'Estrera', 'Gallego', 'Hinampas', 'Jamero', 'Lumongsod', 'Maglinte', 'Nuñal',
        'Olalo', 'Paclibar', 'Quiño', 'Rañola', 'Sarigumba', 'Tumulak', 'Unabia', 'Visitacion', 'Yañez', 'Zamoras',
        'Boncales', 'Cuyos', 'Dagatan', 'Etom', 'Fortich', 'Gimenez', 'Hontanosas', 'Iligan', 'Jubay', 'Lupas',
        'Abellana', 'Binoya', 'Cañete', 'Dinoy', 'Edquiban', 'Fuderanan', 'Gabutan', 'Hermosilla', 'Inocentes', 'Jabagat',
    ];

    /** How each employee works: the rates are per working day, in percent. */
    private const PROFILES = [
        'regular' => ['absent' => 3, 'half' => 3, 'late' => 6, 'undertime' => 3, 'overtime' => 35],
        'overtime' => ['absent' => 2, 'half' => 2, 'late' => 4, 'undertime' => 2, 'overtime' => 85],
        'irregular' => ['absent' => 7, 'half' => 8, 'late' => 25, 'undertime' => 20, 'overtime' => 20],
    ];

    /** @var array<string, string> */
    private array $qrCodes = [];

    /**
     * The first and the last day of the attendance: it ends END_DAYS_AGO days before today and starts on the Monday of the week
     * DAYS before that, so whole payroll weeks fit in it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(): array
    {
        $end = Carbon::today()->subDays(self::END_DAYS_AGO);

        return [$end->copy()->subDays(self::DAYS - 1)->startOfWeek(Carbon::MONDAY), $end];
    }

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('The sample staff is only for a development or demo database.');
        }

        $branches = array_keys(config('bohol_branches', []));
        $superAdmin = (int) (DB::table('users')->where('role', 'super_admin')->orderBy('id')->value('id') ?? 0) ?: null;
        $passwordHash = Hash::make((string) env('DEMO_STAFF_PASSWORD', self::DEFAULT_PASSWORD));

        [$first, $last] = self::window();
        $removed = $this->clearAfterWindow($last);
        $created = $this->createStaff($branches, $passwordHash);
        $employees = DB::table('employees')->where('status', 'active')->orderBy('id')->get();

        $days = [];
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            if (! $day->isSunday()) {
                $days[] = $day->copy();
            }
        }

        $this->createQrCodes($days, $branches, $superAdmin);
        [$inserted, $filled] = $this->writeAttendance($employees, $days);

        $this->command?->info("Staff: {$created} technician account(s) created. Attendance {$first->toDateString()} to {$last->toDateString()}: {$inserted} day(s) added, {$filled} filled in or made again, {$removed} record(s) after it removed.");
    }

    // ---- earlier runs --------------------------------------------------------------------------

    /**
     * What an earlier run made for days after the window (it used to reach today) is removed: the attendance of the sample employees,
     * the attendance that points at a code this seeder generated, and those QR codes.
     *
     * @return int attendance records removed
     */
    private function clearAfterWindow(Carbon $last): int
    {
        $sample = DB::table('employees')->where('employee_code', 'like', 'EMP-DEMO-%')->pluck('id')->all();
        $removed = (int) DB::table('attendance')->whereIn('employee_id', $sample)->where('attendance_date', '>', $last->toDateString())->delete();

        $generated = [];
        foreach (DB::table('qr_codes')->where('valid_date', '>', $last->toDateString())->get(['id', 'qr_code', 'qr_type', 'valid_date', 'branch_name']) as $code) {
            // The suffix of a generated code is a hash of its type, date and branch; a code the super admin made has a random one.
            if (str_ends_with($code->qr_code, '-' . substr(md5($code->qr_type . substr((string) $code->valid_date, 0, 10) . $code->branch_name), 0, 8))) {
                $generated[] = $code;
            }
        }
        if ($generated !== []) {
            $removed += (int) DB::table('attendance')->whereIn('qr_code_used', array_column($generated, 'qr_code'))->where('attendance_date', '>', $last->toDateString())->delete();
            DB::table('qr_codes')->whereIn('id', array_column($generated, 'id'))->delete();
        }

        return $removed;
    }

    // ---- staff ---------------------------------------------------------------------------------

    /** @return int how many technicians were created */
    private function createStaff(array $branches, string $passwordHash): int
    {
        $usedNames = array_flip(DB::table('employees')->pluck('full_name')->all());
        $usedUsernames = array_flip(DB::table('users')->pluck('username')->all());
        $pool = $this->namePool();
        $nextName = 0;
        $created = 0;

        foreach ($branches as $branch) {
            $prefix = strtoupper(substr($branch, 0, 3));
            $barangays = array_values((array) (config('bohol_barangays')[$branch] ?? [$branch]));

            // The barangays that already have a technician (their address starts with the barangay) and the last employee number used.
            $covered = [];
            $slot = 0;
            foreach (DB::table('employees')->where('branch_name', $branch)->where('position', 'like', 'Technician%')->get(['employee_code', 'address']) as $technician) {
                $covered[mb_strtolower(trim(explode(',', (string) $technician->address)[0]))] = true;
                if (preg_match('/^EMP-DEMO-' . $prefix . '-(\d+)$/', $technician->employee_code, $number)) {
                    $slot = max($slot, (int) $number[1]);
                }
            }

            foreach ($barangays as $barangay) {
                if (isset($covered[mb_strtolower($barangay)])) {
                    continue;
                }
                $slot++;
                $code = sprintf('EMP-DEMO-%s-%02d', $prefix, $slot);
                while (isset($usedNames[$pool[$nextName] ?? null])) {
                    $nextName++;
                }
                $name = $pool[$nextName] ?? throw new \RuntimeException('Not enough sample names for this many employees.');
                $usedNames[$name] = true;

                $base = Str::lower(preg_replace('/[^A-Za-z0-9]+/', '', Str::ascii($name)));
                $username = $base;
                for ($suffix = 2; isset($usedUsernames[$username]); $suffix++) {
                    $username = $base . $suffix;
                }
                $usedUsernames[$username] = true;

                $hired = Carbon::create(2025, 1, 6)->addDays(crc32($code) % 540)->setTime(8, 0);
                $position = $slot % 3 === 1 ? 'Technician II' : 'Technician I';
                $email = $username . '@gmail.com';
                $phone = sprintf('0928%07d', 1_000_000 + crc32($code) % 8_000_000);

                DB::transaction(function () use ($branch, $barangay, $code, $name, $username, $email, $phone, $position, $hired, $passwordHash): void {
                    $userId = DB::table('users')->insertGetId([
                        'username' => $username, 'password' => $passwordHash, 'full_name' => $name, 'email' => $email, 'phone' => $phone,
                        'role' => 'employee', 'branch_name' => $branch, 'status' => 'active', 'archived' => 0, 'created_at' => $hired, 'updated_at' => $hired,
                    ]);
                    DB::table('employees')->insert([
                        'employee_code' => $code, 'user_id' => $userId, 'full_name' => $name, 'position' => $position,
                        'contact_number' => $phone, 'email' => $email, 'address' => "{$barangay}, {$branch}, Bohol",
                        'date_hired' => $hired->toDateString(), 'daily_rate' => $position === 'Technician II' ? 580 : 500,
                        'status' => 'active', 'branch_name' => $branch, 'created_at' => $hired,
                    ]);
                });
                $created++;
            }
        }

        return $created;
    }

    /** Every first name with every surname, in a fixed order that looks shuffled, so no name repeats. */
    private function namePool(): array
    {
        $pool = [];
        foreach (self::FIRST_NAMES as $first) {
            foreach (self::LAST_NAMES as $last) {
                $pool[$first . ' ' . $last] = crc32($first . '|' . $last);
            }
        }
        asort($pool);

        return array_keys($pool);
    }

    // ---- QR codes ------------------------------------------------------------------------------

    /**
     * One morning and one afternoon code for every working day (shared by all branches) and, on a branch's overtime days, the overtime
     * code of that branch, so every attendance record points at a code that exists. A day that has its code is left alone.
     *
     * @param array<int, Carbon> $days
     */
    private function createQrCodes(array $days, array $branches, ?int $superAdmin): void
    {
        $schedule = AttendanceTime::schedule();
        $overtimeStart = DB::table('system_settings')->where('setting_key', 'qr_overtime_start')->value('setting_value') ?: '17:00:00';
        $overtimeEnd = DB::table('system_settings')->where('setting_key', 'qr_overtime_end')->value('setting_value') ?: '22:00:00';
        $branchAdmins = DB::table('users')->where('role', 'admin')->where('archived', 0)->pluck('id', 'branch_name')->all();

        $existing = DB::table('qr_codes')->where('status', 'active')->get(['qr_code', 'qr_type', 'valid_date', 'branch_name']);
        foreach ($existing as $row) {
            $this->qrCodes[$this->qrKey($row->qr_type, substr((string) $row->valid_date, 0, 10), $row->branch_name)] ??= $row->qr_code;
        }

        $rows = [];
        foreach ($days as $day) {
            $date = $day->toDateString();
            foreach (['morning', 'afternoon'] as $type) {
                if (! isset($this->qrCodes[$this->qrKey($type, $date, null)])) {
                    $rows[] = $this->qrRow($type, $day, null, $schedule[$type]['start'], $schedule[$type]['end'], $superAdmin, $schedule[$type]['start']);
                }
            }
            foreach ($branches as $branch) {
                if ($this->isOvertimeDay($branch, $day) && ! isset($this->qrCodes[$this->qrKey('overtime', $date, $branch)])) {
                    $rows[] = $this->qrRow('overtime', $day, $branch, $overtimeStart, $overtimeEnd, $branchAdmins[$branch] ?? $superAdmin, '16:50:00');
                }
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('qr_codes')->insert($chunk);
        }
    }

    private function qrRow(string $type, Carbon $day, ?string $branch, string $from, string $until, ?int $createdBy, string $madeAt): array
    {
        $date = $day->toDateString();
        $code = sprintf('JFTECH-%s-%s-%s', strtoupper($type), $day->format('Ymd'), substr(md5($type . $date . $branch), 0, 8));
        $this->qrCodes[$this->qrKey($type, $date, $branch)] = $code;

        return [
            'qr_code' => $code, 'qr_type' => $type, 'valid_date' => $date, 'valid_from' => $from, 'valid_until' => $until,
            'status' => 'active', 'created_by' => $createdBy, 'created_at' => $day->copy()->setTimeFromTimeString($madeAt)->subMinutes(10), 'branch_name' => $branch,
        ];
    }

    private function qrKey(string $type, string $date, ?string $branch): string
    {
        return $type . '|' . $date . '|' . mb_strtolower((string) $branch);
    }

    /**
     * Each branch approves its own overtime days: a usual weekday of its own (Loon works late on Thursdays, another branch on
     * Mondays, ...) and now and then another day.
     */
    private function isOvertimeDay(string $branch, Carbon $day): bool
    {
        $usual = crc32('overtime-weekday|' . $branch) % 6 + 1; // Monday (1) to Saturday (6)

        return $day->dayOfWeekIso === $usual || $this->pick("overtime-extra|{$branch}|{$day->toDateString()}", 100) < 6;
    }

    // ---- attendance ----------------------------------------------------------------------------

    /**
     * @param \Illuminate\Support\Collection<int, object> $employees
     * @param array<int, Carbon> $days
     * @return array{0: int, 1: int} [records added, records filled in or made again]
     */
    private function writeAttendance($employees, array $days): array
    {
        $from = $days[0]->toDateString();
        $schedule = AttendanceTime::schedule();
        $existing = [];
        foreach (DB::table('attendance')->where('attendance_date', '>=', $from)->get() as $row) {
            $existing[$row->employee_id . '|' . substr((string) $row->attendance_date, 0, 10)] = $row;
        }

        $perBranch = [];
        $inserts = [];
        $filled = 0;
        foreach ($employees as $employee) {
            $index = $perBranch[$employee->branch_name] = ($perBranch[$employee->branch_name] ?? -1) + 1;
            $profile = array_keys(self::PROFILES)[$index % count(self::PROFILES)];
            $hired = $employee->date_hired ? Carbon::parse($employee->date_hired)->startOfDay() : null;

            // What there will be for each day: the record that is already there (it stays), or what is planned for a day with none.
            $kept = [];
            $planned = [];
            foreach ($days as $day) {
                if ($hired && $day->lt($hired)) {
                    continue;
                }
                $date = $day->toDateString();
                $old = $existing[$employee->id . '|' . $date] ?? null;
                // A record with times is kept as it is, unless it is one this seeder made (its QR code is a generated one): that is made again.
                if ($old && ($old->morning_time_in || $old->afternoon_time_in || $old->overtime_time_in || $old->morning_time_out || $old->afternoon_time_out || $old->qr_code_used === 'manual')
                    && ! $this->isGenerated($old, $employee)) {
                    $kept[$date] = AttendanceTime::dayFigures($old, $schedule) + ['overtime_minutes' => (int) $old->overtime_minutes];

                    continue;
                }
                if ($columns = $this->plan($employee, $profile, $day)) {
                    $planned[$date] = ['day' => $day, 'columns' => $columns, 'old' => $old, 'generated' => $old && $this->isGenerated($old, $employee)];
                }
            }

            $planned = $this->ensureEveryKind($employee, $profile, $kept, $planned, $schedule);

            foreach ($planned as $date => $plan) {
                $columns = $plan['columns'];
                if ($plan['old']) {
                    // A record this seeder made is rewritten only when it would come out differently; one that only holds the automatic
                    // absent marks is filled in, and only when there is something to put in.
                    if ($plan['generated'] ? $this->sameRecord($plan['old'], $columns) : ! ($columns['morning_time_in'] || $columns['afternoon_time_in'] || $columns['overtime_time_in'])) {
                        continue;
                    }
                    DB::table('attendance')->where('id', $plan['old']->id)->update($columns);
                    $filled++;

                    continue;
                }
                $inserts[] = ['employee_id' => $employee->id, 'attendance_date' => $date] + $columns;
            }
        }

        foreach (array_chunk($inserts, 200) as $chunk) {
            DB::table('attendance')->insert($chunk);
        }

        return [count($inserts), $filled];
    }

    /**
     * Whether an attendance record is one this seeder made: its QR code is one it generates (the suffix is a hash of the type, the
     * date and, for overtime, the branch), which a code made by the super admin is not.
     */
    private function isGenerated(object $row, object $employee): bool
    {
        if (! preg_match('/^JFTECH-(MORNING|AFTERNOON|OVERTIME)-(\d{4})(\d{2})(\d{2})-([0-9a-f]{8})$/', (string) $row->qr_code_used, $match)) {
            return false;
        }
        $type = strtolower($match[1]);
        $branch = $type === 'overtime' ? $employee->branch_name : '';

        return $match[5] === substr(md5($type . "{$match[2]}-{$match[3]}-{$match[4]}" . $branch), 0, 8);
    }

    /** Whether a record already says what the plan would write. */
    private function sameRecord(object $old, array $columns): bool
    {
        foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out', 'overtime_time_in', 'overtime_time_out', 'status_morning', 'status_afternoon', 'qr_code_used'] as $column) {
            if (($old->$column ?? null) !== ($columns[$column] ?? null)) {
                return false;
            }
        }

        return (int) $old->overtime_minutes === (int) $columns['overtime_minutes'] && abs((float) $old->total_hours - (float) $columns['total_hours']) < 0.005;
    }

    /**
     * Every employee has at least one late day, one undertime day and one overtime day in the window: where the plan has none of a
     * kind (counting the days that were already there), a day of the plan on which they worked both sessions is made that kind.
     *
     * @param array<string, array<string, mixed>> $kept days that are already recorded, with their figures
     * @param array<string, array{day: Carbon, columns: array<string, mixed>, old: ?object, generated: bool}> $planned
     * @return array<string, array{day: Carbon, columns: array<string, mixed>, old: ?object, generated: bool}>
     */
    private function ensureEveryKind(object $employee, string $profile, array $kept, array $planned, array $schedule): array
    {
        $has = ['late' => false, 'undertime' => false, 'overtime' => false];
        $note = function (array $figures) use (&$has): void {
            $has['late'] = $has['late'] || ($figures['late_minutes'] ?? 0) > 0;
            $has['undertime'] = $has['undertime'] || ($figures['undertime_minutes'] ?? 0) > 0;
            $has['overtime'] = $has['overtime'] || ($figures['overtime_minutes'] ?? 0) > 0;
        };
        foreach ($kept as $figures) {
            $note($figures);
        }
        foreach ($planned as $plan) {
            $note(AttendanceTime::dayFigures($plan['columns'], $schedule) + ['overtime_minutes' => (int) $plan['columns']['overtime_minutes']]);
        }

        $force = [];
        foreach (array_keys($has) as $kind) {
            if ($has[$kind]) {
                continue;
            }
            // The days that worked both sessions and have not been given a kind yet (overtime: only on the days the branch approves it).
            $candidates = array_filter($planned, function (array $plan, string $date) use ($kind, $employee, $force): bool {
                $c = $plan['columns'];

                return ! isset($force[$date]) && $c['morning_time_in'] && $c['afternoon_time_in']
                    && ($kind !== 'overtime' || $this->isOvertimeDay($employee->branch_name, $plan['day']));
            }, ARRAY_FILTER_USE_BOTH);
            if ($candidates === []) {
                continue;
            }
            $chosen = array_keys($candidates);
            usort($chosen, fn (string $a, string $b) => crc32("force|{$kind}|{$employee->employee_code}|{$a}") <=> crc32("force|{$kind}|{$employee->employee_code}|{$b}"));
            $force[$chosen[0]][$kind] = true;
        }
        foreach ($force as $date => $kinds) {
            $planned[$date]['columns'] = $this->plan($employee, $profile, $planned[$date]['day'], $kinds) ?? $planned[$date]['columns'];
        }

        return $planned;
    }

    /**
     * What one employee did on one day, as the columns of the attendance table (null when there is nothing to record yet).
     * $force makes the day a late, an undertime or an overtime day (keys late, undertime, overtime) when it was worked in full.
     *
     * @param array<string, bool> $force
     * @return array<string, mixed>|null
     */
    private function plan(object $employee, string $profile, Carbon $day, array $force = []): ?array
    {
        $rates = self::PROFILES[$profile];
        $date = $day->toDateString();
        $key = $employee->employee_code . '|' . $date;
        $roll = $this->pick($key . '|day', 100);

        $absent = $roll < $rates['absent'];
        $half = ! $absent && $roll < $rates['absent'] + $rates['half'];
        $morning = ! $absent;
        $afternoon = ! $absent;
        if ($half) {
            // A half day is one session only: the morning or the afternoon.
            $morning = $this->pick($key . '|which', 2) === 0;
            $afternoon = ! $morning;
        }
        $isLate = ! $absent && (isset($force['late']) || $this->pick($key . '|late', 100) < $rates['late']);
        $isUndertime = ! $absent && (isset($force['undertime']) || $this->pick($key . '|under', 100) < $rates['undertime']);
        $overtime = ! $absent && $morning && $afternoon && (isset($force['overtime']) || ($this->isOvertimeDay($employee->branch_name, $day) && $this->pick($key . '|ot', 100) < $rates['overtime']));

        $times = [];
        if ($morning) {
            $lateNow = $isLate && $this->pick($key . '|lw', 2) === 0;
            $times['morning_time_in'] = $this->clock($lateNow ? $this->between($key . '|mi', 511, 565) : $this->between($key . '|mi', 480, 502), $key . '|mis');
            $times['morning_time_out'] = $this->clock($isUndertime && $this->pick($key . '|uw', 2) === 0 ? $this->between($key . '|mo', 670, 704) : $this->between($key . '|mo', 715, 719), $key . '|mos');
            $times['status_morning'] = $lateNow ? 'late' : 'present';
        }
        if ($afternoon) {
            $lateNow = $isLate && ($half || $this->pick($key . '|lw', 2) === 1);
            $times['afternoon_time_in'] = $this->clock($lateNow ? $this->between($key . '|ai', 811, 855) : $this->between($key . '|ai', 780, 800), $key . '|ais');
            $times['afternoon_time_out'] = $this->clock($isUndertime && ($half || $this->pick($key . '|uw', 2) === 1) ? $this->between($key . '|ao', 920, 1004) : $this->between($key . '|ao', 1015, 1019), $key . '|aos');
            $times['status_afternoon'] = $lateNow ? 'late' : 'present';
        }
        if ($overtime) {
            $in = $this->between($key . '|oi', 1022, 1040);
            $times['overtime_time_in'] = $this->clock($in, $key . '|ois');
            $times['overtime_time_out'] = $this->clock($in + $this->between($key . '|od', 45, 220), $key . '|oos');
        }

        // Nobody has scanned in the future: a window that reaches today stops at the present time.
        if ($day->isToday()) {
            $now = now()->format('H:i:s');
            $schedule = AttendanceTime::schedule();
            foreach (['morning', 'afternoon', 'overtime'] as $part) {
                foreach (['in', 'out'] as $edge) {
                    if (isset($times["{$part}_time_{$edge}"]) && $times["{$part}_time_{$edge}"] > $now) {
                        unset($times["{$part}_time_{$edge}"]);
                    }
                }
                if ($part !== 'overtime') {
                    if (! isset($times["{$part}_time_in"])) {
                        unset($times["status_{$part}"]);
                    }
                    // A session that is over and has no scan is absent; one that is still to come, or running, has no status yet.
                    $times["status_{$part}"] ??= $now > $schedule[$part]['end'] ? 'absent' : null;
                }
            }
        } else {
            $times['status_morning'] ??= 'absent';
            $times['status_afternoon'] ??= 'absent';
        }

        $row = $times + ['morning_time_in' => null, 'morning_time_out' => null, 'afternoon_time_in' => null, 'afternoon_time_out' => null, 'overtime_time_in' => null, 'overtime_time_out' => null];
        $minutes = ($row['overtime_time_in'] && $row['overtime_time_out']) ? AttendanceTime::minutesBetween($row['overtime_time_in'], $row['overtime_time_out']) : 0;
        if (! $row['morning_time_in'] && ! $row['afternoon_time_in'] && ! $row['overtime_time_in'] && ! ($times['status_morning'] ?? null) && ! ($times['status_afternoon'] ?? null)) {
            return null; // today, and nothing has happened yet
        }
        $firstScan = $row['morning_time_in'] ?: ($row['afternoon_time_in'] ?: '08:00:00');

        $lastCode = match (true) {
            $row['overtime_time_in'] !== null => $this->qrCodes[$this->qrKey('overtime', $date, $employee->branch_name)] ?? null,
            $row['afternoon_time_in'] !== null => $this->qrCodes[$this->qrKey('afternoon', $date, null)] ?? null,
            $row['morning_time_in'] !== null => $this->qrCodes[$this->qrKey('morning', $date, null)] ?? null,
            default => null,
        };

        return $row + [
            'status_morning' => null, 'status_afternoon' => null,
            'qr_code_used' => $lastCode, 'total_hours' => AttendanceTime::totalHours($row), 'overtime_minutes' => $minutes,
            'branch_name' => $employee->branch_name, 'created_at' => $day->copy()->setTimeFromTimeString($firstScan),
        ];
    }

    private function pick(string $key, int $modulo): int
    {
        return crc32($key) % $modulo;
    }

    private function between(string $key, int $from, int $to): int
    {
        return $from + $this->pick($key, $to - $from + 1);
    }

    /** A time of day from minutes since midnight, with seconds so the scans do not all land on the minute. */
    private function clock(int $minutes, string $secondsKey): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($minutes, 60), $minutes % 60, $this->pick($secondsKey, 60));
    }
}
