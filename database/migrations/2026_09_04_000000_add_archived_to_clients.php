<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'archived')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->tinyInteger('archived')->default(0)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'archived')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('archived');
            });
        }
    }
};
