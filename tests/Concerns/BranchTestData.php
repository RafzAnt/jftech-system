<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An in-memory two-branch company (Balilihan and Loon) for the branch access tests.
 *
 * Ids: clients 1 (Balilihan), 2 and 3 (Loon); employees 1 (Balilihan), 2 (Loon);
 * users 1 super admin, 2 Balilihan admin, 3 Loon admin, 4 admin without a branch, 5 employee account.
 */
trait BranchTestData
{
    protected function createBranchSchema(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('role')->default('employee');
            $table->string('branch_name', 100)->nullable();
            $table->string('avatar')->nullable();
            $table->string('status')->default('active');
            $table->boolean('archived')->default(false);
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });
        Schema::create('barangays', function ($table) {
            $table->id();
            $table->string('barangay_name');
        });
        Schema::create('internet_plans', function ($table) {
            $table->id();
            $table->string('plan_name');
            $table->integer('speed_mbps')->default(10);
            $table->decimal('monthly_price', 10, 2)->default(999);
            $table->decimal('installation_fee', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->boolean('archived')->default(false);
        });
        Schema::create('clients', function ($table) {
            $table->id();
            $table->string('client_code')->nullable();
            $table->string('full_name');
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->string('wifi_plan')->nullable();
            $table->unsignedBigInteger('internet_plan_id')->nullable();
            $table->decimal('monthly_rate', 10, 2)->default(999);
            $table->decimal('installation_fee', 10, 2)->default(0);
            $table->date('installation_date')->nullable();
            $table->string('exact_address')->nullable();
            $table->string('status')->default('active');
            $table->boolean('archived')->default(false);
            $table->string('branch_name', 100)->nullable();
            $table->string('registered_barangay_name')->nullable();
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->timestamps();
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
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
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
            $table->string('submission_token')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('employees', function ($table) {
            $table->id();
            $table->string('employee_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('branch_name', 100)->default('Balilihan');
            $table->string('department')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->date('date_hired')->nullable();
            $table->decimal('daily_rate', 10, 2)->default(500);
            $table->string('status')->default('active');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('attendance', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');
            $table->time('morning_time_in')->nullable();
            $table->time('morning_time_out')->nullable();
            $table->time('afternoon_time_in')->nullable();
            $table->time('afternoon_time_out')->nullable();
            $table->string('status_morning')->nullable();
            $table->string('status_afternoon')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->string('qr_code_used')->nullable();
            $table->string('notes')->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('payroll', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('payroll_period_start');
            $table->date('payroll_period_end');
            $table->integer('total_days_worked')->default(0);
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->string('status')->default('processed');
            $table->boolean('archived')->default(false);
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('client_plan_change_histories', function ($table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('previous_plan_name')->nullable();
            $table->decimal('previous_monthly_rate', 10, 2)->default(0);
            $table->string('new_plan_name')->nullable();
            $table->decimal('new_monthly_rate', 10, 2)->default(0);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('system_settings', function ($table) {
            $table->string('setting_key');
            $table->string('setting_value')->nullable();
        });
        Schema::create('qr_codes', function ($table) {
            $table->id();
            $table->string('qr_code');
            $table->string('qr_type');
            $table->date('valid_date');
            $table->time('valid_from')->nullable();
            $table->time('valid_until')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('login_history', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username');
            $table->string('full_name')->nullable();
            $table->string('role')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('status')->default('success');
            $table->string('error_message')->nullable();
            $table->timestamp('login_time')->nullable();
            $table->timestamp('logout_time')->nullable();
        });
    }

    protected function seedBranchData(): void
    {
        $password = password_hash('secret123', PASSWORD_DEFAULT);
        DB::table('users')->insert([
            ['id' => 1, 'username' => 'super', 'password' => $password, 'full_name' => 'Super Admin', 'role' => 'super_admin', 'branch_name' => null],
            ['id' => 2, 'username' => 'bali', 'password' => $password, 'full_name' => 'Bali Admin', 'role' => 'admin', 'branch_name' => 'Balilihan'],
            ['id' => 3, 'username' => 'loon', 'password' => $password, 'full_name' => 'Loon Admin', 'role' => 'admin', 'branch_name' => 'Loon'],
            ['id' => 4, 'username' => 'nobranch', 'password' => $password, 'full_name' => 'No Branch Admin', 'role' => 'admin', 'branch_name' => null],
            ['id' => 5, 'username' => 'tech', 'password' => $password, 'full_name' => 'Technician', 'role' => 'employee', 'branch_name' => 'Balilihan'],
        ]);

        DB::table('clients')->insert([
            ['id' => 1, 'client_code' => 'JFT-1', 'full_name' => 'Ana Reyes', 'branch_name' => 'Balilihan', 'registered_barangay_name' => 'Boctol', 'status' => 'active', 'archived' => false],
            ['id' => 2, 'client_code' => 'JFT-2', 'full_name' => 'Ben Cruz', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Agsoso', 'status' => 'active', 'archived' => false],
            ['id' => 3, 'client_code' => 'JFT-3', 'full_name' => 'Carl Dela Cruz', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Agsoso', 'status' => 'inactive', 'archived' => true],
        ]);

        DB::table('bills')->insert([
            ['id' => 1, 'client_id' => 1, 'bill_number' => 'B-1', 'billing_period_start' => now()->startOfMonth()->toDateString(), 'billing_period_end' => now()->endOfMonth()->toDateString(), 'amount' => 1000, 'due_date' => now()->endOfMonth()->toDateString(), 'status' => 'paid'],
            ['id' => 2, 'client_id' => 2, 'bill_number' => 'B-2', 'billing_period_start' => now()->startOfMonth()->toDateString(), 'billing_period_end' => now()->endOfMonth()->toDateString(), 'amount' => 2000, 'due_date' => now()->endOfMonth()->toDateString(), 'status' => 'paid'],
            ['id' => 3, 'client_id' => 2, 'bill_number' => 'B-3', 'billing_period_start' => now()->startOfMonth()->toDateString(), 'billing_period_end' => now()->endOfMonth()->toDateString(), 'amount' => 500, 'due_date' => now()->subDay()->toDateString(), 'status' => 'overdue'],
        ]);

        DB::table('payments')->insert([
            ['id' => 1, 'client_id' => 1, 'bill_id' => 1, 'amount' => 1000, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash'],
            ['id' => 2, 'client_id' => 2, 'bill_id' => 2, 'amount' => 2000, 'payment_date' => now()->toDateString(), 'payment_method' => 'gcash'],
        ]);

        DB::table('employees')->insert([
            ['id' => 1, 'employee_code' => 'EMP-1', 'full_name' => 'Eli Santos', 'position' => 'Technician I', 'branch_name' => 'Balilihan', 'status' => 'active'],
            ['id' => 2, 'employee_code' => 'EMP-2', 'full_name' => 'Fay Garcia', 'position' => 'Technician II', 'branch_name' => 'Loon', 'status' => 'active'],
        ]);

        DB::table('attendance')->insert([
            ['id' => 1, 'employee_id' => 1, 'attendance_date' => now()->toDateString(), 'morning_time_in' => '08:00:00', 'status_morning' => 'present', 'branch_name' => 'Balilihan'],
            ['id' => 2, 'employee_id' => 2, 'attendance_date' => now()->toDateString(), 'morning_time_in' => '08:05:00', 'status_morning' => 'present', 'branch_name' => 'Loon'],
        ]);

        DB::table('payroll')->insert([
            ['id' => 1, 'employee_id' => 1, 'payroll_period_start' => now()->startOfMonth()->toDateString(), 'payroll_period_end' => now()->endOfMonth()->toDateString(), 'net_pay' => 3000, 'status' => 'processed'],
            ['id' => 2, 'employee_id' => 2, 'payroll_period_start' => now()->startOfMonth()->toDateString(), 'payroll_period_end' => now()->endOfMonth()->toDateString(), 'net_pay' => 4000, 'status' => 'processed'],
        ]);
    }

    protected function signIn(int $userId): User
    {
        $user = User::findOrFail($userId);
        $this->actingAs($user);

        return $user;
    }
}
