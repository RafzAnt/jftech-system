<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BranchScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

class BranchScopeTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
    }

    public function test_each_role_gets_its_branch_limit(): void
    {
        $this->assertNull(BranchScope::current(User::find(1)), 'super admin sees every branch');
        $this->assertSame('Balilihan', BranchScope::current(User::find(2)));
        $this->assertSame('Loon', BranchScope::current(User::find(3)));
        $this->assertSame('', BranchScope::current(User::find(4)), 'a branch admin with no branch sees nothing');
        $this->assertSame('', BranchScope::current(User::find(5)), 'employee accounts see nothing');
        $this->assertSame('', BranchScope::current(null), 'a guest sees nothing');
    }

    public function test_resolve_lets_only_the_super_admin_pick_a_branch(): void
    {
        $this->assertNull(BranchScope::resolve(null, User::find(1)));
        $this->assertSame('Loon', BranchScope::resolve('loon', User::find(1)));
        $this->assertNull(BranchScope::resolve('Atlantis', User::find(1)), 'unknown branches are ignored');
        $this->assertSame('Balilihan', BranchScope::resolve('Loon', User::find(2)), 'a branch admin cannot switch branch');
    }

    public function test_sql_fragments_filter_clients_and_fail_closed(): void
    {
        $this->assertSame(['', []], BranchScope::clients(null));
        $this->assertSame([' AND 1 = 0', []], BranchScope::clients(''));

        [$sql, $params] = BranchScope::clients('Loon', 'c');
        $ids = DB::table('clients as c')->whereRaw(substr($sql, 5), $params)->orderBy('c.id')->pluck('c.id')->all();
        $this->assertSame([2, 3], array_map('intval', $ids));

        [$sql, $params] = BranchScope::viaClient('Balilihan', 'b.client_id');
        $ids = DB::table('bills as b')->whereRaw(substr($sql, 5), $params)->pluck('b.id')->all();
        $this->assertSame([1], array_map('intval', $ids));

        [$sql, $params] = BranchScope::viaEmployee('Loon', 'a.employee_id');
        $ids = DB::table('attendance as a')->whereRaw(substr($sql, 5), $params)->pluck('a.id')->all();
        $this->assertSame([2], array_map('intval', $ids));

        $this->assertSame([], DB::table('clients as c')->whereRaw('1 = 1 ' . BranchScope::clients('')[0])->pluck('c.id')->all());
    }

    public function test_attendance_belongs_to_the_branch_recorded_on_the_day(): void
    {
        $this->assertSame(['', []], BranchScope::attendance(null));
        $this->assertSame([' AND 1 = 0', []], BranchScope::attendance(''));

        // Employee 2 works in Loon now, but one day was recorded in Balilihan and one has no branch recorded.
        DB::table('attendance')->insert([
            ['id' => 3, 'employee_id' => 2, 'attendance_date' => now()->subDay()->toDateString(), 'branch_name' => 'Balilihan'],
            ['id' => 4, 'employee_id' => 2, 'attendance_date' => now()->subDays(2)->toDateString(), 'branch_name' => null],
            ['id' => 5, 'employee_id' => 2, 'attendance_date' => now()->subDays(3)->toDateString(), 'branch_name' => ''],
        ]);
        $idsFor = function (string $branch): array {
            [$sql, $params] = BranchScope::attendance($branch, 'a');

            return array_map('intval', DB::table('attendance as a')->whereRaw(substr($sql, 5), $params)->orderBy('a.id')->pluck('a.id')->all());
        };

        $this->assertSame([1, 3], $idsFor('Balilihan'), 'a day recorded in Balilihan stays in Balilihan');
        $this->assertSame([2, 4, 5], $idsFor('Loon'), 'no branch recorded falls back to the employee branch');
    }

    public function test_branch_names_are_matched_without_regard_to_case(): void
    {
        [$sql, $params] = BranchScope::clients('lOOn', 'c');
        $this->assertCount(2, DB::table('clients as c')->whereRaw(substr($sql, 5), $params)->get());
    }

    public function test_access_checks_for_a_single_client_or_employee(): void
    {
        $this->assertTrue(BranchScope::canAccessClient(1, User::find(2)));
        $this->assertFalse(BranchScope::canAccessClient(2, User::find(2)));
        $this->assertTrue(BranchScope::canAccessClient(2, User::find(1)));
        $this->assertFalse(BranchScope::canAccessClient(999, User::find(1)), 'a client that does not exist');
        $this->assertFalse(BranchScope::canAccessClient(1, User::find(4)));
        $this->assertFalse(BranchScope::canAccessClient(null, User::find(1)));

        $this->assertTrue(BranchScope::canAccessEmployee(2, User::find(3)));
        $this->assertFalse(BranchScope::canAccessEmployee(1, User::find(3)));

        $this->assertTrue(BranchScope::canAccessBranch('loon', User::find(3)));
        $this->assertFalse(BranchScope::canAccessBranch('Balilihan', User::find(3)));
        $this->assertTrue(BranchScope::canAccessBranch('Anything', User::find(1)));
        $this->assertFalse(BranchScope::canAccessBranch('Balilihan', User::find(4)));
    }

    public function test_abort_helper_answers_not_found_for_another_branchs_client(): void
    {
        $this->signIn(2);
        BranchScope::abortUnlessClient(1);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        BranchScope::abortUnlessClient(2);
    }

    public function test_branch_admins_cannot_open_the_company_wide_report_exports(): void
    {
        $this->signIn(2);

        foreach (['/report-history', '/reports/export/csv', '/print-reports'] as $url) {
            $this->get($url)->assertForbidden("branch admin opened {$url}");
        }
    }

    public function test_a_branch_admin_cannot_change_the_prices(): void
    {
        $this->signIn(2);

        $this->post('/price-management', ['action' => 'create', 'plan_name' => 'Free', 'speed_mbps' => 1, 'monthly_price' => 0, 'status' => 'active'])->assertForbidden();
    }

    public function test_the_super_admin_can_open_company_wide_pages(): void
    {
        $this->signIn(1);

        $this->get('/users')->assertOk()->assertSee('Super Admin');
    }

    public function test_employee_accounts_cannot_use_the_admin_site(): void
    {
        $this->signIn(5);

        $this->get('/dashboard')->assertForbidden();
        $this->get('/clients')->assertForbidden();
        $this->getJson('/backend/api/get_client_billing.php?client_id=1')->assertNotFound();
    }

    public function test_billing_api_only_answers_for_the_admins_own_clients(): void
    {
        $this->signIn(2);
        $this->getJson('/backend/api/get_client_billing.php?client_id=1')->assertOk()->assertJsonCount(1, 'bills');
        $this->getJson('/backend/api/get_client_billing.php?client_id=2')->assertNotFound();
        $this->getJson('/backend/api/get_pending_bills.php?client_id=2')->assertNotFound();

        $this->signIn(1);
        $this->getJson('/backend/api/get_client_billing.php?client_id=2')->assertOk()->assertJsonCount(2, 'bills');
    }

    public function test_every_account_gets_the_whole_sidebar(): void
    {
        $links = ['dashboard.php', 'clients_browse.php', 'bill_inquiry.php', 'payment_history.php', 'price_management.php', 'employees.php', 'payroll.php', 'inventory.php', 'inventory_requests.php', 'reports.php', 'settings.php', 'user_management.php', 'login_history.php'];

        $this->signIn(2);
        $html = $this->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Balilihan Branch Admin', $html);
        foreach ($links as $link) {
            $this->assertStringContainsString($link, $html, "branch admin sidebar is missing {$link}");
        }

        $this->signIn(1);
        $html = $this->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Super Admin · All branches', $html);
        foreach ($links as $link) {
            $this->assertStringContainsString($link, $html, "super admin sidebar is missing {$link}");
        }
    }

    public function test_a_branch_admin_signs_in_but_one_without_a_branch_does_not(): void
    {
        $this->post('/', ['username' => 'bali', 'password' => 'secret123'])->assertRedirect('/pages/dashboard.php');
        $this->assertAuthenticatedAs(User::find(2));
        $this->post('/logout');

        $this->post('/', ['username' => 'nobranch', 'password' => 'secret123'])
            ->assertSessionHasErrors(['username' => 'This admin account has no branch assigned. Ask the super admin to assign one.']);
        $this->assertGuest();
        $this->assertDatabaseHas('login_history', ['username' => 'nobranch', 'status' => 'failed']);

        $this->post('/', ['username' => 'nobranch', 'password' => 'wrong'])
            ->assertSessionHasErrors(['username' => 'Invalid username or password.']);
    }

    public function test_migration_turns_existing_admins_into_super_admins(): void
    {
        Schema::drop('users');
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('username');
            $table->string('role')->default('employee');
        });
        DB::table('users')->insert([
            ['username' => 'old-admin', 'role' => 'admin'],
            ['username' => 'old-admin-2', 'role' => 'admin'],
            ['username' => 'tech', 'role' => 'employee'],
        ]);

        $migration = require database_path('migrations/2026_10_15_000001_add_super_admin_role_and_user_branch.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'branch_name'));
        $this->assertSame(2, DB::table('users')->where('role', 'super_admin')->count());
        $this->assertSame(0, DB::table('users')->where('role', 'admin')->count());
        $this->assertSame('employee', DB::table('users')->where('username', 'tech')->value('role'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'branch_name'));
        $this->assertSame(2, DB::table('users')->where('role', 'admin')->count());
    }
}
