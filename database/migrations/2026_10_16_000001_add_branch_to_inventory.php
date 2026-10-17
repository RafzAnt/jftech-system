<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_UNIQUE = 'inventory_item_name_category_unique';
    private const NEW_UNIQUE = 'inventory_branch_item_category_unique';

    public function up(): void
    {
        // Every branch keeps its own stock. Items that already exist stay with the main office.
        if (! Schema::hasColumn('inventory', 'branch_name')) {
            Schema::table('inventory', function ($table) {
                $table->string('branch_name', 100)->default('Balilihan');
            });
        }
        DB::table('inventory')->whereNull('branch_name')->update(['branch_name' => 'Balilihan']);

        // Two branches may stock the same item, so the name is only unique within a branch.
        Schema::table('inventory', function ($table) {
            if (Schema::hasIndex('inventory', self::OLD_UNIQUE)) {
                $table->dropUnique(self::OLD_UNIQUE);
            }
            if (! Schema::hasIndex('inventory', self::NEW_UNIQUE)) {
                $table->unique(['branch_name', 'item_name', 'category'], self::NEW_UNIQUE);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function ($table) {
            if (Schema::hasIndex('inventory', self::NEW_UNIQUE)) {
                $table->dropUnique(self::NEW_UNIQUE);
            }
        });
        Schema::table('inventory', function ($table) {
            if (! Schema::hasIndex('inventory', self::OLD_UNIQUE)) {
                $table->unique(['item_name', 'category'], self::OLD_UNIQUE);
            }
            $table->dropColumn('branch_name');
        });
    }
};
