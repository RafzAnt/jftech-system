<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function pendingBills(Request $request): JsonResponse
    {
        $data = $request->validate(['client_id' => ['required', 'integer']]);
        \App\Support\BranchScope::abortUnlessClient($data['client_id']);
        return response()->json(['success' => true, 'bills' => DB::table('bills')
            ->select('id', 'bill_number', 'amount', 'due_date')
            ->selectRaw('GREATEST(COALESCE(amount, 0) - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.bill_id = bills.id), 0), 0) AS balance')
            ->where('client_id', $data['client_id'])
            ->whereIn('status', ['pending', 'overdue'])
            ->orderBy('due_date')
            ->get()]);
    }
    public function clientBilling(Request $request): JsonResponse
    {
        $data = $request->validate(['client_id' => ['required', 'integer']]);
        \App\Support\BranchScope::abortUnlessClient($data['client_id']);
        return response()->json(['success' => true, 'bills' => DB::table('bills')->where('client_id', $data['client_id'])->orderByDesc('billing_period_start')->get(), 'payments' => DB::table('payments')->leftJoin('bills', 'payments.bill_id', '=', 'bills.id')->where('payments.client_id', $data['client_id'])->orderByDesc('payment_date')->get(['payments.*', 'bills.bill_number', 'bills.amount as bill_amount', DB::raw('(COALESCE(bills.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = payments.bill_id), 0)) AS balance')])]);
    }
    public function inventoryItems(Request $request): JsonResponse
    {
        // Every branch keeps its own stock; the app is only offered the stock of the signed-in employee's branch.
        $employee = $request->attributes->get('employee');

        // Pending item-out requests reserve stock immediately. The mobile app
        // must only offer quantity that can still be released by the admin.
        $reserved = "COALESCE((SELECT SUM(requests.quantity) FROM inventory_requests AS requests WHERE requests.inventory_id = inventory.id AND requests.status = 'pending' AND requests.request_type = 'out'), 0)";
        $items = DB::table('inventory')
            ->whereRaw("LOWER(COALESCE(inventory.branch_name, '')) = LOWER(?)", [(string) $employee->branch_name])
            ->select('inventory.*')
            ->selectRaw("GREATEST(inventory.quantity - {$reserved}, 0) AS available_quantity")
            ->where('inventory.status', 'active')
            ->where('inventory.archived', 0)
            ->orderBy('inventory.item_name')
            ->get()
            // NAP boxes are installed by the branch admin from the stock (they are not handed out to an employee), so the app never offers them.
            ->reject(fn ($item) => \App\Support\NapBoxes::isStockItem($item))
            ->values();

        // For an item-out request only stock that is left is offered; a return can be of any item of the branch.
        $out = $items->filter(fn ($item) => (int) $item->available_quantity > 0)->values();

        return response()->json(['success' => true, 'data' => $out, 'all_items' => $items->values()]);
    }
    /**
     * The last 30 days the employee has a record for, newest first, each with the minutes it was late, the minutes of early leaving and
     * the hours worked, plus the totals of those days for the top of the app's history screen.
     */
    public function attendanceHistory(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $rows = DB::table('attendance as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.employee_id', $employee->id)
            ->orderByDesc('a.attendance_date')->orderByDesc('a.id')->limit(30)
            ->get(['a.*', 'e.position', DB::raw('COALESCE(a.branch_name, e.branch_name) AS branch_name')]);

        $schedule = \App\Support\AttendanceTime::schedule();
        $summary = ['days_recorded' => 0, 'days_worked' => 0.0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'overtime_minutes' => 0, 'overtime_minimum_minutes' => \App\Support\AttendanceTime::MIN_OVERTIME_MINUTES, 'total_hours' => 0.0, 'absent_sessions' => 0];
        foreach ($rows as $row) {
            $figures = \App\Support\AttendanceTime::dayFigures($row, $schedule);
            $row->late_minutes = $figures['late_minutes'];
            $row->undertime_minutes = $figures['undertime_minutes'];
            // The hours of a record the app never closed (or an old one) are worked out from its times.
            $row->total_hours = $figures['total_hours'] > 0 ? $figures['total_hours'] : (float) $row->total_hours;

            $summary['days_recorded']++;
            $summary['late_minutes'] += $figures['late_minutes'];
            $summary['undertime_minutes'] += $figures['undertime_minutes'];
            $summary['overtime_minutes'] += (int) $row->overtime_minutes;
            $summary['total_hours'] += (float) $row->total_hours;
            foreach (['morning', 'afternoon'] as $part) {
                if ($row->{"{$part}_time_in"}) {
                    $summary['days_worked'] += 0.5;
                } elseif ($row->{"status_{$part}"} === 'absent' && ! \Illuminate\Support\Carbon::parse($row->attendance_date)->isSunday()) {
                    $summary['absent_sessions']++;
                }
            }
        }
        $summary['total_hours'] = round($summary['total_hours'], 2);

        return response()->json(['success' => true, 'data' => $rows, 'summary' => $summary]);
    }
    public function employeeLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $user = DB::table('users')->join('employees', 'employees.user_id', '=', 'users.id')->where('users.username', $data['username'])->where('users.status', 'active')->where('users.archived', 0)->where('employees.status', 'active')->select('users.*', 'employees.id as employee_id', 'employees.position as employee_position', 'employees.full_name as employee_name', 'employees.branch_name as employee_branch')->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            DB::table('login_history')->insert(['username' => $data['username'], 'full_name' => '', 'role' => 'employee', 'ip_address' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'status' => 'failed', 'error_message' => 'Invalid username or password.']);
            return response()->json(['success' => false, 'message' => 'Invalid username or password.'], 401);
        }
        $historyId = DB::table('login_history')->insertGetId(['user_id' => $user->id, 'username' => $user->username, 'full_name' => $user->full_name, 'role' => $user->role, 'ip_address' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'status' => 'success']);
        // Every later request of the app carries this token; it is what proves who is signed in.
        $token = \App\Support\EmployeeTokens::issue((int) $user->employee_id, (int) $historyId);
        return response()->json(['success' => true, 'token' => $token, 'user' => ['id' => $user->id, 'employee_id' => $user->employee_id, 'full_name' => $user->employee_name ?: $user->full_name, 'username' => $user->username, 'position' => $user->employee_position, 'branch_name' => $user->employee_branch, 'login_history_id' => $historyId]]);
    }
    public function employeeLogout(Request $request): JsonResponse
    {
        $data = $request->validate(['login_history_id' => ['required', 'integer']]);
        // Signing out ends the token that was used, so it cannot be used again.
        if ($bearer = $request->bearerToken()) {
            \App\Support\EmployeeTokens::revoke($bearer);
        }
        $history = DB::table('login_history')
            ->select('id', 'status', 'logout_time')
            ->where('id', $data['login_history_id'])
            ->first();

        if (! $history || $history->status !== 'success') {
            return response()->json(['success' => false, 'message' => 'Login history record not found.'], 404);
        }

        // A queued request can be retried after a network interruption. Keep
        // the original logout time instead of replacing it during that retry.
        if ($history->logout_time) {
            return response()->json(['success' => true, 'message' => 'Logout already recorded.']);
        }

        $updated = DB::table('login_history')
            ->where('id', $history->id)
            ->whereNull('logout_time')
            ->update(['logout_time' => now()]);

        return response()->json([
            'success' => $updated === 1,
            'message' => $updated === 1 ? 'Logout recorded.' : 'Logout already recorded.',
        ]);
    }

    public function inventoryRequestCreate(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        $data = $request->validate([
            'request_type' => ['nullable', 'in:out,return'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_id' => ['required', 'integer', 'exists:inventory,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.comments' => ['nullable', 'string'],
            'item_condition' => ['nullable', 'string', 'max:30'],
            'area_used' => ['nullable', 'string', 'max:150'],
            'person_in_charge' => ['nullable', 'string', 'max:100'],
        ]);
        $type = $data['request_type'] ?? 'out';
        $details = [
            'request_type' => $type,
            'item_condition' => isset($data['item_condition']) ? trim($data['item_condition']) ?: null : null,
            'area_used' => isset($data['area_used']) ? trim($data['area_used']) ?: null : null,
            'person_in_charge' => isset($data['person_in_charge']) ? trim($data['person_in_charge']) ?: null : null,
        ];

        $createdIds = [];
        try {
            DB::transaction(function () use (&$createdIds, $employee, $data, $type, $details) {
            foreach ($data['items'] as $item) {
                // A return can be of any item of the branch; an item out must still be active and in stock.
                $inv = DB::table('inventory')
                    ->where('id', $item['inventory_id'])
                    ->when($type === 'out', fn ($query) => $query->where('status', 'active')->where('archived', 0))
                    ->lockForUpdate()
                    ->first();
                if (! $inv) {
                    throw new \DomainException('One or more requested items are no longer available. Please refresh the inventory list.');
                }
                if (strcasecmp(trim((string) $inv->branch_name), trim((string) $employee->branch_name)) !== 0) {
                    throw new \DomainException("{$inv->item_name} is stocked by another branch. Please refresh the inventory list.");
                }
                if (\App\Support\NapBoxes::isStockItem($inv)) {
                    throw new \DomainException("{$inv->item_name} is a NAP box. Your admin installs NAP boxes from the stock, so it cannot be requested.");
                }

                if ($type === 'out') {
                    $reserved = (int) DB::table('inventory_requests')
                        ->where('inventory_id', $inv->id)
                        ->where('status', 'pending')
                        ->where('request_type', 'out')
                        ->sum('quantity');
                    $available = max(0, (int) $inv->quantity - $reserved);
                    if ((int) $item['quantity'] > $available) {
                        throw new \DomainException("{$inv->item_name} only has {$available} unit(s) available for request.");
                    }
                }
                $id = DB::table('inventory_requests')->insertGetId([
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'inventory_id' => $inv->id,
                    'item_name' => $inv->item_name,
                    'quantity' => $item['quantity'],
                    'comments' => $item['comments'] ?? null,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ] + $details);
                $createdIds[] = $id;
            }
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        if (empty($createdIds)) {
            return response()->json(['success' => false, 'message' => 'No valid inventory items to request.'], 422);
        }

        return response()->json(['success' => true, 'message' => count($createdIds) . ($type === 'return' ? ' return(s) submitted.' : ' request(s) submitted.'), 'request_ids' => $createdIds], 201);
    }
    public function inventoryRequestsList(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('employee');
        return response()->json(['success' => true, 'data' => DB::table('inventory_requests')->where('employee_id', $employee->id)->orderByDesc('created_at')->limit(50)->get()]);
    }
    public function scanAttendance(Request $request): JsonResponse
    {
        $data = $request->validate(['qr_code' => ['required', 'string', 'max:255']]);
        $employee = $request->attributes->get('employee');
        $qr = DB::table('qr_codes')->where('qr_code', $data['qr_code'])->where('status', 'active')->whereDate('valid_date', today())->first();
        if (! $employee || ! $qr) return response()->json(['success' => false, 'message' => 'Invalid or expired QR code.'], 422);
        // Overtime has no QR code of its own any more (it is worked out from the afternoon time out), and a code of the earlier kind
        // must not be taken for an afternoon one.
        if ($qr->qr_type === 'overtime') {
            return response()->json(['success' => false, 'message' => 'The overtime QR code is no longer used. Scan the afternoon QR code when you finish work: the time after the afternoon ends is your overtime.'], 422);
        }
        $part = $qr->qr_type === 'morning' ? 'morning' : 'afternoon';
        $session = \App\Support\AttendanceTime::schedule()[$part];

        $now = now();
        $time = $now->format('H:i:s');
        // The afternoon code works until the last scan of the day (so people who work late can scan out) whatever end it was made with,
        // the morning one until its own end.
        $validUntil = $part === 'afternoon' ? max(\App\Support\AttendanceTime::clock($session['end']), \App\Support\AttendanceTime::lastScan()) : $qr->valid_until;
        if ($time < $qr->valid_from || $time > $validUntil) return response()->json(['success' => false, 'message' => 'QR code is outside its valid time window.'], 422);

        $lateThreshold = (int) (DB::table('system_settings')->where('setting_key', 'late_threshold_minutes')->value('setting_value') ?? 30);
        $thresholdTime = date('H:i:s', strtotime($session['start']) + ($lateThreshold * 60));
        $status = $time > $thresholdTime ? 'late' : 'present';
        // A scan after the end of the afternoon can only be the time out of the day; the time after the end is the overtime.
        $sessionEnd = \App\Support\AttendanceTime::clock($session['end']);
        $afterEnd = $part === 'afternoon' && strcmp($time, $sessionEnd) > 0;

        // The first scan of a session is the time in and the next one the time out; the last scan of the day is the time out. The row is locked so two scans at once cannot make two rows for the day.
        $outcome = DB::transaction(function () use ($employee, $qr, $time, $status, $part, $afterEnd, $sessionEnd) {
            $attendance = DB::table('attendance')->where('employee_id', $employee->id)->whereDate('attendance_date', today())->lockForUpdate()->first();

            if ($afterEnd && (! $attendance || ! $attendance->afternoon_time_in)) {
                return ['error' => 'The afternoon session has ended, so you can no longer time in. Ask your branch admin to record your attendance.'];
            }
            if (! $attendance) {
                DB::table('attendance')->insert([
                    'employee_id' => $employee->id, 'attendance_date' => today(), 'qr_code_used' => $qr->qr_code, 'branch_name' => $employee->branch_name,
                    "{$part}_time_in" => $time, "status_{$part}" => $status, 'total_hours' => 0, 'created_at' => now(),
                ]);

                return ['scan_type' => 'Time In', 'status' => $status];
            }
            if (! $attendance->{"{$part}_time_in"}) {
                DB::table('attendance')->where('id', $attendance->id)->update([
                    "{$part}_time_in" => $time, "status_{$part}" => $status, 'qr_code_used' => $qr->qr_code, 'branch_name' => $attendance->branch_name ?: $employee->branch_name,
                ]);

                return ['scan_type' => 'Time In', 'status' => $status];
            }
            if (\App\Support\AttendanceTime::minutesBetween($attendance->{"{$part}_time_in"}, $time) < 1 && ! $attendance->{"{$part}_time_out"}) {
                return ['error' => 'You have just timed in. Scan again when your shift ends.'];
            }

            $row = (array) $attendance;
            $overtime = 0;
            if ($afterEnd) {
                // The afternoon ends at its usual time and what follows is the overtime, kept in the overtime columns of the day.
                $overtime = \App\Support\AttendanceTime::overtimeAfter($sessionEnd, $time);
                $row['afternoon_time_out'] = $sessionEnd;
                $changes = ['afternoon_time_out' => $sessionEnd, 'overtime_time_in' => $sessionEnd, 'overtime_time_out' => $time, 'overtime_minutes' => $overtime];
            } else {
                $row["{$part}_time_out"] = $time;
                $changes = ["{$part}_time_out" => $time];
            }
            DB::table('attendance')->where('id', $attendance->id)->update($changes + [
                'total_hours' => \App\Support\AttendanceTime::totalHours($row),
                'qr_code_used' => $qr->qr_code, 'branch_name' => $attendance->branch_name ?: $employee->branch_name,
            ]);

            // The status of a session is the one its time in earned; a time out does not change it.
            return ['scan_type' => 'Time Out', 'status' => $attendance->{"status_{$part}"} ?: 'present', 'overtime_minutes' => $overtime];
        });

        if (isset($outcome['error'])) {
            return response()->json(['success' => false, 'message' => $outcome['error']], 422);
        }

        // Overtime is recorded whatever its length, but paid only from the minimum; the employee is told which it is.
        $overtime = (int) ($outcome['overtime_minutes'] ?? 0);
        $minimum = \App\Support\AttendanceTime::MIN_OVERTIME_MINUTES;
        $message = 'Attendance recorded.';
        if ($overtime >= $minimum) {
            $message .= ' Overtime: ' . \App\Support\PayrollCalculator::duration($overtime) . '.';
        } elseif ($overtime > 0) {
            $message .= ' Your overtime of ' . \App\Support\PayrollCalculator::duration($overtime) . ' is noted, but overtime is paid only from ' . \App\Support\PayrollCalculator::duration($minimum) . ' a day.';
        }

        return response()->json([
            'success' => true, 'message' => $message, 'status' => $outcome['status'], 'scan_type' => $outcome['scan_type'], 'time' => $now->format('h:i A'),
            'overtime_minutes' => $overtime, 'overtime_counted' => $overtime >= $minimum, 'overtime_minimum_minutes' => $minimum,
            'employee_name' => $employee->full_name, 'position' => $employee->position, 'branch_name' => $employee->branch_name,
        ]);
    }
}
