<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS wifi_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(100) NOT NULL,
    speed VARCHAR(50),
    monthly_price DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO wifi_rates (plan_name, speed, monthly_price, description) VALUES
('Basic Plan', '5 Mbps', 500.00, 'Entry-level WiFi for light browsing'),
('Standard Plan', '10 Mbps', 800.00, 'Perfect for streaming and work'),
('Premium Plan', '20 Mbps', 1200.00, 'High-speed for gaming and 4K'),
('Ultra Plan', '50 Mbps', 2000.00, 'Fiber-speed for heavy users'),
('Business Plan', '100 Mbps', 3500.00, 'Dedicated for business operations');

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS wifi_rates');
    }
};
