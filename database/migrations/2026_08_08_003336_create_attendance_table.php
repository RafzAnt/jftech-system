<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    morning_time_in TIME,
    morning_time_out TIME,
    afternoon_time_in TIME,
    afternoon_time_out TIME,
    status_morning ENUM('present', 'absent', 'late', 'halfday') DEFAULT 'absent',
    status_afternoon ENUM('present', 'absent', 'late', 'halfday') DEFAULT 'absent',
    qr_code_used VARCHAR(100),
    total_hours DECIMAL(5,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS attendance');
    }
};
