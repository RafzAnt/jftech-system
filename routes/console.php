<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\BillingService;
use App\Support\WeeklyPayrollService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:generate', function (BillingService $billing) {
    $result = $billing->syncAllActiveClientBilling();
    $this->info("Created {$result['dueCreated']} due bill(s), {$result['incomingCreated']} incoming bill(s), and marked {$result['overdue']} overdue.");
})->purpose('Synchronize recurring, incoming, and overdue bills for active clients');

Artisan::command('billing:normalize', function (BillingService $billing) {
    $updated = $billing->normalizeRecurringBills();
    $this->info("Normalized {$updated} legacy bill record(s) to the monthly billing policy.");
})->purpose('Normalize legacy bill numbers, monthly due dates, and overdue statuses');

Schedule::command('billing:generate')->dailyAt('00:05')->withoutOverlapping();

Artisan::command('payroll:generate-weekly', function (WeeklyPayrollService $payroll) {
    $created = $payroll->generateForWeek();
    $this->info("Generated {$created} weekly payroll record(s).");
})->purpose('Generate attendance-based payroll for all active employees for the current Monday-Sunday week');

// Runs after Sunday attendance is complete. Configure the server cron to run
// `php artisan schedule:run` every minute for this automatic task to execute.
Schedule::command('payroll:generate-weekly')
    ->weeklyOn(0, '23:55')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
