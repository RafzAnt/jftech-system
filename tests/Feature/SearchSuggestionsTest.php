<?php

namespace Tests\Feature;

use App\Support\NapBoxes;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * What the one search box suggests while someone types: branches (only for the super admin), barangays, and the records of the
 * list (clients, employees, NAP boxes, user accounts), never beyond what the account may see.
 *
 * Seed: clients Ana Reyes (JFT-1, Balilihan, Boctol), Ben Cruz (JFT-2, Loon, Agsoso), Carl Dela Cruz (JFT-3, Loon, archived);
 * employees Eli Santos (Balilihan), Fay Garcia (Loon); users 1 super admin, 2 Balilihan admin, 3 Loon admin, 4 admin without a branch,
 * 5 technician (Balilihan); NAP-01 in Boctol (Balilihan), NAP-02 in Agsoso (Loon).
 */
class SearchSuggestionsTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        NapBoxes::create('Balilihan', 'NAP-01', 'Boctol', 'Purok 2', 4);
        NapBoxes::create('Loon', 'NAP-02', 'Agsoso', null, 2);
    }

    /** @return array{query: string, stage: ?string, suggestions: array<int, array{group: string, label: string, detail: ?string, value: string}>} */
    private function suggest(string $type, string $q): array
    {
        return $this->getJson('/search-suggestions?type=' . $type . '&q=' . urlencode($q))->assertOk()->json();
    }

    /** The kinds that were offered, in order, without repeats. */
    private function kinds(array $answer): array
    {
        return array_values(array_unique(array_map(fn (array $row) => $row['group'], $answer['suggestions'])));
    }

    private function labels(array $answer, ?string $group = null): array
    {
        $rows = array_filter($answer['suggestions'], fn (array $row) => $group === null || $row['group'] === $group);

        return array_values(array_map(fn (array $row) => $row['label'], $rows));
    }

    // ---- step 1: a branch -------------------------------------------------------------------------

    public function test_typing_a_branch_offers_only_branches(): void
    {
        $this->signIn(1);

        $lo = $this->suggest('clients', 'Lo');
        $this->assertSame(['Branch'], $this->kinds($lo), 'no barangays and no clients next to the branches');
        $this->assertSame('branch', $lo['stage']);
        $this->assertSame(['Loboc', 'Loon'], array_slice($this->labels($lo), 0, 2), 'those that start with the text come first');

        $bo = $this->suggest('clients', 'bo');
        $this->assertSame(['Branch'], $this->kinds($bo));
        $this->assertContains('Maribojoc', $this->labels($bo));
        $this->assertNotContains('Boctol', $this->labels($bo), 'Boctol is a barangay, not a branch');
    }

    public function test_the_same_goes_for_employees_nap_boxes_and_user_accounts(): void
    {
        $this->signIn(1);

        foreach (['employees', 'nap_boxes', 'users'] as $type) {
            $this->assertSame(['Branch'], $this->kinds($this->suggest($type, 'Lo')), $type);
        }
    }

    // ---- step 2: a barangay of that branch -----------------------------------------------------------

    public function test_after_a_branch_only_the_barangays_of_that_branch_are_offered(): void
    {
        $this->signIn(1);

        $loon = $this->suggest('clients', 'Loon, ag');
        $this->assertSame(['Barangay'], $this->kinds($loon));
        $this->assertSame('barangay', $loon['stage']);
        $this->assertSame('ag', $loon['query']);
        $this->assertContains('Agsoso', $this->labels($loon));
        $this->assertSame(['group' => 'Barangay', 'label' => 'Agsoso', 'detail' => null, 'value' => 'Agsoso'], $loon['suggestions'][array_search('Agsoso', $this->labels($loon), true)], 'the branch is known, so it is not repeated');

        $balilihan = $this->labels($this->suggest('clients', 'Balilihan, ag'));
        $this->assertNotContains('Agsoso', $balilihan, 'Agsoso belongs to Loon');
        $this->assertContains('Datag Norte', $balilihan);
        $this->assertSame(['Boctol'], $this->labels($this->suggest('clients', 'Balilihan, boc')));
        $this->assertSame([], $this->labels($this->suggest('clients', 'Loon, boc'), 'Barangay'), 'Boctol is not a barangay of Loon');
    }

    public function test_words_without_a_comma_work_the_same(): void
    {
        $this->signIn(1);

        $answer = $this->suggest('clients', 'Loon Ags');
        $this->assertSame('Ags', $answer['query'], 'only the last word is replaced when one is picked');
        $this->assertSame(['Agsoso'], $this->labels($answer));
        $this->assertSame(['Barangay'], $this->kinds($answer));
    }

    public function test_a_step_that_finds_nothing_lets_the_next_one_try(): void
    {
        $this->signIn(1);

        $boctol = $this->suggest('clients', 'Boctol');
        $this->assertSame('barangay', $boctol['stage'], 'no branch is called Boctol, so the barangays are tried');
        $this->assertSame(['group' => 'Barangay', 'label' => 'Boctol', 'detail' => 'Balilihan', 'value' => 'Boctol'], $boctol['suggestions'][0], 'with no branch typed, the branch of the barangay is shown');

        $ben = $this->suggest('clients', 'ben');
        $this->assertSame('record', $ben['stage']);
        $this->assertSame(['Ben Cruz'], $this->labels($ben, 'Client'));
        $this->assertSame([], $this->suggest('clients', 'zzzz qqqq')['suggestions']);
        $this->assertSame([], $this->suggest('clients', '')['suggestions']);
    }

    // ---- step 3: a client of that barangay ------------------------------------------------------------

    public function test_after_a_branch_and_a_barangay_only_their_records_are_offered(): void
    {
        DB::table('clients')->insert([
            ['id' => 4, 'client_code' => 'JFT-4', 'full_name' => 'Ana Lopez', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Cabug', 'status' => 'active', 'archived' => false],
            ['id' => 5, 'client_code' => 'JFT-5', 'full_name' => 'Ana Mora', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Agsoso', 'status' => 'active', 'archived' => false],
        ]);
        $this->signIn(1);

        $this->assertSame(['Ana Mora'], $this->labels($this->suggest('clients', 'Loon, Agsoso, ana')), 'only the clients of that barangay');
        $this->assertSame(['Ana Lopez'], $this->labels($this->suggest('clients', 'Loon, Cabug, ana')));
        $this->assertSame(['Ana Reyes'], $this->labels($this->suggest('clients', 'Balilihan, Boctol, ana')));
        $this->assertSame(['Ana Reyes'], $this->labels($this->suggest('clients', 'Balilihan, Agsoso, ana r')), 'only the branch narrows: the Anas of Loon are not offered');

        $answer = $this->suggest('clients', 'Loon, Agsoso, ben');
        $this->assertSame('record', $answer['stage']);
        $this->assertSame(['Client'], $this->kinds($answer));
        $this->assertSame('JFT-2', $answer['suggestions'][0]['detail'], 'the barangay is known, so only the code is shown');
        $this->assertSame(['Carl Dela Cruz'], $this->labels($this->suggest('clients', 'Loon, Agsoso, carl')), 'archived clients are found as well');
    }

    public function test_employees_and_nap_boxes_follow_the_barangay_too(): void
    {
        DB::table('employees')->where('id', 2)->update(['address' => 'Sitio Mabuhay, Agsoso, Loon']);
        $this->signIn(1);

        $this->assertSame(['Fay Garcia'], $this->labels($this->suggest('employees', 'Loon, Agsoso, fay')));
        $this->assertSame(['Fay Garcia'], $this->labels($this->suggest('employees', 'Loon, Boctol, fay')), 'Boctol is no barangay of Loon, so only the branch narrows the employees');
        $this->assertSame(['NAP-02'], $this->labels($this->suggest('nap_boxes', 'Loon, Agsoso, nap')));
        $this->assertSame(['NAP-01', 'NAP-02'], $this->labels($this->suggest('nap_boxes', 'nap-')), 'a text that is no branch or barangay offers the boxes of every branch');
        $this->assertSame(['Loon Admin'], $this->labels($this->suggest('users', 'Loon, admin')), 'user accounts have no barangay: a branch is followed by the accounts');
    }

    // ---- a branch admin ------------------------------------------------------------------------------

    public function test_a_branch_admin_starts_with_the_barangays_of_their_own_branch(): void
    {
        $this->signIn(2);

        $ag = $this->suggest('clients', 'ag');
        $this->assertSame(['Barangay'], $this->kinds($ag), 'their branch is automatic: no branch step');
        $this->assertNotContains('Agsoso', $this->labels($ag), 'Agsoso belongs to Loon');
        $this->assertContains('Datag Norte', $this->labels($ag));
        $this->assertSame(['group' => 'Barangay', 'label' => 'Boctol', 'detail' => null, 'value' => 'Boctol'], $this->suggest('clients', 'Boc')['suggestions'][0]);

        $this->assertSame([], $this->labels($this->suggest('clients', 'Lo'), 'Branch'), 'never offered a branch');
    }

    public function test_a_branch_admin_then_gets_the_clients_of_the_barangay_in_their_branch_only(): void
    {
        $this->signIn(2);

        $ana = $this->suggest('clients', 'Boctol, ana re');
        $this->assertSame('record', $ana['stage']);
        $this->assertSame(['Ana Reyes'], $this->labels($ana));
        $this->assertSame([], $this->labels($this->suggest('clients', 'Boctol, ben')), 'a client of another branch is not offered');
        $this->assertSame([], $this->labels($this->suggest('clients', 'Loon, Agsoso, ben')), 'typing another branch does not widen the branch');
        $this->assertSame(['Ana Reyes'], $this->labels($this->suggest('clients', 'ana re')), 'a name typed first still finds their own clients');

        $this->assertSame(['Eli Santos'], $this->labels($this->suggest('employees', 'eli')));
        $this->assertSame([], $this->labels($this->suggest('employees', 'fay')));
        $this->assertSame(['NAP-01'], $this->labels($this->suggest('nap_boxes', 'nap-')));
        // A branch admin only manages the technician accounts of their branch, so the Admin accounts are not offered.
        $this->assertSame([], $this->labels($this->suggest('users', 'admin'), 'User'));
        $this->assertSame(['Technician'], $this->labels($this->suggest('users', 'tech'), 'User'));

        $this->signIn(3);
        $this->assertSame(['Ben Cruz', 'Carl Dela Cruz'], $this->labels($this->suggest('clients', 'cruz')), 'the Loon admin has their own clients');
    }

    public function test_an_unknown_list_is_not_found_and_only_admins_can_ask(): void
    {
        $this->signIn(1);
        $this->getJson('/search-suggestions?type=payroll&q=ab')->assertNotFound();

        $this->signIn(5);
        $this->getJson('/search-suggestions?type=clients&q=ana')->assertForbidden();
    }

    public function test_every_search_box_asks_for_the_suggestions_of_its_list(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
        $this->signIn(1);

        foreach (['/clients' => 'clients', '/client-accounts' => 'clients', '/payments' => 'clients', '/payroll' => 'employees', '/nap-boxes' => 'nap_boxes', '/login-history' => 'users'] as $path => $type) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('data-suggest="' . $type . '"', $html, $path);
            $this->assertStringContainsString('data-suggest-url="' . route('search.suggestions') . '"', $html, $path);
            $this->assertStringContainsString('js/search_suggest.js', $html, $path);
        }
    }
}
