<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'marker_color']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->default(0);
            $table->decimal('longitude', 11, 8)->default(0);
            $table->string('marker_color', 20)->default('#00BFFF');
        });
    }
};
