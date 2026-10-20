<?php

namespace Tests\Feature;

use Database\Seeders\BranchClientsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/** The sample clients: two in every barangay of every branch, and running the seeder again doubles nothing. */
class BranchClientsSeederTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        DB::table('internet_plans')->insert([
            ['id' => 1, 'plan_name' => 'Starter', 'monthly_price' => 999, 'installation_fee' => 0, 'status' => 'active', 'archived' => false],
            ['id' => 2, 'plan_name' => 'Essential', 'monthly_price' => 1299, 'installation_fee' => 0, 'status' => 'active', 'archived' => false],
            ['id' => 3, 'plan_name' => 'Old plan', 'monthly_price' => 500, 'installation_fee' => 0, 'status' => 'inactive', 'archived' => true],
        ]);
    }

    private function barangayCount(): int
    {
        return array_sum(array_map('count', config('bohol_barangays')));
    }

    public function test_every_barangay_of_every_branch_gets_two_sample_clients(): void
    {
        $this->seed(BranchClientsSeeder::class);

        $this->assertSame($this->barangayCount() * 2, DB::table('clients')->count());
        foreach (config('bohol_branches') as $branch => $unused) {
            foreach (config('bohol_barangays')[$branch] as $barangay) {
                $clients = DB::table('clients')->where('branch_name', $branch)->where('registered_barangay_name', $barangay)->get();
                $this->assertCount(2, $clients, "{$branch} / {$barangay}");
                foreach ($clients as $client) {
                    $this->assertStringStartsWith('JFT-DEMO-', $client->client_code);
                    $this->assertStringContainsString($barangay, $client->exact_address);
                    $this->assertMatchesRegularExpression('/^09\d{9}$/', $client->contact_number);
                }
            }
        }
        $this->assertCount($this->barangayCount() * 2, DB::table('clients')->pluck('full_name')->unique(), 'every name is different');
        $this->assertCount($this->barangayCount() * 2, DB::table('clients')->pluck('contact_number')->unique(), 'every number is different');
        $this->assertCount($this->barangayCount() * 2, DB::table('clients')->pluck('client_code')->unique());
    }

    public function test_each_client_has_an_active_plan_and_a_first_bill(): void
    {
        $this->seed(BranchClientsSeeder::class);

        $this->assertSame(0, DB::table('clients')->where('internet_plan_id', 3)->count(), 'an archived plan is never used');
        $this->assertSame(DB::table('clients')->count(), DB::table('bills')->count());
        $this->assertCount(DB::table('bills')->count(), DB::table('bills')->pluck('bill_number')->unique());
        foreach (DB::table('clients')->limit(60)->get() as $client) {
            $bill = DB::table('bills')->where('client_id', $client->id)->first();
            $this->assertEquals($client->monthly_rate, $bill->amount);
            $this->assertSame($bill->due_date < now()->toDateString() ? 'overdue' : 'pending', $bill->status);
        }
        $this->assertGreaterThan(0, DB::table('clients')->where('status', 'suspended')->count(), 'not everyone is active');
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seed(BranchClientsSeeder::class);
        $before = DB::table('clients')->orderBy('client_code')->pluck('full_name', 'client_code')->all();

        $this->seed(BranchClientsSeeder::class);

        $this->assertSame($before, DB::table('clients')->orderBy('client_code')->pluck('full_name', 'client_code')->all());
        $this->assertSame(count($before), DB::table('bills')->count());
    }

    public function test_a_sample_client_that_is_already_there_counts_towards_the_two(): void
    {
        // What the earlier three-per-branch version left: JFT-DEMO-LOO-01 in Genomoan.
        DB::table('clients')->insert(['client_code' => 'JFT-DEMO-LOO-01', 'full_name' => 'Teresa Uy', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Genomoan', 'status' => 'active']);

        $this->seed(BranchClientsSeeder::class);

        $this->assertSame(2, DB::table('clients')->where('branch_name', 'Loon')->where('registered_barangay_name', 'Genomoan')->count());
        $this->assertSame('Teresa Uy', DB::table('clients')->where('client_code', 'JFT-DEMO-LOO-01')->value('full_name'));
        $this->assertSame($this->barangayCount() * 2, DB::table('clients')->count());
        $this->assertSame(1, DB::table('clients')->where('full_name', 'Teresa Uy')->count(), 'a name in use is not given again');
    }

    public function test_it_leaves_other_clients_alone_and_does_not_count_them(): void
    {
        DB::table('clients')->insert(['client_code' => 'JFT-2026-REAL', 'full_name' => 'Real Client', 'branch_name' => 'Loon', 'registered_barangay_name' => 'Genomoan', 'status' => 'active']);

        $this->seed(BranchClientsSeeder::class);

        $this->assertSame('Real Client', DB::table('clients')->where('client_code', 'JFT-2026-REAL')->value('full_name'));
        $this->assertSame(3, DB::table('clients')->where('branch_name', 'Loon')->where('registered_barangay_name', 'Genomoan')->count(), 'the real client plus two samples');
        $this->assertSame($this->barangayCount() * 2 + 1, DB::table('clients')->count());
    }
}
