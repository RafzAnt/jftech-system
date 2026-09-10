<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory', 'sku')) {
            DB::statement("ALTER TABLE inventory ADD COLUMN sku VARCHAR(100) AFTER item_name");
        }

        $rows = DB::table('inventory')->whereNull('sku')->get();
        foreach ($rows as $row) {
            DB::table('inventory')
                ->where('id', $row->id)
                ->update(['sku' => 'SKU-' . str_pad($row->id, 4, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory', 'sku')) {
            DB::statement("ALTER TABLE inventory DROP COLUMN sku");
        }
    }
};
