<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS report_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_name VARCHAR(100) NOT NULL,
    report_type ENUM('daily','monthly','yearly','custom') NOT NULL,
    date_from DATE,
    date_to DATE,
    filters TEXT,
    generated_by INT,
    record_count INT NOT NULL DEFAULT 0,
    status VARCHAR(50),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS report_history');
    }
};
