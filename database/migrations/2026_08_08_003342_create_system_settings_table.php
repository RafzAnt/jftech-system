<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_label VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_label) VALUES
('company_name', 'JFTECH WiFi Solutions', 'Company Name'),
('company_address', 'Del Carmen Sur, Balilihan, Bohol', 'Company Address'),
('company_contact', '09123456789', 'Contact Number'),
('company_email', 'jftechitsolutions@gmail.com', 'Email Address'),
('penalty_rate', '50.00', 'Overdue Penalty (PHP)'),
('tax_rate', '0.00', 'Tax Rate (%)'),
('currency', 'PHP', 'Currency'),
('timezone', 'Asia/Manila', 'Timezone'),
('qr_morning_start', '08:00:00', 'Morning QR Start Time'),
('qr_morning_end', '12:00:00', 'Morning QR End Time'),
('qr_afternoon_start', '13:00:00', 'Afternoon QR Start Time'),
('qr_afternoon_end', '17:00:00', 'Afternoon QR End Time'),
('late_threshold_minutes', '30', 'Late Threshold (minutes)'),
('morning_deadline', '08:30:00', 'Morning Attendance Deadline'),
('afternoon_deadline', '13:30:00', 'Afternoon Attendance Deadline');

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS system_settings');
    }
};
