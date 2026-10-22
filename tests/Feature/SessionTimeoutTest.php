<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceInactivityTimeout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The five-minute idle logout, the heartbeat that keeps a busy user signed in, and the login error.
 *
 * Seed: users 1 super admin, 2 Balilihan admin (see BranchTestData).
 */
class SessionTimeoutTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
    }

    private function idleFor(int $seconds): array
    {
        return ['last_activity_at' => time() - $seconds];
    }

    public function test_a_heartbeat_keeps_a_user_who_is_busy_in_the_page_signed_in(): void
    {
        $this->signIn(1);

        // The server has heard nothing for 4m50s, but the browser says the user clicked 10 seconds ago.
        $this->withSession($this->idleFor(290))->postJson('/session/keepalive', ['idle' => 10])->assertNoContent();

        $this->assertEqualsWithDelta(time() - 10, session('last_activity_at'), 2);
        $this->get('/dashboard')->assertOk();
    }

    public function test_the_heartbeat_cannot_claim_more_idle_time_than_the_limit(): void
    {
        $this->signIn(1);

        $this->withSession($this->idleFor(10))->postJson('/session/keepalive', ['idle' => 99999])->assertNoContent();

        $this->assertEqualsWithDelta(time() - (EnforceInactivityTimeout::TIMEOUT_SECONDS - 1), session('last_activity_at'), 2);
    }

    public function test_a_heartbeat_after_five_idle_minutes_is_refused_and_ends_the_session(): void
    {
        $this->signIn(1);

        $this->withSession($this->idleFor(EnforceInactivityTimeout::TIMEOUT_SECONDS + 5))->postJson('/session/keepalive', ['idle' => 0])->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_any_page_after_five_idle_minutes_goes_back_to_the_login_page(): void
    {
        $this->signIn(1);

        $this->withSession($this->idleFor(EnforceInactivityTimeout::TIMEOUT_SECONDS + 5))
            ->get('/dashboard')
            ->assertRedirect('/')
            ->assertSessionHas('status');
    }

    public function test_the_heartbeat_needs_a_signed_in_user(): void
    {
        $this->postJson('/session/keepalive')->assertRedirect(route('login'));
    }

    public function test_a_wrong_username_and_password_say_invalid_and_keep_only_the_username(): void
    {
        $response = $this->from('/')->post('/', ['username' => 'nobody', 'password' => 'wrong-password']);

        $response->assertRedirect('/')->assertSessionHasErrors(['username' => 'Invalid username or password.']);
        $response->assertSessionHas('invalid_credentials', true);
        $this->assertGuest();
        $this->assertSame('nobody', session()->getOldInput('username'));
        $this->assertNull(session()->getOldInput('password'), 'the password must not be kept in the session');
    }

    public function test_the_login_form_shows_the_error_and_marks_both_fields(): void
    {
        $page = $this->followingRedirects()->from('/')->post('/', ['username' => 'nobody', 'password' => 'wrong-password'])->assertOk();

        $this->assertStringContainsString('Invalid username or password.', $page->getContent());
        $this->assertSame(2, substr_count($page->getContent(), 'field-control is-invalid'));
        $this->assertStringContainsString('data-persist', $page->getContent(), 'the error must not auto-hide');
    }

    public function test_a_wrong_password_for_a_real_user_looks_the_same(): void
    {
        $username = DB::table('users')->where('id', 1)->value('username');

        $this->from('/')->post('/', ['username' => $username, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['username' => 'Invalid username or password.'])
            ->assertSessionHas('invalid_credentials', true);
    }

    public function test_the_login_page_says_why_you_were_logged_out(): void
    {
        $this->get('/?timeout=1')->assertOk()->assertSee('You were logged out after 5 minutes of inactivity.');
    }

    public function test_too_many_login_attempts_show_a_message_on_the_form(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/')->post('/', ['username' => 'nobody', 'password' => 'wrong-password']);
        }

        $this->from('/')->post('/', ['username' => 'nobody', 'password' => 'wrong-password'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('username');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('username'));
    }

    public function test_an_employee_app_account_is_refused_at_the_login_instead_of_meeting_a_403_page(): void
    {
        DB::table('users')->where('id', 5)->update(['password' => Hash::make('right-password')]);
        $username = DB::table('users')->where('id', 5)->value('username');

        $this->from('/')->post('/', ['username' => $username, 'password' => 'right-password'])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['username' => 'This account is for the employee app. It cannot log in here.'])
            ->assertSessionHas('invalid_credentials', false);

        $this->assertGuest();
        $this->assertSame('failed', DB::table('login_history')->where('username', $username)->value('status'));
    }

    public function test_the_login_still_works_with_the_right_password(): void
    {
        DB::table('users')->where('id', 1)->update(['password' => Hash::make('right-password')]);
        $username = DB::table('users')->where('id', 1)->value('username');

        $this->post('/', ['username' => $username, 'password' => 'right-password'])->assertRedirect('/pages/dashboard.php');
        $this->assertAuthenticatedAs(\App\Models\User::find(1));
    }
}
