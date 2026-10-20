<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory and NAP boxes work as one: a stock transaction now says which employee handled it and, when it belongs to a job,
 * which client and NAP box it was for. NAP boxes themselves are stock (the "NAP Box" category); installing one takes a unit
 * out of the branch's stock and records the NAP box on that transaction.
 *
 * Existing JFTECH tables use signed INT primary keys, so the columns that point at them are plain integers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_stock_movements', 'employee_id')) {
                $table->integer('employee_id')->nullable();
            }
            if (! Schema::hasColumn('inventory_stock_movements', 'client_id')) {
                $table->integer('client_id')->nullable();
            }
            if (! Schema::hasColumn('inventory_stock_movements', 'nap_box_id')) {
                $table->unsignedBigInteger('nap_box_id')->nullable();
                $table->index('nap_box_id', 'inventory_stock_movements_nap_box_index');
            }
            if (! Schema::hasColumn('inventory_stock_movements', 'work_request_id')) {
                $table->unsignedBigInteger('work_request_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_stock_movements', 'nap_box_id')) {
                $table->dropIndex('inventory_stock_movements_nap_box_index');
            }
            foreach (['employee_id', 'client_id', 'nap_box_id', 'work_request_id'] as $column) {
                if (Schema::hasColumn('inventory_stock_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
