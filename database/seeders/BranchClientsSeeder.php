<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample clients for every barangay of every branch: two in each, with a plan and the first bill, the way the Clients page registers
 * one. The codes are JFT-DEMO-<BRANCH>-B<barangay number>-<1 or 2> and the e-mails end in @demo.jftech.local, so they are easy to
 * recognise.
 *
 * A barangay is topped up to two sample clients: one that exists (also from an earlier run of this seeder) counts, so running it again
 * adds nothing and never goes above two. Clients that are not samples are left alone and do not count.
 *
 *   php artisan db:seed --class=BranchClientsSeeder
 *
 * To remove them: delete the bills and clients whose client_code starts with JFT-DEMO- and has a branch in it (JFT-DEMO-LOO-...).
 */
class BranchClientsSeeder extends Seeder
{
    public const CLIENTS_PER_BARANGAY = 2;

    private const FIRST_NAMES = [
        'Ana', 'Ben', 'Carla', 'Dante', 'Elena', 'Felix', 'Gina', 'Hector', 'Irene', 'Jun',
        'Karen', 'Luis', 'Maria', 'Nestor', 'Olive', 'Pedro', 'Rosa', 'Samuel', 'Teresa', 'Victor',
        'Alma', 'Bernard', 'Cynthia', 'Domingo', 'Edna', 'Fidel', 'Grace', 'Henry', 'Imelda', 'Joel',
        'Lourdes', 'Mario', 'Nena', 'Oscar', 'Perla', 'Ramon', 'Sheila', 'Tomas', 'Ursula', 'Wilfredo',
    ];

    private const LAST_NAMES = [
        'Abad', 'Bacalso', 'Cabigas', 'Dumaluan', 'Escobar', 'Fuentes', 'Galicia', 'Hontiveros', 'Inting', 'Jumawid',
        'Lazaro', 'Magallanes', 'Namocatcat', 'Orbiso', 'Pacaña', 'Quirog', 'Redoble', 'Salinas', 'Tirol', 'Uy',
        'Aniñon', 'Boiser', 'Cutamora', 'Digal', 'Estoque', 'Flores', 'Gonzaga', 'Hubahib', 'Itable', 'Jabines',
        'Lagumbay', 'Maglasang', 'Nistal', 'Olaer', 'Pomoy', 'Rubio', 'Sumaylo', 'Toledo', 'Uyvico', 'Valmoria',
    ];

    public function run(): void
    {
        $plans = DB::table('internet_plans')->where('status', 'active')->where('archived', 0)->orderBy('monthly_price')->limit(3)->get();
        if ($plans->isEmpty()) {
            throw new \RuntimeException('Create an active internet plan before loading sample clients.');
        }

        $branches = array_keys(config('bohol_branches', []));
        $barangaysOf = config('bohol_barangays', []);
        $today = Carbon::today();

        // What is there already: the sample clients per barangay (they count towards the two), the codes, and every name in use.
        $samples = DB::table('clients')->where('client_code', 'like', 'JFT-DEMO-%-%')->get(['client_code', 'branch_name', 'registered_barangay_name']);
        $have = [];
        $codes = [];
        foreach ($samples as $sample) {
            $key = mb_strtolower($sample->branch_name . '|' . $sample->registered_barangay_name);
            $have[$key] = ($have[$key] ?? 0) + 1;
            $codes[$sample->client_code] = true;
        }
        $usedNames = array_flip(DB::table('clients')->pluck('full_name')->all());
        $names = $this->namePool();
        $nextName = 0;
        $balilihanIds = DB::table('barangays')->pluck('id', 'barangay_name')->all();

        $clients = [];
        $sequence = 0;
        foreach ($branches as $branch) {
            $prefix = strtoupper(substr($branch, 0, 3));
            foreach (array_values((array) ($barangaysOf[$branch] ?? [])) as $barangayIndex => $barangay) {
                $missing = self::CLIENTS_PER_BARANGAY - ($have[mb_strtolower($branch . '|' . $barangay)] ?? 0);

                for ($slot = 1; $slot <= self::CLIENTS_PER_BARANGAY && $missing > 0; $slot++) {
                    $sequence++;
                    $code = sprintf('JFT-DEMO-%s-B%03d-%d', $prefix, $barangayIndex + 1, $slot);
                    if (isset($codes[$code])) {
                        continue;
                    }
                    while (isset($usedNames[$names[$nextName] ?? null])) {
                        $nextName++;
                    }
                    $name = $names[$nextName] ?? throw new \RuntimeException('Not enough sample names for this many clients.');
                    $usedNames[$name] = true;
                    $plan = $plans[$sequence % $plans->count()];
                    $installed = $today->copy()->subDays(5 + (($sequence * 11) % 51));

                    $clients[] = [
                        'code' => $code,
                        'row' => [
                            'client_code' => $code,
                            'full_name' => $name,
                            'contact_number' => sprintf('0917%07d', 2000 + $sequence),
                            'email' => strtolower(str_replace(' ', '.', $name)) . '@demo.jftech.local',
                            'branch_name' => $branch,
                            'registered_barangay_name' => $barangay,
                            // Only Balilihan's barangays are also kept in the barangays table.
                            'barangay_id' => $branch === 'Balilihan' ? ($balilihanIds[$barangay] ?? null) : null,
                            'exact_address' => 'Purok ' . (1 + ($sequence % 7)) . ", {$barangay}, {$branch}, Bohol",
                            'wifi_plan' => $plan->plan_name,
                            'internet_plan_id' => $plan->id,
                            'monthly_rate' => $plan->monthly_price,
                            'installation_fee' => $plan->installation_fee,
                            'installation_date' => $installed->toDateString(),
                            // A few clients are not active, so every list has something to filter.
                            'status' => $sequence % 15 === 7 ? 'suspended' : 'active',
                            'archived' => 0,
                        ],
                        'plan' => $plan,
                        'installed' => $installed,
                    ];
                    $missing--;
                }
            }
        }

        DB::transaction(function () use ($clients, $today): void {
            foreach (array_chunk($clients, 100) as $chunk) {
                DB::table('clients')->insert(array_column($chunk, 'row'));

                $ids = DB::table('clients')->whereIn('client_code', array_column($chunk, 'code'))->pluck('id', 'client_code');
                $bills = [];
                foreach ($chunk as $client) {
                    // The first bill, from the installation day for 30 days; it is overdue once that has passed.
                    $due = $client['installed']->copy()->addDays(30);
                    $bills[] = [
                        'client_id' => $ids[$client['code']],
                        'bill_number' => 'BILL-' . $client['code'],
                        'billing_period_start' => $client['installed']->toDateString(),
                        'billing_period_end' => $due->toDateString(),
                        'amount' => $client['plan']->monthly_price,
                        'due_date' => $due->toDateString(),
                        'status' => $due->lt($today) ? 'overdue' : 'pending',
                        'notes' => 'Sample client billing.',
                    ];
                }
                DB::table('bills')->insert($bills);
            }
        });
    }

    /**
     * Unique full names, the same every time: every first name with every last name, shuffled with a fixed seed.
     *
     * @return array<int, string>
     */
    private function namePool(): array
    {
        $all = [];
        foreach (self::FIRST_NAMES as $first) {
            foreach (self::LAST_NAMES as $last) {
                $all[] = "{$first} {$last}";
            }
        }
        mt_srand(2026);
        shuffle($all);
        mt_srand();

        return $all;
    }
}
