<?php

namespace App\Http\Controllers;

use App\Models\InternetPlan;
use App\Models\InternetPlanPriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->applyDueScheduledPrices();
        $search = trim((string) $request->query('search', ''));
        $showArchived = $request->boolean('archived');
        $plans = InternetPlan::query()->where('archived', (int) $showArchived)
            ->when($search, fn ($query) => $query->where('plan_name', 'like', "%{$search}%"))
            ->withCount(['clients as active_clients_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('monthly_price')->get();
        $history = InternetPlanPriceHistory::query()->whereIn('internet_plan_id', $plans->pluck('id'))
            ->leftJoin('internet_plans', 'internet_plan_price_histories.internet_plan_id', '=', 'internet_plans.id')
            ->leftJoin('users', 'internet_plan_price_histories.changed_by', '=', 'users.id')
            ->select('internet_plan_price_histories.*', 'internet_plans.plan_name', 'users.full_name as changed_by_name')
            ->latest('internet_plan_price_histories.created_at')->limit(15)->get();

        return view('pages.price_management', [
            'plans' => $plans, 'history' => $history, 'search' => $search, 'showArchived' => $showArchived,
            'stats' => [
                'active' => InternetPlan::active()->count(),
                'scheduled' => InternetPlan::whereNotNull('price_effective_on')->where('archived', 0)->count(),
                'clients' => DB::table('clients')->where('status', 'active')->count(),
            ],
            'themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1,
            'priceCssVersion' => @filemtime(public_path('css/price_management.css')) ?: 1,
            'mainJsVersion' => @filemtime(public_path('js/jftech-main.js')) ?: 1,
        ]);
    }

    public function handlePost(Request $request)
    {
        $action = $request->string('action')->toString();
        if (in_array($action, ['archive', 'restore', 'toggle'], true)) return $this->availability($request, $action);
        if ($action === 'schedule') return $this->schedule($request);
        if (! in_array($action, ['create', 'update'], true)) return back()->with('error', 'Unknown price-management action.');

        $data = $request->validate([
            'plan_id' => [$action === 'update' ? 'required' : 'nullable', 'integer', 'exists:internet_plans,id'],
            'plan_name' => ['required', 'string', 'max:100', 'unique:internet_plans,plan_name,'.$request->integer('plan_id')],
            'speed_mbps' => ['required', 'integer', 'min:1', 'max:100000'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:1000'], 'status' => ['required', 'in:active,inactive'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($action === 'create') {
            $plan = InternetPlan::create(collect($data)->except('reason')->all() + ['installation_fee' => 0]);
            $this->history($plan, null, null, 'created', null, $data['reason'] ?? 'Plan created');
            return back()->with('success', 'Internet plan added. It is available for new client registrations when active.');
        }
        DB::transaction(function () use ($request, $data) {
            $plan = InternetPlan::where('archived', 0)->lockForUpdate()->findOrFail($data['plan_id']);
            $oldMonthly = $plan->monthly_price; $oldFee = $plan->installation_fee;
            $plan->update(collect($data)->except('reason')->all());
            if ((float) $oldMonthly !== (float) $plan->monthly_price) {
                $this->history($plan, $oldMonthly, $oldFee, 'immediate', now()->toDateString(), $data['reason'] ?? 'Price updated');
            }
        });
        return back()->with('success', 'Plan updated. The new rate applies to future registrations and plan changes; current client rates are preserved.');
    }

    private function schedule(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:internet_plans,id'],
            'scheduled_monthly_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'price_effective_on' => ['required', 'date', 'after:today'], 'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($data) {
            $plan = InternetPlan::where('archived', 0)->lockForUpdate()->findOrFail($data['plan_id']);
            $plan->update(['scheduled_monthly_price' => $data['scheduled_monthly_price'], 'scheduled_installation_fee' => $plan->installation_fee, 'price_effective_on' => $data['price_effective_on']]);
            $this->history($plan, $plan->monthly_price, $plan->installation_fee, 'scheduled', $data['price_effective_on'], $data['reason']);
        });
        return back()->with('success', 'Price change scheduled. It will apply automatically on the selected date.');
    }

    private function availability(Request $request, string $action)
    {
        $plan = InternetPlan::findOrFail($request->integer('plan_id'));
        if ($action === 'archive') { $plan->update(['archived' => true, 'status' => 'inactive']); return back()->with('success', 'Plan archived. Existing clients keep their recorded plan and rate.'); }
        if ($action === 'restore') { $plan->update(['archived' => false]); return back()->with('success', 'Plan restored. Activate it when it should be offered to new clients.'); }
        abort_if($plan->archived, 404);
        $plan->update(['status' => $plan->status === 'active' ? 'inactive' : 'active']);
        return back()->with('success', "Plan {$plan->fresh()->status}.");
    }

    /** Apply due changes whenever plan pricing is read by the application. */
    public function applyDueScheduledPrices(): void
    {
        DB::transaction(function () {
            InternetPlan::whereNotNull('price_effective_on')->whereDate('price_effective_on', '<=', today())->lockForUpdate()->get()->each(function (InternetPlan $plan) {
                $oldMonthly = $plan->monthly_price; $oldFee = $plan->installation_fee; $effectiveOn = $plan->price_effective_on?->toDateString();
                $plan->update(['monthly_price' => $plan->scheduled_monthly_price, 'installation_fee' => $plan->scheduled_installation_fee, 'scheduled_monthly_price' => null, 'scheduled_installation_fee' => null, 'price_effective_on' => null]);
                $this->history($plan, $oldMonthly, $oldFee, 'applied', $effectiveOn, 'Scheduled price applied automatically');
            });
        });
    }

    private function history(InternetPlan $plan, mixed $oldMonthly, mixed $oldFee, string $type, ?string $effectiveOn, ?string $reason): void
    {
        InternetPlanPriceHistory::create(['internet_plan_id' => $plan->id, 'previous_monthly_price' => $oldMonthly, 'new_monthly_price' => $type === 'scheduled' ? $plan->scheduled_monthly_price : $plan->monthly_price, 'previous_installation_fee' => $oldFee, 'new_installation_fee' => $type === 'scheduled' ? $plan->scheduled_installation_fee : $plan->installation_fee, 'change_type' => $type, 'effective_on' => $effectiveOn, 'reason' => $reason, 'changed_by' => auth()->id()]);
    }
}
