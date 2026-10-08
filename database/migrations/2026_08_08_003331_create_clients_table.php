<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_code VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    barangay_id INT,
    exact_address TEXT,
    wifi_plan VARCHAR(50),
    monthly_rate DECIMAL(10,2) DEFAULT 0.00,
    installation_date DATE,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL
);

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS clients');
    }
};
