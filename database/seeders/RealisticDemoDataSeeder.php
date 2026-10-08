<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Replaces legacy TEST-* rows with consistent, clearly identifiable demo data.
 * It never changes client or employee records outside the JFT-DEMO prefixes.
 */
class RealisticDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $plan = DB::table('internet_plans')->where('status', 'active')->where('archived', 0)->orderBy('monthly_price')->first();
        if (! $plan) {
            throw new \RuntimeException('Create an active internet plan before loading demo data.');
        }

        $demoClients = [
            ['code' => 'JFT-DEMO-001', 'name' => 'Ramon Villanueva', 'barangay' => 1, 'phone' => '09171230001', 'email' => 'ramon.villanueva@demo.jftech.local', 'status' => 'active'],
            ['code' => 'JFT-DEMO-002', 'name' => 'Grace Mendoza', 'barangay' => 2, 'phone' => '09171230002', 'email' => 'grace.mendoza@demo.jftech.local', 'status' => 'active'],
            ['code' => 'JFT-DEMO-003', 'name' => 'Noel Cabrera', 'barangay' => 3, 'phone' => '09171230003', 'email' => 'noel.cabrera@demo.jftech.local', 'status' => 'active'],
            ['code' => 'JFT-DEMO-004', 'name' => 'Lorna Agustin', 'barangay' => 4, 'phone' => '09171230004', 'email' => 'lorna.agustin@demo.jftech.local', 'status' => 'active'],
            ['code' => 'JFT-DEMO-005', 'name' => 'Dennis Pangan', 'barangay' => 5, 'phone' => '09171230005', 'email' => 'dennis.pangan@demo.jftech.local', 'status' => 'suspended'],
            ['code' => 'JFT-DEMO-006', 'name' => 'Marites Flores', 'barangay' => 6, 'phone' => '09171230006', 'email' => 'marites.flores@demo.jftech.local', 'status' => 'inactive'],
        ];

        DB::transaction(function () use ($demoClients, $plan, $today): void {
            $legacyIds = DB::table('clients')
                ->where('client_code', 'like', 'TEST-%')
                ->orWhere('client_code', 'like', 'JFT-DEMO-%')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if (count($legacyIds) < count($demoClients)) {
                throw new \RuntimeException('Expected six legacy TEST client records before replacing demo data.');
            }
            $legacyIds = array_slice($legacyIds, 0, count($demoClients));

            // Remove bills and payments belonging only to the old TEST/demo records.
            DB::table('payments')->whereIn('client_id', $legacyIds)->delete();
            DB::table('bills')->whereIn('client_id', $legacyIds)->delete();

            foreach ($demoClients as $index => $client) {
                $id = $legacyIds[$index];
                DB::table('clients')->where('id', $id)->update([
                    'client_code' => $client['code'],
                    'full_name' => $client['name'],
                    'contact_number' => $client['phone'],
                    'email' => $client['email'],
                    'barangay_id' => $client['barangay'],
                    'exact_address' => 'Purok ' . ($index + 1) . ', Balilihan, Bohol',
                    'wifi_plan' => $plan->plan_name,
                    'internet_plan_id' => $plan->id,
                    'monthly_rate' => $plan->monthly_price,
                    'installation_fee' => $plan->installation_fee,
                    'installation_date' => $today->copy()->subMonths(3 + $index)->toDateString(),
                    'status' => $client['status'],
                    'archived' => 0,
                ]);

                $amount = (float) $plan->monthly_price;
                $overdueBillId = DB::table('bills')->insertGetId([
                    'client_id' => $id, 'bill_number' => 'BILL-' . $today->copy()->subMonth()->startOfMonth()->format('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
                    'billing_period_start' => $today->copy()->subMonth()->startOfMonth()->toDateString(),
                    'billing_period_end' => $today->copy()->subMonth()->endOfMonth()->toDateString(),
                    'amount' => $amount, 'due_date' => $today->copy()->subMonth()->endOfMonth()->toDateString(),
                    'status' => $index < 3 ? 'overdue' : 'paid', 'notes' => 'Realistic demo billing record.', 'created_at' => $today->copy()->subMonth(),
                ]);
                if ($index >= 3) {
                    DB::table('payments')->insert(['client_id' => $id, 'bill_id' => $overdueBillId, 'amount' => $amount, 'payment_date' => $today->copy()->subDays(6)->toDateString(), 'payment_method' => $index % 2 ? 'gcash' : 'cash', 'reference_number' => 'DEMO-PAY-' . ($index + 1), 'notes' => 'Realistic demo payment.', 'created_at' => $today->copy()->subDays(6)]);
                }
                DB::table('bills')->insert([
                    'client_id' => $id, 'bill_number' => 'BILL-' . $today->copy()->startOfMonth()->format('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
                    'billing_period_start' => $today->copy()->startOfMonth()->toDateString(),
                    'billing_period_end' => $today->copy()->endOfMonth()->toDateString(),
                    'amount' => $amount, 'due_date' => $today->copy()->endOfMonth()->toDateString(),
                    'status' => 'pending', 'notes' => 'Realistic demo current bill.', 'created_at' => $today,
                ]);
            }

            $employees = [
                ['code' => 'EMP-DEMO-T1', 'name' => 'Carlo Domingo', 'position' => 'Technician', 'rate' => 500, 'email' => 'carlo.domingo@demo.jftech.local'],
                ['code' => 'EMP-DEMO-T2', 'name' => 'Jessa Morales', 'position' => 'Technician II', 'rate' => 580, 'email' => 'jessa.morales@demo.jftech.local'],
                ['code' => 'EMP-DEMO-ADM', 'name' => 'Micaela Torres', 'position' => 'Admin', 'rate' => 600, 'email' => 'micaela.torres@demo.jftech.local'],
            ];
            $employeeIds = [];
            foreach ($employees as $employee) {
                DB::table('employees')->updateOrInsert(['employee_code' => $employee['code']], [
                    'full_name' => $employee['name'], 'position' => $employee['position'], 'department' => 'Operations',
                    'contact_number' => '09171231' . substr($employee['rate'], 0, 3), 'email' => $employee['email'],
                    'address' => 'Balilihan, Bohol', 'date_hired' => $today->copy()->subMonths(8)->toDateString(),
                    'daily_rate' => $employee['rate'], 'status' => 'active',
                ]);
                $employeeIds[] = DB::table('employees')->where('employee_code', $employee['code'])->value('id');
            }

            DB::table('payroll')->whereIn('employee_id', $employeeIds)->delete();
            DB::table('attendance')->whereIn('employee_id', $employeeIds)->delete();
            $periodStart = $today->copy()->startOfWeek(Carbon::MONDAY);
            $periodEnd = $periodStart->copy()->addDays(6);
            foreach ($employees as $index => $employee) {
                $employeeId = $employeeIds[$index];
                $lateSessions = $index === 1 ? 1 : 0;
                $absentSessions = $index === 2 ? 1 : 0;
                for ($day = 0; $day < 5; $day++) {
                    $date = $periodStart->copy()->addDays($day);
                    $isLate = $lateSessions > 0 && $day === 1;
                    $isAbsent = $absentSessions > 0 && $day === 3;
                    DB::table('attendance')->insert([
                        'employee_id' => $employeeId, 'attendance_date' => $date->toDateString(),
                        'morning_time_in' => $isAbsent ? null : ($isLate ? '08:40:00' : '08:00:00'),
                        'morning_time_out' => $isAbsent ? null : '12:00:00',
                        'afternoon_time_in' => '13:00:00', 'afternoon_time_out' => '17:00:00',
                        'status_morning' => $isAbsent ? 'absent' : ($isLate ? 'late' : 'present'),
                        'status_afternoon' => 'present', 'total_hours' => $isAbsent ? 4 : 8,
                        'notes' => 'Realistic demo attendance record.', 'created_at' => $date,
                    ]);
                }

                $daysWorked = 5;
                $basic = $daysWorked * $employee['rate'];
                $late = $lateSessions * ($employee['rate'] / 8);
                $absent = $absentSessions * ($employee['rate'] / 2);
                $statutory = match ($employee['rate']) {
                    500 => ['sss' => 125, 'philhealth' => 75, 'pagibig' => 50],
                    580 => ['sss' => 175, 'philhealth' => 87, 'pagibig' => 50],
                    600 => ['sss' => 181.25, 'philhealth' => 90, 'pagibig' => 50],
                };
                $deductions = $late + $absent + array_sum($statutory);
                DB::table('payroll')->insert([
                    'employee_id' => $employeeId, 'payroll_period_start' => $periodStart->toDateString(), 'payroll_period_end' => $periodEnd->toDateString(),
                    'total_days_worked' => $daysWorked, 'total_hours' => 40 - ($isAbsent ? 4 : 0), 'daily_rate' => $employee['rate'],
                    'monthly_basic_salary' => $employee['rate'] * 26, 'hourly_rate' => $employee['rate'] / 8,
                    'overtime_hours' => 0, 'late' => $late, 'undertime' => 0, 'absent' => $absent, 'basic_salary' => $basic,
                    'overtime_pay' => 0, 'regular_holiday' => 0, 'special_holiday' => 0, 'night_differential' => 0, 'incentives' => 0, 'allowances' => 0, 'leave_pay' => 0, 'rest_day_pay' => 0, 'salary_adjustment' => 0, 'other_earnings' => 0,
                    'gross_pay' => $basic, 'sss' => $statutory['sss'], 'philhealth' => $statutory['philhealth'], 'pagibig' => $statutory['pagibig'],
                    'withholding_tax' => 0, 'cash_advance' => 0, 'salary_loan' => 0, 'hdmf_loan' => 0, 'other_deductions' => 0,
                    'deductions' => $deductions, 'net_pay' => $basic - $deductions, 'status' => 'processed', 'archived' => 0, 'created_at' => $periodEnd,
                ]);
            }
        });
    }
}
