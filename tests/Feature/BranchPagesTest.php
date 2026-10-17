<?php

namespace Tests\Feature;

use App\Http\Controllers\PriceManagementController;
use App\Support\PeriodReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/** Each page only shows (and only changes) the records of the signed-in admin's branch. */
class BranchPagesTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();

        // MySQL's GREATEST() is used by the billing queries.
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
        DB::table('internet_plans')->insert(['id' => 1, 'plan_name' => 'Starter', 'monthly_price' => 999]);

        // Reading plan prices applies scheduled changes; that needs tables this test does not have.
        $this->app->instance(PriceManagementController::class, new class extends PriceManagementController {
            public function applyDueScheduledPrices(): void
            {
            }
        });
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

    private function ids(array $rows, string $key = 'id'): array
    {
        return array_map('intval', array_column($rows, $key));
    }

    // ---- dashboard ------------------------------------------------------------------------------

    public function test_dashboard_numbers_are_per_branch(): void
    {
        DB::table('login_history')->insert([
            ['user_id' => 2, 'username' => 'bali', 'role' => 'admin', 'login_time' => now()],
            ['user_id' => 3, 'username' => 'loon', 'role' => 'admin', 'login_time' => now()],
        ]);

        $this->signIn(2);
        $bali = $this->page('dashboard');
        $this->assertSame(1, (int) $bali['stats']['total_clients']);
        $this->assertSame(1, (int) $bali['stats']['total_employees']);
        $this->assertSame(1, (int) $bali['stats']['today_attendance']);
        $this->assertEquals(1000, $bali['stats']['total_revenue']);
        $this->assertSame(0, (int) $bali['stats']['overdue_bills']);
        $this->assertSame([1], $this->ids($bali['recentPayments']));
        $this->assertSame(['bali'], array_column($bali['recentLogins'], 'username'));
        $this->assertSame(2, (int) $bali['stats']['total_users']);
        $this->assertSame('Balilihan', $bali['branchScopeLabel']);

        $this->signIn(3);
        $loon = $this->page('dashboard', ['branch' => 'Balilihan']);
        $this->assertSame('Loon', $loon['branchScopeLabel'], 'a branch admin cannot ask for another branch');
        $this->assertSame(1, (int) $loon['stats']['total_clients']);
        $this->assertEquals(2000, $loon['stats']['total_revenue']);
        $this->assertSame(1, (int) $loon['stats']['overdue_bills']);
        $this->assertSame([2], $this->ids($loon['recentPayments']));
    }

    public function test_present_today_counts_the_attendance_recorded_in_the_branch(): void
    {
        // Employee 2 (a Loon employee) scanned in at Balilihan today.
        DB::table('attendance')->where('id', 2)->update(['branch_name' => 'Balilihan']);

        $this->signIn(2);
        $this->assertSame(2, (int) $this->page('dashboard')['stats']['today_attendance']);
        $this->signIn(3);
        $this->assertSame(0, (int) $this->page('dashboard')['stats']['today_attendance']);
        $this->signIn(1);
        $this->assertSame(2, (int) $this->page('dashboard')['stats']['today_attendance']);
    }

    public function test_dashboard_for_the_super_admin_covers_everything_or_one_branch(): void
    {
        $this->signIn(1);

        $all = $this->page('dashboard');
        $this->assertSame(2, (int) $all['stats']['total_clients']);
        $this->assertEquals(3000, $all['stats']['total_revenue']);
        $this->assertSame('All branches', $all['branchScopeLabel']);

        $loon = $this->page('dashboard', ['branch' => 'loon']);
        $this->assertSame(1, (int) $loon['stats']['total_clients']);
        $this->assertEquals(2000, $loon['stats']['total_revenue']);
        $this->assertSame('Loon', $loon['branchScopeLabel']);
    }

    public function test_dashboard_is_empty_for_an_admin_without_a_branch(): void
    {
        $this->signIn(4);
        $page = $this->page('dashboard');

        $this->assertSame(0, (int) $page['stats']['total_clients']);
        $this->assertEquals(0, $page['stats']['total_revenue']);
        $this->assertSame([], $page['recentPayments']);
    }

    // ---- clients ----------------------------------------------------------------------------------

    public function test_browse_clients_lists_only_the_admins_branch(): void
    {
        $this->signIn(2);
        $bali = $this->page('clients_browse');
        $this->assertSame([1], $this->ids($bali['clients']));
        $this->assertSame(['Balilihan'], array_keys($bali['branches']));
        $this->assertSame('Balilihan', $bali['selectedBranch']);

        $this->signIn(1);
        $super = $this->page('clients_browse');
        $ids = $this->ids($super['clients']);
        sort($ids);
        $this->assertSame([1, 2], $ids);
        $this->assertCount(19, $super['branches']);
    }

    public function test_a_branch_admin_cannot_ask_browse_clients_for_another_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('clients_browse', ['branch' => 'Loon']);

        $this->assertSame([1], $this->ids($page['clients']));
    }

    public function test_a_branch_admin_cannot_archive_another_branchs_client(): void
    {
        $this->signIn(2);

        try {
            $this->page('clients_browse', [], ['action' => 'archive', 'client_id' => 2]);
            $this->fail('archiving a Loon client should be refused');
        } catch (NotFoundHttpException) {
            $this->assertSame(0, (int) DB::table('clients')->where('id', 2)->value('archived'));
        }

        $page = $this->page('clients_browse', [], ['action' => 'archive', 'client_id' => 1]);
        $this->assertSame('Client archived.', $page['success']);
        $this->assertSame(1, (int) DB::table('clients')->where('id', 1)->value('archived'));
    }

    public function test_the_super_admin_cannot_register_clients(): void
    {
        $form = [
            'action' => 'register', 'first_name' => 'Dina', 'last_name' => 'Lopez', 'contact_number' => '09171234567',
            'email' => 'dina@example.com', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Agsoso', 'exact_address' => 'Purok 1',
            'installation_date' => now()->toDateString(), 'internet_plan_id' => 1,
        ];

        $this->signIn(1);
        $this->assertFalse($this->page('clients_browse')['canRegisterClients']);
        try {
            $this->page('clients_browse', [], $form);
            $this->fail('the super admin should not be able to register a client');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Dina Lopez')->count());

        $this->signIn(2);
        $this->assertTrue($this->page('clients_browse')['canRegisterClients']);
    }

    public function test_the_old_registration_form_is_closed_to_the_super_admin(): void
    {
        $this->signIn(1);

        try {
            $this->page('clients_register', [], ['first_name' => 'Dina', 'last_name' => 'Lopez']);
            $this->fail('the super admin should not be able to register a client');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('clients')->where('full_name', 'like', 'Dina%')->count());
    }

    public function test_the_clients_page_only_offers_registration_to_branch_admins(): void
    {
        $this->signIn(1);
        $html = $this->get('/clients')->assertOk()->getContent();
        $this->assertStringNotContainsString('Register New Client', $html);
        $this->assertStringNotContainsString('registerClientModal', $html);
        $this->assertStringContainsString('id="clientBranchInput"', $html, 'the branch filter is still there');
        $this->assertStringContainsString('Ana Reyes', $html);
        $this->assertStringContainsString('Ben Cruz', $html, 'the super admin sees every branch');

        $this->signIn(2);
        $html = $this->get('/clients')->assertOk()->getContent();
        $this->assertStringContainsString('+ Register New Client', $html);
        $this->assertStringContainsString('registerClientModal', $html);
    }

    public function test_the_registration_link_only_opens_the_form_for_branch_admins(): void
    {
        $this->signIn(1);
        $this->get('/client-registration')->assertRedirect('/clients')->assertSessionMissing('open_register_modal');

        $this->signIn(2);
        $this->get('/client-registration')->assertRedirect('/clients')->assertSessionHas('open_register_modal', true);
    }

    public function test_registering_a_client_stays_in_the_admins_branch(): void
    {
        $this->signIn(2);
        $form = [
            'action' => 'register', 'first_name' => 'Dina', 'last_name' => 'Lopez', 'contact_number' => '09171234567',
            'email' => 'dina@example.com', 'registered_barangay_name' => 'Boctol', 'exact_address' => 'Purok 1',
            'installation_date' => now()->toDateString(), 'internet_plan_id' => 1,
        ];

        $page = $this->page('clients_browse', [], $form + ['branch_name' => 'Loon']);
        $this->assertStringContainsString('Error', $page['error']);
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Dina Lopez')->count());

        $page = $this->page('clients_browse', [], $form + ['branch_name' => 'Balilihan']);
        $this->assertSame('Client registered successfully!', $page['success']);
        $this->assertSame('Balilihan', DB::table('clients')->where('full_name', 'Dina Lopez')->value('branch_name'));
    }

    public function test_archived_clients_page_and_restore_respect_the_branch(): void
    {
        $this->signIn(2);
        $this->assertSame([], $this->page('clients_archive')['archivedClients'], 'the only archived client is in Loon');

        try {
            $this->page('clients_archive', [], ['action' => 'restore', 'client_id' => 3]);
            $this->fail('restoring a Loon client should be refused');
        } catch (NotFoundHttpException) {
            $this->assertSame(1, (int) DB::table('clients')->where('id', 3)->value('archived'));
        }

        $this->signIn(3);
        $this->assertSame([3], $this->ids($this->page('clients_archive')['archivedClients']));
        $this->page('clients_archive', [], ['action' => 'restore', 'client_id' => 3]);
        $this->assertSame(0, (int) DB::table('clients')->where('id', 3)->value('archived'));
    }

    public function test_update_client_page_only_finds_and_edits_the_admins_clients(): void
    {
        $this->signIn(2);
        $this->assertSame([], $this->page('clients_update', ['search' => 'Cruz'])['searchResults']);
        $this->assertSame([1], $this->ids($this->page('clients_update', ['search' => 'Reyes'])['searchResults']));
        $this->assertSame(1, (int) $this->page('clients_update', ['id' => 1])['client']['id']);

        $this->expectException(NotFoundHttpException::class);
        $this->page('clients_update', ['id' => 2]);
    }

    public function test_updating_another_branchs_client_is_refused(): void
    {
        $this->signIn(2);

        try {
            $this->page('clients_update', [], [
                'update_client' => 1, 'client_id' => 2, 'first_name' => 'Hacked', 'last_name' => 'Name',
                'contact_number' => '09170000000', 'internet_plan_id' => 1,
            ]);
            $this->fail('editing a Loon client should be refused');
        } catch (NotFoundHttpException) {
            $this->assertSame('Ben Cruz', DB::table('clients')->where('id', 2)->value('full_name'));
        }
    }

    // ---- client accounts, payments ------------------------------------------------------------------

    public function test_client_accounts_list_and_detail_are_limited_to_the_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('bill_inquiry');
        $this->assertSame([1], $this->ids($page['allClients']));
        $this->assertSame('Balilihan', $page['selectedBranch']);
        $this->assertSame([1], $this->ids($this->page('bill_inquiry', ['search' => 'a'])['searchResults']));
        $this->assertSame(1, (int) $this->page('bill_inquiry', ['client_id' => 1])['selectedClient']['id']);

        $this->expectException(NotFoundHttpException::class);
        $this->page('bill_inquiry', ['client_id' => 2]);
    }

    public function test_client_accounts_actions_on_another_branchs_client_are_refused(): void
    {
        $this->signIn(2);

        try {
            $this->page('bill_inquiry', [], ['client_action' => 'archive', 'client_id' => 2]);
            $this->fail('archiving a Loon client should be refused');
        } catch (NotFoundHttpException) {
            $this->assertSame(0, (int) DB::table('clients')->where('id', 2)->value('archived'));
        }

        $page = $this->page('bill_inquiry', [], [
            'record_payment' => 1, 'client_id' => 2, 'bill_id' => 3, 'amount' => 500,
            'payment_date' => now()->toDateString(), 'payment_method' => 'cash',
            'submission_token' => '3f0c9d38-7c2e-4c3b-9d0c-0a1b2c3d4e5f',
        ]);
        $this->assertSame('Unable to record payment: That client does not belong to your branch.', $page['paymentError']);
        $this->assertSame(2, DB::table('payments')->count(), 'no payment was recorded');
    }

    public function test_payment_history_is_limited_to_the_branch(): void
    {
        $this->signIn(2);
        $page = $this->page('payment_history');
        $this->assertSame([1], $this->ids($page['payments']));
        $this->assertEquals(1000, $page['totalPayments']);

        $this->signIn(1);
        $this->assertSame([2, 1], $this->ids($this->page('payment_history')['payments']));
    }

    // ---- printable pages ----------------------------------------------------------------------------

    public function test_printable_client_and_payment_pages_are_limited_to_the_branch(): void
    {
        $this->signIn(2);
        $this->assertSame([1], $this->ids($this->page('print_clients')['clients']));
        $this->assertSame([1], $this->ids($this->page('print_payments')['payments']));
        $this->assertSame([1], $this->ids($this->page('print_bills')['bills']));
        $this->assertSame(1, (int) $this->page('print_client', ['id' => 1])['client']->id);
        $this->assertSame(1, (int) $this->page('print_payment', ['id' => 1])['payment']['id']);

        $this->signIn(1);
        $this->assertCount(3, $this->page('print_clients')['clients']);
        $this->assertCount(2, $this->page('print_payments')['payments']);
        $this->assertCount(3, $this->page('print_bills')['bills']);
    }

    public function test_printing_another_branchs_client_or_receipt_is_refused(): void
    {
        $this->signIn(2);

        foreach ([['print_client', 2], ['print_payment', 2]] as [$page, $id]) {
            try {
                $this->page($page, ['id' => $id]);
                $this->fail("{$page} for another branch should be refused");
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- reports -------------------------------------------------------------------------------------

    public function test_the_business_report_is_limited_to_the_branch(): void
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $all = PeriodReport::build($start, $end);
        $this->assertSame(3, $all['bills']['count']);
        $this->assertEquals(3000, $all['payments']['collected']);
        $this->assertNotNull($all['requests'], 'stock requests are part of the all-branches report');

        $bali = PeriodReport::build($start, $end, 'Balilihan');
        $this->assertSame(1, $bali['bills']['count']);
        $this->assertEquals(1000, $bali['payments']['collected']);
        $this->assertSame(1, $bali['attendance']['records']);
        $this->assertSame(1, $bali['payroll']['entries']);
        $this->assertEquals(3000, $bali['payroll']['net']);
        $this->assertNull($bali['requests'], 'stock is not split by branch');

        $none = PeriodReport::build($start, $end, '');
        $this->assertSame(0, $none['bills']['count']);
        $this->assertEquals(0, $none['payments']['collected']);
    }

    public function test_the_business_report_counts_attendance_by_the_recorded_branch(): void
    {
        DB::table('attendance')->where('id', 2)->update(['branch_name' => 'Balilihan']);
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $this->assertSame(2, PeriodReport::build($start, $end, 'Balilihan')['attendance']['records']);
        $this->assertSame(0, PeriodReport::build($start, $end, 'Loon')['attendance']['records']);
        $this->assertSame(2, PeriodReport::build($start, $end)['attendance']['records']);
    }

    public function test_reports_page_uses_the_admins_branch(): void
    {
        $this->signIn(3);
        $page = $this->page('reports', ['branch' => 'Balilihan']);

        $this->assertSame('Loon', $page['branch']);
        $this->assertSame(2, $page['report']['bills']['count']);
        $this->assertEquals(2000, $page['report']['payments']['collected']);

        $this->signIn(1);
        $this->assertNull($this->page('reports')['branch']);
        $this->assertSame('Balilihan', $this->page('reports', ['branch' => 'Balilihan'])['branch']);
    }

    // ---- user accounts ---------------------------------------------------------------------------------

    private function users(array $post): array
    {
        session(['user_verified' => true]);

        return $this->page('user_management', [], $post);
    }

    public function test_an_admin_account_is_created_from_an_admin_employee_and_nothing_else(): void
    {
        DB::table('employees')->where('id', 2)->update(['position' => 'Admin', 'email' => 'fay@example.com', 'contact_number' => '09175550000']);
        $this->signIn(1);

        // Names, role and branch posted by hand are ignored: they come from the employee record.
        $page = $this->users([
            'create_user' => 1, 'employee_id' => 2, 'username' => 'fay', 'password' => 'pw12345',
            'role' => 'super_admin', 'branch_name' => 'Balilihan', 'first_name' => 'Someone', 'last_name' => 'Else',
        ]);

        $this->assertSame('User created successfully!', $page['success']);
        $row = DB::table('users')->where('username', 'fay')->first();
        $this->assertSame('admin', $row->role, 'the Admin position becomes a branch admin');
        $this->assertSame('Loon', $row->branch_name, 'the branch of the employee record');
        $this->assertSame('Fay Garcia', $row->full_name);
        $this->assertSame('fay@example.com', $row->email);
        $this->assertSame('09175550000', $row->phone);
        $this->assertSame($row->id, (int) DB::table('employees')->where('id', 2)->value('user_id'), 'the employee is linked to the account');
        $this->assertTrue(\App\Models\User::find($row->id)->isBranchAdmin());
    }

    public function test_a_technician_gets_an_employee_account_in_their_branch(): void
    {
        $this->signIn(1);
        $page = $this->users(['create_user' => 1, 'employee_id' => 1, 'username' => 'eli', 'password' => 'pw12345']);

        $this->assertSame('User created successfully!', $page['success']);
        $row = DB::table('users')->where('username', 'eli')->first();
        $this->assertSame('employee', $row->role);
        $this->assertSame('Balilihan', $row->branch_name);
        $this->assertSame('Eli Santos', $row->full_name);
    }

    public function test_an_account_needs_an_employee_without_one_and_a_username_and_password(): void
    {
        $this->signIn(1);

        $this->assertSame('Error: Select an active employee without an existing account.', $this->users(['create_user' => 1, 'username' => 'x', 'password' => 'pw12345'])['error']);
        $this->assertSame('Error: Select an active employee without an existing account.', $this->users(['create_user' => 1, 'employee_id' => 999, 'username' => 'x', 'password' => 'pw12345'])['error']);
        $this->assertSame('Error: Username and password are required.', $this->users(['create_user' => 1, 'employee_id' => 1, 'username' => '', 'password' => ''])['error']);

        $this->users(['create_user' => 1, 'employee_id' => 1, 'username' => 'eli', 'password' => 'pw12345']);
        $again = $this->users(['create_user' => 1, 'employee_id' => 1, 'username' => 'eli2', 'password' => 'pw12345']);
        $this->assertSame('Error: Select an active employee without an existing account.', $again['error'], 'one account per employee');

        DB::table('employees')->where('id', 2)->update(['status' => 'inactive']);
        $this->assertSame('Error: Select an active employee without an existing account.', $this->users(['create_user' => 1, 'employee_id' => 2, 'username' => 'fay', 'password' => 'pw12345'])['error']);
        $this->assertSame(0, DB::table('users')->whereIn('username', ['x', 'eli2', 'fay'])->count());
    }

    public function test_an_admin_account_needs_a_real_branch_on_the_employee(): void
    {
        DB::table('employees')->where('id', 2)->update(['position' => 'Admin', 'branch_name' => 'Atlantis']);
        $this->signIn(1);
        $page = $this->users(['create_user' => 1, 'employee_id' => 2, 'username' => 'fay', 'password' => 'pw12345']);

        $this->assertSame('Error: Set a valid branch for this employee in Employees before creating an admin account.', $page['error']);
        $this->assertSame(0, DB::table('users')->where('username', 'fay')->count());
        $this->assertNull(DB::table('employees')->where('id', 2)->value('user_id'));
    }

    public function test_the_form_suggests_employees_without_an_account_with_their_position_and_role(): void
    {
        DB::table('employees')->where('id', 2)->update(['position' => 'Admin']);
        DB::table('employees')->where('id', 1)->update(['user_id' => 5]);
        $this->signIn(1);
        $choices = $this->users([])['employeeChoices'];

        $this->assertCount(1, $choices, 'Eli already has an account');
        $this->assertSame('Fay Garcia — Admin · Loon', $choices[0]['label']);
        $this->assertSame(2, $choices[0]['id']);
        $this->assertSame('Branch Admin (manages Loon)', $choices[0]['roleLabel']);
    }

    public function test_an_account_can_be_moved_to_another_branch_but_not_without_one(): void
    {
        $this->signIn(1);
        $edit = ['update_user' => 1, 'user_id' => 2, 'first_name' => 'Bali', 'last_name' => 'Admin', 'role' => 'admin', 'status' => 'active', 'phone' => ''];

        $this->assertStringContainsString('Select the branch', $this->users($edit + ['branch_name' => ''])['error']);
        $this->assertSame('Balilihan', DB::table('users')->where('id', 2)->value('branch_name'));

        $this->assertSame('User updated successfully!', $this->users($edit + ['branch_name' => 'Loon'])['success']);
        $this->assertSame('Loon', DB::table('users')->where('id', 2)->value('branch_name'));

        // Promoting to super admin drops the branch.
        $this->users(array_merge($edit, ['role' => 'super_admin', 'branch_name' => 'Loon']));
        $this->assertNull(DB::table('users')->where('id', 2)->value('branch_name'));
    }

    public function test_the_branch_can_be_typed_in_any_case_when_editing_an_account(): void
    {
        $this->signIn(1);
        $this->users(['update_user' => 1, 'user_id' => 2, 'first_name' => 'Bali', 'last_name' => 'Admin', 'role' => 'admin', 'status' => 'active', 'phone' => '', 'branch_name' => ' lOOn ']);

        $this->assertSame('Loon', DB::table('users')->where('id', 2)->value('branch_name'));
    }

    public function test_the_super_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $this->signIn(1);
        $edit = ['update_user' => 1, 'user_id' => 1, 'first_name' => 'Super', 'last_name' => 'Admin', 'phone' => ''];

        $page = $this->users($edit + ['role' => 'admin', 'branch_name' => 'Loon', 'status' => 'active']);
        $this->assertSame('Error: You cannot change your own Super Admin role or status.', $page['error']);
        $page = $this->users($edit + ['role' => 'super_admin', 'status' => 'inactive']);
        $this->assertSame('Error: You cannot change your own Super Admin role or status.', $page['error']);
        $this->assertSame('super_admin', DB::table('users')->where('id', 1)->value('role'));

        // Renaming themselves is fine.
        $this->assertSame('User updated successfully!', $this->users($edit + ['role' => 'super_admin', 'status' => 'active'])['success']);
    }

    public function test_an_admin_confirms_their_own_password_for_the_account_page(): void
    {
        $this->signIn(2);
        $page = $this->page('user_management', [], ['verify_admin' => 1, 'verify_username' => 'bali', 'verify_password' => 'secret123']);
        $this->assertSame('', $page['verifyError'], 'a branch admin confirms with their own password');

        session()->forget('user_verified');
        $page = $this->page('user_management', [], ['verify_admin' => 1, 'verify_username' => 'super', 'verify_password' => 'secret123']);
        $this->assertSame('Invalid admin credentials.', $page['verifyError'], 'another admin\'s password does not count');

        session()->forget('user_verified');
        $page = $this->page('user_management', [], ['verify_admin' => 1, 'verify_username' => 'bali', 'verify_password' => 'wrong']);
        $this->assertSame('Invalid admin credentials.', $page['verifyError']);

        session()->forget('user_verified');
        $this->signIn(1);
        $page = $this->page('user_management', [], ['verify_admin' => 1, 'verify_username' => 'super', 'verify_password' => 'secret123']);
        $this->assertSame('', $page['verifyError']);
    }
}
