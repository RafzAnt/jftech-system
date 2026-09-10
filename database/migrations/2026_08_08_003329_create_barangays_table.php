<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS barangays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_name VARCHAR(100) NOT NULL UNIQUE,
    latitude DECIMAL(10,8) NOT NULL DEFAULT 0,
    longitude DECIMAL(11,8) NOT NULL DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- All 31 Barangays of Balilihan, Bohol
INSERT IGNORE INTO barangays (barangay_name, latitude, longitude) VALUES
('Baucan Norte', 9.738611, 123.966667),
('Baucan Sur', 9.735000, 123.970000),
('Boctol', 9.758333, 123.975000),
('Boyog Norte', 9.720000, 123.960000),
('Boyog Proper', 9.718000, 123.962500),
('Boyog Sur', 9.715000, 123.965000),
('Cabudburan', 9.745000, 123.980000),
('Candasig', 9.752000, 123.985000),
('Cantalid', 9.730000, 123.990000),
('Cantomimbo', 9.765000, 123.970000),
('Cogon', 9.740000, 123.955000),
('Datag Norte', 9.725000, 123.975000),
('Datag Sur', 9.722000, 123.978000),
('Del Carmen Este', 9.755000, 123.960000),
('Del Carmen Norte', 9.758000, 123.963000),
('Del Carmen Sur', 9.752000, 123.958000),
('Del Carmen Weste', 9.750000, 123.955000),
('Del Rosario', 9.760000, 123.980000),
('Dorol', 9.735000, 123.985000),
('Haguilanan Grande', 9.745000, 123.990000),
('Hanopol Este', 9.768000, 123.975000),
('Hanopol Norte', 9.770000, 123.970000),
('Hanopol Weste', 9.765000, 123.965000),
('Magsija', 9.728000, 123.968000),
('Maslog', 9.742000, 123.975000),
('Sagasa', 9.738000, 123.982000),
('Sal-ing', 9.732000, 123.978000),
('San Isidro', 9.748000, 123.972000),
('San Roque', 9.755000, 123.985000),
('Santa Cruz', 9.760000, 123.990000),
('Tagustusan', 9.718000, 123.970000);

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS barangays');
    }
};
