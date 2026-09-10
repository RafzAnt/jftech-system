<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('login_history', 'error_message')) {
            Schema::table('login_history', fn (Blueprint $table) => $table->string('error_message', 255)->nullable()->after('status'));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('login_history', 'error_message')) {
            Schema::table('login_history', fn (Blueprint $table) => $table->dropColumn('error_message'));
        }
    }
};
