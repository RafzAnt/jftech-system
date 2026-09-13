<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance MODIFY status_morning ENUM('present', 'absent', 'late', 'halfday') NULL DEFAULT NULL");
        DB::statement("ALTER TABLE attendance MODIFY status_afternoon ENUM('present', 'absent', 'late', 'halfday') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE attendance SET status_morning = 'absent' WHERE status_morning IS NULL");
        DB::statement("UPDATE attendance SET status_afternoon = 'absent' WHERE status_afternoon IS NULL");
        DB::statement("ALTER TABLE attendance MODIFY status_morning ENUM('present', 'absent', 'late', 'halfday') NOT NULL DEFAULT 'absent'");
        DB::statement("ALTER TABLE attendance MODIFY status_afternoon ENUM('present', 'absent', 'late', 'halfday') NOT NULL DEFAULT 'absent'");
    }
};
