<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

class ResetBusinessDataTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();

        DB::table('system_settings')->insert(['setting_key' => 'late_threshold_minutes', 'setting_value' => '30']);
        DB::table('internet_plans')->insert(['plan_name' => 'Starter', 'monthly_price' => 999]);
        DB::table('qr_codes')->insert(['qr_code' => 'QR-1', 'qr_type' => 'morning', 'valid_date' => now()->toDateString()]);

        \App\Support\NapBoxes::create('Balilihan', 'NAP-01', 'Boctol', null, 2);
        DB::table('nap_ports')->where('id', 1)->update(['status' => 'occupied', 'client_id' => 1]);
        DB::table('work_requests')->insert(['id' => 1, 'request_code' => 'WR-1', 'branch_name' => 'Balilihan', 'employee_id' => 1, 'work_type' => 'repair', 'client_id' => 1, 'status' => 'pending_approval']);
        DB::table('work_request_materials')->insert(['work_request_id' => 1, 'item_name' => 'Router', 'used_quantity' => 1]);
        $this->appToken(1);
    }

    public function test_it_empties_the_branch_data_and_employee_accounts_but_keeps_the_rest(): void
    {
        $this->artisan('jftech:reset-business-data', ['--force' => true])->assertSuccessful();

        foreach (['clients', 'bills', 'payments', 'employees', 'attendance', 'payroll', 'inventory_requests', 'work_requests', 'work_request_materials', 'employee_api_tokens'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} should be empty");
        }

        // The NAP boxes stay, but no port is connected to a client any more.
        $this->assertSame(1, DB::table('nap_boxes')->count());
        $this->assertSame(2, DB::table('nap_ports')->where('status', 'available')->whereNull('client_id')->count());

        // Admin accounts stay; the employee (mobile app) account goes with its employee.
        $this->assertSame(['bali', 'loon', 'nobranch', 'super'], DB::table('users')->orderBy('username')->pluck('username')->all());
        $this->assertSame(1, DB::table('system_settings')->count());
        $this->assertSame(1, DB::table('internet_plans')->count());
        $this->assertSame(1, DB::table('qr_codes')->count());
    }

    public function test_the_dashboard_is_empty_for_the_super_admin_afterwards(): void
    {
        $this->artisan('jftech:reset-business-data', ['--force' => true])->assertSuccessful();
        $this->signIn(1);
        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $stats = (require app_path('Support/PageActions/dashboard.php'))($request)['stats'];

        $this->assertSame(0, (int) $stats['total_clients']);
        $this->assertSame(0, (int) $stats['total_employees']);
        $this->assertEquals(0, $stats['total_revenue']);
    }

    public function test_it_asks_for_the_word_reset_and_deletes_nothing_otherwise(): void
    {
        $this->artisan('jftech:reset-business-data')
            ->expectsQuestion('Type RESET to permanently delete this data', 'yes')
            ->expectsOutputToContain('Nothing was deleted.')
            ->assertFailed();

        $this->assertSame(3, DB::table('clients')->count());
        $this->assertSame(2, DB::table('employees')->count());
        $this->assertSame(5, DB::table('users')->count());
    }

    public function test_typing_reset_confirms_it(): void
    {
        $this->artisan('jftech:reset-business-data')
            ->expectsQuestion('Type RESET to permanently delete this data', 'RESET')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('clients')->count());
    }
}
