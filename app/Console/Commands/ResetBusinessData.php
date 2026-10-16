<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empties the data every branch works with, so a new branch (and the super admin, who sees all
 * branches) starts with nothing. Never runs on its own: someone has to type the command.
 */
class ResetBusinessData extends Command
{
    protected $signature = 'jftech:reset-business-data {--force : Skip the typed confirmation}';

    protected $description = 'Delete clients, bills, payments, employees, attendance, payroll and employee accounts (fresh start)';

    /** Child tables first, so nothing points at a row that is already gone. */
    private const TABLES = [
        'payments' => 'Payments',
        'bills' => 'Bills',
        'client_plan_change_histories' => 'Client plan changes',
        'clients' => 'Clients',
        'inventory_requests' => 'Stock requests (made by employees)',
        'payroll' => 'Payroll',
        'attendance' => 'Attendance',
        'employees' => 'Employees',
    ];

    public function handle(): int
    {
        $counts = [];
        foreach (self::TABLES as $table => $label) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }
        $employeeAccounts = DB::table('users')->where('role', 'employee')->count();

        $this->warn('This permanently deletes:');
        $rows = [];
        foreach ($counts as $table => $count) {
            $rows[] = [self::TABLES[$table], $count];
        }
        $rows[] = ['Employee login accounts (role: employee)', $employeeAccounts];
        $this->table(['Data', 'Rows'], $rows);
        $this->line('Kept: super admin and branch admin accounts, internet plans, business settings, stock (inventory), QR codes and login history of the kept accounts.');
        $this->line('Back up the database first, for example: mysqldump -u root jftech_db > jftech-backup.sql');

        if (! $this->option('force') && $this->ask('Type RESET to permanently delete this data') !== 'RESET') {
            $this->error('Nothing was deleted.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($counts): void {
            foreach (array_keys($counts) as $table) {
                DB::table($table)->delete();
            }
            DB::table('users')->where('role', 'employee')->delete();
        });

        // Start numbering from 1 again (MySQL keeps counting after a DELETE).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (array_keys($counts) as $table) {
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }
        }

        $this->info('Done. Every branch and the super admin now start with no clients, employees or payments.');

        return self::SUCCESS;
    }
}
