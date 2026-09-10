<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('bills', fn (Blueprint $table) => $table->unique(['client_id', 'billing_period_start'], 'bills_client_period_unique')); }
    public function down(): void { Schema::table('bills', fn (Blueprint $table) => $table->dropUnique('bills_client_period_unique')); }
};
