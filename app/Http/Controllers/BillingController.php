<?php

namespace App\Http\Controllers;

use App\Support\BillingService;
use Illuminate\Http\RedirectResponse;

class BillingController extends Controller
{
    public function generate(BillingService $billing): RedirectResponse
    {
        $overdue = $billing->markOverdueBills();
        $created = $billing->generateDueBills();
        return back()->with('success', "Billing update complete: {$created} new bill(s) generated and {$overdue} bill(s) marked overdue.");
    }
}
