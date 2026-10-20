<?php

namespace Tests\Feature;

use App\Support\NapBoxes;
use App\Support\WorkRequestService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The employee app: NAP boxes, installation and repair requests, and the admin steps that follow
 * (approve, return, confirm), including what a confirmation updates (port, new client, stock).
 *
 * Seed: employees 1 (Eli, Balilihan), 2 (Fay, Loon) and 3 (Gus, Balilihan). NAP-01 (box 1, Boctol, Balilihan) has
 * ports 1-4: port 2 is occupied by client 1, port 4 is faulty. NAP-02 (box 2, Agsoso, Loon) has ports 5-6.
 */
class WorkRequestApiTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);

        DB::table('employees')->insert(['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Tan', 'position' => 'Technician I', 'branch_name' => 'Balilihan', 'status' => 'active']);
        DB::table('internet_plans')->insert(['id' => 1, 'plan_name' => 'Starter', 'monthly_price' => 999, 'installation_fee' => 500, 'status' => 'active', 'archived' => false]);

        NapBoxes::create('Balilihan', 'NAP-01', 'Boctol', 'Purok 2', 4);
        NapBoxes::create('Loon', 'NAP-02', 'Agsoso', 'Near the chapel', 2);
        DB::table('nap_ports')->where('id', 2)->update(['status' => 'occupied', 'client_id' => 1]);
        DB::table('nap_ports')->where('id', 4)->update(['status' => 'faulty']);
    }

    private function install(int $employeeId, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/backend/api/work_request_create.php', $extra + [
            'work_type' => 'installation',
            'new_client' => ['name' => 'Nina Reyes', 'phone' => '09171234567', 'address' => 'Purok 3'],
            'barangay' => 'Boctol', 'nap_box_id' => 1, 'nap_port_id' => 1,
        ], $this->appHeaders($employeeId));
    }

    private function request(int $id): object
    {
        return DB::table('work_requests')->where('id', $id)->first();
    }

    // ---- sign in ----------------------------------------------------------------------------------

    public function test_signing_in_returns_a_token_and_signing_out_ends_it(): void
    {
        DB::table('employees')->where('id', 1)->update(['user_id' => 5]);
        $login = $this->postJson('/backend/api/employee_login.php', ['username' => 'tech', 'password' => 'secret123']);
        $login->assertOk()->assertJsonPath('user.employee_id', 1);
        $token = $login->json('token');
        $this->assertSame(64, strlen($token));
        $this->assertSame(0, DB::table('employee_api_tokens')->where('token_hash', $token)->count(), 'only a hash is stored');

        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        $this->getJson('/backend/api/me.php', $headers)->assertOk()->assertJsonPath('employee.full_name', 'Eli Santos');

        $this->postJson('/backend/api/employee_logout.php', ['login_history_id' => $login->json('user.login_history_id')], $headers)->assertOk();
        $this->getJson('/backend/api/me.php', $headers)->assertUnauthorized();
    }

    public function test_a_wrong_password_gets_no_token_and_an_inactive_employee_cannot_sign_in(): void
    {
        DB::table('employees')->where('id', 1)->update(['user_id' => 5]);
        $this->postJson('/backend/api/employee_login.php', ['username' => 'tech', 'password' => 'wrong'])->assertUnauthorized()->assertJsonMissingPath('token');

        DB::table('employees')->where('id', 1)->update(['status' => 'inactive']);
        $this->postJson('/backend/api/employee_login.php', ['username' => 'tech', 'password' => 'secret123'])->assertUnauthorized();
    }

    public function test_an_expired_token_or_a_deactivated_employee_is_signed_out(): void
    {
        $headers = $this->appHeaders(1);
        $this->getJson('/backend/api/me.php', $headers)->assertOk();

        DB::table('employee_api_tokens')->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/backend/api/me.php', $headers)->assertUnauthorized();

        $fresh = $this->appHeaders(1);
        DB::table('employees')->where('id', 1)->update(['status' => 'inactive']);
        $this->getJson('/backend/api/me.php', $fresh)->assertUnauthorized();
    }

    // ---- the home screen, barangays and clients ---------------------------------------------------

    public function test_the_home_screen_knows_who_is_on_duty(): void
    {
        $off = $this->getJson('/backend/api/me.php', $this->appHeaders(3))->assertOk();
        $off->assertJsonPath('today.checked_in', false)->assertJsonPath('today.on_duty', false);

        DB::table('attendance')->insert(['employee_id' => 3, 'attendance_date' => today()->toDateString(), 'morning_time_in' => '08:05:00', 'status_morning' => 'present', 'branch_name' => 'Balilihan']);
        $on = $this->getJson('/backend/api/me.php', $this->appHeaders(3))->assertOk();
        $on->assertJsonPath('today.checked_in', true)->assertJsonPath('today.on_duty', true)->assertJsonPath('today.check_in_time', '08:05:00');

        DB::table('attendance')->where('employee_id', 3)->update(['morning_time_out' => '12:00:00']);
        $this->getJson('/backend/api/me.php', $this->appHeaders(3))->assertJsonPath('today.on_duty', false);
    }

    public function test_the_barangays_and_clients_are_those_of_the_employees_branch(): void
    {
        $barangays = $this->getJson('/backend/api/barangays.php', $this->appHeaders(1))->assertOk()->json('data');
        $this->assertContains('Boctol', $barangays);
        $this->assertNotContains('Agsoso', $barangays);

        $clients = $this->getJson('/backend/api/clients_search.php', $this->appHeaders(1))->assertOk()->json('data');
        $this->assertSame(['Ana Reyes'], array_column($clients, 'full_name'));
        $this->assertSame('NAP-01', $clients[0]['nap_box_name']);
        $this->assertSame(2, $clients[0]['nap_port_number']);

        $this->getJson('/backend/api/clients_search.php?q=ben', $this->appHeaders(1))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/backend/api/clients_search.php?q=ben', $this->appHeaders(2))->assertOk()->assertJsonCount(1, 'data');
    }

    // ---- NAP boxes --------------------------------------------------------------------------------

    public function test_nap_boxes_show_their_ports_and_only_the_branchs_own(): void
    {
        $boxes = $this->getJson('/backend/api/nap_boxes.php', $this->appHeaders(1))->assertOk()->json('data');
        $this->assertCount(1, $boxes);
        $this->assertEquals(['name' => 'NAP-01', 'total_ports' => 4, 'occupied_ports' => 1, 'faulty_ports' => 1, 'reserved_ports' => 0, 'available_ports' => 2],
            array_intersect_key($boxes[0], array_flip(['name', 'total_ports', 'occupied_ports', 'faulty_ports', 'reserved_ports', 'available_ports'])));

        $this->getJson('/backend/api/nap_box.php?nap_box_id=2', $this->appHeaders(1))->assertNotFound();
        $detail = $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(1))->assertOk()->json('data');
        $this->assertSame(['available', 'occupied', 'available', 'faulty'], array_column($detail['ports'], 'status'));
        $this->assertSame('Ana Reyes', $detail['ports'][1]['client_name']);
    }

    public function test_nap_boxes_can_be_searched_by_barangay_and_filtered(): void
    {
        $headers = $this->appHeaders(1);
        $this->getJson('/backend/api/nap_boxes.php?barangay=Boctol', $headers)->assertJsonCount(1, 'data');
        $this->getJson('/backend/api/nap_boxes.php?barangay=Cabug', $headers)->assertJsonCount(0, 'data');
        $this->getJson('/backend/api/nap_boxes.php?q=purok', $headers)->assertJsonCount(1, 'data');
        $this->getJson('/backend/api/nap_boxes.php?filter=available', $headers)->assertJsonCount(1, 'data');
        $this->getJson('/backend/api/nap_boxes.php?filter=full', $headers)->assertJsonCount(0, 'data');

        DB::table('nap_ports')->whereIn('id', [1, 3])->update(['status' => 'occupied']);
        $this->getJson('/backend/api/nap_boxes.php?filter=full', $headers)->assertJsonCount(1, 'data');
        $this->getJson('/backend/api/nap_boxes.php?filter=available', $headers)->assertJsonCount(0, 'data');
    }

    // ---- an installation request ------------------------------------------------------------------

    public function test_an_installation_for_a_new_client_waits_for_approval_and_reserves_the_port(): void
    {
        $response = $this->install(1)->assertCreated();
        $response->assertJsonPath('data.status', 'pending_approval')->assertJsonPath('data.is_new_client', true)->assertJsonPath('data.client_name', 'Nina Reyes');
        $this->assertStringStartsWith('WR-', $response->json('data.request_code'));
        $this->assertSame('Balilihan', $response->json('data.branch_name'));

        // The port is not occupied yet, but nobody else can take it.
        $this->assertSame('available', DB::table('nap_ports')->where('id', 1)->value('status'));
        $detail = $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(3))->json('data');
        $this->assertSame('reserved', $detail['ports'][0]['status']);
        $this->assertSame(1, $detail['reserved_ports']);

        $clash = $this->install(3);
        $clash->assertStatus(422);
        $this->assertStringContainsString('already reserved', $clash->json('message'));
        $this->assertSame(1, DB::table('work_requests')->count());
    }

    public function test_an_installation_is_checked_before_it_is_saved(): void
    {
        $otherBarangay = collect(NapBoxes::barangaysOf('Balilihan'))->first(fn (string $name) => $name !== 'Boctol');
        $cases = [
            'no client' => [['new_client' => null], 'Choose an existing client'],
            'bad phone' => [['new_client' => ['name' => 'Nina Reyes', 'phone' => '0917', 'address' => 'Purok 3']], 'exactly 11 digits'],
            'no address' => [['new_client' => ['name' => 'Nina Reyes', 'phone' => '09171234567', 'address' => '']], 'address of the new client'],
            'no port' => [['nap_port_id' => null], 'Choose the NAP box and an available port'],
            'occupied port' => [['nap_port_id' => 2], 'already occupied'],
            'faulty port' => [['nap_port_id' => 4], 'faulty'],
            'port of another branch' => [['nap_box_id' => 2, 'nap_port_id' => 5], 'not in Boctol'],
            'box of another barangay' => [['barangay' => $otherBarangay], "not in {$otherBarangay}"],
            'barangay of another branch' => [['barangay' => 'Agsoso'], 'not a barangay of Balilihan'],
            'port of another box' => [['nap_port_id' => 5], 'Choose a port of an active NAP box'],
            'unknown type' => [['work_type' => 'upgrade'], 'Choose Installation or Repair'],
        ];
        foreach ($cases as $name => [$override, $message]) {
            $response = $this->install(1, $override);
            $response->assertStatus(422);
            $this->assertStringContainsString($message, $response->json('message'), $name);
        }
        $this->assertSame(0, DB::table('work_requests')->count());
    }

    public function test_an_installation_can_be_for_an_existing_client_who_is_not_connected_yet(): void
    {
        DB::table('clients')->insert(['id' => 4, 'client_code' => 'JFT-4', 'full_name' => 'Dina Cruz', 'branch_name' => 'Balilihan', 'registered_barangay_name' => 'Boctol', 'status' => 'active', 'archived' => false]);

        $ok = $this->install(1, ['client_id' => 4, 'new_client' => null, 'barangay' => '']);
        $ok->assertCreated()->assertJsonPath('data.client_name', 'Dina Cruz')->assertJsonPath('data.is_new_client', false)->assertJsonPath('data.barangay_name', 'Boctol');

        $twice = $this->install(3, ['client_id' => 4, 'new_client' => null, 'nap_port_id' => 3]);
        $twice->assertStatus(422);
        $this->assertStringContainsString('already has an open installation request', $twice->json('message'));
    }

    public function test_a_client_who_is_already_connected_gets_a_repair_not_an_installation(): void
    {
        $response = $this->install(1, ['client_id' => 1, 'new_client' => null, 'nap_port_id' => 1]);
        $response->assertStatus(422);
        $this->assertStringContainsString('already connected', $response->json('message'));
    }

    public function test_a_client_of_another_branch_cannot_be_chosen(): void
    {
        $response = $this->install(1, ['client_id' => 2, 'new_client' => null]);
        $response->assertStatus(422);
        $this->assertStringContainsString('client of your branch', $response->json('message'));
    }

    // ---- a repair request -------------------------------------------------------------------------

    public function test_a_repair_is_for_an_existing_client_and_needs_the_problem(): void
    {
        $headers = $this->appHeaders(1);
        $this->postJson('/backend/api/work_request_create.php', ['work_type' => 'repair', 'problem_details' => 'No internet'], $headers)
            ->assertStatus(422)->assertJsonPath('message', 'A repair is for an existing client. Choose the client.');
        $this->postJson('/backend/api/work_request_create.php', ['work_type' => 'repair', 'client_id' => 1], $headers)
            ->assertStatus(422);

        $ok = $this->postJson('/backend/api/work_request_create.php', ['work_type' => 'repair', 'client_id' => 1, 'problem_details' => 'No internet, cable cut', 'location' => 'Purok 1', 'nap_port_id' => 3], $headers);
        $ok->assertCreated()->assertJsonPath('data.work_type', 'repair')->assertJsonPath('data.problem_details', 'No internet, cable cut');
        $this->assertNull($this->request($ok->json('data.id'))->nap_port_id, 'a repair does not propose a port');
    }

    // ---- returned, resubmitted, cancelled ---------------------------------------------------------

    public function test_a_returned_request_can_be_corrected_and_sent_again(): void
    {
        $id = $this->install(1)->json('data.id');
        WorkRequestService::returnForCorrection($id, 2, 'Wrong barangay, please check.');

        $returned = $this->getJson("/backend/api/work_request.php?id={$id}", $this->appHeaders(1))->assertOk();
        $returned->assertJsonPath('data.status', 'returned')->assertJsonPath('data.admin_remarks', 'Wrong barangay, please check.');

        $this->postJson('/backend/api/work_request_update.php', ['id' => $id, 'work_type' => 'installation', 'new_client' => ['name' => 'Nina Reyes', 'phone' => '09171234567', 'address' => 'Purok 3, Boctol'], 'barangay' => 'Boctol', 'nap_box_id' => 1, 'nap_port_id' => 3], $this->appHeaders(1))
            ->assertOk()->assertJsonPath('data.status', 'pending_approval')->assertJsonPath('data.nap_port_number', 3);
    }

    public function test_only_a_returned_request_can_be_edited_and_only_by_its_employee(): void
    {
        $id = $this->install(1)->json('data.id');
        $payload = ['id' => $id, 'work_type' => 'installation', 'new_client' => ['name' => 'Nina Reyes', 'phone' => '09171234567', 'address' => 'Purok 3'], 'barangay' => 'Boctol', 'nap_box_id' => 1, 'nap_port_id' => 3];

        $this->postJson('/backend/api/work_request_update.php', $payload, $this->appHeaders(1))->assertStatus(422);
        WorkRequestService::returnForCorrection($id, 2, 'Please check');
        $this->postJson('/backend/api/work_request_update.php', $payload, $this->appHeaders(3))->assertStatus(422)->assertJsonPath('message', 'That request was not found.');
    }

    public function test_a_request_can_be_cancelled_until_it_is_approved_and_the_port_is_free_again(): void
    {
        $id = $this->install(1)->json('data.id');
        $this->postJson('/backend/api/work_request_cancel.php', ['id' => $id], $this->appHeaders(1))->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->install(3)->assertCreated();

        $second = DB::table('work_requests')->orderByDesc('id')->value('id');
        WorkRequestService::approve($second, 2);
        $this->postJson('/backend/api/work_request_cancel.php', ['id' => $second], $this->appHeaders(3))->assertStatus(422);
    }

    public function test_an_employee_only_sees_their_own_requests(): void
    {
        $id = $this->install(1)->json('data.id');

        $this->getJson("/backend/api/work_request.php?id={$id}", $this->appHeaders(3))->assertNotFound();
        $this->getJson("/backend/api/work_request.php?id={$id}", $this->appHeaders(2))->assertNotFound();
        $this->getJson('/backend/api/work_requests.php', $this->appHeaders(3))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/backend/api/work_requests.php', $this->appHeaders(1))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/backend/api/work_requests.php?status=approved', $this->appHeaders(1))->assertOk()->assertJsonCount(0, 'data');
    }

    // ---- the admin's review -----------------------------------------------------------------------

    public function test_approving_return_and_reject_need_the_right_state_and_a_reason(): void
    {
        $id = $this->install(1)->json('data.id');

        foreach (['returnForCorrection', 'reject'] as $method) {
            try {
                WorkRequestService::$method($id, 2, ' ');
                $this->fail("{$method} without a reason");
            } catch (\DomainException $exception) {
                $this->assertSame('Write what the employee has to correct.', $exception->getMessage());
            }
        }

        WorkRequestService::approve($id, 2, 'Go ahead');
        $row = $this->request($id);
        $this->assertSame('approved', $row->status);
        $this->assertSame('Go ahead', $row->admin_remarks);
        $this->assertSame(2, (int) $row->reviewed_by);

        $this->expectException(\DomainException::class);
        WorkRequestService::approve($id, 2);
    }

    public function test_a_port_taken_in_the_meantime_blocks_the_approval(): void
    {
        $id = $this->install(1)->json('data.id');
        DB::table('nap_ports')->where('id', 1)->update(['status' => 'occupied', 'client_id' => 1]);

        $this->expectExceptionMessage('already occupied');
        WorkRequestService::approve($id, 2);
    }

    // ---- the completion report --------------------------------------------------------------------

    private function approvedInstallation(int $employeeId = 1): int
    {
        $id = $this->install($employeeId)->json('data.id');
        WorkRequestService::approve($id, 2);

        return $id;
    }

    private function report(int $id, array $extra = [], int $employeeId = 1): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/backend/api/work_request_complete.php', $extra + [
            'id' => $id, 'completion_notes' => 'Connected and tested, 40 Mbps.', 'actual_nap_box_id' => 1, 'actual_nap_port_id' => 1,
            'materials' => [['inventory_id' => 1, 'used_quantity' => 2, 'returned_quantity' => 1, 'damaged_quantity' => 1]],
        ], $this->appHeaders($employeeId));
    }

    public function test_a_completion_report_needs_an_approved_request_and_real_details(): void
    {
        $pending = $this->install(1)->json('data.id');
        $this->report($pending)->assertStatus(422)->assertJsonPath('message', 'A completion report can only be sent for an approved request.');
        WorkRequestService::approve($pending, 2);

        $this->report($pending, ['completion_notes' => ''])->assertStatus(422);
        $this->report($pending, ['actual_nap_port_id' => null])->assertStatus(422)->assertJsonPath('message', 'Choose the NAP box and port that were really used.');
        $this->report($pending, ['actual_nap_port_id' => 2])->assertStatus(422);
        $this->report($pending, ['materials' => [['inventory_id' => 2, 'used_quantity' => 1]]])->assertStatus(422)->assertJsonPath('message', 'Choose materials from the stock of your branch.');
        $this->report($pending, ['materials' => [['inventory_id' => 1, 'used_quantity' => -1]]])->assertStatus(422);
        $this->assertSame('approved', $this->request($pending)->status);

        $this->report($pending, [], 3)->assertStatus(422);
        $this->report($pending)->assertOk()->assertJsonPath('data.status', 'work_completed')->assertJsonPath('data.materials.0.used_quantity', 2);
    }

    public function test_the_port_actually_used_may_differ_from_the_proposed_one(): void
    {
        $id = $this->approvedInstallation();
        $this->report($id, ['actual_nap_port_id' => 3])->assertOk()->assertJsonPath('data.actual_nap_port_number', 3)->assertJsonPath('data.nap_port_number', 1);

        // The port that was really used is the one that is held now.
        $detail = $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(3))->json('data');
        $this->assertSame(['available', 'occupied', 'reserved', 'faulty'], array_column($detail['ports'], 'status'));
    }

    public function test_a_repair_report_only_needs_the_notes(): void
    {
        $id = $this->postJson('/backend/api/work_request_create.php', ['work_type' => 'repair', 'client_id' => 1, 'problem_details' => 'No internet'], $this->appHeaders(1))->json('data.id');
        WorkRequestService::approve($id, 2);

        $this->postJson('/backend/api/work_request_complete.php', ['id' => $id, 'completion_notes' => 'Replaced the damaged drop cable.'], $this->appHeaders(1))
            ->assertOk()->assertJsonPath('data.status', 'work_completed');
    }

    // ---- the admin confirms -----------------------------------------------------------------------

    public function test_confirming_an_installation_saves_the_client_takes_the_port_and_finalises_the_stock(): void
    {
        $id = $this->approvedInstallation();
        $this->report($id)->assertOk();

        WorkRequestService::confirm($id, 2, ['internet_plan_id' => 1, 'email' => 'nina@example.com']);

        $request = $this->request($id);
        $this->assertSame('confirmed', $request->status);
        $this->assertSame(2, (int) $request->confirmed_by);

        // The new client exists, in the branch and barangay, with the plan and a first bill.
        $client = DB::table('clients')->where('id', $request->client_id)->first();
        $this->assertSame('Nina Reyes', $client->full_name);
        $this->assertSame('09171234567', $client->contact_number);
        $this->assertSame('Balilihan', $client->branch_name);
        $this->assertSame('Boctol', $client->registered_barangay_name);
        $this->assertSame('Starter', $client->wifi_plan);
        $this->assertEquals(999, $client->monthly_rate);
        $this->assertSame(1, DB::table('bills')->where('client_id', $client->id)->count());

        // The port is now occupied by that client.
        $port = DB::table('nap_ports')->where('id', 1)->first();
        $this->assertSame('occupied', $port->status);
        $this->assertSame((int) $client->id, (int) $port->client_id);

        // Stock: 1 returned goes back on the shelf; used and damaged only leave a record.
        $this->assertSame(11, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
        $movements = DB::table('inventory_stock_movements')->where('inventory_id', 1)->where('id', '>', 1)->orderBy('id')->get(['movement_type', 'quantity_change', 'balance_after']);
        $this->assertSame(['stock_returned', 'job_used', 'job_damaged'], $movements->pluck('movement_type')->all());
        $this->assertSame([1, 0, 0], $movements->pluck('quantity_change')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([11, 11, 11], $movements->pluck('balance_after')->map(fn ($v) => (int) $v)->all());

        // Every one of them says which employee, client and NAP box the job was for.
        foreach (DB::table('inventory_stock_movements')->where('work_request_id', $id)->get() as $movement) {
            $this->assertSame(1, (int) $movement->employee_id);
            $this->assertSame((int) $client->id, (int) $movement->client_id);
            $this->assertSame(1, (int) $movement->nap_box_id);
        }
        $this->assertSame(3, DB::table('inventory_stock_movements')->where('work_request_id', $id)->count());
    }

    public function test_the_stock_transactions_show_the_employee_and_the_client_and_nap_of_the_job(): void
    {
        $id = $this->approvedInstallation();
        $this->report($id)->assertOk();
        WorkRequestService::confirm($id, 2, ['internet_plan_id' => 1]);

        $this->signIn(2);
        $rows = $this->get('/inventory?tab=movements')->assertOk()->viewData('movements');
        $job = collect($rows)->firstWhere('movement_type', 'job_used');
        $this->assertSame('Eli Santos', $job->employee_name);
        $this->assertSame('Nina Reyes', $job->client_name);
        $this->assertSame('NAP-01', $job->nap_box_name);

        $html = $this->get('/inventory?tab=movements')->getContent();
        $this->assertStringContainsString('Client / NAP', $html);
        $this->assertStringContainsString('Nina Reyes (', $html);
        $this->assertStringContainsString('· NAP-01', $html);
        $this->assertStringContainsString('Used on Job', $html);

        // And the NAP box lists the same materials under "Inventory used".
        $used = $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(1))->assertOk()->json('data.inventory_used');
        $this->assertSame(['Router', 'Router', 'Router'], array_column($used, 'item_name'));
        $this->assertContains('Used on Job', array_column($used, 'type_label'));
        $this->assertSame('Nina Reyes', $used[0]['client_name']);
        $this->assertSame('Eli Santos', $used[0]['employee_name']);
    }

    public function test_nap_boxes_are_never_offered_to_the_app_as_stock_or_material(): void
    {
        DB::table('inventory')->insert(['id' => 60, 'item_name' => 'NAP Box 8-port', 'sku' => 'SKU-60', 'category' => 'NAP Box', 'quantity' => 4, 'unit_price' => 900, 'reorder_level' => 1, 'branch_name' => 'Balilihan']);
        $headers = $this->appHeaders(1);

        $items = $this->getJson('/backend/api/inventory_items.php', $headers)->assertOk();
        $this->assertNotContains('NAP Box 8-port', array_column($items->json('data'), 'item_name'));
        $this->assertNotContains('NAP Box 8-port', array_column($items->json('all_items'), 'item_name'));

        $request = $this->postJson('/backend/api/inventory_request_create.php', ['items' => [['inventory_id' => 60, 'quantity' => 1]]], $headers);
        $request->assertStatus(422);
        $this->assertStringContainsString('is a NAP box', $request->json('message'));

        $pending = $this->approvedInstallation();
        $this->report($pending, ['materials' => [['inventory_id' => 60, 'used_quantity' => 1]]])->assertStatus(422);
        $this->assertSame(4, (int) DB::table('inventory')->where('id', 60)->value('quantity'));
    }

    public function test_a_new_client_needs_a_plan_to_be_confirmed(): void
    {
        $id = $this->approvedInstallation();
        $this->report($id)->assertOk();

        try {
            WorkRequestService::confirm($id, 2, []);
            $this->fail('confirmed without a plan');
        } catch (\DomainException $exception) {
            $this->assertSame('Choose the internet plan of the new client.', $exception->getMessage());
        }
        $this->assertSame('work_completed', $this->request($id)->status);
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Nina Reyes')->count(), 'nothing is saved when a step fails');
        $this->assertSame('available', DB::table('nap_ports')->where('id', 1)->value('status'));
        $this->assertSame(10, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    public function test_a_port_taken_before_the_confirmation_stops_it_and_changes_nothing(): void
    {
        $id = $this->approvedInstallation();
        $this->report($id)->assertOk();
        DB::table('nap_ports')->where('id', 1)->update(['status' => 'occupied', 'client_id' => 1]);

        try {
            WorkRequestService::confirm($id, 2, ['internet_plan_id' => 1]);
            $this->fail('confirmed onto an occupied port');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('already occupied', $exception->getMessage());
        }
        $this->assertSame('work_completed', $this->request($id)->status);
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Nina Reyes')->count());
        $this->assertSame(10, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    public function test_confirming_an_installation_for_an_existing_client_only_takes_the_port(): void
    {
        DB::table('clients')->insert(['id' => 4, 'client_code' => 'JFT-4', 'full_name' => 'Dina Cruz', 'branch_name' => 'Balilihan', 'registered_barangay_name' => 'Boctol', 'status' => 'active', 'archived' => false]);
        $id = $this->install(1, ['client_id' => 4, 'new_client' => null])->json('data.id');
        WorkRequestService::approve($id, 2);
        $this->report($id, ['materials' => []])->assertOk();

        WorkRequestService::confirm($id, 2);

        $this->assertSame(4, (int) DB::table('nap_ports')->where('id', 1)->value('client_id'));
        $this->assertSame(4, DB::table('clients')->count(), 'no second client is created');
        $this->assertSame(10, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    public function test_confirming_a_repair_leaves_the_port_alone(): void
    {
        $id = $this->postJson('/backend/api/work_request_create.php', ['work_type' => 'repair', 'client_id' => 1, 'problem_details' => 'No internet'], $this->appHeaders(1))->json('data.id');
        WorkRequestService::approve($id, 2);
        $this->postJson('/backend/api/work_request_complete.php', ['id' => $id, 'completion_notes' => 'Replaced the cable.', 'materials' => [['inventory_id' => 1, 'used_quantity' => 3]]], $this->appHeaders(1))->assertOk();

        WorkRequestService::confirm($id, 2);

        $this->assertSame('confirmed', $this->request($id)->status);
        $this->assertSame('occupied', DB::table('nap_ports')->where('id', 2)->value('status'));
        $this->assertSame(1, (int) DB::table('nap_ports')->where('id', 2)->value('client_id'));
        $this->assertSame(10, (int) DB::table('inventory')->where('id', 1)->value('quantity'), 'the 3 used were taken out when the items were released');
        $this->assertSame(1, DB::table('inventory_stock_movements')->where('movement_type', 'job_used')->count());
    }

    public function test_a_wrong_report_goes_back_to_the_employee(): void
    {
        $id = $this->approvedInstallation();
        $this->report($id)->assertOk();
        WorkRequestService::returnCompletion($id, 2, 'Port number is wrong.');

        $this->assertSame('approved', $this->request($id)->status);
        $this->assertNull($this->request($id)->completed_at);
        $this->report($id, ['actual_nap_port_id' => 3])->assertOk()->assertJsonPath('data.status', 'work_completed');
    }

    public function test_the_other_branchs_requests_cannot_be_decided_from_this_branch_scope(): void
    {
        $id = $this->install(1)->json('data.id');

        $this->assertNotNull(WorkRequestService::detail($id, null, 'Balilihan'));
        $this->assertNull(WorkRequestService::detail($id, null, 'Loon'));
        $this->assertNull(WorkRequestService::detail($id, null, ''), 'no branch means no access');
        $this->assertSame([], WorkRequestService::listing(null, 'Loon'));
        $this->assertCount(1, WorkRequestService::listing(null, null), 'null is every branch');
    }

    // ---- history ----------------------------------------------------------------------------------

    public function test_the_history_lists_stock_movements_and_jobs_of_the_employee(): void
    {
        $this->postJson('/backend/api/inventory_request_create.php', ['request_type' => 'out', 'items' => [['inventory_id' => 1, 'quantity' => 2]], 'item_condition' => 'Good', 'area_used' => 'Installation', 'person_in_charge' => 'Marino'], $this->appHeaders(1))->assertCreated();
        $this->postJson('/backend/api/inventory_request_create.php', ['request_type' => 'return', 'items' => [['inventory_id' => 1, 'quantity' => 1]], 'item_condition' => 'Damaged'], $this->appHeaders(1))->assertCreated();
        $this->install(1)->assertCreated();

        $all = $this->getJson('/backend/api/transactions.php', $this->appHeaders(1))->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['item_out', 'item_return', 'installation'], array_values(array_unique(array_column($all, 'kind'))));
        $this->assertContains('Qty 2 • Used for: Installation • In charge: Marino', array_column($all, 'detail'));

        $this->getJson('/backend/api/transactions.php?type=inventory', $this->appHeaders(1))->assertJsonCount(3, 'data');
        $this->getJson('/backend/api/transactions.php?type=installation', $this->appHeaders(1))->assertJsonCount(1, 'data');
        $this->getJson('/backend/api/transactions.php?type=repair', $this->appHeaders(1))->assertJsonCount(0, 'data');
        $this->getJson('/backend/api/transactions.php', $this->appHeaders(3))->assertJsonCount(0, 'data');
    }

    // ---- item out and item return -----------------------------------------------------------------

    public function test_item_out_requests_reserve_stock_but_returns_do_not(): void
    {
        $headers = $this->appHeaders(1);
        $this->postJson('/backend/api/inventory_request_create.php', ['items' => [['inventory_id' => 1, 'quantity' => 8]]], $headers)->assertCreated();
        $tooMany = $this->postJson('/backend/api/inventory_request_create.php', ['items' => [['inventory_id' => 1, 'quantity' => 3]]], $headers);
        $tooMany->assertStatus(422);
        $this->assertStringContainsString('only has', $tooMany->json('message'));

        // 2 already reserved at the seed (10 - 2 pending - 8 now = 0 free), but a return is still accepted.
        $this->postJson('/backend/api/inventory_request_create.php', ['request_type' => 'return', 'items' => [['inventory_id' => 1, 'quantity' => 5]], 'item_condition' => 'Good'], $headers)->assertCreated();
        $items = $this->getJson('/backend/api/inventory_items.php', $headers)->assertOk();
        $items->assertJsonCount(0, 'data');
        $items->assertJsonCount(1, 'all_items');
    }

    public function test_the_admin_accepts_a_return_and_the_stock_goes_up(): void
    {
        $this->postJson('/backend/api/inventory_request_create.php', ['request_type' => 'return', 'items' => [['inventory_id' => 1, 'quantity' => 4]], 'item_condition' => 'Good'], $this->appHeaders(1))->assertCreated();
        $requestId = (int) DB::table('inventory_requests')->orderByDesc('id')->value('id');

        $this->signIn(2);
        $this->post("/inventory-requests/{$requestId}/approve")->assertSessionHas('success', 'Return accepted. The items are back in stock.');

        $this->assertSame(14, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
        $movement = DB::table('inventory_stock_movements')->orderByDesc('id')->first();
        $this->assertSame('stock_returned', $movement->movement_type);
        $this->assertSame(14, (int) $movement->balance_after);
        $this->assertStringContainsString('Condition: Good', $movement->remarks);
    }
}
