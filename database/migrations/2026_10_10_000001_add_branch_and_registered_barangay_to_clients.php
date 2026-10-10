<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('clients', 'branch_name')) {
                $table->string('branch_name', 100)->default('Balilihan');
            }

            if (! Schema::hasColumn('clients', 'registered_barangay_name')) {
                $table->string('registered_barangay_name', 100)->nullable();
            }
        });

        DB::table('clients')->whereNull('branch_name')->update(['branch_name' => 'Balilihan']);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            if (Schema::hasColumn('clients', 'registered_barangay_name')) {
                $table->dropColumn('registered_barangay_name');
            }

            if (Schema::hasColumn('clients', 'branch_name')) {
                $table->dropColumn('branch_name');
            }
        });
    }
};
