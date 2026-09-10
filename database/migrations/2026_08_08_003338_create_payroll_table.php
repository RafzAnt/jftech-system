<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS payroll (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    payroll_period_start DATE NOT NULL,
    payroll_period_end DATE NOT NULL,
    total_days_worked INT DEFAULT 0,
    total_hours DECIMAL(8,2) DEFAULT 0.00,
    daily_rate DECIMAL(10,2) NOT NULL,
    monthly_basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    late DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    undertime DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    absent DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    regular_holiday DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    special_holiday DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    night_differential DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    incentives DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    allowances DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    leave_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    rest_day_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    salary_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    other_earnings DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    gross_pay DECIMAL(10,2) DEFAULT 0.00,
    sss DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    philhealth DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pagibig DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    withholding_tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cash_advance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    salary_loan DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    hdmf_loan DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    other_deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deductions DECIMAL(10,2) DEFAULT 0.00,
    net_pay DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('draft', 'processed', 'paid') DEFAULT 'draft',
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS payroll');
    }
};
