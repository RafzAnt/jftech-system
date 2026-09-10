<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS price_histories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    price_id INT,
    previous_price DECIMAL(10,2),
    new_price DECIMAL(10,2),
    change_amount DECIMAL(10,2),
    change_percentage DECIMAL(8,2),
    previous_discount_type VARCHAR(20),
    previous_discount_value DECIMAL(10,2),
    new_discount_type VARCHAR(20),
    new_discount_value DECIMAL(10,2),
    reason TEXT,
    changed_by INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (price_id) REFERENCES prices(id) ON DELETE SET NULL,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS price_histories');
    }
};
