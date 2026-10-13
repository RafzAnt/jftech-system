<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The branch an employee works at. Existing employees stay with the main office.
        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'branch_name')) {
                $table->string('branch_name', 100)->default('Balilihan');
            }
        });

        // The branch recorded on the day itself, so history stays correct if an employee is moved later.
        Schema::table('attendance', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance', 'branch_name')) {
                $table->string('branch_name', 100)->nullable();
            }
        });

        DB::table('employees')->whereNull('branch_name')->update(['branch_name' => 'Balilihan']);
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance', 'branch_name')) {
                $table->dropColumn('branch_name');
            }
        });

        Schema::table('employees', function (Blueprint $table): void {
            if (Schema::hasColumn('employees', 'branch_name')) {
                $table->dropColumn('branch_name');
            }
        });
    }
};
