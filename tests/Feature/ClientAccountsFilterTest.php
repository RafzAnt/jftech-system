<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientAccountsFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The actions only show a branch's records to its own admin; the super admin sees every branch.
        $this->actingAs((new \App\Models\User())->forceFill(['id' => 1, 'role' => 'super_admin', 'status' => 'active']));

        Schema::create('barangays', function ($table) {
            $table->id();
            $table->string('barangay_name');
        });

        Schema::create('clients', function ($table) {
            $table->id();
            $table->string('client_code')->nullable();
            $table->string('full_name');
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->string('wifi_plan')->nullable();
            $table->string('status')->default('active');
            $table->boolean('archived')->default(false);
            $table->string('branch_name')->nullable();
            $table->string('registered_barangay_name')->nullable();
            $table->unsignedBigInteger('barangay_id')->nullable();
        });

        Schema::create('bills', function ($table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('status')->default('pending');
        });

        DB::table('clients')->insert([
            ['id' => 1, 'client_code' => 'JFT-1', 'full_name' => 'Ana Reyes', 'contact_number' => '09170000001', 'branch_name' => 'Balilihan', 'registered_barangay_name' => 'Boctol', 'archived' => false],
            ['id' => 2, 'client_code' => 'JFT-2', 'full_name' => 'Ben Cruz', 'contact_number' => '09170000002', 'branch_name' => 'Balilihan', 'registered_barangay_name' => 'Boyog Sur', 'archived' => false],
            ['id' => 3, 'client_code' => 'JFT-3', 'full_name' => 'Carl Dela Cruz', 'contact_number' => '09170000003', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Agsoso', 'archived' => false],
            ['id' => 4, 'client_code' => 'JFT-4', 'full_name' => 'Archived Ana', 'contact_number' => '09170000004', 'branch_name' => 'Balilihan', 'registered_barangay_name' => 'Boctol', 'archived' => true],
        ]);
    }

    private function ids(array $query, string $key = 'allClients'): array
    {
        $action = require app_path('Support/PageActions/bill_inquiry.php');
        $result = $action(Request::create('/client-accounts', 'GET', $query));

        return array_map('intval', array_column($result[$key], 'id'));
    }

    public function test_a_branch_typed_in_the_search_limits_clients(): void
    {
        // Newest client first.
        $this->assertSame([3, 2, 1], $this->ids([]));
        $this->assertSame([2, 1], $this->ids(['search' => 'Balilihan']));
        $this->assertSame([3], $this->ids(['search' => 'Loon']));
    }

    public function test_a_barangay_typed_in_the_search_limits_clients_and_words_narrow_each_other(): void
    {
        $this->assertSame([2], $this->ids(['search' => 'Boyog Sur']));
        $this->assertSame([2], $this->ids(['search' => 'Balilihan Boyog Sur']));
        // A barangay that belongs to another branch finds nothing together with this one.
        $this->assertSame([], $this->ids(['search' => 'Loon Boyog']));
        $this->assertSame([1], $this->ids(['search' => 'Balilihan, boctol, ana']));
    }

    public function test_search_matches_name_code_mobile_branch_and_barangay(): void
    {
        $this->assertSame([1], $this->ids(['search' => 'ana']));
        $this->assertSame([2], $this->ids(['search' => 'JFT-2']));
        $this->assertSame([3], $this->ids(['search' => '09170000003']));
        $this->assertSame([3], $this->ids(['search' => 'loon']));
        $this->assertSame([1], $this->ids(['search' => 'boctol']));
        $this->assertSame([2], $this->ids(['search' => 'balilihan cruz']));
    }

    public function test_archived_view_only_lists_archived_clients(): void
    {
        $this->assertSame([4], $this->ids(['view' => 'archived']));
        $this->assertSame([4], $this->ids(['view' => 'archived', 'search' => 'ana']));
    }

    public function test_page_has_one_search_box_and_the_branch_and_barangay_columns(): void
    {
        $action = require app_path('Support/PageActions/bill_inquiry.php');
        $data = $action(Request::create('/client-accounts', 'GET', ['search' => 'Balilihan Boctol']));
        $data['currentPage'] = 'bill_inquiry';

        $html = view('pages.bill_inquiry', $data)->render();

        $this->assertSame(1, substr_count($html, 'name="search"'));
        $this->assertStringContainsString('placeholder="Branch, Barangay, Clients"', $html);
        $this->assertStringNotContainsString('id="clientBranchInput"', $html);
        $this->assertStringNotContainsString('id="clientBarangayInput"', $html);
        $this->assertStringContainsString('<th>Branch</th>', $html);
        $this->assertStringContainsString('<th>Barangay</th>', $html);
        $this->assertStringContainsString('Ana Reyes', $html);
        $this->assertStringNotContainsString('Ben Cruz', $html);
        $this->assertStringNotContainsString('Carl Dela Cruz', $html);
    }
}
