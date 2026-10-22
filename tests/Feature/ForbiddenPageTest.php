<?php

namespace Tests\Feature;

use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Whatever a role may not open ends on a clear page with a way out, never on a bare "403 Forbidden".
 *
 * Seed: users 1 super admin, 2 Balilihan admin, 5 employee app account (see BranchTestData).
 */
class ForbiddenPageTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
    }

    public function test_an_employee_app_account_that_reaches_the_admin_area_can_only_log_out(): void
    {
        $this->signIn(5);

        $this->get('/dashboard')
            ->assertForbidden()
            ->assertSee("This account can't use the admin system", false)
            ->assertSee('action="' . route('logout') . '"', false)
            ->assertDontSee('Forbidden');
    }

    public function test_a_branch_admin_opening_a_super_admin_page_gets_a_way_back(): void
    {
        $this->signIn(2);

        $this->get('/print-reports')
            ->assertForbidden()
            ->assertSee("You don't have access to this page", false)
            ->assertSee('Back to dashboard')
            ->assertDontSee('Forbidden');
    }

    public function test_the_api_still_answers_in_json(): void
    {
        $this->getJson('/backend/api/me.php')->assertUnauthorized();
    }
}
