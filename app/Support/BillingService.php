<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Creates missing recurring bills without duplicating an existing billing period. */
class BillingService
{
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
                    // Due date policy: due = billing_period_start + 30 days
                    $inserted = DB::table('bills')->insertOrIgnore([
                        'client_id' => $client->id,
                        'bill_number' => 'BILL-' . $start->format('Ymd') . '-' . str_pad((string) $client->id, 5, '0', STR_PAD_LEFT),
                        'billing_period_start' => $start->toDateString(),
                        'billing_period_end' => $end->toDateString(),
                        'amount' => $client->monthly_rate,
                        'due_date' => $start->addDays(30)->toDateString(),
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
                    'bill_number' => 'BILL-' . $start->format('Ymd') . '-' . str_pad((string) $clientId, 5, '0', STR_PAD_LEFT),
                    'billing_period_start' => $start->toDateString(),
                    'billing_period_end' => $end->toDateString(),
                    'amount' => $client->monthly_rate,
                    'due_date' => $start->addDays(30)->toDateString(),
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
            'bill_number' => 'BILL-' . $start->format('Ymd') . '-' . str_pad((string) $clientId, 5, '0', STR_PAD_LEFT),
            'billing_period_start' => $start->toDateString(),
            'billing_period_end' => $end->toDateString(),
            'amount' => $client->monthly_rate,
            'due_date' => $start->addDays(30)->toDateString(),
            'status' => 'pending',
            'notes' => 'Automatically generated next recurring monthly bill.',
            'created_at' => now(),
        ]);

        return $inserted ? 1 : 0;
    }

    public function markOverdueBills(): int
    {
        // Mark bills as overdue only if they remain unpaid 5 days past the due date.
        $threshold = today()->subDays(5)->toDateString();
        return DB::table('bills')->where('status', 'pending')->whereDate('due_date', '<', $threshold)->update(['status' => 'overdue']);
    }
}
