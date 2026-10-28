<?php

namespace Database\Seeders;

use App\Support\BillingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives the sample clients (JFT-DEMO-...) the mix a real branch has, so the Clients, Client Accounts, Payment History, Dashboard and
 * Reports pages have something to show:
 *
 *   - paid up: about a third of the clients have paid every bill that is due so far, in full, in cash or by GCash (a bill that was
 *     overdue is paid late), with a payment record each; the next bill, not yet due, stays pending;
 *   - partly paid: a few have paid only part of their oldest bill, so it is still open and the balance is smaller than the bill;
 *   - overdue: bills that are past their due date and unpaid are overdue (the same rule the daily billing run applies);
 *   - suspended: a suspended client is one that is behind. A sample client that was marked suspended without owing anything is active
 *     again, and one that is behind in the same branch is suspended instead, so the number suspended stays the same in every branch.
 *
 * Who is what comes from a number made out of the client code, so running it again changes nothing: a client that already has a payment
 * is left alone and nothing is paid twice. Clients that are not samples are never touched.
 *
 *   php artisan db:seed --class=ClientBillingStatusSeeder
 *
 * To undo it, delete the payments of the sample clients and set their bills back to pending (or restore a backup of clients, bills and
 * payments taken before).
 */
class ClientBillingStatusSeeder extends Seeder
{
    /** Of every 100 sample clients, these many are paid up (numbers 0-34) and these partly paid (35-42). */
    private const PAID_UP = 35;
    private const PARTLY_PAID = 8;

    public function run(): void
    {
        $today = Carbon::today();

        DB::transaction(function () use ($today): void {
            // Unpaid bills past their due date are overdue: the daily billing run says so, and the sample data is from before it ran.
            app(BillingService::class)->markOverdueBills();

            $this->suspendTheOnesWhoAreBehind();
            [$paidUp, $partlyPaid] = $this->recordPayments($today);

            $this->command?->info("{$paidUp} client(s) paid up, {$partlyPaid} partly paid.");
        });
    }

    /** The sample clients (the ones this seeder is allowed to change). */
    private function samples(): \Illuminate\Database\Query\Builder
    {
        return DB::table('clients')->where('client_code', 'like', 'JFT-DEMO-%')->where('archived', 0);
    }

    private function owes(string $alias = 'clients'): string
    {
        return "EXISTS (SELECT 1 FROM bills b WHERE b.client_id = {$alias}.id AND b.status = 'overdue')";
    }

    /** Suspended = behind: per branch, the suspended that owe nothing become active and the same number of those who are behind are suspended. */
    private function suspendTheOnesWhoAreBehind(): void
    {
        $branches = $this->samples()->distinct()->pluck('branch_name');
        foreach ($branches as $branch) {
            $notBehind = $this->samples()->where('branch_name', $branch)->where('status', 'suspended')->whereRaw('NOT ' . $this->owes())->pluck('id')->all();
            if ($notBehind === []) {
                continue;
            }
            DB::table('clients')->whereIn('id', $notBehind)->update(['status' => 'active', 'updated_at' => now()]);

            // The ones who have been behind the longest are suspended first.
            $behind = $this->samples()->where('branch_name', $branch)->where('status', 'active')->whereRaw($this->owes())
                ->orderByRaw("(SELECT MIN(b.due_date) FROM bills b WHERE b.client_id = clients.id AND b.status = 'overdue')")->orderBy('id')
                ->limit(count($notBehind))->pluck('id')->all();
            DB::table('clients')->whereIn('id', $behind)->update(['status' => 'suspended', 'updated_at' => now()]);
        }
    }

    /** @return array{0: int, 1: int} the clients paid up and the clients partly paid */
    private function recordPayments(Carbon $today): array
    {
        $clients = $this->samples()->where('status', '!=', 'suspended')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('payments')->whereColumn('payments.client_id', 'clients.id'))
            ->orderBy('id')->get(['id', 'client_code', 'branch_name']);

        // The cash and GCash are received by the admin of the branch; a branch without one is received by the super admin.
        $admins = DB::table('users')->where('role', 'admin')->where('status', 'active')->orderBy('id')->pluck('id', 'branch_name')->all();
        $superAdmin = (int) DB::table('users')->where('role', 'super_admin')->orderBy('id')->value('id') ?: null;

        $paidUp = 0;
        $partlyPaid = 0;
        $payments = [];

        foreach ($clients as $client) {
            $share = crc32($client->client_code) % 100;
            $mode = $share < self::PAID_UP ? 'paid' : ($share < self::PAID_UP + self::PARTLY_PAID ? 'partly' : null);
            $receivedBy = ($admins[$client->branch_name] ?? $superAdmin);
            // A bill is only payable once its period has begun; the bill of a month that has not started stays pending.
            $bills = DB::table('bills')->where('client_id', $client->id)->whereIn('status', ['pending', 'overdue'])
                ->whereDate('billing_period_start', '<=', $today->toDateString())->orderBy('billing_period_start')->get();
            if ($mode === null || $bills->isEmpty()) {
                continue;
            }

            if ($mode === 'paid') {
                foreach ($bills as $bill) {
                    $payments[] = $this->payment($client->id, $bill, (float) $bill->amount, $today, $receivedBy);
                    DB::table('bills')->where('id', $bill->id)->update(['status' => 'paid']);
                }
                $paidUp++;
            } else {
                // Part of the oldest open bill: between 40% and 70%, in whole 50 pesos.
                $bill = $bills->first();
                $part = round((float) $bill->amount * (0.40 + (crc32('part' . $bill->id) % 31) / 100) / 50) * 50;
                $payments[] = $this->payment($client->id, $bill, min(max($part, 50.0), (float) $bill->amount - 50), $today, $receivedBy);
                $partlyPaid++;
            }
        }

        foreach (array_chunk($payments, 200) as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        return [$paidUp, $partlyPaid];
    }

    /** One payment row for a bill: made after its period began and not after today, a little late for some. */
    private function payment(int $clientId, object $bill, float $amount, Carbon $today, ?int $receivedBy): array
    {
        $seed = crc32('pay' . $bill->id);
        $start = Carbon::parse($bill->billing_period_start);
        $due = Carbon::parse($bill->due_date);
        // On time, within the billing period, or up to a week after the due date; never in the future.
        $latest = $due->copy()->addDays($seed % 8)->min($today);
        $earliest = $start->copy()->addDays(1 + ($seed >> 3) % 5)->min($latest);
        $span = max(0, (int) $earliest->diffInDays($latest));
        $date = $earliest->copy()->addDays($span > 0 ? ($seed >> 6) % ($span + 1) : 0);
        $gcash = ($seed >> 9) % 100 < 40;

        return [
            'client_id' => $clientId,
            'bill_id' => $bill->id,
            'amount' => number_format($amount, 2, '.', ''),
            'payment_date' => $date->toDateString(),
            'payment_method' => $gcash ? 'gcash' : 'cash',
            'reference_number' => $gcash ? 'GC' . str_pad((string) ($seed % 10_000_000_000), 10, '0', STR_PAD_LEFT) : null,
            'notes' => 'Sample payment.',
            'received_by' => $receivedBy,
            'created_at' => $date->copy()->setTime(8 + ($seed >> 12) % 9, ($seed >> 4) % 60)->toDateTimeString(),
        ];
    }
}
