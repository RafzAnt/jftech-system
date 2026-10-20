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
            $table->time('overtime_time_in')->nullable();
            $table->time('overtime_time_out')->nullable();
            $table->unsignedInteger('overtime_minutes')->default(0);
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
            $table->decimal('total_days_worked', 5, 2)->default(0);
            $table->decimal('total_hours', 8, 2)->default(0);
            foreach ([
                'daily_rate', 'monthly_basic_salary', 'hourly_rate', 'overtime_hours', 'absent', 'basic_salary',
                'overtime_pay', 'regular_holiday', 'special_holiday', 'night_differential', 'incentives', 'allowances', 'leave_pay',
                'rest_day_pay', 'salary_adjustment', 'other_earnings', 'late', 'undertime', 'sss', 'philhealth', 'pagibig',
                'withholding_tax', 'cash_advance', 'salary_loan', 'hdmf_loan', 'other_deductions',
            ] as $column) {
                $table->decimal($column, 12, 2)->default(0);
            }
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->string('computation_mode', 10)->nullable();
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
            $table->id();
            $table->string('setting_key');
            $table->string('setting_value')->nullable();
            $table->string('setting_label')->nullable();
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
        Schema::create('inventory', function ($table) {
            $table->id();
            $table->string('item_name');
            $table->string('sku')->nullable();
            $table->string('category');
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('supplier')->nullable();
            $table->integer('reorder_level')->default(5);
            $table->string('status')->default('active');
            $table->boolean('archived')->default(false);
            $table->string('branch_name', 100)->default('Balilihan');
            $table->timestamp('created_at')->nullable();
            $table->unique(['branch_name', 'item_name', 'category']);
        });
        Schema::create('inventory_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_name');
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->string('item_name');
            $table->integer('quantity');
            $table->text('comments')->nullable();
            $table->string('status')->default('pending');
            $table->string('request_type', 10)->default('out');
            $table->string('item_condition', 30)->nullable();
            $table->string('area_used', 150)->nullable();
            $table->string('person_in_charge', 100)->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('nap_boxes', function ($table) {
            $table->id();
            $table->string('name', 50);
            $table->string('branch_name', 100);
            $table->string('barangay_name', 100);
            $table->string('location', 255)->nullable();
            $table->unsignedSmallInteger('total_ports');
            $table->string('status', 20)->default('active');
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->unique(['branch_name', 'name']);
        });
        Schema::create('nap_box_photos', function ($table) {
            $table->unsignedBigInteger('nap_box_id')->primary();
            $table->string('mime_type', 30);
            $table->longText('data');
            $table->timestamps();
        });
        Schema::create('nap_ports', function ($table) {
            $table->id();
            $table->unsignedBigInteger('nap_box_id');
            $table->unsignedSmallInteger('port_number');
            $table->string('status', 20)->default('available');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->timestamp('occupied_at')->nullable();
            $table->timestamps();
            $table->unique(['nap_box_id', 'port_number']);
        });
        Schema::create('work_requests', function ($table) {
            $table->id();
            $table->string('request_code', 30)->unique();
            $table->string('branch_name', 100);
            $table->unsignedBigInteger('employee_id');
            $table->string('work_type', 20);
            $table->string('status', 30)->default('pending_approval');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('new_client_name', 100)->nullable();
            $table->string('new_client_phone', 20)->nullable();
            $table->text('new_client_address')->nullable();
            $table->string('barangay_name', 100)->nullable();
            $table->text('location')->nullable();
            $table->text('problem_details')->nullable();
            $table->unsignedBigInteger('nap_box_id')->nullable();
            $table->unsignedBigInteger('nap_port_id')->nullable();
            $table->unsignedBigInteger('actual_nap_box_id')->nullable();
            $table->unsignedBigInteger('actual_nap_port_id')->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('work_request_materials', function ($table) {
            $table->id();
            $table->unsignedBigInteger('work_request_id');
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->string('item_name', 100);
            $table->unsignedInteger('used_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->timestamps();
        });
        Schema::create('employee_api_tokens', function ($table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('login_history_id')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('inventory_stock_movements', function ($table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->integer('quantity_change');
            $table->integer('balance_after');
            $table->string('movement_type');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('nap_box_id')->nullable();
            $table->unsignedBigInteger('work_request_id')->nullable();
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

        DB::table('inventory')->insert([
            ['id' => 1, 'item_name' => 'Router', 'sku' => 'SKU-1', 'category' => 'Network', 'quantity' => 10, 'unit_price' => 500, 'reorder_level' => 2, 'branch_name' => 'Balilihan'],
            ['id' => 2, 'item_name' => 'Router', 'sku' => 'SKU-2', 'category' => 'Network', 'quantity' => 4, 'unit_price' => 500, 'reorder_level' => 2, 'branch_name' => 'Loon'],
            ['id' => 3, 'item_name' => 'ONU', 'sku' => 'SKU-3', 'category' => 'Network', 'quantity' => 6, 'unit_price' => 800, 'reorder_level' => 2, 'branch_name' => 'Loon'],
        ]);
        DB::table('inventory_requests')->insert([
            ['id' => 1, 'employee_id' => 1, 'employee_name' => 'Eli Santos', 'inventory_id' => 1, 'item_name' => 'Router', 'quantity' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'employee_id' => 2, 'employee_name' => 'Fay Garcia', 'inventory_id' => 2, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('inventory_stock_movements')->insert([
            ['id' => 1, 'inventory_id' => 1, 'quantity_change' => 10, 'balance_after' => 10, 'movement_type' => 'opening_balance', 'created_at' => now()],
            ['id' => 2, 'inventory_id' => 2, 'quantity_change' => 4, 'balance_after' => 4, 'movement_type' => 'opening_balance', 'created_at' => now()],
        ]);
        DB::table('payroll')->insert([
            ['id' => 1, 'employee_id' => 1, 'payroll_period_start' => now()->startOfMonth()->toDateString(), 'payroll_period_end' => now()->endOfMonth()->toDateString(), 'net_pay' => 3000, 'status' => 'processed'],
            ['id' => 2, 'employee_id' => 2, 'payroll_period_start' => now()->startOfMonth()->toDateString(), 'payroll_period_end' => now()->endOfMonth()->toDateString(), 'net_pay' => 4000, 'status' => 'processed'],
        ]);
    }

    /** A token of the employee app for an employee (what employee_login.php hands out). */
    protected function appToken(int $employeeId): string
    {
        return \App\Support\EmployeeTokens::issue($employeeId);
    }

    /** Request headers of the employee app for an employee. */
    protected function appHeaders(int $employeeId): array
    {
        return ['Authorization' => 'Bearer ' . $this->appToken($employeeId), 'Accept' => 'application/json'];
    }

    protected function signIn(int $userId): User
    {
        $user = User::findOrFail($userId);
        $this->actingAs($user);

        return $user;
    }
}
