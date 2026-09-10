<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    quantity INT DEFAULT 0,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    supplier VARCHAR(100),
    reorder_level INT DEFAULT 5,
    status ENUM('active', 'inactive') DEFAULT 'active',
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS inventory');
    }
};
