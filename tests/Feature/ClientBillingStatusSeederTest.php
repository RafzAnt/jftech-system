<?php

namespace Tests\Feature;

use Database\Seeders\ClientBillingStatusSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The sample clients get the mix of a real branch: some paid up, some part-paid, some overdue, and a suspended client is one that owes.
 *
 * 80 sample clients (JFT-DEMO-...): 50 in Balilihan (admin: user 2), 20 in Loon (admin: user 3) and 10 in Albur (no admin: the super admin,
 * user 1, receives); each
 * has the bill of the month it began (the due date of the older ones has passed) and the incoming bill. Every 7th client is suspended,
 * whether it owes or not. The seeded client JFT-1 is not a sample.
 */
class ClientBillingStatusSeederTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::table('payments')->delete();

        for ($i = 1; $i <= 80; $i++) {
            $branch = $i <= 50 ? 'Balilihan' : ($i <= 70 ? 'Loon' : 'Albur');
            $start = Carbon::today()->subDays(5 + ($i * 7) % 70);
            $clientId = 100 + $i;
            DB::table('clients')->insert([
                'id' => $clientId, 'client_code' => sprintf('JFT-DEMO-%s-%02d', strtoupper(substr($branch, 0, 3)), $i), 'full_name' => "Sample {$i}",
                'branch_name' => $branch, 'registered_barangay_name' => 'Boctol', 'monthly_rate' => 999, 'status' => $i % 7 === 0 ? 'suspended' : 'active', 'archived' => false,
            ]);
            // The bill of the month the client began; "pending" even when it is past due, as the sample data was made before the daily run.
            DB::table('bills')->insert([
                'client_id' => $clientId, 'bill_number' => "B-{$clientId}-1", 'billing_period_start' => $start->toDateString(),
                'billing_period_end' => $start->copy()->addDays(30)->toDateString(), 'amount' => 999, 'due_date' => $start->copy()->addDays(30)->toDateString(), 'status' => 'pending',
            ]);
            // The incoming bill: its period has not begun, so it stays pending.
            DB::table('bills')->insert([
                'client_id' => $clientId, 'bill_number' => "B-{$clientId}-2", 'billing_period_start' => Carbon::today()->addDays(10 + $i % 15)->toDateString(),
                'billing_period_end' => Carbon::today()->addDays(40 + $i % 15)->toDateString(), 'amount' => 999, 'due_date' => Carbon::today()->addDays(40 + $i % 15)->toDateString(), 'status' => 'pending',
            ]);
        }
    }

    private function run_(): void
    {
        (new ClientBillingStatusSeeder())->run();
    }

    /** @return array<string, mixed> everything the seeder may change, to compare two runs */
    private function snapshot(): array
    {
        return [
            DB::table('clients')->orderBy('id')->pluck('status', 'id')->all(),
            DB::table('bills')->orderBy('id')->pluck('status', 'id')->all(),
            DB::table('payments')->orderBy('id')->get(['client_id', 'bill_id', 'amount', 'payment_date', 'payment_method'])->map(fn ($row) => (array) $row)->all(),
        ];
    }

    public function test_the_sample_clients_end_up_paid_up_part_paid_overdue_and_suspended(): void
    {
        $this->run_();

        $this->assertGreaterThan(15, DB::table('clients')->where('client_code', 'like', 'JFT-DEMO-%')->whereExists(fn ($q) => $q->selectRaw('1')->from('bills')->whereColumn('bills.client_id', 'clients.id')->where('status', 'paid'))->count(), 'clients paid up');
        $this->assertGreaterThan(0, DB::table('bills')->where('status', 'overdue')->count(), 'overdue bills');
        $this->assertGreaterThan(0, DB::table('clients')->where('status', 'suspended')->count(), 'suspended clients');

        // A part-paid bill is still open and has less paid than it asks.
        $partly = DB::table('bills')->whereIn('status', ['pending', 'overdue'])->whereIn('id', DB::table('payments')->select('bill_id'))->get();
        $this->assertGreaterThan(0, $partly->count(), 'part-paid bills');
        foreach ($partly as $bill) {
            $paid = (float) DB::table('payments')->where('bill_id', $bill->id)->sum('amount');
            $this->assertTrue($paid > 0 && $paid < (float) $bill->amount, "bill {$bill->id}: {$paid} of {$bill->amount}");
        }
    }

    public function test_a_paid_bill_is_paid_in_full_after_its_period_began_and_never_in_the_future(): void
    {
        $this->run_();

        foreach (DB::table('bills')->where('client_id', '>', 100)->where('status', 'paid')->get() as $bill) {
            $payments = DB::table('payments')->where('bill_id', $bill->id)->get();
            $this->assertCount(1, $payments, "bill {$bill->id} has one payment");
            $this->assertEquals((float) $bill->amount, (float) $payments[0]->amount);
            $this->assertLessThanOrEqual(Carbon::today()->toDateString(), $payments[0]->payment_date);
            $this->assertGreaterThanOrEqual($bill->billing_period_start, $payments[0]->payment_date);
            $this->assertLessThanOrEqual(Carbon::today()->toDateString(), $bill->billing_period_start, 'a bill that has not begun is not paid');
        }
        // The incoming bills (their period has not begun) were never touched.
        $this->assertSame(0, DB::table('bills')->where('client_id', '>', 100)->where('billing_period_start', '>', Carbon::today()->toDateString())->where('status', '!=', 'pending')->count());
    }

    public function test_payments_are_cash_or_gcash_received_by_the_branch_admin_or_the_super_admin(): void
    {
        $this->run_();

        $this->assertSame(['cash', 'gcash'], DB::table('payments')->distinct()->orderBy('payment_method')->pluck('payment_method')->all());
        $this->assertSame(0, DB::table('payments')->where('payment_method', 'gcash')->whereNull('reference_number')->count(), 'a GCash payment has its reference');
        $this->assertSame(0, DB::table('payments')->where('payment_method', 'cash')->whereNotNull('reference_number')->count());

        $receivedBy = fn (string $branch) => DB::table('payments')->join('clients', 'clients.id', '=', 'payments.client_id')->where('clients.branch_name', $branch)->distinct()->pluck('payments.received_by')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([2], $receivedBy('Balilihan'), 'the Balilihan admin received them');
        $this->assertSame([3], $receivedBy('Loon'), 'the Loon admin received them');
        $this->assertSame([1], $receivedBy('Albur'), 'Albur has no admin, so the super admin did');
    }

    public function test_a_suspended_client_is_one_that_owes_and_the_number_suspended_per_branch_stays(): void
    {
        $before = DB::table('clients')->where('status', 'suspended')->selectRaw('branch_name, COUNT(*) as total')->groupBy('branch_name')->pluck('total', 'branch_name')->all();
        $this->run_();

        $after = DB::table('clients')->where('status', 'suspended')->selectRaw('branch_name, COUNT(*) as total')->groupBy('branch_name')->pluck('total', 'branch_name')->all();
        $this->assertSame($before, $after);
        foreach (DB::table('clients')->where('status', 'suspended')->pluck('id') as $id) {
            $this->assertTrue(DB::table('bills')->where('client_id', $id)->where('status', 'overdue')->exists(), "suspended client {$id} owes");
            $this->assertSame(0, DB::table('payments')->where('client_id', $id)->count(), "suspended client {$id} has paid nothing");
        }
    }

    public function test_unpaid_bills_past_their_due_date_are_overdue(): void
    {
        $this->run_();

        $this->assertSame(0, DB::table('bills')->where('status', 'pending')->where('due_date', '<', Carbon::today()->toDateString())->count());
    }

    public function test_running_it_again_changes_nothing_and_clients_that_are_not_samples_are_left_alone(): void
    {
        $this->run_();
        $once = $this->snapshot();
        $this->run_();

        $this->assertSame($once, $this->snapshot());
        $this->assertSame('active', DB::table('clients')->where('client_code', 'JFT-1')->value('status'));
        $this->assertSame(0, DB::table('payments')->where('client_id', 1)->count());
        $this->assertSame('paid', DB::table('bills')->where('id', 1)->value('status'), 'the bill of a client that is no sample stays as it was');
    }
}
