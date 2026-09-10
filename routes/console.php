<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\BillingService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:generate', function (BillingService $billing) {
    $overdue = $billing->markOverdueBills();
    $created = $billing->generateDueBills();
    $this->info("Created {$created} bill(s); marked {$overdue} overdue.");
})->purpose('Generate due recurring client bills and mark overdue bills');

Schedule::command('billing:generate')->dailyAt('00:05')->withoutOverlapping();
