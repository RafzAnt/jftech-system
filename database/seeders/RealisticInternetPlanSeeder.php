<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Publishes JFTECH's approved public internet-plan catalog. */
class RealisticInternetPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['plan_name' => 'Starter', 'speed_mbps' => 100, 'monthly_price' => 999.00, 'installation_fee' => 0.00, 'description' => 'Up to 100 Mbps — ideal for everyday browsing, messaging, and streaming.'],
            ['plan_name' => 'Essential', 'speed_mbps' => 150, 'monthly_price' => 1299.00, 'installation_fee' => 0.00, 'description' => 'Up to 150 Mbps — for connected homes, video calls, and HD streaming.'],
            ['plan_name' => 'Premium', 'speed_mbps' => 200, 'monthly_price' => 1499.00, 'installation_fee' => 0.00, 'description' => 'Up to 200 Mbps — for busy households with multiple devices.'],
            ['plan_name' => 'Professional', 'speed_mbps' => 300, 'monthly_price' => 1999.00, 'installation_fee' => 0.00, 'description' => 'Up to 300 Mbps — for demanding households and small teams.'],
            ['plan_name' => 'Pro', 'speed_mbps' => 400, 'monthly_price' => 2499.00, 'installation_fee' => 0.00, 'description' => 'Up to 400 Mbps — for power users and high-bandwidth work.'],
            ['plan_name' => 'Ultimate', 'speed_mbps' => 500, 'monthly_price' => 2999.00, 'installation_fee' => 0.00, 'description' => 'Up to 500 Mbps — for the highest-performance connected experience.'],
        ];
        $renamedLegacyPlans = [
            'Starter' => 'Fiber Starter 30',
            'Essential' => 'Fiber Family 75',
            'Premium' => 'Fiber Pro 150',
        ];

        DB::transaction(function () use ($plans, $renamedLegacyPlans): void {
            foreach ($plans as $data) {
                $plan = DB::table('internet_plans')->where('plan_name', $data['plan_name'])->first();
                if (! $plan && isset($renamedLegacyPlans[$data['plan_name']])) {
                    // These old catalog rows have no linked clients, so safely
                    // reuse them instead of leaving duplicate public offers.
                    $plan = DB::table('internet_plans')->where('plan_name', $renamedLegacyPlans[$data['plan_name']])->first();
                }
                if ($plan) {
                    $priceChanged = (float) $plan->monthly_price !== (float) $data['monthly_price']
                        || (float) $plan->installation_fee !== (float) $data['installation_fee'];
                    DB::table('internet_plans')->where('id', $plan->id)->update($data + ['status' => 'active', 'archived' => 0, 'updated_at' => now()]);
                    if ($priceChanged) {
                        DB::table('internet_plan_price_histories')->insert([
                            'internet_plan_id' => $plan->id,
                            'previous_monthly_price' => $plan->monthly_price,
                            'new_monthly_price' => $data['monthly_price'],
                            'previous_installation_fee' => $plan->installation_fee,
                            'new_installation_fee' => $data['installation_fee'],
                            'change_type' => 'immediate',
                            'effective_on' => now()->toDateString(),
                            'reason' => 'Updated to approved JFTECH public plan catalog.',
                            'changed_by' => null,
                            'created_at' => now(),
                        ]);
                    }
                    continue;
                }

                $planId = DB::table('internet_plans')->insertGetId($data + ['status' => 'active', 'archived' => 0, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('internet_plan_price_histories')->insert([
                    'internet_plan_id' => $planId,
                    'previous_monthly_price' => null,
                    'new_monthly_price' => $data['monthly_price'],
                    'previous_installation_fee' => null,
                    'new_installation_fee' => $data['installation_fee'],
                    'change_type' => 'created',
                    'effective_on' => now()->toDateString(),
                    'reason' => 'Initial live plan catalog entry.',
                    'changed_by' => null,
                    'created_at' => now(),
                ]);
            }

            // Old generic offers are retained only as archived records so
            // linked clients keep their historical plan/rate snapshots. They
            // are no longer selectable for new client registrations.
            DB::table('internet_plans')
                ->whereNotIn('plan_name', array_column($plans, 'plan_name'))
                ->update(['status' => 'inactive', 'archived' => 1, 'updated_at' => now()]);

            // The approved migration maps every existing client to Starter.
            // Paid bills are historical records and remain untouched; only
            // unpaid/current/incoming bills receive the new monthly amount.
            $starter = DB::table('internet_plans')->where('plan_name', 'Starter')->first();
            $clients = DB::table('clients')->lockForUpdate()->get();
            foreach ($clients as $client) {
                if ((int) $client->internet_plan_id === (int) $starter->id && (float) $client->monthly_rate === (float) $starter->monthly_price) {
                    continue;
                }

                DB::table('client_plan_change_histories')->insert([
                    'client_id' => $client->id,
                    'previous_internet_plan_id' => $client->internet_plan_id,
                    'previous_plan_name' => $client->wifi_plan,
                    'previous_monthly_rate' => $client->monthly_rate,
                    'new_internet_plan_id' => $starter->id,
                    'new_plan_name' => $starter->plan_name,
                    'new_monthly_rate' => $starter->monthly_price,
                    'reason' => 'Migrated to the approved Starter plan catalog rate.',
                    'changed_by' => null,
                    'created_at' => now(),
                ]);

                DB::table('clients')->where('id', $client->id)->update([
                    'internet_plan_id' => $starter->id,
                    'wifi_plan' => $starter->plan_name,
                    'monthly_rate' => $starter->monthly_price,
                    'installation_fee' => $starter->installation_fee,
                    'updated_at' => now(),
                ]);
                DB::table('bills')->where('client_id', $client->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->update(['amount' => $starter->monthly_price]);
            }
        });
    }
}
