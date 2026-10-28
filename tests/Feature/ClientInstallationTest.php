<?php

namespace Tests\Feature;

use App\Support\Coordinates;
use App\Support\NapBoxes;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Registering a client with a NAP box and port, and installing a client from the employee app: the employee reports, the branch
 * admin edits and confirms, and only then the client exists, the port is connected and the inventory is updated.
 *
 * Seed: NAP-01 (box 1, Boctol, Balilihan, ports 1-4, port 2 used by client 1) and NAP-02 (box 2, Agsoso, Loon, ports 5-6).
 * Employee 1 works in Balilihan (admin: user 2), employee 2 in Loon (admin: user 3). Stock 1 is a Router (10) of Balilihan.
 */
class ClientInstallationTest extends TestCase
{
    use BranchTestData;

    private const WHERE = '9.6541, 124.1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
        DB::table('internet_plans')->insert(['id' => 1, 'plan_name' => 'Starter', 'speed_mbps' => 100, 'monthly_price' => 999, 'installation_fee' => 0, 'status' => 'active', 'archived' => false]);
        DB::table('inventory')->insert(['id' => 50, 'item_name' => 'NAP Box 8-port', 'sku' => 'SKU-50', 'category' => 'NAP Box', 'quantity' => 5, 'unit_price' => 900, 'reorder_level' => 1, 'branch_name' => 'Balilihan']);

        NapBoxes::create('Balilihan', 'NAP-01', 'Boctol', 'Purok 2', 4, 9.65, 124.12);
        NapBoxes::create('Loon', 'NAP-02', 'Agsoso', null, 2, 9.80, 123.80);
        DB::table('nap_ports')->where('id', 2)->update(['status' => 'occupied', 'client_id' => 1]);
    }

    /** The form of Register New Client. */
    private function registerForm(array $extra = []): array
    {
        return $extra + [
            'action' => 'register', 'first_name' => 'Dina', 'last_name' => 'Lopez', 'contact_number' => '09171234567',
            'email' => 'dina@example.com', 'branch_name' => 'Balilihan', 'registered_barangay_name' => 'Boctol', 'exact_address' => 'Purok 1',
            'installation_date' => now()->toDateString(), 'internet_plan_id' => 1, 'payment_mode' => 'gcash',
            'ip_address' => '192.168.1.20', 'facebook_account' => 'Dina Lopez', 'coordinates' => self::WHERE,
        ];
    }

    /** What the app sends when the technician files the report (the router he took is request 10). */
    private function report(array $extra = []): array
    {
        return $extra + [
            'full_name' => 'Gina Mora', 'contact_number' => '09181234567', 'barangay_name' => 'Boctol', 'exact_address' => 'Purok 3, Sitio Riverside',
            'internet_plan_id' => 1, 'payment_mode' => 'cash', 'ip_address' => '10.0.0.8', 'facebook_account' => 'gina.mora',
            'coordinates' => self::WHERE, 'nap_port_id' => 1, 'request_ids' => [10], 'remarks' => 'Cable passes the mango tree.',
            'returns' => [['inventory_id' => 1, 'quantity' => 1, 'item_condition' => 'Good']],
        ];
    }

    /** Employee 1 took 3 routers for an installation; the admin has already approved (the stock was already reduced to 7). */
    private function takeRouters(): void
    {
        DB::table('inventory')->where('id', 1)->update(['quantity' => 7]);
        DB::table('inventory_requests')->insert([
            'id' => 10, 'employee_id' => 1, 'employee_name' => 'Eli Santos', 'inventory_id' => 1, 'item_name' => 'Router', 'quantity' => 3,
            'status' => 'approved', 'request_type' => 'out', 'area_used' => 'Installation', 'approved_by' => 2, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function file(array $input = [], int $employee = 1): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/backend/api/installation_create.php', $input, $this->appHeaders($employee));
    }

    // ---- Google coordinates -----------------------------------------------------------------------

    public function test_coordinates_are_read_from_what_google_maps_gives(): void
    {
        $this->assertSame([9.6541, 124.1234], Coordinates::parse('9.6541, 124.1234'));
        $this->assertSame([9.6541, 124.1234], Coordinates::parse('  9.6541 124.1234 '));
        $this->assertSame([9.6541, 124.1234], Coordinates::parse('https://www.google.com/maps/place/Balilihan/@9.6541,124.1234,17z'));
        $this->assertSame([9.6541, 124.1234], Coordinates::parse('https://www.google.com/maps?q=9.6541,124.1234'));
        $this->assertSame([9.6541, 124.1234], Coordinates::parse('https://www.google.com/maps/place/x/data=!3d9.6541!4d124.1234'));
        $this->assertSame('9.654100, 124.123400', Coordinates::format(9.6541, 124.1234));
        $this->assertSame('', Coordinates::format(null, null));
        $this->assertNull(Coordinates::mapUrl(null, null));
        $this->assertSame('https://www.google.com/maps?q=9.654100,124.123400', Coordinates::mapUrl('9.6541', '124.1234'));

        foreach (['', 'near the church', '9.6541', '124.1234, 9.6541', '40.7128, -74.0060'] as $bad) {
            try {
                Coordinates::parse($bad);
                $this->fail("'{$bad}' should be refused");
            } catch (DomainException) {
                $this->assertTrue(true);
            }
        }
    }

    // ---- Register Client: barangay -> NAP boxes -> port --------------------------------------------

    public function test_the_nap_boxes_of_a_barangay_come_with_their_ports_and_who_is_plugged_in(): void
    {
        $this->signIn(2);
        $boxes = $this->getJson('/nap-boxes/lookup?barangay=Boctol')->assertOk()->json('boxes');

        $this->assertCount(1, $boxes);
        $this->assertSame('NAP-01', $boxes[0]['name']);
        $this->assertSame('9.650000, 124.120000', $boxes[0]['coordinates']);
        $this->assertSame(3, $boxes[0]['available_ports']);
        $this->assertCount(4, $boxes[0]['ports']);
        $this->assertSame('occupied', $boxes[0]['ports'][1]['status']);
        $this->assertSame('Ana Reyes', $boxes[0]['ports'][1]['client_name']);
        $this->assertSame('JFT-1', $boxes[0]['ports'][1]['client_code']);

        $this->assertSame([], $this->getJson('/nap-boxes/lookup?barangay=Agsoso')->json('boxes'), 'a Balilihan admin never sees the boxes of Loon');
        $this->assertSame([], $this->getJson('/nap-boxes/lookup')->json('boxes'));
        $this->assertSame(['Balilihan'], array_column($this->getJson('/nap-boxes/lookup?barangay=Boctol&branch=Loon')->json('boxes'), 'branch_name'), 'the branch of a branch admin cannot be switched');
        $this->assertSame([], $this->getJson('/nap-boxes/lookup?barangay=Agsoso&branch=Loon')->json('boxes'));

        $this->signIn(3);
        $this->assertSame(['NAP-02'], array_column($this->getJson('/nap-boxes/lookup?barangay=Agsoso')->json('boxes'), 'name'));
    }

    public function test_a_client_is_registered_with_the_installation_details_and_a_nap_port(): void
    {
        $this->signIn(2);
        $page = $this->post('/clients', $this->registerForm(['nap_port_id' => 3]))->assertOk();

        $this->assertSame('Client registered successfully!', $page->viewData('success'));
        $client = DB::table('clients')->where('full_name', 'Dina Lopez')->first();
        $this->assertSame('Balilihan', $client->branch_name);
        $this->assertSame('gcash', $client->payment_mode);
        $this->assertSame('192.168.1.20', $client->ip_address);
        $this->assertSame('Dina Lopez', $client->facebook_account);
        $this->assertEqualsWithDelta(9.6541, (float) $client->latitude, 0.00001);
        $this->assertEqualsWithDelta(124.1234, (float) $client->longitude, 0.00001);
        $this->assertSame(1, DB::table('bills')->where('client_id', $client->id)->count(), 'the first bill is made');

        $port = DB::table('nap_ports')->where('id', 3)->first();
        $this->assertSame('occupied', $port->status);
        $this->assertSame((int) $client->id, (int) $port->client_id);

        // The same port cannot be given to a second client, and nothing of that client is saved.
        $again = $this->post('/clients', $this->registerForm(['first_name' => 'Edna', 'nap_port_id' => 3]))->assertOk();
        $this->assertStringContainsString('already connected to a client', $again->viewData('error'));
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Edna Lopez')->count());
        $this->assertSame('Edna', $again->viewData('registerValues')['first_name'], 'the refused form is shown again');
    }

    public function test_a_client_can_be_registered_without_a_port_but_not_without_coordinates_or_mop_or_with_a_wrong_port(): void
    {
        $this->signIn(2);
        $this->post('/clients', $this->registerForm())->assertOk();
        $this->assertSame(1, DB::table('clients')->where('full_name', 'Dina Lopez')->count());

        $cases = [
            'no coordinates' => [['first_name' => 'A1', 'coordinates' => ''], 'Enter the Google coordinates'],
            'bad coordinates' => [['first_name' => 'A2', 'coordinates' => 'near the church'], 'Could not read the coordinates'],
            'no mode of payment' => [['first_name' => 'A3', 'payment_mode' => ''], 'mode of payment'],
            'bad IP address' => [['first_name' => 'A4', 'ip_address' => '999.1.1.1'], 'IP address is not valid'],
            'port of another branch' => [['first_name' => 'A5', 'nap_port_id' => 5], 'Choose a NAP box and port of your branch'],
            'faulty port' => [['first_name' => 'A6', 'nap_port_id' => 4], 'faulty'],
        ];
        DB::table('nap_ports')->where('id', 4)->update(['status' => 'faulty']);
        foreach ($cases as $name => [$input, $message]) {
            $error = $this->post('/clients', $this->registerForm($input))->viewData('error');
            $this->assertStringContainsString($message, $error, $name);
            $this->assertSame(0, DB::table('clients')->where('full_name', $input['first_name'] . ' Lopez')->count(), $name);
        }
    }

    public function test_the_register_form_has_the_new_fields_and_the_nap_picker(): void
    {
        $this->signIn(2);
        $html = $this->get('/clients')->assertOk()->getContent();

        foreach (['name="payment_mode"', 'name="ip_address"', 'name="facebook_account"', 'name="coordinates"', 'name="nap_port_id"', 'id="registerNapPicker"', 'js/nap_picker.js'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }

        // The picker's script must load before the inline script that uses it, or the whole register form script stops.
        $this->assertLessThan(strpos($html, 'NapPicker.create'), strpos($html, 'js/nap_picker.js'));

        $this->signIn(1);
        $this->assertStringNotContainsString('registerNapPicker', $this->get('/clients')->getContent(), 'the super admin cannot register clients');
    }

    // ---- the employee files the report -------------------------------------------------------------

    public function test_the_app_gets_what_the_report_form_needs_and_the_items_it_took(): void
    {
        $this->takeRouters();
        // Not for an installation, someone else's, and a pending one: none of these is offered.
        DB::table('inventory_requests')->insert([
            ['employee_id' => 1, 'employee_name' => 'Eli', 'inventory_id' => 1, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'approved', 'request_type' => 'out', 'area_used' => 'Repair', 'created_at' => now()],
            ['employee_id' => 2, 'employee_name' => 'Fay', 'inventory_id' => 2, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'approved', 'request_type' => 'out', 'area_used' => 'Installation', 'created_at' => now()],
            ['employee_id' => 1, 'employee_name' => 'Eli', 'inventory_id' => 1, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'pending', 'request_type' => 'out', 'area_used' => 'Installation', 'created_at' => now()],
        ]);

        $options = $this->getJson('/backend/api/installation_options.php', $this->appHeaders(1))->assertOk()->json();

        $this->assertTrue($options['success']);
        $this->assertContains('Boctol', $options['barangays']);
        $this->assertNotContains('Agsoso', $options['barangays']);
        $this->assertSame(['Starter'], array_column($options['plans'], 'plan_name'));
        $this->assertSame(['cash', 'gcash'], array_column($options['payment_modes'], 'value'));
        $this->assertSame([10], array_column($options['taken'], 'id'));

        $this->assertSame(1, $this->getJson('/backend/api/me.php', $this->appHeaders(1))->json('awaiting_installation_reports'));
        $this->getJson('/backend/api/installation_options.php')->assertUnauthorized();
    }

    public function test_the_report_is_saved_and_reserves_the_port_but_adds_no_client_yet(): void
    {
        $this->takeRouters();
        $clients = DB::table('clients')->count();

        $this->file($this->report())->assertCreated()->assertJsonPath('success', true);

        $report = DB::table('client_installations')->first();
        $this->assertSame('submitted', $report->status);
        $this->assertSame('Balilihan', $report->branch_name);
        $this->assertSame(1, (int) $report->employee_id);
        $this->assertSame('Gina Mora', $report->client_name);
        $this->assertSame(1, (int) $report->nap_box_id);
        $this->assertSame(1, (int) $report->nap_port_id);
        $this->assertEqualsWithDelta(9.6541, (float) $report->latitude, 0.00001);
        $this->assertSame($clients, DB::table('clients')->count(), 'the client is only added when the admin confirms');
        $this->assertSame(1, (int) DB::table('inventory_requests')->where('id', 10)->value('installation_id'), 'the items taken belong to the report');
        $line = DB::table('client_installation_returns')->first();
        $this->assertSame([1, 'Router', 1, 'Good'], [(int) $line->inventory_id, $line->item_name, (int) $line->quantity, $line->item_condition]);
        $this->assertSame(7, (int) DB::table('inventory')->where('id', 1)->value('quantity'), 'stock does not change until the admin confirms');
        $this->assertSame('available', DB::table('nap_ports')->where('id', 1)->value('status'));

        // The port is reserved: it is no longer offered, counted as free, or given to another report or client.
        $box = $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(1))->json('data');
        $this->assertTrue($box['ports'][0]['reserved']);
        $this->assertSame(2, $box['available_ports']);
        $this->assertSame(1, $box['reserved_ports']);
        $this->file($this->report(['full_name' => 'Hana Cruz', 'request_ids' => [], 'returns' => []]))->assertStatus(422)
            ->assertJsonPath('message', 'NAP-01 port 1 is already used in an installation report that is waiting for the admin. Choose another port.');
        $this->signIn(2);
        $taken = $this->post('/clients', $this->registerForm(['nap_port_id' => 1]))->viewData('error');
        $this->assertStringContainsString('waiting for the admin', $taken);
        $connect = $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 1, 'do' => 'assign', 'client_label' => 'Ana Reyes — JFT-1'])->viewData('error');
        $this->assertStringContainsString('waiting for you', $connect);

        // The item that was taken is used up by the report: it is not offered again.
        $this->assertSame([], $this->getJson('/backend/api/installation_options.php', $this->appHeaders(1))->json('taken'));
        $this->assertSame(0, $this->getJson('/backend/api/me.php', $this->appHeaders(1))->json('awaiting_installation_reports'));
    }

    public function test_a_wrong_report_is_refused_and_saves_nothing(): void
    {
        $this->takeRouters();
        DB::table('inventory_requests')->insert(['id' => 11, 'employee_id' => 2, 'employee_name' => 'Fay', 'inventory_id' => 2, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'approved', 'request_type' => 'out', 'area_used' => 'Installation', 'created_at' => now()]);
        DB::table('nap_ports')->where('id', 4)->update(['status' => 'faulty']);

        $cases = [
            'no coordinates' => [['coordinates' => ''], 422],
            'coordinates not in the country' => [['coordinates' => '1.0, 1.0'], 422],
            'mobile number too short' => [['contact_number' => '0918'], 422],
            'unknown mode of payment' => [['payment_mode' => 'barter'], 422],
            'a plan that does not exist' => [['internet_plan_id' => 99], 422],
            'bad IP address' => [['ip_address' => 'abc'], 422],
            'port in use' => [['nap_port_id' => 2], 422],
            'faulty port' => [['nap_port_id' => 4], 422],
            'port of another branch' => [['nap_port_id' => 5], 422],
            'no port' => [['nap_port_id' => null], 422],
            'items of another employee' => [['request_ids' => [11]], 422],
            'item of another branch brought back' => [['returns' => [['inventory_id' => 2, 'quantity' => 1]]], 422],
            'a NAP box brought back' => [['returns' => [['inventory_id' => 50, 'quantity' => 1]]], 422],
            'nothing brought back is fine' => [['returns' => []], 201],
        ];
        foreach ($cases as $name => [$input, $status]) {
            $before = DB::table('client_installations')->count();
            $response = $this->file($input + $this->report());
            $response->assertStatus($status);
            if ($status === 422) {
                $this->assertSame($before, DB::table('client_installations')->count(), $name);
                $this->assertNull(DB::table('inventory_requests')->where('id', 10)->value('installation_id'), $name);
            }
        }
        $this->assertSame(1, DB::table('client_installations')->count());
        $this->assertSame(0, DB::table('client_installation_returns')->count());
    }

    public function test_an_employee_sees_only_their_own_reports_and_in_the_history(): void
    {
        $this->takeRouters();
        $this->file($this->report())->assertCreated();

        $mine = $this->getJson('/backend/api/installations.php', $this->appHeaders(1))->assertOk()->json('data');
        $this->assertCount(1, $mine);
        $this->assertSame('submitted', $mine[0]['status']);
        $this->assertSame('NAP-01', $mine[0]['nap_box_name']);
        $this->assertSame(1, $mine[0]['port_number']);
        $this->assertSame('Router', $mine[0]['taken'][0]['item_name']);
        $this->assertSame([], $this->getJson('/backend/api/installations.php', $this->appHeaders(2))->json('data'));

        $history = $this->getJson('/backend/api/transactions.php?type=installation', $this->appHeaders(1))->json('data');
        $this->assertSame('installation', $history[0]['kind']);
        $this->assertSame('Gina Mora', $history[0]['subtitle']);
        $this->assertSame('pending', $history[0]['status']);
    }

    // ---- the admin checks and confirms --------------------------------------------------------------

    private function confirmForm(int $id = 1, array $extra = []): array
    {
        return $extra + [
            'installation_id' => $id, 'decision' => 'confirm', 'full_name' => 'Gina Mora', 'contact_number' => '09181234567', 'email' => '',
            'barangay_name' => 'Boctol', 'exact_address' => 'Purok 3, Sitio Riverside', 'installation_date' => now()->toDateString(),
            'internet_plan_id' => 1, 'payment_mode' => 'cash', 'ip_address' => '10.0.0.8', 'facebook_account' => 'gina.mora',
            'coordinates' => self::WHERE, 'nap_port_id' => 1, 'accepted' => [1 => 1], 'admin_remarks' => '',
        ];
    }

    public function test_the_admin_sees_the_waiting_reports_with_a_number_in_the_sidebar(): void
    {
        $this->takeRouters();
        $this->file($this->report())->assertCreated();

        $this->signIn(2);
        $page = $this->get('/installations')->assertOk();
        $this->assertSame(['Gina Mora'], array_column($page->viewData('reports'), 'client_name'));
        $this->assertSame(1, $page->viewData('counts')['submitted']);
        $this->assertTrue($page->viewData('canDecide'));
        $html = $page->getContent();
        $this->assertStringContainsString('Review &amp; Confirm', $html);
        $this->assertStringContainsString('<span class="nav-badge"', $html);
        $this->assertStringContainsString('href="installations.php"', $html);

        $this->get('/installations?status=bogus')->assertNotFound();

        $this->signIn(3);
        $this->assertSame([], $this->get('/installations')->viewData('reports'), 'the Loon admin does not see Balilihan reports');

        $this->signIn(1);
        $super = $this->get('/installations')->assertOk();
        $this->assertSame(['Gina Mora'], array_column($super->viewData('reports'), 'client_name'));
        $this->assertFalse($super->viewData('canDecide'));
        $this->assertStringNotContainsString('Review &amp; Confirm', $super->getContent());
        $this->assertSame([], $this->get('/installations?branch=Loon')->viewData('reports'));
    }

    public function test_confirming_adds_the_client_connects_the_port_and_updates_the_inventory(): void
    {
        $this->takeRouters();
        $this->file($this->report())->assertCreated();

        $this->signIn(2);
        // The admin fixes the name and moves the client to port 3 before confirming; one of the two routers brought back is damaged.
        $page = $this->post('/installations', $this->confirmForm(1, ['full_name' => 'Gina Mora-Santos', 'nap_port_id' => 3, 'admin_remarks' => 'Name corrected.']))->assertOk();

        $this->assertSame('', $page->viewData('error'));
        $this->assertStringContainsString('Gina Mora-Santos', $page->viewData('success'));

        $client = DB::table('clients')->where('full_name', 'Gina Mora-Santos')->first();
        $this->assertNotNull($client);
        $this->assertSame('Balilihan', $client->branch_name);
        $this->assertSame('Boctol', $client->registered_barangay_name);
        $this->assertSame('cash', $client->payment_mode);
        $this->assertSame('10.0.0.8', $client->ip_address);
        $this->assertSame('gina.mora', $client->facebook_account);
        $this->assertNull($client->email);
        $this->assertSame(1, DB::table('bills')->where('client_id', $client->id)->count());

        $this->assertSame('occupied', DB::table('nap_ports')->where('id', 3)->value('status'));
        $this->assertSame((int) $client->id, (int) DB::table('nap_ports')->where('id', 3)->value('client_id'));
        $this->assertSame('available', DB::table('nap_ports')->where('id', 1)->value('status'), 'the port the technician reported was changed by the admin');

        $report = DB::table('client_installations')->first();
        $this->assertSame('confirmed', $report->status);
        $this->assertSame((int) $client->id, (int) $report->client_id);
        $this->assertSame(2, (int) $report->reviewed_by);
        $this->assertSame('Gina Mora-Santos', $report->client_name);
        $this->assertSame(3, (int) $report->nap_port_id);
        $this->assertSame('Name corrected.', $report->admin_remarks);

        // The router brought back is in stock again (7 + 1) and the stock history says who, which client and which NAP box.
        $this->assertSame(8, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
        $back = DB::table('inventory_stock_movements')->where('movement_type', 'stock_returned')->first();
        $this->assertSame([1, 8, 1, (int) $client->id, 1], [(int) $back->quantity_change, (int) $back->balance_after, (int) $back->employee_id, (int) $back->client_id, (int) $back->nap_box_id]);
        // The other two were used on this job: recorded against the client and box, without changing the stock again.
        $used = DB::table('inventory_stock_movements')->where('movement_type', 'job_used')->first();
        $this->assertSame([0, 8, (int) $client->id, 1], [(int) $used->quantity_change, (int) $used->balance_after, (int) $used->client_id, (int) $used->nap_box_id]);
        $this->assertStringContainsString('2 used', $used->remarks);
        $this->assertSame(1, (int) DB::table('client_installation_returns')->value('accepted_quantity'));

        // It cannot be confirmed twice.
        $twice = $this->post('/installations', $this->confirmForm(1))->viewData('error');
        $this->assertSame('This report was already confirmed.', $twice);
        $this->assertSame(1, DB::table('clients')->where('full_name', 'like', 'Gina%')->count());
        $this->assertSame(8, (int) DB::table('inventory')->where('id', 1)->value('quantity'));

        // The NAP box page lists the stock used on it, and the history of the employee shows the confirmation.
        $this->assertCount(2, NapBoxes::box(1)['inventory_used']);
        $this->assertSame('approved', $this->getJson('/backend/api/transactions.php?type=installation', $this->appHeaders(1))->json('data.0.status'));
        $this->assertSame('Confirmed', $this->getJson('/backend/api/installations.php', $this->appHeaders(1))->json('data.0.status_label'));
    }

    public function test_the_admin_can_put_back_less_than_the_technician_reported(): void
    {
        $this->takeRouters();
        $this->file($this->report(['returns' => [['inventory_id' => 1, 'quantity' => 2, 'item_condition' => 'Damaged']]]))->assertCreated();

        $this->signIn(2);
        $this->post('/installations', $this->confirmForm(1, ['accepted' => [1 => 0]]))->assertOk();

        $this->assertSame(7, (int) DB::table('inventory')->where('id', 1)->value('quantity'), 'nothing accepted, nothing back in stock');
        $this->assertSame(0, DB::table('inventory_stock_movements')->where('movement_type', 'stock_returned')->count());
        $this->assertSame(0, (int) DB::table('client_installation_returns')->value('accepted_quantity'));
        $this->assertStringContainsString('3 used', DB::table('inventory_stock_movements')->where('movement_type', 'job_used')->value('remarks'));

        // More than reported is capped at what was brought.
        DB::table('inventory_requests')->insert(['id' => 12, 'employee_id' => 1, 'employee_name' => 'Eli', 'inventory_id' => 1, 'item_name' => 'Router', 'quantity' => 1, 'status' => 'approved', 'request_type' => 'out', 'area_used' => 'Installation', 'created_at' => now()]);
        $this->file($this->report(['full_name' => 'Hana Cruz', 'nap_port_id' => 3, 'request_ids' => [12], 'returns' => [['inventory_id' => 1, 'quantity' => 1]]]))->assertCreated();
        $this->post('/installations', $this->confirmForm(2, ['full_name' => 'Hana Cruz', 'nap_port_id' => 3, 'accepted' => [2 => 50]]))->assertOk();
        $this->assertSame(8, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    public function test_a_report_that_is_wrong_is_not_confirmed_and_nothing_changes(): void
    {
        $this->takeRouters();
        $this->file($this->report())->assertCreated();
        $this->signIn(2);

        $cases = [
            'no coordinates' => [['coordinates' => ''], 'Enter the Google coordinates'],
            'short mobile number' => [['contact_number' => '123'], '11 digits'],
            'no port' => [['nap_port_id' => ''], 'Choose the NAP box and the port'],
            'port in use' => [['nap_port_id' => 2], 'already connected to a client'],
            'port of another branch' => [['nap_port_id' => 5], 'of your branch'],
            'no plan' => [['internet_plan_id' => ''], 'active internet plan'],
        ];
        foreach ($cases as $name => [$input, $message]) {
            $page = $this->post('/installations', $this->confirmForm(1, $input))->assertOk();
            $this->assertStringContainsString($message, $page->viewData('error'), $name);
            $this->assertSame(1, $page->viewData('openReport'), $name);
        }

        $this->assertSame('submitted', DB::table('client_installations')->value('status'));
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Gina Mora')->count());
        $this->assertSame(7, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
        $this->assertSame(0, DB::table('inventory_stock_movements')->whereIn('movement_type', ['stock_returned', 'job_used'])->count());
        $this->assertSame('available', DB::table('nap_ports')->where('id', 1)->value('status'));
        $this->assertSame(1, (int) DB::table('inventory_requests')->where('id', 10)->value('installation_id'));
    }

    public function test_a_report_can_be_rejected_with_a_reason_and_the_employee_can_report_again(): void
    {
        $this->takeRouters();
        $this->file($this->report())->assertCreated();
        $this->signIn(2);

        $noReason = $this->post('/installations', ['installation_id' => 1, 'decision' => 'reject', 'admin_remarks' => ' '])->viewData('error');
        $this->assertStringContainsString('Write why', $noReason);
        $this->assertSame('submitted', DB::table('client_installations')->value('status'));

        $page = $this->post('/installations', ['installation_id' => 1, 'decision' => 'reject', 'admin_remarks' => 'Wrong port, please check again.'])->assertOk();
        $this->assertStringContainsString('rejected', $page->viewData('success'));
        $this->assertSame('rejected', DB::table('client_installations')->value('status'));
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Gina Mora')->count());
        $this->assertSame(7, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
        $this->assertNull(DB::table('inventory_requests')->where('id', 10)->value('installation_id'));

        // The port is free again and the items can be reported again.
        $this->assertSame(1, $this->getJson('/backend/api/me.php', $this->appHeaders(1))->json('awaiting_installation_reports'));
        $this->assertSame(3, $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(1))->json('data.available_ports'));
        $mine = $this->getJson('/backend/api/installations.php', $this->appHeaders(1))->json('data.0');
        $this->assertSame('rejected', $mine['status']);
        $this->assertSame('Wrong port, please check again.', $mine['admin_remarks']);
        $this->file($this->report(['nap_port_id' => 3]))->assertCreated();
    }

    public function test_only_the_branch_admin_of_the_report_decides(): void
    {
        $this->takeRouters();
        $this->file($this->report())->assertCreated();

        $this->signIn(3);
        $this->post('/installations', $this->confirmForm(1))->assertNotFound();
        $this->post('/installations', ['installation_id' => 1, 'decision' => 'reject', 'admin_remarks' => 'No'])->assertNotFound();

        $this->signIn(1);
        $super = $this->post('/installations', $this->confirmForm(1))->assertOk();
        $this->assertSame('Only a branch admin confirms the installation reports of their branch.', $super->viewData('error'));

        $this->assertSame('submitted', DB::table('client_installations')->value('status'));
        $this->assertSame(0, DB::table('clients')->where('full_name', 'Gina Mora')->count());
        $this->assertSame(7, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    // ---- the stock requests page and the client record ----------------------------------------------

    public function test_the_stock_requests_page_says_where_the_installation_of_an_approved_request_is(): void
    {
        $this->takeRouters();
        $this->signIn(2);

        $find = fn (array $requests) => collect($requests)->firstWhere('id', 10);
        $this->assertNull($find($this->get('/inventory-requests')->viewData('requests'))['installation_status']);
        $this->assertStringContainsString('Installation pending: no report yet', $this->get('/inventory-requests')->getContent());

        $this->file($this->report())->assertCreated();
        $this->assertSame('submitted', $find($this->get('/inventory-requests')->viewData('requests'))['installation_status']);
        $this->assertStringContainsString('Installation report waiting', $this->get('/inventory-requests')->getContent());

        $this->post('/installations', $this->confirmForm(1))->assertOk();
        $this->assertSame('confirmed', $find($this->get('/inventory-requests')->viewData('requests'))['installation_status']);
    }

    public function test_the_client_record_shows_the_nap_port_and_the_installation_details(): void
    {
        $this->takeRouters();
        $this->file($this->report())->assertCreated();
        $this->signIn(2);
        $this->post('/installations', $this->confirmForm(1))->assertOk();
        $id = (int) DB::table('clients')->where('full_name', 'Gina Mora')->value('id');

        $html = $this->get('/clients')->assertOk()->getContent();
        $this->assertStringContainsString('NAP-01 \u00b7 Port 1', $html, 'the client list hands the NAP port to the details window');
        $this->assertStringContainsString('gina.mora', $html);
        $this->assertStringContainsString('9.654100, 124.123400', $html);

        $edit = $this->get("/client-update?id={$id}")->assertOk()->getContent();
        $this->assertStringContainsString('NAP-01 · Port 1', $edit);
        $this->assertStringContainsString('name="coordinates"', $edit);
        $this->assertStringContainsString('value="gina.mora"', $edit);
    }
}
