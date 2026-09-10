<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS price_audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    price_id INT,
    product_id INT,
    admin_id INT,
    action VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (price_id) REFERENCES prices(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS price_audit_logs');
    }
};
