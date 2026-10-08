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
        return response()->json(['success' => true, 'bills' => DB::table('bills')->where('client_id', $data['client_id'])->orderByDesc('billing_period_start')->get(), 'payments' => DB::table('payments')->leftJoin('bills', 'payments.bill_id', '=', 'bills.id')->where('payments.client_id', $data['client_id'])->orderByDesc('payment_date')->get(['payments.*', 'bills.bill_number', 'bills.amount as bill_amount', DB::raw('(COALESCE(bills.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = payments.bill_id), 0)) AS balance')])]);
    }
    public function inventoryItems(): JsonResponse
    {
        // Pending employee requests reserve stock immediately. The mobile app
        // must only offer quantity that can still be released by the admin.
        $items = DB::table('inventory')
            ->select('inventory.*')
            ->selectRaw("GREATEST(inventory.quantity - COALESCE((SELECT SUM(requests.quantity) FROM inventory_requests AS requests WHERE requests.inventory_id = inventory.id AND requests.status = 'pending'), 0), 0) AS available_quantity")
            ->where('inventory.status', 'active')
            ->where('inventory.archived', 0)
            ->having('available_quantity', '>', 0)
            ->orderBy('inventory.item_name')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }
    public function attendanceHistory(Request $request): JsonResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer']]);
        return response()->json(['success' => true, 'data' => DB::table('attendance as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.employee_id', $data['employee_id'])->orderByDesc('a.attendance_date')->limit(30)->get(['a.*', 'e.position'])]);
    }
    public function employeeLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $user = DB::table('users')->join('employees', 'employees.user_id', '=', 'users.id')->where('users.username', $data['username'])->where('users.status', 'active')->select('users.*', 'employees.id as employee_id', 'employees.position as employee_position')->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            DB::table('login_history')->insert(['username' => $data['username'], 'full_name' => '', 'role' => 'employee', 'ip_address' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'status' => 'failed', 'error_message' => 'Invalid username or password.']);
            return response()->json(['success' => false, 'message' => 'Invalid username or password.'], 401);
        }
        $historyId = DB::table('login_history')->insertGetId(['user_id' => $user->id, 'username' => $user->username, 'full_name' => $user->full_name, 'role' => $user->role, 'ip_address' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'status' => 'success']);
        return response()->json(['success' => true, 'user' => ['id' => $user->id, 'employee_id' => $user->employee_id, 'full_name' => $user->full_name, 'username' => $user->username, 'position' => $user->employee_position, 'login_history_id' => $historyId]]);
    }

    public function employeeLogout(Request $request): JsonResponse
    {
        $data = $request->validate(['login_history_id' => ['required', 'integer']]);
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
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_id' => ['required', 'integer', 'exists:inventory,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.comments' => ['nullable', 'string'],
        ]);

        $employee = DB::table('employees')->where('id', $data['employee_id'])->where('status', 'active')->first();
        if (! $employee) return response()->json(['success' => false, 'message' => 'Employee account is inactive.'], 422);

        $createdIds = [];
        try {
            DB::transaction(function () use (&$createdIds, $employee, $data) {
            foreach ($data['items'] as $item) {
                $inv = DB::table('inventory')
                    ->where('id', $item['inventory_id'])
                    ->where('status', 'active')
                    ->where('archived', 0)
                    ->lockForUpdate()
                    ->first();
                if (! $inv) {
                    throw new \DomainException('One or more requested items are no longer available. Please refresh the inventory list.');
                }

                $reserved = (int) DB::table('inventory_requests')
                    ->where('inventory_id', $inv->id)
                    ->where('status', 'pending')
                    ->sum('quantity');
                $available = max(0, (int) $inv->quantity - $reserved);
                if ((int) $item['quantity'] > $available) {
                    throw new \DomainException("{$inv->item_name} only has {$available} unit(s) available for request.");
                }
                $id = DB::table('inventory_requests')->insertGetId([
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'inventory_id' => $inv?->id,
                    'item_name' => $inv->item_name,
                    'quantity' => $item['quantity'],
                    'comments' => $item['comments'] ?? null,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $createdIds[] = $id;
            }
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        if (empty($createdIds)) {
            return response()->json(['success' => false, 'message' => 'No valid inventory items to request.'], 422);
        }

        return response()->json(['success' => true, 'message' => count($createdIds) . ' request(s) submitted.', 'request_ids' => $createdIds], 201);
    }

    public function inventoryRequestsList(Request $request): JsonResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id']]);
        return response()->json(['success' => true, 'data' => DB::table('inventory_requests')->where('employee_id', $data['employee_id'])->orderByDesc('created_at')->limit(50)->get()]);
    }

    public function scanAttendance(Request $request): JsonResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id'], 'qr_code' => ['required', 'string', 'max:255']]);
        $employee = DB::table('employees')->where('id', $data['employee_id'])->where('status', 'active')->first();
        $qr = DB::table('qr_codes')->where('qr_code', $data['qr_code'])->where('status', 'active')->whereDate('valid_date', today())->first();
        if (! $employee || ! $qr) return response()->json(['success' => false, 'message' => 'Invalid or expired QR code.'], 422);
        $now = now();
        if ($now->format('H:i:s') < $qr->valid_from || $now->format('H:i:s') > $qr->valid_until) return response()->json(['success' => false, 'message' => 'QR code is outside its valid time window.'], 422);
        $settings = DB::table('system_settings')->pluck('setting_value', 'setting_key');
        $attendance = DB::table('attendance')->where('employee_id', $employee->id)->whereDate('attendance_date', today())->first();
        $time = $now->format('H:i:s');
        $startTime = $qr->qr_type === 'morning' ? ($settings['qr_morning_start'] ?? '08:00:00') : ($settings['qr_afternoon_start'] ?? '13:00:00');
        $lateThreshold = (int) ($settings['late_threshold_minutes'] ?? 30);
        $thresholdTime = date('H:i:s', strtotime($startTime) + ($lateThreshold * 60));
        $status = $time > $thresholdTime ? 'late' : 'present';
        DB::transaction(function () use (&$attendance, $employee, $qr, $time, $status) {
            if (! $attendance) {
                $id = DB::table('attendance')->insertGetId(['employee_id' => $employee->id, 'attendance_date' => today(), 'qr_code_used' => $qr->qr_code, $qr->qr_type === 'morning' ? 'morning_time_in' : 'afternoon_time_in' => $time, $qr->qr_type === 'morning' ? 'status_morning' : 'status_afternoon' => $status, 'created_at' => now()]);
                $attendance = DB::table('attendance')->where('id', $id)->first(); return;
            }
            $in = $qr->qr_type === 'morning' ? 'morning_time_in' : 'afternoon_time_in'; $out = $qr->qr_type === 'morning' ? 'morning_time_out' : 'afternoon_time_out';
            DB::table('attendance')->where('id', $attendance->id)->update([$attendance->$in ? $out : $in => $time, $attendance->$in ? 'total_hours' : ($qr->qr_type === 'morning' ? 'status_morning' : 'status_afternoon') => $attendance->$in ? DB::raw('total_hours') : $status, 'qr_code_used' => $qr->qr_code]);
        });
        $scanType = $qr->qr_type === 'morning' ? 'Time In' : 'Time Out';
        $formattedTime = $now->format('h:i A');
        return response()->json(['success' => true, 'message' => 'Attendance recorded.', 'status' => $status, 'scan_type' => $scanType, 'time' => $formattedTime, 'employee_name' => $employee->full_name, 'position' => $employee->position]);
    }
}
