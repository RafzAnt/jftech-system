<?php

namespace Tests\Feature;

use App\Http\Controllers\PriceManagementController;
use App\Support\LocationFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Every list page has ONE search box. The super admin types a branch, a barangay or a name (or several words that narrow each other)
 * and sees all the records when it is empty; a branch admin has no branch to type, their own is automatic, so a word of another branch
 * finds nothing.
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

    public function test_the_search_is_one_text_whatever_else_is_in_the_address_bar(): void
    {
        $this->signIn(1);

        $this->assertSame(['searchTerm' => '', 'scopeBranch' => null], $this->filter([]));
        $this->assertSame('Loon Agsoso', $this->filter(['search' => '  Loon Agsoso '])['searchTerm']);
        $this->assertSame('', $this->filter(['branch' => 'Loon', 'barangays' => ['Agsoso']])['searchTerm'], 'the old branch and barangay boxes are gone');
    }

    public function test_a_branch_admin_has_their_own_branch_automatically(): void
    {
        $this->signIn(2);

        $this->assertSame('Balilihan', $this->filter(['branch' => 'Loon'])['scopeBranch']);
    }

    public function test_the_placeholder_says_what_can_be_typed(): void
    {
        $this->assertSame('Branch, Barangay, Clients', LocationFilter::placeholder(null, 'Clients'));
        $this->assertSame('Barangay, Clients', LocationFilter::placeholder('Balilihan', 'Clients'), 'a branch admin has no branch to type');
        $this->assertSame('Branch, Barangay, Employees', LocationFilter::placeholder(null, 'Employees'));
        $this->assertSame('Branch, Users', LocationFilter::placeholder(null, 'Users', false));
        $this->assertSame('Users', LocationFilter::placeholder('Loon', 'Users', false));
    }

    public function test_the_words_of_a_search_narrow_each_other(): void
    {
        $this->assertSame(['Loon', 'Agsoso', 'Ana'], LocationFilter::words(' Loon,  Agsoso   Ana '));
        $this->assertSame([], LocationFilter::words('  , '));

        [$sql, $params] = LocationFilter::wordConditions('Loon ana', ['c.full_name', 'c.branch_name']);
        $this->assertSame(2, substr_count($sql, ' AND ('), 'every word has to be found');
        $this->assertSame(['%Loon%', '%Loon%', '%ana%', '%ana%'], $params);
        $this->assertSame(['', []], LocationFilter::wordConditions('', ['c.full_name']));
    }

    // ---- Payment History -----------------------------------------------------------------------------

    public function test_payment_history_shows_every_branch_until_something_is_typed(): void
    {
        $this->signIn(1);

        $all = $this->page('payment_history');
        $this->assertSame([1, 2], $this->ids($all['payments']));
        $this->assertEquals(3000, $all['totalPayments']);

        $loon = $this->page('payment_history', ['search' => 'Loon']);
        $this->assertSame([2], $this->ids($loon['payments']));
        $this->assertEquals(2000, $loon['totalPayments'], 'the cards follow the search');
    }

    public function test_payment_history_can_be_searched_by_branch_barangay_and_both_together(): void
    {
        $this->signIn(1);

        $this->assertSame([2], $this->ids($this->page('payment_history', ['search' => 'Agsoso'])['payments']), 'a barangay');
        $this->assertSame([1], $this->ids($this->page('payment_history', ['search' => 'boctol'])['payments']));
        $this->assertSame([2], $this->ids($this->page('payment_history', ['search' => 'Loon Agsoso'])['payments']), 'a branch and its barangay');
        $this->assertSame([], $this->ids($this->page('payment_history', ['search' => 'Balilihan Agsoso'])['payments']), 'Agsoso is not a barangay of Balilihan');
        $this->assertSame([2], $this->ids($this->page('payment_history', ['search' => 'Loon, ben'])['payments']), 'a branch and a client');
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

        $this->assertSame([1], $this->ids($this->page('payment_history', ['branch' => 'Loon'])['payments']), 'the old branch box does nothing any more');
        $this->assertSame([], $this->ids($this->page('payment_history', ['search' => 'Loon'])['payments']), 'another branch is not found');
        $this->assertSame([], $this->ids($this->page('payment_history', ['search' => 'ben'])['payments']), 'a client of another branch is not found');
        $this->assertSame([1], $this->ids($this->page('payment_history', ['search' => 'Balilihan Boctol'])['payments']));
    }

    public function test_payment_history_has_one_search_box_and_shows_the_branch_and_barangay_of_each_payment(): void
    {
        $this->signIn(1);
        $html = $this->get('/payments?search=Loon+Agsoso')->assertOk()->getContent();

        $this->assertStringContainsString('id="clientFilterForm"', $html);
        $this->assertStringContainsString('Search Payments', $html);
        $this->assertStringContainsString('placeholder="Branch, Barangay, Payments"', $html);
        $this->assertSame(1, substr_count($html, 'name="search"'));
        $this->assertStringNotContainsString('id="clientBranchInput"', $html);
        $this->assertStringNotContainsString('id="clientBarangayInput"', $html);
        $this->assertStringContainsString('<th>Branch</th>', $html);
        $this->assertStringContainsString('<th>Barangay</th>', $html);
        $this->assertStringContainsString('<td>Agsoso</td>', $html);
        $this->assertStringContainsString('Ben Cruz', $html);
        $this->assertStringNotContainsString('Ana Reyes', $html);

        $this->signIn(2);
        $this->assertStringContainsString('placeholder="Barangay, Payments"', $this->get('/payments')->assertOk()->getContent(), 'a branch admin has no branch to type');
    }

    // ---- Employees and attendance -----------------------------------------------------------------------

    public function test_the_employee_list_shows_everyone_until_something_is_typed(): void
    {
        $this->signIn(1);

        $this->assertSame([1, 2], $this->ids($this->page('employees')['listedEmployees']));
    }

    public static function superAdminEmployeeSearches(): array
    {
        return [
            'a branch' => [['search' => 'loon'], [2]],
            'a name' => [['search' => 'fay'], [2]],
            'a code and its branch' => [['search' => 'EMP-1 balilihan'], [1]],
            'a code and another branch' => [['search' => 'EMP-1 Loon'], []],
        ];
    }

    #[DataProvider('superAdminEmployeeSearches')]
    public function test_the_employee_list_is_searched_by_branch_name_or_code(array $query, array $expected): void
    {
        $this->signIn(1);

        $page = $this->page('employees', $query);

        $this->assertSame($expected, $this->ids($page['listedEmployees']));
        $this->assertSame([1, 2], $this->ids($page['employees']), 'the other tabs still know every employee');
    }

    public function test_the_search_also_narrows_the_attendance_records(): void
    {
        $this->signIn(1);

        $rows = array_filter($this->page('employees', ['tab' => 'attendance', 'search' => 'Loon'])['attendanceRecords'], fn ($row) => $row['attendance_id'] !== null);

        $this->assertSame([2], $this->ids($rows, 'employee_id'));
    }

    /** The employees of the attendance rows of the day that belong to a scan (the page also lists active employees without one). */
    private function attendanceEmployees(array $query): array
    {
        DB::table('employees')->where('id', 1)->update(['address' => 'Purok 2, Boctol, Balilihan']);
        DB::table('employees')->where('id', 2)->update(['address' => 'Sitio Mabuhay, Agsoso, Loon']);
        $rows = array_filter($this->page('employees', ['tab' => 'attendance', ...$query])['attendanceRecords'], fn ($row) => $row['attendance_id'] !== null);

        return $this->ids($rows, 'employee_id');
    }

    public static function superAdminAttendanceSearches(): array
    {
        return [
            'every branch until something is typed' => [[], [1, 2]],
            'a branch' => [['search' => 'Loon'], [2]],
            'a barangay (in the address)' => [['search' => 'agsoso'], [2]],
            'a branch and its barangay' => [['search' => 'Loon agsoso'], [2]],
            'a barangay the branch does not have' => [['search' => 'Loon Cabug'], []],
            'an employee by name' => [['search' => 'fay'], [2]],
            'an employee by code' => [['search' => 'EMP-1'], [1]],
            'an employee of another branch' => [['search' => 'Loon Eli'], []],
        ];
    }

    #[DataProvider('superAdminAttendanceSearches')]
    public function test_attendance_is_searched_by_branch_barangay_and_employee(array $query, array $expected): void
    {
        $this->signIn(1);

        $this->assertSame($expected, $this->attendanceEmployees($query));
    }

    public static function branchAdminAttendanceSearches(): array
    {
        return [
            'another branch in the address bar' => [['branch' => 'Loon'], [1]],
            'a barangay of their branch' => [['search' => 'Boctol'], [1]],
            'a barangay of another branch finds nothing, never widening the branch' => [['search' => 'Agsoso'], []],
            'an employee of another branch' => [['search' => 'Fay'], []],
            'an employee of their branch' => [['search' => 'Eli'], [1]],
        ];
    }

    #[DataProvider('branchAdminAttendanceSearches')]
    public function test_a_branch_admin_searches_attendance_inside_their_own_branch_only(array $query, array $expected): void
    {
        $this->signIn(2);

        $this->assertSame($expected, $this->attendanceEmployees($query));
    }

    public function test_the_attendance_tab_has_the_same_one_search_box_as_the_other_pages(): void
    {
        $this->signIn(1);

        $html = $this->get('/employees?tab=attendance')->assertOk()->getContent();

        $this->assertStringContainsString('id="attendanceSearchInput"', $html);
        $this->assertStringContainsString('name="attendance_date"', $html);
        $this->assertStringContainsString('Search Employees', $html);
        $this->assertStringContainsString('placeholder="Branch, Barangay, Employees"', $html);
        $this->assertStringNotContainsString('id="clientBranchInput"', $html);
        $this->assertStringNotContainsString('id="clientBarangayInput"', $html);
        $this->assertStringNotContainsString('All Employees', $html);
    }

    public function test_a_branch_admin_has_no_branch_to_type_in_the_employee_search(): void
    {
        $this->signIn(2);

        $html = $this->get('/employees?tab=attendance')->assertOk()->getContent();

        $this->assertStringContainsString('placeholder="Barangay, Employees"', $html);
        $this->assertStringNotContainsString('placeholder="Branch, Barangay, Employees"', $html);
    }

    public static function branchAdminEmployeeSearches(): array
    {
        return [
            'the old branch box' => [['branch' => 'Loon'], [1]],
            'a word of another branch' => [['search' => 'Loon'], []],
            'an employee of another branch' => [['search' => 'Fay'], []],
            'an employee of their branch' => [['search' => 'Eli'], [1]],
        ];
    }

    #[DataProvider('branchAdminEmployeeSearches')]
    public function test_a_branch_admin_cannot_list_another_branchs_employees(array $query, array $expected): void
    {
        $this->signIn(2);

        $this->assertSame($expected, $this->ids($this->page('employees', $query)['listedEmployees']));
    }

    public function test_the_employee_details_tab_has_the_search_box_and_lists_what_it_finds(): void
    {
        $this->signIn(1);

        $html = $this->get('/employees?tab=details&search=Loon')->assertOk()->getContent();

        $this->assertStringContainsString('id="employeeListSearchInput"', $html);
        $this->assertStringContainsString('value="Loon"', $html);
        $this->assertStringNotContainsString('<option value="Loon" selected>', $html, 'the branch drop-down is gone');
        $this->assertStringContainsString('Fay Garcia', $html);
        $this->assertStringNotContainsString('Eli Santos</td>', $html, 'only the employees found are listed');
    }

    // ---- Payroll -------------------------------------------------------------------------------------------

    public function test_payroll_shows_every_branch_until_something_is_typed(): void
    {
        $this->signIn(1);

        $all = $this->page('payroll');
        $this->assertSame([1, 2], $this->ids($all['payrollRecords']));
        $this->assertSame(2, $all['totalRecords']);

        $loon = $this->page('payroll', ['search' => 'Loon']);
        $this->assertSame([2], $this->ids($loon['payrollRecords']));
        $this->assertSame(1, $loon['totalRecords'], 'the cards follow the search');
        $this->assertEquals(4000, $loon['totalNetPay']);
    }

    public function test_a_branch_admin_cannot_search_into_another_branchs_payroll(): void
    {
        $this->signIn(2);

        $this->assertSame([1], $this->ids($this->page('payroll', ['branch' => 'Loon'])['payrollRecords']), 'the old branch box does nothing any more');
        $this->assertSame([], $this->ids($this->page('payroll', ['search' => 'Loon'])['payrollRecords']));
    }

    public function test_payroll_can_be_searched_by_barangay_in_the_employee_address(): void
    {
        DB::table('employees')->where('id', 1)->update(['address' => 'Purok 2, Boctol, Balilihan']);
        DB::table('employees')->where('id', 2)->update(['address' => 'Sitio Mabuhay, Agsoso, Loon']);

        $this->signIn(1);
        $this->assertSame([2], $this->ids($this->page('payroll', ['search' => 'agsoso'])['payrollRecords']));
        $this->assertSame([2], $this->ids($this->page('payroll', ['search' => 'Loon agsoso'])['payrollRecords']));
        $this->assertSame([], $this->ids($this->page('payroll', ['search' => 'Loon Cabug'])['payrollRecords']));
        $this->assertSame([], $this->ids($this->page('payroll', ['search' => 'Balilihan Agsoso'])['payrollRecords']));

        $found = $this->page('payroll', ['search' => 'Mabuhay']);
        $this->assertSame([2], $this->ids($found['payrollRecords']));
        $this->assertSame(1, $found['totalRecords'], 'the cards follow the search');
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

        $this->assertSame([1], $this->ids($this->page('payroll', ['search' => 'Boctol'])['payrollRecords']), 'Boctol of Loon is not theirs');
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

    public function test_the_payroll_has_one_search_box_and_the_branch_of_each_payslip_is_shown(): void
    {
        $this->signIn(1);

        $html = $this->get('/payroll?search=Loon')->assertOk()->getContent();

        $this->assertStringContainsString('id="clientFilterForm"', $html);
        $this->assertStringContainsString('Search Employees', $html);
        $this->assertStringContainsString('placeholder="Branch, Barangay, Employees"', $html);
        $this->assertSame(1, substr_count($html, 'name="search"'));
        $this->assertStringNotContainsString('id="clientBranchFilter"', $html);
        $this->assertStringNotContainsString('name="period_start"', $html, 'only the search box');
        $this->assertStringContainsString('<span class="payslip-label">Branch</span><strong>Loon</strong>', $html);

        $this->signIn(2);
        $this->assertStringContainsString('placeholder="Barangay, Employees"', $this->get('/payroll')->assertOk()->getContent());
    }
}
