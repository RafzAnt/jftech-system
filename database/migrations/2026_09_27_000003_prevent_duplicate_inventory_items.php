<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory', function ($table) {
            $table->unique(['item_name', 'category'], 'inventory_item_name_category_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function ($table) {
            $table->dropUnique('inventory_item_name_category_unique');
        });
    }
};
