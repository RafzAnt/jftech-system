<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            // Existing JFTECH tables use INT primary keys (not Laravel BIGINT IDs).
            $table->unsignedInteger('inventory_id');
            $table->integer('quantity_change');
            $table->unsignedInteger('balance_after');
            $table->string('movement_type', 30);
            $table->text('remarks')->nullable();
            $table->unsignedInteger('performed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['inventory_id', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('inventory_stock_movements'); }
};
