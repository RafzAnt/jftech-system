<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The employee app also runs in a web browser (`flutter run -d chrome`). A browser only gives the reply to the app
 * when the server allows it, otherwise the app says it cannot reach the server although the request arrived.
 */
class AppApiCorsTest extends TestCase
{
    public function test_a_browser_app_may_read_the_api_reply(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5555'])
            ->getJson('/backend/api/ping.php')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_the_browser_check_before_a_login_is_answered(): void
    {
        $this->call('OPTIONS', '/backend/api/employee_login.php', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5555',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,authorization',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', 'POST')
            ->assertHeader('Access-Control-Allow-Headers', 'content-type,authorization');
    }

    public function test_the_admin_pages_are_not_opened_to_other_websites(): void
    {
        $this->withHeaders(['Origin' => 'http://evil.example'])
            ->get('/')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
