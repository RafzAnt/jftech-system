<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_code VARCHAR(50) NOT NULL UNIQUE,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(50),
    department VARCHAR(50),
    contact_number VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    date_hired DATE,
    daily_rate DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS employees');
    }
};
