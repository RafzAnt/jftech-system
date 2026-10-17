<?php

namespace Tests\Feature;

use App\Http\Controllers\PriceManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Stock, stock requests, user accounts, login history, prices and settings are in every admin's sidebar.
 * A branch admin works on their own branch only; the super admin looks at every branch and can pick one.
 */
class BranchCompanyPagesTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();

        // MySQL's GREATEST() is used by the stock queries.
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
    }

    /** Run a page action the way PageController does and return what it hands to the view. */
    private function page(string $page, array $query = [], array $post = []): array
    {
        $uri = '/' . $page . ($query ? '?' . http_build_query($query) : '');
        $request = Request::create($uri, $post ? 'POST' : 'GET', $post);
        $request->setLaravelSession(app('session')->driver('array'));
        app()->instance('request', $request);

        $action = require app_path("Support/PageActions/{$page}.php");

        return $action($request);
    }

    private function ids(iterable $rows, string $key = 'id'): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) (is_array($row) ? $row[$key] : $row->$key);
        }
        sort($ids);

        return $ids;
    }

    private function stockForm(array $extra = []): array
    {
        return $extra + ['item_name' => 'Cable Tie', 'category' => 'Supplies', 'quantity' => 20, 'unit_price' => 5, 'reorder_level' => 3, 'submission_token' => 'tok'];
    }

    // ---- stock ------------------------------------------------------------------------------------

    public function test_a_branch_admin_sees_only_their_branchs_stock(): void
    {
        $this->signIn(2);
        $response = $this->get('/inventory')->assertOk();

        $this->assertSame([1], $this->ids($response->viewData('inventoryItems')));
        $this->assertTrue($response->viewData('canManageInventory'));
        $this->assertSame([1], $this->ids($response->viewData('movements'), 'id'));
    }

    public function test_the_super_admin_sees_every_branchs_stock_and_can_pick_one(): void
    {
        $this->signIn(1);
        $all = $this->get('/inventory')->assertOk();
        $this->assertSame([1, 2, 3], $this->ids($all->viewData('inventoryItems')));
        $this->assertFalse($all->viewData('canManageInventory'), 'the super admin only looks at stock');

        $loon = $this->get('/inventory?branch=loon')->assertOk();
        $this->assertSame([2, 3], $this->ids($loon->viewData('inventoryItems')));
        $this->assertSame('Loon', $loon->viewData('branch'));
    }

    public function test_a_branch_admin_cannot_pick_another_branchs_stock(): void
    {
        $this->signIn(2);

        $this->assertSame([1], $this->ids($this->get('/inventory?branch=Loon')->viewData('inventoryItems')));
    }

    public function test_a_new_item_is_kept_in_the_branch_of_the_admin_who_adds_it(): void
    {
        $this->signIn(2);
        $this->withSession(['inventory_submission_token' => 'tok'])->post('/inventory', $this->stockForm())->assertSessionHas('success');

        $item = DB::table('inventory')->where('item_name', 'Cable Tie')->first();
        $this->assertSame('Balilihan', $item->branch_name);
        $this->assertStringStartsWith('SKU-', (string) $item->sku);
        $this->assertSame(1, DB::table('inventory_stock_movements')->where('inventory_id', $item->id)->count());
    }

    public function test_an_item_name_only_has_to_be_unique_within_the_branch(): void
    {
        // Balilihan already has a Router, so a second one is refused there ...
        $this->signIn(2);
        $this->withSession(['inventory_submission_token' => 'tok'])->post('/inventory', $this->stockForm(['item_name' => 'router', 'category' => 'Network']))
            ->assertSessionHasErrors('item_name');
        $this->assertSame(1, DB::table('inventory')->where('branch_name', 'Balilihan')->count());

        // ... but only Loon has an ONU, so Balilihan may stock one too.
        $this->withSession(['inventory_submission_token' => 'tok'])->post('/inventory', $this->stockForm(['item_name' => 'ONU', 'category' => 'Network']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('inventory')->where('item_name', 'ONU')->where('branch_name', 'Balilihan')->count());
        $this->assertSame(2, DB::table('inventory')->where('item_name', 'ONU')->count());
    }

    public function test_the_super_admin_cannot_change_stock(): void
    {
        $this->signIn(1);

        $this->withSession(['inventory_submission_token' => 'tok'])->post('/inventory', $this->stockForm())->assertForbidden();
        $this->post('/inventory/1/movement', ['movement_type' => 'stock_received', 'quantity' => 5, 'remarks' => 'x'])->assertForbidden();
        $this->post('/inventory/1/archive')->assertForbidden();
        $this->assertSame(10, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
        $this->assertSame(0, (int) DB::table('inventory')->where('id', 1)->value('archived'));
    }

    public function test_a_branch_admin_cannot_touch_another_branchs_item(): void
    {
        $this->signIn(2);

        $this->post('/inventory/2/movement', ['movement_type' => 'stock_received', 'quantity' => 5, 'remarks' => 'x'])->assertNotFound();
        $this->post('/inventory/2/archive')->assertNotFound();
        $this->delete('/inventory/2')->assertNotFound();
        $this->withSession(['inventory_submission_token' => 'tok'])->post('/inventory', $this->stockForm(['item_id' => 2, 'item_name' => 'Router', 'category' => 'Network']))->assertNotFound();
        $this->assertSame(4, (int) DB::table('inventory')->where('id', 2)->value('quantity'));
        $this->assertSame(0, (int) DB::table('inventory')->where('id', 2)->value('archived'));
    }

    public function test_a_branch_admin_updates_their_own_stock(): void
    {
        $this->signIn(2);
        $this->post('/inventory/1/movement', ['movement_type' => 'stock_received', 'quantity' => 5, 'remarks' => 'Delivery'])->assertSessionHas('success');

        $this->assertSame(15, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    public function test_the_printed_stock_list_is_limited_to_the_branch(): void
    {
        $this->signIn(2);
        $this->assertSame([1], $this->ids($this->page('print_inventory')['inventoryItems']));

        $this->signIn(1);
        $this->assertSame([1, 2, 3], $this->ids($this->page('print_inventory')['inventoryItems']));
        $this->assertSame([2, 3], $this->ids($this->page('print_inventory', ['branch' => 'Loon'])['inventoryItems']));
    }

    // ---- stock requests ---------------------------------------------------------------------------

    public function test_stock_requests_are_listed_by_the_technicians_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('inventory_requests');
        $this->assertSame([1], $this->ids($page['requests']));
        $this->assertSame(1, $page['pendingCount']);
        $this->assertTrue($page['canDecideRequests']);

        $this->signIn(1);
        $this->assertSame([1, 2], $this->ids($this->page('inventory_requests')['requests']));
        $loon = $this->page('inventory_requests', ['branch' => 'Loon']);
        $this->assertSame([2], $this->ids($loon['requests']));
        $this->assertSame(1, $loon['pendingCount']);
        $this->assertFalse($loon['canDecideRequests']);
    }

    public function test_a_branch_admin_approves_their_branchs_request_and_the_stock_goes_down(): void
    {
        $this->signIn(2);
        $this->post('/inventory-requests/1/approve')->assertSessionHas('success');

        $this->assertSame('approved', DB::table('inventory_requests')->where('id', 1)->value('status'));
        $this->assertSame(8, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    public function test_a_branch_admin_cannot_decide_another_branchs_request(): void
    {
        $this->signIn(2);

        $this->post('/inventory-requests/2/approve')->assertNotFound();
        $this->post('/inventory-requests/2/deny')->assertNotFound();
        $this->assertSame('pending', DB::table('inventory_requests')->where('id', 2)->value('status'));
        $this->assertSame(4, (int) DB::table('inventory')->where('id', 2)->value('quantity'));
    }

    public function test_the_super_admin_cannot_decide_a_request(): void
    {
        $this->signIn(1);

        $this->post('/inventory-requests/1/approve')->assertForbidden();
        $this->assertSame('pending', DB::table('inventory_requests')->where('id', 1)->value('status'));
    }

    // ---- the mobile app ---------------------------------------------------------------------------

    public function test_the_app_is_only_offered_the_stock_of_the_employees_branch(): void
    {
        $this->getJson('/backend/api/inventory_items.php', $this->appHeaders(1))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
        $this->getJson('/backend/api/inventory_items.php', $this->appHeaders(2))->assertOk()->assertJsonCount(2, 'data');
        // The employee is the one who signed in; an employee_id in the request changes nothing.
        $this->getJson('/backend/api/inventory_items.php?employee_id=2', $this->appHeaders(1))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_app_needs_to_be_signed_in(): void
    {
        $this->getJson('/backend/api/inventory_items.php')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->getJson('/backend/api/inventory_items.php', ['Authorization' => 'Bearer not-a-real-token'])->assertUnauthorized();
        $this->getJson('/backend/api/ping.php')->assertOk()->assertJsonPath('success', true);
    }

    public function test_the_app_cannot_request_another_branchs_item(): void
    {
        $other = $this->postJson('/backend/api/inventory_request_create.php', ['items' => [['inventory_id' => 2, 'quantity' => 1]]], $this->appHeaders(1));
        $other->assertStatus(422);
        $this->assertStringContainsString('another branch', $other->json('message'));
        $this->assertSame(2, DB::table('inventory_requests')->count());

        $own = $this->postJson('/backend/api/inventory_request_create.php', ['items' => [['inventory_id' => 1, 'quantity' => 1]]], $this->appHeaders(1));
        $own->assertCreated();
        $this->assertSame(3, DB::table('inventory_requests')->count());
        $this->assertSame(1, (int) DB::table('inventory_requests')->orderByDesc('id')->value('employee_id'), 'the request is made by the employee who signed in');
    }

    // ---- user accounts ----------------------------------------------------------------------------

    private function users(array $post = [], array $query = []): array
    {
        session(['user_verified' => true]);

        return $this->page('user_management', $query, $post);
    }

    /** users: 5 tech (Balilihan employee), plus 6 (Loon employee account) */
    private function addLoonTechnicianAccount(): void
    {
        DB::table('users')->insert(['id' => 6, 'username' => 'loontech', 'password' => 'x', 'full_name' => 'Loon Tech', 'role' => 'employee', 'branch_name' => 'Loon']);
    }

    public function test_a_branch_admin_lists_only_the_technician_accounts_of_their_branch(): void
    {
        $this->addLoonTechnicianAccount();
        $this->signIn(2);
        $page = $this->users();

        $this->assertSame([5], $this->ids($page['users']));
        $this->assertFalse($page['canChangeRoles']);
    }

    public function test_the_super_admin_lists_every_account_and_can_pick_a_branch(): void
    {
        $this->addLoonTechnicianAccount();
        $this->signIn(1);

        $this->assertSame([1, 2, 3, 4, 5, 6], $this->ids($this->users()['users']));
        $this->assertSame([3, 6], $this->ids($this->users([], ['branch' => 'Loon'])['users']));
    }

    public function test_a_branch_admin_creates_an_account_for_a_technician_of_their_branch(): void
    {
        $this->signIn(2);
        $page = $this->users(['create_user' => 1, 'employee_id' => 1, 'username' => 'eli', 'password' => 'pw12345', 'role' => 'super_admin']);

        $this->assertSame('User created successfully!', $page['success']);
        $row = DB::table('users')->where('username', 'eli')->first();
        $this->assertSame('employee', $row->role, 'the role comes from the position, not from the form');
        $this->assertSame('Balilihan', $row->branch_name);
    }

    public function test_a_branch_admin_cannot_create_an_account_for_another_branch_or_an_admin(): void
    {
        $this->signIn(2);
        $this->assertSame('Error: Select an active employee without an existing account.', $this->users(['create_user' => 1, 'employee_id' => 2, 'username' => 'fay', 'password' => 'pw12345'])['error']);

        DB::table('employees')->where('id', 1)->update(['position' => 'Admin']);
        $this->assertSame('Error: Accounts for Admin employees are created by the super admin.', $this->users(['create_user' => 1, 'employee_id' => 1, 'username' => 'eli', 'password' => 'pw12345'])['error']);
        $this->assertSame(0, DB::table('users')->whereIn('username', ['fay', 'eli'])->count());
    }

    public function test_the_account_form_only_suggests_the_branchs_technicians(): void
    {
        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Admin', 'position' => 'Admin', 'branch_name' => 'Balilihan']);
        $this->signIn(2);
        $this->assertSame([1], $this->ids($this->users()['employeeChoices']));

        $this->signIn(1);
        $this->assertSame([1, 2, 3], $this->ids($this->users()['employeeChoices']));
    }

    public function test_a_branch_admin_edits_a_technician_account_without_changing_its_role(): void
    {
        $this->signIn(2);
        $page = $this->users(['update_user' => 1, 'user_id' => 5, 'first_name' => 'Tina', 'last_name' => 'Tech', 'role' => 'super_admin', 'branch_name' => 'Loon', 'status' => 'active', 'phone' => '', 'email' => '', 'employee_position' => 'Technician I']);

        $this->assertSame('User updated successfully!', $page['success']);
        $row = DB::table('users')->where('id', 5)->first();
        $this->assertSame('Tina Tech', $row->full_name);
        $this->assertSame('employee', $row->role);
        $this->assertSame('Balilihan', $row->branch_name);
    }

    public function test_a_branch_admin_cannot_manage_admin_accounts_or_another_branchs_accounts(): void
    {
        $this->addLoonTechnicianAccount();
        $this->signIn(2);
        $edit = ['first_name' => 'X', 'last_name' => 'Y', 'role' => 'employee', 'status' => 'inactive', 'phone' => '', 'email' => '', 'employee_position' => 'Technician I'];

        foreach ([1, 2, 3, 6] as $userId) {
            $this->assertSame('Error: User not found.', $this->users(['update_user' => 1, 'user_id' => $userId] + $edit)['error'], "edit {$userId}");
            $this->assertSame('Error: User not found.', $this->users(['reset_password' => 1, 'user_id' => $userId, 'new_password' => 'pw'])['error'], "reset {$userId}");
            $this->assertSame('Error: User not found.', $this->users(['delete_user' => 1, 'user_id' => $userId])['error'], "archive {$userId}");
        }
        $this->assertSame('super_admin', DB::table('users')->where('id', 1)->value('role'));
        $this->assertSame('active', DB::table('users')->where('id', 3)->value('status'));
        $this->assertSame('x', DB::table('users')->where('id', 6)->value('password'));
        $this->assertSame(0, (int) DB::table('users')->where('id', 1)->value('archived'));
    }

    public function test_a_branch_admin_resets_archives_restores_and_deletes_their_technicians_account(): void
    {
        $this->signIn(2);
        $this->assertSame('Password reset successfully!', $this->users(['reset_password' => 1, 'user_id' => 5, 'new_password' => 'newpass1'])['success']);
        $this->assertTrue(password_verify('newpass1', DB::table('users')->where('id', 5)->value('password')));

        $this->assertSame('User archived successfully!', $this->users(['delete_user' => 1, 'user_id' => 5])['success']);
        $this->assertSame('User restored successfully!', $this->users(['restore_user' => 1, 'user_id' => 5])['success']);
        $this->users(['delete_user' => 1, 'user_id' => 5]);
        $this->assertSame('User deleted permanently!', $this->users(['purge_user' => 1, 'user_id' => 5])['success']);
        $this->assertSame(0, DB::table('users')->where('id', 5)->count());
    }

    public function test_the_super_admin_still_manages_every_account(): void
    {
        $this->signIn(1);
        $page = $this->users(['update_user' => 1, 'user_id' => 3, 'first_name' => 'Loon', 'last_name' => 'Boss', 'role' => 'admin', 'branch_name' => 'Loon', 'status' => 'active', 'phone' => '', 'email' => '']);

        $this->assertSame('User updated successfully!', $page['success']);
        $this->assertSame('Loon Boss', DB::table('users')->where('id', 3)->value('full_name'));
    }

    // ---- login history ----------------------------------------------------------------------------

    private function seedLogins(): void
    {
        DB::table('login_history')->insert([
            ['id' => 1, 'user_id' => 2, 'username' => 'bali', 'full_name' => 'Bali Admin', 'role' => 'admin', 'status' => 'success', 'login_time' => now()],
            ['id' => 2, 'user_id' => 3, 'username' => 'loon', 'full_name' => 'Loon Admin', 'role' => 'admin', 'status' => 'success', 'login_time' => now()],
            ['id' => 3, 'user_id' => 5, 'username' => 'tech', 'full_name' => 'Technician', 'role' => 'employee', 'status' => 'success', 'login_time' => now()],
            ['id' => 4, 'user_id' => null, 'username' => 'ghost', 'full_name' => '', 'role' => 'employee', 'status' => 'failed', 'login_time' => now()],
            ['id' => 5, 'user_id' => 1, 'username' => 'super', 'full_name' => 'Super Admin', 'role' => 'super_admin', 'status' => 'success', 'login_time' => now()],
        ]);
    }

    public function test_a_branch_admin_sees_only_the_logins_of_their_branchs_accounts(): void
    {
        $this->seedLogins();
        $this->signIn(2);
        $page = $this->page('login_history');

        $this->assertSame([1, 3], $this->ids($page['loginHistory']));
        $this->assertSame(2, $page['totalLogins']);
        $this->assertSame(2, $page['successfulLogins']);
        $this->assertSame(0, $page['failedLogins']);
    }

    public function test_the_super_admin_sees_every_login_and_can_pick_a_branch(): void
    {
        $this->seedLogins();
        $this->signIn(1);
        $all = $this->page('login_history');
        $this->assertSame([1, 2, 3, 4, 5], $this->ids($all['loginHistory']));
        $this->assertSame(5, $all['totalLogins']);
        $this->assertSame(1, $all['failedLogins']);

        $loon = $this->page('login_history', ['branch' => 'Loon']);
        $this->assertSame([2], $this->ids($loon['loginHistory']));
        $this->assertSame(1, $loon['totalLogins']);
    }

    public function test_a_branch_admin_cannot_pick_another_branchs_logins(): void
    {
        $this->seedLogins();
        $this->signIn(3);

        $this->assertSame([2], $this->ids($this->page('login_history', ['branch' => 'Balilihan'])['loginHistory']));
    }

    // ---- prices and settings ----------------------------------------------------------------------

    private function preparePriceTables(): void
    {
        Schema::table('internet_plans', function ($table) {
            $table->text('description')->nullable();
            $table->date('price_effective_on')->nullable();
            $table->decimal('scheduled_monthly_price', 10, 2)->nullable();
            $table->decimal('scheduled_installation_fee', 10, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('internet_plan_price_histories', function ($table) {
            $table->id();
            $table->unsignedBigInteger('internet_plan_id');
            $table->decimal('previous_monthly_price', 10, 2)->nullable();
            $table->decimal('new_monthly_price', 10, 2)->nullable();
            $table->decimal('previous_installation_fee', 10, 2)->nullable();
            $table->decimal('new_installation_fee', 10, 2)->nullable();
            $table->string('change_type')->nullable();
            $table->date('effective_on')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });
        DB::table('internet_plans')->insert(['id' => 1, 'plan_name' => 'Starter', 'monthly_price' => 999, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('clients')->where('id', 1)->update(['internet_plan_id' => 1]);
        DB::table('clients')->whereIn('id', [2, 3])->update(['internet_plan_id' => 1]);
    }

    public function test_every_admin_sees_the_plans_but_only_the_super_admin_can_change_them(): void
    {
        $this->preparePriceTables();

        $this->signIn(2);
        $branchAdmin = $this->get('/price-management')->assertOk();
        $branchAdmin->assertDontSee('+ Add Plan');
        $branchAdmin->assertSee('Starter');
        $this->assertFalse($branchAdmin->viewData('canManagePrices'));
        // Only their own branch's clients are counted.
        $this->assertSame(1, (int) $branchAdmin->viewData('plans')->first()->active_clients_count);
        $this->assertSame(1, $branchAdmin->viewData('stats')['clients']);

        $this->signIn(1);
        $super = $this->get('/price-management')->assertOk();
        $super->assertSee('+ Add Plan');
        $this->assertTrue($super->viewData('canManagePrices'));
        $this->assertSame(2, (int) $super->viewData('plans')->first()->active_clients_count);
    }

    public function test_a_branch_admin_cannot_save_a_plan(): void
    {
        $this->preparePriceTables();
        $this->signIn(2);

        $this->post('/price-management', ['action' => 'create', 'plan_name' => 'Free', 'speed_mbps' => 1, 'monthly_price' => 0, 'status' => 'active'])->assertForbidden();
        $this->assertSame(1, DB::table('internet_plans')->count());
    }

    public function test_the_super_admin_saves_a_plan(): void
    {
        $this->preparePriceTables();
        $this->signIn(1);

        $this->post('/price-management', ['action' => 'create', 'plan_name' => 'Pro', 'speed_mbps' => 50, 'monthly_price' => 1500, 'status' => 'active'])->assertSessionHas('success');
        $this->assertSame(1, DB::table('internet_plans')->where('plan_name', 'Pro')->count());
    }

    public function test_a_branch_admin_reads_the_settings_but_only_the_super_admin_saves_them(): void
    {
        DB::table('system_settings')->insert(['setting_key' => 'company_name', 'setting_value' => 'JFTECH']);

        $this->signIn(2);
        $read = $this->page('settings');
        $this->assertFalse($read['canEditSettings']);
        $denied = $this->page('settings', [], ['update_settings' => 1, 'settings' => ['company_name' => 'Hacked']]);
        $this->assertSame('Error: Only the super admin can change the system settings.', $denied['error']);
        $this->assertSame('JFTECH', DB::table('system_settings')->where('setting_key', 'company_name')->value('setting_value'));
    }

    public function test_the_super_admin_saves_the_settings(): void
    {
        DB::table('system_settings')->insert(['setting_key' => 'company_name', 'setting_value' => 'JFTECH']);
        $this->signIn(1);
        $page = $this->page('settings', [], ['update_settings' => 1, 'settings' => ['company_name' => 'JFTECH Wifi']]);

        $this->assertSame('System settings updated successfully!', $page['success']);
        $this->assertSame('JFTECH Wifi', DB::table('system_settings')->where('setting_key', 'company_name')->value('setting_value'));
    }

    public function test_the_settings_page_hides_the_save_buttons_from_a_branch_admin(): void
    {
        $this->signIn(2);
        $html = $this->get('/settings')->assertOk()->getContent();
        $this->assertStringNotContainsString('Save Settings', $html);
        $this->assertStringContainsString('<fieldset disabled', $html);

        $this->signIn(1);
        $html = $this->get('/settings')->assertOk()->getContent();
        $this->assertStringContainsString('Save Settings', $html);
        $this->assertStringNotContainsString('<fieldset disabled', $html);
    }

    // ---- the stock migration ----------------------------------------------------------------------

    public function test_the_inventory_migration_gives_existing_items_a_branch_and_frees_the_item_name(): void
    {
        Schema::drop('inventory');
        Schema::create('inventory', function ($table) {
            $table->id();
            $table->string('item_name');
            $table->string('category');
            $table->integer('quantity')->default(0);
            $table->unique(['item_name', 'category'], 'inventory_item_name_category_unique');
        });
        DB::table('inventory')->insert(['item_name' => 'Router', 'category' => 'Network', 'quantity' => 3]);

        $migration = require database_path('migrations/2026_10_16_000001_add_branch_to_inventory.php');
        $migration->up();
        $migration->up(); // safe to run twice

        $this->assertSame('Balilihan', DB::table('inventory')->value('branch_name'));
        DB::table('inventory')->insert(['item_name' => 'Router', 'category' => 'Network', 'quantity' => 1, 'branch_name' => 'Loon']);
        $this->assertSame(2, DB::table('inventory')->count());
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('inventory')->insert(['item_name' => 'Router', 'category' => 'Network', 'quantity' => 1, 'branch_name' => 'Loon']);
    }

    public function test_the_inventory_migration_can_be_rolled_back(): void
    {
        Schema::drop('inventory');
        Schema::create('inventory', function ($table) {
            $table->id();
            $table->string('item_name');
            $table->string('category');
            $table->unique(['item_name', 'category'], 'inventory_item_name_category_unique');
        });

        $migration = require database_path('migrations/2026_10_16_000001_add_branch_to_inventory.php');
        $migration->up();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('inventory', 'branch_name'));
        $this->assertTrue(Schema::hasIndex('inventory', 'inventory_item_name_category_unique'));
        $this->assertFalse(Schema::hasIndex('inventory', 'inventory_branch_item_category_unique'));
    }
}
