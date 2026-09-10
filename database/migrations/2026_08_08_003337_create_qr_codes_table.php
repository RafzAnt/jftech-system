<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS qr_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    qr_code VARCHAR(255) NOT NULL UNIQUE,
    qr_type ENUM('morning', 'afternoon') NOT NULL,
    valid_date DATE NOT NULL,
    valid_from TIME NOT NULL,
    valid_until TIME NOT NULL,
    status ENUM('active', 'expired', 'disabled') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS qr_codes');
    }
};
