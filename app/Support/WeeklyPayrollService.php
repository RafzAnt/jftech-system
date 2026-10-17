<?php

namespace App\Support;

use Carbon\Carbon;

class WeeklyPayrollService
{
    public function __construct(private readonly PayrollService $payroll)
    {
    }

    /**
     * Generate one processed payroll for every active employee who worked during the Monday-Sunday week that contains the
     * supplied date. The pay follows from the daily rate and the attendance (see {@see PayrollService}). Payroll that already
     * exists for the same employee and period is retained and never overwritten.
     */
    public function generateForWeek(?Carbon $date = null): int
    {
        return $this->payroll->generateForWeek($date)['created'];
    }
}
