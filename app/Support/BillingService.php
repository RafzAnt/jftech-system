<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Creates missing recurring bills without duplicating an existing billing period. */
class BillingService
{
    /**
     * One-time-safe repair for legacy/demo records. Monthly bills use their
     * own period end as due date, retain paid amounts, and use the same
     * deterministic bill-number format as newly generated bills.
     */
    public function normalizeRecurringBills(): int
    {
        $updated = 0;
        DB::transaction(function () use (&$updated): void {
            DB::table('bills')->orderBy('id')->lockForUpdate()->get()->each(function ($bill) use (&$updated): void {
                $periodEnd = CarbonImmutable::parse($bill->billing_period_end)->toDateString();
                $billNumber = $this->billNumber((int) $bill->client_id, $bill->billing_period_start);
                $status = $bill->status;
                if (in_array($status, ['pending', 'overdue'], true)) {
                    $status = $periodEnd < today()->toDateString() ? 'overdue' : 'pending';
                }

                $changed = $bill->due_date !== $periodEnd || $bill->bill_number !== $billNumber || $bill->status !== $status;
                if ($changed) {
                    DB::table('bills')->where('id', $bill->id)->update([
                        'bill_number' => $billNumber,
                        'due_date' => $periodEnd,
                        'status' => $status,
                    ]);
                    $updated++;
                }
            });
        });

        return $updated;
    }

    /**
     * Keep one client ready for account inquiry: catch up every bill due up to
     * today, mark unpaid past-due bills overdue, and create the next future
     * period as the visible incoming bill. This method is idempotent.
     */
    public function syncClientBilling(int $clientId): array
    {
        $overdue = $this->markOverdueBillsForClient($clientId);
        $dueCreated = $this->generateDueBillsForClient($clientId);
        // A newly backfilled historical bill can already be past due, so mark
        // it in this same run instead of waiting for tomorrow's scheduler.
        $overdue += $this->markOverdueBillsForClient($clientId);
        $incomingCreated = $this->ensureIncomingBillForClient($clientId);

        return compact('overdue', 'dueCreated', 'incomingCreated');
    }

    /** Synchronize billing for all active clients for the scheduled daily run. */
    public function syncAllActiveClientBilling(): array
    {
        $summary = ['overdue' => 0, 'dueCreated' => 0, 'incomingCreated' => 0];
        DB::table('clients')->where('status', 'active')->orderBy('id')->pluck('id')->each(function (int $clientId) use (&$summary): void {
            $result = $this->syncClientBilling($clientId);
            foreach ($summary as $key => $value) {
                $summary[$key] += $result[$key];
            }
        });

        return $summary;
    }
    public function generateDueBills(): int
    {
        $created = 0;
        DB::table('clients')->where('status', 'active')->orderBy('id')->get()->each(function ($client) use (&$created) {
            $created += DB::transaction(function () use ($client): int {
                $latest = DB::table('bills')->where('client_id', $client->id)->lockForUpdate()->latest('billing_period_end')->first();
                $start = $latest ? CarbonImmutable::parse($latest->billing_period_end)->addDay() : CarbonImmutable::parse($client->installation_date ?: $client->created_at)->startOfDay();
                $createdForClient = 0;
                // Cap the loop as a safety net for corrupted historical dates.
                while ($start->lessThanOrEqualTo(today()) && $createdForClient < 120) {
                    $end = $start->addMonth()->subDay();
                    // One bill covers one monthly period and is due at the end
                    // of that same period.
                    $inserted = DB::table('bills')->insertOrIgnore([
                        'client_id' => $client->id,
                        'bill_number' => $this->billNumber((int) $client->id, $start),
                        'billing_period_start' => $start->toDateString(),
                        'billing_period_end' => $end->toDateString(),
                        'amount' => $client->monthly_rate,
                        'due_date' => $end->toDateString(),
                        'status' => 'pending',
                        'notes' => 'Automatically generated recurring monthly bill.',
                        'created_at' => now(),
                    ]);
                    if (! $inserted) break;
                    $createdForClient++;
                    $start = $end->addDay();
                }
                return $createdForClient;
            });
        });
        return $created;
    }

    /**
     * Generate due bills for a single client immediately.
     * Returns number of bills created for that client.
     */
    public function generateDueBillsForClient(int $clientId): int
    {
        $created = 0;
        $created += DB::transaction(function () use ($clientId): int {
            $createdForClient = 0;
            $client = DB::table('clients')->where('id', $clientId)->first();
            if (! $client) return 0;

            $latest = DB::table('bills')->where('client_id', $clientId)->lockForUpdate()->latest('billing_period_end')->first();
            $start = $latest ? CarbonImmutable::parse($latest->billing_period_end)->addDay() : CarbonImmutable::parse($client->installation_date ?: $client->created_at)->startOfDay();
            $safety = 0;
            while ($start->lessThanOrEqualTo(today()) && $safety < 120) {
                $end = $start->addMonth()->subDay();
                $inserted = DB::table('bills')->insertOrIgnore([
                    'client_id' => $clientId,
                    'bill_number' => $this->billNumber($clientId, $start),
                    'billing_period_start' => $start->toDateString(),
                    'billing_period_end' => $end->toDateString(),
                    'amount' => $client->monthly_rate,
                    'due_date' => $end->toDateString(),
                    'status' => 'pending',
                    'notes' => 'Automatically generated recurring monthly bill.',
                    'created_at' => now(),
                ]);
                if (! $inserted) break;
                $createdForClient++;
                $start = $end->addDay();
                $safety++;
            }
            return $createdForClient;
        });
        return $created;
    }

    /**
     * Generate the next billing period bill for a client regardless of current date.
     * Returns 1 if a bill was created, 0 if already exists or client missing.
     */
    public function generateNextBillForClient(int $clientId): int
    {
        $client = DB::table('clients')->where('id', $clientId)->first();
        if (! $client) return 0;

        $latest = DB::table('bills')->where('client_id', $clientId)->latest('billing_period_end')->first();
        $start = $latest ? CarbonImmutable::parse($latest->billing_period_end)->addDay() : CarbonImmutable::parse($client->installation_date ?: $client->created_at)->startOfDay();
        $end = $start->addMonth()->subDay();

        $inserted = DB::table('bills')->insertOrIgnore([
            'client_id' => $clientId,
            'bill_number' => $this->billNumber($clientId, $start),
            'billing_period_start' => $start->toDateString(),
            'billing_period_end' => $end->toDateString(),
            'amount' => $client->monthly_rate,
            'due_date' => $end->toDateString(),
            'status' => 'pending',
            'notes' => 'Automatically generated next recurring monthly bill.',
            'created_at' => now(),
        ]);

        return $inserted ? 1 : 0;
    }

    /**
     * Add exactly one future billing period after the latest bill. It becomes
     * the Incoming bill on Client Records. A bill that is already current or
     * past is not treated as incoming.
     */
    public function ensureIncomingBillForClient(int $clientId): int
    {
        return DB::transaction(function () use ($clientId): int {
            $client = DB::table('clients')->where('id', $clientId)->where('status', 'active')->lockForUpdate()->first();
            if (! $client) return 0;

            // Keep exactly one advance bill. If an upcoming period already
            // exists, it is the Incoming bill and another one must not be made.
            $hasIncoming = DB::table('bills')
                ->where('client_id', $clientId)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereDate('billing_period_start', '>', today()->toDateString())
                ->lockForUpdate()
                ->exists();
            if ($hasIncoming) return 0;

            $latest = DB::table('bills')->where('client_id', $clientId)->lockForUpdate()->latest('billing_period_end')->first();
            if (! $latest) return 0;

            $start = CarbonImmutable::parse($latest->billing_period_end)->addDay();
            if (! $start->isFuture()) return 0;

            $end = $start->addMonth()->subDay();
            return DB::table('bills')->insertOrIgnore([
                'client_id' => $clientId,
                'bill_number' => $this->billNumber($clientId, $start),
                'billing_period_start' => $start->toDateString(),
                'billing_period_end' => $end->toDateString(),
                'amount' => $client->monthly_rate,
                'due_date' => $end->toDateString(),
                'status' => 'pending',
                'notes' => 'Automatically generated upcoming monthly bill.',
                'created_at' => now(),
            ]) ? 1 : 0;
        });
    }

    /**
     * Ensure the billing period immediately following a paid-or-partially-paid
     * bill exists. Unlike generateNextBillForClient(), this is safe to call on
     * every payment because it checks the specific following period first.
     */
    public function ensureFollowingBillForBill(int $clientId, int $billId): int
    {
        return DB::transaction(function () use ($clientId, $billId): int {
            $client = DB::table('clients')->where('id', $clientId)->lockForUpdate()->first();
            $bill = DB::table('bills')->where('id', $billId)->where('client_id', $clientId)->lockForUpdate()->first();
            if (! $client || ! $bill) return 0;

            $start = CarbonImmutable::parse($bill->billing_period_end)->addDay();
            $end = $start->addMonth()->subDay();

            $exists = DB::table('bills')
                ->where('client_id', $clientId)
                ->whereDate('billing_period_start', $start->toDateString())
                ->exists();
            if ($exists) return 0;

            DB::table('bills')->insert([
                'client_id' => $clientId,
                'bill_number' => $this->billNumber($clientId, $start),
                'billing_period_start' => $start->toDateString(),
                'billing_period_end' => $end->toDateString(),
                'amount' => $client->monthly_rate,
                'due_date' => $end->toDateString(),
                'status' => 'pending',
                'notes' => 'Automatically generated following monthly bill.',
                'created_at' => now(),
            ]);

            return 1;
        });
    }

    public function markOverdueBills(): int
    {
        // A bill becomes overdue on the day after its monthly due date.
        $threshold = today()->toDateString();
        return DB::table('bills')->where('status', 'pending')->whereDate('due_date', '<', $threshold)->update(['status' => 'overdue']);
    }

    private function markOverdueBillsForClient(int $clientId): int
    {
        return DB::table('bills')
            ->where('client_id', $clientId)
            ->where('status', 'pending')
            ->whereDate('due_date', '<', today()->toDateString())
            ->update(['status' => 'overdue']);
    }

    private function billNumber(int $clientId, CarbonImmutable|string $periodStart): string
    {
        $start = $periodStart instanceof CarbonImmutable ? $periodStart : CarbonImmutable::parse($periodStart);
        return 'BILL-' . $start->format('Ymd') . '-' . str_pad((string) $clientId, 5, '0', STR_PAD_LEFT);
    }
}
