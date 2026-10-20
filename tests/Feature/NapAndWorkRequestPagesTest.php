<?php

namespace Tests\Feature;

use App\Support\NapBoxes;
use App\Support\WorkRequestService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The admin side of the field work: the NAP Boxes page and the Work Requests page.
 * A branch admin keeps and decides their own branch; the super admin only looks (and can pick a branch).
 *
 * Seed: NAP-01 (box 1, Boctol, Balilihan, ports 1-4) and NAP-02 (box 2, Agsoso, Loon, ports 5-6); port 2 is
 * occupied by client 1. Users: 1 super admin, 2 Balilihan admin, 3 Loon admin. Employees: 1 (Balilihan), 2 (Loon).
 */
class NapAndWorkRequestPagesTest extends TestCase
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
        $page = $this->post('/nap-boxes', ['action' => 'save_box', 'name' => 'NAP-03', 'barangay' => 'boctol', 'location' => 'Near the school', 'total_ports' => 8])->assertOk();

        $this->assertSame('NAP-03 was added with 8 ports. One NAP Box was taken from your inventory.', $page->viewData('success'));
        $box = DB::table('nap_boxes')->where('name', 'NAP-03')->first();
        $this->assertSame('Balilihan', $box->branch_name);
        $this->assertSame('Boctol', $box->barangay_name, 'the barangay is spelled the way the branch has it');
        $this->assertSame(range(1, 8), DB::table('nap_ports')->where('nap_box_id', $box->id)->orderBy('port_number')->pluck('port_number')->map(fn ($n) => (int) $n)->all());
    }

    // ---- NAP boxes and the inventory work as one -------------------------------------------------------

    private function newBox(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/nap-boxes', ['action' => 'save_box', 'name' => 'NAP-03', 'barangay' => 'Boctol', 'location' => 'Near the school', 'total_ports' => 8] + $extra);
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
        $this->post('/nap-boxes', ['action' => 'save_box', 'box_id' => 1, 'name' => 'NAP-01', 'barangay' => 'Boctol', 'total_ports' => 6, 'status' => 'active'])->assertOk();
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
        ];
        foreach ($cases as $name => [$input, $message]) {
            $error = $this->post('/nap-boxes', ['action' => 'save_box'] + $input)->viewData('error');
            $this->assertStringContainsString($message, $error, $name);
        }
        $this->assertSame(1, DB::table('nap_boxes')->where('branch_name', 'Balilihan')->count());

        // The same name is fine in another branch.
        $this->signIn(3);
        $this->post('/nap-boxes', ['action' => 'save_box', 'name' => 'NAP-01', 'barangay' => 'Agsoso', 'total_ports' => 4])->assertOk();
        $this->assertSame(2, DB::table('nap_boxes')->where('name', 'NAP-01')->count());
    }

    public function test_the_super_admin_cannot_add_or_change_a_box(): void
    {
        $this->signIn(1);
        $page = $this->post('/nap-boxes', ['action' => 'save_box', 'name' => 'NAP-09', 'barangay' => 'Boctol', 'total_ports' => 4])->assertOk();

        $this->assertSame('Only a branch admin keeps the NAP boxes of their branch.', $page->viewData('error'));
        $this->assertSame(2, DB::table('nap_boxes')->count());
    }

    public function test_a_box_can_grow_and_shrink_but_never_over_a_port_that_is_in_use(): void
    {
        $this->signIn(2);
        $edit = ['action' => 'save_box', 'box_id' => 1, 'name' => 'NAP-01', 'barangay' => 'Boctol', 'status' => 'active'];

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

    public function test_a_reserved_port_cannot_be_removed_or_marked_faulty(): void
    {
        $employee = DB::table('employees')->where('id', 1)->first();
        WorkRequestService::create($employee, ['work_type' => 'installation', 'new_client' => ['name' => 'Nina Reyes', 'phone' => '09171234567', 'address' => 'Purok 3'], 'barangay' => 'Boctol', 'nap_box_id' => 1, 'nap_port_id' => 4]);

        $this->signIn(2);
        $shrink = $this->post('/nap-boxes', ['action' => 'save_box', 'box_id' => 1, 'name' => 'NAP-01', 'barangay' => 'Boctol', 'total_ports' => 3, 'status' => 'active'])->viewData('error');
        $this->assertStringContainsString('Port 4 is in use', $shrink);

        $faulty = $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 4, 'do' => 'faulty'])->viewData('error');
        $this->assertStringContainsString('cannot be marked faulty', $faulty);
        $this->assertSame('available', DB::table('nap_ports')->where('id', 4)->value('status'));
    }

    public function test_a_box_in_use_cannot_be_archived_but_an_empty_one_can_be_archived_and_restored(): void
    {
        $this->signIn(2);
        $busy = $this->post('/nap-boxes', ['action' => 'archive_box', 'box_id' => 1])->viewData('error');
        $this->assertStringContainsString('still has connected or reserved ports', $busy);

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

        $this->post('/nap-boxes', ['action' => 'save_box', 'box_id' => 2, 'name' => 'Hacked', 'barangay' => 'Boctol', 'total_ports' => 2])->assertNotFound();
        $this->post('/nap-boxes', ['action' => 'archive_box', 'box_id' => 2])->assertNotFound();
        $this->post('/nap-boxes', ['action' => 'port', 'port_id' => 5, 'do' => 'faulty'])->assertNotFound();
        $this->assertSame('NAP-02', DB::table('nap_boxes')->where('id', 2)->value('name'));
        $this->assertSame('available', DB::table('nap_ports')->where('id', 5)->value('status'));
    }

    // ---- the Work Requests page -------------------------------------------------------------------

    private function submit(int $employeeId, array $extra = []): int
    {
        $employee = DB::table('employees')->where('id', $employeeId)->first();

        return WorkRequestService::create($employee, $extra + [
            'work_type' => 'installation',
            'new_client' => ['name' => 'Nina Reyes', 'phone' => '09171234567', 'address' => 'Purok 3'],
            'barangay' => $employee->branch_name === 'Loon' ? 'Agsoso' : 'Boctol',
            'nap_box_id' => $employee->branch_name === 'Loon' ? 2 : 1,
            'nap_port_id' => $employee->branch_name === 'Loon' ? 5 : 1,
        ]);
    }

    public function test_a_branch_admin_sees_their_branchs_requests_and_the_super_admin_all_of_them(): void
    {
        $this->submit(1);
        $this->submit(2);

        $this->signIn(2);
        $page = $this->get('/work-requests')->assertOk();
        $this->assertCount(1, $page->viewData('requests'));
        $this->assertSame('Balilihan', $page->viewData('requests')[0]['branch_name']);
        $this->assertSame(1, $page->viewData('tabCounts')['pending']);
        $this->assertSame(1, $page->viewData('tabCounts')['all']);
        $page->assertSee('Pending Approval');

        $this->signIn(1);
        $this->assertCount(2, $this->get('/work-requests')->viewData('requests'));
        $this->assertCount(1, $this->get('/work-requests?branch=Loon')->viewData('requests'));
        $this->assertFalse($this->get('/work-requests')->viewData('canReview'));
    }

    public function test_the_tabs_filter_by_status(): void
    {
        $first = $this->submit(1);
        $second = $this->submit(1, ['nap_port_id' => 3]);
        WorkRequestService::approve($second, 2);

        $this->signIn(2);
        $this->assertSame([$first], array_column($this->get('/work-requests?status=pending')->viewData('requests'), 'id'));
        $this->assertSame([$second], array_column($this->get('/work-requests?status=approved')->viewData('requests'), 'id'));
        $this->assertCount(2, $this->get('/work-requests?status=all')->viewData('requests'));
        $this->assertSame([], $this->get('/work-requests?status=confirmed')->viewData('requests'));
        $this->assertSame('pending', $this->get('/work-requests?status=nonsense')->viewData('tab'));
    }

    public function test_a_branch_admin_approves_returns_and_rejects_from_the_page(): void
    {
        $approve = $this->submit(1);
        $return = $this->submit(1, ['nap_port_id' => 3]);
        $reject = $this->submit(1, ['nap_port_id' => 4]);
        $this->signIn(2);

        $this->post('/work-requests', ['request_id' => $approve, 'action' => 'approve', 'remarks' => 'Go ahead'])->assertOk();
        $this->assertSame('approved', DB::table('work_requests')->where('id', $approve)->value('status'));

        $this->assertSame('Write what the employee has to correct.', $this->post('/work-requests', ['request_id' => $return, 'action' => 'return', 'remarks' => ''])->viewData('error'));
        $this->post('/work-requests', ['request_id' => $return, 'action' => 'return', 'remarks' => 'Wrong port'])->assertOk();
        $this->assertSame('returned', DB::table('work_requests')->where('id', $return)->value('status'));
        $this->assertSame('Wrong port', DB::table('work_requests')->where('id', $return)->value('admin_remarks'));

        $this->post('/work-requests', ['request_id' => $reject, 'action' => 'reject', 'remarks' => 'Out of coverage'])->assertOk();
        $this->assertSame('rejected', DB::table('work_requests')->where('id', $reject)->value('status'));
    }

    public function test_the_super_admin_and_other_branches_cannot_decide_a_request(): void
    {
        $id = $this->submit(1);

        $this->signIn(1);
        $this->assertSame('Only a branch admin reviews the requests of their branch.', $this->post('/work-requests', ['request_id' => $id, 'action' => 'approve'])->viewData('error'));

        $this->signIn(3);
        $this->post('/work-requests', ['request_id' => $id, 'action' => 'approve'])->assertNotFound();
        $this->assertSame('pending_approval', DB::table('work_requests')->where('id', $id)->value('status'));
    }

    public function test_confirming_from_the_page_saves_the_new_client_and_occupies_the_port(): void
    {
        $id = $this->submit(1);
        WorkRequestService::approve($id, 2);
        WorkRequestService::complete(DB::table('employees')->where('id', 1)->first(), $id, [
            'completion_notes' => 'Done', 'actual_nap_box_id' => 1, 'actual_nap_port_id' => 1,
            'materials' => [['inventory_id' => 1, 'used_quantity' => 1, 'returned_quantity' => 2]],
        ]);

        $this->signIn(2);
        $page = $this->get('/work-requests?status=awaiting')->assertOk();
        $page->assertSee('Confirm completion')->assertSee('Internet plan *');

        $this->assertSame('Choose the internet plan of the new client.', $this->post('/work-requests', ['request_id' => $id, 'action' => 'confirm'])->viewData('error'));

        $done = $this->post('/work-requests', ['request_id' => $id, 'action' => 'confirm', 'internet_plan_id' => 1, 'installation_date' => '2026-10-15', 'email' => 'nina@example.com']);
        $this->assertStringContainsString('The work was confirmed', $done->viewData('success'));
        $this->assertSame('confirmed', DB::table('work_requests')->where('id', $id)->value('status'));
        $this->assertSame('occupied', DB::table('nap_ports')->where('id', 1)->value('status'));
        $client = DB::table('clients')->where('full_name', 'Nina Reyes')->first();
        $this->assertSame('2026-10-15', $client->installation_date);
        $this->assertSame('nina@example.com', $client->email);
        $this->assertSame(12, (int) DB::table('inventory')->where('id', 1)->value('quantity'));
    }

    public function test_a_report_can_be_returned_from_the_page(): void
    {
        $id = $this->submit(1);
        WorkRequestService::approve($id, 2);
        WorkRequestService::complete(DB::table('employees')->where('id', 1)->first(), $id, ['completion_notes' => 'Done', 'actual_nap_box_id' => 1, 'actual_nap_port_id' => 1]);

        $this->signIn(2);
        $this->post('/work-requests', ['request_id' => $id, 'action' => 'return_completion', 'remarks' => 'Add the materials'])->assertOk();

        $this->assertSame('approved', DB::table('work_requests')->where('id', $id)->value('status'));
        $this->assertSame('Add the materials', DB::table('work_requests')->where('id', $id)->value('admin_remarks'));
    }

    public function test_the_review_shows_the_completion_report_and_materials(): void
    {
        $id = $this->submit(1);
        WorkRequestService::approve($id, 2);
        WorkRequestService::complete(DB::table('employees')->where('id', 1)->first(), $id, [
            'completion_notes' => 'Connected, 40 Mbps tested', 'actual_nap_box_id' => 1, 'actual_nap_port_id' => 3,
            'materials' => [['inventory_id' => 1, 'used_quantity' => 2, 'damaged_quantity' => 1]],
        ]);

        $this->signIn(2);
        $html = $this->get('/work-requests?status=awaiting')->assertOk()->getContent();
        $this->assertStringContainsString('Connected, 40 Mbps tested', $html);
        $this->assertStringContainsString('NAP-01 · port 3', $html);
        $this->assertStringContainsString('<td>Router</td><td>2</td><td>0</td><td>1</td>', $html);
    }

    public function test_the_sidebar_counts_the_requests_waiting_for_the_admin_of_that_branch(): void
    {
        $first = $this->submit(1);
        $this->submit(1, ['nap_port_id' => 3]);
        $this->submit(2);
        WorkRequestService::approve($first, 2);

        // Balilihan: one still to approve. The approved one waits for the technician, not for the admin.
        $this->signIn(2);
        $this->assertStringContainsString('<span class="nav-badge" title="Waiting for you">1</span>', $this->get('/dashboard')->getContent());

        // The super admin sees the total of every branch: Balilihan's one and Loon's one.
        $this->signIn(1);
        $this->assertStringContainsString('<span class="nav-badge" title="Waiting for you">2</span>', $this->get('/dashboard')->getContent());

        DB::table('work_requests')->update(['status' => 'confirmed']);
        $this->assertStringNotContainsString('nav-badge', $this->get('/dashboard')->getContent());
    }

    public function test_the_sidebar_links_to_both_pages_for_every_admin(): void
    {
        foreach ([1, 2] as $userId) {
            $this->signIn($userId);
            $html = $this->get('/dashboard')->assertOk()->getContent();
            $this->assertStringContainsString('work_requests.php', $html);
            $this->assertStringContainsString('nap_boxes.php', $html);
        }
    }
}
