<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything the branches and the employee app need to look lived in, in the order it depends on itself: the technicians (one in
 * every barangay) and their attendance, then the stock rooms and NAP boxes with the clients connected, then the requests the
 * technicians filed for that stock, then the weekly payroll of that attendance with its deductions.
 * Each part skips what it finds already there, so running this again changes nothing.
 *
 *   php artisan db:seed --class=BranchFieldDataSeeder
 */
class BranchFieldDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BranchStaffAttendanceSeeder::class,
            BranchStockNapSeeder::class,
            EmployeeInventoryRequestsSeeder::class,
            BranchPayrollSeeder::class,
        ]);
    }
}
