<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_requests', 'request_group_id')) {
            Schema::table('inventory_requests', function (Blueprint $table): void {
                $table->uuid('request_group_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_requests', 'request_group_id')) {
            Schema::table('inventory_requests', function (Blueprint $table): void {
                $table->dropIndex(['request_group_id']);
                $table->dropColumn('request_group_id');
            });
        }
    }
};
