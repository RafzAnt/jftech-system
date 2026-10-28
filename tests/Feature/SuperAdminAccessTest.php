<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The super admin is the owner's account: it signs in through the login form, opens every page of the
 * sidebar, reaches what a branch admin cannot, and an account that was archived or switched off is shut out
 * of the admin system and of the employee app.
 *
 * Seed: users 1 super admin, 2 Balilihan admin, 3 Loon admin, 5 employee app account (see BranchTestData).
 * Every account has the password "secret123".
 */
class SuperAdminAccessTest extends TestCase
{
    use BranchTestData;

    /** Every page of the sidebar, plus the super-admin-only print. */
    private const PAGES = [
        '/dashboard', '/clients', '/archived-clients', '/client-accounts', '/payments', '/price-management',
        '/employees', '/payroll', '/inventory', '/inventory-requests', '/nap-boxes', '/reports',
        '/print-monthly-reports', '/settings', '/users', '/login-history', '/print-reports',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);

        // Pages the shared branch schema does not need: the price history and the generated-report history.
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
        Schema::create('report_history', function ($table) {
            $table->id();
            $table->string('report_name');
            $table->string('report_type');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->text('filters')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->integer('record_count')->default(0);
            $table->string('status')->nullable();
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function test_the_super_admin_signs_in_through_the_login_form(): void
    {
        $this->post('/', ['username' => 'super', 'password' => 'secret123'])->assertRedirect('/pages/dashboard.php');

        $this->assertAuthenticatedAs(User::findOrFail(1));
        $this->assertSame('success', DB::table('login_history')->where('username', 'super')->value('status'));
        $this->assertSame('super_admin', DB::table('login_history')->where('username', 'super')->value('role'));
    }

    public function test_the_super_admin_opens_every_page_of_the_sidebar(): void
    {
        $this->signIn(1);

        foreach (self::PAGES as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_the_super_admin_sidebar_says_all_branches(): void
    {
        $this->signIn(1);

        $this->get('/dashboard')->assertSee('Super Admin · All branches');
    }

    public function test_a_branch_admin_is_kept_out_of_what_only_the_super_admin_owns(): void
    {
        $this->signIn(2);

        $this->get('/print-reports')->assertForbidden();
        $this->get('/reports/export/csv')->assertForbidden();
        $this->get('/report-history')->assertForbidden();
        $this->post('/price-management', ['action' => 'archive', 'plan_id' => 1])->assertForbidden();
    }

    public function test_the_super_admin_reaches_the_company_wide_exports_and_history(): void
    {
        $this->signIn(1);

        $this->get('/report-history')->assertOk();
        $this->get('/reports/export/csv')->assertOk();
    }

    public function test_an_archived_account_cannot_sign_in(): void
    {
        foreach ([['super', 1], ['bali', 2]] as [$username, $id]) {
            DB::table('users')->where('id', $id)->update(['archived' => 1]);

            $this->from('/')->post('/', ['username' => $username, 'password' => 'secret123'])->assertRedirect('/');

            $this->assertGuest();
            $this->assertSame('failed', DB::table('login_history')->where('username', $username)->value('status'));
        }
    }

    public function test_an_account_archived_while_signed_in_is_shut_out_on_the_next_request(): void
    {
        $this->signIn(2);
        $this->get('/dashboard')->assertOk();

        DB::table('users')->where('id', 2)->update(['archived' => 1]);
        $this->actingAs(User::findOrFail(2));

        $this->get('/dashboard')->assertForbidden();
    }

    public function test_an_account_switched_off_while_signed_in_is_shut_out_on_the_next_request(): void
    {
        $this->signIn(2);
        $this->get('/dashboard')->assertOk();

        DB::table('users')->where('id', 2)->update(['status' => 'inactive']);
        $this->actingAs(User::findOrFail(2));

        $this->get('/dashboard')->assertForbidden();
    }

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        DB::table('users')->where('id', 1)->update(['status' => 'inactive']);

        $this->from('/')->post('/', ['username' => 'super', 'password' => 'secret123'])->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_the_super_admin_cannot_demote_or_switch_off_their_own_account(): void
    {
        $this->signIn(1);
        $edit = ['update_user' => 1, 'user_id' => 1, 'first_name' => 'Super', 'last_name' => 'Admin', 'phone' => '', 'email' => '', 'branch_name' => 'Loon', 'employee_position' => 'Technician I'];

        $this->post('/users', ['verify_admin' => 1, 'verify_username' => 'super', 'verify_password' => 'secret123']);
        $this->post('/users', $edit + ['role' => 'admin', 'status' => 'active']);
        $this->post('/users', $edit + ['role' => 'super_admin', 'status' => 'inactive']);
        $this->post('/users', ['delete_user' => 1, 'user_id' => 1]);

        $row = DB::table('users')->where('id', 1)->first();
        $this->assertSame('super_admin', $row->role);
        $this->assertSame('active', $row->status);
        $this->assertSame(0, (int) $row->archived);
    }

    public function test_a_branch_admin_cannot_make_anybody_a_super_admin(): void
    {
        $this->signIn(2);

        $this->post('/users', ['verify_admin' => 1, 'verify_username' => 'bali', 'verify_password' => 'secret123']);
        $this->post('/users', ['update_user' => 1, 'user_id' => 5, 'first_name' => 'Tina', 'last_name' => 'Tech', 'role' => 'super_admin', 'status' => 'active', 'phone' => '', 'email' => '', 'branch_name' => 'Balilihan', 'employee_position' => 'Technician I']);
        $this->post('/users', ['update_user' => 1, 'user_id' => 2, 'first_name' => 'Bali', 'last_name' => 'Admin', 'role' => 'super_admin', 'status' => 'active', 'phone' => '', 'email' => '', 'branch_name' => 'Balilihan', 'employee_position' => 'Technician I']);

        $this->assertSame(1, DB::table('users')->where('role', 'super_admin')->count());
        $this->assertSame('admin', DB::table('users')->where('id', 2)->value('role'));
    }

    public function test_an_archived_technician_account_cannot_sign_in_to_the_employee_app(): void
    {
        DB::table('employees')->where('id', 1)->update(['user_id' => 5]);
        $login = ['username' => 'tech', 'password' => 'secret123'];

        $this->postJson('/backend/api/employee_login.php', $login)->assertOk()->assertJsonPath('success', true);

        DB::table('users')->where('id', 5)->update(['archived' => 1]);

        $this->postJson('/backend/api/employee_login.php', $login)->assertUnauthorized();
    }

    public function test_the_app_token_of_an_account_archived_later_stops_working(): void
    {
        DB::table('employees')->where('id', 1)->update(['user_id' => 5]);
        $headers = $this->appHeaders(1);

        $this->getJson('/backend/api/me.php', $headers)->assertOk();

        DB::table('users')->where('id', 5)->update(['archived' => 1]);

        $this->getJson('/backend/api/me.php', $headers)->assertUnauthorized();
    }
}
