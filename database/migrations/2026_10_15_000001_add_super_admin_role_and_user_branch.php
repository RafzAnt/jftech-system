<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The branch a branch admin works for. Super admins and employee accounts leave it empty.
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'branch_name')) {
                $table->string('branch_name', 100)->nullable();
            }
        });

        // super_admin sees every branch; admin is now a branch admin limited to users.branch_name.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin', 'admin', 'employee') DEFAULT 'employee'");
        }

        // Every admin that exists today already had access to everything, so they become super admins.
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'employee') DEFAULT 'employee'");
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'branch_name')) {
                $table->dropColumn('branch_name');
            }
        });
    }
};
