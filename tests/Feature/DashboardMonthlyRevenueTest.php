<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardMonthlyRevenueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('barangays', function ($table) {
            $table->id();
            $table->string('barangay_name');
        });

        Schema::create('clients', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('barangay_id')->nullable();
        });

        Schema::create('employees', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('status')->default('active');
        });

        Schema::create('attendance', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');
            $table->time('morning_time_in')->nullable();
        });

        Schema::create('bills', function ($table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('bill_number');
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->string('status')->default('pending');
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('username');
            $table->string('status')->default('active');
        });

        Schema::create('payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('cash');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('login_history', function ($table) {
            $table->id();
            $table->string('username');
            $table->string('role')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('login_time')->nullable();
            $table->string('status')->default('success');
        });

        DB::table('barangays')->insert([
            ['barangay_name' => 'Barangay A'],
            ['barangay_name' => 'Barangay B'],
        ]);

        DB::table('clients')->insert([
            ['id' => 1, 'full_name' => 'Client One', 'status' => 'active', 'barangay_id' => 1],
            ['id' => 2, 'full_name' => 'Client Two', 'status' => 'active', 'barangay_id' => 2],
        ]);

        DB::table('employees')->insert([
            ['id' => 1, 'full_name' => 'Employee One', 'status' => 'active'],
        ]);

        DB::table('attendance')->insert([
            ['id' => 1, 'employee_id' => 1, 'attendance_date' => date('Y-m-d'), 'morning_time_in' => '08:15:00'],
        ]);

        DB::table('users')->insert([
            ['id' => 1, 'full_name' => 'System Admin', 'username' => 'admin', 'status' => 'active'],
        ]);

        DB::table('login_history')->insert([
            ['id' => 1, 'username' => 'admin', 'role' => 'admin', 'ip_address' => '127.0.0.1', 'login_time' => now(), 'status' => 'success'],
        ]);

        $months = [
            ['month' => '2026-04-01', 'amount' => 2000],
            ['month' => '2026-05-01', 'amount' => 3500],
            ['month' => '2026-06-01', 'amount' => 1800],
            ['month' => '2026-07-01', 'amount' => 4200],
            ['month' => '2026-08-01', 'amount' => 3900],
            ['month' => '2026-09-01', 'amount' => 5000],
            ['month' => '2025-12-01', 'amount' => 1500],
        ];

        foreach ($months as $index => $month) {
            DB::table('bills')->insert([
                'id' => $index + 1,
                'client_id' => 1,
                'bill_number' => 'BILL-' . ($index + 1),
                'billing_period_start' => $month['month'],
                'billing_period_end' => date('Y-m-t', strtotime($month['month'])),
                'amount' => $month['amount'],
                'due_date' => date('Y-m-t', strtotime($month['month'])),
                'status' => 'paid',
            ]);
        }

        DB::table('bills')->insert([
            'id' => 8,
            'client_id' => 1,
            'bill_number' => 'BILL-8',
            'billing_period_start' => '2026-04-15',
            'billing_period_end' => '2026-04-30',
            'amount' => 750,
            'due_date' => '2026-04-30',
            'status' => 'paid',
        ]);
    }

    public function test_dashboard_revenue_chart_uses_current_calendar_year(): void
    {
        $action = require app_path('Support/PageActions/dashboard.php');
        $result = $action(new Request());

        $this->assertCount(12, $result['monthlyRevenue']);

        $expected = [
            'Jan' => 0.0,
            'Feb' => 0.0,
            'Mar' => 0.0,
            'Apr' => 2750.0,
            'May' => 3500.0,
            'Jun' => 1800.0,
            'Jul' => 4200.0,
            'Aug' => 3900.0,
            'Sep' => 5000.0,
            'Oct' => 0.0,
            'Nov' => 0.0,
            'Dec' => 0.0,
        ];

        $actual = [];
        foreach ($result['monthlyRevenue'] as $entry) {
            $actual[$entry['label']] = (float) $entry['total'];
        }

        $this->assertSame($expected, $actual);
    }
}
