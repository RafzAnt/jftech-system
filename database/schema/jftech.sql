-
-- =====================================================
-- 1. BARANGAYS TABLE (Balilihan, Bohol)
-- =====================================================
CREATE TABLE IF NOT EXISTS barangays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_name VARCHAR(100) NOT NULL UNIQUE,
    latitude DECIMAL(10,8) NOT NULL DEFAULT 0,
    longitude DECIMAL(11,8) NOT NULL DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- All 31 Barangays of Balilihan, Bohol
INSERT INTO barangays (barangay_name, latitude, longitude) VALUES
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

-- =====================================================
-- 2. USERS TABLE (Admin & Employee Accounts)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'employee') DEFAULT 'employee',
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    status ENUM('active', 'inactive') DEFAULT 'active',
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin account (password: admin123)
INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES
('admin', '$2y$12$nlNmKVZzUCrvRyopjC9VmePzIetArCFrKaTA7FIn6OVjzINOiWQ8S', 'System Administrator', 'admin@jftech.com', '09123456789', 'admin', 'active');

-- =====================================================
-- 3. CLIENTS TABLE (WiFi Clients)
-- =====================================================
CREATE TABLE IF NOT EXISTS clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_code VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    barangay_id INT,
    exact_address TEXT,
    latitude DECIMAL(10,8) DEFAULT 0,
    longitude DECIMAL(11,8) DEFAULT 0,
    marker_color VARCHAR(20) DEFAULT '#00BFFF',
    wifi_plan VARCHAR(50),
    monthly_rate DECIMAL(10,2) DEFAULT 0.00,
    installation_date DATE,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL
);

-- =====================================================
-- 4. WIFI RATES / PLANS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS wifi_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(100) NOT NULL,
    speed VARCHAR(50),
    monthly_price DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO wifi_rates (plan_name, speed, monthly_price, description) VALUES
('Basic Plan', '5 Mbps', 500.00, 'Entry-level WiFi for light browsing'),
('Standard Plan', '10 Mbps', 800.00, 'Perfect for streaming and work'),
('Premium Plan', '20 Mbps', 1200.00, 'High-speed for gaming and 4K'),
('Ultra Plan', '50 Mbps', 2000.00, 'Fiber-speed for heavy users'),
('Business Plan', '100 Mbps', 3500.00, 'Dedicated for business operations');

-- =====================================================
-- 5. BILLS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    bill_number VARCHAR(50) NOT NULL UNIQUE,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- =====================================================
-- 6. PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    bill_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer', 'other') DEFAULT 'cash',
    reference_number VARCHAR(100),
    notes TEXT,
    received_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 7. EMPLOYEES TABLE
-- =====================================================
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

-- =====================================================
-- 8. ATTENDANCE TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    morning_time_in TIME,
    morning_time_out TIME,
    afternoon_time_in TIME,
    afternoon_time_out TIME,
    status_morning ENUM('present', 'absent', 'late', 'halfday') DEFAULT 'absent',
    status_afternoon ENUM('present', 'absent', 'late', 'halfday') DEFAULT 'absent',
    qr_code_used VARCHAR(100),
    total_hours DECIMAL(5,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- =====================================================
-- 9. QR CODES TABLE (For Attendance)
-- =====================================================
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

-- =====================================================
-- 10. PAYROLL TABLE
-- =====================================================
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

-- =====================================================
-- 11. INVENTORY TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    sku VARCHAR(100) DEFAULT NULL,
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

INSERT IGNORE INTO inventory (item_name, category, quantity, unit_price, description, supplier, reorder_level, status) VALUES
('Router', 'Networking Equipment', 50, 500.00, 'Wireless router', 'Supplier A', 10, 'active'),
('UPS', 'Power Equipment', 30, 750.00, 'Uninterruptible Power Supply', 'Supplier B', 5, 'active'),
('Patch Cord', 'Cabling', 200, 50.00, 'Fiber optic patch cord', 'Supplier C', 20, 'active'),
('Cable Tie', 'Accessories', 1000, 2.00, 'Plastic cable tie', 'Supplier C', 100, 'active'),
('Pigtail', 'Cabling', 100, 25.00, 'Fiber pigtail connector', 'Supplier C', 20, 'active'),
('Cable Clamp', 'Accessories', 200, 10.00, 'Cable clamp holder', 'Supplier C', 30, 'active');

-- =====================================================
-- 12. INVENTORY REQUESTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    employee_name VARCHAR(100) NOT NULL,
    inventory_id INT NULL,
    item_name VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    comments TEXT,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 12. LOGIN HISTORY TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS login_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(50) NOT NULL,
    full_name VARCHAR(100),
    role VARCHAR(20),
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    status ENUM('success', 'failed') DEFAULT 'success',
    error_message VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 13. SYSTEM SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_label VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, setting_value, setting_label) VALUES
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

-- =====================================================
-- 14. PRICES TABLE (Product Pricing)
-- =====================================================
CREATE TABLE IF NOT EXISTS prices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_type ENUM('none', 'percentage', 'fixed') DEFAULT 'none',
    discount_value DECIMAL(10,2) DEFAULT 0.00,
    final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
    effective_from DATE NOT NULL,
    effective_until DATE,
    status ENUM('draft', 'active', 'scheduled', 'inactive', 'expired') DEFAULT 'draft',
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_prices_product_id ON prices(product_id);
CREATE INDEX idx_prices_status ON prices(status);
CREATE INDEX idx_prices_effective_from ON prices(effective_from);

-- =====================================================
-- 15. PRICE HISTORIES TABLE
-- =====================================================
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

CREATE INDEX idx_price_histories_product_id ON price_histories(product_id);
CREATE INDEX idx_price_histories_changed_at ON price_histories(changed_at);

-- =====================================================
-- 16. PRICE AUDIT LOGS TABLE
-- =====================================================
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

CREATE INDEX idx_price_audit_logs_action ON price_audit_logs(action);
CREATE INDEX idx_price_audit_logs_created_at ON price_audit_logs(created_at);

-- =====================================================
-- END OF DATABASE SCHEMA
-- =====================================================
