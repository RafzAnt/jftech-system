<?php

namespace Tests\Feature;

use App\Http\Controllers\PriceManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Changing a client's plan in Update Client: the new price starts with the next billing period. Bills of periods that have begun
 * (paid, pending, overdue or part-paid) keep what the previous plan charged; only the incoming bill takes the new price.
 *
 * Seed: client 1 (Ana Reyes, Balilihan) with its admin user 2.
 */
class ClientPlanChangeBillingTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();

        DB::table('internet_plans')->insert([
            ['id' => 1, 'plan_name' => 'Starter', 'monthly_price' => 999],
            ['id' => 2, 'plan_name' => 'Pro', 'monthly_price' => 1500],
        ]);
        DB::table('clients')->where('id', 1)->update(['internet_plan_id' => 1, 'wifi_plan' => 'Starter', 'monthly_rate' => 999]);

        // Reading plan prices applies scheduled changes; that needs tables this test does not have.
        $this->app->instance(PriceManagementController::class, new class extends PriceManagementController {
            public function applyDueScheduledPrices(): void
            {
            }
        });
    }

    private function bill(int $id, string $start, string $status, float $amount = 999): void
    {
        $from = now()->parse($start);
        DB::table('bills')->insert([
            'id' => $id, 'client_id' => 1, 'bill_number' => "B-{$id}", 'billing_period_start' => $from->toDateString(),
            'billing_period_end' => $from->copy()->addMonth()->subDay()->toDateString(), 'amount' => $amount,
            'due_date' => $from->copy()->addMonth()->subDay()->toDateString(), 'status' => $status,
        ]);
    }

    private function updateClient(int $planId): array
    {
        $post = [
            'update_client' => 1, 'client_id' => 1, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'contact_number' => '09171234567',
            'internet_plan_id' => $planId,
        ];
        $request = Request::create('/client-update', 'POST', $post);
        $request->setLaravelSession(app('session')->driver('array'));
        app()->instance('request', $request);
        $action = require app_path('Support/PageActions/clients_update.php');

        return $action($request);
    }

    private function amount(int $billId): float
    {
        return (float) DB::table('bills')->where('id', $billId)->value('amount');
    }

    public function test_a_plan_change_keeps_the_amount_of_every_bill_whose_period_has_begun(): void
    {
        $this->signIn(2);
        $this->bill(10, now()->subMonths(3)->toDateString(), 'overdue');
        $this->bill(11, now()->subMonths(2)->toDateString(), 'overdue');
        $this->bill(12, now()->subMonths(1)->toDateString(), 'pending');   // part-paid below
        DB::table('payments')->insert(['client_id' => 1, 'bill_id' => 12, 'amount' => 400, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash']);
        $this->bill(13, now()->subDays(5)->toDateString(), 'pending');     // the current period
        $this->bill(14, now()->subMonths(4)->toDateString(), 'paid');

        $page = $this->updateClient(2);

        $this->assertSame('Client updated successfully!', $page['success']);
        foreach ([10, 11, 12, 13, 14] as $id) {
            $this->assertSame(999.0, $this->amount($id), "bill {$id} was charged under the previous plan");
        }
        $this->assertSame(1500.0, (float) DB::table('clients')->where('id', 1)->value('monthly_rate'), 'the client pays the new price from now on');
        $this->assertSame(2, (int) DB::table('clients')->where('id', 1)->value('internet_plan_id'));
        $this->assertSame(1, DB::table('client_plan_change_histories')->where('client_id', 1)->count());
    }

    public function test_only_the_incoming_unpaid_bill_takes_the_new_price(): void
    {
        $this->signIn(2);
        $this->bill(20, now()->subDays(5)->toDateString(), 'pending');
        $this->bill(21, now()->addDays(25)->toDateString(), 'pending');   // incoming: its period has not begun

        $this->updateClient(2);

        $this->assertSame(999.0, $this->amount(20));
        $this->assertSame(1500.0, $this->amount(21));
    }

    public function test_an_incoming_bill_that_already_has_a_payment_is_left_alone(): void
    {
        $this->signIn(2);
        $this->bill(30, now()->addDays(25)->toDateString(), 'pending');
        DB::table('payments')->insert(['client_id' => 1, 'bill_id' => 30, 'amount' => 300, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash']);

        $this->updateClient(2);

        $this->assertSame(999.0, $this->amount(30), 'a part-paid bill keeps its balance');
    }

    public function test_saving_without_changing_the_plan_touches_no_bill(): void
    {
        $this->signIn(2);
        $this->bill(40, now()->addDays(25)->toDateString(), 'pending');
        $this->bill(41, now()->subMonths(2)->toDateString(), 'overdue');

        $this->updateClient(1);

        $this->assertSame(999.0, $this->amount(40));
        $this->assertSame(999.0, $this->amount(41));
        $this->assertSame(0, DB::table('client_plan_change_histories')->count());
    }
}
