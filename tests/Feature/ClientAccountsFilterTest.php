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

    public function test_branch_filter_limits_clients(): void
    {
        $this->assertSame([1, 2], $this->ids(['branch' => 'Balilihan']));
        $this->assertSame([3], $this->ids(['branch' => 'Loon']));
    }

    public function test_barangay_filter_applies_within_branch_and_ignores_other_branches_barangays(): void
    {
        $this->assertSame([2], $this->ids(['branch' => 'Balilihan', 'barangays' => ['Boyog Sur']]));
        // Barangays only count when they belong to the selected branch.
        $this->assertSame([1, 2, 3], $this->ids(['barangays' => ['Boyog Sur']]));
    }

    public function test_search_matches_name_code_mobile_branch_and_barangay_and_respects_filters(): void
    {
        $this->assertSame([1], $this->ids(['search' => 'ana'], 'searchResults'));
        $this->assertSame([2], $this->ids(['search' => 'JFT-2'], 'searchResults'));
        $this->assertSame([3], $this->ids(['search' => '09170000003'], 'searchResults'));
        $this->assertSame([3], $this->ids(['search' => 'loon'], 'searchResults'));
        $this->assertSame([1], $this->ids(['search' => 'boctol'], 'searchResults'));
        $this->assertSame([2], $this->ids(['search' => 'balilihan', 'branch' => 'Balilihan', 'barangays' => ['Boyog Sur']], 'searchResults'));
    }

    public function test_archived_view_only_lists_archived_clients(): void
    {
        $this->assertSame([4], $this->ids(['view' => 'archived']));
        $this->assertSame([4], $this->ids(['view' => 'archived', 'search' => 'ana'], 'searchResults'));
    }

    public function test_page_shows_branch_barangay_filters_and_columns(): void
    {
        $action = require app_path('Support/PageActions/bill_inquiry.php');
        $data = $action(Request::create('/client-accounts', 'GET', ['branch' => 'Balilihan', 'barangays' => ['Boctol']]));
        $data['currentPage'] = 'bill_inquiry';

        $html = view('pages.bill_inquiry', $data)->render();

        $this->assertStringContainsString('id="clientBranchInput"', $html);
        $this->assertStringContainsString('id="clientBarangayInput"', $html);
        $this->assertStringContainsString('<th>Branch</th>', $html);
        $this->assertStringContainsString('<th>Barangay</th>', $html);
        $this->assertStringContainsString('Ana Reyes', $html);
        $this->assertStringNotContainsString('Ben Cruz', $html);
        $this->assertStringNotContainsString('Carl Dela Cruz', $html);
    }
}
