<?php

namespace Tests\Feature;

use App\Support\NapBoxes;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The NAP Boxes page. A branch admin keeps their own branch's boxes; the super admin only looks (and can pick a branch).
 *
 * Seed: NAP-01 (box 1, Boctol, Balilihan, ports 1-4) and NAP-02 (box 2, Agsoso, Loon, ports 5-6); port 2 is
 * occupied by client 1. Users: 1 super admin, 2 Balilihan admin, 3 Loon admin. Employees: 1 (Balilihan), 2 (Loon).
 */
class NapBoxPagesTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
        DB::table('internet_plans')->insert(['id' => 1, 'plan_name' => 'Starter', 'monthly_price' => 999, 'installation_fee' => 0, 'status' => 'active', 'archived' => false]);

        // The branch's NAP Box stock: a new box is taken from it.
        DB::table('inventory')->insert([
            ['id' => 50, 'item_name' => 'NAP Box 8-port', 'sku' => 'SKU-50', 'category' => 'NAP Box', 'quantity' => 5, 'unit_price' => 900, 'reorder_level' => 1, 'branch_name' => 'Balilihan'],
            ['id' => 51, 'item_name' => 'NAP Box 8-port', 'sku' => 'SKU-51', 'category' => 'NAP Box', 'quantity' => 3, 'unit_price' => 900, 'reorder_level' => 1, 'branch_name' => 'Loon'],
        ]);

        NapBoxes::create('Balilihan', 'NAP-01', 'Boctol', 'Purok 2', 4);
        NapBoxes::create('Loon', 'NAP-02', 'Agsoso', null, 2);
        DB::table('nap_ports')->where('id', 2)->update(['status' => 'occupied', 'client_id' => 1]);
    }

    private function names(iterable $rows, string $key = 'name'): array
    {
        $names = [];
        foreach ($rows as $row) {
            $names[] = is_array($row) ? $row[$key] : $row->$key;
        }
        sort($names);

        return $names;
    }

    // ---- the NAP Boxes page -----------------------------------------------------------------------

    public function test_a_branch_admin_sees_their_own_branchs_boxes_and_the_super_admin_every_branch(): void
    {
        $this->signIn(2);
        $page = $this->get('/nap-boxes')->assertOk();
        $this->assertSame(['NAP-01'], $this->names($page->viewData('boxes')));
        $this->assertSame(4, $page->viewData('stats')['ports']);
        $this->assertSame(1, $page->viewData('stats')['occupied']);
        $this->assertTrue($page->viewData('canManage'));

        $this->signIn(1);
        $all = $this->get('/nap-boxes')->assertOk();
        $this->assertSame(['NAP-01', 'NAP-02'], $this->names($all->viewData('boxes')));
        $this->assertFalse($all->viewData('canManage'));
        $this->assertSame(['NAP-02'], $this->names($this->get('/nap-boxes?branch=Loon')->viewData('boxes')));
    }

    public function test_the_nap_boxes_are_searched_in_one_box_by_branch_barangay_or_name(): void
    {
        $this->signIn(1);

        $find = fn (string $search) => $this->names($this->get('/nap-boxes?search=' . urlencode($search))->assertOk()->viewData('boxes'));
        $this->assertSame(['NAP-02'], $find('Loon'), 'a branch');
        $this->assertSame(['NAP-01'], $find('boctol'), 'a barangay');
        $this->assertSame(['NAP-01'], $find('Balilihan NAP'), 'a branch and a name');
        $this->assertSame(['NAP-01'], $find('Purok'), 'where it hangs');
        $this->assertSame([], $find('Loon Boctol'), 'words narrow each other');
        $this->assertSame(['NAP-01', 'NAP-02'], $find(''));

        $html = $this->get('/nap-boxes')->getContent();
        $this->assertStringContainsString('placeholder="Branch, Barangay, NAP Boxes"', $html);
        $this->assertStringNotContainsString('id="filterBarangay"', $html);

        $this->signIn(2);
        $this->assertStringContainsString('placeholder="Barangay, NAP Boxes"', $this->get('/nap-boxes')->getContent());
        $this->assertSame([], $this->names($this->get('/nap-boxes?search=Loon')->viewData('boxes')), 'another branch finds nothing');
    }

    public function test_nap_boxes_are_counted_in_the_inventory_of_their_branch(): void
    {
        $this->signIn(2);
        $page = $this->get('/inventory')->assertOk();
        $this->assertSame(1, $page->viewData('napBoxCount'));
        $this->assertSame(3, $page->viewData('napPortsAvailable'), 'NAP-01 has 4 ports and one is occupied');

        $this->signIn(1);
        $this->assertSame(2, $this->get('/inventory')->viewData('napBoxCount'));
        $this->assertSame(1, $this->get('/inventory?branch=Loon')->viewData('napBoxCount'));
    }

    public function test_nap_boxes_sit_under_inventory_in_the_sidebar(): void
    {
        $this->signIn(2);
        $nav = collect($this->get('/inventory')->viewData('navItems'));

        $this->assertNull($nav->firstWhere('id', 'nap_boxes'), 'no separate NAP Boxes entry any more');
        $this->assertContains('nap_boxes.php', array_column($nav->firstWhere('id', 'inventory')['submenu'], 'url'));
    }

    public function test_a_branch_admin_cannot_look_at_another_branchs_ports(): void
    {
        $this->signIn(2);

        $this->assertNull($this->get('/nap-boxes?box=2&branch=Loon')->assertOk()->viewData('selectedBox'));
        $this->assertNotNull($this->get('/nap-boxes?box=1')->viewData('selectedBox'));
    }

    public function test_a_branch_admin_adds_a_box_with_its_numbered_ports(): void
    {
        $this->signIn(2);
        $page = $this->post('/nap-boxes', ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234', 'name' => 'NAP-03', 'barangay' => 'boctol', 'location' => 'Near the school', 'total_ports' => 8])->assertOk();

        $this->assertSame('NAP-03 was added with 8 ports. One NAP Box was taken from your inventory.', $page->viewData('success'));
        $box = DB::table('nap_boxes')->where('name', 'NAP-03')->first();
        $this->assertSame('Balilihan', $box->branch_name);
        $this->assertSame('Boctol', $box->barangay_name, 'the barangay is spelled the way the branch has it');
        $this->assertSame(range(1, 8), DB::table('nap_ports')->where('nap_box_id', $box->id)->orderBy('port_number')->pluck('port_number')->map(fn ($n) => (int) $n)->all());
    }

    // ---- NAP boxes and the inventory work as one -------------------------------------------------------

    private function newBox(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/nap-boxes', ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234', 'name' => 'NAP-03', 'barangay' => 'Boctol', 'location' => 'Near the school', 'total_ports' => 8] + $extra);
    }

    public function test_installing_a_nap_box_takes_one_from_the_branchs_stock_and_says_so_in_the_history(): void
    {
        $this->signIn(2);
        $this->newBox()->assertOk();

        $box = DB::table('nap_boxes')->where('name', 'NAP-03')->first();
        $this->assertSame(4, (int) DB::table('inventory')->where('id', 50)->value('quantity'));
        $this->assertSame(3, (int) DB::table('inventory')->where('id', 51)->value('quantity'), 'another branch keeps its stock');

        $movement = DB::table('inventory_stock_movements')->where('movement_type', 'nap_installed')->first();
        $this->assertSame(50, (int) $movement->inventory_id);
        $this->assertSame(-1, (int) $movement->quantity_change);
        $this->assertSame(4, (int) $movement->balance_after);
        $this->assertSame((int) $box->id, (int) $movement->nap_box_id, 'the NAP box is on the transaction');
        $this->assertSame(2, (int) $movement->performed_by);

        // The box shows the transaction under "Inventory used".
        $used = $this->get('/nap-boxes?box=' . $box->id)->viewData('selectedBox')['inventory_used'];
        $this->assertSame(['NAP Box Installed'], array_column($used, 'type_label'));
        $this->assertSame(['NAP Box 8-port'], array_column($used, 'item_name'));
        $this->assertStringContainsString('Inventory used on NAP-03', $this->get('/nap-boxes?box=' . $box->id)->getContent());
    }

    public function test_a_nap_box_cannot_be_installed_without_a_unit_in_stock(): void
    {
        $this->signIn(2);
        DB::table('inventory')->where('id', 50)->update(['quantity' => 0]);

        $error = $this->newBox()->viewData('error');

        $this->assertStringContainsString('no NAP Box 8-port left in stock', $error);
        $this->assertSame(0, DB::table('nap_boxes')->where('name', 'NAP-03')->count());
        $this->assertSame(0, DB::table('inventory_stock_movements')->where('movement_type', 'nap_installed')->count());

        DB::table('inventory')->where('id', 50)->delete();
        $this->assertStringContainsString('There is no NAP Box in your stock', $this->newBox()->viewData('error'));
        $this->assertSame(0, DB::table('nap_boxes')->where('name', 'NAP-03')->count());
    }

    public function test_only_a_nap_box_item_of_the_own_branch_can_be_installed_from(): void
    {
        $this->signIn(2);

        $this->assertStringContainsString('Choose a NAP Box from the stock of your branch', $this->newBox(['stock_item_id' => 51])->viewData('error'), 'another branch');
        $this->assertStringContainsString('Choose a NAP Box from the stock of your branch', $this->newBox(['stock_item_id' => 1])->viewData('error'), 'a Router is not a NAP box');
        $this->assertSame(5, (int) DB::table('inventory')->where('id', 50)->value('quantity'));
        $this->assertSame(0, DB::table('nap_boxes')->where('name', 'NAP-03')->count());
    }

    public function test_with_two_nap_box_items_the_admin_chooses_which_one(): void
    {
        $this->signIn(2);
        DB::table('inventory')->insert(['id' => 52, 'item_name' => 'NAP Box 16-port', 'sku' => 'SKU-52', 'category' => 'nap box', 'quantity' => 2, 'unit_price' => 1500, 'reorder_level' => 1, 'branch_name' => 'Balilihan']);

        $this->assertStringContainsString('Choose which NAP Box', $this->newBox()->viewData('error'));
        $this->newBox(['stock_item_id' => 52, 'total_ports' => 16])->assertOk();

        $this->assertSame(1, (int) DB::table('inventory')->where('id', 52)->value('quantity'));
        $this->assertSame(5, (int) DB::table('inventory')->where('id', 50)->value('quantity'));
    }

    public function test_changing_or_archiving_a_box_does_not_touch_the_stock(): void
    {
        $this->signIn(2);
        $this->post('/nap-boxes', ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234', 'box_id' => 1, 'name' => 'NAP-01', 'barangay' => 'Boctol', 'total_ports' => 6, 'status' => 'active'])->assertOk();
        $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 2, 'do' => 'free'])->assertOk();
        $this->post('/nap-boxes', ['action' => 'archive_box', 'box_id' => 1])->assertOk();

        $this->assertSame(5, (int) DB::table('inventory')->where('id', 50)->value('quantity'));
        $this->assertSame(0, DB::table('inventory_stock_movements')->where('movement_type', 'nap_installed')->count());
    }

    public function test_the_inventory_counts_the_nap_boxes_installed_and_in_stock(): void
    {
        $this->signIn(2);
        $page = $this->get('/inventory')->assertOk();
        $this->assertSame(1, $page->viewData('napBoxCount'));
        $this->assertSame(5, $page->viewData('napInStock'));
        $this->assertContains('NAP Box', $page->viewData('modalCategories'), 'the category can be picked before any NAP Box item exists');

        $this->signIn(1);
        $this->assertSame(8, $this->get('/inventory')->viewData('napInStock'), 'the super admin sees every branch');
        $this->assertSame(3, $this->get('/inventory?branch=Loon')->viewData('napInStock'));
        $this->assertSame(3, $this->get('/nap-boxes?branch=Loon')->viewData('napInStock'));
    }

    public function test_a_box_needs_a_unique_name_a_branch_barangay_and_a_sensible_port_count(): void
    {
        $this->signIn(2);
        $cases = [
            'duplicate name' => [['name' => 'nap-01', 'barangay' => 'Boctol', 'total_ports' => 4], 'already exists'],
            'barangay of another branch' => [['name' => 'NAP-09', 'barangay' => 'Agsoso', 'total_ports' => 4], 'Choose a barangay of Balilihan'],
            'no barangay' => [['name' => 'NAP-09', 'barangay' => '', 'total_ports' => 4], 'Choose a barangay'],
            'no name' => [['name' => '', 'barangay' => 'Boctol', 'total_ports' => 4], 'Enter the NAP box name'],
            'no ports' => [['name' => 'NAP-09', 'barangay' => 'Boctol', 'total_ports' => 0], '1 to 96 ports'],
            'too many ports' => [['name' => 'NAP-09', 'barangay' => 'Boctol', 'total_ports' => 97], '1 to 96 ports'],
            'no coordinates' => [['name' => 'NAP-09', 'barangay' => 'Boctol', 'total_ports' => 4, 'coordinates' => ''], 'Enter the Google coordinates'],
            'coordinates outside the country' => [['name' => 'NAP-09', 'barangay' => 'Boctol', 'total_ports' => 4, 'coordinates' => '40.7128, -74.0060'], 'outside the Philippines'],
            'coordinates swapped' => [['name' => 'NAP-09', 'barangay' => 'Boctol', 'total_ports' => 4, 'coordinates' => '124.1234, 9.6541'], 'outside the Philippines'],
        ];
        foreach ($cases as $name => [$input, $message]) {
            $error = $this->post('/nap-boxes', $input + ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234'])->viewData('error');
            $this->assertStringContainsString($message, $error, $name);
        }
        $this->assertSame(1, DB::table('nap_boxes')->where('branch_name', 'Balilihan')->count());

        // The same name is fine in another branch.
        $this->signIn(3);
        $this->post('/nap-boxes', ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234', 'name' => 'NAP-01', 'barangay' => 'Agsoso', 'total_ports' => 4])->assertOk();
        $this->assertSame(2, DB::table('nap_boxes')->where('name', 'NAP-01')->count());
    }

    public function test_the_super_admin_cannot_add_or_change_a_box(): void
    {
        $this->signIn(1);
        $page = $this->post('/nap-boxes', ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234', 'name' => 'NAP-09', 'barangay' => 'Boctol', 'total_ports' => 4])->assertOk();

        $this->assertSame('Only a branch admin keeps the NAP boxes of their branch.', $page->viewData('error'));
        $this->assertSame(2, DB::table('nap_boxes')->count());
    }

    public function test_a_box_can_grow_and_shrink_but_never_over_a_port_that_is_in_use(): void
    {
        $this->signIn(2);
        $edit = ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234', 'box_id' => 1, 'name' => 'NAP-01', 'barangay' => 'Boctol', 'status' => 'active'];

        $this->post('/nap-boxes', $edit + ['total_ports' => 6])->assertOk();
        $this->assertSame(6, DB::table('nap_ports')->where('nap_box_id', 1)->count());
        $this->assertSame(6, (int) DB::table('nap_boxes')->where('id', 1)->value('total_ports'));

        // Port 2 is connected to a client: shrinking to 1 port would remove it.
        $blocked = $this->post('/nap-boxes', $edit + ['total_ports' => 1])->viewData('error');
        $this->assertStringContainsString('Port 2 is in use', $blocked);
        $this->assertSame(6, DB::table('nap_ports')->where('nap_box_id', 1)->count());

        $this->post('/nap-boxes', $edit + ['total_ports' => 3])->assertOk();
        $this->assertSame(3, DB::table('nap_ports')->where('nap_box_id', 1)->count());
    }

    public function test_a_box_in_use_cannot_be_archived_but_an_empty_one_can_be_archived_and_restored(): void
    {
        $this->signIn(2);
        $busy = $this->post('/nap-boxes', ['action' => 'archive_box', 'box_id' => 1])->viewData('error');
        $this->assertStringContainsString('still has connected ports', $busy);

        DB::table('nap_ports')->where('id', 2)->update(['status' => 'available', 'client_id' => null]);
        $this->post('/nap-boxes', ['action' => 'archive_box', 'box_id' => 1])->assertOk();
        $this->assertSame(1, (int) DB::table('nap_boxes')->where('id', 1)->value('archived'));
        $this->assertSame([], $this->get('/nap-boxes')->viewData('boxes'));
        $this->assertSame(['NAP-01'], $this->names($this->get('/nap-boxes?view=archived')->viewData('boxes')));

        $this->post('/nap-boxes', ['action' => 'restore_box', 'box_id' => 1])->assertOk();
        $this->assertSame(0, (int) DB::table('nap_boxes')->where('id', 1)->value('archived'));
    }

    public function test_ports_can_be_marked_faulty_freed_and_connected_to_a_client(): void
    {
        $this->signIn(2);

        $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 3, 'do' => 'faulty'])->assertOk();
        $this->assertSame('faulty', DB::table('nap_ports')->where('id', 3)->value('status'));
        $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 3, 'do' => 'available'])->assertOk();
        $this->assertSame('available', DB::table('nap_ports')->where('id', 3)->value('status'));

        // Connect an existing client who is not on a port yet.
        DB::table('clients')->insert(['id' => 4, 'client_code' => 'JFT-4', 'full_name' => 'Dina Cruz', 'branch_name' => 'Balilihan', 'status' => 'active', 'archived' => false]);
        $page = $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 3, 'do' => 'assign', 'client_label' => 'Dina Cruz — JFT-4'])->assertOk();
        $this->assertSame('Dina Cruz is now connected to NAP-01 port 3.', $page->viewData('success'));
        $this->assertSame(4, (int) DB::table('nap_ports')->where('id', 3)->value('client_id'));

        $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 3, 'do' => 'free'])->assertOk();
        $this->assertSame('available', DB::table('nap_ports')->where('id', 3)->value('status'));
        $this->assertNull(DB::table('nap_ports')->where('id', 3)->value('client_id'));
    }

    public function test_connecting_a_client_checks_who_and_where(): void
    {
        $this->signIn(2);
        DB::table('clients')->insert(['id' => 4, 'client_code' => 'JFT-4', 'full_name' => 'Dina Cruz', 'branch_name' => 'Balilihan', 'status' => 'active', 'archived' => false]);

        $this->assertStringContainsString('already connected', $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 3, 'do' => 'assign', 'client_label' => 'Ana Reyes — JFT-1'])->viewData('error'));
        $this->assertStringContainsString('Choose a client from the suggestions', $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 3, 'do' => 'assign', 'client_label' => 'Ben Cruz — JFT-2'])->viewData('error'), 'a client of another branch');
        $this->assertStringContainsString('not available', $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 2, 'do' => 'assign', 'client_label' => 'Dina Cruz — JFT-4'])->viewData('error'), 'a port that is taken');
        $this->assertSame('available', DB::table('nap_ports')->where('id', 3)->value('status'));
    }

    public function test_a_branch_admin_cannot_change_another_branchs_box_or_port(): void
    {
        $this->signIn(2);

        $this->post('/nap-boxes', ['action' => 'save_box', 'coordinates' => '9.6541, 124.1234', 'box_id' => 2, 'name' => 'Hacked', 'barangay' => 'Boctol', 'total_ports' => 2])->assertNotFound();
        $this->post('/nap-boxes', ['action' => 'archive_box', 'box_id' => 2])->assertNotFound();
        $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 5, 'do' => 'faulty'])->assertNotFound();
        $this->assertSame('NAP-02', DB::table('nap_boxes')->where('id', 2)->value('name'));
        $this->assertSame('available', DB::table('nap_ports')->where('id', 5)->value('status'));
    }

    public function test_the_sidebar_links_to_the_nap_boxes_and_no_longer_to_work_requests(): void
    {
        foreach ([1, 2] as $userId) {
            $this->signIn($userId);
            $html = $this->get('/dashboard')->assertOk()->getContent();
            $this->assertStringContainsString('nap_boxes.php', $html);
            $this->assertStringNotContainsString('work_requests.php', $html);
        }
    }

    public function test_the_work_requests_page_and_its_api_are_gone(): void
    {
        $this->signIn(2);
        $this->get('/work-requests')->assertNotFound();
        $this->get('/pages/work_requests.php')->assertNotFound();
        $this->getJson('/backend/api/work_requests.php')->assertNotFound();
    }
}
