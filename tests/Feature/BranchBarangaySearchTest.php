<?php

namespace Tests\Feature;

use App\Http\Controllers\PriceManagementController;
use App\Support\LocationFilter;
use App\Support\WorkRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The super admin searches every list page by branch (and by barangay where the records have one) and sees all the records when
 * nothing is chosen; a branch admin only ever has their own branch, whatever the address bar says.
 *
 * Seed: users 1 super admin, 2 Balilihan admin, 3 Loon admin. Balilihan: client 1 (Boctol), employee 1, payment 1 (1000), payroll 1;
 * Loon: client 2 (Agsoso), client 3 archived, employee 2, payment 2 (2000), payroll 2. The Employees page declares helper
 * functions every time it runs, so each test runs in its own process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class BranchBarangaySearchTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
        DB::table('internet_plans')->insert(['id' => 1, 'plan_name' => 'Starter', 'monthly_price' => 999]);
        $this->app->instance(PriceManagementController::class, new class extends PriceManagementController {
            public function applyDueScheduledPrices(): void
            {
            }
        });
    }

    /** Run a page action the way PageController does and return what it hands to the view. */
    private function page(string $page, array $query = []): array
    {
        $request = Request::create('/' . $page . ($query ? '?' . http_build_query($query) : ''));
        $request->setLaravelSession(app('session')->driver('array'));
        app()->instance('request', $request);

        $action = require app_path("Support/PageActions/{$page}.php");

        return $action($request);
    }

    private function ids(array $rows, string $key = 'id'): array
    {
        $ids = array_map('intval', array_column($rows, $key));
        sort($ids);

        return $ids;
    }

    private function filter(array $query): array
    {
        return LocationFilter::fromRequest(Request::create('/x?' . http_build_query($query)));
    }

    // ---- the filter itself ------------------------------------------------------------------------

    public function test_the_super_admin_may_pick_any_branch_in_any_spelling(): void
    {
        $this->signIn(1);

        $this->assertSame('', $this->filter([])['selectedBranch']);
        $this->assertSame('Loon', $this->filter(['branch' => ' loon '])['selectedBranch']);
        $this->assertSame('', $this->filter(['branch' => 'Atlantis'])['selectedBranch']);
        $this->assertCount(19, $this->filter([])['branches']);
    }

    public function test_the_barangays_must_belong_to_the_chosen_branch(): void
    {
        $this->signIn(1);

        $this->assertSame(['Agsoso'], $this->filter(['branch' => 'Loon', 'barangays' => ['agsoso', 'Boctol', 'Nowhere']])['selectedBarangays']);
        $this->assertSame([], $this->filter(['barangays' => ['Agsoso']])['selectedBarangays'], 'a barangay needs its branch');
        $this->assertSame(['Agsoso'], $this->filter(['branch' => 'Loon', 'barangays' => ['Agsoso', 'AGSOSO']])['selectedBarangays']);
    }

    public function test_a_branch_admin_only_has_their_own_branch(): void
    {
        $this->signIn(2);

        $filter = $this->filter(['branch' => 'Loon', 'barangays' => ['Agsoso']]);

        $this->assertSame('Balilihan', $filter['selectedBranch']);
        $this->assertSame(['Balilihan'], array_keys($filter['branches']));
        $this->assertSame([], $filter['selectedBarangays']);
    }

    // ---- Payment History -----------------------------------------------------------------------------

    public function test_payment_history_shows_every_branch_until_one_is_picked(): void
    {
        $this->signIn(1);

        $all = $this->page('payment_history');
        $this->assertSame([1, 2], $this->ids($all['payments']));
        $this->assertEquals(3000, $all['totalPayments']);

        $loon = $this->page('payment_history', ['branch' => 'Loon']);
        $this->assertSame([2], $this->ids($loon['payments']));
        $this->assertEquals(2000, $loon['totalPayments'], 'the cards follow the filter');
    }

    public function test_payment_history_can_be_searched_by_barangay(): void
    {
        $this->signIn(1);

        $this->assertSame([2], $this->ids($this->page('payment_history', ['branch' => 'Loon', 'barangays' => ['Agsoso']])['payments']));
        $this->assertSame([1], $this->ids($this->page('payment_history', ['branch' => 'Balilihan', 'barangays' => ['Agsoso']])['payments']), 'Agsoso is not a barangay of Balilihan, so it is ignored and the whole branch shows');
        $this->assertSame([1], $this->ids($this->page('payment_history', ['branch' => 'Balilihan', 'barangays' => ['Boctol']])['payments']));
    }

    public function test_payment_history_can_be_searched_by_client_code_and_reference_number(): void
    {
        DB::table('payments')->where('id', 2)->update(['reference_number' => 'GC-778899']);
        $this->signIn(1);

        $this->assertSame([1], $this->ids($this->page('payment_history', ['search' => 'jft-1'])['payments']));
        $this->assertSame([2], $this->ids($this->page('payment_history', ['search' => 'GC-7788'])['payments']));
        $this->assertSame([2], $this->ids($this->page('payment_history', ['search' => 'ben'])['payments']));
    }

    public function test_a_branch_admin_cannot_search_into_another_branchs_payments(): void
    {
        $this->signIn(2);

        $this->assertSame([1], $this->ids($this->page('payment_history', ['branch' => 'Loon'])['payments']));
        $this->assertSame([], $this->ids($this->page('payment_history', ['search' => 'ben'])['payments']), 'a client of another branch is not found');
    }

    public function test_payment_history_shows_the_filter_and_the_branch_and_barangay_of_each_payment(): void
    {
        $this->signIn(1);

        $html = $this->get('/payments?branch=Loon&barangays[]=Agsoso')->assertOk()->getContent();

        $this->assertStringContainsString('id="clientFilterForm"', $html);
        $this->assertStringContainsString('Search Payments', $html);
        $this->assertStringContainsString('<th>Branch</th>', $html);
        $this->assertStringContainsString('<th>Barangay</th>', $html);
        $this->assertStringContainsString('<td>Agsoso</td>', $html);
        $this->assertStringContainsString('Ben Cruz', $html);
        $this->assertStringNotContainsString('Ana Reyes', $html);
    }

    // ---- Employees and attendance -----------------------------------------------------------------------

    public function test_the_employee_list_can_be_narrowed_to_a_branch(): void
    {
        $this->signIn(1);

        $all = $this->page('employees');
        $this->assertSame([1, 2], $this->ids($all['listedEmployees']));
        $this->assertSame('', $all['selectedListBranch']);
    }

    public function test_the_employee_list_of_one_branch(): void
    {
        $this->signIn(1);

        $loon = $this->page('employees', ['branch' => 'loon']);

        $this->assertSame([2], $this->ids($loon['listedEmployees']));
        $this->assertSame('Loon', $loon['selectedListBranch']);
        $this->assertSame([1, 2], $this->ids($loon['employees']), 'the other tabs still know every employee');
    }

    public function test_the_branch_filter_also_narrows_the_attendance_records(): void
    {
        $this->signIn(1);

        $rows = array_filter($this->page('employees', ['tab' => 'attendance', 'branch' => 'Loon'])['attendanceRecords'], fn ($row) => $row['attendance_id'] !== null);

        $this->assertSame([2], $this->ids($rows, 'employee_id'));
    }

    public function test_a_branch_admin_cannot_list_another_branchs_employees(): void
    {
        $this->signIn(2);

        $page = $this->page('employees', ['branch' => 'Loon']);

        $this->assertSame([1], $this->ids($page['listedEmployees']));
        $this->assertSame('', $page['selectedListBranch']);
    }

    public function test_the_employee_page_has_the_branch_filter_in_the_details_tab(): void
    {
        $this->signIn(1);

        $html = $this->get('/employees?branch=Loon')->assertOk()->getContent();

        $this->assertStringContainsString('<option value="Loon" selected>Loon</option>', $html);
        $this->assertStringContainsString('Fay Garcia', $html);
        $this->assertStringNotContainsString('Eli Santos</td>', $html, 'only the employees of the branch are listed');
    }

    // ---- Payroll -------------------------------------------------------------------------------------------

    public function test_payroll_shows_every_branch_until_one_is_picked(): void
    {
        $this->signIn(1);

        $all = $this->page('payroll');
        $this->assertSame([1, 2], $this->ids($all['payrollRecords']));
        $this->assertSame(2, $all['totalRecords']);

        $loon = $this->page('payroll', ['branch' => 'Loon']);
        $this->assertSame([2], $this->ids($loon['payrollRecords']));
        $this->assertSame(1, $loon['totalRecords'], 'the cards follow the filter');
        $this->assertEquals(4000, $loon['totalNetPay']);
        $this->assertSame('Loon', $loon['selectedBranch']);
    }

    public function test_a_branch_admin_cannot_search_into_another_branchs_payroll(): void
    {
        $this->signIn(2);

        $page = $this->page('payroll', ['branch' => 'Loon']);

        $this->assertSame([1], $this->ids($page['payrollRecords']));
        $this->assertSame('Balilihan', $page['selectedBranch'], 'a branch admin always has their own branch');
    }

    public function test_payroll_can_be_searched_by_barangay_in_the_employee_address(): void
    {
        DB::table('employees')->where('id', 1)->update(['address' => 'Purok 2, Boctol, Balilihan']);
        DB::table('employees')->where('id', 2)->update(['address' => 'Sitio Mabuhay, Agsoso, Loon']);

        $this->signIn(1);
        $this->assertSame([2], $this->ids($this->page('payroll', ['branch' => 'Loon', 'barangays' => ['agsoso']])['payrollRecords']));
        $this->assertSame([], $this->ids($this->page('payroll', ['branch' => 'Loon', 'barangays' => ['Cabug']])['payrollRecords']));
        $this->assertSame([1, 2], $this->ids($this->page('payroll', ['barangays' => ['Agsoso']])['payrollRecords']), 'a barangay is ignored until its branch is picked');

        $both = $this->page('payroll', ['branch' => 'Loon', 'barangays' => ['Cabug', 'Agsoso']]);
        $this->assertSame([2], $this->ids($both['payrollRecords']));
        $this->assertSame(1, $both['totalRecords'], 'the cards follow the filter');
    }

    public function test_payroll_can_be_searched_by_employee(): void
    {
        $this->signIn(1);

        $this->assertSame([2], $this->ids($this->page('payroll', ['search' => 'fay'])['payrollRecords']));
        $this->assertSame([1], $this->ids($this->page('payroll', ['search' => 'EMP-1'])['payrollRecords']));
        $this->assertSame([1, 2], $this->ids($this->page('payroll', ['search' => 'Technician'])['payrollRecords']));
        $this->assertSame([], $this->ids($this->page('payroll', ['search' => 'nobody'])['payrollRecords']));
    }

    public function test_a_branch_admin_searches_the_barangays_and_employees_of_their_own_branch_only(): void
    {
        DB::table('employees')->where('id', 1)->update(['address' => 'Purok 2, Boctol, Balilihan']);
        DB::table('employees')->where('id', 2)->update(['address' => 'Boctol, Loon']);
        $this->signIn(2);

        $this->assertSame([1], $this->ids($this->page('payroll', ['barangays' => ['Boctol']])['payrollRecords']));
        $this->assertSame([1], $this->ids($this->page('payroll', ['branch' => 'Loon', 'barangays' => ['Boctol']])['payrollRecords']));
        $this->assertSame([], $this->ids($this->page('payroll', ['search' => 'Fay'])['payrollRecords']), 'an employee of another branch');
        $this->assertSame([1], $this->ids($this->page('payroll', ['search' => 'Eli'])['payrollRecords']));
    }

    public function test_the_payroll_form_suggests_only_the_employees_this_account_pays(): void
    {
        DB::table('employees')->insert([
            ['id' => 3, 'employee_code' => 'EMP-3', 'full_name' => 'Gus Admin', 'position' => 'Admin', 'branch_name' => 'Balilihan', 'daily_rate' => 600, 'status' => 'active'],
            ['id' => 4, 'employee_code' => 'EMP-4', 'full_name' => 'Hal Admin', 'position' => 'Admin', 'branch_name' => 'Loon', 'daily_rate' => 600, 'status' => 'active'],
        ]);

        $this->signIn(2);
        $own = $this->page('payroll')['employeeChoices'];
        $this->assertSame([1], array_column($own, 'id'), 'the technicians of their own branch');
        $this->assertSame('Eli Santos - EMP-1', $own[0]['label']);

        $this->signIn(1);
        $all = $this->page('payroll')['employeeChoices'];
        $this->assertSame([3, 4], array_column($all, 'id'), 'the Admin employees of every branch');
        $this->assertSame('Hal Admin - EMP-4 · Loon', $all[1]['label'], 'the branch is part of what the super admin searches');

        $html = $this->get('/payroll')->assertOk()->getContent();
        $this->assertStringContainsString('id="employeeSearch"', $html);
        $this->assertStringContainsString('<input type="hidden" name="employee_id" id="employeeId">', $html);
        $this->assertStringNotContainsString('<select name="employee_id"', $html);
    }

    public function test_the_payroll_filter_and_the_branch_of_each_payslip_are_shown(): void
    {
        $this->signIn(1);

        $html = $this->get('/payroll?branch=Loon')->assertOk()->getContent();

        $this->assertStringContainsString('id="clientFilterForm"', $html);
        $this->assertStringContainsString('<input type="hidden" id="clientBranchFilter" name="branch" value="Loon">', $html);
        $this->assertStringContainsString('Search Employees', $html);
        $this->assertStringNotContainsString('name="period_start"', $html, 'only the branch, barangay and employee search');
        $this->assertStringContainsString('<span class="payslip-label">Branch</span><strong>Loon</strong>', $html);
    }

    // ---- Work Requests --------------------------------------------------------------------------------------

    private function addWorkRequests(): void
    {
        foreach ([['WR-1', 'Loon', 'Agsoso'], ['WR-2', 'Loon', 'Cabug'], ['WR-3', 'Balilihan', 'Boctol']] as $index => [$code, $branch, $barangay]) {
            DB::table('work_requests')->insert([
                'id' => $index + 1, 'request_code' => $code, 'branch_name' => $branch, 'employee_id' => $branch === 'Loon' ? 2 : 1, 'work_type' => 'repair',
                'barangay_name' => $barangay, 'status' => 'pending_approval', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_work_requests_can_be_searched_by_branch_and_barangay(): void
    {
        $this->addWorkRequests();

        $this->assertSame([1, 2, 3], $this->ids(WorkRequestService::listing(null, null, null, 100, null)));
        $this->assertSame([1, 2], $this->ids(WorkRequestService::listing(null, 'Loon', null, 100, null)));
        $this->assertSame([2], $this->ids(WorkRequestService::listing(null, 'Loon', null, 100, 'cabug')));
        $this->assertSame([], $this->ids(WorkRequestService::listing(null, 'Balilihan', null, 100, 'Cabug')));
        $this->assertSame(1, WorkRequestService::counts(null, 'Loon', 'Agsoso')['pending_approval']);
    }

    public function test_the_work_requests_page_filters_by_barangay_and_the_tab_counts_follow(): void
    {
        $this->addWorkRequests();
        $this->signIn(1);

        $page = $this->page('work_requests', ['status' => 'all', 'branch' => 'Loon', 'barangay' => 'Cabug']);

        $this->assertSame([2], $this->ids($page['requests']));
        $this->assertSame('Cabug', $page['barangay']);
        $this->assertSame(1, $page['tabCounts']['pending']);
        $this->assertContains('Cabug', $page['barangaySuggestions']);
        $this->assertNotContains('Boctol', $page['barangaySuggestions'], 'only the barangays of the chosen branch are offered');
    }

    public function test_a_barangay_that_is_not_one_of_the_branch_is_ignored(): void
    {
        $this->addWorkRequests();
        $this->signIn(1);

        $page = $this->page('work_requests', ['status' => 'all', 'branch' => 'Loon', 'barangay' => 'Boctol']);

        $this->assertSame('', $page['barangay']);
        $this->assertSame([1, 2], $this->ids($page['requests']));
    }

    public function test_a_branch_admin_filters_their_own_barangays(): void
    {
        $this->addWorkRequests();
        $this->signIn(2);

        $page = $this->page('work_requests', ['status' => 'all', 'branch' => 'Loon', 'barangay' => 'Boctol']);

        $this->assertSame([3], $this->ids($page['requests']));
        $this->assertSame('Boctol', $page['barangay']);
    }

    public function test_the_work_requests_page_shows_the_barangay_filter(): void
    {
        $this->addWorkRequests();
        $this->signIn(1);

        $html = $this->get('/work-requests?status=all&branch=Loon&barangay=Cabug')->assertOk()->getContent();

        $this->assertStringContainsString('id="requestBarangay"', $html);
        $this->assertStringContainsString('value="Cabug"', $html);
        $this->assertStringContainsString('<datalist id="requestBarangaySuggestions">', $html);
    }
}
